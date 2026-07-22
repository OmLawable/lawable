<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/firestore.php';
start_secure_session();

$user = require_login('user');
$db = get_firestore();
$errors = [];
$success = '';

function fetch_student_profile(FirestoreClient $db, string $studentUid): array
{
    $profile = $db->get('students', $studentUid);
    if (!$profile) {
        throw new RuntimeException('Student account not found.');
    }

    // Adapt fields to match the old array keys used in the HTML templates
    $rawAreas = $profile['areasOfInterest'] ?? [];
    if (is_string($rawAreas)) {
        $areas = array_filter(array_map('trim', explode(',', $rawAreas)));
    } else {
        $areas = (array)$rawAreas;
    }

    $rawSkills = $profile['skills'] ?? [];
    if (is_string($rawSkills)) {
        $skills = array_filter(array_map('trim', explode(',', $rawSkills)));
    } else {
        $skills = (array)$rawSkills;
    }

    return [
        'id'                => $profile['__id'] ?? $studentUid,
        'name'              => $profile['name'] ?? '',
        'username'          => $profile['username'] ?? '',
        'email'             => $profile['email'] ?? '',
        'phone'             => $profile['phone'] ?? '',
        'gender'            => $profile['gender'] ?? '',
        'bio'               => $profile['bio'] ?? '',
        'date_of_birth'     => $profile['dateOfBirth'] ?? '',
        'institution'       => $profile['institution'] ?? '',
        'course'            => $profile['course'] ?? '',
        'year_semester'     => $profile['yearSemester'] ?? '',
        'areas_of_interest' => $areas,
        'resume_file'       => $profile['resumeFile'] ?? '',
        'department'        => $profile['department'] ?? '',
        'skills'            => $skills,
        'avatar'            => $profile['avatar'] ?? 'avatar1.png',
    ];
}

try {
    $profile = fetch_student_profile($db, (string) $user['id']);
} catch (Throwable $exception) {
    $profile = [
        'id'                => (string) $user['id'],
        'name'              => $user['name'] ?? '',
        'username'          => $user['username'] ?? '',
        'email'             => $user['email'] ?? '',
        'phone'             => $user['phone'] ?? '',
        'gender'            => '',
        'bio'               => '',
        'date_of_birth'     => '',
        'institution'       => '',
        'course'            => '',
        'year_semester'     => '',
        'areas_of_interest' => [],
        'resume_file'       => '',
        'department'        => '',
        'skills'            => [],
        'avatar'            => 'avatar1.png',
    ];
    $errors[] = 'Profile not found: ' . $exception->getMessage();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        verify_csrf_token($_POST['csrf_token'] ?? '');

        $name            = trim((string) ($_POST['name'] ?? ''));
        $username        = trim((string) ($_POST['username'] ?? ''));
        $email           = trim((string) ($_POST['email'] ?? ''));
        $phone           = trim((string) ($_POST['phone'] ?? ''));
        $gender          = trim((string) ($_POST['gender'] ?? ''));
        if ($gender !== '' && !in_array($gender, ['Female', 'Male', 'Other', 'Prefer not to say'], true)) {
            throw new RuntimeException('Invalid gender value selected.');
        }
        $bio             = trim((string) ($_POST['bio'] ?? ''));
        $dateOfBirth     = trim((string) ($_POST['date_of_birth'] ?? ''));
        $institution     = trim((string) ($_POST['institution'] ?? ''));
        $course          = trim((string) ($_POST['course'] ?? ''));
        $yearSemester    = trim((string) ($_POST['year_semester'] ?? ''));
        $rawAreas        = $_POST['areas_of_interest'] ?? [];
        $areasOfInterest = is_array($rawAreas) ? array_slice(array_filter(array_map('trim', $rawAreas)), 0, 5) : [];
        
        $department      = trim((string) ($_POST['department'] ?? ''));
        
        $rawSkills       = $_POST['skills'] ?? [];
        $skills          = is_array($rawSkills) ? array_slice(array_filter(array_map('trim', $rawSkills)), 0, 5) : [];
        $avatar          = trim((string) ($_POST['avatar'] ?? 'avatar1.png'));
        
        if (!preg_match('/^avatar[1-9][0-9]*\.png$/', $avatar)) {
            $avatar = 'avatar1.png';
        }
        
        // Validate username format
        if ($username === '' || strlen($username) < 3 || strlen($username) > 30 || !preg_match('/^[a-zA-Z0-9._-]+$/', $username)) {
            throw new RuntimeException('Username must be 3-30 chars, alphanumeric or spec characters (._-).');
        }

        // Validate email format
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new RuntimeException('Please enter a valid email address.');
        }

        // Validate username uniqueness
        if ($username !== $profile['username']) {
            $matches = $db->query('students', [['username', 'EQUAL', $username]], 5);
            foreach ($matches as $m) {
                if ($m['__id'] !== (string) $user['id']) {
                    throw new RuntimeException('This username is already taken.');
                }
            }
            $orgMatches = $db->query('organizations', [['username', 'EQUAL', $username]], 1);
            if (!empty($orgMatches)) {
                throw new RuntimeException('This username is already taken.');
            }
        }

        // Validate email uniqueness
        if ($email !== $profile['email']) {
            $matches = $db->query('students', [['email', 'EQUAL', $email]], 5);
            foreach ($matches as $m) {
                if ($m['__id'] !== (string) $user['id']) {
                    throw new RuntimeException('This email is already registered.');
                }
            }
            $orgMatches = $db->query('organizations', [['email', 'EQUAL', $email]], 1);
            if (!empty($orgMatches)) {
                throw new RuntimeException('This email is already registered.');
            }
        }

        $resumeFile = $profile['resume_file'] ?? '';
        
        // Handle resume file upload
        if (isset($_FILES['resume']) && $_FILES['resume']['error'] === UPLOAD_ERR_OK) {
            $resumeUpload = $_FILES['resume'];
            $maxFileSize = 500 * 1024; // 500KB
            
            if ($resumeUpload['size'] > $maxFileSize) {
                throw new RuntimeException('Resume file size must not exceed 500KB.');
            }
            
            $allowedMimes = ['application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'];
            $fileMime = mime_content_type($resumeUpload['tmp_name']);
            if (!in_array($fileMime, $allowedMimes)) {
                throw new RuntimeException('Resume must be a PDF or Word document (DOC, DOCX).');
            }
            
            $uploadDir = __DIR__ . '/uploads/resumes/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }
            
            $studentId = (string) $user['id'];
            $fileExt = pathinfo($resumeUpload['name'], PATHINFO_EXTENSION);
            $newFileName = 'resume_' . $studentId . '_' . time() . '.' . $fileExt;
            $filePath = $uploadDir . $newFileName;
            
            if (!move_uploaded_file($resumeUpload['tmp_name'], $filePath)) {
                throw new RuntimeException('Failed to upload resume file.');
            }
            
            // Delete old resume file if it exists
            if ($resumeFile !== '' && file_exists($uploadDir . $resumeFile)) {
                @unlink($uploadDir . $resumeFile);
            }
            
            $resumeFile = $newFileName;
        }

        if ($name === '' || strlen($name) < 2) {
            throw new RuntimeException('Full name must be at least 2 characters.');
        }

        if (strlen($name) > 150) {
            throw new RuntimeException('Full name must be 150 characters or fewer.');
        }

        if ($phone !== '' && !preg_match('/^[0-9+\-\s()]{7,30}$/', $phone)) {
            throw new RuntimeException('Please enter a valid phone number.');
        }


        if (strlen($bio) > 1000) {
            throw new RuntimeException('Bio must be 1000 characters or fewer.');
        }

        if ($dateOfBirth !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateOfBirth)) {
            throw new RuntimeException('Please enter a valid date of birth (YYYY-MM-DD).');
        }

        foreach ([
            'Institution/College name' => $institution,
            'Course/Degree' => $course,
            'Year/Semester' => $yearSemester,
        ] as $label => $value) {
            if (strlen((string) $value) > 255) {
                throw new RuntimeException($label . ' must be 255 characters or fewer.');
            }
        }

        if (count($areasOfInterest) > 5) {
            throw new RuntimeException('You can select a maximum of 5 Areas of Interest.');
        }

        if (count($skills) > 5) {
            throw new RuntimeException('You can select a maximum of 5 Skills.');
        }

        if (strlen($department) > 150) {
            throw new RuntimeException('Department/Branch must be 150 characters or fewer.');
        }

        // Update Firebase Auth email if it changed
        if ($email !== $profile['email']) {
            require_once __DIR__ . '/../../includes/firebase_auth.php';
            $auth = get_firebase_factory()->createAuth();
            $auth->updateUser((string) $user['id'], [
                'email' => $email
            ]);
        }

        // Update Firestore student document (combines student info and profile fields)
        $db->update('students', (string) $user['id'], [
            'name'            => $name,
            'username'        => $username,
            'email'           => $email,
            'phone'           => $phone,
            'gender'          => $gender,
            'bio'             => $bio,
            'dateOfBirth'     => $dateOfBirth,
            'institution'     => $institution,
            'course'          => $course,
            'yearSemester'    => $yearSemester,
            'areasOfInterest' => $areasOfInterest,
            'department'      => $department,
            'skills'          => $skills,
            'resumeFile'      => $resumeFile,
            'avatar'          => $avatar,
            'updatedAt'       => FirestoreClient::now()
        ]);

        $_SESSION['user']['name'] = $name;
        $_SESSION['user']['phone'] = $phone;
        $_SESSION['user']['avatar'] = $avatar;
        $_SESSION['user']['username'] = $username;
        $_SESSION['user']['email'] = $email;
        $_SESSION['user']['gender'] = $gender;

        $success = 'Profile saved successfully.';
        $profile = fetch_student_profile($db, (string) $user['id']);
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
  <title>Edit Profile - Lawable</title>
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600;700;800&family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet" />
  <link rel="stylesheet" href="../../assets/css/lawable.css?v=1.4" />
  <!-- Flatpickr Datepicker -->
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
  <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
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
    
    /* Filled gray inputs with no visible borders */
    .profile-field input, .profile-field textarea, .profile-field select { width: 100%; min-height: 46px; border: 2px solid transparent; border-radius: 12px; padding: 0.85rem 1.1rem; background: var(--input-bg); color: var(--ink); font-family: 'Inter', sans-serif; font-size: 0.95rem; box-sizing: border-box; transition: border-color .2s, background .2s, box-shadow .2s; }
    .profile-field textarea { min-height: 120px; resize: vertical; }
    
    .profile-field input:focus, .profile-field textarea:focus, .profile-field select:focus { outline: none; border-color: var(--gold); background: var(--white); box-shadow: 0 0 0 3px rgba(201,147,58,0.12); }
    .profile-field input[disabled], .profile-field input[readonly] { background: var(--border); color: var(--ink-soft); cursor: not-allowed; }

    /* ─── Avatar Picker Row ──────────────── */
    .avatar-picker-row { display: flex; align-items: center; gap: 1.5rem; margin-bottom: 2rem; }
    .avatar-main-preview { width: 90px; height: 90px; border-radius: 50%; overflow: hidden; border: 3px solid var(--gold-dk); background: var(--input-bg); flex-shrink: 0; box-shadow: 0 4px 12px rgba(168,115,42,0.15); }
    .avatar-main-preview img { width: 100%; height: 100%; object-fit: cover; }
    
    .avatar-picker-actions { display: flex; align-items: center; gap: 0.75rem; }
    
    /* ─── Buttons (Pill-shaped) ──────────────── */
    .btn-pill { display: inline-flex; align-items: center; justify-content: center; padding: 0.75rem 1.75rem; font-size: 0.9rem; font-weight: 600; border-radius: 9999px; border: 1px solid transparent; cursor: pointer; transition: all 0.2s ease-in-out; text-decoration: none; min-width: 110px; }
    .btn-pill-primary { background: var(--gold-dk); color: white; }
    .btn-pill-primary:hover { background: var(--gold); transform: translateY(-1px); }
    
    .btn-pill-outline { background: transparent; border-color: var(--border); color: var(--ink-mid); }
    .btn-pill-outline:hover { background: var(--page-bg); border-color: var(--gold); color: var(--gold-dk); }
    
    .btn-pill-ghost { background: transparent; border-color: transparent; color: var(--ink-soft); }
    .btn-pill-ghost:hover { background: var(--input-bg); color: var(--ink-mid); }
    
    .profile-form-wrap { display: flex !important; justify-content: flex-start !important; width: 100% !important; }
    .profile-actions { display: flex; justify-content: flex-end; gap: 1rem; margin-top: 2rem; border-top: 1px solid var(--border); padding-top: 1.5rem; }
    
    /* ─── Avatar Grid Overlay/Wrapper ──────────────── */
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
    .field-error-msg { font-size: 0.82rem; color: #DC2626; position: absolute; bottom: -1.45rem; left: 0.25rem; font-weight: 500; display: none; }
    .field-error-msg.active { display: block; }
    .profile-field input.input-error { border-color: #EF4444 !important; background-color: #FEF2F2 !important; }
    
    /* ─── Flatpickr Custom Styling ──────────────── */
    .flatpickr-calendar { border-radius: 16px !important; border: 1px solid #E5E0D8 !important; box-shadow: 0 10px 30px rgba(13,17,23,0.08) !important; font-family: 'Inter', sans-serif !important; background: white !important; }
    .flatpickr-day.selected, .flatpickr-day.startRange, .flatpickr-day.endRange,
    .flatpickr-day.selected.inRange, .flatpickr-day.startRange.inRange, .flatpickr-day.endRange.inRange,
    .flatpickr-day.selected:focus, .flatpickr-day.startRange:focus, .flatpickr-day.endRange:focus,
    .flatpickr-day.selected:hover, .flatpickr-day.startRange:hover, .flatpickr-day.endRange:hover,
    .flatpickr-day.selected.prevMonthDay, .flatpickr-day.startRange.prevMonthDay, .flatpickr-day.endRange.prevMonthDay,
    .flatpickr-day.selected.nextMonthDay, .flatpickr-day.startRange.nextMonthDay, .flatpickr-day.endRange.nextMonthDay {
      background: #A8732A !important; border-color: #A8732A !important; color: white !important;
    }
    .flatpickr-months .flatpickr-month { color: #A8732A !important; fill: #A8732A !important; }
    .flatpickr-current-month .numInputWrapper span.arrowUp:after { border-bottom-color: #A8732A !important; }
    .flatpickr-current-month .numInputWrapper span.arrowDown:after { border-top-color: #A8732A !important; }
    .flatpickr-months .flatpickr-prev-month:hover svg, .flatpickr-months .flatpickr-next-month:hover svg { fill: #A8732A !important; }
    .flatpickr-day.today { border-color: #C9933A !important; }
    .flatpickr-day.today:hover { background: #FAF7F2 !important; color: #A8732A !important; }
    
    /* Make Flatpickr input visually clean and matched */
    .flatpickr-input { background-color: #F3F4F6 !important; }
    .flatpickr-input[readonly] { cursor: pointer !important; background-color: #F3F4F6 !important; }
    
    /* ─── Custom Multiselect Chips UI ──────────────── */
    .custom-multiselect { position: relative; width: 100%; }
    .multiselect-box {
      display: flex;
      flex-direction: column;
      border: 2px solid transparent;
      background: #F3F4F6;
      border-radius: 12px;
      padding: 0.85rem 1rem;
      min-height: 86px;
      cursor: pointer;
      box-sizing: border-box;
      transition: border-color .2s, background .2s, box-shadow .2s;
    }
    .multiselect-box:focus-within {
      outline: none;
      border-color: #C9933A;
      background: white;
      box-shadow: 0 0 0 3px rgba(201,147,58,0.12);
    }
    
    .multiselect-chips-row {
      display: flex;
      flex-wrap: wrap;
      gap: 0.5rem;
      width: 100%;
    }
    
    .multiselect-search-row {
      width: 100%;
      margin-top: 0.55rem;
    }
    .multiselect-search-input {
      width: 100% !important;
      border: none !important;
      background: transparent !important;
      outline: none !important;
      padding: 0 !important;
      font-size: 0.95rem;
      color: #0D1117;
      font-family: 'Inter', sans-serif;
      min-height: auto !important;
      box-shadow: none !important;
    }
    .multiselect-search-input::placeholder {
      color: #9CA3AF;
    }
    
    .multiselect-chip {
      display: inline-flex;
      align-items: center;
      gap: 0.45rem;
      background: #EBF2FC;
      border: 1px solid #D0E1FD;
      color: #1E40AF;
      padding: 0.3rem 0.65rem;
      border-radius: 8px;
      font-size: 0.85rem;
      font-weight: 600;
      line-height: 1;
      transition: background 0.15s;
    }
    .multiselect-chip:hover {
      background: #DBE8FC;
    }
    .multiselect-chip-remove {
      border: none;
      background: transparent;
      cursor: pointer;
      font-size: 0.95rem;
      font-weight: bold;
      color: #1E40AF;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      padding: 0;
      line-height: 1;
      transition: color 0.15s;
    }
    .multiselect-chip-remove:hover {
      color: #DC2626;
    }
    
    .multiselect-dropdown { position: absolute; top: calc(100% + 4px); left: 0; width: 100%; max-height: 220px; overflow-y: auto; background: white; border: 1px solid #E5E0D8; border-radius: 12px; box-shadow: 0 10px 35px rgba(13,17,23,0.08); z-index: 100; display: none; padding: 0.5rem 0; box-sizing: border-box; }
    .multiselect-dropdown.active { display: block; }
    
    .multiselect-option { padding: 0.65rem 1rem; font-size: 0.95rem; color: #374151; cursor: pointer; transition: background 0.15s, color 0.15s; }
    .multiselect-option:hover { background: #FAF7F2; color: #A8732A; }
    .multiselect-option.selected { background: #FAF7F2; color: #A8732A; font-weight: 600; cursor: default; opacity: 0.6; }
    .multiselect-option.disabled { opacity: 0.4; cursor: not-allowed; }
    .multiselect-option.disabled:hover { background: transparent; color: #374151; }
  </style>
</head>
<body class="profile-page">
<nav id="navbar">
  <a href="../dashboard.php" class="nav-logo">Law<span>able</span></a>
  <ul class="nav-links">
    <li><a href="../offerings.php">Offerings</a></li>
    <li class="nav-dropdown">
      <a href="../courses.php" class="nav-dropdown-toggle">
        Courses <span class="nav-dropdown-chevron">▼</span>
      </a>
      <div class="nav-dropdown-menu">
        <a href="../courses.php">Explore Courses</a>
        <a href="../my-learnings.php">My Learnings</a>
      </div>
    </li>
    <li><a href="../about.php">About</a></li>
    <li><a href="../contact.php">Contact</a></li>
    <li class="nav-profile-item">
      <a href="edit-profile.php" class="nav-profile active" aria-label="Edit profile">
        <span aria-hidden="true">👤</span>
      </a>
    </li>
    <li><a href="../../api/logout.php" class="nav-cta">Log out</a></li>
  </ul>
  <button class="nav-hamburger" id="hamburger" aria-label="Menu">
    <span></span><span></span><span></span>
  </button>
</nav>

<nav class="nav-drawer" id="drawer">
  <a href="../offerings.php" onclick="closeDrawer()">Offerings</a>
  <a href="../courses.php" onclick="closeDrawer()">Explore Courses</a>
  <a href="../my-learnings.php" onclick="closeDrawer()">My Learnings</a>
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

    <form class="profile-form" method="post" action="edit-profile.php" enctype="multipart/form-data">
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
                <img id="avatar-preview-img" src="../../assets/img/avatars/<?= !empty($profile['avatar']) ? e($profile['avatar']) : 'avatar1.png' ?>" alt="Selected Avatar" />
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
                    <img src="../../assets/img/avatars/<?= $filename ?>" alt="Avatar <?= $i ?>" />
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
                <input id="username" name="username" type="text" minlength="3" maxlength="30" required value="<?= e($profile['username'] ?? '') ?>" oninput="checkUniqueness('username')" />
                <span class="field-error-msg" id="username-error"></span>
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
                <input id="email" name="email" type="email" required value="<?= e($profile['email'] ?? '') ?>" oninput="checkUniqueness('email')" />
                <span class="field-error-msg" id="email-error"></span>
              </div>

              <div class="profile-field profile-field-full">
                <label for="bio">Bio</label>
                <textarea id="bio" name="bio" maxlength="1000" rows="5"><?= e($profile['bio'] ?? '') ?></textarea>
              </div>
            </div>
            
            <div class="profile-actions">
              <a href="../dashboard.php" class="btn-pill btn-pill-outline">Cancel</a>
              <button type="submit" class="btn-pill btn-pill-primary">Update</button>
            </div>
          </div>

          <!-- Tab 2: Professional Information -->
          <div id="panel-professional" class="profile-panel">
            <h2 class="panel-title">Professional Information</h2>
            
            <div class="profile-form-grid">
              <div class="profile-field">
                <label for="institution">Institution/College Name</label>
                <input id="institution" name="institution" type="text" maxlength="120" value="<?= e($profile['institution'] ?? '') ?>" />
              </div>

              <div class="profile-field">
                <label for="course">Course/Degree</label>
                <input id="course" name="course" type="text" placeholder="e.g., LLB, BA-LLB, LLM" maxlength="120" value="<?= e($profile['course'] ?? '') ?>" />
              </div>

              <div class="profile-field">
                <label for="year_semester">Year/Semester of Study</label>
                <input id="year_semester" name="year_semester" type="text" maxlength="120" value="<?= e($profile['year_semester'] ?? '') ?>" />
              </div>

              <div class="profile-field">
                <label for="department">Department/Branch</label>
                <input id="department" name="department" type="text" maxlength="150" placeholder="e.g. Law, Engineering, Commerce" value="<?= e($profile['department'] ?? '') ?>" />
              </div>

              <div class="profile-field profile-field-full">
                <label>Areas of Interest (Max 5)</label>
                <div class="custom-multiselect" id="multiselect-areas">
                  <div class="multiselect-box" onclick="focusSearch('areas')">
                    <div class="multiselect-chips-row" id="chips-areas">
                      <!-- Chips will render here -->
                    </div>
                    <div class="multiselect-search-row">
                      <input type="text" class="multiselect-search-input" id="search-areas" placeholder="Type to search areas of interest..." oninput="filterOptions('areas')" onfocus="showDropdown('areas')" onblur="hideDropdown('areas')" autocomplete="off" />
                    </div>
                  </div>
                  <div class="multiselect-dropdown" id="dropdown-areas">
                    <?php
                    $predefined_areas = [
                        'Corporate Law', 'Criminal Law', 'Intellectual Property', 
                        'Human Rights', 'Legal Tech', 'Constitutional Law', 
                        'Cyber Law', 'Family Law', 'Alternative Dispute Resolution', 'Tax Law',
                        'Software Engineering', 'Data Science & AI', 'Cybersecurity', 
                        'Mechanical Engineering', 'Electrical Engineering'
                    ];
                    foreach ($predefined_areas as $area):
                        $selected = in_array($area, $profile['areas_of_interest'] ?? [], true);
                    ?>
                      <div class="multiselect-option <?= $selected ? 'selected' : '' ?>" data-value="<?= e($area) ?>" onmousedown="event.preventDefault(); selectOption('areas', '<?= e($area) ?>')">
                        <?= e($area) ?>
                      </div>
                    <?php endforeach; ?>
                  </div>
                  <div id="hidden-inputs-areas">
                    <?php foreach (($profile['areas_of_interest'] ?? []) as $area): ?>
                      <input type="hidden" name="areas_of_interest[]" value="<?= e($area) ?>" />
                    <?php endforeach; ?>
                  </div>
                </div>
              </div>

              <div class="profile-field profile-field-full">
                <label>Skills (Max 5)</label>
                <div class="custom-multiselect" id="multiselect-skills">
                  <div class="multiselect-box" onclick="focusSearch('skills')">
                    <div class="multiselect-chips-row" id="chips-skills">
                      <!-- Chips will render here -->
                    </div>
                    <div class="multiselect-search-row">
                      <input type="text" class="multiselect-search-input" id="search-skills" placeholder="Type to search skills..." oninput="filterOptions('skills')" onfocus="showDropdown('skills')" onblur="hideDropdown('skills')" autocomplete="off" />
                    </div>
                  </div>
                  <div class="multiselect-dropdown" id="dropdown-skills">
                    <?php
                    $predefined_skills = [
                        'Legal Research', 'Legal Writing', 'Contract Drafting', 
                        'Advocacy', 'Litigation', 'Client Counseling', 
                        'Public Speaking', 'Legal Analysis', 'Negotiation', 'Due Diligence',
                        'Python & Programming', 'Web Development', 'Machine Learning & AI', 
                        'Data Analysis', 'Project Management', 'System Architecture'
                    ];
                    foreach ($predefined_skills as $skill):
                        $selected = in_array($skill, $profile['skills'] ?? [], true);
                    ?>
                      <div class="multiselect-option <?= $selected ? 'selected' : '' ?>" data-value="<?= e($skill) ?>" onmousedown="event.preventDefault(); selectOption('skills', '<?= e($skill) ?>')">
                        <?= e($skill) ?>
                      </div>
                    <?php endforeach; ?>
                  </div>
                  <div id="hidden-inputs-skills">
                    <?php foreach (($profile['skills'] ?? []) as $skill): ?>
                      <input type="hidden" name="skills[]" value="<?= e($skill) ?>" />
                    <?php endforeach; ?>
                  </div>
                </div>
              </div>

              <div class="profile-field profile-field-full">
                <label for="resume">Resume Upload (PDF or Word)</label>
                <div class="file-upload-wrapper">
                  <input id="resume" name="resume" type="file" accept=".pdf,.doc,.docx" />
                  <span class="file-upload-hint">Max size: 500KB (PDF, DOC, DOCX)</span>
                  <?php if (!empty($profile['resume_file'])): ?>
                    <div class="file-uploaded" style="margin-top:0.5rem; display:flex; align-items:center; gap:0.75rem;">
                      <span class="file-name" style="font-size:0.9rem; color:#4B5563;"><?= e($profile['resume_file']) ?></span>
                      <a href="uploads/resumes/<?= e($profile['resume_file']) ?>" target="_blank" class="btn-pill btn-pill-outline" style="padding:0.35rem 1rem; font-size:0.8rem; min-width:auto;">View</a>
                    </div>
                  <?php endif; ?>
                </div>
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
                <input type="text" readonly value="Active student account (Firestore verified)" />
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

  let checkTimers = {};

  function checkUniqueness(fieldName) {
    const input = document.getElementById(fieldName);
    const errorSpan = document.getElementById(fieldName + '-error');
    const value = input.value.trim();

    // Clear previous check timer for debounce
    clearTimeout(checkTimers[fieldName]);

    if (value === '') {
      errorSpan.classList.remove('active');
      input.classList.remove('input-error');
      return;
    }

    checkTimers[fieldName] = setTimeout(function() {
      fetch('../../api/validate_uniqueness.php?type=' + fieldName + '&value=' + encodeURIComponent(value))
        .then(response => response.json())
        .then(data => {
          if (!data.available) {
            errorSpan.textContent = data.message;
            errorSpan.classList.add('active');
            input.classList.add('input-error');
          } else {
            errorSpan.classList.remove('active');
            input.classList.remove('input-error');
          }
        })
        .catch(err => {
          console.error('AJAX error checking uniqueness:', err);
        });
    }, 400); // 400ms debounce
  }

  // Store selected values for chips
  const multiselectData = {
    areas: [],
    skills: []
  };

  function initMultiselect(type) {
    const hiddenInputs = document.querySelectorAll('#hidden-inputs-' + type + ' input');
    hiddenInputs.forEach(input => {
      if (input.value.trim() !== '') {
        multiselectData[type].push(input.value.trim());
      }
    });
    renderChips(type);
  }

  function focusSearch(type) {
    document.getElementById('search-' + type).focus();
  }

  function showDropdown(type) {
    // Hide other dropdowns
    document.querySelectorAll('.multiselect-dropdown').forEach(d => {
      if (d.id !== 'dropdown-' + type) d.classList.remove('active');
    });
    const dropdown = document.getElementById('dropdown-' + type);
    dropdown.classList.add('active');
    updateOptionStates(type);
  }

  function hideDropdown(type) {
    // Small delay to allow selectOption triggers before closure
    setTimeout(function() {
      const dropdown = document.getElementById('dropdown-' + type);
      dropdown.classList.remove('active');
      document.getElementById('search-' + type).value = '';
      filterOptions(type);
    }, 200);
  }

  // Handle clicking outside to close dropdowns
  document.addEventListener('click', function(e) {
    if (!e.target.closest('.custom-multiselect')) {
      document.querySelectorAll('.multiselect-dropdown').forEach(d => {
        d.classList.remove('active');
      });
      document.querySelectorAll('.multiselect-search-input').forEach(input => {
        input.value = '';
      });
      document.querySelectorAll('.multiselect-option').forEach(opt => {
        opt.style.display = 'block';
      });
    }
  });

  function filterOptions(type) {
    const query = document.getElementById('search-' + type).value.toLowerCase();
    const dropdown = document.getElementById('dropdown-' + type);
    const options = dropdown.querySelectorAll('.multiselect-option');
    options.forEach(opt => {
      const val = opt.textContent.trim().toLowerCase();
      if (val.includes(query)) {
        opt.style.display = 'block';
      } else {
        opt.style.display = 'none';
      }
    });
  }

  function renderChips(type) {
    const chipsRow = document.getElementById('chips-' + type);
    
    // Clear existing chips
    chipsRow.innerHTML = '';

    // Adjust bottom margin depending on chip count
    chipsRow.style.marginBottom = multiselectData[type].length > 0 ? '0.75rem' : '0';

    multiselectData[type].forEach(value => {
      const chip = document.createElement('span');
      chip.className = 'multiselect-chip';
      chip.innerHTML = e_js(value) + ` <button type="button" class="multiselect-chip-remove" onclick="event.stopPropagation(); removeOption('${type}', '${value.replace(/'/g, "\\'")}')">&times;</button>`;
      chipsRow.appendChild(chip);
    });

    // Update hidden inputs for form submit
    const hiddenContainer = document.getElementById('hidden-inputs-' + type);
    hiddenContainer.innerHTML = '';
    multiselectData[type].forEach(value => {
      const input = document.createElement('input');
      input.type = 'hidden';
      input.name = type === 'areas' ? 'areas_of_interest[]' : 'skills[]';
      input.value = value;
      hiddenContainer.appendChild(input);
    });

    updateOptionStates(type);
  }

  function updateOptionStates(type) {
    const dropdown = document.getElementById('dropdown-' + type);
    const options = dropdown.querySelectorAll('.multiselect-option');
    const limitReached = multiselectData[type].length >= 5;

    options.forEach(opt => {
      const val = opt.getAttribute('data-value');
      const isSelected = multiselectData[type].includes(val);
      
      opt.className = 'multiselect-option';
      if (isSelected) {
        opt.classList.add('selected');
      } else if (limitReached) {
        opt.classList.add('disabled');
      }
    });
  }

  function selectOption(type, value) {
    if (multiselectData[type].includes(value)) return;
    if (multiselectData[type].length >= 5) {
      alert('You can select a maximum of 5 items.');
      return;
    }
    multiselectData[type].push(value);
    renderChips(type);
    document.getElementById('search-' + type).value = '';
    document.getElementById('search-' + type).focus();
    filterOptions(type);
  }

  function removeOption(type, value) {
    multiselectData[type] = multiselectData[type].filter(v => v !== value);
    renderChips(type);
  }

  function e_js(str) {
    return str.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
  }

  document.addEventListener('DOMContentLoaded', function() {
    // Initialize custom multiselects
    initMultiselect('areas');
    initMultiselect('skills');

    // Initialize Flatpickr
    flatpickr('#date_of_birth', {
      dateFormat: 'Y-m-d', // Database save format
      altInput: true,
      altFormat: 'F j, Y', // Visual display format
      maxDate: 'today',
      disableMobile: true // Force desktop UI consistency on mobile
    });

    const form = document.querySelector('.profile-form');
    form.addEventListener('submit', function(e) {
      const activeErrors = document.querySelectorAll('.field-error-msg.active');
      if (activeErrors.length > 0) {
        e.preventDefault();
        alert('Please fix the errors on the form before updating.');
      }
    });

    const alerts = document.querySelectorAll('.profile-alert');
    alerts.forEach(function(alert) {
      if (alert.classList.contains('profile-alert-success')) {
        setTimeout(function() {
          alert.style.animation = 'slideUp 0.3s ease-out forwards';
          setTimeout(function() {
            alert.remove();
          }, 300);
        }, 3000);
      }
    });
  });
</script>
</body>
</html>
