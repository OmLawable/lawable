<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/firestore.php';
require_once __DIR__ . '/../includes/certificates.php';
start_secure_session();

$user       = current_user();
$isLoggedIn = $user !== null;
$isStudent  = $isLoggedIn && ($user['role'] ?? '') === 'user';
$isAdmin    = $isLoggedIn && ($user['role'] ?? '') === 'admin';

$db = get_firestore();

$enrolled_courses = [];
$user_certs       = [];
$total_enrolled   = 0;
$in_progress_cnt  = 0;
$completed_cnt    = 0;
$cert_cnt         = 0;

if ($isStudent) {
    $student_id  = (string) $user['id'];
    $enrollments = $db->query('enrollments', [['studentId', 'EQUAL', $student_id]], 500);

    foreach ($enrollments as $e) {
        $cId = $e['courseId'] ?? '';
        if (empty($cId)) continue;

        $courseDoc = $db->get('courses', $cId);
        if (!$courseDoc) continue;

        $progressId  = $student_id . '_' . $cId;
        $progressDoc = $db->get('progress', $progressId);

        $pct        = (float) ($progressDoc['progressPercentage'] ?? 0.0);
        $completedL = (int) ($progressDoc['completedLessons'] ?? 0);
        $totalL     = (int) ($progressDoc['totalLessons'] ?? count($courseDoc['lessons'] ?? []));

        if ($pct >= 100.0) {
            $completed_cnt++;
            check_and_generate_certificate($student_id, $cId);
        } else {
            $in_progress_cnt++;
        }

        $enrolled_courses[] = [
            'id'                  => $courseDoc['__id'],
            'title'               => $courseDoc['title'] ?? 'Untitled Course',
            'description'         => $courseDoc['description'] ?? '',
            'price'               => (float) ($courseDoc['price'] ?? 0.0),
            'category'            => $courseDoc['category'] ?? 'General',
            'difficulty'          => $courseDoc['difficulty'] ?? 'all-levels',
            'rating'              => (float) ($courseDoc['rating'] ?? 4.5),
            'imageUrl'            => $courseDoc['imageUrl'] ?? '',
            'organizationName'    => $courseDoc['organizationName'] ?? $courseDoc['teacherName'] ?? 'Lawable',
            'progress_percentage' => $pct,
            'completed_lessons'   => $completedL,
            'total_lessons'       => $totalL,
            'last_accessed_at'    => $progressDoc['lastAccessedAt'] ?? '',
            'enrolled_at'         => $e['enrolledAt'] ?? '',
        ];
    }

    $total_enrolled = count($enrolled_courses);

    usort($enrolled_courses, function($a, $b) {
        $cmp = strcmp($b['last_accessed_at'], $a['last_accessed_at']);
        if ($cmp !== 0) return $cmp;
        return strcmp($b['enrolled_at'], $a['enrolled_at']);
    });

    $user_certs = get_student_certificates($student_id);
    $cert_cnt   = count($user_certs);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>My Learnings — Lawable</title>
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600;700;800&family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet" />
  <link rel="stylesheet" href="../assets/css/lawable.css?v=1.4" />
  <style>
    :root {
      --gold: #C9933A;
      --gold-dk: #A8732A;
      --gold-lt: #F4E4C3;
      --cream: #FCF8F1;
      --page-bg: #F6EEF6;
      --white: #FFFFFF;
      --ink: #0D1117;
      --ink-mid: #374151;
      --ink-soft: #6B7280;
      --border: #E5E0D8;
      --green: #16a34a;
      --green-bg: #DCFCE7;
      --blue: #2563EB;
      --blue-bg: #DBEAFE;
      --nav-h: 68px;
      --radius: 12px;
      --radius-lg: 16px;
      --shadow: 0 4px 24px rgba(13,17,23,0.08);
      --shadow-lg: 0 12px 40px rgba(13,17,23,0.12);
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
      --green: #22C55E;
      --green-bg: #064E3B;
      --blue: #60A5FA;
      --blue-bg: #1E3A5F;
      --shadow: 0 4px 24px rgba(0,0,0,0.40);
      --shadow-lg: 0 12px 40px rgba(0,0,0,0.50);
    }

    body { background: var(--page-bg); font-family: 'Inter', sans-serif; color: var(--ink); min-height: 100vh; }
    .learnings-page { padding-top: calc(var(--nav-h) + 24px); padding-bottom: 4rem; min-height: 100vh; }

    .learnings-hero {
      text-align: center;
      padding: 2.5rem 2rem 2rem;
      max-width: 900px;
      margin: 0 auto;
    }
    .learnings-hero h1 {
      font-family: 'Playfair Display', serif;
      font-size: clamp(2rem, 4vw, 2.75rem);
      font-weight: 700;
      color: var(--ink);
      line-height: 1.15;
      margin-bottom: 0.6rem;
    }
    .learnings-hero h1 em { font-style: normal; color: var(--gold); }
    .learnings-hero p {
      color: var(--ink-soft);
      font-size: 1rem;
      max-width: 600px;
      margin: 0 auto;
      line-height: 1.6;
    }

    .learnings-stats {
      display: flex;
      justify-content: center;
      gap: 1.5rem;
      max-width: 900px;
      margin: 1.5rem auto 2.5rem;
      padding: 0 1.5rem;
      flex-wrap: wrap;
    }
    .stat-badge {
      background: var(--white);
      border: 1px solid var(--border);
      border-radius: var(--radius);
      padding: 0.9rem 1.5rem;
      display: flex;
      align-items: center;
      gap: 0.85rem;
      box-shadow: var(--shadow);
      min-width: 170px;
    }
    .stat-badge-icon {
      width: 40px; height: 40px;
      border-radius: 10px;
      background: var(--gold-lt);
      color: var(--gold-dk);
      display: flex; align-items: center; justify-content: center;
      font-size: 1.1rem; font-weight: 700;
    }
    .stat-badge-icon.green { background: var(--green-bg); color: var(--green); }
    .stat-badge-icon.blue { background: var(--blue-bg); color: var(--blue); }
    .stat-badge-num { font-size: 1.35rem; font-weight: 700; color: var(--ink); line-height: 1; }
    .stat-badge-lbl { font-size: 0.75rem; color: var(--ink-soft); font-weight: 500; margin-top: 0.2rem; }

    .learnings-container { max-width: 1100px; margin: 0 auto; padding: 0 1.5rem; }
    .learnings-tabs {
      display: flex;
      gap: 0.5rem;
      margin-bottom: 2rem;
      border-bottom: 2px solid var(--border);
      padding-bottom: 0.5rem;
      flex-wrap: wrap;
    }
    .learnings-tab {
      background: transparent;
      border: none;
      font-size: 0.9rem;
      font-weight: 600;
      color: var(--ink-soft);
      padding: 0.6rem 1.2rem;
      border-radius: 8px;
      cursor: pointer;
      font-family: inherit;
      transition: all 0.2s;
    }
    .learnings-tab:hover { color: var(--ink); background: rgba(201,147,58,0.08); }
    .learnings-tab.active {
      color: var(--gold-dk);
      background: var(--gold-lt);
    }

    .learnings-grid {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
      gap: 1.75rem;
    }

    .learning-card {
      background: var(--white);
      border-radius: var(--radius-lg);
      border: 1px solid var(--border);
      overflow: hidden;
      box-shadow: var(--shadow);
      display: flex;
      flex-direction: column;
      transition: transform 0.25s, box-shadow 0.25s;
    }
    .learning-card:hover {
      transform: translateY(-3px);
      box-shadow: var(--shadow-lg);
    }

    .learning-thumb {
      height: 160px;
      background-size: cover;
      background-position: center;
      position: relative;
      background-color: var(--gold-lt);
    }
    .learning-thumb-fallback {
      height: 160px;
      background: linear-gradient(135deg, #1a0e05 0%, #2d1a08 100%);
      display: flex; align-items: center; justify-content: center;
      font-size: 3rem; color: var(--gold);
    }
    .learning-badge {
      position: absolute;
      top: 12px; right: 12px;
      font-size: 0.72rem;
      font-weight: 700;
      padding: 0.25rem 0.75rem;
      border-radius: 20px;
      text-transform: uppercase;
      letter-spacing: 0.05em;
    }
    .learning-badge.in-progress { background: var(--gold-lt); color: var(--gold-dk); border: 1px solid rgba(201,147,58,0.3); }
    .learning-badge.completed { background: var(--green-bg); color: var(--green); border: 1px solid rgba(22,163,74,0.3); }

    .learning-body {
      padding: 1.35rem;
      display: flex;
      flex-direction: column;
      flex: 1;
    }
    .learning-cat {
      font-size: 0.72rem;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: 0.08em;
      color: var(--gold);
      margin-bottom: 0.35rem;
    }
    .learning-title {
      font-family: 'Playfair Display', serif;
      font-size: 1.15rem;
      font-weight: 700;
      color: var(--ink);
      line-height: 1.3;
      margin-bottom: 0.5rem;
      display: -webkit-box;
      -webkit-line-clamp: 2;
      -webkit-box-orient: vertical;
      overflow: hidden;
    }
    .learning-org {
      font-size: 0.8rem;
      color: var(--ink-soft);
      margin-bottom: 1rem;
    }

    .learning-progress-wrap { margin-top: auto; margin-bottom: 1.25rem; }
    .learning-progress-header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      font-size: 0.78rem;
      font-weight: 600;
      color: var(--ink-mid);
      margin-bottom: 0.4rem;
    }
    .learning-progress-bar {
      height: 8px;
      background: var(--border);
      border-radius: 4px;
      overflow: hidden;
    }
    .learning-progress-fill {
      height: 100%;
      background: linear-gradient(90deg, var(--gold) 0%, var(--gold-dk) 100%);
      border-radius: 4px;
      transition: width 0.4s ease;
    }
    .learning-progress-fill.completed {
      background: linear-gradient(90deg, #16a34a 0%, #15803d 100%);
    }

    .learning-actions {
      display: flex;
      gap: 0.5rem;
      border-top: 1px solid var(--border);
      padding-top: 1rem;
    }
    .btn-content {
      flex: 1;
      text-align: center;
      padding: 0.6rem 0.85rem;
      border-radius: 8px;
      border: 1px solid var(--border);
      background: var(--white);
      color: var(--ink);
      font-size: 0.82rem;
      font-weight: 600;
      text-decoration: none;
      transition: all 0.2s;
    }
    .btn-content:hover { background: var(--page-bg); border-color: var(--gold); color: var(--gold-dk); }

    .btn-learn {
      flex: 1;
      text-align: center;
      padding: 0.6rem 0.85rem;
      border-radius: 8px;
      background: var(--gold);
      color: #1a0e05;
      font-size: 0.82rem;
      font-weight: 700;
      text-decoration: none;
      transition: all 0.2s;
    }
    .btn-learn:hover { background: #D8A84F; transform: translateY(-1px); }

    .empty-learnings {
      background: var(--white);
      border-radius: var(--radius-lg);
      border: 1px dashed var(--border);
      padding: 4rem 2rem;
      text-align: center;
      max-width: 600px;
      margin: 2rem auto;
      box-shadow: var(--shadow);
    }
    .empty-learnings-icon { font-size: 3.5rem; margin-bottom: 1rem; }
    .empty-learnings h2 { font-family: 'Playfair Display', serif; font-size: 1.5rem; margin-bottom: 0.5rem; color: var(--ink); }
    .empty-learnings p { color: var(--ink-soft); font-size: 0.95rem; margin-bottom: 1.75rem; line-height: 1.6; }
    .empty-btn {
      display: inline-flex; align-items: center; gap: 0.5rem;
      background: var(--gold); color: #1a0e05;
      padding: 0.8rem 1.75rem; border-radius: 99px;
      font-weight: 700; font-size: 0.9rem; text-decoration: none;
      transition: all 0.25s;
    }
    .empty-btn:hover { background: #D8A84F; transform: translateY(-2px); box-shadow: 0 8px 24px rgba(201,147,58,0.3); }

    .is-hidden { display: none !important; }
  </style>
</head>
<body>

<div class="cursor-glow" id="cursorGlow"></div>
<div class="progress-bar" id="progressBar"></div>
<button class="back-top" id="backTop" onclick="window.scrollTo({top:0,behavior:'smooth'})">↑</button>

<nav id="navbar" class="scrolled">
  <a href="<?= $isAdmin ? 'admin/dashboard.php' : ($isLoggedIn ? 'dashboard.php' : '../index.php') ?>" class="nav-logo">Law<span>able</span></a>
  <ul class="nav-links">
    <li><a href="offerings.php">Offerings</a></li>
    <li class="nav-dropdown">
      <a href="courses.php" class="nav-dropdown-toggle active">
        Courses <span class="nav-dropdown-chevron">▼</span>
      </a>
      <div class="nav-dropdown-menu">
        <a href="courses.php">Explore Courses</a>
        <a href="my-learnings.php" class="active">My Learnings</a>
      </div>
    </li>
    <li><a href="about.php">About</a></li>
    <li><a href="contact.php">Contact</a></li>
    <?php if ($isLoggedIn): ?>
    <?php if (!$isAdmin): ?>
    <li class="nav-profile-item">
      <a href="student/edit-profile.php" class="nav-profile" aria-label="Edit profile">
        <span aria-hidden="true">👤</span>
      </a>
    </li>
    <?php endif; ?>
    <li><a href="../api/logout.php" class="nav-cta">Log out</a></li>
    <?php else: ?>
    <li><a href="login.php" class="nav-cta">Log in →</a></li>
    <?php endif; ?>
    <li>
      <button class="theme-toggle" type="button" data-theme-toggle aria-label="Switch to dark theme" aria-pressed="false">
        <span class="theme-toggle-icon" aria-hidden="true">D</span>
        <span class="theme-toggle-text">Dark</span>
      </button>
    </li>
  </ul>
  <button class="nav-hamburger" id="hamburger" aria-label="Menu"><span></span><span></span><span></span></button>
</nav>

<nav class="nav-drawer" id="drawer">
  <a href="offerings.php" onclick="closeDrawer()">Offerings</a>
  <a href="courses.php" onclick="closeDrawer()">Explore Courses</a>
  <a href="my-learnings.php" onclick="closeDrawer()">My Learnings</a>
  <a href="about.php" onclick="closeDrawer()">About</a>
  <a href="contact.php" onclick="closeDrawer()">Contact</a>
  <?php if ($isLoggedIn): ?>
  <?php if (!$isAdmin): ?>
  <a href="student/edit-profile.php" onclick="closeDrawer()">Edit Profile</a>
  <?php endif; ?>
  <a href="../api/logout.php" class="drawer-cta">Log out</a>
  <?php else: ?>
  <a href="login.php" class="drawer-cta">Log in →</a>
  <?php endif; ?>
  <button class="theme-toggle drawer-theme-toggle" type="button" data-theme-toggle aria-label="Switch to dark theme" aria-pressed="false">
    <span class="theme-toggle-icon" aria-hidden="true">D</span>
    <span class="theme-toggle-text">Dark theme</span>
  </button>
</nav>

<div class="learnings-page">
  <div class="learnings-hero fade-up">
    <h1>Your <em>Learning Journey</em></h1>
    <p>Track your course progress, review modules, and resume learning right where you left off.</p>
  </div>

  <?php if ($isStudent): ?>
    <div class="learnings-stats fade-up delay-1">
      <div class="stat-badge">
        <div class="stat-badge-icon">📚</div>
        <div>
          <div class="stat-badge-num"><?= $total_enrolled ?></div>
          <div class="stat-badge-lbl">Total Enrolled</div>
        </div>
      </div>
      <div class="stat-badge">
        <div class="stat-badge-icon blue">⏳</div>
        <div>
          <div class="stat-badge-num"><?= $in_progress_cnt ?></div>
          <div class="stat-badge-lbl">In Progress</div>
        </div>
      </div>
      <div class="stat-badge">
        <div class="stat-badge-icon green">🏅</div>
        <div>
          <div class="stat-badge-num"><?= $completed_cnt ?></div>
          <div class="stat-badge-lbl">Completed</div>
        </div>
      </div>
    </div>

    <div class="learnings-container">
      <?php if ($total_enrolled === 0): ?>
        <div class="empty-learnings fade-up">
          <div class="empty-learnings-icon">🎓</div>
          <h2>No Enrolled Courses Yet</h2>
          <p>Explore our catalog of constitutional law, corporate law, cyber security, and technology courses to start learning today.</p>
          <a href="courses.php" class="empty-btn">Explore Courses →</a>
        </div>
      <?php else: ?>
        <div class="learnings-tabs fade-up delay-1" id="learningsTabs">
          <button class="learnings-tab active" onclick="filterLearnings('all', this)">All Courses (<?= $total_enrolled ?>)</button>
          <button class="learnings-tab" onclick="filterLearnings('in_progress', this)">In Progress (<?= $in_progress_cnt ?>)</button>
          <button class="learnings-tab" onclick="filterLearnings('completed', this)">Completed (<?= $completed_cnt ?>)</button>
          <button class="learnings-tab" onclick="filterLearnings('certificates', this)">Certificates Earned (<?= $cert_cnt ?>)</button>
        </div>

        <div class="learnings-grid" id="learningsGrid">
          <?php foreach ($enrolled_courses as $c):
            $pct = min(100, round($c['progress_percentage']));
            $isComp = $pct >= 100;
            $filterCat = $isComp ? 'completed' : 'in_progress';

            $courseImage = $c['imageUrl'];
            $titleLower = strtolower($c['title']);
            $catLower = strtolower($c['category']);
            if (empty($courseImage)) {
                if (str_contains($titleLower, 'python') || str_contains($titleLower, 'data structure') || str_contains($titleLower, 'algorithm')) {
                    $courseImage = '../assets/images/dsa_python.png';
                } elseif (str_contains($titleLower, 'web dev') || str_contains($titleLower, 'bootcamp') || str_contains($titleLower, 'javascript') || str_contains($titleLower, 'html') || str_contains($titleLower, 'css')) {
                    $courseImage = '../assets/images/web_dev.png';
                } elseif (str_contains($titleLower, 'database') || str_contains($titleLower, 'sql')) {
                    $courseImage = '../assets/images/database_sql.png';
                } elseif (str_contains($catLower, 'law') || str_contains($catLower, 'justice')) {
                    $courseImage = '../assets/images/constitutional_law.png';
                } elseif (str_contains($catLower, 'technology') || str_contains($catLower, 'computer science')) {
                    $courseImage = '../assets/images/web_dev.png';
                } elseif (str_contains($catLower, 'business') || str_contains($catLower, 'compliance')) {
                    $courseImage = '../assets/images/business_compliance.png';
                } elseif (str_contains($catLower, 'personal') || str_contains($catLower, 'development') || str_contains($catLower, 'communication')) {
                    $courseImage = '../assets/images/personal_development.png';
                } else {
                    $courseImage = '../assets/images/constitutional_law.png';
                }
            }
          ?>
          <div class="learning-card fade-up" data-status="<?= $filterCat ?>">
            <?php if (!empty($courseImage)): ?>
              <div class="learning-thumb" style="background-image: url('<?= e($courseImage) ?>');">
            <?php else: ?>
              <div class="learning-thumb-fallback">⚖️</div>
            <?php endif; ?>
              <span class="learning-badge <?= $isComp ? 'completed' : 'in-progress' ?>">
                <?= $isComp ? '✓ Completed' : 'In Progress' ?>
              </span>
            </div>

            <div class="learning-body">
              <div class="learning-cat"><?= e($c['category']) ?></div>
              <div class="learning-title"><?= e($c['title']) ?></div>
              <div class="learning-org">By <?= e($c['organizationName']) ?></div>

              <div class="learning-progress-wrap">
                <div class="learning-progress-header">
                  <span>Progress</span>
                  <span><?= $pct ?>% (<?= $c['completed_lessons'] ?>/<?= $c['total_lessons'] ?> Modules)</span>
                </div>
                <div class="learning-progress-bar">
                  <div class="learning-progress-fill <?= $isComp ? 'completed' : '' ?>" style="width: <?= $pct ?>%;"></div>
                </div>
              </div>

              <div class="learning-actions">
                <a href="course-detail.php?id=<?= e($c['id']) ?>" class="btn-content">View Content →</a>
                <a href="student/course-workspace.php?course_id=<?= e($c['id']) ?>" class="btn-learn">
                  <?= $isComp ? 'Review' : 'Continue ▶' ?>
                </a>
              </div>
            </div>
          </div>
          <?php endforeach; ?>
        </div>

        <!-- Certificates Earned Tabular View -->
        <div id="certificatesTableWrap" style="display: none; background: var(--paper); border: 1px solid var(--border); border-radius: 20px; box-shadow: var(--shadow); overflow: hidden; margin-top: 1rem;">
          <div style="overflow-x: auto;">
            <?php if (empty($user_certs)): ?>
              <div style="text-align: center; padding: 3.5rem 1.5rem; color: var(--ink-soft);">
                <div style="font-size: 3rem; margin-bottom: 0.75rem;">📜</div>
                <h3 style="font-family: 'Playfair Display', serif; font-size: 1.25rem; color: var(--ink); margin-bottom: 0.35rem;">No Certificates Earned Yet</h3>
                <p style="font-size: 0.88rem; max-width: 440px; margin: 0 auto 1.25rem;">Complete 100% of any course to earn your official verified Lawable Certificate of Accomplishment.</p>
                <a href="courses.php" class="empty-btn" style="text-decoration: none; display: inline-flex;">Explore Catalog →</a>
              </div>
            <?php else: ?>
              <table style="width: 100%; border-collapse: collapse; font-size: 0.9rem; text-align: left;">
                <thead>
                  <tr style="background: var(--cream); border-bottom: 1px solid var(--border);">
                    <th style="padding: 1rem 1.25rem; font-weight: 700; color: var(--ink-soft); font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.05em;">Certificate ID</th>
                    <th style="padding: 1rem 1.25rem; font-weight: 700; color: var(--ink-soft); font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.05em;">Course Title</th>
                    <th style="padding: 1rem 1.25rem; font-weight: 700; color: var(--ink-soft); font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.05em;">Category & Level</th>
                    <th style="padding: 1rem 1.25rem; font-weight: 700; color: var(--ink-soft); font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.05em;">Organization</th>
                    <th style="padding: 1rem 1.25rem; font-weight: 700; color: var(--ink-soft); font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.05em;">Issued Date</th>
                    <th style="padding: 1rem 1.25rem; font-weight: 700; color: var(--ink-soft); font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.05em; text-align: right;">Action</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($user_certs as $cert): 
                    $cOrgName = $cert['organizationName'] ?? '';
                    if (empty($cOrgName) || $cOrgName === 'Lawable Academy') {
                        if (!empty($cert['courseId'])) {
                            $cDoc = $db->get('courses', $cert['courseId']);
                            if ($cDoc) {
                                if (!empty($cDoc['organizationId'])) {
                                    $oDoc = $db->get('organizations', $cDoc['organizationId']);
                                    if ($oDoc && !empty($oDoc['organizationName'])) {
                                        $cOrgName = $oDoc['organizationName'];
                                    }
                                }
                                if (empty($cOrgName) && !empty($cDoc['organizationName'])) {
                                    $cOrgName = $cDoc['organizationName'];
                                }
                            }
                        }
                    }
                    if (empty($cOrgName)) {
                        $cOrgName = 'Lawable Academy';
                    }
                  ?>
                    <tr style="border-bottom: 1px solid var(--border); transition: background 0.15s;" onmouseenter="this.style.background='var(--sky)';" onmouseleave="this.style.background='transparent';">
                      <td style="padding: 1.1rem 1.25rem; font-weight: 700; font-family: monospace; color: var(--gold-dk);">
                        📜 <?= e($cert['certNumber'] ?? '') ?>
                      </td>
                      <td style="padding: 1.1rem 1.25rem; font-weight: 700; color: var(--ink); font-family: 'Playfair Display', serif; font-size: 1.02rem;">
                        <?= e($cert['courseTitle'] ?? 'Course Certificate') ?>
                      </td>
                      <td style="padding: 1.1rem 1.25rem; color: var(--ink-mid);">
                        <span style="display: inline-block; background: var(--gold-lt); color: var(--gold-dk); font-size: 0.75rem; font-weight: 700; padding: 0.2rem 0.6rem; border-radius: 4px; text-transform: uppercase;">
                          <?= e($cert['category'] ?? 'General Law') ?>
                        </span>
                        <div style="font-size: 0.78rem; color: var(--ink-soft); font-weight: 500; margin-top: 0.35rem;">
                          <?= e($cert['difficulty'] ?? 'Advanced') ?> Level
                        </div>
                      </td>
                      <td style="padding: 1.1rem 1.25rem; color: var(--ink); font-weight: 600;">
                        🏛 <?= e($cOrgName) ?>
                      </td>
                      <td style="padding: 1.1rem 1.25rem; color: var(--ink-mid); white-space: nowrap;">
                        <?= date('M j, Y', strtotime($cert['issuedAt'] ?? 'now')) ?>
                      </td>
                      <td style="padding: 1.1rem 1.25rem; text-align: right; white-space: nowrap;">
                        <a href="student/view-certificate.php?id=<?= e($cert['__id'] ?? $cert['id']) ?>" target="_blank" style="display: inline-flex; align-items: center; gap: 0.4rem; padding: 0.55rem 1.1rem; background: var(--gold-dk); color: #FFFFFF; font-size: 0.82rem; font-weight: 700; border-radius: 9999px; text-decoration: none; transition: background 0.2s;" onmouseenter="this.style.background='var(--gold)';" onmouseleave="this.style.background='var(--gold-dk)';">
                          <span>🖨️</span> View & Print
                        </a>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            <?php endif; ?>
          </div>
        </div>
      <?php endif; ?>
    </div>

  <?php elseif ($isLoggedIn): ?>
    <div class="empty-learnings fade-up">
      <div class="empty-learnings-icon">👤</div>
      <h2>Student Access Only</h2>
      <p>My Learnings displays courses enrolled by student accounts. You are currently logged in as a <?= e(ucfirst($user['role'] ?? 'user')) ?>.</p>
      <a href="dashboard.php" class="empty-btn">Go to Dashboard →</a>
    </div>

  <?php else: ?>
    <div class="empty-learnings fade-up">
      <div class="empty-learnings-icon">🔑</div>
      <h2>Please Log In</h2>
      <p>Sign in with your student account to access your enrolled courses, track progress, and continue learning.</p>
      <div style="display:flex; gap:1rem; justify-content:center; flex-wrap:wrap;">
        <a href="login.php?redirect=pages/my-learnings.php" class="empty-btn">Log In →</a>
        <a href="courses.php" class="empty-btn" style="background:transparent; border:1px solid var(--border); color:var(--ink);">Explore Courses</a>
      </div>
    </div>
  <?php endif; ?>
</div>

<script src="../assets/js/script.js"></script>
<script>
function filterLearnings(status, btn) {
  document.querySelectorAll('#learningsTabs .learnings-tab').forEach(function(t) {
    t.classList.remove('active');
  });
  if (btn) btn.classList.add('active');

  var grid = document.getElementById('learningsGrid');
  var certTable = document.getElementById('certificatesTableWrap');

  if (status === 'certificates') {
    if (grid) grid.style.display = 'none';
    if (certTable) certTable.style.display = 'block';
  } else {
    if (certTable) certTable.style.display = 'none';
    if (grid) grid.style.display = 'grid';

    var cards = document.querySelectorAll('#learningsGrid .learning-card');
    cards.forEach(function(card) {
      if (status === 'all' || card.dataset.status === status) {
        card.classList.remove('is-hidden');
      } else {
        card.classList.add('is-hidden');
      }
    });
  }
}

document.addEventListener('DOMContentLoaded', function() {
  var urlParams = new URLSearchParams(window.location.search);
  var filterParam = urlParams.get('filter') || urlParams.get('tab');
  if (filterParam) {
    var targetBtn = document.querySelector('#learningsTabs .learnings-tab[onclick*="' + filterParam + '"]');
    if (targetBtn) {
      filterLearnings(filterParam, targetBtn);
    }
  }
});
</script>
</body>
</html>
