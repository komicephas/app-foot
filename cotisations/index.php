<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/session.php';

$pdo = getPdo();
$sessions = $pdo->query('SELECT * FROM sessions_cotisation ORDER BY annee DESC, mois DESC')->fetchAll();
$selectedId = (int) ($_GET['session_id'] ?? ($sessions[0]['id'] ?? 0));
$session = null;
$statusRows = [];

if ($selectedId > 0) {
    $sessionStmt = $pdo->prepare('SELECT * FROM sessions_cotisation WHERE id = ?');
    $sessionStmt->execute([$selectedId]);
    $session = $sessionStmt->fetch();
    if ($session) {
        $stmt = $pdo->prepare("
            SELECT m.*, c.id AS cotisation_id, c.montant, c.date_paiement, c.statut
            FROM membres m
            LEFT JOIN cotisations c ON c.membre_id = m.id AND c.session_id = ?
            WHERE m.categorie = 'membre'
            ORDER BY m.nom, m.prenom
        ");
        $stmt->execute([$selectedId]);
        $statusRows = $stmt->fetchAll();
    }
}

renderHeader('Sessions de cotisation');
?>
<div class="row g-4">
    <div class="col-lg-4">
        <div class="card shadow-sm">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <strong>Sessions</strong>
                <a class="btn btn-sm btn-success" href="/gestion_foot/cotisations/ouvrir.php">Ouvrir</a>
            </div>
            <div class="list-group list-group-flush">
                <?php foreach ($sessions as $row): ?>
                    <a href="?session_id=<?= (int) $row['id'] ?>" class="list-group-item list-group-item-action <?= $selectedId === (int) $row['id'] ? 'active' : '' ?>">
                        <?= h(monthLabel((int) $row['mois'])) ?> <?= h((string) $row['annee']) ?>
                        <span class="badge text-bg-light float-end"><?= h($row['statut']) ?></span>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    <div class="col-lg-8">
        <div class="card shadow-sm">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <strong>Etat des paiements</strong>
                <?php if ($session && $session['statut'] === 'ouverte'): ?>
                    <a class="btn btn-sm btn-outline-danger" href="/gestion_foot/cotisations/fermer.php?id=<?= (int) $session['id'] ?>">Fermer la session</a>
                <?php endif; ?>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-striped mb-0">
                        <thead><tr><th>Membre</th><th>Type</th><th>Montant</th><th>Date paiement</th><th>Statut</th><th class="text-end">Action</th></tr></thead>
                        <tbody>
                        <?php foreach ($statusRows as $row): ?>
                            <?php $amount = $row['montant'] ?: memberTariffs('membre', $row['type'])['cotisation']; ?>
                            <tr>
                                <td><?= h($row['nom'] . ' ' . $row['prenom']) ?></td>
                                <td><?= h($row['type']) ?></td>
                                <td><?= h(formatAmount((float) $amount)) ?></td>
                                <td><?= h(formatDateFr($row['date_paiement'])) ?></td>
                                <td>
                                    <span class="badge <?= $row['cotisation_id'] ? 'text-bg-success' : 'text-bg-danger' ?>">
                                        <?= $row['cotisation_id'] ? 'payé' : 'impayé' ?>
                                    </span>
                                </td>
                                <td class="text-end">
                                    <?php if ($session && $session['statut'] === 'ouverte' && !$row['cotisation_id']): ?>
                                        <a class="btn btn-sm btn-success" href="/gestion_foot/cotisations/payer.php?session_id=<?= (int) $session['id'] ?>&membre_id=<?= (int) $row['id'] ?>">Enregistrer</a>
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (!$statusRows): ?>
                            <tr><td colspan="6" class="text-center text-muted">Aucune session selectionnee.</td></tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
<?php renderFooter();
