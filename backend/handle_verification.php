<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/functions.php';
start_secure_session();

$user = require_login('admin');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    set_flash('error', 'Invalid request method.');
    redirect('../pages/admin-dashboard.php');
}

$docId = (int) ($_POST['doc_id'] ?? 0);
$action = (string) ($_POST['action'] ?? '');

if ($docId < 1 || !in_array($action, ['approve', 'reject'], true)) {
    set_flash('error', 'Invalid parameters.');
    redirect('../pages/admin-dashboard.php');
}

try {
    $pdo = get_pdo();

    $stmt = $pdo->prepare("SELECT id, status FROM verification_documents WHERE id = :id LIMIT 1");
    $stmt->execute([':id' => $docId]);
    $doc = $stmt->fetch();

    if (!$doc) {
        set_flash('error', 'Verification document not found.');
        redirect('../pages/admin-dashboard.php');
    }

    $newStatus = $action === 'approve' ? 'verified' : 'rejected';

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

    // Log the activity
    $actionLabel = $action === 'approve' ? 'approved' : 'rejected';
    $stmt = $pdo->prepare("
        INSERT INTO activity_logs (action, description, user_type, user_id, priority)
        VALUES (:action, :desc, 'admin', :uid, 'low')
    ");
    $stmt->execute([
        ':action' => 'verification_' . $actionLabel,
        ':desc' => "Admin {$user['name']} {$actionLabel} verification document #{$docId}.",
        ':uid' => (int) $user['id'],
    ]);

    set_flash('success', "Verification document #{$docId} has been {$actionLabel}.");
} catch (Exception $e) {
    set_flash('error', 'An error occurred while processing your request.');
}

redirect('../pages/admin-dashboard.php');
</write_to_file>
