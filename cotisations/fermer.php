<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/session.php';

$id = (int) ($_GET['id'] ?? 0);
$stmt = getPdo()->prepare("UPDATE sessions_cotisation SET statut = 'fermée', date_fermeture = NOW() WHERE id = ?");
$stmt->execute([$id]);
setFlash('success', 'Session fermee.');
redirect('/gestion_foot/cotisations/index.php?session_id=' . $id);
