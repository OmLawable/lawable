<?php
/**
 * Lawable Project Reorganization Script
 * Phase 1: restructure without breaking paths
 */

$root = __DIR__;
$dryRun = false; // set to true to preview

// ── 1. New directory structure ──
$dirs = [
    $root . '/includes',     // shared PHP helpers
    $root . '/api',          // AJAX/backend endpoints
    $root . '/schema',       // SQL files
    $root . '/migrations',   // SQL migrations (from backend/migrations)
];

foreach ($dirs as $d) {
    if (!is_dir($d)) {
        mkdir($d, 0755, true);
        echo "Created: $d\n";
    }
}

// ── 2. Move files to new locations ──
$moves = [
    // [from, to]

    // Config & includes
    [$root . '/backend/includes/db.php',       $root . '/includes/db.php'],
    [$root . '/backend/includes/functions.php', $root . '/includes/functions.php'],
    [$root . '/backend/config.php',           $root . '/includes/config.php'],

    // Schema
    [$root . '/backend/schema.sql',           $root . '/schema/schema.sql'],

    // Migrations
];

// Copy schema.sql
copy($root . '/backend/schema.sql', $root . '/schema/schema.sql');
echo "Moved: schema/schema.sql\n";

// Copy migration files
$migFiles = glob($root . '/backend/migrations/*.sql');
foreach ($migFiles as $f) {
    $name = basename($f);
    copy($f, $root . '/migrations/' . $name);
    echo "Moved: migrations/$name\n";
}

// Seed dashboard script
copy($root . '/backend/seed_dashboard.php', $root . '/seed_dashboard.php');
echo "Copied: seed_dashboard.php\n";

echo "\n--- File movement complete ---\n";
echo "Now run Phase 2: Path Updates\n";
