<?php
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

$db = get_firestore();
$filter_field = $is_teacher ? 'teacherId' : 'organizationId';
$org_courses = $db->query('courses', [
    [$filter_field, 'EQUAL', $user['id']]
], 200);

// Sort by createdAt descending
if (!empty($org_courses)) {
    usort($org_courses, function($a, $b) {
        return strcmp($b['createdAt'] ?? '', $a['createdAt'] ?? '');
    });
}

function diffLabel(string $diff): string
{
    return ucfirst(strtolower($diff));
}

function diffBg(string $diff): string
{
    $d = strtolower($diff);
    if ($d === 'beginner') return '#DCFCE7';
    if ($d === 'intermediate') return '#FEF3C7';
    return '#FEE2E2';
}

function diffColor(string $diff): string
{
    $d = strtolower($diff);
    if ($d === 'beginner') return '#15803D';
    if ($d === 'intermediate') return '#B45309';
    return '#B91C1C';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Manage Courses — Lawable</title>
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600;700;800&family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet" />
  <link rel="stylesheet" href="../../assets/css/lawable.css" />
  <style>
    .manage-header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 2rem;
      flex-wrap: wrap;
      gap: 1rem;
    }
    .manage-title-area h1 {
      font-family: 'Playfair Display', serif;
      font-size: 2rem;
      color: var(--ink);
    }
    .manage-table-card {
      background: var(--white);
      border: 1px solid var(--border);
      border-radius: var(--radius-lg);
      box-shadow: var(--shadow);
      overflow: hidden;
      margin-bottom: 2rem;
    }
    .manage-table-wrap {
      overflow-x: auto;
    }
    .manage-table {
      width: 100%;
      border-collapse: collapse;
      text-align: left;
      font-size: 0.9rem;
    }
    .manage-table th {
      background: var(--page-bg);
      color: var(--ink-soft);
      font-weight: 600;
      padding: 1rem 1.25rem;
      border-bottom: 1px solid var(--border);
      text-transform: uppercase;
      font-size: 0.75rem;
      letter-spacing: 0.05em;
    }
    .manage-table td {
      padding: 1rem 1.25rem;
      border-bottom: 1px solid var(--border);
      color: var(--ink-mid);
      vertical-align: middle;
    }
    .manage-table tr:last-child td {
      border-bottom: none;
    }
    .course-thumb-mini {
      width: 48px;
      height: 48px;
      border-radius: 8px;
      background-size: cover;
      background-position: center;
      background-color: var(--gold-lt);
      flex-shrink: 0;
    }
    .course-cell {
      display: flex;
      align-items: center;
      gap: 1rem;
    }
    .course-title-text {
      font-weight: 600;
      color: var(--ink);
    }
    .status-badge {
      display: inline-flex;
      align-items: center;
      padding: 0.25rem 0.6rem;
      border-radius: 50px;
      font-size: 0.75rem;
      font-weight: 600;
      text-transform: uppercase;
    }
    .status-published {
      background: #DCFCE7;
      color: #15803D;
    }
    .status-draft {
      background: #F3F4F6;
      color: #4B5563;
    }
    .diff-badge {
      display: inline-block;
      padding: 0.2rem 0.5rem;
      border-radius: 4px;
      font-size: 0.72rem;
      font-weight: 600;
    }
    .empty-courses {
      text-align: center;
      padding: 4rem 2rem;
      color: var(--ink-soft);
    }
    .empty-icon {
      font-size: 3rem;
      margin-bottom: 1rem;
    }
    .btn-action-edit {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      padding: 0.45rem 1rem;
      font-size: 0.8rem;
      font-weight: 600;
      border-radius: 9999px;
      border: 1px solid var(--border);
      background: transparent;
      color: var(--ink-mid);
      text-decoration: none;
      transition: all 0.2s;
      cursor: pointer;
    }
    .btn-action-edit:hover {
      background: #FAF7F2;
      border-color: var(--gold);
      color: var(--gold-dark);
    }
    .btn-action-lessons {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      padding: 0.45rem 1rem;
      font-size: 0.8rem;
      font-weight: 600;
      border-radius: 9999px;
      border: 1px solid transparent;
      background: var(--gold);
      color: white;
      text-decoration: none;
      transition: all 0.2s;
      cursor: pointer;
    }
    .btn-action-lessons:hover {
      background: var(--gold-dark);
      transform: translateY(-1px);
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

<main class="profile-shell" style="max-width:1200px;margin-top: calc(var(--nav-h) + 2rem);">
  <div class="manage-header">
    <div class="manage-title-area">
      <a href="../dashboard.php" style="text-decoration:none;color:var(--gold);font-weight:500;font-size:0.88rem;">← Dashboard</a>
      <h1>Manage Courses</h1>
    </div>
    <a href="create-course.php" class="btn-primary" style="text-decoration:none;">+ Create Course</a>
  </div>

  <div class="manage-table-card">
    <div class="manage-table-wrap">
      <?php if (empty($org_courses)): ?>
        <div class="empty-courses">
          <div class="empty-icon">📖</div>
          <h3>No courses created yet</h3>
          <p style="margin-top:0.5rem;margin-bottom:1.5rem;font-size:0.88rem;">Share your legal expertise by publishing your first course.</p>
          <a href="create-course.php" class="btn-primary" style="text-decoration:none;display:inline-flex;">Create Your First Course</a>
        </div>
      <?php else: ?>
        <table class="manage-table">
          <thead>
            <tr>
              <th>Course</th>
              <th>Category</th>
              <th>Difficulty</th>
              <th>Price</th>
              <th>Students Enrolled</th>
              <th>Status</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($org_courses as $c):
              // Determine course image
              $courseImage = '';
              $titleLower = strtolower($c['title'] ?? '');
              $catLower = strtolower($c['category'] ?? '');
              if (!empty($c['imageUrl'])) {
                  $courseImage = $c['imageUrl'];
              } elseif (str_contains($titleLower, 'python') || str_contains($titleLower, 'data structure') || str_contains($titleLower, 'algorithm')) {
                  $courseImage = '../../assets/images/dsa_python.png';
              } elseif (str_contains($titleLower, 'web dev') || str_contains($titleLower, 'bootcamp') || str_contains($titleLower, 'javascript') || str_contains($titleLower, 'html') || str_contains($titleLower, 'css')) {
                  $courseImage = '../../assets/images/web_dev.png';
              } elseif (str_contains($titleLower, 'database') || str_contains($titleLower, 'sql')) {
                  $courseImage = '../../assets/images/database_sql.png';
              } elseif (str_contains($catLower, 'law') || str_contains($catLower, 'justice')) {
                  $courseImage = '../../assets/images/constitutional_law.png';
              } elseif (str_contains($catLower, 'technology') || str_contains($catLower, 'computer science')) {
                  $courseImage = '../../assets/images/web_dev.png';
              } elseif (str_contains($catLower, 'business') || str_contains($catLower, 'compliance')) {
                  $courseImage = '../../assets/images/business_compliance.png';
              } elseif (str_contains($catLower, 'personal') || str_contains($catLower, 'development') || str_contains($catLower, 'communication')) {
                  $courseImage = '../../assets/images/personal_development.png';
              } else {
                  $courseImage = '../../assets/images/constitutional_law.png';
              }

              $diff = $c['difficulty'] ?? 'Beginner';
              $is_free = (float) ($c['price'] ?? 0.0) <= 0.0;
            ?>
              <tr>
                <td>
                  <div class="course-cell">
                    <div class="course-thumb-mini" style="background-image: url('<?= e($courseImage) ?>');"></div>
                    <div>
                      <div class="course-title-text"><?= e($c['title']) ?></div>
                      <div style="font-size:0.75rem;color:var(--ink-soft);margin-top:0.15rem;">Created <?= date('M j, Y', strtotime($c['createdAt'])) ?></div>
                    </div>
                  </div>
                </td>
                <td><?= e($c['category'] ?? '') ?></td>
                <td>
                  <span class="diff-badge" style="background:<?= diffBg($diff) ?>;color:<?= diffColor($diff) ?>">
                    <?= diffLabel($diff) ?>
                  </span>
                </td>
                <td>
                  <strong><?= $is_free ? '<span style="color:var(--green);">Free</span>' : '₹' . number_format((float)$c['price']) ?></strong>
                </td>
                <td>
                  <strong><?= number_format((int)($c['enrollment_count'] ?? 0)) ?></strong> students
                </td>
                <td>
                  <span class="status-badge status-<?= $c['status'] ?>">
                    <?= e($c['status']) ?>
                  </span>
                </td>
                <td>
                  <div style="display:flex; gap:0.5rem;">
                    <a href="edit-course.php?id=<?= urlencode($c['__id']) ?>" class="btn-action-edit">Edit</a>
                    <a href="manage-lessons.php?id=<?= urlencode($c['__id']) ?>" class="btn-action-lessons">Lessons</a>
                  </div>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      <?php endif; ?>
    </div>
  </div>
</main>

<script src="../../assets/js/script.js"></script>
</body>
</html>
