<?php
// includes/trends.php : analyse des tendances du marché.
// Tout est calculé à partir de la base de données (projets, designers, ressources, commandes).
// Ce sont des statistiques descriptives (comptages, moyennes), pas de la prédiction.

function get_trends(): array
{
    $pdo = db();

    // ---------- 1) Offre et demande par catégorie ----------
    $cats = [];
    foreach (CATEGORIES as $c) {
        $cats[$c] = ['category' => $c, 'projects' => 0, 'open' => 0, 'avg_budget' => 0.0,
                     'designers' => 0, 'avg_rate' => 0.0, 'level_key' => 'balanced', 'level' => 'Équilibré'];
    }
    $rows = $pdo->query("SELECT category, COUNT(*) AS nb, SUM(status = 'ouvert') AS nb_open, AVG(budget) AS avg_budget
                         FROM projects WHERE status <> 'annule' GROUP BY category")->fetchAll();
    foreach ($rows as $r) {
        if (isset($cats[$r['category']])) {
            $cats[$r['category']]['projects']   = (int) $r['nb'];
            $cats[$r['category']]['open']       = (int) $r['nb_open'];
            $cats[$r['category']]['avg_budget'] = (float) $r['avg_budget'];
        }
    }
    $rows = $pdo->query('SELECT specialty, COUNT(*) AS nb, AVG(hourly_rate) AS avg_rate
                         FROM designer_profiles GROUP BY specialty')->fetchAll();
    foreach ($rows as $r) {
        if (isset($cats[$r['specialty']])) {
            $cats[$r['specialty']]['designers'] = (int) $r['nb'];
            $cats[$r['specialty']]['avg_rate']  = (float) $r['avg_rate'];
        }
    }
    // Niveau de tension : nombre de projets par designer de la catégorie
    foreach (array_keys($cats) as $k) {
        $nbP = $cats[$k]['projects'];
        $nbD = $cats[$k]['designers'];
        if ($nbD === 0) {
            $level = $nbP > 0 ? ['high', 'Forte demande'] : ['balanced', 'Peu d\'activité'];
        } else {
            $ratio = $nbP / $nbD;
            if ($ratio >= 1.5)     { $level = ['high', 'Forte demande']; }
            elseif ($ratio <= 0.5) { $level = ['low', 'Offre abondante']; }
            else                   { $level = ['balanced', 'Équilibré']; }
        }
        $cats[$k]['level_key'] = $level[0];
        $cats[$k]['level']     = $level[1];
    }

    // ---------- 2) Compétences : demande (projets) vs offre (profils) ----------
    $demand = [];
    $byCat  = [];
    foreach ($pdo->query("SELECT category, skills FROM projects WHERE status <> 'annule'")->fetchAll() as $r) {
        foreach (split_skills($r['skills']) as $s) {
            $demand[$s] = ($demand[$s] ?? 0) + 1;
            $byCat[$r['category']][$s] = ($byCat[$r['category']][$s] ?? 0) + 1;
        }
    }
    $supply = [];
    foreach ($pdo->query('SELECT skills FROM designer_profiles')->fetchAll() as $r) {
        foreach (split_skills($r['skills']) as $s) {
            $supply[$s] = ($supply[$s] ?? 0) + 1;
        }
    }
    arsort($demand);
    foreach (array_keys($byCat) as $k) {
        arsort($byCat[$k]);
    }
    $skills = [];
    foreach (array_slice($demand, 0, 8, true) as $skill => $n) {
        $skills[] = ['skill' => $skill, 'demand' => $n, 'supply' => $supply[$skill] ?? 0];
    }

    // ---------- 3) Activité des 6 derniers mois (projets publiés) ----------
    $months = [];
    $start = new DateTime('first day of this month 00:00:00');
    for ($i = 5; $i >= 0; $i--) {
        $d = (clone $start)->modify("-$i month");
        $months[$d->format('Y-m')] = ['label' => $d->format('m/Y'), 'count' => 0];
    }
    $rows = $pdo->query("SELECT DATE_FORMAT(created_at, '%Y-%m') AS ym, COUNT(*) AS nb FROM projects GROUP BY ym")->fetchAll();
    foreach ($rows as $r) {
        if (isset($months[$r['ym']])) {
            $months[$r['ym']]['count'] = (int) $r['nb'];
        }
    }

    // ---------- 4) Marketplace ----------
    $resCats = $pdo->query('SELECT r.category, COUNT(DISTINCT r.id) AS nb, COUNT(o.id) AS nb_orders
                            FROM resources r LEFT JOIN orders o ON o.resource_id = r.id
                            GROUP BY r.category')->fetchAll();
    $topRes = $pdo->query('SELECT r.id, r.title, r.category, r.price, COUNT(o.id) AS nb_orders
                           FROM resources r LEFT JOIN orders o ON o.resource_id = r.id
                           GROUP BY r.id, r.title, r.category, r.price
                           ORDER BY nb_orders DESC, r.id DESC LIMIT 5')->fetchAll();

    // ---------- 5) Chiffres clés ----------
    $kpi = [
        'open_projects' => (int) $pdo->query("SELECT COUNT(*) FROM projects WHERE status = 'ouvert'")->fetchColumn(),
        'avg_budget'    => (float) $pdo->query("SELECT COALESCE(AVG(budget), 0) FROM projects WHERE status <> 'annule'")->fetchColumn(),
        'avg_rate'      => (float) $pdo->query('SELECT COALESCE(AVG(hourly_rate), 0) FROM designer_profiles')->fetchColumn(),
        'resources'     => (int) $pdo->query('SELECT COUNT(*) FROM resources')->fetchColumn(),
        'orders'        => (int) $pdo->query('SELECT COUNT(*) FROM orders')->fetchColumn(),
    ];

    return [
        'categories'        => $cats,
        'skills'            => $skills,
        'skills_by_category' => $byCat,
        'months'            => $months,
        'resource_cats'     => $resCats,
        'top_resources'     => $topRes,
        'kpi'               => $kpi,
    ];
}
