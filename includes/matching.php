<?php
// includes/matching.php
// Moteur de matching projet <-> designer.
// ATTENTION : ce n'est PAS un modèle d'IA entraîné. C'est un moteur de score à base de RÈGLES,
// transparent et explicable : on additionne des points selon 5 critères (total = 100 points).
//
//   1) Spécialité  : 35 pts  si la spécialité du designer = la catégorie du projet, sinon 0
//   2) Compétences : 30 pts  x (compétences demandées que le designer possède / compétences demandées)
//   3) Budget      : 15 pts  si le coût estimé (tarif horaire x 15 h) <= budget
//                     7 pts  si le coût estimé dépasse le budget de 20 % maximum, sinon 0
//   4) Portfolio   : 10 pts  si le designer a déjà une réalisation dans la catégorie du projet
//   5) Réputation  : 10 pts  x (note moyenne / 5) ; 5 pts neutres tant qu'il n'a aucun avis
//
// Chaque critère renvoie une phrase d'explication : c'est ce qu'on affiche à l'utilisateur.
// Le même calcul sert dans les deux sens : le client voit les designers adaptés à son projet,
// le designer voit les projets ouverts qui lui correspondent le mieux.

const MATCH_HOURS = 15;   // durée moyenne supposée d'un projet, pour estimer le coût

// $project  : ligne de la table projects
// $designer : ligne issue de designers_base_sql()
// $portfolioCategories : liste des catégories de ses réalisations (tableau de chaînes)
function compute_match(array $project, array $designer, array $portfolioCategories): array
{
    $details = [];   // une entrée par critère : ['points' => ..., 'max' => ..., 'text' => ...]

    // 1) Spécialité
    if ($designer['specialty'] === $project['category']) {
        $details[] = ['points' => 35, 'max' => 35, 'text' => 'Spécialité identique à la catégorie du projet'];
    } else {
        $details[] = ['points' => 0, 'max' => 35, 'text' => 'Spécialité différente (' . $designer['specialty'] . ')'];
    }

    // 2) Compétences
    $wanted = split_skills($project['skills']);
    $owned  = split_skills($designer['skills']);
    if (count($wanted) === 0) {
        $details[] = ['points' => 0, 'max' => 30, 'text' => 'Aucune compétence précisée dans le projet'];
    } else {
        $common = array_intersect($wanted, $owned);
        $points = (int) round(30 * count($common) / count($wanted));
        $text   = count($common) . ' compétence(s) sur ' . count($wanted) . ' demandée(s)';
        if ($common) {
            $text .= ' : ' . implode(', ', $common);
        }
        $details[] = ['points' => $points, 'max' => 30, 'text' => $text];
    }

    // 3) Budget
    $cost = $designer['hourly_rate'] * MATCH_HOURS;
    if ($cost <= $project['budget']) {
        $points = 15;
        $text = 'Coût estimé ' . round($cost) . ' TND (' . MATCH_HOURS . ' h) : dans le budget';
    } elseif ($cost <= $project['budget'] * 1.2) {
        $points = 7;
        $text = 'Coût estimé ' . round($cost) . ' TND : dépasse le budget de 20 % maximum';
    } else {
        $points = 0;
        $text = 'Coût estimé ' . round($cost) . ' TND : trop au-dessus du budget';
    }
    $details[] = ['points' => $points, 'max' => 15, 'text' => $text];

    // 4) Portfolio
    $similar = count(array_filter($portfolioCategories, fn($c) => $c === $project['category']));
    if ($similar > 0) {
        $details[] = ['points' => 10, 'max' => 10, 'text' => $similar . ' réalisation(s) dans cette catégorie'];
    } else {
        $details[] = ['points' => 0, 'max' => 10, 'text' => 'Aucune réalisation dans cette catégorie'];
    }

    // 5) Réputation (note moyenne issue des avis réels)
    $nbReviews = (int) ($designer['nb_reviews'] ?? 0);
    if ($nbReviews > 0) {
        $avg = (float) $designer['avg_rating'];
        $details[] = ['points' => (int) round(10 * $avg / 5), 'max' => 10,
                      'text' => 'Note moyenne ' . number_format($avg, 1) . ' / 5 (' . $nbReviews . ' avis)'];
    } else {
        $details[] = ['points' => 5, 'max' => 10, 'text' => 'Pas encore d\'avis : points neutres'];
    }

    $total = array_sum(array_column($details, 'points'));
    return ['score' => $total, 'details' => $details];
}


// Qualificatif lisible pour un score
function match_label(int $score): string
{
    if ($score >= 75) { return 'Excellent'; }
    if ($score >= 55) { return 'Bon'; }
    if ($score >= 35) { return 'Moyen'; }
    return 'Faible';
}

// Ligne de designer (avec note moyenne) + catégories de son portfolio, pour un designer donné
function load_designer_for_match(int $designerId): ?array
{
    $stmt = db()->prepare(designers_base_sql() . ' AND u.id = ?');
    $stmt->execute([$designerId]);
    $designer = $stmt->fetch();
    if (!$designer) {
        return null;
    }
    $stmt = db()->prepare('SELECT category FROM portfolio_items WHERE designer_id = ?');
    $stmt->execute([$designerId]);
    return ['designer' => $designer, 'categories' => array_column($stmt->fetchAll(), 'category')];
}

// Compatibilité d'un designer avec un projet précis (null si le designer n'existe pas)
function match_designer_project(array $project, int $designerId): ?array
{
    $data = load_designer_for_match($designerId);
    return $data ? compute_match($project, $data['designer'], $data['categories']) : null;
}

// Projets ouverts classés du plus au moins adapté à ce designer
function recommend_projects_for_designer(int $designerId): array
{
    $data = load_designer_for_match($designerId);
    if (!$data) {
        return [];
    }
    $projects = db()->query("SELECT p.*, c.full_name AS client_name FROM projects p
                             JOIN users c ON c.id = p.client_id
                             WHERE p.status = 'ouvert' ORDER BY p.created_at DESC, p.id DESC")->fetchAll();
    $results = [];
    foreach ($projects as $p) {
        $results[] = ['project' => $p] + compute_match($p, $data['designer'], $data['categories']);
    }
    usort($results, fn($a, $b) => $b['score'] <=> $a['score']);
    return $results;
}
