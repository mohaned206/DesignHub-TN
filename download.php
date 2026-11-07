<?php
// download.php?id=3 : téléchargement du fichier source d'une ressource.
// Autorisé uniquement pour l'auteur et pour les utilisateurs qui ont obtenu la ressource (table orders).
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/upload.php';

$user = require_login();

$stmt = db()->prepare('SELECT * FROM resources WHERE id = ?');
$stmt->execute([(int) ($_GET['id'] ?? 0)]);
$res = $stmt->fetch();

$allowed = false;
if ($res) {
    $allowed = $res['author_id'] === $user['id'];
    if (!$allowed) {
        $stmt = db()->prepare('SELECT COUNT(*) FROM orders WHERE resource_id = ? AND buyer_id = ?');
        $stmt->execute([$res['id'], $user['id']]);
        $allowed = (int) $stmt->fetchColumn() > 0;
    }
}

$path = $res && $res['file_path'] ? SOURCE_DIR . basename($res['file_path']) : '';
if (!$res || !$allowed || !is_file($path)) {
    set_flash('error', !$res ? 'Ressource introuvable.' : (!$allowed ? 'Obtenez d\'abord cette ressource pour la télécharger.' : 'Aucun fichier disponible pour cette ressource.'));
    redirect('marketplace.php');
}

$downloadName = $res['file_name'] ?: basename($path);
while (ob_get_level() > 0) {
    ob_end_clean();
}
header('Content-Type: application/octet-stream');
header('Content-Disposition: attachment; filename="' . str_replace('"', '', $downloadName) . '"');
header('Content-Length: ' . filesize($path));
header('X-Content-Type-Options: nosniff');
readfile($path);
exit;
