<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/functions.php';
start_secure_session();

$user = require_login('admin');

$redirectTo = 'pages/admin-dashboard.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    set_flash('error', 'Invalid request method.');
    redirect($redirectTo);
}

$docId = (int) ($_POST['doc_id'] ?? 0);
$action = (string) ($_POST['action'] ?? '');

if ($docId < 1 || !in_array($action, ['approve', 'reject'], true)) {
    set_flash('error', 'Invalid parameters.');
    redirect($redirectTo);
}

try {
    $pdo = get_pdo();

    // Fetch verification document WITH its organization
    $stmt = $pdo->prepare("
        SELECT v.id, v.status, v.organization_id
        FROM verification_documents v
        WHERE v.id = :id
        LIMIT 1
    ");
    $stmt->execute([':id' => $docId]);
    $doc = $stmt->fetch();

    if (!$doc) {
        set_flash('error', 'Verification document not found.');
        redirect($redirectTo);
    }

    $orgId = (int) $doc['organization_id'];
    $newStatus = $action === 'approve' ? 'verified' : 'rejected';

    // Update the verification document status
    $stmt = $pdo->prepare("
        UPDATE verification_documents
        SET status = :status, reviewed_by = :reviewer, reviewed_at = NOW()
        WHERE id = :id
    ");
    $stmt->execute([
        ':status' => $newStatus,
        ':reviewer' => (int) $user['id'],
        ':id' => $docId,
    ]);

    // When approved: set organization status to active so it can log in
    // When rejected: keep as inactive
    if ($action === 'approve') {
        $stmt = $pdo->prepare("UPDATE organizations SET status = 'active' WHERE id = :oid AND status != 'active'");
        $stmt->execute([':oid' => $orgId]);
    }

    // Log the activity
    $actionLabel = $action === 'approve' ? 'approved' : 'rejected';
    $stmt = $pdo->prepare("SELECT organization_name FROM organizations WHERE id = :oid");
    $stmt->execute([':oid' => $orgId]);
    $orgRow = $stmt->fetch();

    $stmt = $pdo->prepare("
        INSERT INTO activity_logs (action, description, user_type, user_id, priority)
        VALUES (:action, :desc, 'admin', :uid, 'low')
    ");
    $stmt->execute([
        ':action' => 'verification_' . $actionLabel,
        ':desc' => "Admin {$user['name']} {$actionLabel} verification for \"{$orgRow['organization_name']}\" (#{$docId}).",
        ':uid' => (int) $user['id'],
    ]);

    set_flash('success', "Organization \"{$orgRow['organization_name']}\" has been {$actionLabel} and can now log in.");
} catch (Exception $e) {
    set_flash('error', 'An error occurred while processing your request.');
}

redirect($redirectTo);
