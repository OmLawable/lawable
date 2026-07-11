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
  <title>About — Lawable</title>
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
    <li><a href="courses.php">Courses</a></li>
    <li><a href="about.php" class="active">About</a></li>
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
  <div class="hero-eyebrow">About Lawable</div>
  <h1>Transforming legal education through clarity and access</h1>
  <p class="hero-sub">We believe high-quality legal learning should be practical, modern, and available to everyone.</p>
</section>

<section id="about">
  <div class="fade-left">
    <h2 class="section-title">Built to make law accessible to all</h2>
    <p class="section-body">Lawable combines expert instruction, flexible learning formats, and strong student support to make law education more approachable and effective.</p>
  </div>
  <div class="about-right fade-right">
    <div class="about-feature">
      <div class="about-feature-icon">🌐</div>
      <div>
        <div class="about-feature-title">Accessible learning</div>
        <p class="about-feature-desc">Designed for students, professionals, and institutions across India and beyond.</p>
      </div>
    </div>
    <div class="about-feature">
      <div class="about-feature-icon">🎯</div>
      <div>
        <div class="about-feature-title">Community-first approach</div>
        <p class="about-feature-desc">We focus on clear outcomes, support, and practical understanding rather than rote memorization.</p>
      </div>
    </div>
  </div>
</section>

<script src="../assets/js/script.js"></script>
</body>
</html>
