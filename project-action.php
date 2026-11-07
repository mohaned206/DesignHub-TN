<?php
// project-action.php : traite toutes les actions (formulaires POST) liées aux projets.
// Actions : invite, respond, complete, cancel, review, propose, proposal_answer, add_task, task_status, delete_task, send_message
// Principe : on vérifie TOUJOURS côté serveur qui a le droit de faire quoi (rôle + propriétaire du projet + statut).

require_once __DIR__ . '/includes/functions.php';

csrf_check();                       // POST obligatoire + jeton CSRF valide
$user   = require_login();
$action = $_POST['action'] ?? '';

// Adresse de retour : on n'accepte que "fichier.php?parametres" (évite les redirections vers un autre site)
$back = $_POST['back'] ?? 'projects.php';
if (!preg_match('#^[a-z-]+\.php(\?[a-zA-Z0-9=&_-]*)?(\#[a-z-]+)?$#', $back)) {
    $back = 'projects.php';
}

// Termine l'action : message + retour à la page d'origine
function done(string $type, string $message, string $back): void
{
    set_flash($type, $message);
    redirect($back);
}

// Retourne le projet demandé s'il appartient au client connecté, sinon null
function own_project(int $projectId, int $clientId): ?array
{
    $stmt = db()->prepare('SELECT * FROM projects WHERE id = ? AND client_id = ?');
    $stmt->execute([$projectId, $clientId]);
    return $stmt->fetch() ?: null;
}

$pdo = db();

switch ($action) {

    // ---------- Le client invite un designer sur un projet ouvert ----------
    case 'invite':
        require_role('client');
        $project = own_project((int) ($_POST['project_id'] ?? 0), $user['id']);
        $designerId = (int) ($_POST['designer_id'] ?? 0);

        $stmt = $pdo->prepare("SELECT id FROM users WHERE id = ? AND role = 'designer'");
        $stmt->execute([$designerId]);

        if (!$project || $project['status'] !== 'ouvert' || !$stmt->fetch()) {
            done('error', 'Invitation impossible (projet ou designer invalide).', $back);
        }
        // INSERT IGNORE : la contrainte UNIQUE empêche d'inviter deux fois le même designer
        $stmt = $pdo->prepare('INSERT IGNORE INTO project_invitations (project_id, designer_id) VALUES (?, ?)');
        $stmt->execute([$project['id'], $designerId]);
        if ($stmt->rowCount() === 0) {
            done('error', 'Ce designer a déjà été invité sur ce projet.', $back);
        }
        done('success', 'Invitation envoyée.', $back);
        break;

    // ---------- Le designer accepte ou refuse une invitation ----------
    case 'respond':
        require_role('designer');
        $answer = $_POST['answer'] ?? '';
        $stmt = $pdo->prepare("SELECT i.id, i.project_id FROM project_invitations i
                               JOIN projects p ON p.id = i.project_id
                               WHERE i.id = ? AND i.designer_id = ? AND i.status = 'en_attente' AND p.status = 'ouvert'");
        $stmt->execute([(int) ($_POST['invitation_id'] ?? 0), $user['id']]);
        $inv = $stmt->fetch();

        if (!$inv || !in_array($answer, ['accept', 'refuse'], true)) {
            done('error', 'Invitation introuvable ou déjà traitée.', $back);
        }

        if ($answer === 'refuse') {
            $pdo->prepare("UPDATE project_invitations SET status = 'refusee' WHERE id = ?")->execute([$inv['id']]);
            done('success', 'Invitation refusée.', $back);
        }

        // Acceptation : plusieurs modifications liées -> transaction (tout ou rien)
        $pdo->beginTransaction();
        // "AND status = 'ouvert'" : si un autre designer a accepté entre-temps, rien n'est modifié
        $stmt = $pdo->prepare("UPDATE projects SET status = 'en_cours', designer_id = ? WHERE id = ? AND status = 'ouvert'");
        $stmt->execute([$user['id'], $inv['project_id']]);
        if ($stmt->rowCount() === 0) {
            $pdo->rollBack();
            done('error', 'Ce projet n\'est plus disponible.', $back);
        }
        $pdo->prepare("UPDATE project_invitations SET status = 'acceptee' WHERE id = ?")->execute([$inv['id']]);
        // les autres invitations en attente du même projet n'ont plus de raison d'être
        $pdo->prepare("UPDATE project_invitations SET status = 'annulee' WHERE project_id = ? AND status = 'en_attente'")->execute([$inv['project_id']]);
        $pdo->prepare("UPDATE proposals SET status = 'refusee' WHERE project_id = ? AND status = 'en_attente'")->execute([$inv['project_id']]);
        $pdo->commit();
        done('success', 'Invitation acceptée : le projet est maintenant en cours.', 'project-details.php?id=' . (int) $inv['project_id']);
        break;

    // ---------- Le client marque son projet comme terminé ----------
    case 'complete':
        require_role('client');
        $project = own_project((int) ($_POST['project_id'] ?? 0), $user['id']);
        if (!$project || $project['status'] !== 'en_cours') {
            done('error', 'Seul un projet en cours peut être terminé.', $back);
        }
        $pdo->prepare("UPDATE projects SET status = 'termine' WHERE id = ?")->execute([$project['id']]);
        done('success', 'Projet terminé. Vous pouvez maintenant évaluer le designer.', $back);
        break;

    // ---------- Le client annule un projet encore ouvert ----------
    case 'cancel':
        require_role('client');
        $project = own_project((int) ($_POST['project_id'] ?? 0), $user['id']);
        if (!$project || $project['status'] !== 'ouvert') {
            done('error', 'Seul un projet ouvert peut être annulé.', $back);
        }
        $pdo->beginTransaction();
        $pdo->prepare("UPDATE projects SET status = 'annule' WHERE id = ?")->execute([$project['id']]);
        $pdo->prepare("UPDATE project_invitations SET status = 'annulee' WHERE project_id = ? AND status = 'en_attente'")->execute([$project['id']]);
        $pdo->prepare("UPDATE proposals SET status = 'annulee' WHERE project_id = ? AND status = 'en_attente'")->execute([$project['id']]);
        $pdo->commit();
        done('success', 'Projet annulé.', $back);
        break;

    // ---------- Le client évalue le designer d'un projet terminé ----------
    case 'review':
        require_role('client');
        $project = own_project((int) ($_POST['project_id'] ?? 0), $user['id']);
        $rating  = (int) ($_POST['rating'] ?? 0);
        $comment = trim($_POST['comment'] ?? '');

        if (!$project || $project['status'] !== 'termine' || !$project['designer_id']) {
            done('error', 'Vous ne pouvez évaluer qu\'un projet terminé.', $back);
        }
        if ($rating < 1 || $rating > 5 || mb_strlen($comment) > 500) {
            done('error', 'Note (1 à 5) ou commentaire (500 caractères max) invalide.', $back);
        }
        // UNIQUE(project_id) : une seule évaluation par projet
        $stmt = $pdo->prepare('INSERT IGNORE INTO reviews (project_id, client_id, designer_id, rating, comment) VALUES (?, ?, ?, ?, ?)');
        $stmt->execute([$project['id'], $user['id'], $project['designer_id'], $rating, $comment]);
        if ($stmt->rowCount() === 0) {
            done('error', 'Ce projet a déjà été évalué.', $back);
        }
        done('success', 'Merci, votre évaluation a été enregistrée.', $back);
        break;

    // ---------- Le designer se propose sur un projet ouvert (prix, délai, message) ----------
    case 'propose':
        require_role('designer');
        $stmt = $pdo->prepare("SELECT id FROM projects WHERE id = ? AND status = 'ouvert'");
        $stmt->execute([(int) ($_POST['project_id'] ?? 0)]);
        $projectId = $stmt->fetchColumn();
        $price   = $_POST['price'] ?? '';
        $delay   = $_POST['delay'] ?? '';
        $message = trim($_POST['message'] ?? '');

        if (!$projectId) {
            done('error', 'Ce projet n\'est plus ouvert.', $back);
        }
        if (!is_numeric($price) || $price <= 0 || $price > 1000000 || !ctype_digit((string) $delay) || $delay < 1 || $delay > 365
            || mb_strlen($message) < 10 || mb_strlen($message) > 600) {
            done('error', 'Proposition invalide : prix positif, délai de 1 à 365 jours, message de 10 à 600 caractères.', $back);
        }
        // UNIQUE(project_id, designer_id) : une seule proposition par projet
        $stmt = $pdo->prepare('INSERT IGNORE INTO proposals (project_id, designer_id, price, delay_days, message) VALUES (?, ?, ?, ?, ?)');
        $stmt->execute([$projectId, $user['id'], $price, $delay, $message]);
        if ($stmt->rowCount() === 0) {
            done('error', 'Vous avez déjà envoyé une proposition pour ce projet.', $back);
        }
        done('success', 'Proposition envoyée au client.', $back);
        break;

    // ---------- Le client accepte ou refuse une proposition reçue ----------
    case 'proposal_answer':
        require_role('client');
        $answer = $_POST['answer'] ?? '';
        $stmt = $pdo->prepare("SELECT pr.* FROM proposals pr
                               JOIN projects p ON p.id = pr.project_id
                               WHERE pr.id = ? AND p.client_id = ? AND pr.status = 'en_attente' AND p.status = 'ouvert'");
        $stmt->execute([(int) ($_POST['proposal_id'] ?? 0), $user['id']]);
        $proposal = $stmt->fetch();

        if (!$proposal || !in_array($answer, ['accept', 'refuse'], true)) {
            done('error', 'Proposition introuvable ou déjà traitée.', $back);
        }
        if ($answer === 'refuse') {
            $pdo->prepare("UPDATE proposals SET status = 'refusee' WHERE id = ?")->execute([$proposal['id']]);
            done('success', 'Proposition refusée.', $back);
        }

        // Acceptation : le projet passe « en cours » avec le prix et le délai convenus (transaction)
        $pdo->beginTransaction();
        $stmt = $pdo->prepare("UPDATE projects SET status = 'en_cours', designer_id = ?, budget = ?, deadline_days = ?
                               WHERE id = ? AND status = 'ouvert'");
        $stmt->execute([$proposal['designer_id'], $proposal['price'], $proposal['delay_days'], $proposal['project_id']]);
        if ($stmt->rowCount() === 0) {
            $pdo->rollBack();
            done('error', 'Ce projet n\'est plus disponible.', $back);
        }
        $pdo->prepare("UPDATE proposals SET status = 'acceptee' WHERE id = ?")->execute([$proposal['id']]);
        $pdo->prepare("UPDATE proposals SET status = 'refusee' WHERE project_id = ? AND status = 'en_attente'")->execute([$proposal['project_id']]);
        $pdo->prepare("UPDATE project_invitations SET status = 'annulee' WHERE project_id = ? AND status = 'en_attente'")->execute([$proposal['project_id']]);
        $pdo->commit();
        done('success', 'Proposition acceptée : le projet est en cours. Vous pouvez définir les tâches et échanger avec le designer.',
             'project-details.php?id=' . (int) $proposal['project_id'] . '#espace');
        break;

    // ---------- Collaboration : tâches et messages (client ou designer du projet) ----------
    case 'add_task':
    case 'task_status':
    case 'delete_task':
    case 'send_message':
        $stmt = $pdo->prepare('SELECT * FROM projects WHERE id = ?');
        $stmt->execute([(int) ($_POST['project_id'] ?? 0)]);
        $project = $stmt->fetch();
        if (!$project || !is_project_member($project, $user)) {
            done('error', 'Action non autorisée sur ce projet.', $back);
        }

        if ($action === 'send_message') {
            $body = trim($_POST['body'] ?? '');
            if (!in_array($project['status'], ['en_cours', 'termine'], true) || $body === '' || mb_strlen($body) > 1000) {
                done('error', 'Message vide, trop long (1000 caractères max) ou projet non actif.', $back);
            }
            $pdo->prepare('INSERT INTO project_messages (project_id, user_id, body) VALUES (?, ?, ?)')
                ->execute([$project['id'], $user['id'], $body]);
            done('success', 'Message envoyé.', $back);
        }

        // Les tâches ne se modifient que pendant l'exécution du projet
        if ($project['status'] !== 'en_cours') {
            done('error', 'Les tâches ne peuvent être modifiées que sur un projet en cours.', $back);
        }

        if ($action === 'add_task') {
            $title = trim($_POST['title'] ?? '');
            if (mb_strlen($title) < 3 || mb_strlen($title) > 150) {
                done('error', 'Le titre de la tâche doit contenir entre 3 et 150 caractères.', $back);
            }
            $pdo->prepare('INSERT INTO project_tasks (project_id, title, created_by) VALUES (?, ?, ?)')
                ->execute([$project['id'], $title, $user['id']]);
            done('success', 'Tâche ajoutée.', $back);
        }

        // task_status et delete_task : la tâche doit appartenir à CE projet
        $taskId = (int) ($_POST['task_id'] ?? 0);
        if ($action === 'task_status') {
            $status = $_POST['status'] ?? '';
            if (!array_key_exists($status, TASK_STATUS)) {
                done('error', 'Statut de tâche invalide.', $back);
            }
            $pdo->prepare('UPDATE project_tasks SET status = ? WHERE id = ? AND project_id = ?')
                ->execute([$status, $taskId, $project['id']]);
            done('success', 'Tâche mise à jour.', $back);
        }
        $pdo->prepare('DELETE FROM project_tasks WHERE id = ? AND project_id = ?')->execute([$taskId, $project['id']]);
        done('success', 'Tâche supprimée.', $back);
        break;

    default:
        done('error', 'Action inconnue.', $back);
}
