<?php
// login.php : connexion
require_once __DIR__ . '/includes/functions.php';

if (current_user()) {
    redirect('dashboard.php');
}

$error = '';
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    // Requête préparée : l'email saisi n'est jamais collé dans le SQL (protection contre l'injection SQL)
    $stmt = db()->prepare('SELECT id, password_hash FROM users WHERE email = ?');
    $stmt->execute([$email]);
    $row = $stmt->fetch();

    // password_verify compare le mot de passe saisi avec le hash stocké
    if ($row && password_verify($password, $row['password_hash'])) {
        session_regenerate_id(true);          // nouvel identifiant de session (contre la fixation de session)
        $_SESSION['user_id'] = (int) $row['id'];
        set_flash('success', 'Connexion réussie. Bienvenue !');
        redirect('dashboard.php');
    }
    // Même message dans les deux cas : on ne révèle pas si l'email existe
    $error = 'Email ou mot de passe incorrect.';
}

$page_title = 'Connexion';
$active = '';
require __DIR__ . '/includes/header.php';
?>
<section class="auth-box card">
    <h1>Connexion</h1>
    <?php if ($error): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>

    <form method="post" novalidate>
        <?= csrf_field() ?>
        <label>Adresse email
            <input type="email" name="email" value="<?= e($email) ?>" required autofocus>
        </label>
        <label>Mot de passe
            <input type="password" name="password" required>
        </label>
        <button class="btn btn-block" type="submit">Se connecter</button>
    </form>

    <p class="small center">Pas encore de compte ? <a href="register.php">Créer un compte</a></p>
    <div class="demo-box small">
        <strong>Comptes de démonstration</strong> (mot de passe : <code>demo1234</code>)<br>
        Client : <code>client@designhub.tn</code><br>
        Designer : <code>yassine@designhub.tn</code><br>
        Administrateur : <code>admin@designhub.tn</code> (mot de passe : <code>admin1234</code>)
    </div>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
