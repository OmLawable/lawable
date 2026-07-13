<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/functions.php';
start_secure_session();

$user = require_login();
$flash = get_flash();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Dashboard — Lawable</title>
    <style>
        body { font-family: Inter, sans-serif; margin:0; background:#07111f; color:#f7f7f2; }
        main { max-width: 900px; margin: 2rem auto; padding: 2rem; background: rgba(255,255,255,0.08); border-radius: 24px; }
        a { color:#f2c94c; }
    </style>
</head>
<body>
<main>
    <h1>Welcome, <?= e($user['name']) ?></h1>
    <p>You are logged in as <strong><?= e($user['role']) ?></strong>.</p>

    <?php if ($flash): ?>
        <p style="padding:0.8rem 1rem; border-radius:12px; background:#14532d;"><?= e($flash['message']) ?></p>
    <?php endif; ?>

    <p><a href="logout.php">Logout</a></p>
</main>
</body>
</html>
