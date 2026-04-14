<?php
declare(strict_types=1);

require_once __DIR__ . '/config/session.php';

$pdo = getPdo();
$today = date('Y-m-d');
$month = (int) date('n');
$year = (int) date('Y');

$balance = currentBalance($pdo);
$members = (int) $pdo->query("SELECT COUNT(*) FROM membres WHERE categorie='membre' AND statut='actif'")->fetchColumn();
$supporters = (int) $pdo->query("SELECT COUNT(*) FROM membres WHERE categorie='sympathisant'")->fetchColumn();

$openSessionStmt = $pdo->prepare("SELECT * FROM sessions_cotisation WHERE mois = ? AND annee = ? ORDER BY id DESC LIMIT 1");
$openSessionStmt->execute([$month, $year]);
$currentSession = $openSessionStmt->fetch();
$paidCount = 0;
$unpaidCount = 0;
$alerts = [];
if ($currentSession) {
    $paidStmt = $pdo->prepare("SELECT COUNT(DISTINCT membre_id) FROM cotisations WHERE session_id = ? AND statut = 'payé'");
    $paidStmt->execute([$currentSession['id']]);
    $paidCount = (int) $paidStmt->fetchColumn();
    $unpaidCount = max(0, $members - $paidCount);

    $alertStmt = $pdo->prepare("
        SELECT m.id, m.numero_membre, m.nom, m.prenom
        FROM membres m
        LEFT JOIN cotisations c ON c.membre_id = m.id AND c.session_id = ?
        WHERE m.categorie = 'membre' AND m.statut = 'actif' AND c.id IS NULL
        ORDER BY m.nom, m.prenom
        LIMIT 8
    ");
    $alertStmt->execute([$currentSession['id']]);
    $alerts = $alertStmt->fetchAll();
}

$nextMatch = $pdo->query("SELECT * FROM matchs WHERE date_match >= CURDATE() ORDER BY date_match ASC LIMIT 1")->fetch();
$transactions = $pdo->query("SELECT * FROM finances ORDER BY date_operation DESC, id DESC LIMIT 5")->fetchAll();

$monthlyFlowStmt = $pdo->prepare("
    SELECT type, COALESCE(SUM(montant), 0) AS total
    FROM finances
    WHERE MONTH(date_operation) = ? AND YEAR(date_operation) = ?
    GROUP BY type
");
$monthlyFlowStmt->execute([$month, $year]);
$flow = ['entrée' => 0, 'sortie' => 0];
foreach ($monthlyFlowStmt->fetchAll() as $row) {
    $flow[$row['type']] = (float) $row['total'];
}

renderHeader('Tableau de bord');
?>
<div class="row g-4">
    <div class="col-lg-3 col-md-6">
        <div class="metric-card primary">
            <span>Solde actuel du club</span>
            <strong><?= h(formatAmount($balance)) ?></strong>
        </div>
    </div>
    <div class="col-lg-3 col-md-6">
        <div class="metric-card">
            <span>Membres actifs</span>
            <strong><?= $members ?></strong>
        </div>
    </div>
    <div class="col-lg-3 col-md-6">
        <div class="metric-card">
            <span>Sympathisants</span>
            <strong><?= $supporters ?></strong>
        </div>
    </div>
    <div class="col-lg-3 col-md-6">
        <div class="metric-card">
            <span>Cotisations du mois</span>
            <strong><?= $paidCount ?> payees / <?= $unpaidCount ?> non payees</strong>
        </div>
    </div>
</div>

<div class="row g-4 mt-1">
    <div class="col-lg-8">
        <div class="card shadow-sm h-100">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <strong>Entrees vs sorties du mois</strong>
                <span class="badge text-bg-light"><?= h(monthLabel($month)) ?> <?= $year ?></span>
            </div>
            <div class="card-body">
                <canvas id="financeChart" height="120"></canvas>
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="card shadow-sm h-100">
            <div class="card-header bg-white"><strong>Prochain match</strong></div>
            <div class="card-body">
                <?php if ($nextMatch): ?>
                    <div class="fs-5 fw-bold mb-2"><?= h($nextMatch['adversaire'] ?: 'Adversaire a definir') ?></div>
                    <p class="mb-1"><i class="bi bi-calendar-event"></i> <?= h(formatDateFr($nextMatch['date_match'])) ?></p>
                    <p class="mb-2"><i class="bi bi-geo-alt"></i> <?= h($nextMatch['lieu'] ?: 'Lieu a definir') ?></p>
                    <span class="badge text-bg-success"><?= h($nextMatch['statut']) ?></span>
                <?php else: ?>
                    <p class="text-muted mb-0">Aucun match a venir.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<div class="row g-4 mt-1">
    <div class="col-lg-6">
        <div class="card shadow-sm h-100">
            <div class="card-header bg-white"><strong>Dernieres transactions</strong></div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-striped mb-0">
                        <thead><tr><th>Date</th><th>Type</th><th>Categorie</th><th>Montant</th></tr></thead>
                        <tbody>
                        <?php foreach ($transactions as $transaction): ?>
                            <tr>
                                <td><?= h(formatDateFr($transaction['date_operation'])) ?></td>
                                <td><?= h($transaction['type']) ?></td>
                                <td><?= h($transaction['categorie']) ?></td>
                                <td><?= h(formatAmount((float) $transaction['montant'])) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (!$transactions): ?>
                            <tr><td colspan="4" class="text-center text-muted">Aucune transaction.</td></tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="card shadow-sm h-100">
            <div class="card-header bg-white"><strong>Alertes cotisations impayees</strong></div>
            <div class="card-body">
                <?php if ($alerts): ?>
                    <ul class="list-group list-group-flush">
                        <?php foreach ($alerts as $alert): ?>
                            <li class="list-group-item px-0">
                                <a href="/gestion_foot/membres/voir.php?id=<?= (int) $alert['id'] ?>"><?= h(trim($alert['nom'] . ' ' . $alert['prenom'])) ?></a>
                                <span class="text-muted small">(<?= h($alert['numero_membre']) ?>)</span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php else: ?>
                    <p class="text-muted mb-0">Aucune alerte pour le mois en cours.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
<?php
$chartScript = '<script>
const ctx = document.getElementById("financeChart");
if (ctx) {
  new Chart(ctx, {
    type: "bar",
    data: {
      labels: ["Entrees", "Sorties"],
      datasets: [{
        label: "Flux du mois",
        data: [' . json_encode($flow['entrée']) . ', ' . json_encode($flow['sortie']) . '],
        backgroundColor: ["#003087", "#28a745"]
      }]
    },
    options: { responsive: true, maintainAspectRatio: false }
  });
}
</script>';
renderFooter([$chartScript]);
