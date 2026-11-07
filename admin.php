<?php
// admin.php : espace administrateur.
//  - vue d'ensemble : statistiques générales de la plateforme (calculées depuis la base)
//  - modération : utilisateurs, projets, ressources et avis (suppression avec motif, enregistrée au journal)
// Réservé au rôle « admin » : vérifié côté serveur par require_admin().
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/workspace.php';

$user = require_admin();
$pdo  = db();

$tabs = ['overview' => 'Vue d\'ensemble', 'users' => 'Utilisateurs', 'projects' => 'Projets', 'resources' => 'Ressources', 'reviews' => 'Avis', 'logs' => 'Journal'];
$tab = $_GET['tab'] ?? 'overview';
if (!isset($tabs[$tab])) {
    $tab = 'overview';
}
$q = trim($_GET['q'] ?? '');
$filter = $_GET['f'] ?? '';
$LIMIT = 100;

$one = fn(string $sql) => (float) $pdo->query($sql)->fetchColumn();

// ---------- Compteurs (barre latérale et indicateurs) ----------
$n = [
    'users'     => (int) $one("SELECT COUNT(*) FROM users WHERE role <> 'admin'"),
    'clients'   => (int) $one("SELECT COUNT(*) FROM users WHERE role = 'client'"),
    'designers' => (int) $one("SELECT COUNT(*) FROM users WHERE role = 'designer'"),
    'projects'  => (int) $one('SELECT COUNT(*) FROM projects'),
    'resources' => (int) $one('SELECT COUNT(*) FROM resources'),
    'reviews'   => (int) $one('SELECT COUNT(*) FROM reviews'),
    'orders'    => (int) $one('SELECT COUNT(*) FROM orders'),
    'logs'      => (int) $one('SELECT COUNT(*) FROM admin_logs'),
];

// ---------- Données de la vue d'ensemble ----------
if ($tab === 'overview') {
    $statusCounts = array_column($pdo->query('SELECT status, COUNT(*) AS n FROM projects GROUP BY status')->fetchAll(), 'n', 'status');
    $budgetDone   = $one("SELECT COALESCE(SUM(budget), 0) FROM projects WHERE status = 'termine'");
    $salesTotal   = $one('SELECT COALESCE(SUM(price), 0) FROM orders');
    $avgRating    = $one('SELECT COALESCE(AVG(rating), 0) FROM reviews');
    $openProjects = (int) ($statusCounts['ouvert'] ?? 0);
    $doneRate     = $n['projects'] ? (int) round(100 * (int) ($statusCounts['termine'] ?? 0) / $n['projects']) : 0;

    // Activité des 6 derniers mois : inscriptions, projets publiés, ressources obtenues
    $months = [];
    for ($i = 5; $i >= 0; $i--) {
        $t = strtotime("first day of -$i month");
        $months[date('Y-m', $t)] = ['label' => ['janv', 'févr', 'mars', 'avr', 'mai', 'juin', 'juil', 'août', 'sept', 'oct', 'nov', 'déc'][(int) date('n', $t) - 1],
                                    'users' => 0, 'projects' => 0, 'orders' => 0];
    }
    foreach (['users' => 'users', 'projects' => 'projects', 'orders' => 'orders'] as $key => $table) {
        $where = $table === 'users' ? "WHERE role <> 'admin' AND" : 'WHERE';
        $rows = $pdo->query("SELECT DATE_FORMAT(created_at, '%Y-%m') AS m, COUNT(*) AS n FROM $table $where created_at >= DATE_SUB(DATE_FORMAT(NOW(), '%Y-%m-01'), INTERVAL 5 MONTH) GROUP BY m")->fetchAll();
        foreach ($rows as $r) {
            if (isset($months[$r['m']])) {
                $months[$r['m']][$key] = (int) $r['n'];
            }
        }
    }
    $maxMonth = 1;
    foreach ($months as $m) {
        $maxMonth = max($maxMonth, $m['users'] + $m['projects'] + $m['orders']);
    }

    $byCategory = $pdo->query('SELECT category, COUNT(*) AS n, COALESCE(AVG(budget), 0) AS avg_budget FROM projects GROUP BY category ORDER BY n DESC')->fetchAll();
    $maxCat = max(1, ...array_column($byCategory ?: [['n' => 1]], 'n'));

    $topDesigners = $pdo->query("SELECT u.id, u.full_name, p.specialty, p.avatar_url,
            (SELECT AVG(rating) FROM reviews WHERE designer_id = u.id) AS avg_rating,
            (SELECT COUNT(*) FROM reviews WHERE designer_id = u.id) AS nb_reviews,
            (SELECT COUNT(*) FROM projects WHERE designer_id = u.id AND status = 'termine') AS done
        FROM users u JOIN designer_profiles p ON p.user_id = u.id WHERE u.role = 'designer'
        ORDER BY avg_rating DESC, done DESC LIMIT 5")->fetchAll();

    $latestUsers = $pdo->query("SELECT id, full_name, email, role, created_at FROM users WHERE role <> 'admin' ORDER BY id DESC LIMIT 5")->fetchAll();
    $latestLogs  = $pdo->query('SELECT l.*, u.full_name AS admin_name FROM admin_logs l LEFT JOIN users u ON u.id = l.admin_id ORDER BY l.id DESC LIMIT 5')->fetchAll();
}

// ---------- Données des onglets de modération (requêtes préparées, filtres en liste blanche) ----------
$rows = [];
if ($tab === 'users') {
    $sql = "SELECT u.id, u.full_name, u.email, u.role, u.created_at, p.avatar_url,
                   (SELECT COUNT(*) FROM projects WHERE client_id = u.id OR designer_id = u.id) AS nb_projects,
                   (SELECT COUNT(*) FROM resources WHERE author_id = u.id) AS nb_resources
            FROM users u LEFT JOIN designer_profiles p ON p.user_id = u.id WHERE 1=1";
    $params = [];
    if ($q !== '') { $sql .= ' AND (u.full_name LIKE ? OR u.email LIKE ?)'; array_push($params, "%$q%", "%$q%"); }
    if (in_array($filter, ['client', 'designer', 'admin'], true)) { $sql .= ' AND u.role = ?'; $params[] = $filter; }
    $stmt = $pdo->prepare($sql . " ORDER BY u.id DESC LIMIT $LIMIT");
    $stmt->execute($params);
    $rows = $stmt->fetchAll();
} elseif ($tab === 'projects') {
    $sql = 'SELECT p.*, c.full_name AS client_name, d.full_name AS designer_name FROM projects p
            JOIN users c ON c.id = p.client_id LEFT JOIN users d ON d.id = p.designer_id WHERE 1=1';
    $params = [];
    if ($q !== '') { $sql .= ' AND (p.title LIKE ? OR c.full_name LIKE ?)'; array_push($params, "%$q%", "%$q%"); }
    if (isset(PROJECT_STATUS[$filter])) { $sql .= ' AND p.status = ?'; $params[] = $filter; }
    $stmt = $pdo->prepare($sql . " ORDER BY p.id DESC LIMIT $LIMIT");
    $stmt->execute($params);
    $rows = $stmt->fetchAll();
} elseif ($tab === 'resources') {
    $sql = 'SELECT r.*, u.full_name AS author_name, (SELECT COUNT(*) FROM orders o WHERE o.resource_id = r.id) AS nb_orders
            FROM resources r JOIN users u ON u.id = r.author_id WHERE 1=1';
    $params = [];
    if ($q !== '') { $sql .= ' AND (r.title LIKE ? OR u.full_name LIKE ?)'; array_push($params, "%$q%", "%$q%"); }
    if (in_array($filter, RESOURCE_CATEGORIES, true)) { $sql .= ' AND r.category = ?'; $params[] = $filter; }
    $stmt = $pdo->prepare($sql . " ORDER BY r.id DESC LIMIT $LIMIT");
    $stmt->execute($params);
    $rows = $stmt->fetchAll();
} elseif ($tab === 'reviews') {
    $rows = $pdo->query("SELECT r.*, c.full_name AS client_name, d.full_name AS designer_name, p.title AS project_title
                         FROM reviews r JOIN users c ON c.id = r.client_id JOIN users d ON d.id = r.designer_id JOIN projects p ON p.id = r.project_id
                         ORDER BY r.id DESC LIMIT $LIMIT")->fetchAll();
} elseif ($tab === 'logs') {
    $rows = $pdo->query("SELECT l.*, u.full_name AS admin_name FROM admin_logs l LEFT JOIN users u ON u.id = l.admin_id ORDER BY l.id DESC LIMIT $LIMIT")->fetchAll();
}

$LOG_LABELS = ['delete_user' => 'Utilisateur supprimé', 'delete_project' => 'Projet supprimé', 'delete_resource' => 'Ressource supprimée', 'delete_review' => 'Avis supprimé'];

// Bouton « Supprimer » : ouvre la fenêtre de confirmation avec motif (voir js/app.js)
function del_button(string $type, int $id, string $label, string $warn = ''): string
{
    return '<button class="btn btn-small btn-danger-soft" type="button" data-modal="del-modal" data-type="' . e($type) . '" data-id="' . $id
        . '" data-label="' . e($label) . '" data-warn="' . e($warn) . '">' . icon('trash') . 'Supprimer</button>';
}

$page_title = 'Administration';
$active = '';
require __DIR__ . '/includes/header.php';

echo '<div class="ws">';
echo ws_sidebar($user, 'Administrateur', 'images/avatars/admin.jpg', [
    ['overview', 'admin.php', 'chart', 'Vue d\'ensemble'],
    ['-'],
    ['users', 'admin.php?tab=users', 'users', 'Utilisateurs', $n['users']],
    ['projects', 'admin.php?tab=projects', 'kanban', 'Projets', $n['projects']],
    ['resources', 'admin.php?tab=resources', 'bag', 'Ressources', $n['resources']],
    ['reviews', 'admin.php?tab=reviews', 'star', 'Avis', $n['reviews']],
    ['-'],
    ['logs', 'admin.php?tab=logs', 'history', 'Journal des actions'],
], $tab, 'Rôle de modération', '<p style="margin:0">Supprimez un utilisateur, un projet, une ressource ou un avis qui enfreint les règles. Chaque suppression demande un motif et reste tracée dans le journal.</p>');
?>
<div class="ws-main">

<?php if ($tab === 'overview'): ?>
    <section class="ws-hero">
        <div>
            <div class="hello"><?= icon('shield') ?>Espace administrateur</div>
            <h1>Tableau de bord de la plateforme</h1>
            <p>Statistiques générales calculées en direct depuis la base de données, et outils de modération.</p>
            <div class="ws-hero-actions">
                <a class="btn btn-sun" href="admin.php?tab=users"><?= icon('users') ?>Gérer les utilisateurs</a>
                <a class="btn btn-light" href="admin.php?tab=logs"><?= icon('history') ?>Journal</a>
            </div>
        </div>
    </section>

    <div class="kpis kpis-5">
        <?= kpi_card('users', 'blue', $n['users'], 'Utilisateurs', 'admin.php?tab=users') ?>
        <?= kpi_card('palette', 'teal', $n['designers'], 'Designers') ?>
        <?= kpi_card('briefcase', 'sun', $n['clients'], 'Clients') ?>
        <?= kpi_card('kanban', 'navy', $n['projects'], 'Projets', 'admin.php?tab=projects') ?>
        <?= kpi_card('bag', 'green', $n['resources'], 'Ressources', 'admin.php?tab=resources') ?>
    </div>
    <div class="kpis kpis-5">
        <?= kpi_card('inbox', 'sun', $openProjects, 'Projets ouverts') ?>
        <?= kpi_card('check-circle', 'green', $doneRate, 'Projets terminés', null, ' %') ?>
        <?= kpi_card('coins', 'navy', (int) $budgetDone, 'TND de budgets livrés') ?>
        <?= kpi_card('download', 'teal', $n['orders'], 'Ressources obtenues', null, '', (int) $salesTotal . ' TND') ?>
        <?= kpi_card('star', 'clay', $n['reviews'] ? number_format($avgRating, 1) : '—', 'Note moyenne (' . $n['reviews'] . ' avis)', 'admin.php?tab=reviews') ?>
    </div>

    <div class="ws-cols">
        <div>
            <section class="panel">
                <div class="panel-head"><h2><?= icon('chart') ?>Activité des 6 derniers mois</h2></div>
                <div class="chart-cols">
                    <?php foreach ($months as $m): $tot = $m['users'] + $m['projects'] + $m['orders']; $h = $tot ? max(6, (int) round(100 * $tot / $maxMonth)) : 0; ?>
                        <div class="chart-col">
                            <b><?= $tot ?></b>
                            <div class="stack" style="height:<?= $h ?>%">
                                <?php foreach ([['users', '#1f5fae'], ['projects', '#0e8a8a'], ['orders', '#e9b44c']] as [$k, $col]): if ($m[$k]): ?>
                                    <i style="flex:<?= $m[$k] ?>;background:<?= $col ?>" title="<?= (int) $m[$k] ?>"></i>
                                <?php endif; endforeach; ?>
                            </div>
                            <small><?= e($m['label']) ?></small>
                        </div>
                    <?php endforeach; ?>
                </div>
                <div class="chart-legend"><span><i style="background:#1f5fae"></i>Inscriptions</span><span><i style="background:#0e8a8a"></i>Projets publiés</span><span><i style="background:#e9b44c"></i>Ressources obtenues</span></div>
            </section>

            <section class="panel">
                <div class="panel-head"><h2><?= icon('layers') ?>Projets par catégorie</h2></div>
                <?php if (!$byCategory): ?><?= empty_state('layers', 'Pas encore de projet', 'Les statistiques apparaîtront dès les premières publications.') ?><?php endif; ?>
                <?php foreach ($byCategory as $c): ?>
                    <div class="hbar">
                        <div class="hbar-top"><span><?= e($c['category']) ?></span><b><?= (int) $c['n'] ?> · <?= e(number_format($c['avg_budget'], 0, ',', ' ')) ?> TND moy.</b></div>
                        <div class="bar"><span style="width:<?= max(4, (int) round(100 * $c['n'] / $maxCat)) ?>%"></span></div>
                    </div>
                <?php endforeach; ?>
            </section>

            <section class="panel">
                <div class="panel-head"><h2><?= icon('award') ?>Designers les mieux notés</h2></div>
                <?php foreach ($topDesigners as $d): ?>
                    <div class="item">
                        <?= avatar_html($d['full_name'], $d['avatar_url'], 'md') ?>
                        <div class="item-main"><a class="title" href="designer-profile.php?id=<?= (int) $d['id'] ?>"><?= e($d['full_name']) ?></a>
                            <p class="meta"><?= e($d['specialty']) ?> · <?= (int) $d['done'] ?> projet(s) terminé(s)</p></div>
                        <div class="item-side small"><?= rating_html($d['avg_rating'], $d['nb_reviews']) ?></div>
                    </div>
                <?php endforeach; ?>
            </section>
        </div>
        <div>
            <section class="panel">
                <div class="panel-head"><h2><?= icon('kanban') ?>Statut des projets</h2></div>
                <?php
                $palette = ['ouvert' => '#e9b44c', 'en_cours' => '#1f5fae', 'termine' => '#4fae5a', 'annule' => '#cf3b3b'];
                $acc = 0; $stops = [];
                foreach (PROJECT_STATUS as $k => $lbl) {
                    $v = (int) ($statusCounts[$k] ?? 0);
                    if ($v && $n['projects']) { $from = $acc; $acc += 100 * $v / $n['projects']; $stops[] = $palette[$k] . ' ' . round($from, 2) . '% ' . round($acc, 2) . '%'; }
                }
                ?>
                <div class="donut-wrap">
                    <div class="donut" style="background:<?= $stops ? 'conic-gradient(' . implode(',', $stops) . ')' : '#e4ebf4' ?>"><div class="donut-center"><div><?= $n['projects'] ?><small>projets</small></div></div></div>
                    <ul class="legend">
                        <?php foreach (PROJECT_STATUS as $k => $lbl): ?><li><i style="background:<?= $palette[$k] ?>"></i><?= e($lbl) ?><b><?= (int) ($statusCounts[$k] ?? 0) ?></b></li><?php endforeach; ?>
                    </ul>
                </div>
            </section>

            <section class="panel">
                <div class="panel-head"><h2><?= icon('users') ?>Derniers inscrits</h2><a class="link-more" href="admin.php?tab=users" style="margin:0">Tous <?= icon('arrow-right') ?></a></div>
                <?php foreach ($latestUsers as $u): ?>
                    <div class="item">
                        <?= avatar_html($u['full_name'], null, 'md') ?>
                        <div class="item-main"><strong class="title"><?= e($u['full_name']) ?></strong><p class="meta"><?= e(date_fr($u['created_at'])) ?></p></div>
                        <div class="item-side"><span class="badge badge-<?= e($u['role']) ?>"><?= e(role_label($u['role'])) ?></span></div>
                    </div>
                <?php endforeach; ?>
            </section>

            <section class="panel">
                <div class="panel-head"><h2><?= icon('history') ?>Dernières actions</h2><a class="link-more" href="admin.php?tab=logs" style="margin:0">Journal <?= icon('arrow-right') ?></a></div>
                <?php if (!$latestLogs): ?><?= empty_state('shield', 'Aucune modération', 'Les suppressions effectuées par un administrateur apparaîtront ici.') ?><?php endif; ?>
                <?php foreach ($latestLogs as $l): ?>
                    <div class="item">
                        <?= icon_tile('trash', 'clay') ?>
                        <div class="item-main"><strong class="title"><?= e($LOG_LABELS[$l['action']] ?? $l['action']) ?></strong>
                            <p class="meta"><?= e($l['target_label']) ?> · <?= e(date_fr($l['created_at'])) ?></p></div>
                    </div>
                <?php endforeach; ?>
            </section>
        </div>
    </div>

<?php elseif ($tab === 'users'): ?>
    <div class="page-head"><h1>Utilisateurs</h1></div>
    <p class="muted lead-sm">Retrouvez un compte et supprimez-le s'il enfreint les règles. Ses projets, ressources et avis sont supprimés avec lui.</p>
    <section class="panel">
        <form method="get" class="toolbar">
            <input type="hidden" name="tab" value="users">
            <input class="grow" type="search" name="q" value="<?= e($q) ?>" placeholder="Nom ou email…">
            <select name="f"><option value="">Tous les rôles</option>
                <?php foreach (['client' => 'Clients', 'designer' => 'Designers', 'admin' => 'Administrateurs'] as $k => $l): ?><option value="<?= $k ?>" <?= $filter === $k ? 'selected' : '' ?>><?= $l ?></option><?php endforeach; ?></select>
            <button class="btn btn-small" type="submit"><?= icon('search') ?>Filtrer</button>
        </form>
        <div class="table-wrap"><table class="table">
            <thead><tr><th>Utilisateur</th><th>Rôle</th><th>Inscrit le</th><th class="num">Projets</th><th class="num">Ressources</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($rows as $u): ?>
                <tr>
                    <td><div class="who"><?= avatar_html($u['full_name'], $u['avatar_url'], 'md') ?><div><strong><?= e($u['full_name']) ?></strong><span><?= e($u['email']) ?></span></div></div></td>
                    <td><span class="badge badge-<?= e($u['role']) ?>"><?= e(role_label($u['role'])) ?></span></td>
                    <td><?= e(date_fr($u['created_at'])) ?></td>
                    <td class="num"><?= (int) $u['nb_projects'] ?></td>
                    <td class="num"><?= (int) $u['nb_resources'] ?></td>
                    <td class="actions">
                        <?php if ($u['role'] === 'designer'): ?><a class="btn btn-small btn-light" href="designer-profile.php?id=<?= (int) $u['id'] ?>"><?= icon('eye') ?>Profil</a><?php endif; ?>
                        <?php if ($u['role'] !== 'admin'): echo del_button('user', (int) $u['id'], $u['full_name'] . ' (' . $u['email'] . ')', 'Son compte, ses projets, ses ressources et ses avis seront supprimés définitivement.');
                        else: ?><span class="muted small">Protégé</span><?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table></div>
        <?php if (!$rows): ?><?= empty_state('users', 'Aucun utilisateur trouvé', 'Modifiez la recherche ou le filtre.') ?><?php endif; ?>
    </section>

<?php elseif ($tab === 'projects'): ?>
    <div class="page-head"><h1>Projets</h1></div>
    <p class="muted lead-sm">Supprimez un projet inapproprié. Ses tâches, messages, propositions et invitations sont supprimés avec lui.</p>
    <section class="panel">
        <form method="get" class="toolbar">
            <input type="hidden" name="tab" value="projects">
            <input class="grow" type="search" name="q" value="<?= e($q) ?>" placeholder="Titre du projet ou client…">
            <select name="f"><option value="">Tous les statuts</option>
                <?php foreach (PROJECT_STATUS as $k => $l): ?><option value="<?= e($k) ?>" <?= $filter === $k ? 'selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?></select>
            <button class="btn btn-small" type="submit"><?= icon('search') ?>Filtrer</button>
        </form>
        <div class="table-wrap"><table class="table">
            <thead><tr><th>Projet</th><th>Client</th><th>Designer</th><th>Statut</th><th class="num">Budget</th><th>Publié le</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($rows as $p): ?>
                <tr>
                    <td><strong style="color:var(--navy)"><?= e($p['title']) ?></strong><br><span class="muted small"><?= e($p['category']) ?></span></td>
                    <td><?= e($p['client_name']) ?></td>
                    <td><?= $p['designer_name'] ? e($p['designer_name']) : '<span class="muted">—</span>' ?></td>
                    <td><?= status_badge($p['status']) ?></td>
                    <td class="num"><?= e(number_format($p['budget'], 0, ',', ' ')) ?> TND</td>
                    <td><?= e(date_fr($p['created_at'])) ?></td>
                    <td class="actions"><a class="btn btn-small btn-light" href="project-details.php?id=<?= (int) $p['id'] ?>"><?= icon('eye') ?>Voir</a>
                        <?= del_button('project', (int) $p['id'], $p['title'], 'Les tâches, messages, propositions, invitations et l\'avis liés seront supprimés.') ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table></div>
        <?php if (!$rows): ?><?= empty_state('kanban', 'Aucun projet trouvé', 'Modifiez la recherche ou le filtre.') ?><?php endif; ?>
    </section>

<?php elseif ($tab === 'resources'): ?>
    <div class="page-head"><h1>Ressources</h1></div>
    <p class="muted lead-sm">Supprimez une ressource du marketplace (contenu protégé, non conforme ou trompeur). Les fichiers sont effacés du serveur.</p>
    <section class="panel">
        <form method="get" class="toolbar">
            <input type="hidden" name="tab" value="resources">
            <input class="grow" type="search" name="q" value="<?= e($q) ?>" placeholder="Titre ou auteur…">
            <select name="f"><option value="">Toutes les catégories</option>
                <?php foreach (RESOURCE_CATEGORIES as $c): ?><option value="<?= e($c) ?>" <?= $filter === $c ? 'selected' : '' ?>><?= e($c) ?></option><?php endforeach; ?></select>
            <button class="btn btn-small" type="submit"><?= icon('search') ?>Filtrer</button>
        </form>
        <div class="table-wrap"><table class="table">
            <thead><tr><th>Ressource</th><th>Auteur</th><th>Catégorie</th><th class="num">Prix</th><th class="num">Obtentions</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($rows as $r): ?>
                <tr>
                    <td><div class="who"><?php if ($r['image_url']): ?><img class="thumb" src="<?= e($r['image_url']) ?>" alt="" data-fallback="hide"><?php else: ?><span class="thumb"></span><?php endif; ?>
                        <div><strong><?= e($r['title']) ?></strong><span><?= e(date_fr($r['created_at'])) ?></span></div></div></td>
                    <td><?= e($r['author_name']) ?></td>
                    <td><span class="tag" style="margin:0"><?= e($r['category']) ?></span></td>
                    <td class="num"><?= e(format_price($r['price'])) ?></td>
                    <td class="num"><?= (int) $r['nb_orders'] ?></td>
                    <td class="actions"><?= del_button('resource', (int) $r['id'], $r['title'], 'Le fichier source et l\'aperçu seront effacés, ainsi que les commandes liées.') ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table></div>
        <?php if (!$rows): ?><?= empty_state('bag', 'Aucune ressource trouvée', 'Modifiez la recherche ou le filtre.') ?><?php endif; ?>
    </section>

<?php elseif ($tab === 'reviews'): ?>
    <div class="page-head"><h1>Avis</h1></div>
    <p class="muted lead-sm">Supprimez un avis abusif ou injurieux. La note moyenne du designer est recalculée automatiquement.</p>
    <section class="panel">
        <?php if (!$rows): ?><?= empty_state('star', 'Aucun avis', 'Les évaluations des clients apparaîtront ici.') ?><?php endif; ?>
        <?php foreach ($rows as $r): ?>
            <div class="item">
                <div class="item-main">
                    <?= stars_html((int) $r['rating']) ?>
                    <p style="margin:.3rem 0 .1rem"><?= e($r['comment'] !== '' ? $r['comment'] : '(sans commentaire)') ?></p>
                    <p class="meta"><?= e($r['client_name']) ?> → <?= e($r['designer_name']) ?> · <?= e($r['project_title']) ?> · <?= e(date_fr($r['created_at'])) ?></p>
                </div>
                <div class="item-side"><?= del_button('review', (int) $r['id'], $r['client_name'] . ' → ' . $r['designer_name'] . ' (' . (int) $r['rating'] . '/5)') ?></div>
            </div>
        <?php endforeach; ?>
    </section>

<?php else: ?>
    <div class="page-head"><h1>Journal des actions</h1></div>
    <p class="muted lead-sm">Trace de chaque suppression : qui, quoi, quand et pourquoi. Le journal ne peut pas être modifié.</p>
    <section class="panel">
        <?php if (!$rows): ?><?= empty_state('history', 'Journal vide', 'Aucune action de modération pour le moment.') ?><?php else: ?>
        <div class="table-wrap"><table class="table">
            <thead><tr><th>Date</th><th>Action</th><th>Élément</th><th>Motif</th><th>Administrateur</th></tr></thead>
            <tbody>
            <?php foreach ($rows as $l): ?>
                <tr>
                    <td class="small" style="white-space:nowrap"><?= e(date('d/m/Y H:i', strtotime($l['created_at']))) ?></td>
                    <td><span class="badge badge-annule"><?= e($LOG_LABELS[$l['action']] ?? $l['action']) ?></span></td>
                    <td><?= e($l['target_label']) ?></td>
                    <td class="small muted"><?= e($l['reason']) ?></td>
                    <td class="small"><?= e($l['admin_name'] ?? 'compte supprimé') ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table></div>
        <?php endif; ?>
    </section>
<?php endif; ?>

</div>
</div>

<dialog class="modal" id="del-modal">
    <form method="post" action="admin-action.php" class="modal-in">
        <?= csrf_field() ?>
        <input type="hidden" name="target_type" value="">
        <input type="hidden" name="target_id" value="">
        <input type="hidden" name="back" value="<?= e('admin.php?tab=' . $tab . ($q !== '' ? '&q=' . urlencode($q) : '') . ($filter !== '' ? '&f=' . urlencode($filter) : '')) ?>">
        <?= icon_tile('trash', 'clay') ?>
        <h3>Supprimer définitivement ?</h3>
        <p class="muted small cascade" style="margin:0"></p>
        <div class="target"></div>
        <label>Motif de la suppression (enregistré au journal)
            <textarea name="reason" rows="3" minlength="5" maxlength="300" required placeholder="Ex : contenu protégé par le droit d'auteur, compte frauduleux…"></textarea>
        </label>
        <div class="modal-actions">
            <button class="btn btn-light" type="button" data-close>Annuler</button>
            <button class="btn btn-danger" type="submit"><?= icon('trash') ?>Supprimer</button>
        </div>
    </form>
</dialog>
<?php require __DIR__ . '/includes/footer.php'; ?>
