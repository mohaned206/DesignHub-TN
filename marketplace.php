<?php
// marketplace.php : ressources graphiques (templates, mockups, packs), rangées en dossiers.
//  - page principale : dossiers + ressources sans dossier ; avec une recherche/un filtre : liste à plat
//  - marketplace.php?folder=Nom&author=3 : contenu d'un dossier (galerie, clic sur une image = agrandissement)
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/upload.php';   // format_size()

$user     = current_user();
$uid      = $user ? $user['id'] : 0;
$category = $_GET['category'] ?? '';
$q        = trim($_GET['q'] ?? '');
$price    = $_GET['price'] ?? '';          // '' | free | paid
$sort     = $_GET['sort'] ?? 'recent';     // recent | popular | price_asc | price_desc
$folder   = trim($_GET['folder'] ?? '');
$folderAuthor = (int) ($_GET['author'] ?? 0);

// Tri : liste blanche (jamais de valeur du navigateur directement dans le SQL)
$orders = [
    'recent'     => 'r.created_at DESC, r.id DESC',
    'popular'    => 'nb_orders DESC, r.id DESC',
    'price_asc'  => 'r.price ASC, r.id DESC',
    'price_desc' => 'r.price DESC, r.id DESC',
];
if (!isset($orders[$sort])) {
    $sort = 'recent';
}

// Le 1er paramètre ci-dessous correspond au "?" du sous-select "déjà obtenu"
$sql = 'SELECT r.*, u.full_name AS author_name,
               (SELECT COUNT(*) FROM orders o WHERE o.resource_id = r.id) AS nb_orders,
               (SELECT COUNT(*) FROM orders o WHERE o.resource_id = r.id AND o.buyer_id = ?) AS owned
        FROM resources r JOIN users u ON u.id = r.author_id WHERE 1=1';
$params = [$uid];
if ($folder !== '') {
    $sql .= ' AND r.folder = ? AND r.author_id = ?';
    array_push($params, $folder, $folderAuthor);
}
if (in_array($category, RESOURCE_CATEGORIES, true)) {
    $sql .= ' AND r.category = ?';
    $params[] = $category;
}
if ($q !== '') {
    $sql .= ' AND (r.title LIKE ? OR r.description LIKE ?)';
    array_push($params, "%$q%", "%$q%");
}
if ($price === 'free') {
    $sql .= ' AND r.price = 0';
} elseif ($price === 'paid') {
    $sql .= ' AND r.price > 0';
}
$sql .= ' ORDER BY ' . $orders[$sort];

$stmt = db()->prepare($sql);
$stmt->execute($params);
$resources = $stmt->fetchAll();

// Vue principale sans filtre : on range les ressources par dossier
$isFiltered = $category !== '' || $q !== '' || $price !== '';
$groupByFolder = $folder === '' && !$isFiltered;
$folders = [];
$loose = [];
if ($groupByFolder) {
    foreach ($resources as $r) {
        if ($r['folder'] !== null && $r['folder'] !== '') {
            $key = $r['author_id'] . '|' . $r['folder'];
            $folders[$key] ??= ['name' => $r['folder'], 'author_id' => $r['author_id'], 'author_name' => $r['author_name'], 'items' => []];
            $folders[$key]['items'][] = $r;
        } else {
            $loose[] = $r;
        }
    }
}

$page_title = $folder !== '' ? $folder : 'Marketplace';
$active = 'marketplace';
require __DIR__ . '/includes/header.php';

// Adresse de la page courante (pour revenir au même endroit après une commande)
$here = 'marketplace.php' . (($_SERVER['QUERY_STRING'] ?? '') !== '' && preg_match('#^[a-zA-Z0-9=&_%+.-]*$#', ($_SERVER['QUERY_STRING'] ?? ''))
        ? '?' . ($_SERVER['QUERY_STRING'] ?? '') : '');
?>
<?php if ($folder !== ''): ?>
    <p class="crumbs"><a href="marketplace.php"><?= icon('arrow-left') ?> Marketplace</a></p>
    <h1><?= icon('folder') ?> <?= e($folder) ?></h1>
    <p class="muted lead-sm"><?= count($resources) ?> ressource(s)<?= $resources ? ' · par ' . e($resources[0]['author_name']) : '' ?> · cliquez sur une image pour l'agrandir.</p>
<?php else: ?>
    <h1>Marketplace de ressources graphiques</h1>
    <p class="muted lead-sm">Templates, mockups et packs graphiques créés par les designers tunisiens. Les designers publient leurs ressources depuis <a href="edit-profile.php">leur profil</a>.</p>

    <div class="chips">
        <a class="chip <?= $category === '' ? 'active' : '' ?>" href="marketplace.php">Tout</a>
        <?php foreach (RESOURCE_CATEGORIES as $c): ?>
            <a class="chip <?= $category === $c ? 'active' : '' ?>" href="marketplace.php?category=<?= urlencode($c) ?>"><?= e($c) ?></a>
        <?php endforeach; ?>
    </div>

    <form method="get" class="filters card">
        <?php if ($category): ?><input type="hidden" name="category" value="<?= e($category) ?>"><?php endif; ?>
        <input type="search" name="q" value="<?= e($q) ?>" placeholder="Chercher une ressource…">
        <label>Prix
            <select name="price">
                <option value="">Tous</option>
                <option value="free" <?= $price === 'free' ? 'selected' : '' ?>>Gratuit</option>
                <option value="paid" <?= $price === 'paid' ? 'selected' : '' ?>>Payant</option>
            </select>
        </label>
        <label>Trier par
            <select name="sort">
                <option value="recent" <?= $sort === 'recent' ? 'selected' : '' ?>>Plus récentes</option>
                <option value="popular" <?= $sort === 'popular' ? 'selected' : '' ?>>Plus obtenues</option>
                <option value="price_asc" <?= $sort === 'price_asc' ? 'selected' : '' ?>>Prix croissant</option>
                <option value="price_desc" <?= $sort === 'price_desc' ? 'selected' : '' ?>>Prix décroissant</option>
            </select>
        </label>
        <button class="btn" type="submit">Filtrer</button>
    </form>
<?php endif; ?>

<?php if ($groupByFolder && $folders): ?>
    <h2>Dossiers</h2>
    <div class="grid grid-4">
        <?php foreach ($folders as $f):
            $covers = array_slice(array_values(array_filter(array_column($f['items'], 'image_url'))), 0, 4); ?>
            <a class="card folder-card" href="marketplace.php?folder=<?= urlencode($f['name']) ?>&amp;author=<?= (int) $f['author_id'] ?>">
                <div class="folder-cover cover-<?= max(1, count($covers)) ?>">
                    <?php foreach ($covers as $src): ?><img src="<?= e($src) ?>" alt="" data-fallback="placeholder"><?php endforeach; ?>
                    <?php if (!$covers): ?><div class="img-placeholder"><?= icon('folder') ?></div><?php endif; ?>
                </div>
                <div class="card-body">
                    <span class="tag">Dossier</span>
                    <h3><?= e($f['name']) ?></h3>
                    <p class="small muted"><?= count($f['items']) ?> ressource(s) · par <?= e($f['author_name']) ?></p>
                </div>
            </a>
        <?php endforeach; ?>
    </div>
    <?php if ($loose): ?><h2>Autres ressources</h2><?php endif; ?>
<?php endif; ?>

<?php $list = $groupByFolder ? $loose : $resources; ?>
<?php if (!$groupByFolder): ?><p class="muted small"><?= count($resources) ?> ressource(s)</p><?php endif; ?>
<div class="grid grid-4">
    <?php foreach ($list as $r): include __DIR__ . '/includes/resource_card.php'; endforeach; ?>
</div>
<?php if (!$resources): ?><p class="empty">Aucune ressource trouvée.</p><?php endif; ?>
<?php require __DIR__ . '/includes/footer.php'; ?>
