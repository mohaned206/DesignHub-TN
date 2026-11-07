<?php
// includes/profile.php : diagnostic de profil d'un designer (score /100 et critères).
// Utilisé par assistant.php (détail + conseils) et dashboard.php (score, liste « à compléter »).
// Règles transparentes : chaque critère rapporte des points, le total fait 100.

function profile_diagnostic(array $user): array
{
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

    $checks = [];
    // $got = taux de réussite entre 0 et 1, $max = points possibles ; $link = page où corriger
    $add = function (string $title, float $got, int $max, string $okText, string $advice, string $link, string $short) use (&$checks) {
        $got = max(0.0, min(1.0, $got));
        $checks[] = ['title' => $title, 'short' => $short, 'points' => (int) round($got * $max), 'max' => $max,
                     'ok' => $got >= 1, 'text' => $got >= 1 ? $okText : $advice, 'link' => $link];
    };
    $add('Portfolio', $nbItems / 3, 25, "Vous présentez $nbItems réalisations.",
         "Vous avez $nbItems réalisation(s) : visez au moins 3 projets pour que les clients jugent votre style.",
         'edit-profile.php#portfolio', 'Ajouter 3 réalisations au portfolio');
    $add('Présentation', $bioLength / 80, 20, 'Votre présentation est suffisamment détaillée.',
         "Votre présentation fait $bioLength caractères : visez 80 caractères ou plus (parcours, spécialité, méthode).",
         'edit-profile.php#profil', 'Rédiger une présentation (80 caractères)');
    $add('Compétences', $nbSkills / 4, 15, "$nbSkills compétences listées : elles servent au matching.",
         "Vous avez $nbSkills compétence(s) : listez vos logiciels et savoir-faire (figma, illustrator…).",
         'edit-profile.php#profil', 'Lister au moins 4 compétences');
    $add('Photo de profil', empty($profile['avatar_url']) ? 0 : 1, 10, 'Photo de profil présente.',
         'Ajoutez une photo : un profil avec photo inspire davantage confiance.',
         'edit-profile.php#profil', 'Ajouter une photo de profil');
    $add('Descriptions des réalisations', $nbItems ? ($nbItems - $noDesc) / $nbItems : 0, 10, 'Toutes vos réalisations sont décrites.',
         $nbItems ? "$noDesc réalisation(s) sans description : indiquez le contexte, les outils et votre rôle." : 'Ajoutez des réalisations avec une description.',
         'edit-profile.php#portfolio', 'Décrire vos réalisations');
    $add('Cohérence spécialité / portfolio', $inSpecialty > 0 ? 1 : 0, 10, 'Votre portfolio illustre votre spécialité.',
         "Aucune réalisation dans votre spécialité « {$profile['specialty']} » : ajoutez-en une.",
         'edit-profile.php#portfolio', 'Ajouter une réalisation dans votre spécialité');
    $add('Ressources publiées', $nbResources > 0 ? 1 : 0, 5, 'Vous proposez des ressources au marketplace.',
         'Publiez un template ou un pack au marketplace pour gagner en visibilité.',
         'edit-profile.php#ressources', 'Publier une ressource au marketplace');
    $add('Avis clients', $nbReviews > 0 ? 1 : 0, 5, "$nbReviews avis reçu(s).",
         'Terminez un premier projet : le client pourra vous évaluer.',
         'projects.php', 'Obtenir un premier avis client');

    $score = array_sum(array_column($checks, 'points'));
    return compact('profile', 'items', 'nbItems', 'nbReviews', 'nbResources', 'ownedSkills', 'checks', 'score');
}

function profile_level(int $score): string
{
    return $score >= 80 ? 'Profil très solide' : ($score >= 60 ? 'Bon profil' : ($score >= 40 ? 'Profil à améliorer' : 'Profil à compléter'));
}
