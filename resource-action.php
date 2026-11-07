<?php
// resource-action.php : obtenir une ressource du marketplace (SIMULATION : aucun paiement réel).
require_once __DIR__ . '/includes/functions.php';

csrf_check();
$user = require_login();

$back = $_POST['back'] ?? 'marketplace.php';
if (!preg_match('#^[a-z-]+\.php(\?[a-zA-Z0-9=&_%+.-]*)?$#', $back)) {
    $back = 'marketplace.php';
}

$stmt = db()->prepare('SELECT id, author_id, title, price FROM resources WHERE id = ?');
$stmt->execute([(int) ($_POST['resource_id'] ?? 0)]);
$resource = $stmt->fetch();

if (!$resource) {
    set_flash('error', 'Ressource introuvable.');
    redirect($back);
}
if ($resource['author_id'] === $user['id']) {
    set_flash('error', 'Vous êtes l\'auteur de cette ressource.');
    redirect($back);
}

// UNIQUE(resource_id, buyer_id) : on ne peut obtenir une ressource qu'une seule fois
$stmt = db()->prepare('INSERT IGNORE INTO orders (resource_id, buyer_id, price) VALUES (?, ?, ?)');
$stmt->execute([$resource['id'], $user['id'], $resource['price']]);

if ($stmt->rowCount() === 0) {
    set_flash('error', 'Vous avez déjà obtenu cette ressource.');
} else {
    set_flash('success', 'Commande enregistrée : « ' . $resource['title'] . ' ». Vous pouvez la télécharger depuis le marketplace ou « Mon espace ».');
}
redirect($back);
