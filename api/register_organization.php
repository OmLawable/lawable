<?php

declare(strict_types=1);

/**
 * api/register_organization.php — Organization signup with Firebase Authentication & Firestore.
 *
 * Flow:
 *   1. Browser JS creates the user in Firebase Auth (email + password).
 *   2. JS gets the Firebase ID token and sends it here along with
 *      organization_name, contact_person, username, and phone via AJAX (JSON body).
 *   3. This endpoint verifies the ID token, extracts the UID, and
 *      stores the organization profile in Firestore.
 *   4. Organization status defaults to 'inactive' (pending admin approval).
 *   5. A verificationRequests document is auto-created for the admin panel.
 *
 * Accepts: JSON POST  { idToken, organization_name, contact_person, username, phone }
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
    $idToken          = trim((string) ($payload['idToken']           ?? ''));
    $organizationName = trim((string) ($payload['organization_name'] ?? ''));
    $contactPerson    = trim((string) ($payload['contact_person']    ?? ''));
    $username         = trim((string) ($payload['username']          ?? ''));
    $phone            = trim((string) ($payload['phone']             ?? ''));

    if ($idToken === '') {
        throw new RuntimeException('Firebase ID token is missing.');
    }
    if ($organizationName === '') {
        throw new RuntimeException('Organization name is required.');
    }
    if ($contactPerson === '') {
        throw new RuntimeException('Contact person name is required.');
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

    // ── 3. Check for duplicates ───────────────────────────────────────────
    $existingById = $db->get('organizations', $uid);
    if ($existingById !== null) {
        throw new RuntimeException('An organization already exists for this Firebase identity.');
    }

    $existingByEmail = $db->query('organizations', [['email', 'EQUAL', $email]], 1);
    if (!empty($existingByEmail)) {
        throw new RuntimeException('An organization already exists for that email.');
    }

    $existingByUsername = $db->query('organizations', [['username', 'EQUAL', $username]], 1);
    if (!empty($existingByUsername)) {
        throw new RuntimeException('An organization already exists for that username.');
    }

    // Also check students to make sure username/email is globally unique
    $existingStudentEmail = $db->query('students', [['email', 'EQUAL', $email]], 1);
    if (!empty($existingStudentEmail)) {
        throw new RuntimeException('An account already exists for that email.');
    }

    $existingStudentUsername = $db->query('students', [['username', 'EQUAL', $username]], 1);
    if (!empty($existingStudentUsername)) {
        throw new RuntimeException('An account already exists for that username.');
    }

    // Also check teachers to make sure username/email is globally unique
    $existingTeacherEmail = $db->query('teachers', [['email', 'EQUAL', $email]], 1);
    if (!empty($existingTeacherEmail)) {
        throw new RuntimeException('An account already exists for that email.');
    }

    $existingTeacherUsername = $db->query('teachers', [['username', 'EQUAL', $username]], 1);
    if (!empty($existingTeacherUsername)) {
        throw new RuntimeException('An account already exists for that username.');
    }

    // ── 4. Insert organization document into Firestore ─────────────────────
    $now = FirestoreClient::now();
    $orgDoc = [
        'organizationName' => $organizationName,
        'contactPerson'    => $contactPerson,
        'username'         => $username,
        'email'            => $email,
        'phone'            => $phone,
        'status'           => 'inactive', // Requires admin approval
        'displayName'      => '',
        'officialEmail'    => '',
        'organizationType' => '',
        'tagline'          => '',
        'aboutDescription' => '',
        'yearEstablished'  => null,
        'websiteUrl'       => '',
        'createdAt'        => $now,
        'updatedAt'        => $now
    ];

    $db->set('organizations', $orgDoc, $uid);

    // ── 5. Auto-create a pending verification request in Firestore ────────
    $verifDoc = [
        'organizationId' => $uid,
        'documentType'   => 'registration',
        'filePath'       => '',
        'status'         => 'pending',
        'adminNotes'     => '',
        'submittedAt'    => $now,
        'reviewedBy'     => '',
        'reviewedAt'     => ''
    ];

    $db->set('verificationRequests', $verifDoc, $uid);

    json_response([
        'success' => true,
        'message' => 'Registration submitted. Your organization is pending admin approval. You will be able to log in once verified.',
    ]);

} catch (RuntimeException $e) {
    json_response(['success' => false, 'message' => $e->getMessage()], 400);
}
