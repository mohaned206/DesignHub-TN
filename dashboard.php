<?php
// dashboard.php : espace personnel. Le contenu dépend du rôle (client ou designer).
// Toutes les statistiques sont calculées à partir de la base de données.
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/matching.php';
require_once __DIR__ . '/includes/workspace.php';

$user = require_login();
if ($user['role'] === 'admin') {
    redirect('admin.php');   // l'administrateur a son propre espace
}
$pdo  = db();

// Ressources obtenues (commandes) : commun aux deux rôles
$stmt = $pdo->prepare('SELECT r.id, r.title, r.category, r.file_path, o.price, o.created_at
                       FROM orders o JOIN resources r ON r.id = o.resource_id
                       WHERE o.buyer_id = ? ORDER BY o.id DESC LIMIT 5');
$stmt->execute([$user['id']]);
$myOrders = $stmt->fetchAll();

if ($user['role'] === 'client') {
    // Nombre de projets par statut : GROUP BY
    $stmt = $pdo->prepare('SELECT status, COUNT(*) AS n FROM projects WHERE client_id = ? GROUP BY status');
    $stmt->execute([$user['id']]);
    $counts = array_column($stmt->fetchAll(), 'n', 'status');

    // Budget engagé : projets en cours ou terminés
    $stmt = $pdo->prepare("SELECT COALESCE(SUM(budget), 0) FROM projects WHERE client_id = ? AND status IN ('en_cours', 'termine')");
    $stmt->execute([$user['id']]);
    $engaged = (float) $stmt->fetchColumn();

    $stmt = $pdo->prepare('SELECT p.id, p.title, p.status, p.category FROM projects p
                           WHERE p.client_id = ? ORDER BY p.created_at DESC, p.id DESC LIMIT 5');
    $stmt->execute([$user['id']]);
    $recent = $stmt->fetchAll();
    $progress = progress_map(array_column($recent, 'id'));

    // Propositions reçues sur les projets ouverts
    $stmt = $pdo->prepare("SELECT pr.price, pr.delay_days, u.id AS designer_id, u.full_name, p.id AS project_id, p.title
                           FROM proposals pr
                           JOIN projects p ON p.id = pr.project_id
                           JOIN users u ON u.id = pr.designer_id
                           WHERE p.client_id = ? AND pr.status = 'en_attente' AND p.status = 'ouvert'
                           ORDER BY pr.created_at DESC, pr.id DESC LIMIT 6");
    $stmt->execute([$user['id']]);
    $proposalsIn = $stmt->fetchAll();

    $stmt = $pdo->prepare('SELECT i.status, u.full_name, u.id AS designer_id, p.title, p.id AS project_id
                           FROM project_invitations i
                           JOIN projects p ON p.id = i.project_id
                           JOIN users u ON u.id = i.designer_id
                           WHERE p.client_id = ? ORDER BY i.created_at DESC, i.id DESC LIMIT 8');
    $stmt->execute([$user['id']]);
    $invitations = $stmt->fetchAll();
} else {
    $stmt = $pdo->prepare('SELECT p.*, (SELECT COUNT(*) FROM portfolio_items WHERE designer_id = p.user_id) AS nb_portfolio,
                                  (SELECT AVG(rating) FROM reviews WHERE designer_id = p.user_id) AS avg_rating,
                                  (SELECT COUNT(*) FROM reviews WHERE designer_id = p.user_id) AS nb_reviews
                           FROM designer_profiles p WHERE p.user_id = ?');
    $stmt->execute([$user['id']]);
    $profile = $stmt->fetch();

    $stmt = $pdo->prepare('SELECT status, COUNT(*) AS n FROM projects WHERE designer_id = ? GROUP BY status');
    $stmt->execute([$user['id']]);
    $counts = array_column($stmt->fetchAll(), 'n', 'status');

    // Revenus : budgets des projets terminés ; ventes : commandes de mes ressources
    $stmt = $pdo->prepare("SELECT COALESCE(SUM(budget), 0) FROM projects WHERE designer_id = ? AND status = 'termine'");
    $stmt->execute([$user['id']]);
    $revenue = (float) $stmt->fetchColumn();

    $stmt = $pdo->prepare('SELECT COUNT(o.id) AS n, COALESCE(SUM(o.price), 0) AS total
                           FROM orders o JOIN resources r ON r.id = o.resource_id WHERE r.author_id = ?');
    $stmt->execute([$user['id']]);
    $sales = $stmt->fetch();

    // Invitations en attente (sur des projets encore ouverts)
    $stmt = $pdo->prepare("SELECT i.id, p.id AS project_id, p.title, p.budget, p.deadline_days, c.full_name AS client_name
                           FROM project_invitations i
                           JOIN projects p ON p.id = i.project_id
                           JOIN users c ON c.id = p.client_id
                           WHERE i.designer_id = ? AND i.status = 'en_attente' AND p.status = 'ouvert'
                           ORDER BY i.created_at DESC, i.id DESC");
    $stmt->execute([$user['id']]);
    $pending = $stmt->fetchAll();

    // Mes propositions envoyées
    $stmt = $pdo->prepare('SELECT pr.status, pr.price, p.id AS project_id, p.title
                           FROM proposals pr JOIN projects p ON p.id = pr.project_id
                           WHERE pr.designer_id = ? ORDER BY pr.created_at DESC, pr.id DESC LIMIT 6');
    $stmt->execute([$user['id']]);
    $myProposals = $stmt->fetchAll();

    // Mes projets en cours, avec avancement
    $stmt = $pdo->prepare("SELECT id, title FROM projects WHERE designer_id = ? AND status = 'en_cours' ORDER BY id DESC");
    $stmt->execute([$user['id']]);
    $activeProjects = $stmt->fetchAll();
    $progress = progress_map(array_column($activeProjects, 'id'));

    // Projets ouverts recommandés (mêmes règles que la page Matching)
    $recommended = array_slice(recommend_projects_for_designer($user['id']), 0, 3);
}

$page_title = 'Mon espace';
$active = '';   // aucun lien du menu n'est surligné
require __DIR__ . '/includes/header.php';

echo '<div class="ws">';
if ($user['role'] === 'client') {
    echo ws_sidebar($user, 'Espace client', null, [
        ['overview', 'dashboard.php', 'chart', 'Vue d\'ensemble'],
        ['-'],
        ['projets', '#projets', 'kanban', 'Mes projets', array_sum($counts)],
        ['propositions', '#propositions', 'inbox', 'Propositions reçues', count($proposalsIn)],
        ['invitations', '#invitations', 'mail', 'Invitations envoyées', count($invitations)],
        ['obtenues', '#obtenues', 'download', 'Ressources obtenues', count($myOrders)],
        ['-'],
        ['publier', 'projects.php', 'plus', 'Publier un projet'],
        ['matching', 'matching.php', 'target', 'Trouver un designer'],
        ['market', 'marketplace.php', 'bag', 'Marketplace'],
    ], 'overview', 'Astuce', '<p style="margin:0">Publiez un projet puis ouvrez <a href="matching.php">Matching</a> : les designers sont classés avec le détail des points.</p>');
} else {
    echo ws_sidebar($user, 'Espace designer · ' . $profile['specialty'], $profile['avatar_url'], [
        ['overview', 'dashboard.php', 'chart', 'Vue d\'ensemble'],
        ['-'],
        ['invitations', '#invitations', 'mail', 'Invitations reçues', count($pending)],
        ['projets', '#projets', 'kanban', 'Projets en cours', count($activeProjects)],
        ['propositions', '#propositions', 'inbox', 'Mes propositions', count($myProposals)],
        ['obtenues', '#obtenues', 'download', 'Ressources obtenues', count($myOrders)],
        ['-'],
        ['profil', 'edit-profile.php', 'edit', 'Modifier profil et portfolio'],
        ['public', 'designer-profile.php?id=' . (int) $user['id'], 'eye', 'Voir mon profil public'],
        ['assistant', 'assistant.php', 'spark', 'Améliorer mon profil'],
        ['matching', 'matching.php', 'target', 'Projets recommandés'],
    ], 'overview', 'Astuce', '<p style="margin:0">Un portfolio complet et une bio claire augmentent votre score de matching. Testez l\'<a href="assistant.php">assistant portfolio</a>.</p>');
}
?>
<div class="ws-main">

<?php if ($user['role'] === 'client'): ?>
    <section class="ws-hero">
        <div class="hello"><?= icon('briefcase') ?>Espace client</div>
        <h1>Bonjour, <?= e($user['full_name']) ?></h1>
        <p>Suivez vos projets, examinez les propositions des designers et retrouvez vos ressources en un seul endroit.</p>
        <div class="ws-hero-actions">
            <a class="btn btn-sun" href="projects.php"><?= icon('plus') ?>Publier un projet</a>
            <a class="btn btn-light" href="matching.php"><?= icon('target') ?>Trouver un designer</a>
            <a class="btn btn-light" href="marketplace.php"><?= icon('bag') ?>Marketplace</a>
        </div>
    </section>

    <div class="kpis">
        <?php
        $tones = ['ouvert' => ['inbox', 'sun'], 'en_cours' => ['clock', 'blue'], 'termine' => ['check-circle', 'green'], 'annule' => ['close', 'clay']];
        foreach (PROJECT_STATUS as $key => $label): [$ic, $tn] = $tones[$key]; ?>
            <?= kpi_card($ic, $tn, (int) ($counts[$key] ?? 0), 'Projet(s) ' . mb_strtolower($label)) ?>
        <?php endforeach; ?>
    </div>
    <div class="kpis kpis-3">
        <?= kpi_card('coins', 'navy', (int) $engaged, 'TND engagés (projets en cours ou terminés)') ?>
        <?= kpi_card('inbox', 'sun', count($proposalsIn), 'Proposition(s) à examiner') ?>
        <?= kpi_card('download', 'teal', count($myOrders), 'Ressource(s) obtenue(s)') ?>
    </div>

    <?php if ($proposalsIn): ?>
        <section class="panel" id="propositions" style="border-color:#f1dca6;background:linear-gradient(180deg,#fffaf0,#fff)">
            <div class="panel-head"><h2><?= icon('inbox') ?>Propositions à examiner (<?= count($proposalsIn) ?>)</h2></div>
            <?php foreach ($proposalsIn as $pr): ?>
                <div class="item">
                    <?= icon_tile('palette', 'sun') ?>
                    <div class="item-main"><a class="title" href="designer-profile.php?id=<?= (int) $pr['designer_id'] ?>"><?= e($pr['full_name']) ?></a>
                        <p class="meta">pour <a href="project-details.php?id=<?= (int) $pr['project_id'] ?>"><?= e($pr['title']) ?></a> · <?= (int) $pr['delay_days'] ?> jours</p></div>
                    <div class="item-side"><strong class="price"><?= e(number_format($pr['price'], 0, ',', ' ')) ?> TND</strong>
                        <a class="btn btn-small" href="project-details.php?id=<?= (int) $pr['project_id'] ?>">Examiner</a></div>
                </div>
            <?php endforeach; ?>
        </section>
    <?php endif; ?>

    <div class="ws-cols">
        <section class="panel" id="projets">
            <div class="panel-head"><h2><?= icon('kanban') ?>Mes derniers projets</h2><a class="link-more" href="projects.php" style="margin:0">Tous <?= icon('arrow-right') ?></a></div>
            <?php if (!$recent): ?><?= empty_state('kanban', 'Aucun projet', 'Publiez votre premier projet pour recevoir des propositions.', 'projects.php', 'Publier un projet') ?><?php endif; ?>
            <?php foreach ($recent as $p): ?>
                <div class="item">
                    <div class="item-main">
                        <a class="title" href="project-details.php?id=<?= (int) $p['id'] ?>"><?= e($p['title']) ?></a>
                        <p class="meta"><?= e($p['category']) ?></p>
                        <?php if ($p['status'] === 'en_cours'): ?><?= progress_bar($progress[$p['id']] ?? null) ?><?php endif; ?>
                    </div>
                    <div class="item-side"><?= status_badge($p['status']) ?></div>
                </div>
            <?php endforeach; ?>
        </section>
        <section class="panel" id="invitations">
            <div class="panel-head"><h2><?= icon('mail') ?>Invitations envoyées</h2></div>
            <?php if (!$invitations): ?><?= empty_state('mail', 'Aucune invitation', 'Invitez un designer depuis la page Matching.', 'matching.php', 'Ouvrir le matching') ?><?php endif; ?>
            <?php foreach ($invitations as $i): ?>
                <div class="item">
                    <div class="item-main"><a class="title" href="designer-profile.php?id=<?= (int) $i['designer_id'] ?>"><?= e($i['full_name']) ?></a>
                        <p class="meta"><a href="project-details.php?id=<?= (int) $i['project_id'] ?>"><?= e($i['title']) ?></a></p></div>
                    <div class="item-side"><span class="badge badge-<?= e($i['status']) ?>"><?= e(INVITATION_STATUS[$i['status']]) ?></span></div>
                </div>
            <?php endforeach; ?>
        </section>
    </div>

<?php else: ?>
    <section class="ws-hero">
        <div class="hello"><?= icon('palette') ?>Espace designer</div>
        <h1>Bonjour, <?= e($user['full_name']) ?></h1>
        <p>Répondez aux invitations, suivez vos projets en cours et valorisez votre portfolio pour décrocher de nouvelles missions.</p>
        <div class="ws-hero-actions">
            <a class="btn btn-sun" href="edit-profile.php"><?= icon('edit') ?>Modifier mon profil et mon portfolio</a>
            <a class="btn btn-light" href="designer-profile.php?id=<?= (int) $user['id'] ?>"><?= icon('eye') ?>Voir mon profil public</a>
            <a class="btn btn-light" href="assistant.php"><?= icon('spark') ?>Améliorer mon profil</a>
        </div>
    </section>

    <div class="kpis">
        <?= kpi_card('mail', 'sun', count($pending), 'Invitation(s) en attente') ?>
        <?= kpi_card('clock', 'blue', (int) ($counts['en_cours'] ?? 0), 'Projet(s) en cours') ?>
        <?= kpi_card('check-circle', 'green', (int) ($counts['termine'] ?? 0), 'Projet(s) terminé(s)') ?>
        <?= kpi_card('image', 'teal', (int) $profile['nb_portfolio'], 'Réalisation(s) au portfolio') ?>
    </div>
    <div class="kpis kpis-3">
        <?= kpi_card('coins', 'navy', (int) $revenue, 'TND de projets terminés') ?>
        <?= kpi_card('download', 'teal', (int) $sales['n'], 'Ressource(s) obtenue(s)', null, '', number_format((float) $sales['total'], 0, ',', ' ') . ' TND') ?>
        <?= kpi_card('star', 'clay', $profile['nb_reviews'] ? number_format($profile['avg_rating'], 1) : '—', 'Note moyenne (' . (int) $profile['nb_reviews'] . ' avis)') ?>
    </div>

    <?php if ($recommended): ?>
        <section class="panel" style="border-color:#c5d9f2;background:linear-gradient(180deg,#f6faff,#fff)">
            <div class="panel-head"><h2><?= icon('target') ?>Projets recommandés pour vous</h2><a class="link-more" href="matching.php" style="margin:0">Voir tout <?= icon('arrow-right') ?></a></div>
            <?php foreach ($recommended as $r): $p = $r['project']; ?>
                <div class="item">
                    <?= icon_tile('target', 'blue') ?>
                    <div class="item-main">
                        <a class="title" href="project-details.php?id=<?= (int) $p['id'] ?>"><?= e($p['title']) ?></a>
                        <p class="meta"><?= e($p['category']) ?> · <?= e(number_format($p['budget'], 0)) ?> TND · <?= (int) $p['deadline_days'] ?> jours</p>
                    </div>
                    <div class="item-side"><span class="badge badge-en_cours">Compatibilité <?= (int) $r['score'] ?>/100</span></div>
                </div>
            <?php endforeach; ?>
        </section>
    <?php endif; ?>

    <section class="panel" id="invitations">
        <div class="panel-head"><h2><?= icon('mail') ?>Invitations reçues</h2></div>
        <?php if (!$pending): ?><?= empty_state('mail', 'Aucune invitation en attente', 'Complétez votre portfolio : les clients vous trouveront plus facilement.', 'edit-profile.php', 'Améliorer mon portfolio') ?><?php endif; ?>
        <?php foreach ($pending as $i): ?>
            <div class="item">
                <?= icon_tile('briefcase', 'sun') ?>
                <div class="item-main">
                    <a class="title" href="project-details.php?id=<?= (int) $i['project_id'] ?>"><?= e($i['title']) ?></a>
                    <p class="meta">Client : <?= e($i['client_name']) ?> · <?= e(number_format($i['budget'], 0)) ?> TND · <?= (int) $i['deadline_days'] ?> jours</p>
                </div>
                <form method="post" action="project-action.php" class="item-side">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="respond">
                    <input type="hidden" name="invitation_id" value="<?= (int) $i['id'] ?>">
                    <input type="hidden" name="back" value="dashboard.php">
                    <button class="btn btn-small" name="answer" value="accept" type="submit"><?= icon('check') ?>Accepter</button>
                    <button class="btn btn-small btn-light" name="answer" value="refuse" type="submit">Refuser</button>
                </form>
            </div>
        <?php endforeach; ?>
    </section>

    <div class="ws-cols">
        <section class="panel" id="projets">
            <div class="panel-head"><h2><?= icon('kanban') ?>Projets en cours</h2></div>
            <?php if (!$activeProjects): ?><?= empty_state('kanban', 'Aucun projet en cours', 'Acceptez une invitation ou envoyez une proposition.', 'projects.php', 'Voir les projets') ?><?php endif; ?>
            <?php foreach ($activeProjects as $p): ?>
                <div class="item">
                    <div class="item-main">
                        <a class="title" href="project-details.php?id=<?= (int) $p['id'] ?>#espace"><?= e($p['title']) ?></a>
                        <?= progress_bar($progress[$p['id']] ?? null) ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </section>
        <section class="panel" id="propositions">
            <div class="panel-head"><h2><?= icon('inbox') ?>Mes propositions</h2></div>
            <?php if (!$myProposals): ?><?= empty_state('inbox', 'Aucune proposition envoyée', 'Proposez vos services sur un projet ouvert.', 'projects.php', 'Parcourir les projets') ?><?php endif; ?>
            <?php foreach ($myProposals as $pr): ?>
                <div class="item">
                    <div class="item-main"><a class="title" href="project-details.php?id=<?= (int) $pr['project_id'] ?>"><?= e($pr['title']) ?></a>
                        <p class="meta"><?= e(number_format($pr['price'], 0)) ?> TND</p></div>
                    <div class="item-side"><span class="badge badge-<?= e($pr['status']) ?>"><?= e(INVITATION_STATUS[$pr['status']]) ?></span></div>
                </div>
            <?php endforeach; ?>
        </section>
    </div>
<?php endif; ?>

<?php require __DIR__ . '/includes/dash_orders.php'; ?>
</div>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
