<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/firestore.php';

start_secure_session();

// Ensure the user is logged in
$user = current_user();
if ($user === null) {
    json_response(['success' => false, 'message' => 'You must be logged in to submit queries.'], 401);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['success' => false, 'message' => 'Method not allowed.'], 405);
}

// Read JSON input
$body = (string) file_get_contents('php://input');
$payload = json_decode($body, true);

if (!is_array($payload)) {
    json_response(['success' => false, 'message' => 'Invalid request format.'], 400);
}

$subject = trim((string) ($payload['subject'] ?? ''));
$message = trim((string) ($payload['message'] ?? ''));

if ($subject === '' || $message === '') {
    json_response(['success' => false, 'message' => 'Please select a subject and type a message.'], 400);
}

try {
    $db = get_firestore();
    
    $queryDoc = [
        'userId'    => (string) $user['id'],
        'name'      => (string) ($user['name'] ?? ''),
        'email'     => (string) ($user['email'] ?? ''),
        'role'      => (string) ($user['role'] ?? 'user'),
        'subject'   => $subject,
        'message'   => $message,
        'createdAt' => FirestoreClient::now()
    ];
    
    // Add to support_queries collection using set() with auto-generated ID
    $db->set('support_queries', $queryDoc);
    
    json_response([
        'success' => true,
        'message' => 'Your message has been sent successfully. We will get back to you soon!'
    ]);
} catch (\Throwable $e) {
    json_response([
        'success' => false,
        'message' => 'An error occurred while saving your message. Please try again.'
    ], 500);
}
