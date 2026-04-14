<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/session.php';

$pdo = getPdo();
[$page, $perPage, $offset] = paginate();
$total = (int) $pdo->query('SELECT COUNT(*) FROM matchs')->fetchColumn();
$matchs = $pdo->query("SELECT * FROM matchs ORDER BY date_match DESC LIMIT {$perPage} OFFSET {$offset}")->fetchAll();

renderHeader('Liste des matchs');
?>
<div class="card shadow-sm">
    <div class="card-body">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <strong>Matchs du dimanche</strong>
            <a class="btn btn-success" href="/gestion_foot/matchs/ajouter.php">Nouveau match</a>
        </div>
        <div class="table-responsive">
            <table class="table table-striped">
                <thead><tr><th>Date</th><th>Adversaire</th><th>Lieu</th><th>Statut</th><th class="text-end">Actions</th></tr></thead>
                <tbody>
                <?php foreach ($matchs as $match): ?>
                    <tr>
                        <td><?= h(formatDateFr($match['date_match'])) ?></td>
                        <td><?= h($match['adversaire']) ?></td>
                        <td><?= h($match['lieu']) ?></td>
                        <td><?= h($match['statut']) ?></td>
                        <td class="text-end">
                            <a class="btn btn-sm btn-outline-primary" href="/gestion_foot/matchs/voir.php?id=<?= (int) $match['id'] ?>">Voir</a>
                            <a class="btn btn-sm btn-success" href="/gestion_foot/matchs/participation.php?id=<?= (int) $match['id'] ?>">Participations</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$matchs): ?>
                    <tr><td colspan="5" class="text-center text-muted">Aucun match enregistre.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?= paginationLinks($total, $page, $perPage) ?>
    </div>
</div>
<?php renderFooter();
