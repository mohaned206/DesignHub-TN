<?php
require_once __DIR__ . '/includes/functions.php';
$user = require_login();
$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $stmt = db()->prepare('SELECT password_hash FROM users WHERE id = ?');
    $stmt->execute([$user['id']]);
    $hash = $stmt->fetchColumn();
    $old = $_POST['old'] ?? ''; $new = $_POST['new'] ?? ''; $conf = $_POST['confirm'] ?? '';
    if (!password_verify($old, $hash)) $errors[] = 'Mot de passe actuel incorrect.';
    if (strlen($new) < 8)              $errors[] = '8 caractères minimum.';
    if ($new !== $conf)                $errors[] = 'Les deux mots de passe sont différents.';
    if (!$errors) {
        db()->prepare('UPDATE users SET password_hash = ? WHERE id = ?')
            ->execute([password_hash($new, PASSWORD_DEFAULT), $user['id']]);
        session_regenerate_id(true);
        set_flash('success', 'Mot de passe modifié.');
        redirect('dashboard.php');
    }
}
// puis header.php, un formulaire (3 champs type="password" + csrf_field()), footer.php