<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/session.php';

$id = (int) ($_GET['id'] ?? 0);
$stmt = getPdo()->prepare('DELETE FROM membres WHERE id = ?');
$stmt->execute([$id]);
setFlash('success', 'Enregistrement supprime.');
redirect('/gestion_foot/membres/index.php');
