<?php
// projects.php : liste des projets + création d'une demande (client)
require_once __DIR__ . '/includes/functions.php';

$user = require_login();
$errors = [];
$old = ['title' => '', 'category' => CATEGORIES[0], 'skills' => '', 'budget' => '', 'deadline' => '', 'description' => ''];

// ---------- Création d'un projet (réservée aux clients) ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    require_role('client');

    $old['title']       = trim($_POST['title'] ?? '');
    $old['category']    = $_POST['category'] ?? '';
    $old['skills']      = trim($_POST['skills'] ?? '');
    $old['budget']      = trim($_POST['budget'] ?? '');
    $old['deadline']    = trim($_POST['deadline'] ?? '');
    $old['description'] = trim($_POST['description'] ?? '');

    if (mb_strlen($old['title']) < 5 || mb_strlen($old['title']) > 150) {
        $errors[] = 'Le titre doit contenir entre 5 et 150 caractères.';
    }
    if (!in_array($old['category'], CATEGORIES, true)) {
        $errors[] = 'Catégorie invalide.';
    }
    if (!is_numeric($old['budget']) || $old['budget'] <= 0 || $old['budget'] > 1000000) {
        $errors[] = 'Le budget doit être un nombre positif (en TND).';
    }
    if (!ctype_digit($old['deadline']) || $old['deadline'] < 1 || $old['deadline'] > 365) {
        $errors[] = 'Le délai doit être un nombre de jours entre 1 et 365.';
    }
    if (mb_strlen($old['description']) < 20 || mb_strlen($old['description']) > 2000) {
        $errors[] = 'La description doit contenir entre 20 et 2000 caractères.';
    }

    if (!$errors) {
        $stmt = db()->prepare('INSERT INTO projects (client_id, title, category, skills, budget, deadline_days, description)
                               VALUES (?, ?, ?, ?, ?, ?, ?)');
        $stmt->execute([$user['id'], $old['title'], $old['category'], clean_skills($old['skills']),
                        $old['budget'], $old['deadline'], $old['description']]);
        set_flash('success', 'Projet publié. Utilisez le matching pour trouver un designer.');
        redirect('project-details.php?id=' . db()->lastInsertId());
    }
}

// ---------- Données à afficher ----------
if ($user['role'] === 'client') {
    $stmt = db()->prepare('SELECT p.*, d.full_name AS designer_name FROM projects p
                           LEFT JOIN users d ON d.id = p.designer_id
                           WHERE p.client_id = ? ORDER BY p.created_at DESC, p.id DESC');
    $stmt->execute([$user['id']]);
    $myProjects = $stmt->fetchAll();
} else {
    // Designer : ses projets (assignés) et les projets ouverts
    $stmt = db()->prepare('SELECT p.*, c.full_name AS client_name FROM projects p
                           JOIN users c ON c.id = p.client_id
                           WHERE p.designer_id = ? ORDER BY p.created_at DESC');
    $stmt->execute([$user['id']]);
    $myProjects = $stmt->fetchAll();

    $openProjects = db()->query("SELECT p.*, c.full_name AS client_name FROM projects p
                                 JOIN users c ON c.id = p.client_id
                                 WHERE p.status = 'ouvert' ORDER BY p.created_at DESC")->fetchAll();
}

$page_title = 'Projets';
$active = 'projects';
require __DIR__ . '/includes/header.php';

// Petite fonction locale : ligne de projet
function project_row(array $p, string $otherLabel, ?string $otherName): string
{
    return '<a class="card project-row" href="project-details.php?id=' . (int) $p['id'] . '">
        <div>
            <span class="tag">' . e($p['category']) . '</span>
            <h3>' . e($p['title']) . '</h3>
            <p class="small muted">Budget : ' . e(number_format($p['budget'], 0)) . ' TND · Délai : ' . (int) $p['deadline_days'] . ' jours'
            . ($otherName ? ' · ' . e($otherLabel) . ' : ' . e($otherName) : '') . '</p>
        </div>' . status_badge($p['status']) . '</a>';
}
?>
<h1>Projets</h1>

<?php if ($user['role'] === 'client'): ?>
    <details class="card" <?= $errors ? 'open' : '' ?>>
        <summary class="btn">+ Publier un nouveau projet</summary>
        <?php if ($errors): ?>
            <div class="alert alert-error"><?php foreach ($errors as $err): ?><div><?= e($err) ?></div><?php endforeach; ?></div>
        <?php endif; ?>
        <form method="post" novalidate>
            <?= csrf_field() ?>
            <label>Titre du projet
                <input type="text" name="title" value="<?= e($old['title']) ?>" maxlength="150" required>
            </label>
            <div class="form-row">
                <label>Catégorie
                    <select name="category">
                        <?php foreach (CATEGORIES as $c): ?>
                            <option value="<?= e($c) ?>" <?= $old['category'] === $c ? 'selected' : '' ?>><?= e($c) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label>Budget (TND)
                    <input type="number" name="budget" min="1" step="1" value="<?= e($old['budget']) ?>" required>
                </label>
                <label>Délai (jours)
                    <input type="number" name="deadline" min="1" max="365" value="<?= e($old['deadline']) ?>" required>
                </label>
            </div>
            <label>Compétences recherchées (séparées par des virgules)
                <input type="text" name="skills" value="<?= e($old['skills']) ?>" placeholder="ex : figma, prototypage, logo" maxlength="255">
            </label>
            <label>Description
                <textarea name="description" rows="4" maxlength="2000" required><?= e($old['description']) ?></textarea>
            </label>
            <button class="btn" type="submit">Publier le projet</button>
        </form>
    </details>

    <h2>Mes projets (<?= count($myProjects) ?>)</h2>
    <?php if (!$myProjects): ?><p class="empty">Vous n'avez pas encore publié de projet.</p><?php endif; ?>
    <?php foreach ($myProjects as $p) { echo project_row($p, 'Designer', $p['designer_name']); } ?>

<?php else: ?>
    <h2>Mes projets en cours ou terminés (<?= count($myProjects) ?>)</h2>
    <?php if (!$myProjects): ?><p class="empty">Aucun projet ne vous est encore attribué.</p><?php endif; ?>
    <?php foreach ($myProjects as $p) { echo project_row($p, 'Client', $p['client_name']); } ?>

    <h2>Projets ouverts (<?= count($openProjects) ?>)</h2>
    <p class="muted small">Seul un client peut vous inviter sur un projet ; vous pourrez alors accepter ou refuser.</p>
    <?php if (!$openProjects): ?><p class="empty">Aucun projet ouvert.</p><?php endif; ?>
    <?php foreach ($openProjects as $p) { echo project_row($p, 'Client', $p['client_name']); } ?>
<?php endif; ?>
<?php require __DIR__ . '/includes/footer.php'; ?>
