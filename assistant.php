<?php
// assistant.php : assistant portfolio.
// 1) Diagnostic À RÈGLES (toujours disponible) : score sur 100 + conseils, calculés depuis le profil.
// 2) Recommandations basées sur les TENDANCES de la plateforme (compétences demandées, positionnement tarifaire).
// 3) Analyse par IA FACULTATIVE : active seulement si une clé d'API est renseignée dans config/ai.php.
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/trends.php';
require_once __DIR__ . '/includes/ai.php';

$user = current_user();
$isDesigner = $user && $user['role'] === 'designer';
$checks = [];
$score = 0;
$missingSkills = [];
$ratePhrase = '';
$bioSuggestion = '';
$aiText = null;
$aiError = null;

if ($isDesigner) {
    $pdo = db();
    $stmt = $pdo->prepare('SELECT * FROM designer_profiles WHERE user_id = ?');
    $stmt->execute([$user['id']]);
    $profile = $stmt->fetch();

    $stmt = $pdo->prepare('SELECT category, description FROM portfolio_items WHERE designer_id = ?');
    $stmt->execute([$user['id']]);
    $items = $stmt->fetchAll();

    $stmt = $pdo->prepare('SELECT COUNT(*) FROM reviews WHERE designer_id = ?');
    $stmt->execute([$user['id']]);
    $nbReviews = (int) $stmt->fetchColumn();

    $stmt = $pdo->prepare('SELECT COUNT(*) FROM resources WHERE author_id = ?');
    $stmt->execute([$user['id']]);
    $nbResources = (int) $stmt->fetchColumn();

    $nbItems     = count($items);
    $inSpecialty = count(array_filter($items, fn($i) => $i['category'] === $profile['specialty']));
    $noDesc      = count(array_filter($items, fn($i) => trim($i['description']) === ''));
    $bioLength   = mb_strlen(trim((string) $profile['bio']));
    $ownedSkills = split_skills($profile['skills']);
    $nbSkills    = count($ownedSkills);

    // Chaque critère : $got = taux de réussite entre 0 et 1, $max = points possibles (total = 100)
    $add = function (string $title, float $got, int $max, string $okText, string $advice) use (&$checks) {
        $got = max(0.0, min(1.0, $got));
        $checks[] = ['title' => $title, 'points' => (int) round($got * $max), 'max' => $max,
                     'ok' => $got >= 1, 'text' => $got >= 1 ? $okText : $advice];
    };
    $add('Portfolio', $nbItems / 3, 25, "Vous présentez $nbItems réalisations.",
         "Vous avez $nbItems réalisation(s) : visez au moins 3 projets pour que les clients jugent votre style.");
    $add('Présentation', $bioLength / 80, 20, 'Votre présentation est suffisamment détaillée.',
         "Votre présentation fait $bioLength caractères : visez 80 caractères ou plus (parcours, spécialité, méthode).");
    $add('Compétences', $nbSkills / 4, 15, "$nbSkills compétences listées : elles servent au matching.",
         "Vous avez $nbSkills compétence(s) : listez vos logiciels et savoir-faire (figma, illustrator…).");
    $add('Photo de profil', empty($profile['avatar_url']) ? 0 : 1, 10, 'Photo de profil présente.',
         'Ajoutez une photo : un profil avec photo inspire davantage confiance.');
    $add('Descriptions des réalisations', $nbItems ? ($nbItems - $noDesc) / $nbItems : 0, 10, 'Toutes vos réalisations sont décrites.',
         $nbItems ? "$noDesc réalisation(s) sans description : indiquez le contexte, les outils et votre rôle." : 'Ajoutez des réalisations avec une description.');
    $add('Cohérence spécialité / portfolio', $inSpecialty > 0 ? 1 : 0, 10, 'Votre portfolio illustre votre spécialité.',
         "Aucune réalisation dans votre spécialité « {$profile['specialty']} » : ajoutez-en une.");
    $add('Ressources publiées', $nbResources > 0 ? 1 : 0, 5, 'Vous proposez des ressources au marketplace.',
         'Publiez un template ou un pack au marketplace pour gagner en visibilité.');
    $add('Avis clients', $nbReviews > 0 ? 1 : 0, 5, "$nbReviews avis reçu(s).",
         'Terminez un premier projet : le client pourra vous évaluer.');
    $score = array_sum(array_column($checks, 'points'));

    // Recommandations issues des tendances
    $trends = get_trends();
    $catInfo = $trends['categories'][$profile['specialty']] ?? null;
    foreach (($trends['skills_by_category'][$profile['specialty']] ?? []) as $skill => $n) {
        if (!in_array($skill, $ownedSkills, true)) {
            $missingSkills[$skill] = $n;
        }
    }
    $missingSkills = array_slice($missingSkills, 0, 4, true);

    if ($catInfo && $catInfo['designers'] > 1 && $catInfo['avg_rate'] > 0) {
        $diff = (int) round(100 * ($profile['hourly_rate'] - $catInfo['avg_rate']) / $catInfo['avg_rate']);
        $avgTxt = number_format($catInfo['avg_rate'], 0);
        if ($diff > 15) {
            $ratePhrase = "Votre tarif ({$profile['hourly_rate']} TND/h) est $diff % au-dessus de la moyenne de votre catégorie ($avgTxt TND/h) : appuyez-vous sur vos avis et votre portfolio pour le justifier.";
        } elseif ($diff < -15) {
            $ratePhrase = "Votre tarif ({$profile['hourly_rate']} TND/h) est " . abs($diff) . " % sous la moyenne de votre catégorie ($avgTxt TND/h) : vous pouvez envisager de le réévaluer.";
        } else {
            $ratePhrase = "Votre tarif ({$profile['hourly_rate']} TND/h) est proche de la moyenne de votre catégorie ($avgTxt TND/h).";
        }
    }

    // Exemple de présentation (modèle de texte rempli avec vos données)
    $topSkills = $ownedSkills ? implode(', ', array_slice($ownedSkills, 0, 3)) : mb_strtolower($profile['specialty']);
    $bioSuggestion = $user['full_name'] . ' : ' . $profile['specialty'] . ' basé(e) à ' . $profile['city']
        . '. Je maîtrise ' . $topSkills . ' et j\'accompagne les startups et PME tunisiennes du brief à la livraison finale, '
        . 'avec une communication claire et des délais tenus.';

    // Analyse par IA (facultative), déclenchée par un formulaire POST protégé par jeton CSRF
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        csrf_check();
        $prompt = "Tu es un coach pour designers freelances en Tunisie. Voici un profil :\n"
            . "Spécialité : {$profile['specialty']}\nVille : {$profile['city']}\nTarif : {$profile['hourly_rate']} TND/h\n"
            . "Compétences : " . ($profile['skills'] ?: 'aucune') . "\nPrésentation : " . ($profile['bio'] ?: 'aucune') . "\n"
            . "Nombre de réalisations : $nbItems\nAvis reçus : $nbReviews\n"
            . "Compétences très demandées qu'il n'a pas listées : " . ($missingSkills ? implode(', ', array_keys($missingSkills)) : 'aucune') . "\n\n"
            . "Réponds en français, en 200 mots maximum : 1) trois conseils concrets pour améliorer son profil, "
            . "2) une version améliorée de sa présentation en 2 phrases.";
        $aiText = ai_complete($prompt);
        if ($aiText === null) {
            $aiError = 'L\'analyse personnalisée est momentanément indisponible. Réessayez plus tard.';
        }
    }
}

$level = $score >= 80 ? 'Profil très solide' : ($score >= 60 ? 'Bon profil' : ($score >= 40 ? 'Profil à améliorer' : 'Profil à compléter'));

$page_title = 'Assistant portfolio';
$active = 'assistant';
require __DIR__ . '/includes/header.php';
?>
<h1>Assistant portfolio</h1>
<p class="muted lead-sm">Un diagnostic clair de votre profil, avec des conseils pour attirer plus de clients.</p>

<?php if (!$user): ?>
    <p class="empty"><a href="login.php">Connectez-vous</a> avec un compte designer pour obtenir un diagnostic de votre profil.</p>
<?php elseif (!$isDesigner): ?>
    <p class="empty">L'assistant est réservé aux designers : il analyse leur profil et leur portfolio.</p>
<?php else: ?>
    <section class="card score-card">
        <div class="score-ring" style="--p:<?= (int) $score ?>"><span><?= (int) $score ?></span></div>
        <div>
            <h2>Score de profil : <?= (int) $score ?>/100</h2>
            <p class="muted"><?= e($level) ?></p>
            <a class="btn btn-small" href="edit-profile.php">Modifier mon profil</a>
        </div>
    </section>

    <h2>Diagnostic détaillé</h2>
    <?php foreach ($checks as $c): ?>
        <div class="card tip tip-<?= $c['ok'] ? 'ok' : 'warn' ?>">
            <h3><?= $c['ok'] ? icon('check-circle') : icon('alert') ?> <?= e($c['title']) ?> <span class="muted small">(<?= (int) $c['points'] ?>/<?= (int) $c['max'] ?>)</span></h3>
            <p class="small"><?= e($c['text']) ?></p>
        </div>
    <?php endforeach; ?>

    <h2>Recommandations selon les tendances</h2>
    <div class="card tip tip-info">
        <h3><?= icon('trend') ?> Compétences demandées que vous n'avez pas listées</h3>
        <?php if ($missingSkills): ?>
            <p class="small">Dans la catégorie « <?= e($profile['specialty']) ?> », les clients demandent :
                <?php foreach ($missingSkills as $skill => $n): ?><span class="tag"><?= e($skill) ?> · <?= (int) $n ?> projet(s)</span><?php endforeach; ?>
                Si vous les maîtrisez, ajoutez-les à votre profil : elles améliorent votre score de matching.</p>
        <?php else: ?>
            <p class="small">Vous couvrez déjà les compétences demandées dans votre catégorie. Voir <a href="trends.php">les tendances</a>.</p>
        <?php endif; ?>
    </div>
    <?php if ($ratePhrase): ?>
        <div class="card tip tip-info"><h3><?= icon('coins') ?> Positionnement tarifaire</h3><p class="small"><?= e($ratePhrase) ?></p></div>
    <?php endif; ?>
    <div class="card tip tip-info">
        <h3><?= icon('edit') ?> Exemple de présentation</h3>
        <p class="small"><?= e($bioSuggestion) ?></p>
        <p class="small muted">Modèle généré avec vos informations : adaptez-le avec vos vraies références.</p>
    </div>

    <?php if (ai_enabled()): ?>
    <h2>Analyse personnalisée</h2>
        <form method="post" data-confirm="Analyser votre profil avec l'assistant IA ?">
            <?= csrf_field() ?>
            <button class="btn" type="submit"><?= icon('spark') ?>Obtenir une analyse personnalisée</button>
        </form>
        <?php if ($aiText): ?>
            <div class="card tip tip-info"><p><?= nl2br(e($aiText)) ?></p></div>
        <?php elseif ($aiError): ?>
            <div class="alert alert-error"><?= e($aiError) ?></div>
        <?php endif; ?>
    <?php endif; ?>
<?php endif; ?>
<?php require __DIR__ . '/includes/footer.php'; ?>
