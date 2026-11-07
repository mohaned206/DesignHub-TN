<?php
// includes/resource_card.php : carte d'une ressource du marketplace.
// Variables attendues : $r (ligne de la table resources + author_name, nb_orders, owned), $user (ou null), $here (adresse de retour).
?>
<article class="card media-card">
    <?php if ($r['image_url']): ?>
        <img src="<?= e($r['image_url']) ?>" alt="<?= e($r['title']) ?>" data-fallback="placeholder" data-lightbox data-caption="<?= e($r['title']) ?>">
    <?php else: ?>
        <div class="img-placeholder"><?= e($r['category']) ?></div>
    <?php endif; ?>
    <div class="card-body">
        <span class="tag"><?= e($r['category']) ?></span>
        <h3><?= e($r['title']) ?></h3>
        <?php if ($r['description']): ?><p class="small muted"><?= e($r['description']) ?></p><?php endif; ?>
        <p class="small">Par <a href="designer-profile.php?id=<?= (int) $r['author_id'] ?>"><?= e($r['author_name']) ?></a>
           · <span class="muted"><?= (int) $r['nb_orders'] ?> obtention(s)</span></p>
        <p><strong class="price"><?= e(format_price($r['price'])) ?></strong>
           <?php if ($r['file_name']): ?><span class="tag"><?= e(strtoupper(pathinfo($r['file_name'], PATHINFO_EXTENSION))) ?> · <?= e(format_size($r['file_size'])) ?></span><?php endif; ?></p>

        <?php if (!$user): ?>
            <a class="btn btn-small btn-light" href="login.php">Connectez-vous pour l'obtenir</a>
        <?php elseif ($r['author_id'] === $user['id']): ?>
            <span class="badge">Votre ressource</span>
            <?php if ($r['file_path']): ?><a class="btn btn-small btn-light" href="download.php?id=<?= (int) $r['id'] ?>"><?= icon('download') ?>Télécharger</a><?php endif; ?>
        <?php elseif ((int) $r['owned'] > 0): ?>
            <?php if ($r['file_path']): ?><a class="btn btn-small" href="download.php?id=<?= (int) $r['id'] ?>"><?= icon('download') ?>Télécharger</a>
            <?php else: ?><span class="badge badge-termine"><?= icon('check') ?>Déjà obtenue</span><?php endif; ?>
        <?php else: ?>
            <form method="post" action="resource-action.php" data-confirm="<?= $r['price'] > 0 ? 'Commander cette ressource ?' : 'Obtenir cette ressource gratuite ?' ?>">
                <?= csrf_field() ?>
                <input type="hidden" name="resource_id" value="<?= (int) $r['id'] ?>">
                <input type="hidden" name="back" value="<?= e($here) ?>">
                <button class="btn btn-small" type="submit"><?= $r['price'] > 0 ? 'Commander' : 'Obtenir gratuitement' ?></button>
            </form>
        <?php endif; ?>
    </div>
</article>
