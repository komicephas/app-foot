<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/session.php';

$pdo = getPdo();
$id = (int) ($_GET['id'] ?? 0);
$stmt = $pdo->prepare('SELECT * FROM membres WHERE id = ?');
$stmt->execute([$id]);
$member = $stmt->fetch();

if (!$member) {
    setFlash('danger', 'Enregistrement introuvable.');
    redirect('/gestion_foot/membres/index.php');
}

$payments = [];
if ($member['categorie'] === 'membre') {
    $payStmt = $pdo->prepare('SELECT * FROM cotisations WHERE membre_id = ? ORDER BY annee DESC, mois DESC');
    $payStmt->execute([$id]);
    $payments = $payStmt->fetchAll();
}

renderHeader('Fiche membre');
?>
<div class="row g-4">
    <div class="col-lg-4">
        <div class="card-member">
            <div class="text-center mb-3">
                <img src="<?= h($member['photo'] ? '/gestion_foot/uploads/photos/' . $member['photo'] : '/gestion_foot/assets/img/logo_club.png') ?>" alt="Photo" class="rounded-circle" style="width:120px;height:120px;object-fit:cover;">
            </div>
            <h2 class="h5 text-center"><?= h($member['nom'] . ' ' . $member['prenom']) ?></h2>
            <p class="text-center text-muted"><?= h($member['categorie']) ?> / <?= h($member['type']) ?></p>
            <div class="d-grid gap-2">
                <a class="btn btn-primary" href="/gestion_foot/membres/modifier.php?id=<?= (int) $member['id'] ?>">Modifier</a>
                <?php if ($member['categorie'] === 'membre'): ?>
                    <a class="btn btn-outline-success" target="_blank" href="/gestion_foot/membres/carte.php?id=<?= (int) $member['id'] ?>">Carte membre PDF</a>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <div class="col-lg-8">
        <div class="card shadow-sm mb-4">
            <div class="card-header bg-white"><strong>Informations generales</strong></div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-6"><strong>N° membre :</strong> <?= h($member['numero_membre'] ?: '-') ?></div>
                    <div class="col-md-6"><strong>Statut :</strong> <?= h($member['statut']) ?></div>
                    <div class="col-md-6"><strong>Date naissance :</strong> <?= h(formatDateFr($member['date_naissance'])) ?></div>
                    <div class="col-md-6"><strong>Telephone :</strong> <?= h($member['telephone']) ?></div>
                    <div class="col-md-6"><strong>Date inscription :</strong> <?= h(formatDateFr($member['date_inscription'])) ?></div>
                    <div class="col-md-6"><strong>Annee carte :</strong> <?= h((string) $member['annee_inscription']) ?></div>
                    <div class="col-12"><strong>Adresse :</strong> <?= h($member['adresse']) ?></div>
                </div>
            </div>
        </div>
        <?php if ($member['categorie'] === 'membre'): ?>
            <div class="card shadow-sm">
                <div class="card-header bg-white"><strong>Historique des cotisations</strong></div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-striped mb-0">
                            <thead><tr><th>Mois</th><th>Annee</th><th>Montant</th><th>Date</th><th>Statut</th></tr></thead>
                            <tbody>
                            <?php foreach ($payments as $payment): ?>
                                <tr>
                                    <td><?= h(monthLabel((int) $payment['mois'])) ?></td>
                                    <td><?= h((string) $payment['annee']) ?></td>
                                    <td><?= h(formatAmount((float) $payment['montant'])) ?></td>
                                    <td><?= h(formatDateFr($payment['date_paiement'])) ?></td>
                                    <td><?= h($payment['statut']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if (!$payments): ?>
                                <tr><td colspan="5" class="text-center text-muted">Aucune cotisation enregistree.</td></tr>
                            <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>
<?php renderFooter();
