<?php
// designer-profile.php?id=3 : profil public d'un designer (infos, portfolio, avis)
require_once __DIR__ . '/includes/functions.php';

$id = (int) ($_GET['id'] ?? 0);

$stmt = db()->prepare(designers_base_sql() . ' AND u.id = ?');
$stmt->execute([$id]);
$designer = $stmt->fetch();

if (!$designer) {
    http_response_code(404);
    $page_title = 'Designer introuvable';
    $active = 'designers';
    require __DIR__ . '/includes/header.php';
    echo '<p class="empty">Ce designer n\'existe pas. <a href="designers.php">Retour à l\'annuaire</a></p>';
    require __DIR__ . '/includes/footer.php';
    exit;
}

// Portfolio
$stmt = db()->prepare('SELECT * FROM portfolio_items WHERE designer_id = ? ORDER BY created_at DESC, id DESC');
$stmt->execute([$id]);
$portfolio = $stmt->fetchAll();

// Avis (on joint le projet et le client pour l'affichage)
$stmt = db()->prepare('SELECT r.rating, r.comment, r.created_at, u.full_name AS client_name, p.title AS project_title
                       FROM reviews r
                       JOIN users u ON u.id = r.client_id
                       JOIN projects p ON p.id = r.project_id
                       WHERE r.designer_id = ? ORDER BY r.created_at DESC, r.id DESC');
$stmt->execute([$id]);
$reviews = $stmt->fetchAll();

// Si l'utilisateur est un client : ses projets encore ouverts qu'il n'a pas déjà proposés à ce designer
$user = current_user();
$openProjects = [];
if ($user && $user['role'] === 'client') {
    $stmt = db()->prepare("SELECT id, title FROM projects
                           WHERE client_id = ? AND status = 'ouvert'
                             AND id NOT IN (SELECT project_id FROM project_invitations WHERE designer_id = ?)");
    $stmt->execute([$user['id'], $id]);
    $openProjects = $stmt->fetchAll();
}

$page_title = $designer['full_name'];
$active = 'designers';
require __DIR__ . '/includes/header.php';
?>
<a href="designers.php" class="small"><?= icon('arrow-left') ?> Retour à l'annuaire</a>

<section class="card profile-head">
    <?= avatar_html($designer['full_name'], $designer['avatar_url'], 'lg') ?>
    <div>
        <h1><?= e($designer['full_name']) ?></h1>
        <p class="accent"><?= e($designer['specialty']) ?> · <?= icon('pin') ?> <?= e($designer['city']) ?></p>
        <p><?= rating_html($designer['avg_rating'], $designer['nb_reviews']) ?></p>
        <p><strong><?= e(number_format($designer['hourly_rate'], 0)) ?> TND / heure</strong></p>
        <div class="tags">
            <?php foreach (split_skills($designer['skills']) as $s): ?><span class="tag"><?= e($s) ?></span><?php endforeach; ?>
        </div>
    </div>
</section>

<section>
    <h2>À propos</h2>
    <p><?= nl2br(e($designer['bio'] ?: 'Ce designer n\'a pas encore rédigé de présentation.')) ?></p>
</section>

<?php if ($user && $user['id'] === (int) $designer['id']): ?>
    <p><a class="btn btn-small" href="edit-profile.php">Modifier mon profil et mon portfolio</a></p>
<?php elseif ($user && $user['role'] === 'client'): ?>
    <section class="card">
        <h2>Proposer un projet à ce designer</h2>
        <?php if ($openProjects): ?>
            <form method="post" action="project-action.php" class="inline-fields">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="invite">
                <input type="hidden" name="designer_id" value="<?= (int) $designer['id'] ?>">
                <input type="hidden" name="back" value="designer-profile.php?id=<?= (int) $designer['id'] ?>">
                <select name="project_id" required>
                    <?php foreach ($openProjects as $p): ?><option value="<?= (int) $p['id'] ?>"><?= e($p['title']) ?></option><?php endforeach; ?>
                </select>
                <button class="btn" type="submit">Envoyer l'invitation</button>
            </form>
        <?php else: ?>
            <p class="muted small">Aucun projet ouvert disponible. <a href="projects.php">Publiez un projet</a> d'abord (ou ce designer a déjà été invité à tous vos projets ouverts).</p>
        <?php endif; ?>
    </section>
<?php elseif (!$user): ?>
    <p class="muted small"><a href="login.php">Connectez-vous</a> en tant que client pour proposer un projet à ce designer.</p>
<?php endif; ?>

<section>
    <h2>Portfolio (<?= count($portfolio) ?>)</h2>
    <?php if (!$portfolio): ?><p class="empty">Aucune réalisation pour le moment.</p><?php endif; ?>
    <div class="grid grid-3">
        <?php foreach ($portfolio as $item): ?>
            <article class="card media-card">
                <img src="<?= e($item['image_url']) ?>" alt="<?= e($item['title']) ?>" data-fallback="placeholder" data-lightbox data-caption="<?= e($item['title']) ?>">
                <div class="card-body">
                    <span class="tag"><?= e($item['category']) ?></span>
                    <h3><?= e($item['title']) ?></h3>
                    <p class="small muted"><?= e($item['description']) ?></p>
                </div>
            </article>
        <?php endforeach; ?>
    </div>
</section>

<section>
    <h2>Avis des clients (<?= count($reviews) ?>)</h2>
    <?php if (!$reviews): ?><p class="empty">Pas encore d'avis.</p><?php endif; ?>
    <?php foreach ($reviews as $r): ?>
        <div class="card review">
            <?= stars_html((int) $r['rating']) ?>
            <strong><?= e($r['client_name']) ?></strong> · <span class="muted small"><?= e($r['project_title']) ?></span>
            <p class="small"><?= e($r['comment']) ?></p>
        </div>
    <?php endforeach; ?>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
