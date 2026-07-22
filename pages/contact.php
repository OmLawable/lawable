<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/functions.php';
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
  <title>Contact — Lawable</title>
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600;700;800&family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet" />
  <link rel="stylesheet" href="../assets/css/lawable.css?v=1.4" />
  <style>
    /* Styling to complement lawable.css and implement reference layout structure */
    .contact-hero {
      padding: 6.5rem 5% 3.5rem;
      background: var(--page-bg);
      text-align: center;
      position: relative;
    }
    .contact-hero-content {
      max-width: 700px;
      margin: 0 auto;
    }
    .contact-hero h1 {
      font-family: var(--display);
      font-size: clamp(2.25rem, 5vw, 3.5rem);
      font-weight: 700;
      color: var(--ink);
      margin-bottom: 1.25rem;
      line-height: 1.25;
      letter-spacing: -0.01em;
    }
    .contact-hero p {
      font-size: 1.05rem;
      line-height: 1.6;
      color: var(--ink-soft);
      font-family: var(--body);
    }

    .contact-layout {
      display: grid;
      grid-template-columns: 0.9fr 1.10fr;
      gap: 3.5rem;
      max-width: 1200px;
      margin: 0 auto;
      padding: 0 5% 6rem;
      position: relative;
      z-index: 2;
    }

    /* Left Column: Info blocks */
    .contact-info-list {
      display: flex;
      flex-direction: column;
      gap: 2.25rem;
      justify-content: center;
    }
    .info-block {
      display: flex;
      align-items: flex-start;
      gap: 1.25rem;
    }
    .info-icon-wrapper {
      width: 52px;
      height: 52px;
      border-radius: 12px;
      background: rgba(201, 147, 58, 0.12); /* light gold */
      display: flex;
      align-items: center;
      justify-content: center;
      color: var(--gold-dk);
      flex-shrink: 0;
      transition: background 0.25s, transform 0.25s;
    }
    .info-block:hover .info-icon-wrapper {
      background: var(--gold);
      color: var(--white);
      transform: translateY(-2px);
    }
    .info-text-wrapper {
      display: flex;
      flex-direction: column;
      gap: 0.25rem;
    }
    .info-title {
      font-family: var(--body);
      font-size: 1.1rem;
      font-weight: 700;
      color: var(--ink);
    }
    .info-desc {
      font-size: 0.88rem;
      color: var(--ink-soft);
      line-height: 1.45;
    }
    .info-detail {
      font-size: 0.95rem;
      font-weight: 600;
      color: var(--gold-dk);
      margin-top: 0.15rem;
      text-decoration: none;
      transition: color 0.2s;
    }
    .info-detail:hover {
      color: var(--gold);
    }

    /* Right Column: Form card */
    .contact-card {
      background: var(--white);
      border: 1px solid var(--border);
      border-radius: 20px;
      padding: 2.5rem;
      box-shadow: var(--shadow);
      transition: box-shadow 0.3s, border-color 0.3s;
    }
    .contact-card:hover {
      box-shadow: var(--shadow-lg);
    }

    .form-group-row {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 1.25rem;
      margin-bottom: 1.5rem;
    }
    .form-group {
      display: flex;
      flex-direction: column;
      gap: 0.5rem;
      margin-bottom: 1.5rem;
    }
    .form-group.last {
      margin-bottom: 2rem;
    }
    .form-group label {
      font-size: 0.85rem;
      font-weight: 600;
      color: var(--ink-mid);
    }
    .form-group input,
    .form-group select,
    .form-group textarea {
      width: 100%;
      min-height: 48px;
      border: 2px solid transparent;
      border-radius: 10px;
      padding: 0.8rem 1rem;
      background: #F3F4F6;
      color: var(--ink);
      font-family: var(--body);
      font-size: 0.92rem;
      box-sizing: border-box;
      transition: border-color .2s, background .2s, box-shadow .2s;
    }
    .form-group textarea {
      min-height: 140px;
      resize: vertical;
    }
    .form-group input:focus,
    .form-group select:focus,
    .form-group textarea:focus {
      outline: none;
      border-color: var(--gold);
      background: var(--white);
      box-shadow: 0 0 0 3px rgba(201, 147, 58, 0.12);
    }
    .form-group input[disabled] {
      background: var(--border);
      color: var(--ink-soft);
      cursor: not-allowed;
    }

    /* Form Action button */
    .btn-submit {
      background: var(--ink);
      color: var(--white);
      border: none;
      padding: 0.9rem 2rem;
      border-radius: 9999px;
      font-family: var(--body);
      font-size: 0.95rem;
      font-weight: 600;
      cursor: pointer;
      display: inline-flex;
      align-items: center;
      gap: 0.6rem;
      transition: background 0.2s, transform 0.15s;
    }
    .btn-submit:hover {
      background: var(--gold-dk);
      transform: translateY(-1px);
    }
    .btn-submit svg {
      width: 16px;
      height: 16px;
      fill: currentColor;
      transition: transform 0.2s;
    }
    .btn-submit:hover svg {
      transform: translateX(4px);
    }

    /* Not Logged In CTA Panel */
    .cta-card-content {
      text-align: center;
      padding: 1.5rem 0.5rem;
      display: flex;
      flex-direction: column;
      align-items: center;
      gap: 1.25rem;
    }
    .cta-icon {
      font-size: 2.5rem;
      color: var(--gold-dk);
      margin-bottom: 0.25rem;
    }
    .cta-title {
      font-family: var(--display);
      font-size: 1.6rem;
      font-weight: 700;
      color: var(--ink);
    }
    .cta-text {
      font-size: 0.95rem;
      color: var(--ink-soft);
      line-height: 1.55;
      max-width: 360px;
    }
    .btn-cta-login {
      background: var(--ink);
      color: var(--white);
      text-decoration: none;
      padding: 0.85rem 2rem;
      border-radius: 9999px;
      font-weight: 600;
      font-size: 0.92rem;
      transition: background 0.2s, transform 0.15s;
      margin-top: 0.5rem;
    }
    .btn-cta-login:hover {
      background: var(--gold-dk);
      transform: translateY(-1px);
    }

    /* Dark theme overrides specifically for filled grey inputs */
    body.dark-theme .form-group input,
    body.dark-theme .form-group select,
    body.dark-theme .form-group textarea {
      background: #1E293B;
    }
    body.dark-theme .form-group input:focus,
    body.dark-theme .form-group select:focus,
    body.dark-theme .form-group textarea:focus {
      background: #111827;
    }

    @media (max-width: 900px) {
      .contact-layout {
        grid-template-columns: 1fr;
        gap: 4rem;
      }
      .contact-info-list {
        order: 2;
      }
      .contact-card {
        order: 1;
      }
      .form-group-row {
        grid-template-columns: 1fr;
      }
    }
    /* Toast styles */
    #toast {
      position: fixed;
      top: 1.5rem;
      right: 1.5rem;
      max-width: 380px;
      padding: 1rem 1.25rem;
      border-radius: 14px;
      background: var(--white);
      box-shadow: var(--shadow-lg);
      border-left: 4px solid var(--gold);
      font-size: 0.9rem;
      z-index: 9999;
      display: none;
      animation: slideIn .3s ease;
      font-family: var(--body);
    }
    #toast.error { border-left-color: #C0604A; }
    #toast.success { border-left-color: #4A7C59; }
    @keyframes slideIn { from { opacity: 0; transform: translateX(40px); } to { opacity: 1; transform: translateX(0); } }
  </style>
</head>
<body>
<div id="toast"></div>
<div class="cursor-glow" id="cursorGlow"></div>
<div class="progress-bar" id="progressBar"></div>
<button class="back-top" id="backTop" onclick="window.scrollTo({top:0,behavior:'smooth'})">↑</button>

<nav id="navbar">
  <a href="<?= $isAdmin ? 'admin/dashboard.php' : ($isLoggedIn ? 'dashboard.php' : '../index.php') ?>" class="nav-logo">Law<span>able</span></a>
  <ul class="nav-links">
    <li><a href="offerings.php">Offerings</a></li>
    <li class="nav-dropdown">
      <a href="courses.php" class="nav-dropdown-toggle">
        Courses <span class="nav-dropdown-chevron">▼</span>
      </a>
      <div class="nav-dropdown-menu">
        <a href="courses.php">Explore Courses</a>
        <a href="my-learnings.php">My Learnings</a>
      </div>
    </li>
    <li><a href="about.php">About</a></li>
    <li><a href="contact.php" class="active">Contact</a></li>
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
  <button class="nav-hamburger" id="hamburger" aria-label="Menu">
    <span></span><span></span><span></span>
  </button>
</nav>

<nav class="nav-drawer" id="drawer">
  <a href="offerings.php" onclick="closeDrawer()">Offerings</a>
  <a href="courses.php" onclick="closeDrawer()">Explore Courses</a>
  <a href="my-learnings.php" onclick="closeDrawer()">My Learnings</a>
  <a href="about.php" onclick="closeDrawer()">About</a>
  <a href="contact.php" onclick="closeDrawer()">Contact</a>
  <?php if ($isLoggedIn): ?>
  <?php if (!$isAdmin): ?>
  <a href="student/edit-profile.php" onclick="closeDrawer()">Edit profile</a>
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

<section class="contact-hero fade-up">
  <div class="contact-hero-content">
    <h1>Get in Touch</h1>
    <p>We are here to answer any questions you may have about our professional services. Reach out to us and we'll respond as soon as we can.</p>
  </div>
</section>

<div class="contact-layout">

  <!-- LEFT column: contact info blocks -->
  <div class="contact-info-list fade-up">
    <!-- Block 1: Email -->
    <div class="info-block">
      <div class="info-icon-wrapper">
        <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" fill="currentColor" viewBox="0 0 24 24">
          <path d="M20 4H4c-1.1 0-1.99.9-1.99 2L2 18c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2zm0 4l-8 5-8-5V6l8 5 8-5v2z"/>
        </svg>
      </div>
      <div class="info-text-wrapper">
        <span class="info-title">Email Us</span>
        <span class="info-desc">Our friendly team is here to help.</span>
        <a class="info-detail" href="mailto:contact@lawable.in">contact@lawable.in</a>
      </div>
    </div>

    <!-- Block 2: Call -->
    <div class="info-block">
      <div class="info-icon-wrapper">
        <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" fill="currentColor" viewBox="0 0 24 24">
          <path d="M6.62 10.79c1.44 2.83 3.76 5.14 6.59 6.59l2.2-2.2c.27-.27.67-.36 1.02-.24 1.12.37 2.33.57 3.57.57.55 0 1 .45 1 1V20c0 .55-.45 1-1 1-9.39 0-17-7.61-17-17 0-.55.45-1 1-1h3.5c.55 0 1 .45 1 1 0 1.25.2 2.45.57 3.57.11.35.03.74-.25 1.02l-2.2 2.2z"/>
        </svg>
      </div>
      <div class="info-text-wrapper">
        <span class="info-title">Call Us</span>
        <span class="info-desc">Mon-Fri from 10am to 5pm.</span>
        <a class="info-detail" href="tel:+912348373461">(+91) 234-837-3461</a>
      </div>
    </div>

    <!-- Block 3: Visit -->
    <div class="info-block">
      <div class="info-icon-wrapper">
        <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" fill="currentColor" viewBox="0 0 24 24">
          <path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7zm0 9.5c-1.38 0-2.5-1.12-2.5-2.5s1.12-2.5 2.5-2.5 2.5 1.12 2.5 2.5-1.12 2.5-2.5 2.5z"/>
        </svg>
      </div>
      <div class="info-text-wrapper">
        <span class="info-title">Visit Us</span>
        <span class="info-desc">Come say hello at our local office.</span>
        <span class="info-detail">Ghansoli, Navi Mumbai</span>
      </div>
    </div>
  </div>

  <!-- RIGHT column: form card or login prompt -->
  <div class="contact-card fade-up">
    <?php if ($isLoggedIn): ?>
      <form id="contactForm" novalidate>
        <div class="form-group-row">
          <div class="form-group">
            <label for="fullName">Full Name</label>
            <input id="fullName" type="text" value="<?= e($user['name'] ?? '') ?>" disabled />
          </div>
          <div class="form-group">
            <label for="emailAddress">Email Address</label>
            <input id="emailAddress" type="email" value="<?= e($user['email'] ?? '') ?>" disabled />
          </div>
        </div>

        <div class="form-group">
          <label for="subject">Subject</label>
          <select id="subject" required>
            <option value="">Select subject option...</option>
            <option value="General Inquiry">General Inquiry</option>
            <option value="Course Question / Study Material Support">Course Question / Study Material Support</option>
            <option value="Admissions & Mentorship Support">Admissions & Mentorship Support</option>
            <option value="Technical Support (Website/App issues)">Technical Support (Website/App issues)</option>
            <option value="Partnership & Organization Inquiry">Partnership & Organization Inquiry</option>
          </select>
        </div>

        <div class="form-group last">
          <label for="message">Message</label>
          <textarea id="message" placeholder="How can we help you?" required></textarea>
        </div>

        <button type="submit" class="btn-submit" id="submitBtn">
          <span>Send Message</span>
          <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">
            <path d="M2.01 21L23 12 2.01 3 2 10l15 2-15 2z"/>
          </svg>
        </button>
      </form>
    <?php else: ?>
      <div class="cta-card-content">
        <div class="cta-icon">✉️</div>
        <div class="cta-title">Start a Conversation</div>
        <p class="cta-text">Please sign in to your Lawable account to submit a message to our support and admin team.</p>
        <a href="login.php" class="btn-cta-login">Sign In to Continue</a>
      </div>
    <?php endif; ?>
  </div>

</div>

<script src="../assets/js/script.js"></script>
<script>
  const toast = document.getElementById('toast');
  
  function showToast(message, type = 'error') {
    toast.textContent = message;
    toast.className = type;
    toast.style.display = 'block';
    clearTimeout(toast._timer);
    toast._timer = setTimeout(() => { toast.style.display = 'none'; }, 4500);
  }

  const form = document.getElementById('contactForm');
  if (form) {
    const btn = document.getElementById('submitBtn');
    form.addEventListener('submit', async (e) => {
      e.preventDefault();

      const subject = document.getElementById('subject').value;
      const message = document.getElementById('message').value.trim();

      if (!subject) {
        showToast('Please select a subject for your inquiry.', 'error');
        return;
      }
      if (!message) {
        showToast('Please enter your message.', 'error');
        return;
      }

      btn.disabled = true;
      const originalText = btn.querySelector('span').textContent;
      btn.querySelector('span').textContent = 'Sending...';

      try {
        const res = await fetch('../api/submit_contact.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ subject, message })
        });
        const data = await res.json();

        if (data.success) {
          showToast(data.message, 'success');
          // Reset form fields
          document.getElementById('subject').value = '';
          document.getElementById('message').value = '';
        } else {
          showToast(data.message || 'Failed to send message.', 'error');
        }
      } catch (err) {
        showToast('A network error occurred. Please try again.', 'error');
      } finally {
        btn.disabled = false;
        btn.querySelector('span').textContent = originalText;
      }
    });
  }
</script>
</body>
</html>
