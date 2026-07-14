<?php

declare(strict_types=1);

// API endpoint for student dashboard AJAX actions using Firestore
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/firestore.php';
start_secure_session();

$action = $_GET['action'] ?? '';

if ($action === 'dismiss_nudge') {
    $user = require_login('user');
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        exit;
    }
    
    $db = get_firestore();
    $studentId = (string) $user['id'];

    $db->update('students', $studentId, [
        'completionNudgeDismissed' => true,
        'updatedAt'                => FirestoreClient::now()
    ]);
    
    json_response(['success' => true]);
}

// Default: invalid action
json_response(['success' => false, 'message' => 'Invalid action.'], 400);
