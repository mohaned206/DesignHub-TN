<?php
// includes/header.php : en-tête commun (menu). Chaque page définit avant : $page_title et $active.
require_once __DIR__ . '/functions.php';
$user   = current_user();
$flash  = get_flash();
$active = $active ?? '';
$page_title = $page_title ?? 'DesignHub-TN';

// Liste des liens du menu : [fichier, texte]
$menu = [
    'index'       => ['index.php', 'Accueil'],
    'designers'   => ['designers.php', 'Designers'],
    'marketplace' => ['marketplace.php', 'Marketplace'],
    'projects'    => ['projects.php', 'Projets'],
    'matching'    => ['matching.php', 'Matching'],
    'trends'      => ['trends.php', 'Tendances'],
    'assistant'   => ['assistant.php', 'Assistant portfolio'],
];
if ($user && $user['role'] === 'admin') {
    unset($menu['matching'], $menu['assistant']);   // fonctions réservées aux clients et designers
    $menu['admin'] = ['admin.php', 'Administration'];
}
$firstName = $user ? explode(' ', trim($user['full_name']))[0] : '';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($page_title) ?> | DesignHub-TN</title>
    <link rel="preload" href="fonts/inter-latin-wght-normal.woff2" as="font" type="font/woff2" crossorigin>
    <link rel="preload" href="fonts/bricolage-grotesque-latin-wght-normal.woff2" as="font" type="font/woff2" crossorigin>
    <link rel="stylesheet" href="css/style.css?v=5">
    <link rel="icon" type="image/png" href="images/favicon.png">
    <script>document.documentElement.classList.add('js');</script>
</head>
<body>
<header class="site-header">
    <div class="container header-inner">
        <a class="logo" href="index.php"><img class="logo-img" src="images/logo.png" alt="Logo DesignHub-TN" height="44"><span>DesignHub<span class="accent">-TN</span></span></a>

        <button class="menu-toggle" type="button" aria-label="Ouvrir le menu"><?= icon('menu') ?></button>

        <nav class="main-nav" id="main-nav">
            <?php foreach ($menu as $key => [$file, $label]): ?>
                <a href="<?= e($file) ?>" class="<?= $active === $key ? 'active' : '' ?>"><?= e($label) ?></a>
            <?php endforeach; ?>
        </nav>

        <div class="auth-nav">
            <?php if ($user): ?>
                <a class="user-chip" href="<?= e(home_url($user)) ?>" title="Mon espace">
                    <?= avatar_html($user['full_name'], $user['avatar_url'], 'md') ?><span class="nm"><?= e($firstName) ?></span>
                </a>
                <form method="post" action="logout.php" class="inline-form">
                    <?= csrf_field() ?>
                    <button class="btn btn-small btn-ghost" type="submit"><?= icon('logout') ?>Déconnexion</button>
                </form>
            <?php else: ?>
                <a class="btn btn-small btn-ghost" href="login.php">Connexion</a>
                <a class="btn btn-small" href="register.php">S'inscrire</a>
            <?php endif; ?>
        </div>
    </div>
</header>

<main class="container page">
<?php if ($flash): ?>
    <div class="alert alert-<?= e($flash['type']) ?>"><?= e($flash['message']) ?></div>
<?php endif; ?>
