<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/firestore.php';
start_secure_session();

$user = require_login('user');

header('Content-Type: application/json');

$type  = trim((string) ($_GET['type'] ?? ''));
$value = trim((string) ($_GET['value'] ?? ''));

if (!in_array($type, ['username', 'email'], true) || $value === '') {
    echo json_encode(['available' => false, 'message' => 'Invalid parameters.']);
    exit;
}

$db = get_firestore();
$currentUid = (string) $user['id'];

try {
    if ($type === 'username') {
        // Validate format
        if (strlen($value) < 3 || strlen($value) > 30 || !preg_match('/^[a-zA-Z0-9._-]+$/', $value)) {
            echo json_encode(['available' => false, 'message' => 'Username must be 3-30 chars, alphanumeric or spec characters (._-).']);
            exit;
        }

        // Check students
        $matches = $db->query('students', [['username', 'EQUAL', $value]], 5);
        foreach ($matches as $m) {
            if ($m['__id'] !== $currentUid) {
                echo json_encode(['available' => false, 'message' => 'This username is already taken.']);
                exit;
            }
        }

        // Check organizations
        $orgMatches = $db->query('organizations', [['username', 'EQUAL', $value]], 1);
        if (!empty($orgMatches)) {
            echo json_encode(['available' => false, 'message' => 'This username is already taken by an organization.']);
            exit;
        }
    } else {
        // Validate email format
        if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
            echo json_encode(['available' => false, 'message' => 'Please enter a valid email address.']);
            exit;
        }

        // Check students
        $matches = $db->query('students', [['email', 'EQUAL', $value]], 5);
        foreach ($matches as $m) {
            if ($m['__id'] !== $currentUid) {
                echo json_encode(['available' => false, 'message' => 'This email is already registered.']);
                exit;
            }
        }

        // Check organizations
        $orgMatches = $db->query('organizations', [['email', 'EQUAL', $value]], 1);
        if (!empty($orgMatches)) {
            echo json_encode(['available' => false, 'message' => 'This email is already registered by an organization.']);
            exit;
        }
    }

    echo json_encode(['available' => true]);
} catch (Throwable $e) {
    echo json_encode(['available' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
