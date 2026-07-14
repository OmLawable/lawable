<?php

declare(strict_types=1);

/**
 * api/login.php — Firebase-based login endpoint for Lawable using Firestore.
 *
 * Flow:
 *   1. Browser JS signs in via Firebase Auth.
 *   2. JS sends { idToken, role } as JSON to this endpoint.
 *   3. PHP verifies token, retrieves student/organization/admin document by UID in Firestore,
 *      checks status, and stores the user data in $_SESSION['user'].
 *   4. Returns { success, message, redirect } JSON.
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
    $idToken = trim((string) ($payload['idToken'] ?? ''));
    $role    = trim((string) ($payload['role']    ?? 'user'));

    if ($idToken === '') {
        throw new RuntimeException('Firebase ID token is missing.');
    }

    // ── Validate role ─────────────────────────────────────────────────────
    $collection = match ($role) {
        'user'         => 'students',
        'organization' => 'organizations',
        'admin'        => 'admins',
        default        => throw new RuntimeException('Invalid account type selected.'),
    };

    // ── Verify the Firebase ID token ──────────────────────────────────────
    $firebaseUser = verify_firebase_token($idToken);
    $uid          = $firebaseUser['uid'];
    $email        = $firebaseUser['email'];

    $db = get_firestore();

    // ── Look up user in Firestore by UID ──────────────────────────────────
    $user = $db->get($collection, $uid);

    // Fallback: search by email if not matched by UID directly (e.g. legacy/third-party)
    if (!$user) {
        $results = $db->query($collection, [['email', 'EQUAL', $email]], 1);
        if (!empty($results)) {
            $user = $results[0];
            $uid = $user['__id'];
        }
    }

    if (!$user) {
        throw new RuntimeException('No account found for this email under the selected account type. Please check your role or register first.');
    }

    if (($user['status'] ?? '') !== 'active') {
        if ($collection === 'organizations') {
            throw new RuntimeException('Your organization account is pending admin approval. You will be notified once verified.');
        }
        throw new RuntimeException('This account is inactive. Please contact support.');
    }

    // ── Write session (identical structure, ID is now firebase_uid string) ──
    $_SESSION['user'] = [
        'id'    => $uid, // Store firebase_uid string instead of MySQL int ID
        'name'  => $collection === 'organizations' ? ($user['contactPerson'] ?? '') : ($user['name'] ?? ''),
        'email' => $user['email'] ?? '',
        'role'  => $role,
        'phone' => $user['phone'] ?? '',
    ];

    if ($collection === 'organizations') {
        $_SESSION['user']['organization_name'] = $user['organizationName'] ?? '';
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
