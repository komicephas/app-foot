<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/session.php';

$pdo = getPdo();
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $mois = max(1, min(12, (int) ($_POST['mois'] ?? date('n'))));
    $annee = max(2020, (int) ($_POST['annee'] ?? date('Y')));
    $open = $pdo->prepare("SELECT COUNT(*) FROM sessions_cotisation WHERE mois = ? AND annee = ? AND statut = 'ouverte'");
    $open->execute([$mois, $annee]);
    if ((int) $open->fetchColumn() > 0) {
        setFlash('danger', 'Une session ouverte existe deja pour cette periode.');
        redirect('/gestion_foot/cotisations/index.php');
    }
    $stmt = $pdo->prepare('INSERT INTO sessions_cotisation (mois, annee, date_ouverture, statut) VALUES (?, ?, NOW(), ?)');
    $stmt->execute([$mois, $annee, 'ouverte']);
    setFlash('success', 'Session de cotisation ouverte.');
    redirect('/gestion_foot/cotisations/index.php?session_id=' . $pdo->lastInsertId());
}

renderHeader('Ouvrir une session');
?>
<div class="card shadow-sm">
    <div class="card-body">
        <form method="post" class="row g-3">
            <?= csrfField() ?>
            <div class="col-md-6">
                <label class="form-label">Mois</label>
                <select name="mois" class="form-select">
                    <?php for ($i = 1; $i <= 12; $i++): ?>
                        <option value="<?= $i ?>" <?= $i === (int) date('n') ? 'selected' : '' ?>><?= h(monthLabel($i)) ?></option>
                    <?php endfor; ?>
                </select>
            </div>
            <div class="col-md-6">
                <label class="form-label">Annee</label>
                <input type="number" name="annee" class="form-control" value="<?= h(date('Y')) ?>" min="2020" max="2100">
            </div>
            <div class="col-12">
                <button class="btn btn-success">Ouvrir la session</button>
            </div>
        </form>
    </div>
</div>
<?php renderFooter();
