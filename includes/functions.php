<?php
// includes/functions.php
// Fonctions communes à toutes les pages : session, sécurité, authentification, petits outils d'affichage.
// Chaque page commence par : require_once __DIR__ . '/includes/functions.php';

require_once __DIR__ . '/../config/database.php';

// Page d'erreur claire (au lieu d'un « Fatal error ») si la base n'est pas installée ou inaccessible
set_exception_handler(function (Throwable $e) {
    error_log($e->getMessage());
    http_response_code(500);
    $code = $e instanceof PDOException ? (string) $e->getCode() : '';
    $isLocal = in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1'], true);
    echo '<!doctype html><html lang="fr"><head><meta charset="utf-8"><title>DesignHub-TN</title>'
       . '<style>body{font-family:system-ui,sans-serif;background:#f6f7fb;color:#1e293b;display:grid;place-items:center;min-height:100vh;margin:0}'
       . '.b{background:#fff;border:1px solid #e5e7ef;border-radius:16px;padding:2rem;max-width:640px}code{background:#eef2ff;padding:2px 6px;border-radius:5px}'
       . 'pre{background:#f1f5f9;padding:.8rem;border-radius:8px;white-space:pre-wrap;font-size:.8rem}</style></head><body><div class="b">';
    if (in_array($code, ['42S02', '1049'], true)) {
        echo '<h1>Base de données non installée</h1><p>Ouvrez <a href="install.php">install.php</a> et cliquez sur '
           . '<strong>Installer la base de données</strong>, puis rechargez cette page.</p>';
    } elseif ($code === '42S22') {
        echo '<h1>Base de données à mettre à jour</h1><p>Une colonne est absente. Exécutez <code>database/upgrade-files.sql</code> '
           . 'dans phpMyAdmin (onglet SQL), ou relancez <a href="install.php">install.php</a>.</p>';
    } elseif (in_array($code, ['2002', 'HY000'], true) && stripos($e->getMessage(), 'refused') !== false) {
        echo '<h1>MySQL ne répond pas</h1><p>Démarrez <strong>MySQL</strong> dans le panneau XAMPP (bouton Start), puis rechargez.</p>';
    } else {
        echo '<h1>Une erreur est survenue</h1><p>Si le problème persiste, vérifiez que MySQL est démarré et que <code>config/database.php</code> est correct.</p>';
    }
    if ($isLocal) {   // détail technique visible uniquement en local, pour le débogage
        echo '<p class="small"><strong>Détail (local) :</strong></p><pre>' . htmlspecialchars($e->getMessage() . "\n" . basename($e->getFile()) . ':' . $e->getLine()) . '</pre>';
    }
    echo '</div></body></html>';
});

// ---------- Session PHP ----------
// httponly : le cookie de session n'est pas lisible par JavaScript
session_set_cookie_params(['httponly' => true, 'samesite' => 'Lax']);
session_start();

// ---------- Listes fixes utilisées dans plusieurs pages ----------
const CATEGORIES = ['UI/UX Design', 'Branding & Logo', 'Illustration & 3D', 'Motion Design'];
const RESOURCE_CATEGORIES = ['Template', 'Mockup', 'Pack graphique'];
const CITIES = ['Tunis', 'Ariana', 'Sousse', 'Sfax', 'Bizerte', 'Monastir', 'Nabeul', 'Autre'];

const PROJECT_STATUS = [
    'ouvert'   => 'Ouvert',
    'en_cours' => 'En cours',
    'termine'  => 'Terminé',
    'annule'   => 'Annulé',
];
const INVITATION_STATUS = [
    'en_attente' => 'En attente',
    'acceptee'   => 'Acceptée',
    'refusee'    => 'Refusée',
    'annulee'    => 'Annulée',
];

// ---------- Affichage sécurisé ----------
// e() = "escape" : transforme <, >, " ... en texte inoffensif (protection contre les attaques XSS).
// À utiliser sur TOUTE donnée affichée qui vient de l'utilisateur ou de la base.
function e($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function redirect(string $url): void
{
    header('Location: ' . $url);
    exit;
}

// ---------- Messages "flash" (affichés une seule fois après une action) ----------
function set_flash(string $type, string $message): void
{
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];   // type : success | error
}

function get_flash(): ?array
{
    $flash = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    return $flash;
}

// ---------- Protection CSRF ----------
// Chaque formulaire POST contient un jeton secret lié à la session.
// Un site pirate ne peut pas le deviner, donc il ne peut pas soumettre le formulaire à notre place.
function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf" value="' . e(csrf_token()) . '">';
}

// À appeler au début de chaque traitement de formulaire POST
function csrf_check(): void
{
    $ok = $_SERVER['REQUEST_METHOD'] === 'POST'
        && hash_equals($_SESSION['csrf'] ?? '', $_POST['csrf'] ?? '');
    if (!$ok) {
        http_response_code(403);
        exit('Requête refusée (jeton CSRF invalide). Retournez à la page précédente et réessayez.');
    }
}

// ---------- Authentification ----------
// Retourne l'utilisateur connecté (id, full_name, role) ou null.
// On relit la base à chaque requête : le rôle ne vient jamais du navigateur.
function current_user(): ?array
{
    static $user = false;

    if ($user === false) {
        $user = null;
        if (!empty($_SESSION['user_id'])) {
            $stmt = db()->prepare('SELECT u.id, u.full_name, u.email, u.role, p.avatar_url
                                   FROM users u LEFT JOIN designer_profiles p ON p.user_id = u.id WHERE u.id = ?');
            $stmt->execute([$_SESSION['user_id']]);
            $user = $stmt->fetch() ?: null;
            if ($user && $user['role'] === 'admin') {
                $user['avatar_url'] = 'images/avatars/admin.jpg';
            }
        }
    }
    return $user;
}

// Page privée : il faut être connecté
function require_login(): array
{
    $user = current_user();
    if (!$user) {
        set_flash('error', 'Connectez-vous pour accéder à cette page.');
        redirect('login.php');
    }
    return $user;
}

// Page réservée à un rôle ('client' ou 'designer') : vérifié côté serveur
function require_role(string $role): array
{
    $user = require_login();
    if ($user['role'] !== $role) {
        http_response_code(403);
        set_flash('error', 'Cette page est réservée aux ' . ($role === 'client' ? 'clients' : 'designers') . '.');
        redirect('dashboard.php');
    }
    return $user;
}

// Page réservée à l'administrateur : vérifié côté serveur, le rôle vient toujours de la base
function require_admin(): array
{
    $user = require_login();
    if ($user['role'] !== 'admin') {
        http_response_code(403);
        set_flash('error', 'Cette page est réservée à l\'administrateur.');
        redirect('dashboard.php');
    }
    return $user;
}

// Adresse de « Mon espace » selon le rôle
function home_url(?array $user): string
{
    return $user && $user['role'] === 'admin' ? 'admin.php' : 'dashboard.php';
}

// ---------- Petits outils ----------
// Transforme "Figma, Illustrator ,logo" en ['figma', 'illustrator', 'logo'] (minuscules, sans doublon)
function split_skills(string $text): array
{
    $list = array_map(fn($s) => mb_strtolower(trim($s)), explode(',', $text));
    return array_values(array_unique(array_filter($list, fn($s) => $s !== '')));
}

// Nettoie un champ de compétences saisi par l'utilisateur
function clean_skills(string $text): string
{
    return mb_substr(implode(', ', split_skills($text)), 0, 255);
}

// Accepte uniquement les URL http(s) (refuse javascript:, data:, etc.)
function valid_url(string $url): bool
{
    return filter_var($url, FILTER_VALIDATE_URL) !== false
        && preg_match('#^https?://#i', $url) === 1;
}

function format_price($price): string
{
    return $price > 0 ? number_format((float) $price, 0, ',', ' ') . ' TND' : 'Gratuit';
}

// Cercle avec les initiales, ou la photo si une URL est fournie
function avatar_html(string $name, ?string $url, string $size = 'md'): string
{
    $initials = mb_strtoupper(mb_substr($name, 0, 1));
    $parts = preg_split('/\s+/', trim($name));
    if (count($parts) > 1) {
        $initials .= mb_strtoupper(mb_substr(end($parts), 0, 1));
    }
    $html = '<span class="avatar avatar-' . e($size) . '">';
    if ($url) {
        $html .= '<img src="' . e($url) . '" alt="" data-fallback="hide">';
    }
    return $html . '<span class="avatar-initials">' . e($initials) . '</span></span>';
}

// Étoiles + note moyenne. Si aucun avis : message honnête, pas de fausse note.
function rating_html($avg, $count): string
{
    if (!$count) {
        return '<span class="muted">Pas encore d\'évaluation</span>';
    }
    return stars_html((int) round($avg)) . ' <span class="muted">' . e(number_format($avg, 1)) . ' (' . (int) $count . ' avis)</span>';
}

function status_badge(string $status): string
{
    $label = PROJECT_STATUS[$status] ?? $status;
    return '<span class="badge badge-' . e($status) . '">' . e($label) . '</span>';
}

// ---------- Requêtes réutilisées ----------
// Requête de base pour lister les designers avec leur note moyenne (calculée depuis la table reviews)
function designers_base_sql(): string
{
    return 'SELECT u.id, u.full_name, p.specialty, p.skills, p.city, p.hourly_rate, p.bio, p.avatar_url,
                   (SELECT AVG(r.rating) FROM reviews r WHERE r.designer_id = u.id) AS avg_rating,
                   (SELECT COUNT(*)      FROM reviews r WHERE r.designer_id = u.id) AS nb_reviews
            FROM users u
            JOIN designer_profiles p ON p.user_id = u.id
            WHERE u.role = \'designer\'';
}

// Carte d'un designer (utilisée sur l'accueil et l'annuaire)
function designer_card(array $d): string
{
    $skills = '';
    foreach (split_skills($d['skills']) as $s) {
        $skills .= '<span class="tag">' . e($s) . '</span>';
    }
    return '<article class="card designer-card">
        <div class="designer-head">' . avatar_html($d['full_name'], $d['avatar_url']) . '
            <div>
                <h3>' . e($d['full_name']) . '</h3>
                <p class="accent">' . e($d['specialty']) . '</p>
                <p class="muted small loc">' . icon('pin') . e($d['city']) . '</p>
            </div>
        </div>
        <p class="small">' . e(mb_strimwidth((string) $d['bio'], 0, 120, '…')) . '</p>
        <div class="tags">' . $skills . '</div>
        <p class="small">' . rating_html($d['avg_rating'], $d['nb_reviews']) . '</p>
        <div class="card-footer">
            <strong>' . e(number_format($d['hourly_rate'], 0)) . ' TND / h</strong>
            <a class="btn btn-small" href="designer-profile.php?id=' . (int) $d['id'] . '">Voir le profil</a>
        </div>
    </article>';
}


// ============================================================
// Ajouts : collaboration, propositions, commandes
// ============================================================
const TASK_STATUS = ['a_faire' => 'À faire', 'en_cours' => 'En cours', 'fait' => 'Fait'];

// Vrai si l'utilisateur est le client du projet ou le designer assigné
function is_project_member(array $project, array $user): bool
{
    return $project['client_id'] === $user['id'] || $project['designer_id'] === $user['id'];
}

// Avancement des projets d'après leurs tâches : [project_id => ['total', 'done', 'percent']]
function progress_map(array $ids): array
{
    if (!$ids) {
        return [];
    }
    $in = implode(',', array_fill(0, count($ids), '?'));
    $stmt = db()->prepare("SELECT project_id, COUNT(*) AS total, SUM(status = 'fait') AS done
                           FROM project_tasks WHERE project_id IN ($in) GROUP BY project_id");
    $stmt->execute(array_values($ids));
    $map = [];
    foreach ($stmt->fetchAll() as $r) {
        $total = (int) $r['total'];
        $done  = (int) $r['done'];
        $map[(int) $r['project_id']] = [
            'total'   => $total,
            'done'    => $done,
            'percent' => $total ? (int) round(100 * $done / $total) : 0,
        ];
    }
    return $map;
}

// Barre d'avancement (HTML). Les valeurs sont des entiers : pas d'échappement nécessaire.
function progress_bar(?array $p): string
{
    if (!$p || !$p['total']) {
        return '<span class="muted small">Aucune tâche définie</span>';
    }
    return '<div class="progress" title="' . (int) $p['percent'] . ' %"><span style="width:' . (int) $p['percent'] . '%"></span></div>'
         . '<span class="small muted">' . (int) $p['done'] . '/' . (int) $p['total'] . ' tâche(s) · ' . (int) $p['percent'] . ' %</span>';
}


// ============================================================
// Interface : icônes SVG locales (dossier images/icons), libellés, dates
// ============================================================

// Icône SVG en ligne : hérite de la couleur du texte (currentColor). Le nom est limité à [a-z-].
function icon(string $name, string $class = ''): string
{
    static $cache = [];
    if (!preg_match('/^[a-z-]+$/', $name)) {
        return '';
    }
    if (!isset($cache[$name])) {
        $file = __DIR__ . '/../images/icons/' . $name . '.svg';
        $svg  = is_file($file) ? trim((string) file_get_contents($file)) : '';
        $cache[$name] = $svg === '' ? '' : preg_replace('/^<svg /', '<svg class="ico" aria-hidden="true" focusable="false" ', $svg, 1);
    }
    return $class === '' ? $cache[$name] : str_replace('class="ico"', 'class="ico ' . e($class) . '"', $cache[$name]);
}

// Pastille colorée contenant une icône : tons blue | teal | sun | green | navy | clay
function icon_tile(string $name, string $tone = 'blue'): string
{
    $tones = ['blue', 'teal', 'sun', 'green', 'navy', 'clay'];
    $tone  = in_array($tone, $tones, true) ? $tone : 'blue';
    if (!preg_match('/^[a-z-]+$/', $name) || !is_file(__DIR__ . '/../images/icons/png/' . $tone . '/' . $name . '.png')) {
        return '<span class="tile tile-' . e($tone) . '">' . icon($name) . '</span>';
    }
    // vraie image PNG du dossier images/icons/png/<ton>/
    return '<span class="tile tile-' . e($tone) . '"><img class="tile-img" src="images/icons/png/' . $tone . '/' . $name . '.png" alt="" width="24" height="24" loading="lazy"></span>';
}

function role_label(string $role): string
{
    return ['client' => 'Client', 'designer' => 'Designer', 'admin' => 'Administrateur'][$role] ?? $role;
}

// Date en français : 4 nov. 2026
function date_fr(?string $date): string
{
    if (!$date) {
        return '—';
    }
    $t = strtotime($date);
    $m = ['janv.', 'févr.', 'mars', 'avr.', 'mai', 'juin', 'juil.', 'août', 'sept.', 'oct.', 'nov.', 'déc.'];
    return date('j', $t) . ' ' . $m[(int) date('n', $t) - 1] . ' ' . date('Y', $t);
}

// Étoiles (note entière sur 5)
function stars_html(int $rating): string
{
    $rating = max(0, min(5, $rating));
    return '<span class="stars" title="' . $rating . ' / 5">' . str_repeat(icon('star', 'on'), $rating) . str_repeat(icon('star', 'off'), 5 - $rating) . '</span>';
}
