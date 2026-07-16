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
$is_teacher = ($user['role'] ?? '') === 'teacher';

if (!$is_org && !$is_teacher) {
    redirect('pages/dashboard.php');
}

$db = get_firestore();
$errors = [];
$success = '';

// Handle Message Posting
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_message'])) {
    try {
        verify_csrf_token($_POST['csrf_token'] ?? '');
        $studentId   = trim((string) ($_POST['student_id'] ?? ''));
        $courseId    = trim((string) ($_POST['course_id'] ?? ''));
        $courseTitle = trim((string) ($_POST['course_title'] ?? ''));
        $messageText = trim((string) ($_POST['message_text'] ?? ''));

        if ($studentId === '' || $courseId === '' || $messageText === '') {
            throw new RuntimeException('Message content is required.');
        }

        $msgId = 'msg_' . bin2hex(random_bytes(6));
        $msgDoc = [
            'senderId'    => (string) $user['id'],
            'senderName'  => $is_teacher ? ($user['name'] ?? 'Instructor') : ($user['organization_name'] ?? $user['name'] ?? 'Organization'),
            'receiverId'  => $studentId,
            'courseId'    => $courseId,
            'courseTitle' => $courseTitle,
            'messageText' => $messageText,
            'isRead'      => false,
            'createdAt'   => date('c')
        ];

        $db->set('messages', $msgDoc, $msgId);
        $success = 'Message sent to student successfully!';
    } catch (Throwable $e) {
        $errors[] = $e->getMessage();
    }
}

// Handle Message Deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_message'])) {
    try {
        verify_csrf_token($_POST['csrf_token'] ?? '');
        $messageId = trim((string) ($_POST['message_id'] ?? ''));
        if ($messageId === '') {
            throw new RuntimeException('Message ID is required.');
        }

        // Verify message ownership before deleting
        $msgDoc = $db->get('messages', $messageId);
        if ($msgDoc && ($msgDoc['senderId'] ?? '') === (string) $user['id']) {
            $db->delete('messages', $messageId);
            $success = 'Message deleted successfully!';
        } else {
            throw new RuntimeException('You do not have permission to delete this message.');
        }
    } catch (Throwable $e) {
        $errors[] = $e->getMessage();
    }
}

// Fetch teacher's courses
$filter_field = $is_teacher ? 'teacherId' : 'organizationId';
$org_courses = $db->query('courses', [
    [$filter_field, 'EQUAL', $user['id']]
], 100);

$teacher_course_ids = array_column($org_courses, '__id');
$enrolled_list = [];
$total_unique_students = [];

if (!empty($teacher_course_ids)) {
    $all_enrollments = $db->query('enrollments', [], 1000);
    foreach ($all_enrollments as $e) {
        if (in_array($e['courseId'] ?? '', $teacher_course_ids, true)) {
            $studentDoc = $db->get('students', $e['studentId']);
            if (!$studentDoc) continue;
            
            $courseDoc = $db->get('courses', $e['courseId']);
            if (!$courseDoc) continue;

            $progressId = $e['studentId'] . '_' . $e['courseId'];
            $progressDoc = $db->get('progress', $progressId);

            $progressPercentage = (float) ($progressDoc['progressPercentage'] ?? 0.0);
            $total_unique_students[$e['studentId']] = true;

            $enrolled_list[] = [
                'student_id'    => $e['studentId'],
                'student_name'  => $studentDoc['name'] ?? 'Unknown Student',
                'student_email' => $studentDoc['email'] ?? '',
                'course_id'     => $e['courseId'],
                'course_title'  => $courseDoc['title'] ?? '',
                'progress'      => $progressPercentage,
                'completed'     => (int) ($progressDoc['completedLessons'] ?? 0),
                'total'         => (int) ($progressDoc['totalLessons'] ?? 0)
            ];
        }
    }
}

// Fetch sent messages history by this teacher
$sent_messages = [];
$all_messages = $db->query('messages', [['senderId', 'EQUAL', (string) $user['id']]], 100);
if (!empty($all_messages)) {
    usort($all_messages, function($a, $b) {
        return strcmp($b['createdAt'] ?? '', $a['createdAt'] ?? '');
    });
    $sent_messages = array_slice($all_messages, 0, 10);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Student Directory — Lawable</title>
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
      margin-bottom: 2.5rem;
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
    .progress-bar-wrap {
      display: flex;
      align-items: center;
      gap: 0.50rem;
    }
    .progress-track {
      width: 100px;
      height: 6px;
      background: #E5E7EB;
      border-radius: 4px;
      overflow: hidden;
    }
    .progress-fill {
      height: 100%;
      background: #16A34A;
    }
    .btn-action-message {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      padding: 0.45rem 1rem;
      font-size: 0.8rem;
      font-weight: 600;
      border-radius: 9999px;
      border: 1px solid var(--border);
      background: transparent;
      color: var(--gold-dark);
      text-decoration: none;
      transition: all 0.2s;
      cursor: pointer;
    }
    .btn-action-message:hover {
      background: #FAF7F2;
      border-color: var(--gold);
    }
    .message-drawer {
      background: #FAF7F2;
      border-bottom: 1px solid var(--border);
      padding: 1.5rem 2rem;
      display: none;
    }
    .message-drawer.active {
      display: table-row;
    }
    .alert {
      padding: 1rem;
      border-radius: 8px;
      margin-bottom: 1.5rem;
      font-size: 0.88rem;
    }
    .alert-error {
      background: #FEE2E2;
      color: #991B1B;
    }
    .alert-success {
      background: #DCFCE7;
      color: #166534;
    }
    .btn-pill { display: inline-flex; align-items: center; justify-content: center; padding: 0.75rem 1.75rem; font-size: 0.9rem; font-weight: 600; border-radius: 9999px; border: 1px solid transparent; cursor: pointer; transition: all 0.2s ease-in-out; text-decoration: none; min-width: 110px; }
    .btn-pill-primary { background: #A8732A; color: white; }
    .btn-pill-primary:hover { background: #8E5E1E; transform: translateY(-1px); }
    .btn-pill-outline { background: transparent; border-color: #E5E0D8; color: #4B5563; }
    .btn-pill-outline:hover { background: #F9F8F6; border-color: #C9933A; color: #A8732A; }
  </style>
</head>
<body class="profile-page">
<div class="cursor-glow" id="cursorGlow"></div>
<div class="progress-bar" id="progressBar"></div>

<nav id="navbar" class="scrolled">
  <a href="../dashboard.php" class="nav-logo">Law<span>able</span></a>
  <ul class="nav-links">
    <li><a href="../dashboard.php">Dashboard</a></li>
    <li><a href="manage-courses.php">Manage Courses</a></li>
    <li><a href="#" class="active">Student Directory</a></li>
    <li><a href="edit-profile.php">Profile</a></li>
    <li><a href="../../api/logout.php" class="nav-cta">Log out</a></li>
  </ul>
</nav>

<main class="profile-shell" style="margin-top: 5rem; padding: 2rem 1.25rem;">
  <div class="manage-header">
    <div class="manage-title-area">
      <h1>Student Enrollment Directory</h1>
      <p style="color:var(--ink-soft); font-size:0.9rem; margin-top:0.25rem;">Track progress of students enrolled in your courses and send direct feedback messages.</p>
    </div>
  </div>

  <?php if ($success !== ''): ?>
    <div class="alert alert-success">✓ <?= e($success) ?></div>
  <?php endif; ?>

  <?php foreach ($errors as $err): ?>
    <div class="alert alert-error">✕ <?= e($err) ?></div>
  <?php endforeach; ?>

  <!-- Quick Statistics -->
  <div class="stat-row" style="margin-bottom: 2rem; display:grid; grid-template-columns:1fr 1fr; gap:1.5rem;">
    <div class="stat-card" style="background:var(--white); border:1px solid var(--border); border-radius:16px; padding:1.25rem;">
      <div style="font-size:0.85rem; color:var(--ink-soft); font-weight:600; text-transform:uppercase;">Total Enrollments</div>
      <div style="font-size:1.8rem; font-weight:700; color:var(--ink); margin-top:0.25rem;"><?= count($enrolled_list) ?></div>
    </div>
    <div class="stat-card" style="background:var(--white); border:1px solid var(--border); border-radius:16px; padding:1.25rem;">
      <div style="font-size:0.85rem; color:var(--ink-soft); font-weight:600; text-transform:uppercase;">Unique Students</div>
      <div style="font-size:1.8rem; font-weight:700; color:var(--ink); margin-top:0.25rem;"><?= count($total_unique_students) ?></div>
    </div>
  </div>

  <div class="manage-table-card">
    <div style="overflow-x:auto;">
      <?php if (empty($enrolled_list)): ?>
        <div style="text-align:center; padding:4rem; color:var(--ink-soft);">
          <div style="font-size:3rem; margin-bottom:1rem;">🎓</div>
          <h3>No student enrollments found</h3>
          <p style="font-size:0.85rem; margin-top:0.25rem;">Once students enroll in your courses, they will appear here.</p>
        </div>
      <?php else: ?>
        <table class="manage-table">
          <thead>
            <tr>
              <th>Student Name</th>
              <th>Email Address</th>
              <th>Course Title</th>
              <th>Progress</th>
              <th>Action</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($enrolled_list as $index => $e): ?>
              <tr>
                <td><strong><?= e($e['student_name']) ?></strong></td>
                <td><?= e($e['student_email']) ?></td>
                <td><?= e($e['course_title']) ?></td>
                <td>
                  <div class="progress-bar-wrap">
                    <div class="progress-track">
                      <div class="progress-fill" style="width:<?= round($e['progress']) ?>%"></div>
                    </div>
                    <span style="font-size:0.8rem; font-weight:600;"><?= round($e['progress']) ?>%</span>
                  </div>
                </td>
                <td>
                  <button class="btn-action-message" onclick="toggleMessageForm('drawer-<?= $index ?>')">✉ Message</button>
                </td>
              </tr>
              <!-- Toggleable Messaging Row -->
              <tr id="drawer-<?= $index ?>" class="message-drawer">
                <td colspan="5">
                  <div style="max-width: 600px;">
                    <h4 style="margin-bottom:0.75rem; color:var(--ink);">Send message to <?= e($e['student_name']) ?></h4>
                    <form method="POST" action="enrolled-students.php">
                      <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>" />
                      <input type="hidden" name="send_message" value="1" />
                      <input type="hidden" name="student_id" value="<?= e($e['student_id']) ?>" />
                      <input type="hidden" name="course_id" value="<?= e($e['course_id']) ?>" />
                      <input type="hidden" name="course_title" value="<?= e($e['course_title']) ?>" />

                      <textarea name="message_text" rows="3" required placeholder="Type encouraging feedback, check-in instructions, or notes for the student..." style="width:100%; border:1px solid var(--border); border-radius:8px; padding:0.75rem; font-family:'Inter',sans-serif; font-size:0.9rem; resize:vertical; background:white;"></textarea>
                      <div style="margin-top:0.75rem; display:flex; gap:0.5rem;">
                        <button type="submit" class="btn-pill btn-pill-primary" style="padding:0.45rem 1.25rem; font-size:0.82rem; min-width:auto;">Send Message</button>
                        <button type="button" class="btn-pill btn-pill-outline" onclick="toggleMessageForm('drawer-<?= $index ?>')" style="padding:0.45rem 1.25rem; font-size:0.82rem; min-width:auto;">Cancel</button>
                      </div>
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

  <!-- Messages History -->
  <?php if (!empty($sent_messages)): ?>
    <h2 style="font-family:'Playfair Display', serif; font-size:1.4rem; margin-bottom:1rem; color:var(--ink);">Recent Sent Messages</h2>
    <div style="background:var(--white); border:1px solid var(--border); border-radius:16px; padding:1.5rem;">
      <?php foreach ($sent_messages as $m): ?>
        <div style="border-bottom:1px solid var(--border); padding-bottom:1rem; margin-bottom:1rem; display:flex; justify-content:space-between; align-items:start; flex-wrap:wrap; gap:0.5rem;">
          <div>
            <div style="font-size:0.75rem; color:var(--gold); font-weight:700; text-transform:uppercase;">To Course: <?= e($m['courseTitle']) ?></div>
            <p style="font-size:0.9rem; margin-top:0.25rem; color:var(--ink-mid); font-style:italic;">"<?= e($m['messageText']) ?>"</p>
          </div>
          <div style="display:flex; flex-direction:column; align-items:flex-end; gap:0.35rem;">
            <span style="font-size:0.75rem; color:var(--ink-soft);"><?= date('M j, Y • g:i A', strtotime($m['createdAt'])) ?></span>
            <form method="POST" action="enrolled-students.php" style="margin: 0;" onsubmit="return confirm('Are you sure you want to delete this message?');">
              <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>" />
              <input type="hidden" name="delete_message" value="1" />
              <input type="hidden" name="message_id" value="<?= e($m['__id']) ?>" />
              <button type="submit" class="btn-pill btn-pill-outline" style="padding: 0.25rem 0.65rem; font-size: 0.7rem; min-width: auto; border-color: #FCA5A5; color: #DC2626;">Delete</button>
            </form>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</main>

<script>
  function toggleMessageForm(drawerId) {
    var drawer = document.getElementById(drawerId);
    if (drawer.style.display === 'table-row') {
      drawer.style.display = 'none';
    } else {
      drawer.style.display = 'table-row';
    }
  }
</script>
<script src="../../assets/js/script.js"></script>
</body>
</html>
