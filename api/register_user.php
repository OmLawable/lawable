<?php

declare(strict_types=1);

/**
 * api/register_user.php — Student signup with Firebase Authentication & Firestore.
 *
 * Flow:
 *   1. Browser JS creates the user in Firebase Auth (email + password).
 *   2. JS gets the Firebase ID token and sends it here along with
 *      name, username, and phone via AJAX (JSON body).
 *   3. This endpoint verifies the ID token, extracts the UID, and
 *      stores the profile in Firestore.
 *
 * Accepts: JSON POST  { idToken, name, username, phone }
 * Returns: JSON       { success, message }
 */

require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/firebase_auth.php';
require_once __DIR__ . '/../includes/firestore.php';

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

    $db = get_firestore();

    // ── 3. Check for duplicate username / email / firebase_uid ───────────
    $existingById = $db->get('students', $uid);
    if ($existingById !== null) {
        throw new RuntimeException('An account already exists for this Firebase identity.');
    }

    $existingByEmail = $db->query('students', [['email', 'EQUAL', $email]], 1);
    if (!empty($existingByEmail)) {
        throw new RuntimeException('An account already exists for that email.');
    }

    $existingByUsername = $db->query('students', [['username', 'EQUAL', $username]], 1);
    if (!empty($existingByUsername)) {
        throw new RuntimeException('An account already exists for that username.');
    }

    // Also check organizations to make sure username/email is globally unique
    $existingOrgEmail = $db->query('organizations', [['email', 'EQUAL', $email]], 1);
    if (!empty($existingOrgEmail)) {
        throw new RuntimeException('An account already exists for that email.');
    }

    $existingOrgUsername = $db->query('organizations', [['username', 'EQUAL', $username]], 1);
    if (!empty($existingOrgUsername)) {
        throw new RuntimeException('An account already exists for that username.');
    }

    // ── 4. Insert student document into Firestore ────────────────────────
    $now = FirestoreClient::now();
    $studentDoc = [
        'name'            => $name,
        'username'        => $username,
        'email'           => $email,
        'phone'           => $phone,
        'status'          => 'active',
        'city'            => '',
        'bio'             => '',
        'dateOfBirth'     => '',
        'institution'     => '',
        'course'          => '',
        'yearSemester'    => '',
        'areasOfInterest' => '',
        'resumeFile'      => '',
        'linkedinUrl'     => '',
        'skills'          => '',
        'createdAt'       => $now,
        'updatedAt'       => $now
    ];

    $db->set('students', $studentDoc, $uid);

    json_response(['success' => true, 'message' => 'Student account created. You can now log in.']);

} catch (RuntimeException $e) {
    json_response(['success' => false, 'message' => $e->getMessage()], 400);
}
