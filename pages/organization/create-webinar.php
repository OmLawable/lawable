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

$errors = [];
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        verify_csrf_token($_POST['csrf_token'] ?? '');

        $title       = trim((string) ($_POST['title'] ?? ''));
        $description = trim((string) ($_POST['description'] ?? ''));
        $date        = trim((string) ($_POST['date'] ?? ''));
        $hour        = (int) ($_POST['hour'] ?? 12);
        $minute      = trim((string) ($_POST['minute'] ?? '00'));
        $period      = trim((string) ($_POST['period'] ?? 'PM'));
        $meetLink    = trim((string) ($_POST['meetLink'] ?? ''));
        $status      = trim((string) ($_POST['status'] ?? 'draft'));

        if ($title === '') {
            throw new RuntimeException('Webinar title is required.');
        }
        if ($description === '') {
            throw new RuntimeException('Webinar description is required.');
        }
        if ($date === '') {
            throw new RuntimeException('Webinar date is required.');
        }

        // Combine into standard datetime-local format
        if ($period === 'PM' && $hour < 12) {
            $hour += 12;
        } elseif ($period === 'AM' && $hour === 12) {
            $hour = 0;
        }
        $hourStr = str_pad((string) $hour, 2, '0', STR_PAD_LEFT);
        $minuteStr = str_pad($minute, 2, '0', STR_PAD_LEFT);
        $dateTime = $date . 'T' . $hourStr . ':' . $minuteStr;
        if ($meetLink === '') {
            throw new RuntimeException('Google Meet link is required.');
        }
        if (!filter_var($meetLink, FILTER_VALIDATE_URL)) {
            throw new RuntimeException('Please enter a valid Google Meet URL.');
        }
        if (!str_contains(strtolower($meetLink), 'meet.google.com')) {
            throw new RuntimeException('The link must be a valid Google Meet link (containing meet.google.com).');
        }
        if (!in_array($status, ['draft', 'published'], true)) {
            throw new RuntimeException('Invalid status.');
        }

        // Write to Firestore
        $db = get_firestore();
        $webinarId = 'webinar_' . bin2hex(random_bytes(6));
        $now = date('c');

        $webinarData = [
            'title'            => $title,
            'description'      => $description,
            'dateTime'         => $dateTime,
            'meetLink'         => $meetLink,
            'status'           => $status,
            'organizationId'   => $orgId,
            'organizationName' => $orgName,
            'createdAt'        => $now,
            'updatedAt'        => $now
        ];

        $db->set('webinars', $webinarData, $webinarId);
        
        // Send notifications to affiliated teachers if published
        if ($status === 'published') {
            try {
                $teachers = $db->query('teachers', [['organizationId', 'EQUAL', $orgId]], 100);
                if (!empty($teachers)) {
                    foreach ($teachers as $t) {
                        // Skip the teacher who is scheduling the webinar
                        if ($t['__id'] === $user['id']) {
                            continue;
                        }
                        $msgId = 'msg_' . bin2hex(random_bytes(6));
                        $msgDoc = [
                            'senderId'    => $orgId,
                            'senderName'  => $orgName,
                            'receiverId'  => $t['__id'],
                            'courseId'    => '',
                            'courseTitle' => 'Webinar Notification',
                            'messageText' => "A new webinar has been scheduled by our organization:\n\nTitle: " . $title . "\nDate & Time: " . date('d-m-Y • h:i A', strtotime($dateTime)) . "\nMeet Link: " . $meetLink . "\n\nDescription:\n" . $description,
                            'isRead'      => false,
                            'createdAt'   => date('c')
                        ];
                        $db->set('messages', $msgDoc, $msgId);
                    }
                }
            } catch (Throwable $notifErr) {
                // Fail silently for notification errors
            }
        }
        
        set_flash('success', 'Webinar scheduled successfully!');
        redirect('pages/organization/manage-webinars.php');
    } catch (Throwable $e) {
        $errors[] = $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Schedule Webinar — Lawable</title>
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600;700;800&family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet" />
  <link rel="stylesheet" href="../../assets/css/lawable.css" />
  <style>
    body.profile-page { background: var(--cream) !important; }
    .form-btn-row { display: flex; justify-content: flex-end; gap: 1rem; grid-column: 1 / -1; margin-top: 1.5rem; }
    .btn-secondary { background: transparent; border: 1px solid var(--border); color: var(--ink); padding: 0.75rem 1.5rem; border-radius: var(--radius); font-size: 0.9rem; font-weight: 500; cursor: pointer; transition: background 0.2s, border-color 0.2s; text-decoration: none; display: inline-flex; align-items: center; justify-content: center; }
    .btn-secondary:hover { background: var(--page-bg); border-color: var(--ink-soft); }
    .profile-alert { margin-bottom: 1.5rem; padding: 1rem; border-radius: var(--radius); font-size: 0.9rem; }
    .profile-alert-error { background: #FEE2E2; color: #DC2626; border: 1px solid #FCA5A5; }
    .profile-alert-success { background: #DCFCE7; color: #16A34A; border: 1px solid #86EFAC; }
  </style>
</head>
<body class="profile-page">

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
    <li><a href="manage-webinars.php">Manage Webinars</a></li>
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

<main class="profile-shell" style="margin-top: calc(var(--nav-h) + 2rem); padding: 0 1rem;">
  <section class="profile-form-wrap">
    
    <div style="width:100%;">
      <?php foreach ($errors as $error): ?>
        <div class="profile-alert profile-alert-error"><?= e($error) ?></div>
      <?php endforeach; ?>

      <?php if ($success !== ''): ?>
        <div class="profile-alert profile-alert-success"><?= e($success) ?></div>
      <?php endif; ?>

      <form class="profile-form" method="POST" action="create-webinar.php">
        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>" />

        <div class="profile-card">
          <div class="profile-card-header">
            <span class="profile-card-icon" aria-hidden="true">🎙️</span>
            <div>
              <h1>Schedule New Webinar</h1>
              <p style="color:var(--ink-soft);font-size:0.88rem;margin-top:0.2rem;">Setup a Google Meet session for students under <?= e($orgName) ?></p>
            </div>
          </div>

          <div class="profile-card-body">
            <h2 class="profile-section-title">Webinar Details</h2>

            <div class="profile-form-grid">
              <div class="profile-field profile-field-full">
                <label for="title">Webinar Title</label>
                <input id="title" name="title" type="text" maxlength="255" required placeholder="e.g., Understanding the Cybersecurity Act of 2026" value="<?= e($_POST['title'] ?? '') ?>" />
              </div>

              <div class="profile-field">
                <label for="date">Webinar Date</label>
                <input id="date" name="date" type="date" required value="<?= e($_POST['date'] ?? date('Y-m-d')) ?>" />
              </div>

              <div class="profile-field">
                <label>Scheduled Time (IST)</label>
                <div style="display: flex; gap: 0.5rem; align-items: center;">
                  <select name="hour" id="hour" required style="flex: 1;">
                    <?php 
                      $selHour = (int)($_POST['hour'] ?? 12);
                      for ($h = 1; $h <= 12; $h++): 
                    ?>
                      <option value="<?= $h ?>" <?= $selHour === $h ? 'selected' : '' ?>><?= str_pad((string)$h, 2, '0', STR_PAD_LEFT) ?></option>
                    <?php endfor; ?>
                  </select>
                  <span>:</span>
                  <select name="minute" id="minute" required style="flex: 1;">
                    <?php 
                      $selMinute = $_POST['minute'] ?? '00';
                      for ($m = 0; $m < 60; $m += 5): 
                        $mStr = str_pad((string)$m, 2, '0', STR_PAD_LEFT);
                    ?>
                      <option value="<?= $mStr ?>" <?= $selMinute === $mStr ? 'selected' : '' ?>><?= $mStr ?></option>
                    <?php endfor; ?>
                  </select>
                  <select name="period" id="period" required style="flex: 1;">
                    <?php $selPeriod = $_POST['period'] ?? 'PM'; ?>
                    <option value="AM" <?= $selPeriod === 'AM' ? 'selected' : '' ?>>AM</option>
                    <option value="PM" <?= $selPeriod === 'PM' ? 'selected' : '' ?>>PM</option>
                  </select>
                </div>
              </div>

              <div class="profile-field">
                <label for="status">Publication Status</label>
                <select id="status" name="status" required>
                  <option value="draft" <?= ($_POST['status'] ?? '') === 'draft' ? 'selected' : '' ?>>Draft (Private)</option>
                  <option value="published" <?= ($_POST['status'] ?? '') === 'published' ? 'selected' : '' ?>>Published (Public)</option>
                </select>
              </div>

              <div class="profile-field profile-field-full">
                <label for="meetLink">Google Meet Link</label>
                <input id="meetLink" name="meetLink" type="url" required placeholder="e.g., https://meet.google.com/abc-defg-hij" value="<?= e($_POST['meetLink'] ?? '') ?>" />
                <p style="color:var(--ink-soft);font-size:0.75rem;margin-top:0.25rem;">Must be a valid URL starting with meet.google.com</p>
              </div>

              <div class="profile-field profile-field-full">
                <label for="description">Webinar Description</label>
                <textarea id="description" name="description" rows="5" placeholder="Provide a brief summary of the webinar topic, what will be discussed, and who should attend..." required><?= e($_POST['description'] ?? '') ?></textarea>
              </div>

              <div class="form-btn-row">
                <a href="manage-webinars.php" class="btn-secondary">Cancel</a>
                <button class="btn-primary" type="submit">Schedule Webinar</button>
              </div>
            </div>
          </div>
        </div>
      </form>
    </div>
  </section>
</main>

<script src="../../assets/js/script.js"></script>
</body>
</html>
