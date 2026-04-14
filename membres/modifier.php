<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/session.php';

$pdo = getPdo();
$id = (int) ($_GET['id'] ?? 0);
$stmt = $pdo->prepare('SELECT * FROM membres WHERE id = ?');
$stmt->execute([$id]);
$member = $stmt->fetch();

if (!$member) {
    setFlash('danger', 'Membre introuvable.');
    redirect('/gestion_foot/membres/index.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $categorie = in_array($_POST['categorie'] ?? '', ['membre', 'sympathisant'], true) ? $_POST['categorie'] : $member['categorie'];
    $type = in_array($_POST['type'] ?? '', ['jeune', 'adulte'], true) ? $_POST['type'] : $member['type'];
    $nom = normalizeText($_POST['nom'] ?? '');
    $prenom = normalizeText($_POST['prenom'] ?? '');
    $dateNaissance = $_POST['date_naissance'] ?: null;
    $telephone = normalizeText($_POST['telephone'] ?? '');
    $adresse = normalizeText($_POST['adresse'] ?? '');
    $dateInscription = $_POST['date_inscription'] ?: $member['date_inscription'];
    $anneeInscription = (int) date('Y', strtotime($dateInscription));
    $statut = in_array($_POST['statut'] ?? '', ['actif', 'inactif'], true) ? $_POST['statut'] : $member['statut'];

    $photo = $member['photo'];
    try {
        $newPhoto = uploadImage($_FILES['photo'] ?? [], __DIR__ . '/../uploads/photos');
        if ($newPhoto) {
            $photo = $newPhoto;
        }
    } catch (Throwable $exception) {
        setFlash('danger', $exception->getMessage());
        redirect('/gestion_foot/membres/modifier.php?id=' . $id);
    }

    $numero = $member['numero_membre'];
    if ($categorie === 'membre' && !$numero) {
        $numero = generateMemberNumber($pdo, $anneeInscription);
    }
    if ($categorie === 'sympathisant') {
        $numero = null;
    }

    $update = $pdo->prepare('UPDATE membres SET numero_membre=?, nom=?, prenom=?, date_naissance=?, telephone=?, adresse=?, categorie=?, type=?, date_inscription=?, annee_inscription=?, photo=?, statut=? WHERE id=?');
    $update->execute([$numero, $nom, $prenom, $dateNaissance, $telephone, $adresse, $categorie, $type, $dateInscription, $anneeInscription, $photo, $statut, $id]);
    setFlash('success', 'Enregistrement mis a jour.');
    redirect('/gestion_foot/membres/voir.php?id=' . $id);
}

renderHeader('Modifier un membre');
?>
<div class="card shadow-sm">
    <div class="card-body">
        <form method="post" enctype="multipart/form-data" class="row g-3">
            <?= csrfField() ?>
            <div class="col-md-4">
                <label class="form-label">Categorie</label>
                <select name="categorie" class="form-select">
                    <option value="membre" <?= $member['categorie'] === 'membre' ? 'selected' : '' ?>>Membre</option>
                    <option value="sympathisant" <?= $member['categorie'] === 'sympathisant' ? 'selected' : '' ?>>Sympathisant</option>
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label">Type</label>
                <select name="type" class="form-select">
                    <option value="adulte" <?= $member['type'] === 'adulte' ? 'selected' : '' ?>>Adulte</option>
                    <option value="jeune" <?= $member['type'] === 'jeune' ? 'selected' : '' ?>>Jeune</option>
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label">Statut</label>
                <select name="statut" class="form-select">
                    <option value="actif" <?= $member['statut'] === 'actif' ? 'selected' : '' ?>>Actif</option>
                    <option value="inactif" <?= $member['statut'] === 'inactif' ? 'selected' : '' ?>>Inactif</option>
                </select>
            </div>
            <div class="col-md-6">
                <label class="form-label">Nom</label>
                <input type="text" name="nom" class="form-control" required value="<?= h($member['nom']) ?>">
            </div>
            <div class="col-md-6">
                <label class="form-label">Prenom</label>
                <input type="text" name="prenom" class="form-control" required value="<?= h($member['prenom']) ?>">
            </div>
            <div class="col-md-4">
                <label class="form-label">Date de naissance</label>
                <input type="date" name="date_naissance" class="form-control" value="<?= h($member['date_naissance']) ?>">
            </div>
            <div class="col-md-4">
                <label class="form-label">Telephone</label>
                <input type="text" name="telephone" class="form-control" value="<?= h($member['telephone']) ?>">
            </div>
            <div class="col-md-4">
                <label class="form-label">Date inscription</label>
                <input type="date" name="date_inscription" class="form-control" value="<?= h($member['date_inscription']) ?>">
            </div>
            <div class="col-12">
                <label class="form-label">Adresse</label>
                <textarea name="adresse" class="form-control" rows="3"><?= h($member['adresse']) ?></textarea>
            </div>
            <div class="col-md-6">
                <label class="form-label">Photo</label>
                <input type="file" name="photo" class="form-control" accept="image/png,image/jpeg,image/webp">
            </div>
            <div class="col-12">
                <button class="btn btn-primary">Mettre a jour</button>
                <a href="/gestion_foot/membres/voir.php?id=<?= (int) $member['id'] ?>" class="btn btn-secondary">Retour</a>
            </div>
        </form>
    </div>
</div>
<?php renderFooter();
