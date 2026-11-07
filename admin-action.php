<?php
// admin-action.php : suppressions de modération (POST uniquement, jeton CSRF, rôle admin vérifié côté serveur).
// Chaque suppression exige un motif et est enregistrée dans la table admin_logs.
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/upload.php';

$admin = require_admin();
csrf_check();
$pdo = db();

$type   = $_POST['target_type'] ?? '';
$id     = (int) ($_POST['target_id'] ?? 0);
$reason = trim($_POST['reason'] ?? '');

// Retour : uniquement vers une page admin.php (jamais une adresse choisie par le navigateur)
$back = $_POST['back'] ?? 'admin.php';
if (!preg_match('#^admin\.php(\?[a-zA-Z0-9=&_%+.-]*)?$#', $back)) {
    $back = 'admin.php';
}

if (mb_strlen($reason) < 5 || mb_strlen($reason) > 300) {
    set_flash('error', 'Indiquez un motif de 5 à 300 caractères pour justifier la suppression.');
    redirect($back);
}

$files = [];   // fichiers à effacer du disque après la suppression en base
$label = '';
$action = '';

try {
    $pdo->beginTransaction();

    if ($type === 'user') {
        $stmt = $pdo->prepare('SELECT id, full_name, email, role FROM users WHERE id = ?');
        $stmt->execute([$id]);
        $target = $stmt->fetch();
        if (!$target) { throw new RuntimeException('Utilisateur introuvable.'); }
        if ($target['role'] === 'admin' || $target['id'] === $admin['id']) { throw new RuntimeException('Un compte administrateur ne peut pas être supprimé.'); }

        // Fichiers liés : ressources, portfolio, photo de profil
        $stmt = $pdo->prepare('SELECT image_url, file_path FROM resources WHERE author_id = ?');
        $stmt->execute([$id]);
        $files = $stmt->fetchAll();
        $stmt = $pdo->prepare('SELECT image_url FROM portfolio_items WHERE designer_id = ?');
        $stmt->execute([$id]);
        foreach ($stmt->fetchAll() as $row) { $files[] = ['image_url' => $row['image_url']]; }
        $stmt = $pdo->prepare('SELECT avatar_url FROM designer_profiles WHERE user_id = ?');
        $stmt->execute([$id]);
        if ($av = $stmt->fetchColumn()) { $files[] = ['image_url' => $av]; }

        // Les projets en cours d'un designer supprimé redeviennent ouverts (le client peut en choisir un autre)
        $pdo->prepare("UPDATE projects SET status = 'ouvert' WHERE designer_id = ? AND status = 'en_cours'")->execute([$id]);
        $pdo->prepare('DELETE FROM users WHERE id = ?')->execute([$id]);   // les tables liées suivent (ON DELETE CASCADE)
        $label  = $target['full_name'] . ' (' . $target['email'] . ')';
        $action = 'delete_user';

    } elseif ($type === 'project') {
        $stmt = $pdo->prepare('SELECT id, title FROM projects WHERE id = ?');
        $stmt->execute([$id]);
        $target = $stmt->fetch();
        if (!$target) { throw new RuntimeException('Projet introuvable.'); }
        $pdo->prepare('DELETE FROM projects WHERE id = ?')->execute([$id]);
        $label  = $target['title'];
        $action = 'delete_project';

    } elseif ($type === 'resource') {
        $stmt = $pdo->prepare('SELECT * FROM resources WHERE id = ?');
        $stmt->execute([$id]);
        $target = $stmt->fetch();
        if (!$target) { throw new RuntimeException('Ressource introuvable.'); }
        $files[] = $target;
        $pdo->prepare('DELETE FROM resources WHERE id = ?')->execute([$id]);
        $label  = $target['title'];
        $action = 'delete_resource';

    } elseif ($type === 'review') {
        $stmt = $pdo->prepare('SELECT r.id, r.rating, c.full_name AS client_name, d.full_name AS designer_name
                               FROM reviews r JOIN users c ON c.id = r.client_id JOIN users d ON d.id = r.designer_id WHERE r.id = ?');
        $stmt->execute([$id]);
        $target = $stmt->fetch();
        if (!$target) { throw new RuntimeException('Avis introuvable.'); }
        $pdo->prepare('DELETE FROM reviews WHERE id = ?')->execute([$id]);
        $label  = $target['client_name'] . ' → ' . $target['designer_name'] . ' (' . $target['rating'] . '/5)';
        $action = 'delete_review';

    } else {
        throw new RuntimeException('Action inconnue.');
    }

    $pdo->prepare('INSERT INTO admin_logs (admin_id, action, target_type, target_id, target_label, reason) VALUES (?, ?, ?, ?, ?, ?)')
        ->execute([$admin['id'], $action, $type, $id, mb_substr($label, 0, 190), $reason]);
    $pdo->commit();
} catch (RuntimeException $e) {
    $pdo->rollBack();
    set_flash('error', $e->getMessage());
    redirect($back);
}

// Suppression des fichiers (après la validation en base) : uniquement ceux du dossier uploads/
foreach ($files as $f) {
    delete_resource_files($f);
}
set_flash('success', 'Suppression effectuée et enregistrée au journal.');
redirect($back);
