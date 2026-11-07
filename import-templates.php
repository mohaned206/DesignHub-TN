<?php
// import-templates.php : publie en une fois plusieurs templates déposés dans le dossier import/.
// Chaque template = une paire de fichiers de même nom : logo-cafe.jpg (aperçu) + logo-cafe.psd (source).
// Outil d'administration, utilisable uniquement en local.
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/upload.php';

if (!in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1'], true)) {
    http_response_code(403);
    exit('Outil réservé à la machine locale.');
}

$dir = __DIR__ . '/import/';
$imageExt  = ['jpg', 'jpeg', 'png', 'webp'];
$sourceExt = ['psd', 'zip', 'rar', '7z', 'ai', 'fig', 'xd', 'sketch', 'svg', 'pdf'];

// Repère les paires aperçu + source (même nom, extensions différentes)
$previews = [];
$sources = [];
foreach (is_dir($dir) ? scandir($dir) : [] as $f) {
    if (!is_file($dir . $f)) {
        continue;
    }
    $ext  = strtolower(pathinfo($f, PATHINFO_EXTENSION));
    $base = strtolower(pathinfo($f, PATHINFO_FILENAME));
    if (in_array($ext, $imageExt, true)) {
        $previews[$base] = $f;
    } elseif (in_array($ext, $sourceExt, true)) {
        $sources[$base] = $f;
    }
}
$pairs = array_intersect_key($previews, $sources);
ksort($pairs);
$orphans = array_merge(array_diff_key($previews, $sources), array_diff_key($sources, $previews));

function title_from_filename(string $file): string
{
    $name = str_replace(['-', '_'], ' ', pathinfo($file, PATHINFO_FILENAME));
    return mb_substr(mb_convert_case(trim($name), MB_CASE_TITLE, 'UTF-8'), 0, 120);
}

$designers = db()->query("SELECT id, full_name FROM users WHERE role = 'designer' ORDER BY full_name")->fetchAll();
$report = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $authorId = (int) ($_POST['author_id'] ?? 0);
    $category = $_POST['category'] ?? '';
    $price    = $_POST['price'] ?? '0';
    $folder   = trim($_POST['folder'] ?? '');
    $validAuthor = in_array($authorId, array_column($designers, 'id'), true);

    if (!$validAuthor || !in_array($category, RESOURCE_CATEGORIES, true) || !is_numeric($price) || $price < 0 || $price > 10000 || mb_strlen($folder) > 100) {
        $report[] = ['ko', 'Paramètres invalides (designer, catégorie ou prix).'];
    } else {
        ensure_dir(PREVIEW_DIR);
        ensure_dir(SOURCE_DIR);
        ensure_dir($dir . 'termine/');
        $insert = db()->prepare('INSERT INTO resources (author_id, title, category, folder, description, price, image_url, file_path, file_name, file_size)
                                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
        foreach ($pairs as $base => $imgFile) {
            $srcFile = $sources[$base];
            $info = @getimagesize($dir . $imgFile);
            if (!$info || !isset(PREVIEW_TYPES[$info[2]])) {
                $report[] = ['ko', "$imgFile : image illisible (JPG, PNG ou WebP attendu)."];
                continue;
            }
            $previewName = random_name(PREVIEW_TYPES[$info[2]]);
            $sourceName  = random_name(strtolower(pathinfo($srcFile, PATHINFO_EXTENSION)));
            $size = filesize($dir . $srcFile);

            if (!copy($dir . $imgFile, PREVIEW_DIR . $previewName) || !copy($dir . $srcFile, SOURCE_DIR . $sourceName)) {
                $report[] = ['ko', "$imgFile : copie impossible (droits du dossier uploads/)."];
                continue;
            }
            $title = title_from_filename($imgFile);
            $insert->execute([$authorId, $title, $category, $folder !== '' ? $folder : null, '', $price, 'uploads/previews/' . $previewName,
                              $sourceName, clean_filename($srcFile), $size]);
            rename($dir . $imgFile, $dir . 'termine/' . $imgFile);
            rename($dir . $srcFile, $dir . 'termine/' . $srcFile);
            $report[] = ['ok', "« $title » publié ($srcFile, " . format_size($size) . ')'];
        }
        if (!$pairs) {
            $report[] = ['ko', 'Aucune paire aperçu + source trouvée dans le dossier import/.'];
        }
        $pairs = [];   // la liste affichée en dessous est maintenant vide
    }
}

$page_title = 'Import de templates';
$active = '';
require __DIR__ . '/includes/header.php';
?>
<h1>Import de templates</h1>
<p class="muted lead-sm">Déposez vos fichiers dans le dossier <code>import/</code> par paires de même nom (<code>logo-cafe.jpg</code> + <code>logo-cafe.psd</code>), puis publiez-les en un clic.</p>

<?php foreach ($report as [$type, $msg]): ?>
    <div class="alert alert-<?= $type === 'ok' ? 'success' : 'error' ?>"><?= e($msg) ?></div>
<?php endforeach; ?>

<section class="card">
    <h2>Templates détectés (<?= count($pairs) ?>)</h2>
    <?php if (!$pairs): ?>
        <p class="empty">Aucune paire détectée dans <code>import/</code>.</p>
    <?php endif; ?>
    <?php foreach ($pairs as $base => $imgFile): ?>
        <p class="small"><?= icon('check-circle') ?> <strong><?= e(title_from_filename($imgFile)) ?></strong>
           <span class="muted">· <?= e($imgFile) ?> + <?= e($sources[$base]) ?> (<?= e(format_size(filesize($dir . $sources[$base]))) ?>)</span></p>
    <?php endforeach; ?>
    <?php foreach ($orphans as $file): ?>
        <p class="small muted"><?= icon('alert') ?> <?= e($file) ?> : pas de fichier du même nom pour former une paire (ignoré).</p>
    <?php endforeach; ?>
</section>

<?php if ($pairs): ?>
<section class="card">
    <h2>Publier</h2>
    <form method="post">
        <?= csrf_field() ?>
        <div class="form-row">
            <label>Designer auteur
                <select name="author_id"><?php foreach ($designers as $d): ?><option value="<?= (int) $d['id'] ?>"><?= e($d['full_name']) ?></option><?php endforeach; ?></select>
            </label>
            <label>Catégorie
                <select name="category"><?php foreach (RESOURCE_CATEGORIES as $c): ?><option value="<?= e($c) ?>"><?= e($c) ?></option><?php endforeach; ?></select>
            </label>
            <label>Prix (TND, 0 = gratuit)
                <input type="number" name="price" min="0" step="1" value="0">
            </label>
        </div>
        <label>Dossier (facultatif : tous les templates importés seront rangés dedans)
            <input type="text" name="folder" maxlength="100" placeholder="ex : Templates Restaurant">
        </label>
        <p class="small muted">Titre = nom du fichier mis en forme. Vous pourrez ensuite supprimer ou republier une ressource depuis « Modifier mon profil ».</p>
        <button class="btn" type="submit">Importer <?= count($pairs) ?> template(s)</button>
    </form>
</section>
<?php endif; ?>
<?php require __DIR__ . '/includes/footer.php'; ?>
