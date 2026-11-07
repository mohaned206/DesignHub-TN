<?php
// matching.php : moteur de matching à base de règles, utilisable dans les deux sens
//  - client   : designers recommandés pour un de ses projets ouverts
//  - designer : projets ouverts qui correspondent le mieux à son profil
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/matching.php';

$user = current_user();
$projects = [];
$project = null;
$results = [];
$invited = [];
$recommended = [];

if ($user && $user['role'] === 'client') {
    // Projets ouverts du client connecté
    $stmt = db()->prepare("SELECT * FROM projects WHERE client_id = ? AND status = 'ouvert' ORDER BY created_at DESC, id DESC");
    $stmt->execute([$user['id']]);
    $projects = $stmt->fetchAll();

    // Projet choisi dans la liste (par défaut : le plus récent)
    $chosenId = (int) ($_GET['project_id'] ?? 0);
    foreach ($projects as $p) {
        if ($p['id'] === $chosenId || ($project === null && !$chosenId)) {
            $project = $p;
        }
    }

    if ($project) {
        // Catégories des réalisations de chaque designer (une seule requête)
        $portfolioByDesigner = [];
        foreach (db()->query('SELECT designer_id, category FROM portfolio_items')->fetchAll() as $row) {
            $portfolioByDesigner[$row['designer_id']][] = $row['category'];
        }
        // Designers déjà invités sur ce projet
        $stmt = db()->prepare('SELECT designer_id, status FROM project_invitations WHERE project_id = ?');
        $stmt->execute([$project['id']]);
        $invited = array_column($stmt->fetchAll(), 'status', 'designer_id');

        foreach (db()->query(designers_base_sql())->fetchAll() as $designer) {
            $match = compute_match($project, $designer, $portfolioByDesigner[$designer['id']] ?? []);
            $results[] = ['designer' => $designer] + $match;
        }
        // Tri : meilleur score d'abord
        usort($results, fn($a, $b) => $b['score'] <=> $a['score']);
    }
} elseif ($user && $user['role'] === 'designer') {
    $recommended = recommend_projects_for_designer($user['id']);
}

$page_title = 'Matching';
$active = 'matching';
require __DIR__ . '/includes/header.php';
?>
<h1>Matching</h1>
<p class="muted lead-sm">Un score de compatibilité sur 100 entre chaque projet et chaque designer, avec le détail de chaque critère.</p>

<details class="card score-help">
    <summary>Comment le score est-il calculé ?</summary>
    <ul class="small">
        <li><strong>Spécialité</strong> (35 pts) : correspondance avec la catégorie du projet.</li>
        <li><strong>Compétences</strong> (30 pts) : part des compétences demandées maîtrisées.</li>
        <li><strong>Budget</strong> (15 pts) : coût estimé (<?= MATCH_HOURS ?> h au tarif du designer) par rapport au budget.</li>
        <li><strong>Portfolio</strong> (10 pts) : réalisations déjà faites dans cette catégorie.</li>
        <li><strong>Réputation</strong> (10 pts) : note moyenne des avis clients.</li>
    </ul>
</details>

<?php if (!$user): ?>
    <p class="empty"><a href="login.php">Connectez-vous</a> pour obtenir des recommandations : les clients voient les designers adaptés à leurs projets, les designers voient les projets qui leur correspondent.</p>

<?php elseif ($user['role'] === 'designer'): ?>
    <h2>Projets ouverts qui vous correspondent</h2>
    <?php if (!$recommended): ?><p class="empty">Aucun projet ouvert pour le moment.</p><?php endif; ?>
    <?php foreach ($recommended as $r): $p = $r['project']; ?>
        <article class="card match-card">
            <div class="score"><?= (int) $r['score'] ?><small>/100 · <?= e(match_label($r['score'])) ?></small></div>
            <div class="match-body">
                <h3><a href="project-details.php?id=<?= (int) $p['id'] ?>"><?= e($p['title']) ?></a></h3>
                <p class="small muted"><?= e($p['category']) ?> · <?= e(number_format($p['budget'], 0)) ?> TND · <?= (int) $p['deadline_days'] ?> jours · Client : <?= e($p['client_name']) ?></p>
                <ul class="criteria">
                    <?php foreach ($r['details'] as $c): ?>
                        <li class="<?= $c['points'] > 0 ? 'ok' : 'ko' ?>"><strong>+<?= (int) $c['points'] ?>/<?= (int) $c['max'] ?></strong> <?= e($c['text']) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <div class="match-actions">
                <a class="btn btn-small" href="project-details.php?id=<?= (int) $p['id'] ?>">Voir et proposer</a>
            </div>
        </article>
    <?php endforeach; ?>

<?php elseif (!$projects): ?>
    <p class="empty">Vous n'avez aucun projet ouvert. <a href="projects.php">Publiez un projet</a> pour voir les recommandations.</p>

<?php else: ?>
    <form method="get" class="filters card">
        <label>Projet à évaluer
            <select name="project_id" data-autosubmit>
                <?php foreach ($projects as $p): ?>
                    <option value="<?= (int) $p['id'] ?>" <?= $project && $project['id'] === $p['id'] ? 'selected' : '' ?>>
                        <?= e($p['title']) ?> (<?= e($p['category']) ?> · <?= e(number_format($p['budget'], 0)) ?> TND)
                    </option>
                <?php endforeach; ?>
            </select>
        </label>
        <button class="btn" type="submit">Calculer</button>
    </form>

    <?php foreach ($results as $r): $d = $r['designer']; ?>
        <article class="card match-card">
            <div class="score"><?= (int) $r['score'] ?><small>/100 · <?= e(match_label($r['score'])) ?></small></div>
            <div class="match-body">
                <h3><a href="designer-profile.php?id=<?= (int) $d['id'] ?>"><?= e($d['full_name']) ?></a></h3>
                <p class="small muted"><?= e($d['specialty']) ?> · <?= e($d['city']) ?> · <?= e(number_format($d['hourly_rate'], 0)) ?> TND/h</p>
                <ul class="criteria">
                    <?php foreach ($r['details'] as $c): ?>
                        <li class="<?= $c['points'] > 0 ? 'ok' : 'ko' ?>">
                            <strong>+<?= (int) $c['points'] ?>/<?= (int) $c['max'] ?></strong> <?= e($c['text']) ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <div class="match-actions">
                <?php if (isset($invited[$d['id']])): ?>
                    <span class="badge badge-<?= e($invited[$d['id']]) ?>">Invité : <?= e(INVITATION_STATUS[$invited[$d['id']]]) ?></span>
                <?php else: ?>
                    <form method="post" action="project-action.php">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="invite">
                        <input type="hidden" name="project_id" value="<?= (int) $project['id'] ?>">
                        <input type="hidden" name="designer_id" value="<?= (int) $d['id'] ?>">
                        <input type="hidden" name="back" value="matching.php?project_id=<?= (int) $project['id'] ?>">
                        <button class="btn btn-small" type="submit">Proposer le projet</button>
                    </form>
                <?php endif; ?>
            </div>
        </article>
    <?php endforeach; ?>
<?php endif; ?>
<?php require __DIR__ . '/includes/footer.php'; ?>
