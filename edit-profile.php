<?php
// edit-profile.php : le designer modifie son profil, son portfolio et ses ressources (CRUD)
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/upload.php';

$user = require_role('designer');
$pdo  = db();
$errors = [];

// Fichier plus gros que post_max_size : PHP vide alors $_POST (donc le jeton CSRF) : on l'explique clairement
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$_POST && (int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) {
    set_flash('error', 'Envoi trop volumineux pour le serveur (post_max_size = ' . ini_get('post_max_size') . '). Réduisez le fichier ou augmentez cette valeur dans php.ini.');
    redirect('edit-profile.php');
}

// ---------- Traitement des formulaires (un champ caché "action" indique lequel) ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = $_POST['action'] ?? '';

    // --- Modifier le profil (UPDATE) ---
    if ($action === 'profile') {
        $specialty = $_POST['specialty'] ?? '';
        $city      = $_POST['city'] ?? '';
        $rate      = trim($_POST['hourly_rate'] ?? '');
        $bio       = trim($_POST['bio'] ?? '');
        $skills    = clean_skills($_POST['skills'] ?? '');

        if (!in_array($specialty, CATEGORIES, true)) $errors[] = 'Spécialité invalide.';
        if (!in_array($city, CITIES, true))          $errors[] = 'Ville invalide.';
        if (!is_numeric($rate) || $rate < 1 || $rate > 1000) $errors[] = 'Le tarif horaire doit être entre 1 et 1000 TND.';
        if (mb_strlen($bio) > 1000)                  $errors[] = 'La présentation est limitée à 1000 caractères.';

        if (!$errors) {
            // Photo actuelle : conservée, remplacée par un nouvel envoi, ou supprimée
            $stmt = $pdo->prepare('SELECT avatar_url FROM designer_profiles WHERE user_id = ?');
            $stmt->execute([$user['id']]);
            $oldAvatar = $stmt->fetchColumn() ?: null;
            $avatar = $oldAvatar;
            $newAvatar = store_preview($_FILES['avatar_file'] ?? [], $errors);
            if ($newAvatar) {
                $avatar = $newAvatar;
            } elseif (!empty($_POST['remove_avatar'])) {
                $avatar = null;
            }
        }

        if (!$errors) {
            $stmt = $pdo->prepare('UPDATE designer_profiles SET specialty = ?, skills = ?, city = ?, hourly_rate = ?, bio = ?, avatar_url = ? WHERE user_id = ?');
            $stmt->execute([$specialty, $skills, $city, $rate, $bio, $avatar, $user['id']]);
            if ($oldAvatar && $oldAvatar !== $avatar) {
                delete_resource_files(['image_url' => $oldAvatar]);   // supprime l'ancien fichier s'il avait été envoyé par upload
            }
            set_flash('success', 'Profil mis à jour.');
            redirect('edit-profile.php');
        }
    }

    // --- Ajouter une réalisation (INSERT) ---
    if ($action === 'add_portfolio') {
        $title = trim($_POST['title'] ?? '');
        $cat   = $_POST['category'] ?? '';
        $desc  = trim($_POST['description'] ?? '');

        if (mb_strlen($title) < 3 || mb_strlen($title) > 120) $errors[] = 'Le titre de la réalisation doit contenir 3 à 120 caractères.';
        if (!in_array($cat, CATEGORIES, true))               $errors[] = 'Catégorie de réalisation invalide.';
        if (($_FILES['image_file']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) $errors[] = 'Ajoutez l\'image de la réalisation.';
        if (mb_strlen($desc) > 500)                          $errors[] = 'Description limitée à 500 caractères.';

        if (!$errors) {
            $image = store_preview($_FILES['image_file'], $errors);
            if (!$errors) {
                $stmt = $pdo->prepare('INSERT INTO portfolio_items (designer_id, title, category, image_url, description) VALUES (?, ?, ?, ?, ?)');
                $stmt->execute([$user['id'], $title, $cat, $image, $desc]);
                set_flash('success', 'Réalisation ajoutée au portfolio.');
                redirect('edit-profile.php');
            }
        }
    }

    // --- Supprimer une réalisation (DELETE) : la condition designer_id empêche de supprimer celle d'un autre ---
    if ($action === 'delete_portfolio') {
        $stmt = $pdo->prepare('SELECT image_url FROM portfolio_items WHERE id = ? AND designer_id = ?');
        $stmt->execute([(int) ($_POST['item_id'] ?? 0), $user['id']]);
        $imageToDelete = $stmt->fetchColumn();
        $stmt = $pdo->prepare('DELETE FROM portfolio_items WHERE id = ? AND designer_id = ?');
        $stmt->execute([(int) ($_POST['item_id'] ?? 0), $user['id']]);
        if ($imageToDelete) {
            delete_resource_files(['image_url' => $imageToDelete]);
        }
        set_flash('success', 'Réalisation supprimée.');
        redirect('edit-profile.php');
    }

    // --- Ajouter une ressource à la marketplace ---
    if ($action === 'add_resource') {
        $title = trim($_POST['res_title'] ?? '');
        $cat   = $_POST['res_category'] ?? '';
        $price = trim($_POST['res_price'] ?? '0');
        $desc  = trim($_POST['res_description'] ?? '');
        $folder = trim($_POST['res_folder'] ?? '');

        if (mb_strlen($title) < 3 || mb_strlen($title) > 120) $errors[] = 'Le titre de la ressource doit contenir 3 à 120 caractères.';
        if (!in_array($cat, RESOURCE_CATEGORIES, true))      $errors[] = 'Catégorie de ressource invalide.';
        if (!is_numeric($price) || $price < 0 || $price > 10000) $errors[] = 'Prix invalide (0 = gratuit).';
        if (mb_strlen($desc) > 500)                          $errors[] = 'Description limitée à 500 caractères.';
        if (mb_strlen($folder) > 100)                        $errors[] = 'Nom de dossier limité à 100 caractères.';

        if (($_FILES['res_file']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            $errors[] = 'Ajoutez le fichier source de la ressource (PSD, ZIP...).';
        }

        if (!$errors) {
            $preview = store_preview($_FILES['res_preview'] ?? [], $errors);
            $source  = store_source($_FILES['res_file'] ?? [], $errors);
            if ($errors) {
                // un des deux envois a échoué : on supprime l'autre pour ne rien laisser traîner
                delete_resource_files(['image_url' => $preview, 'file_path' => $source['stored'] ?? null]);
            } else {
                $stmt = $pdo->prepare('INSERT INTO resources (author_id, title, category, folder, description, price, image_url, file_path, file_name, file_size)
                                       VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
                $stmt->execute([$user['id'], $title, $cat, $folder !== '' ? $folder : null, $desc, $price, $preview,
                                $source['stored'], $source['name'], $source['size']]);
                set_flash('success', 'Ressource publiée sur la marketplace.');
                redirect('edit-profile.php');
            }
        }
    }

    // --- Ranger une ressource dans un dossier (ou l'en sortir : champ vide) ---
    if ($action === 'set_folder') {
        $folder = trim($_POST['folder'] ?? '');
        if (mb_strlen($folder) > 100) {
            $errors[] = 'Nom de dossier limité à 100 caractères.';
        } else {
            $pdo->prepare('UPDATE resources SET folder = ? WHERE id = ? AND author_id = ?')
                ->execute([$folder !== '' ? $folder : null, (int) ($_POST['resource_id'] ?? 0), $user['id']]);
            set_flash('success', 'Dossier mis à jour.');
            redirect('edit-profile.php');
        }
    }

    // --- Regrouper d'un coup toutes les ressources qui n'ont pas encore de dossier ---
    if ($action === 'group_all') {
        $folder = trim($_POST['folder'] ?? '');
        if ($folder === '' || mb_strlen($folder) > 100) {
            $errors[] = 'Donnez un nom de dossier (100 caractères maximum).';
        } else {
            $stmt = $pdo->prepare('UPDATE resources SET folder = ? WHERE author_id = ? AND folder IS NULL');
            $stmt->execute([$folder, $user['id']]);
            set_flash('success', $stmt->rowCount() . ' ressource(s) rangée(s) dans le dossier « ' . $folder . ' ».');
            redirect('edit-profile.php');
        }
    }

    // --- Supprimer une de ses ressources ---
    if ($action === 'delete_resource') {
        $stmt = $pdo->prepare('SELECT * FROM resources WHERE id = ? AND author_id = ?');
        $stmt->execute([(int) ($_POST['resource_id'] ?? 0), $user['id']]);
        if ($res = $stmt->fetch()) {
            $pdo->prepare('DELETE FROM resources WHERE id = ?')->execute([$res['id']]);
            delete_resource_files($res);
        }
        set_flash('success', 'Ressource supprimée.');
        redirect('edit-profile.php');
    }
}

// ---------- Lecture des données actuelles ----------
$stmt = $pdo->prepare('SELECT * FROM designer_profiles WHERE user_id = ?');
$stmt->execute([$user['id']]);
$profile = $stmt->fetch();

$stmt = $pdo->prepare('SELECT * FROM portfolio_items WHERE designer_id = ? ORDER BY id DESC');
$stmt->execute([$user['id']]);
$portfolio = $stmt->fetchAll();

$stmt = $pdo->prepare('SELECT * FROM resources WHERE author_id = ? ORDER BY id DESC');
$stmt->execute([$user['id']]);
$resources = $stmt->fetchAll();

// En cas d'erreur de validation, on réaffiche ce que l'utilisateur a saisi
if ($errors && ($_POST['action'] ?? '') === 'profile') {
    $profile = array_merge($profile, [
        'specialty' => $_POST['specialty'] ?? '', 'city' => $_POST['city'] ?? '', 'hourly_rate' => $_POST['hourly_rate'] ?? '',
        'bio' => $_POST['bio'] ?? '', 'skills' => $_POST['skills'] ?? '',
    ]);
}

$page_title = 'Mon profil';
$active = '';
require __DIR__ . '/includes/header.php';
?>
<h1>Mon profil designer</h1>
<?php if ($errors): ?>
    <div class="alert alert-error"><?php foreach ($errors as $err): ?><div><?= e($err) ?></div><?php endforeach; ?></div>
<?php endif; ?>

<section class="card">
    <h2>Informations</h2>
    <form method="post" enctype="multipart/form-data" novalidate>
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="profile">
        <div class="form-row">
            <label>Spécialité principale
                <select name="specialty">
                    <?php foreach (CATEGORIES as $c): ?><option value="<?= e($c) ?>" <?= $profile['specialty'] === $c ? 'selected' : '' ?>><?= e($c) ?></option><?php endforeach; ?>
                </select>
            </label>
            <label>Ville
                <select name="city">
                    <?php foreach (CITIES as $c): ?><option value="<?= e($c) ?>" <?= $profile['city'] === $c ? 'selected' : '' ?>><?= e($c) ?></option><?php endforeach; ?>
                </select>
            </label>
            <label>Tarif horaire (TND)
                <input type="number" name="hourly_rate" min="1" max="1000" step="1" value="<?= e(is_numeric($profile['hourly_rate']) ? $profile['hourly_rate'] + 0 : '') ?>" required>
            </label>
        </div>
        <label>Compétences (séparées par des virgules)
            <input type="text" name="skills" value="<?= e($profile['skills']) ?>" placeholder="figma, illustrator, blender" maxlength="255">
        </label>
        <div class="profile-head">
            <?= avatar_html($user['full_name'], $profile['avatar_url'], 'lg') ?>
            <div>
                <label>Photo de profil (JPG, PNG ou WebP, 5 Mo max)
                    <input type="file" name="avatar_file" accept="image/jpeg,image/png,image/webp">
                </label>
                <?php if ($profile['avatar_url']): ?>
                    <label class="small"><input type="checkbox" name="remove_avatar" value="1"> Supprimer ma photo actuelle</label>
                <?php endif; ?>
            </div>
        </div>
        <label>Présentation
            <textarea name="bio" rows="4" maxlength="1000"><?= e($profile['bio']) ?></textarea>
        </label>
        <button class="btn" type="submit">Enregistrer le profil</button>
    </form>
</section>

<section class="card">
    <h2>Mon portfolio (<?= count($portfolio) ?>)</h2>
    <div class="grid grid-3">
        <?php foreach ($portfolio as $item): ?>
            <article class="card media-card">
                <img src="<?= e($item['image_url']) ?>" alt="<?= e($item['title']) ?>" data-fallback="placeholder">
                <div class="card-body">
                    <span class="tag"><?= e($item['category']) ?></span>
                    <h3><?= e($item['title']) ?></h3>
                    <form method="post" data-confirm="Supprimer cette réalisation ?">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="delete_portfolio">
                        <input type="hidden" name="item_id" value="<?= (int) $item['id'] ?>">
                        <button class="btn btn-small btn-danger" type="submit">Supprimer</button>
                    </form>
                </div>
            </article>
        <?php endforeach; ?>
    </div>
    <h3>Ajouter une réalisation</h3>
    <form method="post" enctype="multipart/form-data" novalidate>
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="add_portfolio">
        <div class="form-row">
            <label>Titre <input type="text" name="title" maxlength="120" required></label>
            <label>Catégorie
                <select name="category"><?php foreach (CATEGORIES as $c): ?><option value="<?= e($c) ?>"><?= e($c) ?></option><?php endforeach; ?></select>
            </label>
        </div>
        <label>Image de la réalisation (JPG, PNG ou WebP, 5 Mo max)
            <input type="file" name="image_file" accept="image/jpeg,image/png,image/webp" required>
        </label>
        <label>Description <textarea name="description" rows="2" maxlength="500"></textarea></label>
        <button class="btn" type="submit">Ajouter au portfolio</button>
    </form>
</section>

<section class="card">
    <h2>Mes ressources sur la marketplace (<?= count($resources) ?>)</h2>
    <?php if (count($resources) > 1): ?>
        <form method="post" class="filters">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="group_all">
            <label>Ranger toutes les ressources sans dossier dans :
                <input type="text" name="folder" maxlength="100" placeholder="ex : Templates Restaurant" list="folder-list" required>
            </label>
            <button class="btn btn-small" type="submit">Regrouper</button>
        </form>
    <?php endif; ?>
    <datalist id="folder-list">
        <?php foreach (array_unique(array_filter(array_column($resources, 'folder'))) as $fname): ?><option value="<?= e($fname) ?>"><?php endforeach; ?>
    </datalist>
    <?php foreach ($resources as $r): ?>
        <div class="invite-row">
            <span><?= e($r['title']) ?> <span class="tag"><?= e($r['category']) ?></span> <span class="muted small"><?= e(format_price($r['price'])) ?><?= $r['file_name'] ? ' · 📎 ' . e($r['file_name']) . ' (' . e(format_size($r['file_size'])) . ')' : ' · sans fichier' ?></span></span>
            <div class="inline-fields">
                <form method="post" class="inline-fields">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="set_folder">
                    <input type="hidden" name="resource_id" value="<?= (int) $r['id'] ?>">
                    <input type="text" name="folder" maxlength="100" value="<?= e($r['folder'] ?? '') ?>" placeholder="Dossier" list="folder-list" style="margin:0;width:170px" aria-label="Dossier">
                    <button class="btn btn-small btn-light" type="submit">OK</button>
                </form>
                <form method="post" data-confirm="Supprimer cette ressource ?">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="delete_resource">
                    <input type="hidden" name="resource_id" value="<?= (int) $r['id'] ?>">
                    <button class="btn btn-small btn-danger" type="submit">Supprimer</button>
                </form>
            </div>
        </div>
    <?php endforeach; ?>
    <h3>Publier une ressource</h3>
    <form method="post" enctype="multipart/form-data" novalidate>
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="add_resource">
        <div class="form-row">
            <label>Titre <input type="text" name="res_title" maxlength="120" required></label>
            <label>Catégorie
                <select name="res_category"><?php foreach (RESOURCE_CATEGORIES as $c): ?><option value="<?= e($c) ?>"><?= e($c) ?></option><?php endforeach; ?></select>
            </label>
            <label>Prix (TND, 0 = gratuit) <input type="number" name="res_price" min="0" step="1" value="0"></label>
        </div>
        <div class="form-row">
            <label>Aperçu (JPG, PNG ou WebP, 5 Mo max)
                <input type="file" name="res_preview" accept="image/jpeg,image/png,image/webp">
            </label>
            <label>Fichier source (PSD, ZIP, AI, FIG... 100 Mo max)
                <input type="file" name="res_file" accept=".psd,.zip,.rar,.7z,.ai,.fig,.xd,.sketch,.svg,.pdf,.png,.jpg,.jpeg" required>
            </label>
        </div>
        <label>Dossier (facultatif : regroupe plusieurs ressources) <input type="text" name="res_folder" maxlength="100" list="folder-list" placeholder="ex : Templates Restaurant"></label>
        <label>Description <textarea name="res_description" rows="2" maxlength="500"></textarea></label>
        <button class="btn" type="submit">Publier la ressource</button>
    </form>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
