<?php

declare(strict_types=1);

require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/firestore.php';

start_secure_session();
if (!is_logged_in()) {
    redirect('pages/login.php');
}

$user = current_user();
$isStudent = ($user['role'] ?? '') === 'user';
if (!$isStudent) {
    redirect('pages/dashboard.php');
}

$courseId = trim((string) ($_GET['course_id'] ?? ''));
if ($courseId === '') {
    redirect('pages/dashboard.php');
}

$db = get_firestore();
$course = $db->get('courses', $courseId);

if (!$course) {
    redirect('pages/dashboard.php');
}

// Verify enrollment
$enrollmentId = $user['id'] . '_' . $courseId;
$enrollment = $db->get('enrollments', $enrollmentId);
if (!$enrollment) {
    // If not explicitly enrolled, redirect to course catalog
    redirect('pages/courses.php');
}

// Get lessons list
$lessons = $course['lessons'] ?? [];
usort($lessons, function($a, $b) {
    return ((int) ($a['sortOrder'] ?? 0)) <=> ((int) ($b['sortOrder'] ?? 0));
});

// Determine active lesson
$activeLessonId = trim((string) ($_GET['lesson_id'] ?? ''));
$activeLesson = null;
if ($activeLessonId !== '') {
    foreach ($lessons as $l) {
        if (($l['id'] ?? '') === $activeLessonId) {
            $activeLesson = $l;
            break;
        }
    }
}
// Fallback to first lesson if none selected
if (!$activeLesson && !empty($lessons)) {
    $activeLesson = $lessons[0];
    $activeLessonId = (string) ($activeLesson['id'] ?? '');
}

// Load or initialize progress document
$progressId = $user['id'] . '_' . $courseId;
$progress = $db->get('progress', $progressId);
if (!$progress) {
    $progress = [
        'studentId'          => (string) $user['id'],
        'courseId'           => $courseId,
        'completedLessons'   => 0,
        'totalLessons'       => count($lessons),
        'progressPercentage' => 0.0,
        'completedLessonIds' => [],
        'lastAccessedAt'     => date('c'),
        'createdAt'          => date('c'),
        'updatedAt'          => date('c')
    ];
    $db->set('progress', $progress, $progressId);
} else {
    // Sync/recalculate progress dynamically to prevent stale stats if course lessons changed
    $lessonIds = array_column($lessons, 'id');
    $completedLessonIds = array_intersect($progress['completedLessonIds'] ?? [], $lessonIds);
    
    $currentCompleted = count($completedLessonIds);
    $currentTotal = count($lessons);
    $currentPercentage = $currentTotal > 0 ? round(($currentCompleted / $currentTotal) * 100, 1) : 100.0;
    
    $storedCompleted = (int) ($progress['completedLessons'] ?? 0);
    $storedTotal = (int) ($progress['totalLessons'] ?? 0);
    $storedPercentage = (float) ($progress['progressPercentage'] ?? 0.0);
    
    if ($currentCompleted !== $storedCompleted || 
        $currentTotal !== $storedTotal || 
        abs($currentPercentage - $storedPercentage) > 0.05 ||
        count($completedLessonIds) !== count($progress['completedLessonIds'] ?? [])) {
        
        $progress['completedLessonIds'] = array_values($completedLessonIds);
        $progress['completedLessons']   = $currentCompleted;
        $progress['totalLessons']       = $currentTotal;
        $progress['progressPercentage'] = $currentPercentage;
        $progress['updatedAt']          = date('c');
        
        $db->set('progress', $progress, $progressId);
    }
}

// Handle Mark as Completed
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mark_completed'])) {
    try {
        verify_csrf_token($_POST['csrf_token'] ?? '');
        $completedLessonIds = $progress['completedLessonIds'] ?? [];

        if (!in_array($activeLessonId, $completedLessonIds, true)) {
            $completedLessonIds[] = $activeLessonId;
            $completedCount = count($completedLessonIds);
            $totalCount = count($lessons);
            $percentage = $totalCount > 0 ? round(($completedCount / $totalCount) * 100, 1) : 100.0;

            $progress['completedLessonIds'] = $completedLessonIds;
            $progress['completedLessons']   = $completedCount;
            $progress['totalLessons']       = $totalCount;
            $progress['progressPercentage'] = $percentage;
            $progress['lastAccessedAt']     = date('c');
            $progress['updatedAt']          = date('c');

            $db->set('progress', $progress, $progressId);
            
            // Redirect to reload page
            header('Location: course-workspace.php?course_id=' . urlencode($courseId) . '&lesson_id=' . urlencode($activeLessonId));
            exit();
        }
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
  <title><?= e($course['title']) ?> — Lawable</title>
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600;700;800&family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet" />
  <link rel="stylesheet" href="../../assets/css/lawable.css?v=1.4" />
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

    body.workspace-page {
      background: var(--page-bg) !important;
      color: var(--ink);
      font-family: 'Inter', sans-serif;
    }
    .workspace-shell {
      max-width: 1240px;
      margin: 5.5rem auto 2rem;
      padding: 0 1.5rem;
      box-sizing: border-box;
    }
    .workspace-layout {
      display: grid;
      grid-template-columns: 320px 1fr;
      gap: 2rem;
      align-items: start;
    }
    @media (max-width: 992px) {
      .workspace-layout {
        grid-template-columns: 1fr;
      }
    }
    .workspace-sidebar {
      background: var(--white);
      border: 1px solid var(--border);
      border-radius: 24px;
      padding: 1.5rem;
      box-shadow: 0 4px 24px rgba(13,17,23,0.04);
    }
    .workspace-content {
      background: var(--white);
      border: 1px solid var(--border);
      border-radius: 24px;
      padding: 2.25rem;
      box-shadow: 0 4px 24px rgba(13,17,23,0.04);
      min-height: 500px;
    }
    .lesson-link {
      display: flex;
      align-items: center;
      justify-content: space-between;
      padding: 0.85rem 1rem;
      border-radius: 12px;
      text-decoration: none;
      color: var(--ink-mid);
      font-weight: 500;
      font-size: 0.92rem;
      transition: all 0.2s;
      margin-bottom: 0.5rem;
    }
    .lesson-link:hover {
      background: var(--gold-lt);
      color: var(--gold-dk);
    }
    .lesson-link.active {
      background: var(--gold-lt);
      color: var(--gold-dk);
      font-weight: 600;
      border-left: 4px solid var(--gold);
    }
    .video-wrapper {
      position: relative;
      padding-bottom: 56.25%; /* 16:9 */
      height: 0;
      overflow: hidden;
      border-radius: 16px;
      background: #000;
      margin-bottom: 1.75rem;
      box-shadow: 0 4px 15px rgba(0,0,0,0.15);
    }
    .video-wrapper iframe, .video-wrapper video {
      position: absolute;
      top: 0;
      left: 0;
      width: 100%;
      height: 100%;
      border: 0;
    }
    .material-card {
      background: var(--page-bg);
      border: 1px solid var(--border);
      border-radius: 16px;
      padding: 1.25rem;
      margin-bottom: 1.75rem;
      display: flex;
      align-items: center;
      justify-content: space-between;
      flex-wrap: wrap;
      gap: 1rem;
    }
    .btn-pill {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      padding: 0.75rem 1.75rem;
      font-size: 0.9rem;
      font-weight: 600;
      border-radius: 9999px;
      border: 1px solid transparent;
      cursor: pointer;
      transition: all 0.2s ease-in-out;
      text-decoration: none;
      min-width: 110px;
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
  </style>
</head>
<body class="workspace-page">
<div class="cursor-glow" id="cursorGlow"></div>
<div class="progress-bar" id="progressBar"></div>

<nav id="navbar">
  <a href="../dashboard.php" class="nav-logo">Law<span>able</span></a>
  <ul class="nav-links">
    <li><a href="../dashboard.php">Dashboard</a></li>
    <li class="nav-dropdown">
      <a href="../courses.php" class="nav-dropdown-toggle">
        Courses <span class="nav-dropdown-chevron">▼</span>
      </a>
      <div class="nav-dropdown-menu">
        <a href="../courses.php">Explore Courses</a>
        <a href="../my-learnings.php">My Learnings</a>
      </div>
    </li>
    <li class="nav-profile-item">
      <a href="edit-profile.php" class="nav-profile" aria-label="Edit profile">
        <span aria-hidden="true">👤</span>
      </a>
    </li>
    <li><a href="../../api/logout.php" class="nav-cta">Log out</a></li>
  </ul>
</nav>

<main class="workspace-shell">
  <!-- Course Head Info -->
  <div style="margin-bottom: 2rem;">
    <div style="font-size:0.85rem; font-weight:700; color:var(--gold); text-transform:uppercase; letter-spacing:0.05em; margin-bottom:0.25rem;">
      <?= e($course['category'] ?? 'Law') ?> • Workspace
    </div>
    <h1 style="font-family:'Playfair Display', serif; font-size:2rem; font-weight:700; color:var(--ink);"><?= e($course['title']) ?></h1>
    
    <!-- Progress Bar -->
    <div style="display:flex; align-items:center; gap:1rem; margin-top:0.75rem;">
      <div style="flex:1; max-width:300px; height:8px; background:var(--border); border-radius:10px; overflow:hidden;">
        <div style="width:<?= round((float)($progress['progressPercentage'] ?? 0)) ?>%; height:100%; background:#16A34A; border-radius:10px;"></div>
      </div>
      <span style="font-size:0.85rem; font-weight:600; color:var(--ink-mid);">
        <?= round((float)($progress['progressPercentage'] ?? 0)) ?>% Complete (<?= count($progress['completedLessonIds'] ?? []) ?>/<?= count($lessons) ?> lessons)
      </span>
    </div>
  </div>

  <div class="workspace-layout">
    
    <!-- Left: Lessons Sidebar Navigation -->
    <aside class="workspace-sidebar">
      <h3 style="font-family:'Playfair Display', serif; font-size:1.15rem; margin-bottom:1.25rem; color:var(--ink); border-bottom:1px solid var(--border); padding-bottom:0.5rem;">📖 Course Syllabus</h3>
      
      <?php if (empty($lessons)): ?>
        <p style="font-size:0.85rem; color:var(--ink-soft);">No syllabus lessons available.</p>
      <?php else: ?>
        <div style="display:flex; flex-direction:column;">
          <?php foreach ($lessons as $index => $l): 
              $isCompleted = in_array(($l['id'] ?? ''), $progress['completedLessonIds'] ?? [], true);
              $isActive = ($l['id'] ?? '') === $activeLessonId;
          ?>
            <a href="course-workspace.php?course_id=<?= urlencode($courseId) ?>&lesson_id=<?= urlencode((string)$l['id']) ?>" class="lesson-link <?= $isActive ? 'active' : '' ?>">
              <span style="display:flex; align-items:center; gap:0.5rem; text-overflow:ellipsis; overflow:hidden; white-space:nowrap;">
                <span style="font-weight:700; color:var(--gold);"><?= $index + 1 ?>.</span>
                <span><?= e($l['title']) ?></span>
              </span>
              <span><?= $isCompleted ? '✅' : '○' ?></span>
            </a>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </aside>

    <!-- Right: Active Lesson Workspace -->
    <div class="workspace-content">
      <?php if (!$activeLesson): ?>
        <div style="text-align:center; padding:5rem 2rem; color:var(--ink-soft);">
          <div style="font-size:3rem; margin-bottom:1rem;">📘</div>
          <h3>Select a lesson from the syllabus menu to start learning.</h3>
        </div>
      <?php else: ?>
        <!-- Lesson Header -->
        <div style="margin-bottom: 2rem; border-bottom:1px solid var(--border); padding-bottom:1rem;">
          <h2 style="font-family:'Playfair Display', serif; font-size:1.6rem; color:var(--ink);"><?= e($activeLesson['title']) ?></h2>
          <div style="font-size:0.85rem; color:var(--ink-soft); margin-top:0.25rem;">
            Estimated Reading: <strong><?= (int) ($activeLesson['durationMinutes'] ?? 15) ?> minutes</strong>
          </div>
        </div>

        <!-- Video Player -->
        <?php if (!empty($activeLesson['videoUrl'])): 
            $vid = $activeLesson['videoUrl'];
            $embedUrl = '';
            // Parse YouTube URLs to embed
            if (preg_match('%(?:youtube(?:-nocookie)?\.com/(?:[^/]+/.+/|(?:v|e(?:mbed)?)/|.*[?&]v=)|youtu\.be/)([^"&?/ ]{11})%i', $vid, $match)) {
                $embedUrl = "https://www.youtube.com/embed/" . $match[1];
            }
        ?>
          <div class="video-wrapper">
            <?php if ($embedUrl !== ''): ?>
              <iframe src="<?= e($embedUrl) ?>" allowfullscreen></iframe>
            <?php else: ?>
              <video src="<?= e($vid) ?>" controls></video>
            <?php endif; ?>
          </div>
        <?php endif; ?>

        <!-- Study Materials Card (PDF Uploads and links) -->
        <?php if (!empty($activeLesson['noteFile']) || !empty($activeLesson['documentUrl'])): ?>
          <div class="material-card">
            <div>
              <h4 style="font-weight:600; color:var(--ink); font-size:0.95rem; display:flex; align-items:center; gap:0.35rem;">📄 Attached Study Notes</h4>
              <p style="font-size:0.78rem; color:var(--ink-soft); margin-top:0.15rem;">Download or view the references attached to this module.</p>
            </div>
            <div style="display:flex; gap:0.5rem;">
              <?php if (!empty($activeLesson['noteFile'])): ?>
                <a href="../../uploads/notes/<?= e($activeLesson['noteFile']) ?>" target="_blank" class="btn-pill btn-pill-primary" style="padding:0.5rem 1rem; font-size:0.8rem; min-width:auto;">📥 Download Notes (PDF)</a>
              <?php endif; ?>
              <?php if (!empty($activeLesson['documentUrl'])): ?>
                <a href="<?= e($activeLesson['documentUrl']) ?>" target="_blank" class="btn-pill btn-pill-outline" style="padding:0.5rem 1rem; font-size:0.8rem; min-width:auto;">🔗 Open Slides / Docs</a>
              <?php endif; ?>
            </div>
          </div>
        <?php endif; ?>

        <!-- Lesson Body Text Content -->
        <div class="lesson-content-body" style="font-size:1rem; line-height:1.75; color:var(--ink-mid); margin-bottom:2.5rem; white-space:pre-line;">
          <?= e($activeLesson['content'] ?? '') ?>
        </div>

        <!-- Completion Action Row -->
        <div style="display:flex; justify-content:space-between; align-items:center; border-top:1px solid var(--border); padding-top:1.5rem;">
          <a href="../dashboard.php" style="text-decoration:none; color:var(--ink-soft); font-weight:600; font-size:0.9rem;">← Back to Dashboard</a>
          
          <?php 
            $isAlreadyCompleted = in_array($activeLessonId, $progress['completedLessonIds'] ?? [], true);
            if ($isAlreadyCompleted):
          ?>
            <span style="font-size:0.95rem; color:#16A34A; font-weight:700; display:flex; align-items:center; gap:0.35rem;">✓ Lesson Completed</span>
          <?php else: ?>
            <form method="POST" action="course-workspace.php?course_id=<?= urlencode($courseId) ?>&lesson_id=<?= urlencode($activeLessonId) ?>">
              <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>" />
              <input type="hidden" name="mark_completed" value="1" />
              <button type="submit" class="btn-pill btn-pill-primary">Mark as Completed ✓</button>
            </form>
          <?php endif; ?>
        </div>
      <?php endif; ?>
    </div>

  </div>
</main>

<script src="../../assets/js/script.js"></script>
</body>
</html>
