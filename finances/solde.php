<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/session.php';

$pdo = getPdo();
$incomes = (float) $pdo->query("SELECT COALESCE(SUM(montant), 0) FROM finances WHERE type = 'entrée'")->fetchColumn();
$expenses = (float) $pdo->query("SELECT COALESCE(SUM(montant), 0) FROM finances WHERE type = 'sortie'")->fetchColumn();
$balance = $incomes - $expenses;

renderHeader('Solde du club');
?>
<div class="row g-4">
    <div class="col-md-4"><div class="metric-card primary"><span>Solde actuel</span><strong><?= h(formatAmount($balance)) ?></strong></div></div>
    <div class="col-md-4"><div class="metric-card"><span>Total entrees</span><strong><?= h(formatAmount($incomes)) ?></strong></div></div>
    <div class="col-md-4"><div class="metric-card"><span>Total sorties</span><strong><?= h(formatAmount($expenses)) ?></strong></div></div>
</div>
<?php renderFooter();
