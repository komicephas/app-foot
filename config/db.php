<?php
declare(strict_types=1);

const DB_HOST = '127.0.0.1';
const DB_PORT = 3306;
const DB_NAME = 'gestion_foot';
const DB_USER = 'root';
const DB_PASS = '';

function getPdo(bool $withoutDb = false): PDO
{
    static $pdo = null;
    static $pdoServer = null;

    if ($withoutDb) {
        if ($pdoServer instanceof PDO) {
            return $pdoServer;
        }

        $dsn = sprintf('mysql:host=%s;port=%d;charset=utf8mb4', DB_HOST, DB_PORT);
        $pdoServer = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);

        return $pdoServer;
    }

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', DB_HOST, DB_PORT, DB_NAME);

    try {
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
    } catch (PDOException $exception) {
        $installFile = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'install.php';
        if (is_file($installFile) && PHP_SAPI !== 'cli') {
            header('Location: /gestion_foot/install.php');
            exit;
        }

        throw $exception;
    }

    return $pdo;
}
