<?php
// designers.php : annuaire des designers avec recherche et filtres
require_once __DIR__ . '/includes/functions.php';

// Les filtres arrivent par l'URL (méthode GET) : designers.php?q=figma&specialty=UI/UX Design
$q         = trim($_GET['q'] ?? '');
$specialty = $_GET['specialty'] ?? '';
$city      = $_GET['city'] ?? '';

// On construit la requête morceau par morceau ; les valeurs vont dans $params (requête préparée)
$sql    = designers_base_sql();
$params = [];

if ($q !== '') {
    // recherche dans le nom, les compétences et la spécialité
    $sql .= ' AND (u.full_name LIKE ? OR p.skills LIKE ? OR p.specialty LIKE ?)';
    array_push($params, "%$q%", "%$q%", "%$q%");
}
if (in_array($specialty, CATEGORIES, true)) {
    $sql .= ' AND p.specialty = ?';
    $params[] = $specialty;
}
if (in_array($city, CITIES, true)) {
    $sql .= ' AND p.city = ?';
    $params[] = $city;
}
$sql .= ' ORDER BY u.full_name';

$stmt = db()->prepare($sql);
$stmt->execute($params);
$designers = $stmt->fetchAll();

$page_title = 'Designers';
$active = 'designers';
require __DIR__ . '/includes/header.php';
?>
<h1>Annuaire des designers</h1>

<form method="get" class="filters card">
    <input type="search" name="q" value="<?= e($q) ?>" placeholder="Nom, compétence (ex : figma)…">
    <select name="specialty">
        <option value="">Toutes les spécialités</option>
        <?php foreach (CATEGORIES as $c): ?>
            <option value="<?= e($c) ?>" <?= $specialty === $c ? 'selected' : '' ?>><?= e($c) ?></option>
        <?php endforeach; ?>
    </select>
    <select name="city">
        <option value="">Toutes les villes</option>
        <?php foreach (CITIES as $c): ?>
            <option value="<?= e($c) ?>" <?= $city === $c ? 'selected' : '' ?>><?= e($c) ?></option>
        <?php endforeach; ?>
    </select>
    <button class="btn" type="submit">Rechercher</button>
    <a class="btn btn-light" href="designers.php">Réinitialiser</a>
</form>

<p class="muted"><?= count($designers) ?> designer(s) trouvé(s)</p>
<div class="grid grid-3">
    <?php foreach ($designers as $d) { echo designer_card($d); } ?>
</div>
<?php if (!$designers): ?>
    <p class="empty">Aucun designer ne correspond à ces critères.</p>
<?php endif; ?>
<?php require __DIR__ . '/includes/footer.php'; ?>
