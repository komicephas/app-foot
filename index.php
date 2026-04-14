<?php
declare(strict_types=1);

require_once __DIR__ . '/config/fonctions.php';

if (!is_file(__DIR__ . '/install.php')) {
    try {
        getPdo();
    } catch (Throwable $exception) {
        setFlash('danger', 'Base de donnees inaccessible.');
    }
}

if (!is_file(__DIR__ . '/install.php') && !empty($_SESSION['admin_id'])) {
    redirect('/gestion_foot/dashboard.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $username = normalizeText($_POST['username'] ?? '');
    $password = (string) ($_POST['password'] ?? '');

    $stmt = getPdo()->prepare('SELECT * FROM admin WHERE username = ? LIMIT 1');
    $stmt->execute([$username]);
    $admin = $stmt->fetch();

    if ($admin && password_verify($password, $admin['password'])) {
        $_SESSION['admin_id'] = (int) $admin['id'];
        $_SESSION['admin_username'] = $admin['username'];
        redirect('/gestion_foot/dashboard.php');
    }

    setFlash('danger', 'Identifiants invalides.');
    redirect('/gestion_foot/index.php');
}

renderHeader('Connexion');
?>
<div class="login-card">
    <div class="text-center mb-4">
        <img src="/gestion_foot/assets/img/logo_club.png" alt="Logo du club" class="login-logo mb-3">
        <h1 class="h3 mb-1">Gestion Club de Football</h1>
        <p class="text-muted mb-0">Connectez-vous avec le compte administrateur</p>
    </div>
    <?php if (is_file(__DIR__ . '/install.php')): ?>
        <div class="alert alert-warning">Installation detectee. <a href="/gestion_foot/install.php">Lancer l'installation</a>.</div>
    <?php endif; ?>
    <form method="post">
        <?= csrfField() ?>
        <div class="mb-3">
            <label class="form-label">Login</label>
            <input type="text" name="username" class="form-control" required value="admin">
        </div>
        <div class="mb-3">
            <label class="form-label">Mot de passe</label>
            <input type="password" name="password" class="form-control" required value="admin123">
        </div>
        <button class="btn btn-primary w-100">Se connecter</button>
    </form>
</div>
<?php renderFooter();
