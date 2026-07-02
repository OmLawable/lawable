<?php
require_once __DIR__ . '/backend/includes/functions.php';
start_secure_session();

if (!is_logged_in()) {
    redirect('pages/login.php');
}

$user = current_user();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Lawable — Transforming Legal Education</title>
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600;700;800&family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet" />
  <link rel="stylesheet" href="assets/css/lawable.css" />
</head>
<body>
<div class="cursor-glow" id="cursorGlow"></div>
<div class="progress-bar" id="progressBar"></div>
<button class="back-top" id="backTop" onclick="window.scrollTo({top:0,behavior:'smooth'})">↑</button>

<nav id="navbar">
  <a href="home.php" class="nav-logo">Law<span>able</span></a>
  <ul class="nav-links">
    <li><a href="pages/offerings.html">Offerings</a></li>
    <li><a href="pages/courses.html">Courses</a></li>
    <li><a href="pages/about.html">About</a></li>
    <li><a href="pages/contact.html">Contact</a></li>
    <li><a href="backend/logout.php" class="nav-cta">Log out</a></li>
  </ul>
  <button class="nav-hamburger" id="hamburger" aria-label="Menu">
    <span></span><span></span><span></span>
  </button>
</nav>

<nav class="nav-drawer" id="drawer">
  <a href="pages/offerings.html" onclick="closeDrawer()">Offerings</a>
  <a href="pages/courses.html" onclick="closeDrawer()">Courses</a>
  <a href="pages/about.html" onclick="closeDrawer()">About</a>
  <a href="pages/contact.html" onclick="closeDrawer()">Contact</a>
  <a href="backend/logout.php" class="drawer-cta">Log out</a>
</nav>

<section class="hero" id="home">
  <div class="hero-bg"></div>
  <div class="hero-deco">
    <span>⚖</span><span>📜</span><span>🏛</span><span>📖</span>
  </div>

  <div class="hero-eyebrow">Legal Education Platform — India</div>
  <h1>
    Welcome back,<br>
    <em><?= htmlspecialchars($user['name'], ENT_QUOTES, 'UTF-8') ?></em>
  </h1>
  <p class="hero-sub">
    A modern learning experience for students, professionals, and institutions.
  </p>
  <div class="hero-actions">
    <a href="pages/courses.html" class="btn-primary">Explore Courses →</a>
    <a href="pages/offerings.html" class="btn-ghost">See Offerings</a>
  </div>
</section>

<section class="landing-links">
  <div class="landing-card fade-up">
    <h3>Offerings</h3>
    <p>Discover the programs and services built for different learning needs.</p>
    <a href="pages/offerings.html">View offerings →</a>
  </div>
  <div class="landing-card fade-up delay-1">
    <h3>Courses</h3>
    <p>Browse practical, expert-led courses for beginners and advanced learners.</p>
    <a href="pages/courses.html">Explore courses →</a>
  </div>
  <div class="landing-card fade-up delay-2">
    <h3>About</h3>
    <p>Learn more about Lawable’s mission and approach to legal education.</p>
    <a href="pages/about.html">Read about us →</a>
  </div>
  <div class="landing-card fade-up delay-3">
    <h3>Contact</h3>
    <p>Get in touch for admissions, partnerships, or general enquiries.</p>
    <a href="pages/contact.html">Contact us →</a>
  </div>
</section>

<footer style="padding:2rem 5% 3rem; color:var(--ink-soft);">
  <p>© 2026 Lawable. Built for modern legal education.</p>
</footer>

<script src="assets/js/script.js"></script>
</body>
</html>
