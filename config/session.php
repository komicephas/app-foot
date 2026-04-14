<?php
declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_name('gestion_foot_session');
    session_start();
}

require_once __DIR__ . '/fonctions.php';

if (empty($_SESSION['admin_id'])) {
    setFlash('danger', 'Veuillez vous connecter.');
    header('Location: /gestion_foot/index.php');
    exit;
}
