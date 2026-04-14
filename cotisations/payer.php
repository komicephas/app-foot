<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/session.php';

$pdo = getPdo();
$sessionId = (int) ($_GET['session_id'] ?? $_POST['session_id'] ?? 0);
$membreId = (int) ($_GET['membre_id'] ?? $_POST['membre_id'] ?? 0);

$sessionStmt = $pdo->prepare("SELECT * FROM sessions_cotisation WHERE id = ?");
$sessionStmt->execute([$sessionId]);
$session = $sessionStmt->fetch();

$memberStmt = $pdo->prepare("SELECT * FROM membres WHERE id = ? AND categorie = 'membre'");
$memberStmt->execute([$membreId]);
$member = $memberStmt->fetch();

if (!$session || !$member) {
    setFlash('danger', 'Session ou membre introuvable.');
    redirect('/gestion_foot/cotisations/index.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $datePaiement = $_POST['date_paiement'] ?: date('Y-m-d');
    $amount = (float) memberTariffs('membre', $member['type'])['cotisation'];
    $exists = $pdo->prepare('SELECT COUNT(*) FROM cotisations WHERE membre_id = ? AND session_id = ?');
    $exists->execute([$membreId, $sessionId]);
    if ((int) $exists->fetchColumn() > 0) {
        setFlash('danger', 'Cotisation deja enregistree pour ce membre.');
        redirect('/gestion_foot/cotisations/index.php?session_id=' . $sessionId);
    }
    $stmt = $pdo->prepare("INSERT INTO cotisations (membre_id, session_id, mois, annee, montant, date_paiement, statut) VALUES (?, ?, ?, ?, ?, ?, 'payé')");
    $stmt->execute([$membreId, $sessionId, $session['mois'], $session['annee'], $amount, $datePaiement]);
    $cotisationId = (int) $pdo->lastInsertId();
    financeEntry($pdo, 'entrée', 'Cotisation', 'Cotisation ' . monthLabel((int) $session['mois']) . ' ' . $session['annee'] . ' - ' . $member['nom'] . ' ' . $member['prenom'], $amount, $datePaiement, $cotisationId, 'cotisation');
    setFlash('success', 'Cotisation enregistree.');
    redirect('/gestion_foot/cotisations/index.php?session_id=' . $sessionId);
}

renderHeader('Paiement de cotisation');
?>
<div class="card shadow-sm">
    <div class="card-body">
        <p class="mb-3">Session : <strong><?= h(monthLabel((int) $session['mois'])) ?> <?= h((string) $session['annee']) ?></strong></p>
        <p class="mb-3">Membre : <strong><?= h($member['nom'] . ' ' . $member['prenom']) ?></strong></p>
        <form method="post" class="row g-3">
            <?= csrfField() ?>
            <input type="hidden" name="session_id" value="<?= (int) $sessionId ?>">
            <input type="hidden" name="membre_id" value="<?= (int) $membreId ?>">
            <div class="col-md-6">
                <label class="form-label">Montant</label>
                <input type="text" class="form-control" value="<?= h(formatAmount((float) memberTariffs('membre', $member['type'])['cotisation'])) ?>" readonly>
            </div>
            <div class="col-md-6">
                <label class="form-label">Date paiement</label>
                <input type="date" name="date_paiement" class="form-control" value="<?= h(date('Y-m-d')) ?>">
            </div>
            <div class="col-12">
                <button class="btn btn-success">Valider</button>
            </div>
        </form>
    </div>
</div>
<?php renderFooter();
