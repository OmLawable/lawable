<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/firestore.php';
require_once __DIR__ . '/../includes/credits.php';

start_secure_session();

if (!is_logged_in()) {
    json_response(['success' => false, 'message' => 'Unauthorized. Please log in first.'], 401);
}

$user = current_user();
$studentId = (string) ($user['id'] ?? '');

$body = (string) file_get_contents('php://input');
$payload = json_decode($body, true);

if (!is_array($payload)) {
    $payload = $_POST;
}

$courseId = trim((string) ($payload['course_id'] ?? ''));
if ($courseId === '') {
    json_response(['success' => false, 'message' => 'Invalid or missing course ID.'], 400);
}

$res = unlock_course_with_credits($studentId, $courseId, 500);

if ($res['success']) {
    json_response($res, 200);
} else {
    json_response($res, 400);
}
