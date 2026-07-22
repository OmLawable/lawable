<?php
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/firestore.php';
start_secure_session();

if (!is_logged_in()) {
    redirect('pages/login.php');
}

$user = current_user();
$is_org = ($user['role'] ?? '') === 'organization';
$is_teacher = ($user['role'] ?? '') === 'teacher';

$orgId = '';
$orgName = '';

$db = get_firestore();

if ($is_org) {
    $orgId = $user['id'];
    $orgName = $user['organization_name'] ?? $user['name'] ?? 'Organization';
} elseif ($is_teacher) {
    try {
        $teacherDoc = $db->get('teachers', $user['id']);
        $orgId = $teacherDoc['organizationId'] ?? '';
        if ($orgId === '') {
            redirect('pages/dashboard.php');
        }
        $orgDoc = $db->get('organizations', $orgId);
        $orgName = $orgDoc['organization_name'] ?? $orgDoc['name'] ?? 'Organization';
    } catch (Throwable $e) {
        redirect('pages/dashboard.php');
    }
} else {
    redirect('pages/dashboard.php');
}

$success = '';
$errors = [];

// Handle delete action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
    try {
        verify_csrf_token($_POST['csrf_token'] ?? '');
        $webinarId = trim((string) ($_POST['webinar_id'] ?? ''));
        if ($webinarId === '') {
            throw new RuntimeException('Webinar ID is required.');
        }

        // Fetch webinar and verify ownership
        $webinar = $db->get('webinars', $webinarId);
        if (!$webinar) {
            throw new RuntimeException('Webinar not found.');
        }

        if (($webinar['organizationId'] ?? '') !== $orgId) {
            throw new RuntimeException('Unauthorized to delete this webinar.');
        }

        $db->delete('webinars', $webinarId);
        $success = 'Webinar deleted successfully.';
    } catch (Throwable $e) {
        $errors[] = $e->getMessage();
    }
}

// Fetch webinars for this organization
$org_webinars = $db->query('webinars', [
    ['organizationId', 'EQUAL', $orgId]
], 200);

if (!empty($org_webinars)) {
    usort($org_webinars, function($a, $b) {
        return strcmp($b['dateTime'] ?? '', $a['dateTime'] ?? '');
    });
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Manage Webinars — Lawable</title>
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600;700;800&family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet" />
  <link rel="stylesheet" href="../../assets/css/lawable.css" />
  <style>
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
      font-size: 2rem;
      color: var(--ink);
    }
    .manage-table-card {
      background: var(--white);
      border: 1px solid var(--border);
      border-radius: var(--radius-lg);
      box-shadow: var(--shadow);
      overflow: hidden;
      margin-bottom: 2rem;
    }
    .manage-table-wrap {
      overflow-x: auto;
    }
    .manage-table {
      width: 100%;
      border-collapse: collapse;
      text-align: left;
      font-size: 0.9rem;
    }
    .manage-table th {
      background: var(--page-bg);
      color: var(--ink-soft);
      font-weight: 600;
      padding: 1rem 1.25rem;
      border-bottom: 1px solid var(--border);
      text-transform: uppercase;
      font-size: 0.75rem;
      letter-spacing: 0.05em;
    }
    .manage-table td {
      padding: 1rem 1.25rem;
      border-bottom: 1px solid var(--border);
      color: var(--ink-mid);
      vertical-align: middle;
    }
    .manage-table tr:last-child td {
      border-bottom: none;
    }
    .webinar-cell {
      display: flex;
      align-items: center;
      gap: 1rem;
    }
    .webinar-icon-mini {
      width: 48px;
      height: 48px;
      border-radius: 8px;
      background: var(--gold-lt);
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 1.5rem;
      flex-shrink: 0;
    }
    .webinar-title-text {
      font-weight: 600;
      color: var(--ink);
    }
    .status-badge {
      display: inline-flex;
      align-items: center;
      padding: 0.25rem 0.6rem;
      border-radius: 50px;
      font-size: 0.75rem;
      font-weight: 600;
      text-transform: uppercase;
    }
    .status-published {
      background: #DCFCE7;
      color: #15803D;
    }
    .status-draft {
      background: #F3F4F6;
      color: #4B5563;
    }
    .empty-webinars {
      text-align: center;
      padding: 4rem 2rem;
      color: var(--ink-soft);
    }
    .empty-icon {
      font-size: 3rem;
      margin-bottom: 1rem;
    }
    .btn-action-edit {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      padding: 0.45rem 1rem;
      font-size: 0.8rem;
      font-weight: 600;
      border-radius: 9999px;
      border: 1px solid var(--border);
      background: transparent;
      color: var(--ink-mid);
      text-decoration: none;
      transition: all 0.2s;
      cursor: pointer;
    }
    .btn-action-edit:hover {
      background: #FAF7F2;
      border-color: var(--gold);
      color: var(--gold-dk);
    }
    .btn-action-delete {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      padding: 0.45rem 1rem;
      font-size: 0.8rem;
      font-weight: 600;
      border-radius: 9999px;
      border: 1px solid #FCA5A5;
      background: transparent;
      color: #DC2626;
      text-decoration: none;
      transition: all 0.2s;
      cursor: pointer;
    }
    .btn-action-delete:hover {
      background: #FEE2E2;
      border-color: #DC2626;
    }
    .profile-alert {
      margin-bottom: 1.5rem;
      padding: 1rem;
      border-radius: var(--radius);
      font-size: 0.9rem;
    }
    .profile-alert-error {
      background: #FEE2E2;
      color: #DC2626;
      border: 1px solid #FCA5A5;
    }
    .profile-alert-success {
      background: #DCFCE7;
      color: #16A34A;
      border: 1px solid #86EFAC;
    }
    .meet-link {
      color: var(--gold);
      text-decoration: none;
      font-weight: 500;
    }
    .meet-link:hover {
      text-decoration: underline;
    }
  </style>
</head>
<body>

<nav id="navbar" class="scrolled">
  <a href="../dashboard.php" class="nav-logo">Law<span>able</span></a>
  <?php 
    $profileLink = 'edit-profile.php';
    $coursesLink = 'manage-courses.php';
    if ($is_teacher) {
        $profileLink = '../teacher/edit-profile.php';
        $coursesLink = 'enrolled-students.php';
    }
  ?>
  <ul class="nav-links">
    <li><a href="../dashboard.php">Dashboard</a></li>
    <li><a href="<?= $coursesLink ?>">Manage Courses</a></li>
    <li><a href="manage-webinars.php" class="active">Manage Webinars</a></li>
    <li><a href="<?= $profileLink ?>">Profile</a></li>
    <li><a href="../../api/logout.php" class="nav-cta">Log out</a></li>
  </ul>
  <button class="nav-hamburger" id="hamburger" aria-label="Menu">
    <span></span><span></span><span></span>
  </button>
</nav>

<nav class="nav-drawer" id="drawer">
  <a href="../dashboard.php">Dashboard</a>
  <a href="<?= $coursesLink ?>">Manage Courses</a>
  <a href="manage-webinars.php">Manage Webinars</a>
  <a href="<?= $profileLink ?>">Profile</a>
  <a href="../../api/logout.php" class="drawer-cta">Log out</a>
</nav>

<main class="profile-shell" style="max-width:1200px;margin-top: calc(var(--nav-h) + 2rem); padding: 0 1rem;">
  
  <?php foreach ($errors as $error): ?>
    <div class="profile-alert profile-alert-error"><?= e($error) ?></div>
  <?php endforeach; ?>

  <?php if ($success !== ''): ?>
    <div class="profile-alert profile-alert-success"><?= e($success) ?></div>
  <?php endif; ?>

  <div class="manage-header">
    <div class="manage-title-area">
      <a href="../dashboard.php" style="text-decoration:none;color:var(--gold);font-weight:500;font-size:0.88rem;">← Dashboard</a>
      <h1>Manage Webinars</h1>
    </div>
    <a href="create-webinar.php" class="btn-primary" style="text-decoration:none;">+ Schedule Webinar</a>
  </div>

  <div class="manage-table-card">
    <div class="manage-table-wrap">
      <?php if (empty($org_webinars)): ?>
        <div class="empty-webinars">
          <div class="empty-icon">🎙️</div>
          <h3>No webinars scheduled yet</h3>
          <p style="margin-top:0.5rem;margin-bottom:1.5rem;font-size:0.88rem;">Host interactive live webcasts using Google Meet to connect with your students.</p>
          <a href="create-webinar.php" class="btn-primary" style="text-decoration:none;display:inline-flex;">Schedule Your First Webinar</a>
        </div>
      <?php else: ?>
        <table class="manage-table">
          <thead>
            <tr>
              <th>Webinar Details</th>
              <th>Scheduled Time</th>
              <th>Google Meet Link</th>
              <th>Status</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($org_webinars as $w): ?>
              <tr>
                <td>
                  <div class="webinar-cell">
                    <div class="webinar-icon-mini">📹</div>
                    <div>
                      <div class="webinar-title-text"><?= e($w['title']) ?></div>
                      <div style="font-size:0.75rem;color:var(--ink-soft);margin-top:0.15rem;"><?= e(substr($w['description'], 0, 80)) ?><?= strlen($w['description']) > 80 ? '...' : '' ?></div>
                    </div>
                  </div>
                </td>
                <td>
                  <strong><?= date('d-m-Y • h:i A', strtotime($w['dateTime'])) ?></strong>
                </td>
                <td>
                  <a href="<?= e($w['meetLink']) ?>" target="_blank" rel="noopener noreferrer" class="meet-link">
                    <?= e($w['meetLink']) ?>
                  </a>
                </td>
                <td>
                  <span class="status-badge status-<?= $w['status'] ?>">
                    <?= e($w['status']) ?>
                  </span>
                </td>
                <td>
                  <div style="display:flex; gap:0.5rem; align-items: center;">
                    <a href="edit-webinar.php?id=<?= urlencode($w['__id']) ?>" class="btn-action-edit">Edit</a>
                    <form method="POST" action="manage-webinars.php" onsubmit="return confirm('Are you sure you want to delete this webinar?');" style="margin:0;">
                      <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>" />
                      <input type="hidden" name="action" value="delete" />
                      <input type="hidden" name="webinar_id" value="<?= e($w['__id']) ?>" />
                      <button type="submit" class="btn-action-delete">Delete</button>
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
</main>

<script src="../../assets/js/script.js"></script>
</body>
</html>
