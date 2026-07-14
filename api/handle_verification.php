<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/firestore.php';
start_secure_session();

$user = require_login('admin');

$redirectTo = 'pages/admin/dashboard.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    set_flash('error', 'Invalid request method.');
    redirect($redirectTo);
}

$docId = trim((string) ($_POST['doc_id'] ?? ''));
$action = (string) ($_POST['action'] ?? '');

if ($docId === '' || !in_array($action, ['approve', 'reject'], true)) {
    set_flash('error', 'Invalid parameters.');
    redirect($redirectTo);
}

try {
    $db = get_firestore();

    // Fetch verification request from Firestore (using org UID string as doc ID)
    $doc = $db->get('verificationRequests', $docId);

    if (!$doc) {
        set_flash('error', 'Verification document not found.');
        redirect($redirectTo);
    }

    $orgId = $doc['organizationId'] ?? $docId;
    $newStatus = $action === 'approve' ? 'verified' : 'rejected';

    // Update the verification document status in Firestore
    $db->update('verificationRequests', $docId, [
        'status'     => $newStatus,
        'reviewedBy' => (string) $user['id'],
        'reviewedAt' => FirestoreClient::now()
    ]);

    // Fetch org details to show name in logs/flashes and activate if approved
    $org = $db->get('organizations', $orgId);
    $orgName = $org ? ($org['organizationName'] ?? 'Organization') : 'Organization';

    // When approved: set organization status to active so it can log in
    if ($action === 'approve') {
        $db->update('organizations', $orgId, [
            'status'    => 'active',
            'updatedAt' => FirestoreClient::now()
        ]);
    }

    // Log the activity
    $actionLabel = $action === 'approve' ? 'approved' : 'rejected';
    
    $logDoc = [
        'action'      => 'verification_' . $actionLabel,
        'description' => "Admin {$user['name']} {$actionLabel} verification for \"{$orgName}\" (#{$docId}).",
        'userType'    => 'admin',
        'userId'      => (string) $user['id'],
        'priority'    => 'low',
        'createdAt'   => FirestoreClient::now()
    ];
    $db->set('activityLogs', $logDoc);

    set_flash('success', "Organization \"{$orgName}\" has been {$actionLabel} and can now log in.");
} catch (Exception $e) {
    set_flash('error', 'An error occurred while processing your request: ' . $e->getMessage());
}

redirect($redirectTo);
