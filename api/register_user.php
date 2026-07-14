<?php

declare(strict_types=1);

/**
 * api/register_user.php — Student signup with Firebase Authentication.
 *
 * Flow:
 *   1. Browser JS creates the user in Firebase Auth (email + password).
 *   2. JS gets the Firebase ID token and sends it here along with
 *      name, username, and phone via AJAX (JSON body).
 *   3. This endpoint verifies the ID token, extracts the UID, and
 *      stores the profile in MySQL — no password_hash stored.
 *
 * Accepts: JSON POST  { idToken, name, username, phone }
 * Returns: JSON       { success, message }
 */

require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/firebase_auth.php';

start_secure_session();

// ── Only accept POST ──────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['success' => false, 'message' => 'Method not allowed.'], 405);
}

// ── Read JSON body ────────────────────────────────────────────────────────
$body    = (string) file_get_contents('php://input');
$payload = json_decode($body, true);

if (!is_array($payload)) {
    json_response(['success' => false, 'message' => 'Invalid request format.'], 400);
}

try {
    // ── 1. Extract fields ─────────────────────────────────────────────────
    $idToken  = trim((string) ($payload['idToken']  ?? ''));
    $name     = trim((string) ($payload['name']     ?? ''));
    $username = trim((string) ($payload['username'] ?? ''));
    $phone    = trim((string) ($payload['phone']    ?? ''));

    if ($idToken === '') {
        throw new RuntimeException('Firebase ID token is missing.');
    }
    if ($name === '') {
        throw new RuntimeException('Full name is required.');
    }
    if ($username === '') {
        throw new RuntimeException('Username is required.');
    }
    if (strlen($username) < 3 || !preg_match('/^[a-zA-Z0-9._-]+$/', $username)) {
        throw new RuntimeException('Username may only contain letters, numbers, dots, underscores, or hyphens (min 3 chars).');
    }

    // ── 2. Verify Firebase ID token ───────────────────────────────────────
    $firebaseUser = verify_firebase_token($idToken);
    $uid          = $firebaseUser['uid'];
    $email        = $firebaseUser['email'];

    if ($email === '') {
        throw new RuntimeException('Could not retrieve email from Firebase token.');
    }

    // ── 3. Check for duplicate username / email / firebase_uid ───────────
    $pdo  = get_pdo();
    $stmt = $pdo->prepare(
        'SELECT id FROM students WHERE email = :email OR username = :username OR firebase_uid = :uid LIMIT 1'
    );
    $stmt->execute([':email' => $email, ':username' => $username, ':uid' => $uid]);

    if ($stmt->fetch()) {
        throw new RuntimeException('An account already exists for that username, email, or Firebase identity.');
    }

    // ── 4. Insert student row (no password_hash) ──────────────────────────
    $stmt = $pdo->prepare(
        'INSERT INTO students (firebase_uid, name, username, email, phone, status)
         VALUES (:firebase_uid, :name, :username, :email, :phone, :status)'
    );
    $stmt->execute([
        ':firebase_uid' => $uid,
        ':name'         => $name,
        ':username'     => $username,
        ':email'        => $email,
        ':phone'        => $phone ?: null,
        ':status'       => 'active',
    ]);

    json_response(['success' => true, 'message' => 'Student account created. You can now log in.']);

} catch (RuntimeException $e) {
    json_response(['success' => false, 'message' => $e->getMessage()], 400);
}
