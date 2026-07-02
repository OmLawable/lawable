<?php

declare(strict_types=1);

/**
 * Core PHP configuration for Lawable authentication backend.
 * Uses the shared database config file in the project root.
 *
 * FIX: previously this only checked __DIR__ . '/../config/database.php',
 * which silently fails (and falls back to hardcoded defaults below)
 * unless you actually have a config/ subfolder. This now also checks
 * a couple of common alternate locations so it works whether
 * database.php sits next to config.php, one level up, or in a
 * config/ subfolder. Adjust the list below to match your real layout
 * if needed.
 */
$possibleDatabaseConfigPaths = [
    __DIR__ . '/../config/database.php',
    __DIR__ . '/database.php',
    __DIR__ . '/../database.php',
];

foreach ($possibleDatabaseConfigPaths as $databaseConfigPath) {
    if (file_exists($databaseConfigPath)) {
        require_once $databaseConfigPath;
        break;
    }
}

if (!defined('DB_HOST')) {
    define('DB_HOST', 'localhost');
}
if (!defined('DB_NAME')) {
    define('DB_NAME', 'lawable_db');
}
if (!defined('DB_USER')) {
    define('DB_USER', 'root');
}
if (!defined('DB_PASS')) {
    define('DB_PASS', '');
}
if (!defined('DB_CHARSET')) {
    define('DB_CHARSET', 'utf8mb4');
}
if (!defined('APP_NAME')) {
    define('APP_NAME', 'Lawable');
}