<?php

declare(strict_types=1);

/**
 * api/register_teacher.php — Teacher signup with Firebase Authentication & Firestore.
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
    $idToken        = trim((string) ($payload['idToken']        ?? ''));
    $name           = trim((string) ($payload['name']           ?? ''));
    $username       = trim((string) ($payload['username']       ?? ''));
    $phone          = trim((string) ($payload['phone']          ?? ''));
    $organizationId = trim((string) ($payload['organizationId'] ?? ''));

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
    if ($organizationId === '') {
        throw new RuntimeException('Affiliated organization is required.');
    }

    // ── 2. Verify Firebase ID token ───────────────────────────────────────
    $firebaseUser = verify_firebase_token($idToken);
    $uid          = $firebaseUser['uid'];
    $email        = $firebaseUser['email'];

    if ($email === '') {
        throw new RuntimeException('Could not retrieve email from Firebase token.');
    }

    $db = get_firestore();

    // Verify Organization exists and is active
    $org = $db->get('organizations', $organizationId);
    if (!$org || ($org['status'] ?? '') !== 'active') {
        throw new RuntimeException('Selected organization is invalid or inactive.');
    }

    // ── 3. Check for duplicate username / email / firebase_uid ───────────
    $existingById = $db->get('teachers', $uid);
    if ($existingById !== null) {
        throw new RuntimeException('An account already exists for this Firebase identity.');
    }

    // Check teachers
    $existingByEmail = $db->query('teachers', [['email', 'EQUAL', $email]], 1);
    if (!empty($existingByEmail)) {
        throw new RuntimeException('An account already exists for that email.');
    }
    $existingByUsername = $db->query('teachers', [['username', 'EQUAL', $username]], 1);
    if (!empty($existingByUsername)) {
        throw new RuntimeException('An account already exists for that username.');
    }

    // Check students
    $existingStudentEmail = $db->query('students', [['email', 'EQUAL', $email]], 1);
    if (!empty($existingStudentEmail)) {
        throw new RuntimeException('An account already exists for that email.');
    }
    $existingStudentUsername = $db->query('students', [['username', 'EQUAL', $username]], 1);
    if (!empty($existingStudentUsername)) {
        throw new RuntimeException('An account already exists for that username.');
    }

    // Check organizations
    $existingOrgEmail = $db->query('organizations', [['email', 'EQUAL', $email]], 1);
    if (!empty($existingOrgEmail)) {
        throw new RuntimeException('An account already exists for that email.');
    }
    $existingOrgUsername = $db->query('organizations', [['username', 'EQUAL', $username]], 1);
    if (!empty($existingOrgUsername)) {
        throw new RuntimeException('An account already exists for that username.');
    }

    // ── 4. Insert teacher document into Firestore ────────────────────────
    $now = FirestoreClient::now();
    $teacherDoc = [
        'name'             => $name,
        'username'         => $username,
        'email'            => $email,
        'phone'            => $phone,
        'organizationId'   => $organizationId,
        'organizationName' => $org['organizationName'] ?? '',
        'status'           => 'pending',
        'bio'              => '',
        'qualification'    => '',
        'specialization'   => '',
        'experience'       => '',
        'designation'      => '',
        'headline'         => '',
        'publicEmail'      => '',
        'avatar'           => 'avatar1.png',
        'dateOfBirth'      => '',
        'gender'           => '',
        'createdAt'        => $now,
        'updatedAt'        => $now
    ];

    $db->set('teachers', $teacherDoc, $uid);

    json_response(['success' => true, 'message' => 'Teacher registration submitted. Your account is pending organization approval. You can log in once verified.']);

} catch (RuntimeException $e) {
    json_response(['success' => false, 'message' => $e->getMessage()], 400);
}
