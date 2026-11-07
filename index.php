<?php
// index.php : page d'accueil
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/trends.php';

// Statistiques réelles, calculées depuis la base (aucun chiffre inventé)
$nbDesigners = (int) db()->query("SELECT COUNT(*) FROM users WHERE role = 'designer'")->fetchColumn();
$nbProjects  = (int) db()->query('SELECT COUNT(*) FROM projects')->fetchColumn();
$nbDone      = (int) db()->query("SELECT COUNT(*) FROM projects WHERE status = 'termine'")->fetchColumn();
$nbResources = (int) db()->query('SELECT COUNT(*) FROM resources')->fetchColumn();

// Aperçu des tendances (catégories triées par nombre de projets)
$trends = get_trends();
$topCats = $trends['categories'];
usort($topCats, fn($a, $b) => $b['projects'] <=> $a['projects']);
$topCats = array_slice($topCats, 0, 3);

// Designers à la une : les mieux notés d'abord, puis les plus récents
$featured = db()->query(designers_base_sql() . ' ORDER BY avg_rating DESC, u.id DESC LIMIT 3')->fetchAll();

$page_title = 'Accueil';
$active = 'index';
require __DIR__ . '/includes/header.php';
?>
<div class="home">
<section class="hero">
    <div>
        <span class="pill">Plateforme de mise en relation · Tunisie</span>
        <h1>Connectez les <span class="accent">designers tunisiens</span> avec des projets ambitieux.</h1>
        <p class="lead">Trouvez le designer idéal pour votre identité visuelle, vos maquettes UI/UX, vos illustrations 3D ou vos animations. Publiez un projet, comparez les profils et collaborez.</p>
        <div class="hero-actions">
            <a class="btn btn-sun" href="designers.php">Explorer les designers <?= icon('arrow-right') ?></a>
            <a class="btn btn-light" href="<?= current_user() ? 'projects.php' : 'register.php' ?>">Publier un projet</a>
        </div>
        <div class="hero-trust">
            <span><?= icon('check-circle') ?>Score de matching détaillé</span>
            <span><?= icon('kanban') ?>Suivi d'avancement par tâches</span>
            <span><?= icon('message') ?>Messagerie intégrée</span>
        </div>
    </div>
    <div class="hero-visual" aria-hidden="true">
        <div class="hv hv-1"><img src="images/sections/designers.jpg" alt=""></div>
        <div class="hv hv-2"><img src="images/sections/matching.jpg" alt=""></div>
        <div class="hv hv-3"><img src="images/sections/marketplace.jpg" alt=""></div>
        <div class="hv-badge hv-b1"><?= icon_tile('target', 'clay') ?>Matching expliqué</div>
        <div class="hv-badge hv-b2"><?= icon_tile('palette', 'teal') ?>Portfolios réels</div>
    </div>
</section>

<div class="stats-band reveal">
    <div class="stat-pill"><?= icon_tile('palette', 'blue') ?><div><strong data-count="<?= $nbDesigners ?>"><?= $nbDesigners ?></strong><span class="lbl">designer(s) inscrit(s)</span></div></div>
    <div class="stat-pill"><?= icon_tile('kanban', 'teal') ?><div><strong data-count="<?= $nbProjects ?>"><?= $nbProjects ?></strong><span class="lbl">projet(s) publié(s)</span></div></div>
    <div class="stat-pill"><?= icon_tile('check-circle', 'green') ?><div><strong data-count="<?= $nbDone ?>"><?= $nbDone ?></strong><span class="lbl">projet(s) terminé(s)</span></div></div>
    <div class="stat-pill"><?= icon_tile('bag', 'sun') ?><div><strong data-count="<?= $nbResources ?>"><?= $nbResources ?></strong><span class="lbl">ressource(s) au marketplace</span></div></div>
</div>

<section class="reveal">
    <h2>Comment ça marche ?</h2>
    <div class="steps-home">
        <div class="step-card"><?= icon_tile('edit', 'blue') ?><h3>Décrivez votre besoin</h3><p>Le client publie un projet : catégorie, compétences, budget et délai.</p></div>
        <div class="step-card"><?= icon_tile('target', 'sun') ?><h3>Le matching propose</h3><p>Un score expliqué sur 100 points classe les designers adaptés, ou les designers se proposent.</p></div>
        <div class="step-card"><?= icon_tile('award', 'teal') ?><h3>Collaborez et évaluez</h3><p>Tâches, messages et suivi d'avancement, puis évaluation du designer à la fin.</p></div>
    </div>
</section>

<section>
    <div class="section-head reveal">
        <h2>Les sections de la plateforme</h2>
    </div>
    <div class="sec-grid">
        <?php
        $sections = [
            ['designers.php',   'designers',   'palette', 'blue',  'Designers & portfolios',    'Recherchez par spécialité, ville ou compétence et consultez les réalisations.'],
            ['marketplace.php', 'marketplace', 'bag',     'sun',   'Marketplace',               'Templates, mockups et packs graphiques : filtrez, triez et commandez.'],
            ['projects.php',    'projects',    'kanban',  'teal',  'Projets & collaboration',   'Propositions, tâches, messagerie et suivi jusqu\'à la livraison.'],
            ['matching.php',    'matching',    'target',  'clay',  'Matching intelligent',      'Des profils et des projets classés selon leur compatibilité, avec le détail du score.'],
            ['trends.php',      'trends',      'trend',   'green', 'Tendances',                 'Demande, offre, compétences et tarifs, calculés depuis la plateforme.'],
            ['assistant.php',   'assistant',   'spark',   'navy',  'Assistant portfolio',       'Un score de profil et des conseils pour valoriser votre portfolio.'],
        ];
        foreach ($sections as [$href, $img, $ico, $tone, $title, $text]): ?>
            <a class="sec-card reveal" href="<?= e($href) ?>">
                <div class="sec-media">
                    <div class="sec-img"><img src="images/sections/<?= e($img) ?>.jpg" alt="" loading="lazy" width="1000" height="625"></div>
                    <?= icon_tile($ico, $tone) ?>
                </div>
                <div class="sec-body">
                    <h3><?= e($title) ?></h3>
                    <p><?= e($text) ?></p>
                    <span class="sec-more">Découvrir <?= icon('arrow-right') ?></span>
                </div>
            </a>
        <?php endforeach; ?>
    </div>
</section>

<?php if (array_sum(array_column($topCats, 'projects')) > 0): ?>
<section class="reveal">
    <div class="section-head">
        <h2>Catégories les plus demandées</h2>
        <a href="trends.php">Toutes les tendances <?= icon('arrow-right') ?></a>
    </div>
    <div class="grid grid-3">
        <?php foreach ($topCats as $c): ?>
            <div class="card cat-card">
                <?= icon_tile('layers', 'blue') ?>
                <div>
                    <h3><?= e($c['category']) ?></h3>
                    <p class="small muted" style="margin:.1rem 0 .4rem"><?= (int) $c['projects'] ?> projet(s) · <?= (int) $c['designers'] ?> designer(s)</p>
                    <span class="badge level-<?= e($c['level_key']) ?>"><?= e($c['level']) ?></span>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</section>
<?php endif; ?>

<section class="reveal">
    <div class="section-head">
        <h2>Designers à la une</h2>
        <a href="designers.php">Voir tous les designers <?= icon('arrow-right') ?></a>
    </div>
    <div class="grid grid-3">
        <?php foreach ($featured as $d) { echo designer_card($d); } ?>
        <?php if (!$featured): ?><p class="muted">Aucun designer inscrit pour le moment.</p><?php endif; ?>
    </div>
</section>

<?php if (!current_user()): ?>
<section class="cta-band reveal">
    <div>
        <h2>Prêt à lancer votre prochain projet ?</h2>
        <p>Créez un compte gratuit : publiez un projet en quelques minutes ou présentez votre portfolio aux clients.</p>
    </div>
    <a class="btn btn-sun" href="register.php">Créer un compte <?= icon('arrow-right') ?></a>
</section>
<?php endif; ?>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
