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

$courses_filter_options = [];
foreach ($org_courses as $c) {
    $cId = $c['__id'] ?? '';
    if ($cId !== '') {
        $courses_filter_options[$cId] = $c['title'] ?? 'Untitled Course';
    }
}

$selected_course_id = trim((string) ($_GET['course_id'] ?? 'all'));

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
                'student_phone' => !empty($studentDoc['phone']) ? $studentDoc['phone'] : 'Not provided',
                'enrolled_at'   => !empty($e['createdAt']) ? date('M j, Y', strtotime($e['createdAt'])) : 'N/A',
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
    .btn-action-view {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      padding: 0.45rem 0.85rem;
      font-size: 0.8rem;
      font-weight: 600;
      border-radius: 9999px;
      border: 1px solid var(--border);
      background: #F9F8F6;
      color: var(--ink-mid);
      text-decoration: none;
      transition: all 0.2s;
      cursor: pointer;
    }
    .btn-action-view:hover {
      background: #F0ECE1;
      border-color: var(--gold);
      color: var(--gold-dark);
    }
    .btn-pill { display: inline-flex; align-items: center; justify-content: center; padding: 0.75rem 1.75rem; font-size: 0.9rem; font-weight: 600; border-radius: 9999px; border: 1px solid transparent; cursor: pointer; transition: all 0.2s ease-in-out; text-decoration: none; min-width: 110px; }
    .btn-pill-primary { background: #A8732A; color: white; }
    .btn-pill-primary:hover { background: #8E5E1E; transform: translateY(-1px); }
    .btn-pill-outline { background: transparent; border-color: #E5E0D8; color: #4B5563; }
    .btn-pill-outline:hover { background: #F9F8F6; border-color: #C9933A; color: #A8732A; }

    /* Student Details Modal */
    .modal-backdrop {
      position: fixed;
      top: 0;
      left: 0;
      width: 100vw;
      height: 100vh;
      background: rgba(13, 17, 23, 0.55);
      backdrop-filter: blur(4px);
      z-index: 9999;
      display: flex;
      align-items: center;
      justify-content: center;
      opacity: 0;
      pointer-events: none;
      transition: opacity 0.25s ease;
    }
    .modal-backdrop.active {
      opacity: 1;
      pointer-events: auto;
    }
    .modal-card {
      background: var(--white);
      border-radius: var(--radius-lg);
      border: 1px solid var(--border);
      box-shadow: 0 20px 50px rgba(0,0,0,0.2);
      width: 90%;
      max-width: 520px;
      padding: 1.75rem;
      transform: translateY(20px);
      transition: transform 0.25s ease;
    }
    .modal-backdrop.active .modal-card {
      transform: translateY(0);
    }
    .modal-header {
      display: flex;
      justify-content: space-between;
      align-items: flex-start;
      padding-bottom: 1.25rem;
      border-bottom: 1px solid var(--border);
    }
    .modal-avatar {
      width: 52px;
      height: 52px;
      border-radius: 50%;
      background: #F4E4C3;
      color: #A8732A;
      display: flex;
      align-items: center;
      justify-content: center;
      font-weight: 700;
      font-size: 1.15rem;
      flex-shrink: 0;
    }
    .modal-close-btn {
      background: transparent;
      border: none;
      font-size: 1.6rem;
      color: var(--ink-soft);
      cursor: pointer;
      line-height: 1;
      padding: 0 0.5rem;
    }
    .modal-close-btn:hover {
      color: var(--ink);
    }
    .detail-grid {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 1.25rem;
      margin-top: 1.25rem;
    }
    .detail-item {
      display: flex;
      flex-direction: column;
      gap: 0.25rem;
    }
    .detail-label {
      font-size: 0.75rem;
      font-weight: 600;
      text-transform: uppercase;
      letter-spacing: 0.04em;
      color: var(--ink-soft);
    }
    .detail-val {
      font-size: 0.95rem;
      font-weight: 600;
      color: var(--ink);
    }
    .modal-footer {
      display: flex;
      justify-content: flex-end;
      gap: 0.75rem;
      margin-top: 1.75rem;
      padding-top: 1.25rem;
      border-top: 1px solid var(--border);
    }
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
    <?php if (!empty($courses_filter_options)): ?>
      <div class="manage-filter-area">
        <div style="display:flex; align-items:center; gap:0.75rem; background:var(--white); padding:0.6rem 1rem; border:1px solid var(--border); border-radius:12px; box-shadow:0 2px 8px rgba(0,0,0,0.03);">
          <label for="courseFilterSelect" style="font-size:0.85rem; font-weight:600; color:var(--ink-mid); white-space:nowrap; display:flex; align-items:center; gap:0.35rem;">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="color:var(--gold-dark);"><polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"></polygon></svg>
            Filter by Course:
          </label>
          <select id="courseFilterSelect" onchange="filterStudentsByCourse(this.value)" style="border:1px solid var(--border); background:var(--page-bg); padding:0.5rem 0.85rem; border-radius:8px; font-family:'Inter',sans-serif; font-size:0.88rem; color:var(--ink); font-weight:500; cursor:pointer; outline:none; transition:all 0.2s;">
            <option value="all" <?= $selected_course_id === 'all' ? 'selected' : '' ?>>All Courses (<?= count($enrolled_list) ?>)</option>
            <?php foreach ($courses_filter_options as $cId => $cTitle): ?>
              <option value="<?= e($cId) ?>" <?= $selected_course_id === $cId ? 'selected' : '' ?>><?= e($cTitle) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>
    <?php endif; ?>
  </div>

  <?php if ($success !== ''): ?>
    <div class="alert alert-success">✓ <?= e($success) ?></div>
  <?php endif; ?>

  <?php foreach ($errors as $err): ?>
    <div class="alert alert-error">✕ <?= e($err) ?></div>
  <?php endforeach; ?>

  <!-- Quick Statistics & Enrollment Analytics -->
  <div style="display:grid; grid-template-columns:1fr 2fr; gap:1.5rem; margin-bottom: 2rem;" class="stat-analytics-grid">
    <div style="display:flex; flex-direction:column; gap:1.25rem;">
      <div class="stat-card" style="background:var(--white); border:1px solid var(--border); border-radius:16px; padding:1.25rem;">
        <div style="font-size:0.85rem; color:var(--ink-soft); font-weight:600; text-transform:uppercase;">Total Enrollments</div>
        <div id="statTotalEnrollments" style="font-size:1.8rem; font-weight:700; color:var(--ink); margin-top:0.25rem;"><?= count($enrolled_list) ?></div>
      </div>
      <div class="stat-card" style="background:var(--white); border:1px solid var(--border); border-radius:16px; padding:1.25rem;">
        <div style="font-size:0.85rem; color:var(--ink-soft); font-weight:600; text-transform:uppercase;">Unique Students</div>
        <div id="statUniqueStudents" style="font-size:1.8rem; font-weight:700; color:var(--ink); margin-top:0.25rem;"><?= count($total_unique_students) ?></div>
      </div>
    </div>
    
    <!-- Enrollment Breakdown Pie Chart -->
    <div style="background:var(--white); border:1px solid var(--border); border-radius:16px; padding:1.25rem; box-shadow:var(--shadow);">
      <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:0.75rem;">
        <h4 style="font-family:'Playfair Display', serif; font-size:1.05rem; color:var(--ink); margin:0;">Students Enrolled by Course</h4>
        <span style="font-size:0.75rem; color:var(--gold-dark); font-weight:600;">Distribution</span>
      </div>
      <div style="position:relative; height:160px; display:flex; justify-content:center; align-items:center;">
        <?php if (empty($enrolled_list)): ?>
          <div style="font-size:0.85rem; color:var(--ink-soft);">No enrollments data</div>
        <?php else: ?>
          <canvas id="directoryPieChart"></canvas>
        <?php endif; ?>
      </div>
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
              <tr class="student-row" data-course-id="<?= e($e['course_id']) ?>" data-student-id="<?= e($e['student_id']) ?>">
                <td>
                  <a href="javascript:void(0)" onclick="openStudentModal(<?= $index ?>)" style="color:var(--ink); text-decoration:none; font-weight:700; display:inline-flex; align-items:center; gap:0.25rem;" title="Click to view student details">
                    <span><?= e($e['student_name']) ?></span>
                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="color:var(--gold-dark); opacity:0.8;"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"></path><polyline points="15 3 21 3 21 9"></polyline><line x1="10" y1="14" x2="21" y2="3"></line></svg>
                  </a>
                </td>
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
                  <div style="display:flex; gap:0.4rem; align-items:center;">
                    <button class="btn-action-view" onclick="openStudentModal(<?= $index ?>)" title="View Student Details">👤 Details</button>
                    <button class="btn-action-message" onclick="toggleMessageForm('drawer-<?= $index ?>')">✉ Message</button>
                  </div>
                </td>
              </tr>
              <!-- Toggleable Messaging Row -->
              <tr id="drawer-<?= $index ?>" class="message-drawer" data-course-id="<?= e($e['course_id']) ?>">
                <td colspan="5">
                  <div style="max-width: 600px;">
                    <h4 style="margin-bottom:0.75rem; color:var(--ink);">Send message to <?= e($e['student_name']) ?></h4>
                    <form method="POST" action="enrolled-students.php<?= $selected_course_id !== 'all' ? '?course_id=' . urlencode($selected_course_id) : '' ?>">
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
            <tr id="noFilteredResultsRow" style="display:none;">
              <td colspan="5" style="text-align:center; padding:3rem; color:var(--ink-soft);">
                <div style="font-size:2rem; margin-bottom:0.5rem;">🔍</div>
                <h4 style="color:var(--ink); margin-bottom:0.25rem;">No students found for this course</h4>
                <p style="font-size:0.85rem;">Try selecting another course from the filter above.</p>
              </td>
            </tr>
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
  <!-- Student Details Modal -->
  <div id="studentDetailsModal" class="modal-backdrop" onclick="closeStudentModalOnBackdrop(event)">
    <div class="modal-card">
      <div class="modal-header">
        <div style="display:flex; align-items:center; gap:1rem;">
          <div id="modalStudentAvatar" class="modal-avatar">ST</div>
          <div>
            <h3 id="modalStudentName" style="font-family:'Playfair Display',serif; font-size:1.3rem; color:var(--ink); margin:0;">Student Name</h3>
            <span id="modalStudentEmail" style="font-size:0.85rem; color:var(--ink-soft);">student@example.com</span>
          </div>
        </div>
        <button class="modal-close-btn" onclick="closeStudentModal()">&times;</button>
      </div>
      <div class="modal-body" style="padding-top:1rem;">
        <div class="detail-grid">
          <div class="detail-item">
            <span class="detail-label">Enrolled Course</span>
            <span id="modalCourseTitle" class="detail-val">Course Title</span>
          </div>
          <div class="detail-item">
            <span class="detail-label">Enrollment Date</span>
            <span id="modalEnrolledAt" class="detail-val">Aug 5, 2026</span>
          </div>
          <div class="detail-item">
            <span class="detail-label">Phone Number</span>
            <span id="modalStudentPhone" class="detail-val">Not provided</span>
          </div>
          <div class="detail-item">
            <span class="detail-label">Lessons Completed</span>
            <span id="modalLessonsCount" class="detail-val">0 / 0</span>
          </div>
        </div>

        <div style="margin-top:1.5rem; background:var(--page-bg); padding:1rem; border-radius:12px; border:1px solid var(--border);">
          <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:0.5rem;">
            <span style="font-size:0.85rem; font-weight:600; color:var(--ink-mid);">Overall Progress</span>
            <span id="modalProgressText" style="font-size:0.9rem; font-weight:700; color:#16A34A;">0%</span>
          </div>
          <div style="width:100%; height:8px; background:#E5E7EB; border-radius:4px; overflow:hidden;">
            <div id="modalProgressBar" style="height:100%; background:#16A34A; width:0%;"></div>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn-pill btn-pill-outline" onclick="closeStudentModal()" style="padding:0.45rem 1.25rem; font-size:0.82rem; min-width:auto;">Close</button>
        <button type="button" id="modalMsgBtn" class="btn-pill btn-pill-primary" style="padding:0.45rem 1.25rem; font-size:0.82rem; min-width:auto;">✉ Send Message</button>
      </div>
    </div>
  </div>
</main>

<script>
  const enrolledData = <?= json_encode(array_values($enrolled_list)) ?>;

  function openStudentModal(index) {
    const data = enrolledData[index];
    if (!data) return;

    document.getElementById('modalStudentName').textContent = data.student_name;
    document.getElementById('modalStudentEmail').textContent = data.student_email;
    document.getElementById('modalCourseTitle').textContent = data.course_title;
    document.getElementById('modalStudentPhone').textContent = data.student_phone;
    document.getElementById('modalEnrolledAt').textContent = data.enrolled_at;
    document.getElementById('modalLessonsCount').textContent = data.completed + ' / ' + data.total;
    
    const progressPct = Math.round(data.progress);
    document.getElementById('modalProgressText').textContent = progressPct + '%';
    document.getElementById('modalProgressBar').style.width = progressPct + '%';

    const initials = data.student_name.split(' ').map(n => n[0]).join('').substring(0, 2).toUpperCase() || 'ST';
    document.getElementById('modalStudentAvatar').textContent = initials;

    document.getElementById('modalMsgBtn').onclick = function() {
      closeStudentModal();
      const drawer = document.getElementById('drawer-' + index);
      if (drawer) {
        drawer.style.display = 'table-row';
        drawer.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
      }
    };

    document.getElementById('studentDetailsModal').classList.add('active');
  }

  function closeStudentModal() {
    document.getElementById('studentDetailsModal').classList.remove('active');
  }

  function closeStudentModalOnBackdrop(e) {
    if (e.target.id === 'studentDetailsModal') {
      closeStudentModal();
    }
  }

  function toggleMessageForm(drawerId) {
    var drawer = document.getElementById(drawerId);
    if (drawer.style.display === 'table-row') {
      drawer.style.display = 'none';
    } else {
      drawer.style.display = 'table-row';
    }
  }

  function filterStudentsByCourse(courseId) {
    const rows = document.querySelectorAll('.student-row');
    let visibleCount = 0;
    const uniqueStudents = new Set();

    rows.forEach((row, index) => {
      const rowCourseId = row.getAttribute('data-course-id');
      const studentId = row.getAttribute('data-student-id');
      const drawer = document.getElementById('drawer-' + index);

      if (courseId === 'all' || rowCourseId === courseId) {
        row.style.display = '';
        visibleCount++;
        if (studentId) uniqueStudents.add(studentId);
      } else {
        row.style.display = 'none';
        if (drawer) drawer.style.display = 'none';
      }
    });

    const statTotal = document.getElementById('statTotalEnrollments');
    const statUnique = document.getElementById('statUniqueStudents');
    if (statTotal) statTotal.textContent = visibleCount;
    if (statUnique) statUnique.textContent = uniqueStudents.size;

    const noResultsRow = document.getElementById('noFilteredResultsRow');
    if (noResultsRow) {
      noResultsRow.style.display = (visibleCount === 0 && rows.length > 0) ? '' : 'none';
    }

    const url = new URL(window.location.href);
    if (courseId === 'all') {
      url.searchParams.delete('course_id');
    } else {
      url.searchParams.set('course_id', courseId);
    }
    window.history.replaceState({}, '', url);
  }

  document.addEventListener('DOMContentLoaded', function() {
    const select = document.getElementById('courseFilterSelect');
    if (select && select.value !== 'all') {
      filterStudentsByCourse(select.value);
    }

    // Initialize Directory Pie Chart
    const ctxDir = document.getElementById('directoryPieChart');
    if (ctxDir && typeof enrolledData !== 'undefined' && enrolledData.length > 0) {
      const courseMap = {};
      enrolledData.forEach(item => {
        const title = item.course_title || 'Untitled Course';
        courseMap[title] = (courseMap[title] || 0) + 1;
      });

      const labels = Object.keys(courseMap);
      const dataValues = Object.values(courseMap);
      const colorPalette = ['#C9933A', '#16A34A', '#2563EB', '#7C3AED', '#E11D48', '#D97706'];

      new Chart(ctxDir.getContext('2d'), {
        type: 'doughnut',
        data: {
          labels: labels,
          datasets: [{
            data: dataValues,
            backgroundColor: colorPalette.slice(0, labels.length),
            borderWidth: 2,
            borderColor: '#FFFFFF',
            hoverOffset: 4
          }]
        },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          plugins: {
            legend: {
              position: 'right',
              labels: {
                boxWidth: 10,
                font: { family: 'Inter', size: 10 },
                color: '#374151'
              }
            },
            tooltip: {
              callbacks: {
                label: function(context) {
                  const val = context.raw || 0;
                  const total = context.dataset.data.reduce((a, b) => a + b, 0);
                  const pct = total > 0 ? Math.round((val / total) * 100) : 0;
                  return ` ${context.label}: ${val} (${pct}%)`;
                }
              }
            }
          }
        }
      });
    }
  });
</script>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="../../assets/js/script.js"></script>
</body>
</html>
