<?php
// trends.php : analyse des tendances du marché (offre / demande, compétences, activité, marketplace)
// Données 100 % issues de la base : comptages et moyennes, sans chiffre inventé.
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/trends.php';

$t = get_trends();
$cats = $t['categories'];

// Largeur d'une barre en % (entier), au moins 3 % si la valeur n'est pas nulle
function bar_pct($value, $max): int
{
    if ($max <= 0 || $value <= 0) {
        return 0;
    }
    return max(3, (int) round(100 * $value / $max));
}

$maxProjects = max(1, ...array_column($cats, 'projects'));
$maxDesigners = max(1, ...array_column($cats, 'designers'));
$maxSkill = 1;
foreach ($t['skills'] as $s) {
    $maxSkill = max($maxSkill, $s['demand'], $s['supply']);
}
$maxMonth = max(1, ...array_column($t['months'], 'count'));

// Constats générés automatiquement
$insights = [];
$topCat = null;
foreach ($cats as $c) {
    if ($topCat === null || $c['projects'] > $topCat['projects']) {
        $topCat = $c;
    }
}
if ($topCat && $topCat['projects'] > 0) {
    $insights[] = 'Catégorie la plus demandée : ' . $topCat['category'] . ' (' . $topCat['projects'] . ' projet(s), budget moyen ' . number_format($topCat['avg_budget'], 0, ',', ' ') . ' TND).';
}
foreach ($cats as $c) {
    if ($c['level_key'] === 'high') {
        $insights[] = '« ' . $c['category'] . ' » : forte demande face au nombre de designers inscrits, un bon moment pour s\'y positionner.';
    }
}
foreach ($t['skills'] as $s) {
    if ($s['demand'] > $s['supply']) {
        $insights[] = 'Compétence « ' . $s['skill'] . ' » : demandée dans ' . $s['demand'] . ' projet(s) mais déclarée par ' . $s['supply'] . ' designer(s).';
        break;
    }
}

$page_title = 'Tendances';
$active = 'trends';
require __DIR__ . '/includes/header.php';
?>
<h1>Tendances du marché</h1>
<p class="muted lead-sm">Ce que demandent les clients, ce que proposent les designers, et où se trouvent les opportunités.</p>

<div class="grid grid-4 stat-grid">
    <div class="card stat"><strong><?= (int) $t['kpi']['open_projects'] ?></strong><span>Projet(s) ouvert(s)</span></div>
    <div class="card stat"><strong><?= e(number_format($t['kpi']['avg_budget'], 0, ',', ' ')) ?></strong><span>TND de budget moyen</span></div>
    <div class="card stat"><strong><?= e(number_format($t['kpi']['avg_rate'], 0)) ?></strong><span>TND/h de tarif moyen</span></div>
    <div class="card stat"><strong><?= (int) $t['kpi']['orders'] ?></strong><span>Ressource(s) obtenue(s) (<?= (int) $t['kpi']['resources'] ?> au catalogue)</span></div>
</div>

<?php if ($insights): ?>
    <section class="card info-box">
        <h2>À retenir</h2>
        <ul class="small">
            <?php foreach ($insights as $i): ?><li><?= e($i) ?></li><?php endforeach; ?>
        </ul>
    </section>
<?php endif; ?>

<section class="card">
    <h2>Demande et offre par catégorie</h2>
    <p class="small muted">Demande = projets publiés (hors annulés) · Offre = designers dont c'est la spécialité.</p>
    <?php foreach ($cats as $c): ?>
        <div class="trend-row">
            <div class="trend-label">
                <strong><?= e($c['category']) ?></strong>
                <span class="badge level-<?= e($c['level_key']) ?>"><?= e($c['level']) ?></span>
            </div>
            <div class="trend-bars">
                <div class="bar-line"><span class="bar-name">Projets</span>
                    <div class="bar"><span style="width:<?= bar_pct($c['projects'], $maxProjects) ?>%"></span></div>
                    <span class="bar-val"><?= (int) $c['projects'] ?></span></div>
                <div class="bar-line"><span class="bar-name">Designers</span>
                    <div class="bar bar-alt"><span style="width:<?= bar_pct($c['designers'], $maxDesigners) ?>%"></span></div>
                    <span class="bar-val"><?= (int) $c['designers'] ?></span></div>
            </div>
            <div class="trend-meta small muted">
                Budget moyen : <strong><?= $c['projects'] ? e(number_format($c['avg_budget'], 0, ',', ' ')) . ' TND' : '—' ?></strong><br>
                Tarif moyen : <strong><?= $c['designers'] ? e(number_format($c['avg_rate'], 0)) . ' TND/h' : '—' ?></strong>
            </div>
        </div>
    <?php endforeach; ?>
</section>

<div class="grid grid-2">
    <section class="card">
        <h2>Compétences les plus demandées</h2>
        <p class="small muted">Demande (projets) face à l'offre (designers qui la déclarent).</p>
        <?php if (!$t['skills']): ?><p class="empty">Pas encore de données.</p><?php endif; ?>
        <?php foreach ($t['skills'] as $s): ?>
            <div class="skill-row">
                <strong class="small"><?= e($s['skill']) ?></strong>
                <div class="bar-line"><span class="bar-name">Demande</span>
                    <div class="bar"><span style="width:<?= bar_pct($s['demand'], $maxSkill) ?>%"></span></div>
                    <span class="bar-val"><?= (int) $s['demand'] ?></span></div>
                <div class="bar-line"><span class="bar-name">Offre</span>
                    <div class="bar bar-alt"><span style="width:<?= bar_pct($s['supply'], $maxSkill) ?>%"></span></div>
                    <span class="bar-val"><?= (int) $s['supply'] ?></span></div>
            </div>
        <?php endforeach; ?>
    </section>

    <section class="card">
        <h2>Projets publiés (6 derniers mois)</h2>
        <div class="month-chart">
            <?php foreach ($t['months'] as $m): ?>
                <div class="month-col">
                    <span class="bar-val"><?= (int) $m['count'] ?></span>
                    <div class="month-bar" style="height:<?= bar_pct($m['count'], $maxMonth) ?>%"></div>
                    <span class="small muted"><?= e($m['label']) ?></span>
                </div>
            <?php endforeach; ?>
        </div>

        <h2>Marketplace</h2>
        <?php foreach ($t['top_resources'] as $r): ?>
            <p class="small"><span class="tag"><?= e($r['category']) ?></span> <?= e($r['title']) ?>
               <span class="muted">· <?= (int) $r['nb_orders'] ?> obtention(s) · <?= e(format_price($r['price'])) ?></span></p>
        <?php endforeach; ?>
        <?php if (!$t['top_resources']): ?><p class="empty">Aucune ressource.</p><?php endif; ?>
    </section>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
