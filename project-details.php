<?php
// project-details.php?id=1 : détail d'un projet, propositions, invitations, suivi et espace de collaboration
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/matching.php';

$user = require_login();
$id = (int) ($_GET['id'] ?? 0);

$stmt = db()->prepare('SELECT p.*, c.full_name AS client_name, d.full_name AS designer_name
                       FROM projects p
                       JOIN users c ON c.id = p.client_id
                       LEFT JOIN users d ON d.id = p.designer_id
                       WHERE p.id = ?');
$stmt->execute([$id]);
$project = $stmt->fetch();

// Invitations du projet (avec le nom des designers)
$stmt = db()->prepare('SELECT i.id, i.status, i.designer_id, u.full_name
                       FROM project_invitations i JOIN users u ON u.id = i.designer_id
                       WHERE i.project_id = ? ORDER BY i.created_at DESC, i.id DESC');
$stmt->execute([$id]);
$invitations = $stmt->fetchAll();

// Propositions des designers (prix, délai, message)
$stmt = db()->prepare('SELECT pr.*, u.full_name, dp.hourly_rate
                       FROM proposals pr
                       JOIN users u ON u.id = pr.designer_id
                       LEFT JOIN designer_profiles dp ON dp.user_id = pr.designer_id
                       WHERE pr.project_id = ? ORDER BY pr.created_at DESC, pr.id DESC');
$stmt->execute([$id]);
$proposals = $stmt->fetchAll();

// ---------- Contrôle d'accès ----------
// - le client propriétaire : toujours
// - un designer : si le projet est ouvert, ou s'il est invité, ou s'il a proposé, ou s'il est assigné
$isOwner = $project && $project['client_id'] === $user['id'];
$myInvitation = null;
foreach ($invitations as $inv) {
    if ($inv['designer_id'] === $user['id']) {
        $myInvitation = $inv;
    }
}
$myProposal = null;
foreach ($proposals as $pr) {
    if ($pr['designer_id'] === $user['id']) {
        $myProposal = $pr;
    }
}
$isAssigned = $project && $project['designer_id'] === $user['id'];
$allowed = $project && ($isOwner || ($user['role'] === 'designer'
            && ($project['status'] === 'ouvert' || $myInvitation || $myProposal || $isAssigned)));

if (!$allowed) {
    http_response_code($project ? 403 : 404);
    $page_title = 'Projet';
    $active = 'projects';
    require __DIR__ . '/includes/header.php';
    echo '<p class="empty">Projet introuvable ou accès non autorisé. <a href="projects.php">Retour aux projets</a></p>';
    require __DIR__ . '/includes/footer.php';
    exit;
}

// Évaluation déjà donnée ?
$stmt = db()->prepare('SELECT rating, comment FROM reviews WHERE project_id = ?');
$stmt->execute([$id]);
$review = $stmt->fetch();

// Espace de collaboration : client + designer assigné, quand le projet est en cours ou terminé
$isMember = $isOwner || $isAssigned;
$workspace = $isMember && in_array($project['status'], ['en_cours', 'termine'], true);
$canEditTasks = $isMember && $project['status'] === 'en_cours';
$tasks = [];
$messages = [];
if ($workspace) {
    $stmt = db()->prepare('SELECT * FROM project_tasks WHERE project_id = ? ORDER BY id ASC');
    $stmt->execute([$id]);
    $tasks = $stmt->fetchAll();

    $stmt = db()->prepare('SELECT m.*, u.full_name FROM project_messages m
                           JOIN users u ON u.id = m.user_id
                           WHERE m.project_id = ? ORDER BY m.id ASC LIMIT 200');
    $stmt->execute([$id]);
    $messages = $stmt->fetchAll();
}
$progress = progress_map([$id])[$id] ?? null;

// Compatibilité du designer connecté avec ce projet ouvert (même calcul que la page Matching)
$myMatch = null;
if ($user['role'] === 'designer' && !$isOwner && $project['status'] === 'ouvert') {
    $myMatch = match_designer_project($project, $user['id']);
}

$page_title = $project['title'];
$active = 'projects';
$self = 'project-details.php?id=' . $id;
require __DIR__ . '/includes/header.php';
?>
<a href="projects.php" class="small"><?= icon('arrow-left') ?> Retour aux projets</a>

<section class="card">
    <div class="section-head">
        <h1><?= e($project['title']) ?></h1>
        <?= status_badge($project['status']) ?>
    </div>
    <p><span class="tag"><?= e($project['category']) ?></span>
       <?php foreach (split_skills($project['skills']) as $s): ?><span class="tag"><?= e($s) ?></span><?php endforeach; ?></p>
    <p><?= nl2br(e($project['description'])) ?></p>
    <ul class="facts">
        <li><?= icon('coins') ?> Budget : <strong><?= e(number_format($project['budget'], 0)) ?> TND</strong></li>
        <li>⏱ Délai : <strong><?= (int) $project['deadline_days'] ?> jours</strong></li>
        <li><?= icon('user') ?> Client : <strong><?= e($project['client_name']) ?></strong></li>
        <li><?= icon('palette') ?> Designer :
            <?php if ($project['designer_id']): ?>
                <a href="designer-profile.php?id=<?= (int) $project['designer_id'] ?>"><?= e($project['designer_name']) ?></a>
            <?php else: ?><span class="muted">pas encore attribué</span><?php endif; ?></li>
        <li><?= icon('calendar') ?> Publié le <?= e(date('d/m/Y', strtotime($project['created_at']))) ?></li>
    </ul>
</section>

<?php /* ----- Suivi : étapes du statut ----- */ ?>
<section class="steps">
    <?php
    $order = ['ouvert' => 1, 'en_cours' => 2, 'termine' => 3];
    $current = $order[$project['status']] ?? 0;
    foreach (['ouvert' => 'Ouvert', 'en_cours' => 'En cours', 'termine' => 'Terminé'] as $key => $label): ?>
        <div class="step <?= $current >= $order[$key] ? 'done' : '' ?>"><?= e($label) ?></div>
    <?php endforeach; ?>
    <?php if ($project['status'] === 'annule'): ?><div class="step cancelled">Annulé</div><?php endif; ?>
</section>

<?php /* ----- Designer : compatibilité + proposition ----- */ ?>
<?php if ($myMatch): ?>
    <section class="card highlight">
        <h2>Votre compatibilité avec ce projet : <?= (int) $myMatch['score'] ?>/100 <span class="badge"><?= e(match_label($myMatch['score'])) ?></span></h2>
        <ul class="criteria">
            <?php foreach ($myMatch['details'] as $c): ?>
                <li class="<?= $c['points'] > 0 ? 'ok' : 'ko' ?>"><strong>+<?= (int) $c['points'] ?>/<?= (int) $c['max'] ?></strong> <?= e($c['text']) ?></li>
            <?php endforeach; ?>
        </ul>
    </section>
<?php endif; ?>

<?php if ($user['role'] === 'designer' && !$isOwner && $project['status'] === 'ouvert'): ?>
    <section class="card">
        <h2>Ma proposition</h2>
        <?php if ($myProposal): ?>
            <p>Vous avez proposé <strong><?= e(number_format($myProposal['price'], 0)) ?> TND</strong> en
               <strong><?= (int) $myProposal['delay_days'] ?> jours</strong>
               <span class="badge badge-<?= e($myProposal['status']) ?>"><?= e(INVITATION_STATUS[$myProposal['status']]) ?></span></p>
            <p class="small muted"><?= nl2br(e($myProposal['message'])) ?></p>
        <?php else: ?>
            <form method="post" action="project-action.php">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="propose">
                <input type="hidden" name="project_id" value="<?= $id ?>">
                <input type="hidden" name="back" value="<?= e($self) ?>">
                <div class="form-row">
                    <label>Votre prix (TND)
                        <input type="number" name="price" min="1" step="1" value="<?= (int) $project['budget'] ?>" required>
                    </label>
                    <label>Délai (jours)
                        <input type="number" name="delay" min="1" max="365" value="<?= (int) $project['deadline_days'] ?>" required>
                    </label>
                </div>
                <label>Message au client
                    <textarea name="message" rows="3" minlength="10" maxlength="600" placeholder="Présentez votre approche, vos références, ce que vous livrerez…" required></textarea>
                </label>
                <button class="btn" type="submit">Envoyer ma proposition</button>
            </form>
        <?php endif; ?>
    </section>
<?php endif; ?>

<?php /* ----- Actions du designer invité ----- */ ?>
<?php if ($myInvitation && $myInvitation['status'] === 'en_attente' && $project['status'] === 'ouvert'): ?>
    <section class="card highlight">
        <h2>Vous êtes invité sur ce projet</h2>
        <form method="post" action="project-action.php" class="inline-fields">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="respond">
            <input type="hidden" name="invitation_id" value="<?= (int) $myInvitation['id'] ?>">
            <input type="hidden" name="back" value="<?= e($self) ?>">
            <button class="btn" name="answer" value="accept" type="submit">Accepter</button>
            <button class="btn btn-light" name="answer" value="refuse" type="submit">Refuser</button>
        </form>
    </section>
<?php endif; ?>

<?php /* ----- Propositions reçues (client) ----- */ ?>
<?php if ($isOwner && ($proposals || $project['status'] === 'ouvert')): ?>
    <section class="card">
        <h2>Propositions reçues (<?= count($proposals) ?>)</h2>
        <?php if (!$proposals): ?><p class="muted small">Aucune proposition pour le moment : les designers peuvent se proposer sur les projets ouverts.</p><?php endif; ?>
        <?php foreach ($proposals as $pr): ?>
            <div class="invite-row">
                <div>
                    <a href="designer-profile.php?id=<?= (int) $pr['designer_id'] ?>"><strong><?= e($pr['full_name']) ?></strong></a>
                    <span class="badge badge-<?= e($pr['status']) ?>"><?= e(INVITATION_STATUS[$pr['status']]) ?></span>
                    <p class="small"><?= icon('coins') ?> <?= e(number_format($pr['price'], 0)) ?> TND · <?= icon('clock') ?> <?= (int) $pr['delay_days'] ?> jours
                       <?php if ($pr['hourly_rate']): ?><span class="muted">· tarif habituel <?= e(number_format($pr['hourly_rate'], 0)) ?> TND/h</span><?php endif; ?></p>
                    <p class="small muted"><?= nl2br(e($pr['message'])) ?></p>
                </div>
                <?php if ($pr['status'] === 'en_attente' && $project['status'] === 'ouvert'): ?>
                    <form method="post" action="project-action.php" class="inline-fields">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="proposal_answer">
                        <input type="hidden" name="proposal_id" value="<?= (int) $pr['id'] ?>">
                        <input type="hidden" name="back" value="<?= e($self) ?>">
                        <button class="btn btn-small" name="answer" value="accept" type="submit">Accepter</button>
                        <button class="btn btn-small btn-light" name="answer" value="refuse" type="submit">Refuser</button>
                    </form>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </section>
<?php endif; ?>

<?php /* ----- Espace de collaboration : tâches + messages ----- */ ?>
<?php if ($workspace): ?>
    <section class="card" id="espace">
        <h2>Espace de collaboration</h2>
        <p><?= progress_bar($progress) ?></p>

        <div class="kanban">
            <?php foreach (TASK_STATUS as $key => $label): ?>
                <div class="kanban-col">
                    <h3><?= e($label) ?> <span class="muted small">(<?= count(array_filter($tasks, fn($t) => $t['status'] === $key)) ?>)</span></h3>
                    <?php foreach ($tasks as $t): if ($t['status'] !== $key) { continue; } ?>
                        <div class="task">
                            <p><?= e($t['title']) ?></p>
                            <?php if ($canEditTasks): ?>
                                <div class="inline-fields">
                                    <form method="post" action="project-action.php" class="inline-fields">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="action" value="task_status">
                                        <input type="hidden" name="project_id" value="<?= $id ?>">
                                        <input type="hidden" name="task_id" value="<?= (int) $t['id'] ?>">
                                        <input type="hidden" name="back" value="<?= e($self) ?>#espace">
                                        <select name="status" data-autosubmit aria-label="Statut de la tâche">
                                            <?php foreach (TASK_STATUS as $k => $l): ?>
                                                <option value="<?= e($k) ?>" <?= $t['status'] === $k ? 'selected' : '' ?>><?= e($l) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </form>
                                    <form method="post" action="project-action.php" data-confirm="Supprimer cette tâche ?">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="action" value="delete_task">
                                        <input type="hidden" name="project_id" value="<?= $id ?>">
                                        <input type="hidden" name="task_id" value="<?= (int) $t['id'] ?>">
                                        <input type="hidden" name="back" value="<?= e($self) ?>#espace">
                                        <button class="btn btn-small btn-ghost" type="submit" aria-label="Supprimer la tâche"><?= icon('trash') ?></button>
                                    </form>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endforeach; ?>
        </div>

        <?php if ($canEditTasks): ?>
            <form method="post" action="project-action.php" class="filters">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="add_task">
                <input type="hidden" name="project_id" value="<?= $id ?>">
                <input type="hidden" name="back" value="<?= e($self) ?>#espace">
                <input type="text" name="title" placeholder="Nouvelle tâche (ex : maquette de la page d'accueil)" minlength="3" maxlength="150" required style="min-width:320px">
                <button class="btn btn-small" type="submit">+ Ajouter</button>
            </form>
        <?php elseif (!$tasks): ?>
            <p class="muted small">Aucune tâche n'avait été définie pour ce projet.</p>
        <?php endif; ?>

        <h3>Messages</h3>
        <div class="chat">
            <?php if (!$messages): ?><p class="muted small">Aucun message. Écrivez le premier !</p><?php endif; ?>
            <?php foreach ($messages as $m): ?>
                <div class="msg <?= $m['user_id'] === $user['id'] ? 'msg-me' : '' ?>">
                    <span class="msg-meta"><?= e($m['full_name']) ?> · <?= e(date('d/m H:i', strtotime($m['created_at']))) ?></span>
                    <p><?= nl2br(e($m['body'])) ?></p>
                </div>
            <?php endforeach; ?>
        </div>
        <form method="post" action="project-action.php">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="send_message">
            <input type="hidden" name="project_id" value="<?= $id ?>">
            <input type="hidden" name="back" value="<?= e($self) ?>#espace">
            <label>Votre message
                <textarea name="body" rows="2" maxlength="1000" required></textarea>
            </label>
            <button class="btn btn-small" type="submit">Envoyer</button>
        </form>
    </section>
<?php endif; ?>

<?php /* ----- Actions du client propriétaire ----- */ ?>
<?php if ($isOwner): ?>
    <section class="card">
        <h2>Invitations envoyées (<?= count($invitations) ?>)</h2>
        <?php if (!$invitations): ?><p class="muted small">Aucune invitation. <a href="matching.php?project_id=<?= $id ?>">Voir les designers recommandés</a>.</p><?php endif; ?>
        <?php foreach ($invitations as $inv): ?>
            <p><a href="designer-profile.php?id=<?= (int) $inv['designer_id'] ?>"><?= e($inv['full_name']) ?></a>
               <span class="badge badge-<?= e($inv['status']) ?>"><?= e(INVITATION_STATUS[$inv['status']]) ?></span></p>
        <?php endforeach; ?>
        <?php if ($project['status'] === 'ouvert'): ?>
            <p><a class="btn btn-small" href="matching.php?project_id=<?= $id ?>">Trouver des designers (matching)</a></p>
        <?php endif; ?>
    </section>

    <?php if ($project['status'] === 'en_cours'): ?>
        <form method="post" action="project-action.php" data-confirm="Marquer ce projet comme terminé ?">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="complete">
            <input type="hidden" name="project_id" value="<?= $id ?>">
            <input type="hidden" name="back" value="<?= e($self) ?>">
            <button class="btn" type="submit"><?= icon('check') ?>Marquer comme terminé</button>
        </form>
    <?php elseif ($project['status'] === 'ouvert'): ?>
        <form method="post" action="project-action.php" data-confirm="Annuler ce projet ?">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="cancel">
            <input type="hidden" name="project_id" value="<?= $id ?>">
            <input type="hidden" name="back" value="<?= e($self) ?>">
            <button class="btn btn-danger" type="submit">Annuler le projet</button>
        </form>
    <?php elseif ($project['status'] === 'termine'): ?>
        <section class="card">
            <h2>Évaluation du designer</h2>
            <?php if ($review): ?>
                <p><?= stars_html((int) $review['rating']) ?></p>
                <p class="small"><?= e($review['comment']) ?></p>
            <?php else: ?>
                <form method="post" action="project-action.php">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="review">
                    <input type="hidden" name="project_id" value="<?= $id ?>">
                    <input type="hidden" name="back" value="<?= e($self) ?>">
                    <label>Note
                        <select name="rating" required>
                            <?php for ($i = 5; $i >= 1; $i--): ?><option value="<?= $i ?>"><?= $i ?> / 5</option><?php endfor; ?>
                        </select>
                    </label>
                    <label>Commentaire (facultatif)
                        <textarea name="comment" rows="3" maxlength="500"></textarea>
                    </label>
                    <button class="btn" type="submit">Envoyer mon évaluation</button>
                </form>
            <?php endif; ?>
        </section>
    <?php endif; ?>
<?php elseif ($project['status'] === 'termine' && $review): ?>
    <section class="card">
        <h2>Évaluation reçue</h2>
        <p><?= stars_html((int) $review['rating']) ?></p>
        <p class="small"><?= e($review['comment']) ?></p>
    </section>
<?php endif; ?>
<?php require __DIR__ . '/includes/footer.php'; ?>
