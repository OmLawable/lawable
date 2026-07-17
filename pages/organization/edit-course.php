<?php

declare(strict_types=1);

require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/firestore.php';

start_secure_session();
if (!is_logged_in()) {
    redirect('pages/login.php');
}

$user = current_user();
$is_org = ($user['role'] ?? '') === 'organization';
$is_teacher = ($user['role'] ?? '') === 'teacher';

if (!$is_org && !$is_teacher) {
    redirect('pages/dashboard.php');
}

$courseId = trim((string) ($_GET['id'] ?? ''));
if ($courseId === '') {
    redirect('pages/organization/manage-courses.php');
}

$db = get_firestore();
$course = $db->get('courses', $courseId);

if (!$course) {
    redirect('pages/organization/manage-courses.php');
}

// Verify ownership
$ownerId = $is_teacher ? ($course['teacherId'] ?? '') : ($course['organizationId'] ?? '');
if ($ownerId !== $user['id']) {
    redirect('pages/organization/manage-courses.php');
}

$errors = [];
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        verify_csrf_token($_POST['csrf_token'] ?? '');

        $title       = trim((string) ($_POST['title'] ?? ''));
        $category    = trim((string) ($_POST['category'] ?? ''));
        $difficulty  = trim((string) ($_POST['difficulty'] ?? ''));
        $priceInput  = trim((string) ($_POST['price'] ?? '0'));
        $description = trim((string) ($_POST['description'] ?? ''));
        $imageUrl    = trim((string) ($_POST['imageUrl'] ?? ''));
        $status      = trim((string) ($_POST['status'] ?? 'draft'));

        if ($title === '') {
            throw new RuntimeException('Course title is required.');
        }
        if ($category === '') {
            throw new RuntimeException('Course category is required.');
        }
        if ($difficulty === '') {
            throw new RuntimeException('Course difficulty level is required.');
        }
        if ($description === '') {
            throw new RuntimeException('Course description is required.');
        }
        if (!in_array($status, ['draft', 'published'], true)) {
            throw new RuntimeException('Invalid course status.');
        }

        $price = (float) $priceInput;
        if ($price < 0.0) {
            throw new RuntimeException('Price cannot be negative.');
        }

        $updatedCourse = array_merge($course, [
            'title'       => $title,
            'category'    => $category,
            'price'       => $price,
            'difficulty'  => $difficulty,
            'description' => $description,
            'imageUrl'    => $imageUrl,
            'status'      => $status,
            'updatedAt'   => date('c')
        ]);

        $db->set('courses', $updatedCourse, $courseId);
        $success = 'Course updated successfully!';
        
        // Reload course info
        $course = $db->get('courses', $courseId);

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
  <title>Edit Course — Lawable</title>
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600;700;800&family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet" />
  <link rel="stylesheet" href="../../assets/css/lawable.css" />
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

    /* Styling consistency with B2B pages */
    .profile-card-icon svg {
      color: var(--gold);
    }
    .form-btn-row {
      display: flex;
      gap: 1rem;
      margin-top: 2rem;
      border-top: 1px solid var(--border);
      padding-top: 1.5rem;
    }
    .alert {
      padding: 1rem;
      border-radius: 8px;
      margin-bottom: 1.5rem;
      font-size: 0.88rem;
    }
    .alert-error {
      background: #FEE2E2;
      color: #991B1B;
    }
    .alert-success {
      background: #DCFCE7;
      color: #166534;
    }
    .btn-pill { display: inline-flex; align-items: center; justify-content: center; padding: 0.75rem 1.75rem; font-size: 0.9rem; font-weight: 600; border-radius: 9999px; border: 1px solid transparent; cursor: pointer; transition: all 0.2s ease-in-out; text-decoration: none; min-width: 110px; }
    .btn-pill-primary { background: #A8732A; color: white; }
    .btn-pill-primary:hover { background: #8E5E1E; transform: translateY(-1px); }
    .btn-pill-outline { background: transparent; border-color: #E5E0D8; color: #4B5563; }
    .btn-pill-outline:hover { background: #F9F8F6; border-color: #C9933A; color: #A8732A; }
  </style>
</head>
<body class="profile-page">
<div class="cursor-glow" id="cursorGlow"></div>
<div class="progress-bar" id="progressBar"></div>

<nav id="navbar">
  <a href="../dashboard.php" class="nav-logo">Law<span>able</span></a>
  <ul class="nav-links">
    <li><a href="../dashboard.php">Dashboard</a></li>
    <li><a href="manage-courses.php" class="active">Manage Courses</a></li>
    <li><a href="edit-profile.php">Profile</a></li>
    <li><a href="../../api/logout.php" class="nav-cta">Log out</a></li>
  </ul>
</nav>

<main class="profile-shell" style="margin-top: 5rem; padding: 2rem 1.25rem;">
  <h1 class="profile-header-title">Edit Course</h1>

  <section class="profile-form-wrap" style="max-width: 800px; background: var(--white); padding: 2.5rem; border-radius: 24px; border: 1px solid var(--border); box-shadow: 0 4px 24px rgba(13,17,23,0.04);">
    
    <?php foreach ($errors as $err): ?>
      <div class="alert alert-error">✕ <?= e($err) ?></div>
    <?php endforeach; ?>

    <?php if ($success !== ''): ?>
      <div class="alert alert-success">✓ <?= e($success) ?></div>
    <?php endif; ?>

    <form method="POST" action="edit-course.php?id=<?= urlencode($courseId) ?>">
      <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>" />

      <div class="profile-form-grid">
        <div class="profile-field profile-field-full">
          <label for="title">Course Title</label>
          <input id="title" name="title" type="text" maxlength="255" required value="<?= e($course['title'] ?? '') ?>" />
        </div>

        <div class="profile-field">
          <label for="category">Category</label>
          <select id="category" name="category" required>
            <option value="">Select Category</option>
            <option value="Law & Justice" <?= ($course['category'] ?? '') === 'Law & Justice' ? 'selected' : '' ?>>Law & Justice</option>
            <option value="Technology & Computer Science" <?= ($course['category'] ?? '') === 'Technology & Computer Science' ? 'selected' : '' ?>>Technology & Computer Science</option>
            <option value="Business & Compliance" <?= ($course['category'] ?? '') === 'Business & Compliance' ? 'selected' : '' ?>>Business & Compliance</option>
            <option value="Personal Development" <?= ($course['category'] ?? '') === 'Personal Development' ? 'selected' : '' ?>>Personal Development</option>
          </select>
        </div>

        <div class="profile-field">
          <label for="difficulty">Difficulty Level</label>
          <select id="difficulty" name="difficulty" required>
            <option value="">Select Level</option>
            <option value="Beginner" <?= ($course['difficulty'] ?? '') === 'Beginner' ? 'selected' : '' ?>>Beginner</option>
            <option value="Intermediate" <?= ($course['difficulty'] ?? '') === 'Intermediate' ? 'selected' : '' ?>>Intermediate</option>
            <option value="Advanced" <?= ($course['difficulty'] ?? '') === 'Advanced' ? 'selected' : '' ?>>Advanced</option>
          </select>
        </div>

        <div class="profile-field">
          <label for="price">Price (₹)</label>
          <input id="price" name="price" type="number" min="0" required value="<?= e((string)($course['price'] ?? 0)) ?>" />
          <p style="color:var(--ink-soft);font-size:0.75rem;margin-top:0.25rem;">Enter 0 for free courses.</p>
        </div>

        <div class="profile-field">
          <label for="status">Publication Status</label>
          <select id="status" name="status" required>
            <option value="draft" <?= ($course['status'] ?? '') === 'draft' ? 'selected' : '' ?>>Draft (Private)</option>
            <option value="published" <?= ($course['status'] ?? '') === 'published' ? 'selected' : '' ?>>Published (Public)</option>
          </select>
        </div>

        <div class="profile-field profile-field-full">
          <label for="imageUrl">Custom Image URL (Optional)</label>
          <input id="imageUrl" name="imageUrl" type="url" value="<?= e($course['imageUrl'] ?? '') ?>" placeholder="https://example.com/image.png" />
          <p style="color:var(--ink-soft);font-size:0.75rem;margin-top:0.25rem;">Leave blank to automatically use our high-quality category banners.</p>
        </div>

        <div class="profile-field profile-field-full">
          <label for="description">Course Description</label>
          <textarea id="description" name="description" rows="6" placeholder="Provide an in-depth summary of what students will learn..." required><?= e($course['description'] ?? '') ?></textarea>
        </div>

        <div class="form-btn-row">
          <a href="manage-courses.php" class="btn-pill btn-pill-outline" style="text-decoration:none; display:inline-flex; align-items:center; justify-content:center;">Cancel</a>
          <button class="btn-pill btn-pill-primary" type="submit">Save Changes</button>
        </div>
      </div>
    </form>
  </section>
</main>

<script src="../../assets/js/script.js"></script>
</body>
</html>
