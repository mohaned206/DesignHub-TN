<?php
// install.php : installe (ou réinstalle) la base de données en un clic, sans passer par phpMyAdmin.
// Utilisable uniquement en local. Supprimez ce fichier une fois le site installé.
require_once __DIR__ . '/config/database.php';

if (!in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1'], true)) {
    http_response_code(403);
    exit('Installation autorisée uniquement depuis la machine locale.');
}

$done = false;
$error = null;
$count = 0;
$users = 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Connexion au serveur MySQL (sans choisir de base : le script la crée)
        $pdo = new PDO('mysql:host=' . DB_HOST . ';charset=utf8mb4', DB_USER, DB_PASS,
                       [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

        $sql = file_get_contents(__DIR__ . '/database/schema.sql');
        $sql = preg_replace('/^\s*--.*$/m', '', $sql);      // lignes de commentaires
        $sql = preg_replace('/[ \t]--[ \t].*$/m', '', $sql); // commentaires en fin de ligne

        // Une instruction par requête : la première erreur est ainsi clairement identifiée
        foreach (preg_split('/;[ \t]*\r?\n/', $sql) as $statement) {
            $statement = trim($statement);
            if ($statement === '') {
                continue;
            }
            try {
                $pdo->exec($statement);
                $count++;
            } catch (PDOException $e) {
                throw new RuntimeException($e->getMessage() . "\n\nInstruction concernée :\n" . mb_substr($statement, 0, 300));
            }
        }
        $users = (int) $pdo->query('SELECT COUNT(*) FROM ' . DB_NAME . '.users')->fetchColumn();
        $done = true;
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}
?><!doctype html>
<html lang="fr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Installation de DesignHub-TN</title>
<style>
  body { font-family: system-ui, "Segoe UI", sans-serif; background: #f6f7fb; color: #1e293b; margin: 0; min-height: 100vh; display: grid; place-items: center; }
  .box { background: #fff; border: 1px solid #e5e7ef; border-radius: 16px; padding: 2rem; max-width: 600px; width: 92%; box-shadow: 0 10px 30px -12px rgba(15,23,42,.18); }
  h1 { margin-top: 0; }
  .btn { background: #4f46e5; color: #fff; border: 0; border-radius: 10px; padding: .75rem 1.4rem; font-size: 1rem; font-weight: 600; cursor: pointer; text-decoration: none; display: inline-block; }
  .ok { background: #ecfdf5; border: 1px solid #a7f3d0; color: #065f46; padding: 1rem; border-radius: 10px; }
  .ko { background: #fef2f2; border: 1px solid #fecaca; color: #991b1b; padding: 1rem; border-radius: 10px; white-space: pre-wrap; font-family: Consolas, monospace; font-size: .85rem; }
  code { background: #eef2ff; padding: 2px 6px; border-radius: 5px; }
</style>
</head>
<body>
<div class="box">
<h1>Installation de DesignHub-TN</h1>
<?php if ($done): ?>
    <div class="ok">✅ Installation réussie : <?= $count ?> instructions exécutées, <?= $users ?> comptes de démonstration créés dans la base <code><?= htmlspecialchars(DB_NAME) ?></code>.</div>
    <p>Mot de passe de tous les comptes de démonstration : <code>demo1234</code></p>
    <p><a class="btn" href="index.php">Ouvrir le site</a></p>
    <p><small>Par sécurité, supprimez maintenant le fichier <code>install.php</code>.</small></p>
<?php else: ?>
    <?php if ($error): ?><div class="ko">❌ Échec de l'installation :

<?= htmlspecialchars($error) ?></div><br><?php endif; ?>
    <p>Ce programme crée la base <code><?= htmlspecialchars(DB_NAME) ?></code> et y charge les tables et les données de démonstration.
       <strong>Si la base existe déjà, elle sera entièrement réinitialisée.</strong></p>
    <p class="small">Prérequis : MySQL démarré dans XAMPP (utilisateur <code><?= htmlspecialchars(DB_USER) ?></code>, voir <code>config/database.php</code>).</p>
    <form method="post"><button class="btn" type="submit">Installer la base de données</button></form>
<?php endif; ?>
</div>
</body>
</html>
