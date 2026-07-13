<?php
require_once __DIR__ . '/../includes/functions.php';
start_secure_session();
$user = current_user();
$isLoggedIn = $user !== null;
$isAdmin = $isLoggedIn && ($user['role'] ?? '') === 'admin';
$isStudent = $isLoggedIn && ($user['role'] ?? '') === 'user';

$pdo = get_pdo();

// Fetch all published courses
$stmt = $pdo->query("SELECT id, title, description, category, difficulty, price, rating, status, created_at FROM courses WHERE status='published' ORDER BY category, title");
$all_courses = $stmt->fetchAll();

// Get unique categories
$categories = [];
foreach ($all_courses as $c) {
    $cat = $c['category'] ?? 'Other';
    if (!in_array($cat, $categories)) $categories[] = $cat;
}

// Get student enrollments if logged in
$enrolled_ids = [];
if ($isStudent) {
    $stmt = $pdo->prepare("SELECT course_id FROM course_enrollments WHERE student_id = :sid");
    $stmt->execute([':sid' => (int) $user['id']]);
    $enrolled_ids = array_column($stmt->fetchAll(), 'course_id');
}
$isEnrolled = function($courseId) use ($enrolled_ids) {
    return in_array((int) $courseId, $enrolled_ids);
};

function diffLabel(string $diff): string {
    return match($diff) {
        'beginner' => 'Beginner',
        'intermediate' => 'Intermediate',
        'advanced' => 'Advanced',
        'all-levels' => 'All Levels',
        default => $diff,
    };
}
function diffColor(string $diff): string {
    return match($diff) {
        'beginner' => '#16a34a',
        'intermediate' => '#C9933A',
        'advanced' => '#DC2626',
        'all-levels' => '#2563EB',
        default => '#6B7280',
    };
}
function diffBg(string $diff): string {
    return match($diff) {
        'beginner' => '#DCFCE7',
        'intermediate' => '#F4E4C3',
        'advanced' => '#FEE2E2',
        'all-levels' => '#DBEAFE',
        default => '#F3F4F6',
    };
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Explore Courses — Lawable</title>
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600;700;800&family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet" />
  <link rel="stylesheet" href="../assets/css/lawable.css" />
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
      --yellow: #EAB308;
      --yellow-bg: #FEF9C3;
      --red: #DC2626;
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
      --yellow: #EAB308;
      --yellow-bg: #422006;
      --red: #EF4444;
      --red-bg: #450A0A;
      --blue: #60A5FA;
      --blue-bg: #1E3A5F;
      --shadow: 0 4px 24px rgba(0,0,0,0.40);
      --shadow-lg: 0 12px 40px rgba(0,0,0,0.50);
    }

    body { background: var(--page-bg); font-family: 'Inter', sans-serif; color: var(--ink); min-height: 100vh; }

    .courses-page { padding-top: calc(var(--nav-h) + 24px); min-height: 100vh; }

    /* ─── Hero ─── */
    .courses-hero {
      text-align: center;
      padding: 3rem 2rem 2.5rem;
      max-width: 900px;
      margin: 0 auto;
    }
    .courses-hero h1 {
      font-family: 'Playfair Display', serif;
      font-size: clamp(2rem, 4vw, 2.8rem);
      font-weight: 700;
      color: var(--ink);
      line-height: 1.15;
      margin-bottom: 0.75rem;
    }
    .courses-hero h1 em { font-style: normal; color: var(--gold); }
    .courses-hero p {
      color: var(--ink-soft);
      font-size: 1.05rem;
      max-width: 600px;
      margin: 0 auto;
      line-height: 1.6;
    }

    /* ─── Search & Filter Bar ─── */
    .filter-bar {
      display: flex;
      align-items: center;
      gap: 0.75rem;
      flex-wrap: wrap;
      padding: 0 2rem;
      margin: 0 auto 1.5rem;
      max-width: 1240px;
    }
    .filter-bar .search-wrap {
      flex: 1;
      min-width: 200px;
      position: relative;
    }
    .filter-bar .search-wrap input {
      width: 100%;
      padding: 0.7rem 1rem 0.7rem 2.4rem;
      border: 1.5px solid var(--border);
      border-radius: var(--radius);
      background: var(--white);
      color: var(--ink);
      font-family: 'Inter', sans-serif;
      font-size: 0.9rem;
      transition: border-color .2s, box-shadow .2s;
    }
    .filter-bar .search-wrap input:focus {
      outline: none;
      border-color: var(--gold);
      box-shadow: 0 0 0 3px rgba(201,147,58,0.12);
    }
    .filter-bar .search-wrap .search-icon {
      position: absolute;
      left: 0.85rem;
      top: 50%;
      transform: translateY(-50%);
      color: var(--ink-soft);
      font-size: 0.9rem;
      pointer-events: none;
    }
    .filter-tabs {
      display: flex;
      gap: 0.4rem;
      flex-wrap: wrap;
      padding: 0 2rem;
      margin: 0 auto 1.5rem;
      max-width: 1240px;
    }
    .filter-tab {
      padding: 0.5rem 1.2rem;
      border-radius: 20px;
      font-size: 0.82rem;
      font-weight: 600;
      font-family: 'Inter', sans-serif;
      border: 1.5px solid var(--border);
      background: var(--white);
      color: var(--ink-mid);
      cursor: pointer;
      transition: all .2s;
    }
    .filter-tab:hover { border-color: var(--gold); color: var(--gold); }
    .filter-tab.active {
      background: var(--ink);
      color: var(--white);
      border-color: var(--ink);
    }

    /* ─── Course Grid ─── */
    .courses-container {
      max-width: 1240px;
      margin: 0 auto;
      padding: 0 2rem 3rem;
    }
    .courses-grid {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
      gap: 1.25rem;
    }
    .course-card {
      background: var(--white);
      border: 1px solid var(--border);
      border-radius: var(--radius-lg);
      overflow: hidden;
      transition: box-shadow .3s, transform .3s;
      cursor: default;
      position: relative;
    }
    .course-card:hover {
      box-shadow: var(--shadow-lg);
      transform: translateY(-4px);
    }
    .course-card.is-hidden { display: none; }

    /* Thumbnail with category gradient */
    .course-thumb {
      height: 110px;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 2.5rem;
      position: relative;
      overflow: hidden;
    }
    .course-thumb-tech { background: linear-gradient(135deg, #1E3A5F 0%, #2D5B8A 100%); }
    .course-thumb-law { background: linear-gradient(135deg, #7C3AED 0%, #A78BFA 100%); }
    .course-thumb-business { background: linear-gradient(135deg, #065F46 0%, #34D399 100%); }
    .course-thumb-personal { background: linear-gradient(135deg, #92400E 0%, #FBBF24 100%); }
    .course-thumb-default { background: linear-gradient(135deg, #6B7280 0%, #9CA3AF 100%); }
    .course-thumb-icon { position: relative; z-index: 1; filter: drop-shadow(0 2px 8px rgba(0,0,0,0.2)); }
    .course-thumb::after {
      content: '';
      position: absolute; inset: 0;
      background: radial-gradient(circle at 70% 30%, rgba(255,255,255,0.10) 0%, transparent 60%);
    }
    .course-badge {
      position: absolute; top: 10px; left: 10px; z-index: 2;
      font-size: 0.62rem; font-weight: 700; letter-spacing: 0.1em;
      text-transform: uppercase; padding: 0.25rem 0.6rem; border-radius: 4px;
    }
    .course-badge.free { background: #065F46; color: #A7F3D0; }
    .course-badge.popular { background: rgba(255,255,255,0.15); color: #fff; backdrop-filter: blur(4px); }

    .course-body { padding: 1rem 1.25rem 1.25rem; }
    .course-category {
      font-size: 0.65rem;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: 0.1em;
      color: var(--ink-soft);
      margin-bottom: 0.3rem;
    }
    .course-title {
      font-family: 'Playfair Display', serif;
      font-size: 1rem;
      font-weight: 700;
      color: var(--ink);
      line-height: 1.3;
      margin-bottom: 0.5rem;
      display: -webkit-box;
      -webkit-line-clamp: 2;
      -webkit-box-orient: vertical;
      overflow: hidden;
    }
    .course-desc {
      font-size: 0.78rem;
      color: var(--ink-soft);
      line-height: 1.6;
      margin-bottom: 0.7rem;
      display: -webkit-box;
      -webkit-line-clamp: 2;
      -webkit-box-orient: vertical;
      overflow: hidden;
    }
    .course-meta-row {
      display: flex;
      align-items: center;
      justify-content: space-between;
      margin-bottom: 0.75rem;
    }
    .course-rating {
      font-size: 0.78rem;
      font-weight: 600;
      color: var(--ink-mid);
    }
    .course-rating .stars { color: var(--gold); letter-spacing: 0.05em; }
    .course-diff {
      font-size: 0.65rem;
      font-weight: 600;
      padding: 0.2rem 0.65rem;
      border-radius: 10px;
      white-space: nowrap;
    }
    .course-footer-line {
      display: flex;
      justify-content: space-between;
      align-items: center;
      border-top: 1px solid var(--border);
      padding-top: 0.75rem;
    }
    .course-price {
      font-family: 'Playfair Display', serif;
      font-size: 1rem;
      font-weight: 700;
      color: var(--ink);
    }
    .course-price .free-label { color: var(--green); font-size: 0.85rem; }
    .course-enroll-btn {
      font-size: 0.78rem;
      font-weight: 700;
      color: var(--white);
      background: var(--gold);
      padding: 0.45rem 1.1rem;
      border-radius: 6px;
      text-decoration: none;
      display: inline-flex;
      align-items: center;
      gap: 0.3rem;
      transition: background .2s, transform .15s;
      border: none;
      cursor: pointer;
      font-family: 'Inter', sans-serif;
    }
    .course-enroll-btn:hover {
      background: var(--gold-dk);
      transform: translateY(-1px);
    }
    .course-enroll-btn.enrolled {
      background: var(--green-bg);
      color: var(--green);
      cursor: default;
    }
    .course-enroll-btn.enrolled:hover { background: var(--green-bg); transform: none; }

    .no-results {
      grid-column: 1 / -1;
      text-align: center;
      padding: 3rem 1rem;
      color: var(--ink-soft);
    }
    .no-results-icon { font-size: 2.5rem; margin-bottom: 0.75rem; }

    /* ─── Responsive ─── */
    @media (max-width: 768px) {
      .courses-hero { padding: 2rem 1.25rem 1.5rem; }
      .filter-bar, .filter-tabs { padding: 0 1rem; }
      .courses-container { padding: 0 1rem 2rem; }
      .courses-grid { grid-template-columns: 1fr; }
    }
  </style>
</head>
<body>
<div class="cursor-glow" id="cursorGlow"></div>
<div class="progress-bar" id="progressBar"></div>
<button class="back-top" id="backTop" onclick="window.scrollTo({top:0,behavior:'smooth'})">↑</button>

<nav id="navbar">
  <a href="<?= $isAdmin ? 'admin/dashboard.php' : ($isLoggedIn ? 'dashboard.php' : '../index.php') ?>" class="nav-logo">Law<span>able</span></a>
  <ul class="nav-links">
    <li><a href="offerings.php">Offerings</a></li>
    <li><a href="courses.php" class="active">Courses</a></li>
    <li><a href="about.php">About</a></li>
    <li><a href="contact.php">Contact</a></li>
    <?php if ($isLoggedIn): ?>
    <?php if (!$isAdmin): ?>
    <li class="nav-profile-item"><a href="student/edit-profile.php" class="nav-profile" aria-label="Edit profile"><span aria-hidden="true">👤</span></a></li>
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
  <a href="courses.php" onclick="closeDrawer()">Courses</a>
  <a href="about.php" onclick="closeDrawer()">About</a>
  <a href="contact.php" onclick="closeDrawer()">Contact</a>
  <?php if ($isLoggedIn): ?>
  <?php if (!$isAdmin): ?><a href="student/edit-profile.php" onclick="closeDrawer()">Edit profile</a><?php endif; ?>
  <a href="../api/logout.php" class="drawer-cta">Log out</a>
  <?php else: ?>
  <a href="login.php" class="drawer-cta">Log in →</a>
  <?php endif; ?>
  <button class="theme-toggle drawer-theme-toggle" type="button" data-theme-toggle aria-label="Switch to dark theme" aria-pressed="false">
    <span class="theme-toggle-icon" aria-hidden="true">D</span>
    <span class="theme-toggle-text">Dark theme</span>
  </button>
</nav>

<div class="courses-page">
  <div class="courses-hero fade-up">
    <h1>Explore courses that <em>build your future</em></h1>
    <p>From law and technology to business and personal growth — find the right course for your journey, at every level.</p>
  </div>

  <div class="filter-bar fade-up delay-1">
    <div class="search-wrap">
      <span class="search-icon">🔍</span>
      <input type="text" id="courseSearch" placeholder="Search courses..." oninput="filterCourses()" />
    </div>
  </div>

  <div class="filter-tabs fade-up delay-1" id="filterTabs">
    <button class="filter-tab active" data-cat="all" onclick="setCategory('all')">All</button>
    <?php foreach ($categories as $cat): ?>
    <button class="filter-tab" data-cat="<?= e($cat) ?>" onclick="setCategory('<?= e($cat) ?>')"><?= e($cat) ?></button>
    <?php endforeach; ?>
  </div>

  <div class="courses-container">
    <div class="courses-grid" id="coursesGrid">
      <?php foreach ($all_courses as $course):
        $cat = $course['category'] ?? 'Other';
        $price = (float) $course['price'];
        $rating = (float) ($course['rating'] ?? 4.5);
        $diff = $course['difficulty'] ?? 'all-levels';
        $enrolled = $isEnrolled((int) $course['id']);

        // Pick thumb class by category
        $thumbClass = 'course-thumb-default';
        $thumbIcon = '📘';
        if (str_contains($cat, 'Technology')) { $thumbClass = 'course-thumb-tech'; $thumbIcon = '💻'; }
        elseif (str_contains($cat, 'Law')) { $thumbClass = 'course-thumb-law'; $thumbIcon = '⚖️'; }
        elseif (str_contains($cat, 'Business')) { $thumbClass = 'course-thumb-business'; $thumbIcon = '📊'; }
        elseif (str_contains($cat, 'Personal')) { $thumbClass = 'course-thumb-personal'; $thumbIcon = '🌟'; }
      ?>
      <div class="course-card fade-up" data-category="<?= e($cat) ?>" data-title="<?= e(strtolower($course['title'])) ?>">
        <div class="course-thumb <?= $thumbClass ?>">
          <span class="course-thumb-icon"><?= $thumbIcon ?></span>
          <?php if ($price == 0): ?><span class="course-badge free">Free</span><?php endif; ?>
          <?php if ($rating >= 4.8): ?><span class="course-badge popular" style="right:10px;left:auto;">★ Popular</span><?php endif; ?>
        </div>
        <div class="course-body">
          <div class="course-category"><?= e($cat) ?></div>
          <div class="course-title"><?= e($course['title']) ?></div>
          <div class="course-desc"><?= e($course['description'] ?? '') ?></div>
          <div class="course-meta-row">
            <span class="course-rating"><span class="stars">★★★★★</span> <?= number_format($rating, 1) ?></span>
            <span class="course-diff" style="background:<?= diffBg($diff) ?>;color:<?= diffColor($diff) ?>"><?= diffLabel($diff) ?></span>
          </div>
          <div class="course-footer-line">
            <span class="course-price"><?= $price > 0 ? '₹' . number_format($price) : '<span class="free-label">Free</span>' ?></span>
            <span class="course-enroll-btn <?= $enrolled ? 'enrolled' : '' ?>" data-course-id="<?= (int) $course['id'] ?>" onclick="<?= $isStudent ? "enrollCourse(this, {$course['id']})" : ($isLoggedIn ? '' : "window.location='login.php'") ?>">
              <?= $enrolled ? '✓ Enrolled' : ($isLoggedIn ? 'Enroll →' : 'Join →') ?>
            </span>
          </div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</div>

<script src="../assets/js/script.js"></script>
<script>
(function() {
  'use strict';

  var activeCategory = 'all';
  var searchTerm = '';

  window.filterCourses = function() {
    searchTerm = (document.getElementById('courseSearch').value || '').toLowerCase().trim();
    applyFilters();
  };

  window.setCategory = function(cat) {
    activeCategory = cat;
    document.querySelectorAll('#filterTabs .filter-tab').forEach(function(btn) {
      btn.classList.toggle('active', btn.dataset.cat === cat);
    });
    applyFilters();
  };

  function applyFilters() {
    var cards = document.querySelectorAll('#coursesGrid .course-card');
    var visibleCount = 0;

    cards.forEach(function(card) {
      var catMatch = activeCategory === 'all' || card.dataset.category === activeCategory;
      var title = card.dataset.title || '';
      var searchMatch = searchTerm === '' || title.indexOf(searchTerm) !== -1;

      if (catMatch && searchMatch) {
        card.classList.remove('is-hidden');
        visibleCount++;
      } else {
        card.classList.add('is-hidden');
      }
    });
  }

  // Keyboard enter on search
  document.getElementById('courseSearch').addEventListener('keyup', function(e) {
    if (e.key === 'Enter') filterCourses();
  });

  // ── Enrollment via AJAX ──
  window.enrollCourse = function(btn, courseId) {
    if (btn.classList.contains('enrolled')) return;

    btn.textContent = '⏳';
    btn.style.pointerEvents = 'none';

    var xhr = new XMLHttpRequest();
    xhr.open('POST', '../api/enroll.php', true);
    xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
    xhr.onload = function() {
      if (xhr.status === 200) {
        try {
          var resp = JSON.parse(xhr.responseText);
          if (resp.success) {
            btn.classList.add('enrolled');
            btn.textContent = '✓ Enrolled';
            btn.style.pointerEvents = '';
            // Reload home page data if user goes there by updating count indicator
          } else {
            btn.textContent = 'Enroll →';
            btn.style.pointerEvents = '';
            alert(resp.message || 'Enrollment failed.');
          }
        } catch(e) {
          btn.textContent = 'Enroll →';
          btn.style.pointerEvents = '';
          alert('Something went wrong.');
        }
      } else {
        btn.textContent = 'Enroll →';
        btn.style.pointerEvents = '';
        alert('Server error. Try again.');
      }
    };
    xhr.onerror = function() {
      btn.textContent = 'Enroll →';
      btn.style.pointerEvents = '';
      alert('Network error.');
    };
    xhr.send('course_id=' + courseId);
  };
})();
</script>
</html>
