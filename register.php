<?php
// register.php : inscription (client ou designer)
require_once __DIR__ . '/includes/functions.php';

if (current_user()) {
    redirect('dashboard.php');
}

$errors = [];
$old = ['full_name' => '', 'email' => '', 'role' => 'client'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $old['full_name'] = trim($_POST['full_name'] ?? '');
    $old['email']     = trim($_POST['email'] ?? '');
    $old['role']      = $_POST['role'] ?? '';
    $password         = $_POST['password'] ?? '';
    $confirm          = $_POST['confirm'] ?? '';

    // ----- Validation côté serveur (celle du navigateur ne suffit jamais) -----
    if (mb_strlen($old['full_name']) < 3 || mb_strlen($old['full_name']) > 100) {
        $errors[] = 'Le nom doit contenir entre 3 et 100 caractères.';
    }
    if (!filter_var($old['email'], FILTER_VALIDATE_EMAIL) || mb_strlen($old['email']) > 150) {
        $errors[] = 'Adresse email invalide.';
    }
    if (!in_array($old['role'], ['client', 'designer'], true)) {
        $errors[] = 'Rôle invalide.';
    }
    if (strlen($password) < 8) {
        $errors[] = 'Le mot de passe doit contenir au moins 8 caractères.';
    }
    if ($password !== $confirm) {
        $errors[] = 'Les deux mots de passe ne sont pas identiques.';
    }

    // Email déjà utilisé ?
    if (!$errors) {
        $stmt = db()->prepare('SELECT id FROM users WHERE email = ?');
        $stmt->execute([$old['email']]);
        if ($stmt->fetch()) {
            $errors[] = 'Un compte existe déjà avec cet email.';
        }
    }

    // ----- Création du compte -----
    if (!$errors) {
        $pdo = db();
        $pdo->beginTransaction();   // les 2 INSERT réussissent ensemble ou pas du tout
        $stmt = $pdo->prepare('INSERT INTO users (full_name, email, password_hash, role) VALUES (?, ?, ?, ?)');
        $stmt->execute([$old['full_name'], $old['email'], password_hash($password, PASSWORD_DEFAULT), $old['role']]);
        $newId = (int) $pdo->lastInsertId();

        if ($old['role'] === 'designer') {
            // profil vide que le designer complètera ensuite
            $pdo->prepare('INSERT INTO designer_profiles (user_id) VALUES (?)')->execute([$newId]);
        }
        $pdo->commit();

        session_regenerate_id(true);
        $_SESSION['user_id'] = $newId;
        set_flash('success', 'Compte créé avec succès !' . ($old['role'] === 'designer' ? ' Complétez maintenant votre profil.' : ''));
        redirect($old['role'] === 'designer' ? 'edit-profile.php' : 'dashboard.php');
    }
}

$page_title = 'Inscription';
$active = '';
require __DIR__ . '/includes/header.php';
?>
<section class="auth-box card">
    <h1>Créer un compte</h1>
    <?php if ($errors): ?>
        <div class="alert alert-error"><?php foreach ($errors as $err): ?><div><?= e($err) ?></div><?php endforeach; ?></div>
    <?php endif; ?>

    <form method="post" novalidate>
        <?= csrf_field() ?>
        <fieldset class="role-choice">
            <legend>Je suis…</legend>
            <label><input type="radio" name="role" value="client" <?= $old['role'] !== 'designer' ? 'checked' : '' ?>> Client (je publie des projets)</label>
            <label><input type="radio" name="role" value="designer" <?= $old['role'] === 'designer' ? 'checked' : '' ?>> Designer freelance (je présente mon portfolio)</label>
        </fieldset>
        <label>Nom complet
            <input type="text" name="full_name" value="<?= e($old['full_name']) ?>" maxlength="100" required>
        </label>
        <label>Adresse email
            <input type="email" name="email" value="<?= e($old['email']) ?>" maxlength="150" required>
        </label>
        <label>Mot de passe (8 caractères minimum)
            <input type="password" name="password" minlength="8" required>
        </label>
        <label>Confirmer le mot de passe
            <input type="password" name="confirm" minlength="8" required>
        </label>
        <button class="btn btn-block" type="submit">Créer mon compte</button>
    </form>
    <p class="small center">Déjà inscrit ? <a href="login.php">Se connecter</a></p>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
