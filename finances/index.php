<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/session.php';

$pdo = getPdo();
[$page, $perPage, $offset] = paginate();
$total = (int) $pdo->query('SELECT COUNT(*) FROM finances')->fetchColumn();
$rows = $pdo->query("SELECT * FROM finances ORDER BY date_operation DESC, id DESC LIMIT {$perPage} OFFSET {$offset}")->fetchAll();

renderHeader('Tableau financier');
?>
<div class="card shadow-sm">
    <div class="card-body">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <strong>Mouvements financiers</strong>
            <div class="section-actions">
                <a class="btn btn-success" href="/gestion_foot/finances/ajouter_depense.php">Ajouter une depense</a>
                <a class="btn btn-outline-primary" href="/gestion_foot/finances/solde.php">Voir le solde</a>
            </div>
        </div>
        <div class="table-responsive">
            <table class="table table-striped">
                <thead><tr><th>Date</th><th>Type</th><th>Categorie</th><th>Description</th><th>Montant</th></tr></thead>
                <tbody>
                <?php foreach ($rows as $row): ?>
                    <tr>
                        <td><?= h(formatDateFr($row['date_operation'])) ?></td>
                        <td><?= h($row['type']) ?></td>
                        <td><?= h($row['categorie']) ?></td>
                        <td><?= h($row['description']) ?></td>
                        <td><?= h(formatAmount((float) $row['montant'])) ?></td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$rows): ?>
                    <tr><td colspan="5" class="text-center text-muted">Aucune operation.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?= paginationLinks($total, $page, $perPage) ?>
    </div>
</div>
<?php renderFooter();
