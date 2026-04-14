<?php
declare(strict_types=1);

require_once __DIR__ . '/config/fonctions.php';

session_destroy();
session_start();
setFlash('success', 'Vous etes deconnecte.');
redirect('/gestion_foot/index.php');
