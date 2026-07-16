<?php

declare(strict_types=1);

require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/firestore.php';

start_secure_session();
$user = require_login('teacher');
$db = get_firestore();
$errors = [];
$success = '';

function fetch_teacher_profile(FirestoreClient $db, string $teacherUid): array
{
    $profile = $db->get('teachers', $teacherUid);
    if (!$profile) {
        throw new RuntimeException('Teacher account not found.');
    }

    return [
        'id'             => $profile['__id'] ?? $teacherUid,
        'name'           => $profile['name'] ?? '',
        'username'       => $profile['username'] ?? '',
        'email'          => $profile['email'] ?? '',
        'phone'          => $profile['phone'] ?? '',
        'bio'            => $profile['bio'] ?? '',
        'qualification'  => $profile['qualification'] ?? '',
        'specialization' => $profile['specialization'] ?? '',
        'experience'     => $profile['experience'] ?? '',
        'designation'    => $profile['designation'] ?? '',
        'headline'       => $profile['headline'] ?? '',
        'publicEmail'    => $profile['publicEmail'] ?? '',
        'avatar'         => $profile['avatar'] ?? 'avatar1.png',
        'date_of_birth'  => $profile['dateOfBirth'] ?? '',
        'gender'         => $profile['gender'] ?? '',
    ];
}

try {
    $profile = fetch_teacher_profile($db, (string) $user['id']);
} catch (Throwable $exception) {
    $profile = [
        'id'             => (string) $user['id'],
        'name'           => $user['name'] ?? '',
        'username'       => $user['username'] ?? '',
        'email'          => $user['email'] ?? '',
        'phone'          => $user['phone'] ?? '',
        'bio'            => '',
        'qualification'  => '',
        'specialization' => '',
        'experience'     => '',
        'designation'    => '',
        'headline'       => '',
        'publicEmail'    => '',
        'avatar'         => 'avatar1.png',
        'date_of_birth'  => '',
        'gender'         => '',
    ];
    $errors[] = 'Profile not found: ' . $exception->getMessage();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        verify_csrf_token($_POST['csrf_token'] ?? '');

        $name           = trim((string) ($_POST['name'] ?? ''));
        $username       = trim((string) ($_POST['username'] ?? ''));
        $email          = trim((string) ($_POST['email'] ?? ''));
        $phone          = trim((string) ($_POST['phone'] ?? ''));
        $bio            = trim((string) ($_POST['bio'] ?? ''));
        $qualification  = trim((string) ($_POST['qualification'] ?? ''));
        $specialization = trim((string) ($_POST['specialization'] ?? ''));
        $experience     = trim((string) ($_POST['experience'] ?? ''));
        $designation    = trim((string) ($_POST['designation'] ?? ''));
        $headline       = trim((string) ($_POST['headline'] ?? ''));
        $publicEmail    = trim((string) ($_POST['public_email'] ?? ''));
        $avatar         = trim((string) ($_POST['avatar'] ?? 'avatar1.png'));
        $dateOfBirth    = trim((string) ($_POST['date_of_birth'] ?? ''));
        $gender         = trim((string) ($_POST['gender'] ?? ''));

        if ($name === '') {
            throw new RuntimeException('Full name is required.');
        }

        // Validate username format
        if ($username === '' || strlen($username) < 3 || strlen($username) > 30 || !preg_match('/^[a-zA-Z0-9._-]+$/', $username)) {
            throw new RuntimeException('Username must be 3-30 characters, alphanumeric or special characters (._-).');
        }

        // Validate email format
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new RuntimeException('Please enter a valid email address.');
        }

        // Validate username uniqueness
        if ($username !== $profile['username']) {
            $matches = $db->query('teachers', [['username', 'EQUAL', $username]], 1);
            if (!empty($matches)) {
                throw new RuntimeException('This username is already taken.');
            }
            $studentMatches = $db->query('students', [['username', 'EQUAL', $username]], 1);
            if (!empty($studentMatches)) {
                throw new RuntimeException('This username is already taken.');
            }
            $orgMatches = $db->query('organizations', [['username', 'EQUAL', $username]], 1);
            if (!empty($orgMatches)) {
                throw new RuntimeException('This username is already taken.');
            }
        }

        // Validate email uniqueness
        if ($email !== $profile['email']) {
            $matches = $db->query('teachers', [['email', 'EQUAL', $email]], 1);
            if (!empty($matches)) {
                throw new RuntimeException('This email is already registered.');
            }
            $studentMatches = $db->query('students', [['email', 'EQUAL', $email]], 1);
            if (!empty($studentMatches)) {
                throw new RuntimeException('This email is already registered.');
            }
            $orgMatches = $db->query('organizations', [['email', 'EQUAL', $email]], 1);
            if (!empty($orgMatches)) {
                throw new RuntimeException('This email is already registered.');
            }
        }

        $teacherDoc = [
            'name'           => $name,
            'username'       => $username,
            'email'          => $email,
            'phone'          => $phone,
            'bio'            => $bio,
            'qualification'  => $qualification,
            'specialization' => $specialization,
            'experience'     => $experience,
            'designation'    => $designation,
            'headline'       => $headline,
            'publicEmail'    => $publicEmail,
            'avatar'         => $avatar,
            'dateOfBirth'    => $dateOfBirth,
            'gender'         => $gender,
            'updatedAt'      => FirestoreClient::now()
        ];

        // Merge existing fields like status, createdAt, etc.
        $original = $db->get('teachers', (string) $user['id']);
        if ($original) {
            $teacherDoc = array_merge($original, $teacherDoc);
        }

        $db->set('teachers', $teacherDoc, (string) $user['id']);

        // Sync to PHP Session
        $_SESSION['user']['name'] = $name;
        $_SESSION['user']['email'] = $email;
        $_SESSION['user']['phone'] = $phone;

        $success = 'Profile updated successfully!';
        
        // Reload fresh values
        $profile = fetch_teacher_profile($db, (string) $user['id']);

    } catch (Throwable $exception) {
        $errors[] = $exception->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Edit Profile — Lawable</title>
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600;700;800&family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet" />
  <link rel="stylesheet" href="../../assets/css/lawable.css?v=2" />
  <!-- Flatpickr Datepicker -->
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
  <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
  <style>
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
      --input-bg: #F3F4F6;
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
      --input-bg: #0F172A;
    }

    body.profile-page { background: var(--page-bg) !important; color: var(--ink); font-family: 'Inter', sans-serif; }
    
    /* ─── Breadcrumbs & Header ──────────────── */
    .profile-breadcrumbs { font-size: 0.85rem; color: var(--ink-soft); margin-bottom: 0.5rem; display: flex; align-items: center; gap: 0.5rem; }
    .profile-breadcrumbs a { color: var(--ink-soft); text-decoration: none; transition: color 0.2s; }
    .profile-breadcrumbs a:hover { color: var(--gold); }
    .profile-breadcrumbs span { color: var(--border); }
    
    .profile-header-title { font-family: 'Playfair Display', serif; font-size: 2rem; font-weight: 700; color: var(--ink); margin-bottom: 1.75rem; }

    /* ─── Shell Layout ──────────────── */
    .profile-shell { width: min(1040px, 100%) !important; margin: 2rem 0 2rem 3.5rem !important; padding: 0 1.5rem 0 0; box-sizing: border-box; }
    .profile-layout { display: grid; grid-template-columns: 280px 1fr; gap: 2rem; align-items: start; }
    
    /* ─── Sidebar Cards ──────────────── */
    .profile-sidebar { background: var(--white); border: 1px solid var(--border); border-radius: 24px; padding: 1.5rem; box-shadow: 0 4px 24px rgba(13,17,23,0.04); display: flex; flex-direction: column; gap: 0.5rem; }
    
    .tab-btn { display: flex; align-items: center; gap: 0.75rem; width: 100%; border: none; background: transparent; padding: 0.95rem 1.25rem; font-size: 0.95rem; font-weight: 600; text-align: left; color: var(--ink-mid); border-radius: 9999px; cursor: pointer; transition: background 0.2s, color 0.2s; }
    .tab-btn:hover { background: var(--gold-lt); color: var(--gold-dk); }
    .tab-btn.active { background: var(--gold); color: white; }
    
    /* ─── Content Panel ──────────────── */
    .profile-content { background: var(--white); border: 1px solid var(--border); border-radius: 24px; padding: 2.25rem; box-shadow: 0 4px 24px rgba(13,17,23,0.04); min-height: 480px; width: 700px; box-sizing: border-box; }
    
    .profile-panel { display: none; }
    .profile-panel.active { display: block; }
    
    .panel-title { font-family: 'Playfair Display', serif; font-size: 1.45rem; font-weight: 700; color: var(--ink); margin-bottom: 1.5rem; border-bottom: 1px solid var(--border); padding-bottom: 0.75rem; }

    /* ─── Forms and Fields ──────────────── */
    .profile-form-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); column-gap: 1.5rem; row-gap: 2.25rem; }
    .profile-field { display: grid; gap: 0.5rem; position: relative; }
    .profile-field-full { grid-column: 1 / -1; }
    
    .profile-field label { font-size: 0.9rem; font-weight: 600; color: var(--ink-mid); }
    .profile-field label .required-asterisk { color: var(--gold); font-weight: bold; margin-left: 0.2rem; }
    
    .profile-field input, .profile-field textarea, .profile-field select { width: 100%; min-height: 46px; border: 2px solid transparent; border-radius: 12px; padding: 0.85rem 1.1rem; background: var(--input-bg); color: var(--ink); font-family: 'Inter', sans-serif; font-size: 0.95rem; box-sizing: border-box; transition: border-color .2s, background .2s, box-shadow .2s; }
    .profile-field textarea { min-height: 120px; resize: vertical; }
    
    .profile-field input:focus, .profile-field textarea:focus, .profile-field select:focus { outline: none; border-color: var(--gold); background: var(--white); box-shadow: 0 0 0 3px rgba(201,147,58,0.12); }
    .profile-field input[disabled], .profile-field input[readonly] { background: var(--border); color: var(--ink-soft); cursor: not-allowed; }

    /* ─── Avatar Picker Row ──────────────── */
    .avatar-picker-row { display: flex; align-items: center; gap: 1.5rem; margin-bottom: 2rem; }
    .avatar-main-preview { width: 90px; height: 90px; border-radius: 50%; overflow: hidden; border: 3px solid var(--gold-dk); background: var(--input-bg); flex-shrink: 0; box-shadow: 0 4px 12px rgba(168,115,42,0.15); }
    .avatar-main-preview img { width: 100%; height: 100%; object-fit: cover; }
    
    .avatar-picker-actions { display: flex; align-items: center; gap: 0.75rem; }
    
    /* ─── Buttons ──────────────── */
    .btn-pill { display: inline-flex; align-items: center; justify-content: center; padding: 0.75rem 1.75rem; font-size: 0.9rem; font-weight: 600; border-radius: 9999px; border: 1px solid transparent; cursor: pointer; transition: all 0.2s ease-in-out; text-decoration: none; min-width: 110px; }
    .btn-pill-primary { background: var(--gold-dk); color: white; }
    .btn-pill-primary:hover { background: var(--gold); transform: translateY(-1px); }
    
    .btn-pill-outline { background: transparent; border-color: var(--border); color: var(--ink-mid); }
    .btn-pill-outline:hover { background: var(--page-bg); border-color: var(--gold); color: var(--gold-dk); }
    
    .profile-form-wrap { display: flex !important; justify-content: flex-start !important; width: 100% !important; }
    .profile-actions { display: flex; justify-content: flex-end; gap: 1rem; margin-top: 2rem; border-top: 1px solid var(--border); padding-top: 1.5rem; }
    
    /* ─── Avatar Grid ──────────────── */
    .avatar-grid-dropdown { margin-top: 1rem; padding: 1rem; background: var(--page-bg); border: 1px solid var(--border); border-radius: 16px; display: none; }
    .avatar-grid-dropdown.active { display: block; }
    .avatar-grid-title { font-size: 0.85rem; font-weight: 600; color: #6B7280; margin-bottom: 0.75rem; display: block; text-transform: uppercase; letter-spacing: 0.05em; }
    .avatar-grid { display: flex; flex-wrap: wrap; gap: 0.5rem; }
    .avatar-thumb { width: 46px; height: 46px; border-radius: 50%; overflow: hidden; border: 2px solid transparent; cursor: pointer; transition: transform 0.15s, border-color 0.15s; }
    .avatar-thumb:hover { transform: scale(1.1); }
    .avatar-thumb.selected { border-color: #A8732A; box-shadow: 0 0 0 3px rgba(168, 115, 42, 0.2); }
    .avatar-thumb img { width: 100%; height: 100%; object-fit: cover; }

    /* ─── Alerts ──────────────── */
    .profile-alert { padding: 1rem 1.5rem; border-radius: 16px; font-size: 0.95rem; margin-bottom: 1.5rem; display: flex; align-items: center; border-width: 1px; border-style: solid; }
    .profile-alert-error { background: #FEE2E2; border-color: #FCA5A5; color: #B91C1C; }
    .profile-alert-success { background: #DCFCE7; border-color: #86EFAC; color: #15803D; }

    /* Responsive */
    @media (max-width: 768px) {
      .profile-shell { margin: 1rem auto !important; padding: 0 1rem !important; }
      .profile-layout { grid-template-columns: 1fr; }
      .profile-content { width: 100% !important; }
    }
  </style>
</head>
<body class="profile-page">
<div class="cursor-glow" id="cursorGlow"></div>
<div class="progress-bar" id="progressBar"></div>

<nav id="navbar">
  <a href="../dashboard.php" class="nav-logo">Law<span>able</span></a>
  <ul class="nav-links">
    <li><a href="../offerings.php">Offerings</a></li>
    <li><a href="../courses.php">Courses</a></li>
    <li><a href="../about.php">About</a></li>
    <li><a href="../contact.php">Contact</a></li>
    <li class="nav-profile-item">
      <a href="edit-profile.php" class="nav-profile active" aria-label="Edit profile">
        <span aria-hidden="true">👤</span>
      </a>
    </li>
    <li><a href="../../api/logout.php" class="nav-cta">Log out</a></li>
  </ul>
</nav>

<nav class="nav-drawer" id="drawer">
  <a href="../offerings.php" onclick="closeDrawer()">Offerings</a>
  <a href="../courses.php" onclick="closeDrawer()">Courses</a>
  <a href="../about.php" onclick="closeDrawer()">About</a>
  <a href="../contact.php" onclick="closeDrawer()">Contact</a>
  <a href="edit-profile.php" onclick="closeDrawer()">Edit profile</a>
  <a href="../../api/logout.php" class="drawer-cta">Log out</a>
</nav>

<main class="profile-shell">
  <!-- Breadcrumbs -->
  <div class="profile-breadcrumbs">
    <a href="../dashboard.php">Home</a>
    <span>&rsaquo;</span>
    <a href="edit-profile.php">My Profile</a>
  </div>
  
  <!-- Page Title -->
  <h1 class="profile-header-title">My Account</h1>

  <section class="profile-form-wrap">
    <?php foreach ($errors as $error): ?>
      <div class="profile-alert profile-alert-error"><?= e($error) ?></div>
    <?php endforeach; ?>

    <?php if ($success !== ''): ?>
      <div class="profile-alert profile-alert-success"><?= e($success) ?></div>
    <?php endif; ?>

    <form class="profile-form" method="post" action="edit-profile.php">
      <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>" />

      <div class="profile-layout">
        
        <!-- Left Sidebar Navigation -->
        <aside class="profile-sidebar">
          <button type="button" class="tab-btn active" id="tab-personal" onclick="switchTab('personal')">
            <span>👤</span> Personal Information
          </button>
          <button type="button" class="tab-btn" id="tab-professional" onclick="switchTab('professional')">
            <span>🎓</span> Professional Info
          </button>
          <button type="button" class="tab-btn" id="tab-security" onclick="switchTab('security')">
            <span>🔒</span> Account Security
          </button>
        </aside>

        <!-- Right Content Panels -->
        <div class="profile-content">
          
          <!-- Tab 1: Personal Information -->
          <div id="panel-personal" class="profile-panel active">
            <h2 class="panel-title">Personal Information</h2>
            
            <!-- Avatar Picker Row -->
            <div class="avatar-picker-row">
              <div class="avatar-main-preview">
                <img id="avatar-preview-img" src="../../assets/img/avatars/<?= !empty($profile['avatar']) ? e($profile['avatar']) : 'avatar1.png' ?>" alt="Selected Avatar" onerror="this.src='https://api.dicebear.com/7.x/bottts/svg?seed=<?= e($profile['username']) ?>'" />
              </div>
              <div class="avatar-picker-actions">
                <button type="button" class="btn-pill btn-pill-primary" id="btn-choose-avatar" onclick="toggleAvatarGrid()">Choose Avatar</button>
                <button type="button" class="btn-pill btn-pill-outline" id="btn-remove-avatar" onclick="removeAvatar()">Remove</button>
              </div>
              <input type="hidden" name="avatar" id="selected_avatar" value="<?= e($profile['avatar'] ?? 'avatar1.png') ?>" />
            </div>

            <!-- Avatar Grid Dropdown -->
            <div class="avatar-grid-dropdown" id="avatar-dropdown">
              <span class="avatar-grid-title">Select Avatar Image</span>
              <div class="avatar-grid">
                <?php for ($i = 1; $i <= 10; $i++): 
                    $filename = "avatar{$i}.png";
                    $isSelected = ($profile['avatar'] ?? 'avatar1.png') === $filename;
                ?>
                  <div class="avatar-thumb <?= $isSelected ? 'selected' : '' ?>" data-filename="<?= $filename ?>" onclick="selectAvatar(this, '<?= $filename ?>')">
                    <img src="../../assets/img/avatars/<?= $filename ?>" alt="Avatar <?= $i ?>" onerror="this.src='https://api.dicebear.com/7.x/bottts/svg?seed=avatar<?= $i ?>'" />
                  </div>
                <?php endfor; ?>
              </div>
            </div>
            
            <div class="profile-form-grid">
              <div class="profile-field">
                <label for="name">Full Name <span class="required-asterisk">*</span></label>
                <input id="name" name="name" type="text" maxlength="150" required value="<?= e($profile['name'] ?? '') ?>" />
              </div>

              <div class="profile-field">
                <label for="username">Username <span class="required-asterisk">*</span></label>
                <input id="username" name="username" type="text" minlength="3" maxlength="30" required value="<?= e($profile['username'] ?? '') ?>" />
              </div>

              <div class="profile-field">
                <label for="date_of_birth">Date of Birth</label>
                <input id="date_of_birth" name="date_of_birth" type="text" placeholder="Select Date of Birth" value="<?= e($profile['date_of_birth'] ?? '') ?>" />
              </div>

              <div class="profile-field">
                <label for="gender">Gender</label>
                <select id="gender" name="gender">
                  <option value="">Select Gender</option>
                  <option value="Female" <?= ($profile['gender'] ?? '') === 'Female' ? 'selected' : '' ?>>Female</option>
                  <option value="Male" <?= ($profile['gender'] ?? '') === 'Male' ? 'selected' : '' ?>>Male</option>
                  <option value="Other" <?= ($profile['gender'] ?? '') === 'Other' ? 'selected' : '' ?>>Other</option>
                  <option value="Prefer not to say" <?= ($profile['gender'] ?? '') === 'Prefer not to say' ? 'selected' : '' ?>>Prefer not to say</option>
                </select>
              </div>

              <div class="profile-field">
                <label for="phone">Phone Number</label>
                <input id="phone" name="phone" type="tel" maxlength="30" value="<?= e($profile['phone'] ?? '') ?>" />
              </div>

              <div class="profile-field">
                <label for="email">Email <span class="required-asterisk">*</span></label>
                <input id="email" name="email" type="email" required value="<?= e($profile['email'] ?? '') ?>" />
              </div>

              <div class="profile-field profile-field-full">
                <label for="bio">Bio</label>
                <textarea id="bio" name="bio" maxlength="1000" rows="5" placeholder="Tell students about your teaching goals and legal background..."><?= e($profile['bio'] ?? '') ?></textarea>
              </div>
            </div>
            
            <div class="profile-actions">
              <a href="../dashboard.php" class="btn-pill btn-pill-outline">Cancel</a>
              <button type="submit" class="btn-pill btn-pill-primary">Update</button>
            </div>
          </div>

          <!-- Tab 2: Professional Info -->
          <div id="panel-professional" class="profile-panel">
            <h2 class="panel-title">Professional Info</h2>
            
            <div class="profile-form-grid">
              <div class="profile-field">
                <label for="designation">Affiliation / Designation</label>
                <input id="designation" name="designation" type="text" placeholder="e.g. Professor of Law at National Law University" value="<?= e($profile['designation'] ?? '') ?>" />
              </div>

              <div class="profile-field">
                <label for="headline">Professional Headline / Tagline</label>
                <input id="headline" name="headline" type="text" placeholder="e.g. Specialist in Cyber Crime Litigation" value="<?= e($profile['headline'] ?? '') ?>" />
              </div>

              <div class="profile-field">
                <label for="qualification">Qualifications / Degrees</label>
                <input id="qualification" name="qualification" type="text" placeholder="e.g. LL.B., LL.M., Ph.D. in Law" value="<?= e($profile['qualification'] ?? '') ?>" />
              </div>

              <div class="profile-field">
                <label for="specialization">Specialization Area / Expertise</label>
                <input id="specialization" name="specialization" type="text" placeholder="e.g. Constitutional Law, Intellectual Property" value="<?= e($profile['specialization'] ?? '') ?>" />
              </div>

              <div class="profile-field">
                <label for="experience">Years of Experience</label>
                <input id="experience" name="experience" type="text" placeholder="e.g. 10+ years in litigation / 5 years teaching" value="<?= e($profile['experience'] ?? '') ?>" />
              </div>

              <div class="profile-field">
                <label for="public_email">Public Contact Email (Optional)</label>
                <input id="public_email" name="public_email" type="email" placeholder="students-contact@example.com" value="<?= e($profile['publicEmail'] ?? '') ?>" />
              </div>
            </div>
            
            <div class="profile-actions">
              <a href="../dashboard.php" class="btn-pill btn-pill-outline">Cancel</a>
              <button type="submit" class="btn-pill btn-pill-primary">Update</button>
            </div>
          </div>

          <!-- Tab 3: Account Security -->
          <div id="panel-security" class="profile-panel">
            <h2 class="panel-title">Account Security</h2>
            
            <div class="profile-form-grid">
              <div class="profile-field">
                <label>Username</label>
                <input type="text" readonly value="<?= e($profile['username'] ?? '') ?>" />
              </div>
              <div class="profile-field">
                <label>Email Address</label>
                <input type="text" readonly value="<?= e($profile['email'] ?? '') ?>" />
              </div>
              <div class="profile-field profile-field-full">
                <label>Account Status</label>
                <input type="text" readonly value="Active instructor account (Firestore verified)" />
              </div>
            </div>
            <p style="margin-top: 1.5rem; font-size: 0.9rem; color: #6B7280; line-height: 1.5;">
              🛡️ For security reasons, email addresses cannot be changed directly. Password reset and verification emails are managed securely. Contact support if you need to update your email credentials.
            </p>
          </div>
          
        </div>
      </div>
    </form>
  </section>
</main>

<script src="../../assets/js/script.js"></script>
<script>
  function switchTab(tabName) {
    // Switch active buttons
    document.querySelectorAll('.tab-btn').forEach(btn => {
      btn.classList.remove('active');
    });
    document.getElementById('tab-' + tabName).classList.add('active');

    // Switch active panels
    document.querySelectorAll('.profile-panel').forEach(panel => {
      panel.classList.remove('active');
    });
    document.getElementById('panel-' + tabName).classList.add('active');
  }

  function toggleAvatarGrid() {
    const dropdown = document.getElementById('avatar-dropdown');
    dropdown.classList.toggle('active');
  }

  function selectAvatar(element, filename) {
    // Remove selected class from all options
    document.querySelectorAll('.avatar-thumb').forEach(function(opt) {
      opt.classList.remove('selected');
    });
    // Add selected class to the clicked element
    element.classList.add('selected');
    // Update the hidden input value
    document.getElementById('selected_avatar').value = filename;
    // Update the live preview image
    document.getElementById('avatar-preview-img').src = '../../assets/img/avatars/' + filename;
    
    // Hide the grid after selecting
    document.getElementById('avatar-dropdown').classList.remove('active');
  }

  function removeAvatar() {
    const defaultFilename = 'avatar1.png';
    // Update hidden input
    document.getElementById('selected_avatar').value = defaultFilename;
    // Update preview img
    document.getElementById('avatar-preview-img').src = '../../assets/img/avatars/' + defaultFilename;
    // Reset selected thumbnail highlighting
    document.querySelectorAll('.avatar-thumb').forEach(function(opt) {
      opt.classList.remove('selected');
      if (opt.getAttribute('data-filename') === defaultFilename) {
        opt.classList.add('selected');
      }
    });
  }

  document.addEventListener('DOMContentLoaded', function() {
    flatpickr('#date_of_birth', {
      dateFormat: 'Y-m-d',
      altInput: true,
      altFormat: 'F j, Y',
      maxDate: 'today',
      disableMobile: true
    });
  });
</script>
</body>
</html>
