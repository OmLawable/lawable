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
    return [
        'id'                => $profile['__id'] ?? $studentUid,
        'name'              => $profile['name'] ?? '',
        'username'          => $profile['username'] ?? '',
        'email'             => $profile['email'] ?? '',
        'phone'             => $profile['phone'] ?? '',
        'city'              => $profile['city'] ?? '',
        'bio'               => $profile['bio'] ?? '',
        'date_of_birth'     => $profile['dateOfBirth'] ?? '',
        'institution'       => $profile['institution'] ?? '',
        'course'            => $profile['course'] ?? '',
        'year_semester'     => $profile['yearSemester'] ?? '',
        'areas_of_interest' => $profile['areasOfInterest'] ?? '',
        'resume_file'       => $profile['resumeFile'] ?? '',
        'linkedin_url'      => $profile['linkedinUrl'] ?? '',
        'skills'            => $profile['skills'] ?? '',
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
        'city'              => '',
        'bio'               => '',
        'date_of_birth'     => '',
        'institution'       => '',
        'course'            => '',
        'year_semester'     => '',
        'areas_of_interest' => '',
        'resume_file'       => '',
        'linkedin_url'      => '',
        'skills'            => '',
    ];
    $errors[] = 'Profile not found: ' . $exception->getMessage();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        verify_csrf_token($_POST['csrf_token'] ?? '');

        $name            = trim((string) ($_POST['name'] ?? ''));
        $phone           = trim((string) ($_POST['phone'] ?? ''));
        $city            = trim((string) ($_POST['city'] ?? ''));
        $bio             = trim((string) ($_POST['bio'] ?? ''));
        $dateOfBirth     = trim((string) ($_POST['date_of_birth'] ?? ''));
        $institution     = trim((string) ($_POST['institution'] ?? ''));
        $course          = trim((string) ($_POST['course'] ?? ''));
        $yearSemester    = trim((string) ($_POST['year_semester'] ?? ''));
        $areasOfInterest = trim((string) ($_POST['areas_of_interest'] ?? ''));
        $linkedinUrl     = trim((string) ($_POST['linkedin_url'] ?? ''));
        $skills          = trim((string) ($_POST['skills'] ?? ''));
        
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

        if ($city !== '' && strlen($city) > 120) {
            throw new RuntimeException('City must be 120 characters or fewer.');
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
            'Areas of Interest' => $areasOfInterest,
            'LinkedIn URL' => $linkedinUrl,
        ] as $label => $value) {
            if (strlen($value) > 255) {
                throw new RuntimeException($label . ' must be 255 characters or fewer.');
            }
        }

        if (strlen($skills) > 1000) {
            throw new RuntimeException('Skills must be 1000 characters or fewer.');
        }

        if ($linkedinUrl !== '' && !filter_var($linkedinUrl, FILTER_VALIDATE_URL)) {
            throw new RuntimeException('Please enter a valid LinkedIn URL.');
        }

        // Update Firestore student document (combines student info and profile fields)
        $db->update('students', (string) $user['id'], [
            'name'            => $name,
            'phone'           => $phone,
            'city'            => $city,
            'bio'             => $bio,
            'dateOfBirth'     => $dateOfBirth,
            'institution'     => $institution,
            'course'          => $course,
            'yearSemester'    => $yearSemester,
            'areasOfInterest' => $areasOfInterest,
            'linkedinUrl'     => $linkedinUrl,
            'skills'          => $skills,
            'resumeFile'      => $resumeFile,
            'updatedAt'       => FirestoreClient::now()
        ]);

        $_SESSION['user']['name'] = $name;
        $_SESSION['user']['phone'] = $phone;

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
  <link rel="stylesheet" href="../../assets/css/lawable.css?v=2" />
  <style>
    body.profile-page { background: #FCF8F1 !important; }
    .profile-shell { width: min(980px, 100%) !important; margin: 0 auto !important; }
    .profile-form-wrap { display: flex !important; justify-content: center !important; width: 100% !important; }
    .profile-form { width: 100% !important; }
    .profile-card { width: 100%; max-width: 920px; margin: 0 auto; background: white; border: 1px solid #E5E0D8; border-radius: 28px; box-shadow: 0 4px 24px rgba(13,17,23,0.08); }
    .profile-card-header { display: flex; align-items: center; gap: 1rem; padding: 1.75rem 2rem 1.5rem; border-bottom: 1px solid rgba(229,224,216,0.9); }
    .profile-card-icon { width: 48px; height: 48px; display: inline-flex; align-items: center; justify-content: center; border-radius: 16px; background: rgba(201,147,58,0.14); color: #A8732A; }
    .profile-card-body { display: grid; gap: 1.75rem; padding: 1.75rem 2rem 2rem; }
    .profile-form-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 1.25rem; }
    .profile-field { display: grid; gap: 0.5rem; }
    .profile-field label { font-size: 0.95rem; font-weight: 600; color: #4B5563; }
    .profile-field input, .profile-field textarea { width: 100%; min-height: 44px; border: 1px solid #E5E0D8; border-radius: 16px; padding: 0.95rem 1rem; background: white; color: #0D1117; font-family: 'Inter', sans-serif; font-size: 0.95rem; transition: border-color .2s, box-shadow .2s; }
    .profile-field textarea { min-height: 140px; resize: vertical; }
    .profile-field input:focus, .profile-field textarea:focus { outline: none; border-color: #C9933A; box-shadow: 0 0 0 3px rgba(201,147,58,0.12); }
    .profile-field input[disabled], .profile-field input[readonly] { background: #F8F4EF; color: #6B7280; cursor: not-allowed; }
    .profile-field-full { grid-column: 1 / -1; }
    .profile-actions { display: flex; justify-content: flex-end; gap: 1rem; }
    .btn-ghost { border: 1px solid #E5E0D8; background: white; color: #0D1117; }
    .btn-primary { background: #A8732A; color: white; }
    .profile-form-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 1.25rem; }
    .profile-field { display: grid; gap: 0.5rem; }
    .profile-field label { font-size: 0.95rem; font-weight: 600; color: #4B5563; }
    .profile-field input, .profile-field textarea { width: 100%; min-height: 44px; border: 1px solid #E5E0D8; border-radius: 16px; padding: 0.95rem 1rem; background: white; color: #0D1117; font-family: 'Inter', sans-serif; font-size: 0.95rem; transition: border-color .2s, box-shadow .2s; }
    .profile-field textarea { min-height: 140px; resize: vertical; }
    .profile-field input:focus, .profile-field textarea:focus { outline: none; border-color: #C9933A; box-shadow: 0 0 0 3px rgba(201,147,58,0.12); }
    .profile-field input[disabled], .profile-field input[readonly] { background: #F8F4EF; color: #6B7280; cursor: not-allowed; }
    .profile-field-full { grid-column: 1 / -1; }
    .profile-actions { display: flex; justify-content: flex-end; gap: 1rem; }
    .btn-ghost { border: 1px solid #E5E0D8; background: white; color: #0D1117; }
    .btn-primary { background: #A8732A; color: white; }
  </style>
</head>
<body class="profile-page">
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
  <button class="nav-hamburger" id="hamburger" aria-label="Menu">
    <span></span><span></span><span></span>
  </button>
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
  <section class="profile-form-wrap">
    <?php foreach ($errors as $error): ?>
      <div class="profile-alert profile-alert-error"><?= e($error) ?></div>
    <?php endforeach; ?>

    <?php if ($success !== ''): ?>
      <div class="profile-alert profile-alert-success"><?= e($success) ?></div>
    <?php endif; ?>

    <form class="profile-form" method="post" action="edit-profile.php">
      <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>" />

      <div class="profile-card">
        <div class="profile-card-header">
          <span class="profile-card-icon" aria-hidden="true">
            <svg viewBox="0 0 24 24" fill="none">
              <circle cx="12" cy="8" r="3.5" stroke="currentColor" stroke-width="1.8"/>
              <path d="M5.5 19c1.3-3.2 3.5-4.8 6.5-4.8s5.2 1.6 6.5 4.8" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
            </svg>
          </span>
          <div>
            <h1>Edit Profile</h1>
          </div>
        </div>

        <div class="profile-card-body">
          <div class="profile-form-grid">
            <div class="profile-field">
              <label for="name">Full Name</label>
              <input id="name" name="name" type="text" maxlength="150" required value="<?= e($profile['name'] ?? '') ?>" />
            </div>

            <div class="profile-field">
              <label for="username">Username</label>
              <input id="username" type="text" disabled value="<?= e($profile['username'] ?? '') ?>" />
            </div>

            <div class="profile-field">
              <label for="date_of_birth">Date of Birth</label>
              <input id="date_of_birth" name="date_of_birth" type="date" value="<?= e($profile['date_of_birth'] ?? '') ?>" />
            </div>

            <div class="profile-field">
              <label for="city">City</label>
              <input id="city" name="city" type="text" maxlength="120" value="<?= e($profile['city'] ?? '') ?>" />
            </div>

            <div class="profile-field">
              <label for="phone">Phone Number</label>
              <input id="phone" name="phone" type="tel" maxlength="30" value="<?= e($profile['phone'] ?? '') ?>" />
            </div>

            <div class="profile-field">
              <label for="email">Email</label>
              <input id="email" type="email" disabled value="<?= e($profile['email'] ?? '') ?>" />
            </div>

            <div class="profile-field profile-field-full">
              <label for="bio">Bio</label>
              <textarea id="bio" name="bio" maxlength="1000" rows="5"><?= e($profile['bio'] ?? '') ?></textarea>
            </div>
          </div>

          <div class="profile-section-divider"></div>

          <h2 class="profile-section-title">Professional Information</h2>

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
              <label for="linkedin_url">LinkedIn URL</label>
              <input id="linkedin_url" name="linkedin_url" type="url" maxlength="255" value="<?= e($profile['linkedin_url'] ?? '') ?>" />
            </div>

            <div class="profile-field profile-field-full">
              <label for="areas_of_interest">Areas of Interest</label>
              <textarea id="areas_of_interest" name="areas_of_interest" maxlength="1000" rows="4" placeholder="e.g., Corporate Law, Intellectual Property, Human Rights"><?= e($profile['areas_of_interest'] ?? '') ?></textarea>
            </div>

            <div class="profile-field profile-field-full">
              <label for="skills">Skills</label>
              <textarea id="skills" name="skills" maxlength="1000" rows="4" placeholder="e.g., Legal Research, Contract Drafting, Litigation, Legal Writing"><?= e($profile['skills'] ?? '') ?></textarea>
            </div>

            <div class="profile-field profile-field-full">
              <label for="resume">Resume Upload (PDF or Word)</label>
              <div class="file-upload-wrapper">
                <input id="resume" name="resume" type="file" accept=".pdf,.doc,.docx" />
                <span class="file-upload-hint">Max size: 500KB (PDF, DOC, DOCX)</span>
                <?php if (!empty($profile['resume_file'])): ?>
                  <div class="file-uploaded">
                    <span class="file-name"><?= e($profile['resume_file']) ?></span>
                    <a href="uploads/resumes/<?= e($profile['resume_file']) ?>" target="_blank" class="file-download">View</a>
                  </div>
                <?php endif; ?>
              </div>
            </div>
          </div>

          <div class="profile-actions">
            <a href="../dashboard.php" class="btn-ghost">Cancel</a>
            <button type="submit" class="btn-primary">Update</button>
          </div>
        </div>
      </div>
    </form>
  </section>
</main>

<script src="../../assets/js/script.js"></script>
<script>
  document.addEventListener('DOMContentLoaded', function() {
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
<style>
  @keyframes slideUp {
    from { opacity: 1; transform: translateX(-50%) translateY(0); }
    to { opacity: 0; transform: translateX(-50%) translateY(-20px); }
  }
</style>
</body>
</html>
