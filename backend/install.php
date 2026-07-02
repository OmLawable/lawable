<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/functions.php';

try {
    $pdo = get_pdo(false);
    $pdo->exec('DROP DATABASE IF EXISTS ' . DB_NAME);
    $pdo->exec('CREATE DATABASE IF NOT EXISTS ' . DB_NAME);
    $pdo = get_pdo();

    $sql = file_get_contents(__DIR__ . '/schema.sql');
    $statements = array_filter(array_map('trim', preg_split('/;\s*(?:\r?\n|$)/', $sql)));

    foreach ($statements as $statement) {
        if ($statement === '') {
            continue;
        }
        $pdo->exec($statement);
    }

    echo 'Database initialized successfully. Tables created: students, organizations, admins.';
} catch (Throwable $exception) {
    echo 'Installation failed: ' . $exception->getMessage();
}
