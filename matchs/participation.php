<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/session.php';

$pdo = getPdo();
$id = (int) ($_GET['id'] ?? $_POST['match_id'] ?? 0);
$matchStmt = $pdo->prepare('SELECT * FROM matchs WHERE id = ?');
$matchStmt->execute([$id]);
$match = $matchStmt->fetch();

if (!$match) {
    setFlash('danger', 'Match introuvable.');
    redirect('/gestion_foot/matchs/index.php');
}

$members = $pdo->query("SELECT * FROM membres WHERE statut = 'actif' ORDER BY nom, prenom")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $membreId = (int) ($_POST['membre_id'] ?? 0);
    $datePaiement = $_POST['date_paiement'] ?: date('Y-m-d');
    $nomVisiteur = normalizeText($_POST['nom_visiteur'] ?? '');
    $typeParticipant = '';
    $montant = 0;

    if ($membreId > 0) {
        $memberStmt = $pdo->prepare('SELECT * FROM membres WHERE id = ?');
        $memberStmt->execute([$membreId]);
        $member = $memberStmt->fetch();
        if (!$member) {
            setFlash('danger', 'Participant introuvable.');
            redirect('/gestion_foot/matchs/participation.php?id=' . $id);
        }
        $typeParticipant = $member['categorie'] . '_' . $member['type'];
        $montant = (float) memberTariffs($member['categorie'], $member['type'])['match'];
        $description = 'Participation match - ' . $member['nom'] . ' ' . $member['prenom'];
    } else {
        $typeParticipant = in_array($_POST['type_participant'] ?? '', ['sympathisant_adulte', 'sympathisant_jeune', 'membre_adulte', 'membre_jeune'], true) ? $_POST['type_participant'] : 'sympathisant_adulte';
        [$categorie, $type] = explode('_', $typeParticipant);
        $montant = (float) memberTariffs($categorie, $type)['match'];
        $description = 'Participation visiteur - ' . $nomVisiteur;
    }

    $stmt = $pdo->prepare('INSERT INTO participations_match (match_id, membre_id, nom_visiteur, type_participant, montant, date_paiement) VALUES (?, ?, ?, ?, ?, ?)');
    $stmt->execute([$id, $membreId ?: null, $nomVisiteur ?: null, $typeParticipant, $montant, $datePaiement]);
    $participationId = (int) $pdo->lastInsertId();
    financeEntry($pdo, 'entrée', 'Match', $description, $montant, $datePaiement, $participationId, 'match');
    setFlash('success', 'Participation enregistree.');
    redirect('/gestion_foot/matchs/voir.php?id=' . $id);
}

renderHeader('Participations du match');
?>
<div class="card shadow-sm">
    <div class="card-body">
        <p><strong>Match :</strong> <?= h($match['adversaire']) ?> le <?= h(formatDateFr($match['date_match'])) ?></p>
        <form method="post" class="row g-3">
            <?= csrfField() ?>
            <input type="hidden" name="match_id" value="<?= (int) $id ?>">
            <div class="col-md-6">
                <label class="form-label">Membre ou sympathisant existant</label>
                <select name="membre_id" class="form-select">
                    <option value="0">Selectionner plus bas un visiteur si besoin</option>
                    <?php foreach ($members as $member): ?>
                        <option value="<?= (int) $member['id'] ?>"><?= h($member['nom'] . ' ' . $member['prenom'] . ' - ' . $member['categorie'] . ' / ' . $member['type']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">Date paiement</label>
                <input type="date" name="date_paiement" class="form-control" value="<?= h(date('Y-m-d')) ?>">
            </div>
            <div class="col-12"><hr></div>
            <div class="col-md-6">
                <label class="form-label">Nom du visiteur</label>
                <input type="text" name="nom_visiteur" class="form-control" placeholder="Si non present dans la base">
            </div>
            <div class="col-md-6">
                <label class="form-label">Type visiteur</label>
                <select name="type_participant" class="form-select">
                    <option value="sympathisant_adulte">Sympathisant adulte</option>
                    <option value="sympathisant_jeune">Sympathisant jeune</option>
                    <option value="membre_adulte">Membre adulte</option>
                    <option value="membre_jeune">Membre jeune</option>
                </select>
            </div>
            <div class="col-12">
                <button class="btn btn-success">Enregistrer la participation</button>
            </div>
        </form>
    </div>
</div>
<?php renderFooter();
