<?php

declare(strict_types=1);

// API endpoint for student dashboard AJAX actions
require_once __DIR__ . '/../includes/functions.php';
start_secure_session();

$action = $_GET['action'] ?? '';

if ($action === 'dismiss_nudge') {
    $user = require_login('user');
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        exit;
    }
    $pdo = get_pdo();
    $stmt = $pdo->prepare("UPDATE student_profiles SET completion_nudge_dismissed = 1 WHERE student_id = :sid");
    $stmt->execute([':sid' => (int) $user['id']]);
    json_response(['success' => true]);
}

// Default: invalid action
json_response(['success' => false, 'message' => 'Invalid action.'], 400);
