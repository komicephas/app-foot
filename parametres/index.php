<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/session.php';

$pdo = getPdo();
$settings = clubSettings();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $clubName = normalizeText($_POST['nom_club'] ?? '');
    $devise = normalizeText($_POST['devise'] ?? 'FCFA') ?: 'FCFA';
    $password = (string) ($_POST['password'] ?? '');

    $logo = $settings['logo'];
    try {
        $newLogo = uploadImage($_FILES['logo'] ?? [], __DIR__ . '/../assets/img');
        if ($newLogo) {
            $logo = $newLogo;
        }
    } catch (Throwable $exception) {
        setFlash('danger', $exception->getMessage());
        redirect('/gestion_foot/parametres/index.php');
    }

    $stmt = $pdo->prepare('UPDATE parametres SET nom_club = ?, logo = ?, devise = ? WHERE id = 1');
    $stmt->execute([$clubName !== '' ? $clubName : 'Mon Club', $logo, $devise]);

    if ($password !== '') {
        $updatePassword = $pdo->prepare('UPDATE admin SET password = ? WHERE id = ?');
        $updatePassword->execute([password_hash($password, PASSWORD_DEFAULT), $_SESSION['admin_id']]);
    }

    setFlash('success', 'Parametres mis a jour.');
    redirect('/gestion_foot/parametres/index.php');
}

renderHeader('Parametres');
?>
<div class="card shadow-sm">
    <div class="card-body">
        <form method="post" enctype="multipart/form-data" class="row g-3">
            <?= csrfField() ?>
            <div class="col-md-6">
                <label class="form-label">Nom du club</label>
                <input type="text" name="nom_club" class="form-control" value="<?= h($settings['nom_club']) ?>" required>
            </div>
            <div class="col-md-3">
                <label class="form-label">Devise</label>
                <input type="text" name="devise" class="form-control" value="<?= h($settings['devise']) ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label">Logo</label>
                <input type="file" name="logo" class="form-control" accept="image/png,image/jpeg,image/webp">
            </div>
            <div class="col-md-6">
                <label class="form-label">Nouveau mot de passe admin</label>
                <input type="password" name="password" class="form-control" placeholder="Laisser vide pour conserver">
            </div>
            <div class="col-12">
                <div class="section-actions">
                    <button class="btn btn-success">Enregistrer</button>
                    <a class="btn btn-outline-primary" href="/gestion_foot/exports/membres.php">Export membres</a>
                    <a class="btn btn-outline-primary" href="/gestion_foot/exports/cotisations.php">Export cotisations</a>
                    <a class="btn btn-outline-primary" href="/gestion_foot/exports/matchs.php">Export matchs</a>
                    <a class="btn btn-outline-primary" href="/gestion_foot/exports/finances.php">Export finances</a>
                </div>
            </div>
        </form>
    </div>
</div>
<?php renderFooter();
