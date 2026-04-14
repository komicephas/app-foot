<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/session.php';

$pdo = getPdo();
$id = (int) ($_GET['id'] ?? 0);
$stmt = $pdo->prepare('SELECT * FROM matchs WHERE id = ?');
$stmt->execute([$id]);
$match = $stmt->fetch();

if (!$match) {
    setFlash('danger', 'Match introuvable.');
    redirect('/gestion_foot/matchs/index.php');
}

$participantsStmt = $pdo->prepare("
    SELECT pm.*, m.nom, m.prenom
    FROM participations_match pm
    LEFT JOIN membres m ON m.id = pm.membre_id
    WHERE pm.match_id = ?
    ORDER BY pm.id DESC
");
$participantsStmt->execute([$id]);
$participants = $participantsStmt->fetchAll();

renderHeader('Details du match');
?>
<div class="card shadow-sm mb-4">
    <div class="card-body">
        <div class="d-flex justify-content-between align-items-start flex-wrap gap-3">
            <div>
                <h2 class="h4"><?= h($match['adversaire'] ?: 'Adversaire a definir') ?></h2>
                <p class="mb-1"><i class="bi bi-calendar-event"></i> <?= h(formatDateFr($match['date_match'])) ?></p>
                <p class="mb-1"><i class="bi bi-geo-alt"></i> <?= h($match['lieu']) ?></p>
                <p class="text-muted mb-0"><?= h($match['description']) ?></p>
            </div>
            <a class="btn btn-success" href="/gestion_foot/matchs/participation.php?id=<?= (int) $match['id'] ?>">Ajouter des participations</a>
        </div>
    </div>
</div>
<div class="card shadow-sm">
    <div class="card-header bg-white"><strong>Participants</strong></div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-striped mb-0">
                <thead><tr><th>Participant</th><th>Type</th><th>Montant</th><th>Date paiement</th></tr></thead>
                <tbody>
                <?php foreach ($participants as $participant): ?>
                    <tr>
                        <td><?= h($participant['nom'] ? $participant['nom'] . ' ' . $participant['prenom'] : $participant['nom_visiteur']) ?></td>
                        <td><?= h($participant['type_participant']) ?></td>
                        <td><?= h(formatAmount((float) $participant['montant'])) ?></td>
                        <td><?= h(formatDateFr($participant['date_paiement'])) ?></td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$participants): ?>
                    <tr><td colspan="4" class="text-center text-muted">Aucune participation enregistree.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php renderFooter();
