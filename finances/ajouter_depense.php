<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/session.php';

$pdo = getPdo();
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $categorie = normalizeText($_POST['categorie'] ?? '');
    $description = normalizeText($_POST['description'] ?? '');
    $montant = (float) ($_POST['montant'] ?? 0);
    $dateOperation = $_POST['date_operation'] ?: date('Y-m-d');

    if ($categorie === '' || $montant <= 0) {
        setFlash('danger', 'Categorie et montant valides obligatoires.');
        redirect('/gestion_foot/finances/ajouter_depense.php');
    }

    financeEntry($pdo, 'sortie', $categorie, $description, $montant, $dateOperation);
    setFlash('success', 'Depense enregistree.');
    redirect('/gestion_foot/finances/index.php');
}

renderHeader('Ajouter une depense');
?>
<div class="card shadow-sm">
    <div class="card-body">
        <form method="post" class="row g-3">
            <?= csrfField() ?>
            <div class="col-md-6">
                <label class="form-label">Categorie</label>
                <input type="text" name="categorie" class="form-control" required>
            </div>
            <div class="col-md-3">
                <label class="form-label">Montant</label>
                <input type="number" name="montant" class="form-control" min="1" step="1" required>
            </div>
            <div class="col-md-3">
                <label class="form-label">Date</label>
                <input type="date" name="date_operation" class="form-control" value="<?= h(date('Y-m-d')) ?>">
            </div>
            <div class="col-12">
                <label class="form-label">Motif</label>
                <textarea name="description" class="form-control" rows="4"></textarea>
            </div>
            <div class="col-12">
                <button class="btn btn-success">Enregistrer</button>
            </div>
        </form>
    </div>
</div>
<?php renderFooter();
