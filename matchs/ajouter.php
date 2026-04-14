<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/session.php';

$pdo = getPdo();
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $dateMatch = $_POST['date_match'] ?: date('Y-m-d');
    $adversaire = normalizeText($_POST['adversaire'] ?? '');
    $lieu = normalizeText($_POST['lieu'] ?? '');
    $description = normalizeText($_POST['description'] ?? '');
    $statut = in_array($_POST['statut'] ?? '', ['à venir', 'terminé'], true) ? $_POST['statut'] : 'à venir';

    $stmt = $pdo->prepare('INSERT INTO matchs (date_match, adversaire, lieu, description, statut) VALUES (?, ?, ?, ?, ?)');
    $stmt->execute([$dateMatch, $adversaire, $lieu, $description, $statut]);
    setFlash('success', 'Match cree avec succes.');
    redirect('/gestion_foot/matchs/voir.php?id=' . $pdo->lastInsertId());
}

renderHeader('Nouveau match');
?>
<div class="card shadow-sm">
    <div class="card-body">
        <form method="post" class="row g-3">
            <?= csrfField() ?>
            <div class="col-md-4">
                <label class="form-label">Date</label>
                <input type="date" name="date_match" class="form-control" value="<?= h(date('Y-m-d')) ?>">
            </div>
            <div class="col-md-4">
                <label class="form-label">Adversaire</label>
                <input type="text" name="adversaire" class="form-control">
            </div>
            <div class="col-md-4">
                <label class="form-label">Lieu</label>
                <input type="text" name="lieu" class="form-control">
            </div>
            <div class="col-12">
                <label class="form-label">Description</label>
                <textarea name="description" class="form-control" rows="4"></textarea>
            </div>
            <div class="col-md-4">
                <label class="form-label">Statut</label>
                <select name="statut" class="form-select">
                    <option value="à venir">A venir</option>
                    <option value="terminé">Termine</option>
                </select>
            </div>
            <div class="col-12">
                <button class="btn btn-success">Enregistrer</button>
            </div>
        </form>
    </div>
</div>
<?php renderFooter();
