<?php
/**
 * Batch update all path references in the project.
 */

$root = __DIR__;
$files = [];

// Collect all PHP files in the project (excluding tmp_* and legacy/)
$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root));
foreach ($iterator as $file) {
    if ($file->isDir()) continue;
    $path = $file->getPathname();
    $rel = str_replace($root . '/', '', $path);
    // Skip non-PHP, tmp files, and deleted folders
    if (!str_ends_with($path, '.php') && !str_ends_with($path, '.html') && !str_ends_with($path, '.htaccess')) continue;
    if (str_starts_with($rel, 'tmp_')) continue;
    if (str_starts_with($rel, 'legacy')) continue;
    if (str_starts_with($rel, '.git')) continue;
    if (str_starts_with($rel, 'node_modules')) continue;
    if (str_starts_with($rel, 'uploads')) continue;
    if (str_starts_with($rel, 'assets/')) continue;
    if ($file->getBasename() === 'schema.sql') continue;
    if ($file->getBasename() === 'seed_dashboard.php') continue;
    $files[] = $path;
}

echo "Files to process: " . count($files) . "\n\n";

$replacements = [];

// Map OLD include/require paths to NEW paths
foreach ($files as $filepath) {
    $content = file_get_contents($filepath);
    $original = $content;

    // --- Replace backend/includes/ references ---
    // These files need to use the new /includes/ location
    // But pages/ files reference backend/includes/functions.php — update that
    if (str_starts_with($filepath, $root . '/pages/') || 
        in_array(basename($filepath), ['home.php', 'edit-profile.php', 'edit-org-profile.php'])) {
        $content = str_replace(
            "require_once __DIR__ . '/../backend/includes/functions.php';",
            "require_once __DIR__ . '/../includes/functions.php';",
            $content
        );
    }

    // Root-level files that reference backend/includes/
    if (in_array(basename($filepath), ['home.php', 'edit-profile.php', 'edit-org-profile.php'])) {
        $content = str_replace(
            "require_once __DIR__ . '/backend/includes/functions.php';",
            "require_once __DIR__ . '/includes/functions.php';",
            $content
        );
    }

    // backend/ files that reference includes/ (keeping as-is since they'll be the working copies)
    
    // Update CSS/JS paths in pages/ that reference "../assets/" — already correct
    
    // Update api/ files that reference "includes/"
    if (str_starts_with($filepath, $root . '/api/')) {
        // Already fixed the functions.php path above
        // But also fix the old backend path fallback in dashboard-ajax.php
        $content = str_replace(
            "require_once __DIR__ . '/auth/dashboard.php';",
            "require_once __DIR__ . '/dashboard.php';",
            $content
        );
    }

    if ($content !== $original) {
        file_put_contents($filepath, $content);
        echo "  Updated: " . basename($filepath) . "\n";
    }
}

// Now update page-level files (login.php, courses.php, etc.) that reference
// backend/ URL paths in hrefs and form actions
foreach ($files as $filepath) {
    $content = file_get_contents($filepath);
    $original = $content;

    $basename = basename($filepath);

    // Update URL references: ../api/login.php → ../api/login.php etc.
    $content = str_replace(
        "../api/login.php",
        "../api/login.php",
        $content
    );
    $content = str_replace(
        "../api/logout.php",
        "../api/logout.php",
        $content
    );
    $content = str_replace(
        "../api/register_user.php",
        "../api/register_user.php",
        $content
    );
    $content = str_replace(
        "../api/register_organization.php",
        "../api/register_organization.php",
        $content
    );
    $content = str_replace(
        "../api/enroll.php",
        "../api/enroll.php",
        $content
    );
    $content = str_replace(
        "../api/handle_verification.php",
        "../api/handle_verification.php",
        $content
    );
    $content = str_replace(
        "../api/dashboard-ajax.php",
        "../api/dashboard-ajax.php",
        $content
    );

    // Root-level hrefs to backend/
    $content = str_replace(
        "href='api/logout.php'",
        "href='api/logout.php'",
        $content
    );
    $content = str_replace(
        "href=\"backend/logout.php\"",
        "href=\"api/logout.php\"",
        $content
    );
    $content = str_replace(
        "href='api/dashboard-ajax.php'",
        "href='api/dashboard-ajax.php'",
        $content
    );

    if ($content !== $original) {
        file_put_contents($filepath, $content);
        echo "  URL-updated: $basename\n";
    }
}

echo "\n--- Path update complete ---\n";
echo "Next: Update remaining includes (login.php, courses.php)\n";
