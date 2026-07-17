<?php

declare(strict_types=1);

require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/firestore.php';

start_secure_session();
if (!is_logged_in()) {
    redirect('pages/login.php');
}

$user = current_user();
$is_org = ($user['role'] ?? '') === 'organization';

if (!$is_org) {
    redirect('pages/dashboard.php');
}

$db = get_firestore();
$errors = [];
$success = '';

// Handle POST approval/rejection actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        verify_csrf_token($_POST['csrf_token'] ?? '');
        $action = trim((string) ($_POST['action'] ?? ''));
        $teacherId = trim((string) ($_POST['teacher_id'] ?? ''));

        if ($teacherId === '') {
            throw new RuntimeException('Teacher ID is required.');
        }

        // Verify that this teacher actually belongs to this organization
        $teacher = $db->get('teachers', $teacherId);
        if (!$teacher || ($teacher['organizationId'] ?? '') !== (string) $user['id']) {
            throw new RuntimeException('Teacher not found under your organization.');
        }

        if ($action === 'approve') {
            $db->update('teachers', $teacherId, [
                'status' => 'active',
                'updatedAt' => date('c')
            ]);
            $success = "Teacher '{$teacher['name']}' approved successfully!";
        } elseif ($action === 'reject') {
            // Delete teacher document
            $db->delete('teachers', $teacherId);
            $success = "Teacher registration rejected and account deleted.";
        } elseif ($action === 'revoke') {
            $db->update('teachers', $teacherId, [
                'status' => 'pending',
                'updatedAt' => date('c')
            ]);
            $success = "Teacher access revoked successfully.";
        }
    } catch (Throwable $e) {
        $errors[] = $e->getMessage();
    }
}

// Load teachers lists
$pending_teachers = [];
$active_teachers = [];

try {
    $all_teachers = $db->query('teachers', [
        ['organizationId', 'EQUAL', $user['id']]
    ], 100);

    foreach ($all_teachers as $t) {
        if (($t['status'] ?? '') === 'active') {
            $active_teachers[] = $t;
        } else {
            $pending_teachers[] = $t;
        }
    }
} catch (Throwable $e) {
    $errors[] = 'Failed to load teaching staff list.';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Manage Teachers — Lawable</title>
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600;700;800&family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet" />
  <link rel="stylesheet" href="../../assets/css/lawable.css" />
  <style>
    :root {
      --gold: #C9933A;
      --gold-dk: #A8732A;
      --gold-lt: #F4E4C3;
      --cream: #FCF8F1;
      --page-bg: #FCF8F1;
      --white: #FFFFFF;
      --ink: #0D1117;
      --ink-mid: #374151;
      --ink-soft: #6B7280;
      --border: #E5E0D8;
    }
    body.dark-theme {
      --gold: #D8A84F;
      --gold-dk: #F0C56D;
      --gold-lt: #3A3022;
      --cream: #111827;
      --page-bg: #0F172A;
      --white: #1E293B;
      --ink: #F8FAFC;
      --ink-mid: #CBD5E1;
      --ink-soft: #94A3B8;
      --border: #334155;
    }

    body {
      background: var(--page-bg) !important;
      color: var(--ink);
      font-family: 'Inter', sans-serif;
    }

    .manage-header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 2rem;
      flex-wrap: wrap;
      gap: 1rem;
    }
    .manage-title-area h1 {
      font-family: 'Playfair Display', serif;
      font-size: 2.2rem;
      font-weight: 700;
      color: var(--ink);
    }
    .manage-table-card {
      background: var(--white);
      border: 1px solid var(--border);
      border-radius: 24px;
      box-shadow: 0 4px 24px rgba(13,17,23,0.04);
      overflow: hidden;
      margin-bottom: 2.5rem;
    }
    .manage-table-wrap {
      overflow-x: auto;
    }
    .manage-table {
      width: 100%;
      border-collapse: collapse;
      text-align: left;
      font-size: 0.92rem;
    }
    .manage-table th {
      background: rgba(168,115,42,0.03);
      color: var(--ink-soft);
      font-weight: 700;
      text-transform: uppercase;
      font-size: 0.78rem;
      letter-spacing: 0.05em;
      padding: 1.1rem 1.5rem;
      border-bottom: 1px solid var(--border);
    }
    .manage-table td {
      padding: 1.25rem 1.5rem;
      border-bottom: 1px solid var(--border);
      color: var(--ink-mid);
    }
    .manage-table tr:last-child td {
      border-bottom: none;
    }

    .teacher-cell {
      display: flex;
      align-items: center;
      gap: 0.85rem;
    }
    .teacher-avatar {
      width: 44px;
      height: 44px;
      border-radius: 50%;
      background: var(--gold-lt);
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 1.2rem;
      border: 1px solid var(--border);
    }

    .alert {
      padding: 1rem 1.5rem;
      border-radius: 12px;
      margin-bottom: 1.5rem;
      font-size: 0.9rem;
    }
    .alert-error {
      background: #FEE2E2;
      color: #991B1B;
      border: 1px solid #FCA5A5;
    }
    .alert-success {
      background: #DCFCE7;
      color: #166534;
      border: 1px solid #86EFAC;
    }

    .btn-pill {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      padding: 0.55rem 1.25rem;
      font-size: 0.82rem;
      font-weight: 600;
      border-radius: 9999px;
      border: 1px solid transparent;
      cursor: pointer;
      transition: all 0.2s;
      text-decoration: none;
    }
    .btn-pill-primary {
      background: var(--gold-dk);
      color: white;
    }
    .btn-pill-primary:hover {
      background: var(--gold);
      transform: translateY(-1px);
    }
    .btn-pill-outline {
      background: transparent;
      border-color: var(--border);
      color: var(--ink-mid);
    }
    .btn-pill-outline:hover {
      background: var(--gold-lt);
      border-color: var(--gold);
      color: var(--gold-dk);
    }
    .btn-pill-danger {
      background: #FEE2E2;
      color: #B91C1C;
      border-color: #FCA5A5;
    }
    .btn-pill-danger:hover {
      background: #FCA5A5;
      color: #991B1B;
    }
  </style>
</head>
<body class="profile-page">
<div class="cursor-glow" id="cursorGlow"></div>
<div class="progress-bar" id="progressBar"></div>

<nav id="navbar">
  <a href="../dashboard.php" class="nav-logo">Law<span>able</span></a>
  <ul class="nav-links">
    <li><a href="../dashboard.php">Dashboard</a></li>
    <li><a href="manage-courses.php">Manage Courses</a></li>
    <li><a href="enrolled-students.php">Student Directory</a></li>
    <li><a href="edit-profile.php">Profile</a></li>
    <li><a href="../../api/logout.php" class="nav-cta">Log out</a></li>
  </ul>
</nav>

<main class="profile-shell" style="max-width: 1100px; margin-top: calc(var(--nav-h) + 2.5rem);">
  <div class="manage-header">
    <div class="manage-title-area">
      <a href="../dashboard.php" style="text-decoration:none; color:var(--gold); font-weight:600; font-size:0.85rem; display:block; margin-bottom:0.35rem;">← Dashboard</a>
      <h1>Manage Instructors</h1>
      <p style="color:var(--ink-soft); font-size:0.95rem; margin-top:0.25rem;">
        Organization: <strong><?= e($user['organization_name'] ?? $user['name'] ?? 'Unknown') ?></strong>
      </p>
      <?php if (is_numeric($user['id'] ?? null)): ?>
        <div style="background:#FFFBEB; border:1px solid #FCD34D; color:#92400E; padding:0.75rem 1rem; border-radius:8px; font-size:0.85rem; margin-top:0.75rem; max-width: 600px;">
          ⚠️ <strong>Stale Session Detected:</strong> Your session has an old numeric ID. Please <strong>Log out</strong> and log back in so your account is verified with the new database string ID.
        </div>
      <?php endif; ?>
    </div>
  </div>

  <?php foreach ($errors as $err): ?>
    <div class="alert alert-error">✕ <?= e($err) ?></div>
  <?php endforeach; ?>

  <?php if ($success !== ''): ?>
    <div class="alert alert-success">✓ <?= e($success) ?></div>
  <?php endif; ?>

  <!-- Section 1: Pending Approvals -->
  <h2 style="font-family:'Playfair Display', serif; font-size:1.4rem; margin-bottom:1rem; color:var(--ink); display:flex; align-items:center; gap:0.5rem;">
    ⏳ Pending Approvals 
    <span style="font-size:0.8rem; background:var(--gold-lt); color:var(--gold-dk); padding:0.15rem 0.5rem; border-radius:50px; font-weight:700;"><?= count($pending_teachers) ?></span>
  </h2>
  
  <div class="manage-table-card">
    <div class="manage-table-wrap">
      <?php if (empty($pending_teachers)): ?>
        <div style="text-align:center; padding:3rem; color:var(--ink-soft);">
          <div style="font-size:2.5rem; margin-bottom:0.75rem;">📋</div>
          <h3 style="font-size:1rem; font-weight:600;">No pending verification requests</h3>
          <p style="font-size:0.8rem; margin-top:0.15rem;">When new teachers sign up under your organization, they will show up here for approval.</p>
        </div>
      <?php else: ?>
        <table class="manage-table">
          <thead>
            <tr>
              <th>Teacher Details</th>
              <th>Username</th>
              <th>Phone Number</th>
              <th>Submitted</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($pending_teachers as $t): ?>
              <tr>
                <td>
                  <div class="teacher-cell">
                    <div class="teacher-avatar">👨‍🏫</div>
                    <div>
                      <strong style="color:var(--ink);"><?= e($t['name']) ?></strong>
                      <div style="font-size:0.78rem; color:var(--ink-soft);"><?= e($t['email']) ?></div>
                    </div>
                  </div>
                </td>
                <td><code style="background:var(--page-bg); padding:0.2rem 0.4rem; border-radius:4px; font-size:0.8rem;"><?= e($t['username']) ?></code></td>
                <td><?= e($t['phone'] ?: '—') ?></td>
                <td><span style="font-size:0.8rem; color:var(--ink-soft);"><?= date('M j, Y', strtotime($t['createdAt'] ?? 'now')) ?></span></td>
                <td>
                  <div style="display:flex; gap:0.5rem;">
                    <form method="POST" action="manage-teachers.php" style="margin:0;">
                      <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>" />
                      <input type="hidden" name="action" value="approve" />
                      <input type="hidden" name="teacher_id" value="<?= e($t['__id']) ?>" />
                      <button type="submit" class="btn-pill btn-pill-primary">✓ Approve</button>
                    </form>
                    <form method="POST" action="manage-teachers.php" style="margin:0;" onsubmit="return confirm('Are you sure you want to reject this registration request?');">
                      <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>" />
                      <input type="hidden" name="action" value="reject" />
                      <input type="hidden" name="teacher_id" value="<?= e($t['__id']) ?>" />
                      <button type="submit" class="btn-pill btn-pill-danger">✕ Reject</button>
                    </form>
                  </div>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      <?php endif; ?>
    </div>
  </div>

  <!-- Section 2: Active Teaching Staff -->
  <h2 style="font-family:'Playfair Display', serif; font-size:1.4rem; margin-bottom:1rem; color:var(--ink); display:flex; align-items:center; gap:0.5rem;">
    ✅ Active Instructors
    <span style="font-size:0.8rem; background:#DCFCE7; color:#15803D; padding:0.15rem 0.5rem; border-radius:50px; font-weight:700;"><?= count($active_teachers) ?></span>
  </h2>

  <div class="manage-table-card">
    <div class="manage-table-wrap">
      <?php if (empty($active_teachers)): ?>
        <div style="text-align:center; padding:3rem; color:var(--ink-soft);">
          <div style="font-size:2.5rem; margin-bottom:0.75rem;">🏫</div>
          <h3 style="font-size:1rem; font-weight:600;">No active instructors yet</h3>
          <p style="font-size:0.8rem; margin-top:0.15rem;">Approve pending accounts above to add them to your official teaching staff list.</p>
        </div>
      <?php else: ?>
        <table class="manage-table">
          <thead>
            <tr>
              <th>Instructor Details</th>
              <th>Username</th>
              <th>Phone Number</th>
              <th>Status</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($active_teachers as $t): ?>
              <tr>
                <td>
                  <div class="teacher-cell">
                    <div class="teacher-avatar">🎓</div>
                    <div>
                      <strong style="color:var(--ink);"><?= e($t['name']) ?></strong>
                      <div style="font-size:0.78rem; color:var(--ink-soft);"><?= e($t['email']) ?></div>
                    </div>
                  </div>
                </td>
                <td><code style="background:var(--page-bg); padding:0.2rem 0.4rem; border-radius:4px; font-size:0.8rem;"><?= e($t['username']) ?></code></td>
                <td><?= e($t['phone'] ?: '—') ?></td>
                <td><span style="font-size:0.8rem; font-weight:700; color:#15803D; background:#DCFCE7; padding:0.15rem 0.5rem; border-radius:50px;">Verified</span></td>
                <td>
                  <form method="POST" action="manage-teachers.php" style="margin:0;" onsubmit="return confirm('Are you sure you want to revoke access for this instructor?');">
                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>" />
                    <input type="hidden" name="action" value="revoke" />
                    <input type="hidden" name="teacher_id" value="<?= e($t['__id']) ?>" />
                    <button type="submit" class="btn-pill btn-pill-outline" style="color:#DC2626; border-color:#FCA5A5;">Revoke Access</button>
                  </form>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      <?php endif; ?>
    </div>
  </div>
</main>

<script src="../../assets/js/script.js"></script>
</body>
</html>
