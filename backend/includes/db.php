<?php

declare(strict_types=1);

require_once __DIR__ . '/../config.php';

/**
 * Returns a shared PDO connection for MySQL.
 */
function get_pdo(bool $useDatabase = true): PDO
{
    static $pdo = null;
    static $pdoWithoutDb = null;

    if ($useDatabase && $pdo instanceof PDO) {
        return $pdo;
    }

    if (!$useDatabase && $pdoWithoutDb instanceof PDO) {
        return $pdoWithoutDb;
    }

    $dsn = $useDatabase
        ? sprintf('mysql:host=%s;dbname=%s;charset=%s', DB_HOST, DB_NAME, DB_CHARSET)
        : sprintf('mysql:host=%s;charset=%s', DB_HOST, DB_CHARSET);

    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ];

    try {
        $connection = new PDO($dsn, DB_USER, DB_PASS, $options);
    } catch (PDOException $exception) {
        throw new RuntimeException('Database connection failed. Please verify your MySQL settings and import the schema.', 0, $exception);
    }

    if ($useDatabase) {
        $pdo = $connection;
    } else {
        $pdoWithoutDb = $connection;
    }

    return $connection;
}
