<?php
require_once __DIR__ . '/includes/db.php';

try {
    $pdo = get_pdo();
    $stmt = $pdo->prepare('SELECT email FROM admins WHERE email = :email LIMIT 1');
    $stmt->execute([':email' => 'admin@lawable.in']);
    $admin = $stmt->fetch(PDO::FETCH_ASSOC);
    echo $admin ? 'Admin found in `admins` table.' : 'Admin not found. Run install.php first.';
} catch (Throwable $e) {
    echo 'Database error: ' . $e->getMessage();
}
