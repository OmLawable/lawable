<?php
require_once __DIR__ . '/../backend/includes/functions.php';
start_secure_session();
$user = current_user();
$isLoggedIn = $user !== null;
$isAdmin = $isLoggedIn && ($user['role'] ?? '') === 'admin';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Courses — Lawable</title>
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600;700;800&family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet" />
  <link rel="stylesheet" href="../assets/css/lawable.css" />
</head>
<body>
<div class="cursor-glow" id="cursorGlow"></div>
<div class="progress-bar" id="progressBar"></div>
<button class="back-top" id="backTop" onclick="window.scrollTo({top:0,behavior:'smooth'})">↑</button>

<nav id="navbar">
  <a href="<?= $isAdmin ? '../pages/admin-dashboard.php' : ($isLoggedIn ? '../home.php' : '../index.html') ?>" class="nav-logo">Law<span>able</span></a>
  <ul class="nav-links">
    <li><a href="offerings.php">Offerings</a></li>
    <li><a href="courses.php" class="active">Courses</a></li>
    <li><a href="about.php">About</a></li>
    <li><a href="contact.php">Contact</a></li>
    <?php if ($isLoggedIn): ?>
    <?php if (!$isAdmin): ?>
    <li class="nav-profile-item">
      <a href="../edit-profile.php" class="nav-profile" aria-label="Edit profile">
        <span aria-hidden="true">👤</span>
      </a>
    </li>
    <?php endif; ?>
    <li><a href="../backend/logout.php" class="nav-cta">Log out</a></li>
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
  <button class="nav-hamburger" id="hamburger" aria-label="Menu">
    <span></span><span></span><span></span>
  </button>
</nav>

<nav class="nav-drawer" id="drawer">
  <a href="offerings.php" onclick="closeDrawer()">Offerings</a>
  <a href="courses.php" onclick="closeDrawer()">Courses</a>
  <a href="about.php" onclick="closeDrawer()">About</a>
  <a href="contact.php" onclick="closeDrawer()">Contact</a>
  <?php if ($isLoggedIn): ?>
  <?php if (!$isAdmin): ?>
  <a href="../edit-profile.php" onclick="closeDrawer()">Edit profile</a>
  <?php endif; ?>
  <a href="../backend/logout.php" class="drawer-cta">Log out</a>
  <?php else: ?>
  <a href="login.php" class="drawer-cta">Log in →</a>
  <?php endif; ?>
  <button class="theme-toggle drawer-theme-toggle" type="button" data-theme-toggle aria-label="Switch to dark theme" aria-pressed="false">
    <span class="theme-toggle-icon" aria-hidden="true">D</span>
    <span class="theme-toggle-text">Dark theme</span>
  </button>
</nav>

<section class="hero" style="min-height:70vh;">
  <div class="hero-bg"></div>
  <div class="hero-eyebrow">Course Catalog</div>
  <h1>Learn law with concise, practical courses</h1>
  <p class="hero-sub">Pick a course that fits your pace and goals.</p>
</section>

<section>
  <div class="courses-grid">
    <div class="course-card fade-up">
      <div class="course-thumb">⚖️<span class="course-badge">Popular</span></div>
      <div class="course-body">
        <div class="course-title">Constitutional Law Fundamentals</div>
        <div class="course-meta"><span>📚 12 Modules</span><span>⏱ 8 hrs</span><span>⭐ 4.8</span></div>
        <div class="course-footer"><a href="#" class="course-enroll">Enroll →</a></div>
      </div>
    </div>
    <div class="course-card fade-up delay-1">
      <div class="course-thumb" style="background:#E8F4FD;">🏢<span class="course-badge">New</span></div>
      <div class="course-body">
        <div class="course-title">Corporate Law & Governance</div>
        <div class="course-meta"><span>📚 9 Modules</span><span>⏱ 6 hrs</span><span>⭐ 4.7</span></div>
        <div class="course-footer"><a href="#" class="course-enroll">Enroll →</a></div>
      </div>
    </div>
    <div class="course-card fade-up delay-2">
      <div class="course-thumb" style="background:#F0FDF4;">📋<span class="course-badge free">Free</span></div>
      <div class="course-body">
        <div class="course-title">Introduction to Indian Legal System</div>
        <div class="course-meta"><span>📚 5 Modules</span><span>⏱ 3 hrs</span><span>⭐ 4.9</span></div>
        <div class="course-footer"><a href="#" class="course-enroll">Start →</a></div>
      </div>
    </div>
  </div>
</section>

<script src="../assets/js/script.js"></script>
</body>
</html>
