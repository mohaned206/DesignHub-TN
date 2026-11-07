<?php
// includes/dash_orders.php : panneau « ressources obtenues » commun aux espaces client et designer.
// Variables attendues : $myOrders (commandes de l'utilisateur).
?>
<section class="panel" id="obtenues">
    <div class="panel-head"><h2><?= icon('download') ?>Ressources obtenues</h2></div>
    <?php if (!$myOrders): ?>
        <?= empty_state('bag', 'Aucune ressource obtenue', 'Les templates et packs que vous commandez apparaissent ici, prêts à télécharger.', 'marketplace.php', 'Parcourir le marketplace') ?>
    <?php endif; ?>
    <?php foreach ($myOrders as $o): ?>
        <div class="item">
            <div class="item-main"><strong class="title"><?= e($o['title']) ?></strong>
                <p class="meta"><?= e($o['category']) ?> · <?= e(format_price($o['price'])) ?> · <?= e(date_fr($o['created_at'])) ?></p></div>
            <?php if ($o['file_path']): ?><div class="item-side"><a class="btn btn-small btn-light" href="download.php?id=<?= (int) $o['id'] ?>"><?= icon('download') ?>Télécharger</a></div><?php endif; ?>
        </div>
    <?php endforeach; ?>
</section>
