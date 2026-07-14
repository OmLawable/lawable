<?php

declare(strict_types=1);

/**
 * api/register_organization.php — Organization signup with Firebase Authentication.
 *
 * Flow:
 *   1. Browser JS creates the user in Firebase Auth (email + password).
 *   2. JS gets the Firebase ID token and sends it here along with
 *      organization_name, contact_person, username, and phone via AJAX (JSON body).
 *   3. This endpoint verifies the ID token, extracts the UID, and
 *      stores the organization profile in MySQL — no password_hash stored.
 *   4. Organization status defaults to 'inactive' (pending admin approval).
 *   5. A verification_documents row is auto-created for the admin panel.
 *
 * Accepts: JSON POST  { idToken, organization_name, contact_person, username, phone }
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

    // ── 3. Check for duplicates ───────────────────────────────────────────
    $pdo  = get_pdo();
    $stmt = $pdo->prepare(
        'SELECT id FROM organizations WHERE email = :email OR username = :username OR firebase_uid = :uid LIMIT 1'
    );
    $stmt->execute([':email' => $email, ':username' => $username, ':uid' => $uid]);

    if ($stmt->fetch()) {
        throw new RuntimeException('An organization already exists for that username, email, or Firebase identity.');
    }

    // ── 4. Insert organization row (no password_hash) ─────────────────────
    $stmt = $pdo->prepare(
        'INSERT INTO organizations (firebase_uid, organization_name, contact_person, username, email, phone, status)
         VALUES (:firebase_uid, :organization_name, :contact_person, :username, :email, :phone, :status)'
    );
    $stmt->execute([
        ':firebase_uid'      => $uid,
        ':organization_name' => $organizationName,
        ':contact_person'    => $contactPerson,
        ':username'          => $username,
        ':email'             => $email,
        ':phone'             => $phone ?: null,
        ':status'            => 'inactive', // Requires admin approval
    ]);

    $orgId = (int) $pdo->lastInsertId();

    // ── 5. Auto-create a pending verification document ────────────────────
    $stmt = $pdo->prepare(
        'INSERT INTO verification_documents (organization_id, document_type, status, submitted_at)
         VALUES (:oid, :type, :status, NOW())'
    );
    $stmt->execute([
        ':oid'    => $orgId,
        ':type'   => 'registration',
        ':status' => 'pending',
    ]);

    json_response([
        'success' => true,
        'message' => 'Registration submitted. Your organization is pending admin approval. You will be able to log in once verified.',
    ]);

} catch (RuntimeException $e) {
    json_response(['success' => false, 'message' => $e->getMessage()], 400);
}
