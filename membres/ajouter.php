<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/session.php';

$pdo = getPdo();
$defaultCategory = in_array($_GET['categorie'] ?? '', ['membre', 'sympathisant'], true) ? $_GET['categorie'] : 'membre';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $categorie = in_array($_POST['categorie'] ?? '', ['membre', 'sympathisant'], true) ? $_POST['categorie'] : 'membre';
    $type = in_array($_POST['type'] ?? '', ['jeune', 'adulte'], true) ? $_POST['type'] : 'adulte';
    $nom = normalizeText($_POST['nom'] ?? '');
    $prenom = normalizeText($_POST['prenom'] ?? '');
    $dateNaissance = $_POST['date_naissance'] ?: null;
    $telephone = normalizeText($_POST['telephone'] ?? '');
    $adresse = normalizeText($_POST['adresse'] ?? '');
    $dateInscription = $_POST['date_inscription'] ?: date('Y-m-d');
    $anneeInscription = (int) date('Y', strtotime($dateInscription));
    $statut = in_array($_POST['statut'] ?? '', ['actif', 'inactif'], true) ? $_POST['statut'] : 'actif';

    if ($nom === '' || $prenom === '') {
        setFlash('danger', 'Nom et prenom sont obligatoires.');
        redirect('/gestion_foot/membres/ajouter.php?categorie=' . $categorie);
    }

    try {
        $photo = uploadImage($_FILES['photo'] ?? [], __DIR__ . '/../uploads/photos');
        $numero = $categorie === 'membre' ? generateMemberNumber($pdo, $anneeInscription) : null;
        $stmt = $pdo->prepare('INSERT INTO membres (numero_membre, nom, prenom, date_naissance, telephone, adresse, categorie, type, date_inscription, annee_inscription, photo, statut) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
        $stmt->execute([$numero, $nom, $prenom, $dateNaissance, $telephone, $adresse, $categorie, $type, $dateInscription, $anneeInscription, $photo, $statut]);
        $memberId = (int) $pdo->lastInsertId();

        if ($categorie === 'membre') {
            $tariffs = memberTariffs($categorie, $type);
            financeEntry($pdo, 'entrée', 'Inscription', 'Inscription de ' . $nom . ' ' . $prenom, (float) $tariffs['inscription'], $dateInscription, $memberId, 'membre');
        }

        setFlash('success', ucfirst($categorie) . ' ajoute avec succes.');
        redirect('/gestion_foot/membres/voir.php?id=' . $memberId);
    } catch (Throwable $exception) {
        setFlash('danger', $exception->getMessage());
        redirect('/gestion_foot/membres/ajouter.php?categorie=' . $categorie);
    }
}

renderHeader(($defaultCategory === 'membre' ? 'Ajouter un membre' : 'Ajouter un sympathisant'));
?>
<div class="card shadow-sm">
    <div class="card-body">
        <form method="post" enctype="multipart/form-data" class="row g-3">
            <?= csrfField() ?>
            <div class="col-md-4">
                <label class="form-label">Categorie</label>
                <select name="categorie" class="form-select">
                    <option value="membre" <?= $defaultCategory === 'membre' ? 'selected' : '' ?>>Membre</option>
                    <option value="sympathisant" <?= $defaultCategory === 'sympathisant' ? 'selected' : '' ?>>Sympathisant</option>
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label">Type</label>
                <select name="type" class="form-select">
                    <option value="adulte">Adulte</option>
                    <option value="jeune">Jeune</option>
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label">Statut</label>
                <select name="statut" class="form-select">
                    <option value="actif">Actif</option>
                    <option value="inactif">Inactif</option>
                </select>
            </div>
            <div class="col-md-6">
                <label class="form-label">Nom</label>
                <input type="text" name="nom" class="form-control" required>
            </div>
            <div class="col-md-6">
                <label class="form-label">Prenom</label>
                <input type="text" name="prenom" class="form-control" required>
            </div>
            <div class="col-md-4">
                <label class="form-label">Date de naissance</label>
                <input type="date" name="date_naissance" class="form-control">
            </div>
            <div class="col-md-4">
                <label class="form-label">Telephone</label>
                <input type="text" name="telephone" class="form-control">
            </div>
            <div class="col-md-4">
                <label class="form-label">Date inscription</label>
                <input type="date" name="date_inscription" class="form-control" value="<?= h(date('Y-m-d')) ?>" required>
            </div>
            <div class="col-12">
                <label class="form-label">Adresse</label>
                <textarea name="adresse" class="form-control" rows="3"></textarea>
            </div>
            <div class="col-md-6">
                <label class="form-label">Photo</label>
                <input type="file" name="photo" class="form-control" accept="image/png,image/jpeg,image/webp">
            </div>
            <div class="col-12">
                <button class="btn btn-success">Enregistrer</button>
                <a href="/gestion_foot/membres/index.php" class="btn btn-secondary">Annuler</a>
            </div>
        </form>
    </div>
</div>
<?php renderFooter();
