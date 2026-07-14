<?php

declare(strict_types=1);

/**
 * api/login.php — Firebase-based login endpoint for Lawable.
 *
 * Flow:
 *   1. Browser JS signs in via Firebase Auth (signInWithEmailAndPassword).
 *   2. JS sends { idToken, role } as a JSON body to this endpoint.
 *   3. PHP verifies the ID token, looks up the user in MySQL by firebase_uid
 *      (falling back to email for accounts not yet migrated), checks status,
 *      and writes the same $_SESSION['user'] structure as before.
 *   4. Returns { success, message, redirect } as JSON.
 *
 * Accepts: JSON POST  { idToken, role }
 * Returns: JSON       { success, message, redirect? }
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
    $idToken = trim((string) ($payload['idToken'] ?? ''));
    $role    = trim((string) ($payload['role']    ?? 'user'));

    if ($idToken === '') {
        throw new RuntimeException('Firebase ID token is missing.');
    }

    // ── Validate role ─────────────────────────────────────────────────────
    $table = match ($role) {
        'user'         => 'students',
        'organization' => 'organizations',
        'admin'        => 'admins',
        default        => throw new RuntimeException('Invalid account type selected.'),
    };

    // ── Verify the Firebase ID token ──────────────────────────────────────
    $firebaseUser = verify_firebase_token($idToken);
    $uid          = $firebaseUser['uid'];
    $email        = $firebaseUser['email'];

    // ── Look up user in MySQL ─────────────────────────────────────────────
    // Try firebase_uid first (new accounts), fall back to email (legacy rows)
    $pdo = get_pdo();

    $stmt = match ($role) {
        'organization' => $pdo->prepare(
            "SELECT id, organization_name, contact_person AS name, username, email, phone, status, firebase_uid
             FROM {$table}
             WHERE firebase_uid = :uid OR email = :email
             LIMIT 1"
        ),
        'admin' => $pdo->prepare(
            "SELECT id, name, username, email, '' AS phone, status, firebase_uid
             FROM {$table}
             WHERE firebase_uid = :uid OR email = :email
             LIMIT 1"
        ),
        default => $pdo->prepare(
            "SELECT id, name, username, email, phone, status, firebase_uid
             FROM {$table}
             WHERE firebase_uid = :uid OR email = :email
             LIMIT 1"
        ),
    };

    $stmt->execute([':uid' => $uid, ':email' => $email]);
    $user = $stmt->fetch();

    if (!$user) {
        throw new RuntimeException('No account found for this email under the selected account type. Please check your role or register first.');
    }

    if (($user['status'] ?? '') !== 'active') {
        if ($role === 'organization') {
            throw new RuntimeException('Your organization account is pending admin approval. You will be notified once verified.');
        }
        throw new RuntimeException('This account is inactive. Please contact support.');
    }

    // ── Backfill firebase_uid if this is a legacy account ────────────────
    if (empty($user['firebase_uid'])) {
        $pdo->prepare("UPDATE {$table} SET firebase_uid = :uid WHERE id = :id")
            ->execute([':uid' => $uid, ':id' => $user['id']]);
    }

    // ── Write session (identical structure to the old system) ─────────────
    $_SESSION['user'] = [
        'id'    => (int) $user['id'],
        'name'  => $user['name'],
        'email' => $user['email'],
        'role'  => $role,
        'phone' => $user['phone'] ?? '',
    ];

    if ($role === 'organization') {
        $_SESSION['user']['organization_name'] = $user['organization_name'] ?? '';
    }

    // ── Role-based redirect ───────────────────────────────────────────────
    $redirect = match ($role) {
        'admin' => '/lawable/pages/admin/dashboard.php',
        default => '/lawable/pages/dashboard.php',
    };

    json_response([
        'success'  => true,
        'message'  => 'Login successful.',
        'redirect' => $redirect,
    ]);

} catch (RuntimeException $e) {
    json_response(['success' => false, 'message' => $e->getMessage()], 400);
}
