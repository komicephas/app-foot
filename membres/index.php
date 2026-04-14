<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/session.php';

$pdo = getPdo();
$filterCategory = $_GET['categorie'] ?? '';
$search = normalizeText($_GET['q'] ?? '');
[$page, $perPage, $offset] = paginate();

$where = [];
$params = [];
if (in_array($filterCategory, ['membre', 'sympathisant'], true)) {
    $where[] = 'categorie = ?';
    $params[] = $filterCategory;
}
if ($search !== '') {
    $where[] = '(nom LIKE ? OR prenom LIKE ? OR numero_membre LIKE ?)';
    $like = '%' . $search . '%';
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
}

$sqlWhere = $where ? 'WHERE ' . implode(' AND ', $where) : '';
$countStmt = $pdo->prepare("SELECT COUNT(*) FROM membres {$sqlWhere}");
$countStmt->execute($params);
$total = (int) $countStmt->fetchColumn();

$stmt = $pdo->prepare("SELECT * FROM membres {$sqlWhere} ORDER BY created_at DESC LIMIT {$perPage} OFFSET {$offset}");
$stmt->execute($params);
$members = $stmt->fetchAll();

renderHeader('Liste des membres');
?>
<div class="card shadow-sm">
    <div class="card-body">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-3">
            <form class="row g-2">
                <div class="col-auto">
                    <input type="text" name="q" class="form-control" placeholder="Recherche" value="<?= h($search) ?>">
                </div>
                <div class="col-auto">
                    <select name="categorie" class="form-select">
                        <option value="">Toutes categories</option>
                        <option value="membre" <?= $filterCategory === 'membre' ? 'selected' : '' ?>>Membres</option>
                        <option value="sympathisant" <?= $filterCategory === 'sympathisant' ? 'selected' : '' ?>>Sympathisants</option>
                    </select>
                </div>
                <div class="col-auto">
                    <button class="btn btn-outline-primary">Filtrer</button>
                </div>
            </form>
            <div class="section-actions">
                <a class="btn btn-success" href="/gestion_foot/membres/ajouter.php?categorie=membre">Ajouter un membre</a>
                <a class="btn btn-outline-success" href="/gestion_foot/membres/ajouter.php?categorie=sympathisant">Ajouter un sympathisant</a>
            </div>
        </div>
        <div class="table-responsive">
            <table class="table table-striped align-middle">
                <thead>
                    <tr>
                        <th>N° membre</th>
                        <th>Nom</th>
                        <th>Categorie</th>
                        <th>Type</th>
                        <th>Date inscription</th>
                        <th>Telephone</th>
                        <th>Statut</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($members as $member): ?>
                    <tr>
                        <td><?= h($member['numero_membre'] ?: '-') ?></td>
                        <td><?= h($member['nom'] . ' ' . $member['prenom']) ?></td>
                        <td><span class="badge text-bg-primary"><?= h($member['categorie']) ?></span></td>
                        <td><?= h($member['type']) ?></td>
                        <td><?= h(formatDateFr($member['date_inscription'])) ?></td>
                        <td><?= h($member['telephone']) ?></td>
                        <td><?= h($member['statut']) ?></td>
                        <td class="text-end">
                            <a class="btn btn-sm btn-outline-primary" href="/gestion_foot/membres/voir.php?id=<?= (int) $member['id'] ?>">Voir</a>
                            <a class="btn btn-sm btn-primary" href="/gestion_foot/membres/modifier.php?id=<?= (int) $member['id'] ?>">Modifier</a>
                            <a class="btn btn-sm btn-danger" data-confirm="Supprimer cet enregistrement ?" href="/gestion_foot/membres/supprimer.php?id=<?= (int) $member['id'] ?>">Supprimer</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$members): ?>
                    <tr><td colspan="8" class="text-center text-muted">Aucun enregistrement.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?= paginationLinks($total, $page, $perPage) ?>
    </div>
</div>
<?php renderFooter();
