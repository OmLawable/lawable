<?php
require_once __DIR__ . '/../../includes/functions.php';
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
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Create Course — Lawable</title>
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600;700;800&family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet" />
  <link rel="stylesheet" href="../../assets/css/lawable.css" />
  <style>
    /* Premium overrides */
    .profile-card-icon svg {
      color: var(--gold);
    }
    .form-btn-row {
      display: flex;
      justify-content: flex-end;
      gap: 1rem;
      grid-column: 1 / -1;
      margin-top: 1.5rem;
    }
    .btn-secondary {
      background: transparent;
      border: 1px solid var(--border);
      color: var(--ink);
      padding: 0.75rem 1.5rem;
      border-radius: var(--radius);
      font-size: 0.9rem;
      font-weight: 500;
      cursor: pointer;
      transition: background 0.2s, border-color 0.2s;
      text-decoration: none;
      display: inline-flex;
      align-items: center;
      justify-content: center;
    }
    .btn-secondary:hover {
      background: var(--page-bg);
      border-color: var(--ink-soft);
    }
    .profile-alert {
      margin-bottom: 1.5rem;
      padding: 1rem;
      border-radius: var(--radius);
      font-size: 0.9rem;
      display: none;
    }
    .profile-alert-error {
      background: #FEE2E2;
      color: #DC2626;
      border: 1px solid #FCA5A5;
    }
    .profile-alert-success {
      background: #DCFCE7;
      color: #16A34A;
      border: 1px solid #86EFAC;
    }
  </style>
</head>
<body>

<nav id="navbar" class="scrolled">
  <a href="../dashboard.php" class="nav-logo">Law<span>able</span></a>
  <ul class="nav-links">
    <li><a href="../dashboard.php">Dashboard</a></li>
    <li><a href="manage-courses.php" class="active">Manage Courses</a></li>
    <li><a href="edit-profile.php">Profile</a></li>
    <li><a href="../../api/logout.php" class="nav-cta">Log out</a></li>
  </ul>
  <button class="nav-hamburger" id="hamburger" aria-label="Menu">
    <span></span><span></span><span></span>
  </button>
</nav>

<nav class="nav-drawer" id="drawer">
  <a href="../dashboard.php">Dashboard</a>
  <a href="manage-courses.php">Manage Courses</a>
  <a href="edit-profile.php">Profile</a>
  <a href="../../api/logout.php" class="drawer-cta">Log out</a>
</nav>

<main class="profile-shell">
  <section class="profile-form-wrap">
    
    <div class="profile-alert profile-alert-error" id="errorAlert"></div>
    <div class="profile-alert profile-alert-success" id="successAlert"></div>

    <form class="profile-form" id="createCourseForm" novalidate>
      <div class="profile-card">
        <div class="profile-card-header">
          <span class="profile-card-icon" aria-hidden="true">
            <svg viewBox="0 0 24 24" fill="none">
              <path d="M12 4.5L3 9l9 4.5 9-4.5-9-4.5z" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
              <path d="M3 14.25l9 4.5 9-4.5M3 9v5.25M21 9v5.25" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
          </span>
          <div>
            <h1>Create New Course</h1>
            <p style="color:var(--ink-soft);font-size:0.88rem;margin-top:0.2rem;">Publish learning materials under <?= e($user['organization_name'] ?? $user['name']) ?></p>
          </div>
        </div>

        <div class="profile-card-body">
          <h2 class="profile-section-title">Course Information</h2>

          <div class="profile-form-grid">
            <div class="profile-field profile-field-full">
              <label for="title">Course Title</label>
              <input id="title" name="title" type="text" maxlength="255" required placeholder="e.g., Fundamentals of Intellectual Property" />
            </div>

            <div class="profile-field">
              <label for="category">Category</label>
              <select id="category" name="category" required>
                <option value="">Select Category</option>
                <option value="Law & Justice">Law & Justice</option>
                <option value="Technology & Computer Science">Technology & Computer Science</option>
                <option value="Business & Compliance">Business & Compliance</option>
                <option value="Personal Development">Personal Development</option>
              </select>
            </div>

            <div class="profile-field">
              <label for="difficulty">Difficulty Level</label>
              <select id="difficulty" name="difficulty" required>
                <option value="">Select Level</option>
                <option value="Beginner">Beginner</option>
                <option value="Intermediate">Intermediate</option>
                <option value="Advanced">Advanced</option>
              </select>
            </div>

            <div class="profile-field">
              <label for="price">Price (₹)</label>
              <input id="price" name="price" type="number" min="0" value="0" required />
              <p style="color:var(--ink-soft);font-size:0.75rem;margin-top:0.25rem;">Enter 0 for free courses.</p>
            </div>

            <div class="profile-field">
              <label for="status">Publication Status</label>
              <select id="status" name="status" required>
                <option value="draft">Draft (Private)</option>
                <option value="published">Published (Public)</option>
              </select>
            </div>

            <div class="profile-field profile-field-full">
              <label for="imageUrl">Custom Image URL (Optional)</label>
              <input id="imageUrl" name="imageUrl" type="url" placeholder="https://example.com/image.png" />
              <p style="color:var(--ink-soft);font-size:0.75rem;margin-top:0.25rem;">Leave blank to automatically use our high-quality category banners.</p>
            </div>

            <div class="profile-field profile-field-full">
              <label for="description">Course Description</label>
              <textarea id="description" name="description" rows="6" placeholder="Provide an in-depth summary of what students will learn, target audience, and course prerequisites..." required></textarea>
            </div>

            <div class="form-btn-row">
              <a href="../dashboard.php" class="btn-secondary">Cancel</a>
              <button class="btn-primary" type="submit" id="submitBtn">Create Course</button>
            </div>
          </div>
        </div>
      </div>
    </form>
  </section>
</main>

<script src="../../assets/js/script.js"></script>
<script>
document.getElementById('createCourseForm').addEventListener('submit', function(e) {
  e.preventDefault();
  
  var form = e.target;
  var submitBtn = document.getElementById('submitBtn');
  var errorAlert = document.getElementById('errorAlert');
  var successAlert = document.getElementById('successAlert');

  // Hide existing alerts
  errorAlert.style.display = 'none';
  successAlert.style.display = 'none';

  // Read fields
  var title = document.getElementById('title').value.trim();
  var category = document.getElementById('category').value;
  var difficulty = document.getElementById('difficulty').value;
  var price = document.getElementById('price').value.trim();
  var description = document.getElementById('description').value.trim();
  var status = document.getElementById('status').value;
  var imageUrl = document.getElementById('imageUrl').value.trim();

  // Basic validation
  if (!title || !category || !difficulty || !description) {
    errorAlert.textContent = 'Please fill out all required fields.';
    errorAlert.style.display = 'block';
    window.scrollTo({top: 0, behavior: 'smooth'});
    return;
  }

  submitBtn.disabled = true;
  submitBtn.textContent = 'Saving...';

  var payload = {
    title: title,
    category: category,
    difficulty: difficulty,
    price: price,
    description: description,
    status: status,
    imageUrl: imageUrl
  };

  var xhr = new XMLHttpRequest();
  xhr.open('POST', '../../api/organization/create-course-api.php', true);
  xhr.setRequestHeader('Content-Type', 'application/json');
  xhr.onload = function() {
    submitBtn.disabled = false;
    submitBtn.textContent = 'Create Course';
    
    if (xhr.status === 200 || xhr.status === 400) {
      try {
        var res = JSON.parse(xhr.responseText);
        if (res.success) {
          successAlert.textContent = res.message + ' Redirecting to Course Management...';
          successAlert.style.display = 'block';
          window.scrollTo({top: 0, behavior: 'smooth'});
          setTimeout(function() {
            window.location.href = 'manage-courses.php';
          }, 1500);
        } else {
          errorAlert.textContent = res.message || 'Failed to create course.';
          errorAlert.style.display = 'block';
          window.scrollTo({top: 0, behavior: 'smooth'});
        }
      } catch(err) {
        errorAlert.textContent = 'Received invalid response from server.';
        errorAlert.style.display = 'block';
      }
    } else {
      errorAlert.textContent = 'Server returned error status: ' + xhr.status;
      errorAlert.style.display = 'block';
    }
  };
  xhr.onerror = function() {
    submitBtn.disabled = false;
    submitBtn.textContent = 'Create Course';
    errorAlert.textContent = 'Connection error. Please try again.';
    errorAlert.style.display = 'block';
  };
  xhr.send(JSON.stringify(payload));
});
</script>
</body>
</html>
