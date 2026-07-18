<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/firestore.php';
start_secure_session();

$user = require_login('admin');
$db   = get_firestore();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['success' => false, 'message' => 'Method not allowed.'], 405);
}

$body    = (string)file_get_contents('php://input');
$payload = json_decode($body, true);
if (!is_array($payload)) {
    json_response(['success' => false, 'message' => 'Invalid request.'], 400);
}

$id = trim((string)($payload['id'] ?? ''));
if ($id === '') {
    json_response(['success' => false, 'message' => 'Inquiry ID is required.'], 400);
}

$existing = $db->get('support_queries', $id);
if (!$existing) {
    json_response(['success' => false, 'message' => 'Inquiry not found.'], 404);
}

try {
    if (($payload['action'] ?? '') === 'delete') {
        $db->delete('support_queries', $id);
        json_response(['success' => true, 'message' => 'Inquiry deleted.']);
    }

    $updates = [];

    if (isset($payload['status'])) {
        $allowed = ['new', 'in_progress', 'resolved'];
        $status  = trim((string)$payload['status']);
        if (!in_array($status, $allowed, true)) {
            json_response(['success' => false, 'message' => 'Invalid status value.'], 400);
        }
        $updates['status']    = $status;
        $updates['updatedAt'] = FirestoreClient::now();
        $updates['updatedBy'] = (string)($user['id'] ?? 'admin');
    }

    if (isset($payload['admin_note'])) {
        $updates['adminNote'] = trim((string)$payload['admin_note']);
        $updates['updatedAt'] = FirestoreClient::now();
    }

    if (empty($updates)) {
        json_response(['success' => false, 'message' => 'Nothing to update.'], 400);
    }

    $db->update('support_queries', $id, $updates);
    json_response(['success' => true, 'message' => 'Inquiry updated.']);

} catch (\Throwable $e) {
    json_response(['success' => false, 'message' => 'Update failed: ' . $e->getMessage()], 500);
}