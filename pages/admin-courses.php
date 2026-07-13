<?php
declare(strict_types=1);

require_once __DIR__ . '/../backend/includes/functions.php';
start_secure_session();

$user = require_login('admin');

$pdo = get_pdo();

/* ── Fetch courses with organization names and enrollment counts ── */
$stmt = $pdo->query("
    SELECT
        c.id,
        c.title,
        c.description,
        c.price,
        c.status,
        c.created_at,
        c.updated_at,
        o.organization_name,
        COALESCE(e.enrollment_count, 0) AS enrollment_count
    FROM courses c
    LEFT JOIN organizations o ON c.organization_id = o.id
    LEFT JOIN (
        SELECT course_id, COUNT(*) AS enrollment_count
        FROM course_enrollments
        GROUP BY course_id
    ) e ON c.id = e.course_id
    ORDER BY c.created_at DESC
");
$all_courses = $stmt->fetchAll();

/* Stats */
$total_courses      = count($all_courses);
$published_count    = 0;
$draft_count        = 0;
$archived_count     = 0;
$total_enrollments  = 0;

foreach ($all_courses as $course) {
    switch ($course['status']) {
        case 'published': $published_count++; break;
        case 'draft':     $draft_count++;     break;
        case 'archived':  $archived_count++;  break;
    }
    $total_enrollments += (int) $course['enrollment_count'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Courses — Lawable Admin</title>
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600;700;800&family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet" />
  <link rel="stylesheet" href="../assets/css/lawable.css" />
  <style>
    /* ─── Same design tokens as dashboard ──────────────── */
    :root {
      --gold: #C9933A;
      --gold-dk: #A8732A;
      --gold-lt: #F4E4C3;
      --cream: #FCF8F1;
      --page-bg: #F6EEF6;
      --white: #FFFFFF;
      --ink: #0D1117;
      --ink-mid: #374151;
      --ink-soft: #6B7280;
      --border: #E5E0D8;
      --green: #16a34a;
      --green-bg: #DCFCE7;
      --yellow: #EAB308;
      --yellow-bg: #FEF9C3;
      --red: #DC2626;
      --red-bg: #FEE2E2;
      --blue: #2563EB;
      --blue-bg: #DBEAFE;
      --purple: #7C3AED;
      --purple-bg: #EDE9FE;
      --nav-h: 68px;
      --radius: 12px;
      --radius-lg: 16px;
      --shadow: 0 4px 24px rgba(13,17,23,0.08);
      --shadow-lg: 0 12px 40px rgba(13,17,23,0.12);
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
      --green: #22C55E;
      --green-bg: #064E3B;
      --yellow: #EAB308;
      --yellow-bg: #422006;
      --red: #EF4444;
      --red-bg: #450A0A;
      --blue: #60A5FA;
      --blue-bg: #1E3A5F;
      --purple: #A78BFA;
      --purple-bg: #3B2070;
      --shadow: 0 4px 24px rgba(0,0,0,0.40);
      --shadow-lg: 0 12px 40px rgba(0,0,0,0.50);
    }

    body {
      background: var(--page-bg);
      font-family: 'Inter', sans-serif;
      color: var(--ink);
      min-height: 100vh;
    }

    .dashboard-page {
      padding-top: calc(var(--nav-h) + 24px);
      min-height: 100vh;
    }

    /* ─── Page Header ──────────────────────────────────── */
    .dash-header {
      display: flex;
      align-items: center;
      justify-content: space-between;
      flex-wrap: wrap;
      gap: 1rem;
      padding: 0 2rem;
      margin: 0 auto 1.5rem;
      max-width: 1440px;
    }
    .dash-header-left {
      display: flex;
      align-items: center;
      gap: 1rem;
    }
    .dash-header-left h1 {
      font-family: 'Playfair Display', serif;
      font-size: 1.75rem;
      font-weight: 700;
      color: var(--ink);
      margin: 0;
    }
    .dash-header-left .back-link {
      font-size: 0.85rem;
      font-weight: 500;
      color: var(--ink-soft);
      text-decoration: none;
      display: inline-flex;
      align-items: center;
      gap: 0.35rem;
      padding: 0.35rem 0.75rem;
      border-radius: 6px;
      border: 1px solid var(--border);
      transition: border-color .2s, color .2s;
    }
    .dash-header-left .back-link:hover {
      border-color: var(--gold);
      color: var(--gold);
    }

    /* ─── Content ──────────────────────────────────────── */
    .dash-content {
      max-width: 1440px;
      margin: 0 auto;
      padding: 0 2rem 3rem;
    }

    /* ─── Mini stats row ───────────────────────────────── */
    .stat-row {
      display: grid;
      grid-template-columns: repeat(4, 1fr);
      gap: 1rem;
      margin-bottom: 1.5rem;
    }
    .stat-card {
      background: var(--white);
      border-radius: var(--radius-lg);
      padding: 1.25rem 1.5rem;
      box-shadow: var(--shadow);
      border: 1px solid rgba(229,224,216,0.5);
      transition: transform .2s, box-shadow .2s;
      display: flex;
      align-items: flex-start;
      gap: 1rem;
    }
    .stat-card:hover {
      transform: translateY(-2px);
      box-shadow: var(--shadow-lg);
    }
    .stat-card-icon {
      width: 44px; height: 44px;
      border-radius: 10px;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 1.3rem;
      flex-shrink: 0;
    }
    .stat-card-icon.courses-all    { background: var(--purple-bg); }
    .stat-card-icon.published      { background: var(--green-bg); }
    .stat-card-icon.draft          { background: var(--yellow-bg); }
    .stat-card-icon.enrollments    { background: var(--blue-bg); }
    .stat-card-body {}
    .stat-card-label {
      font-size: 0.8rem;
      font-weight: 500;
      color: var(--ink-soft);
      margin-bottom: 0.15rem;
    }
    .stat-card-value {
      font-family: 'Playfair Display', serif;
      font-size: 1.75rem;
      font-weight: 700;
      color: var(--ink);
      line-height: 1.2;
    }
    .stat-card-sub {
      font-size: 0.75rem;
      color: var(--ink-soft);
      margin-top: 0.15rem;
    }

    /* ─── Filters / Search bar ─────────────────────────── */
    .courses-toolbar {
      display: flex;
      align-items: center;
      justify-content: space-between;
      flex-wrap: wrap;
      gap: 0.75rem;
      background: var(--white);
      border-radius: var(--radius-lg);
      padding: 1rem 1.5rem;
      box-shadow: var(--shadow);
      border: 1px solid rgba(229,224,216,0.5);
      margin-bottom: 1.25rem;
    }
    .courses-search {
      display: flex;
      align-items: center;
      gap: 0.5rem;
      flex: 1;
      min-width: 200px;
    }
    .courses-search input {
      flex: 1;
      padding: 0.6rem 0.85rem;
      border: 1.5px solid var(--border);
      border-radius: 8px;
      font-family: 'Inter', sans-serif;
      font-size: 0.85rem;
      color: var(--ink);
      background: var(--cream);
      outline: none;
      transition: border-color .2s, box-shadow .2s;
    }
    .courses-search input:focus {
      border-color: var(--gold);
      box-shadow: 0 0 0 3px rgba(201,147,58,0.12);
    }
    .courses-search input::placeholder {
      color: var(--ink-soft);
    }
    .courses-filters {
      display: flex;
      align-items: center;
      gap: 0.5rem;
    }
    .courses-filters select {
      appearance: none;
      background: var(--white);
      border: 1.5px solid var(--border);
      border-radius: 8px;
      padding: 0.5rem 2rem 0.5rem 0.85rem;
      font-size: 0.85rem;
      font-weight: 500;
      font-family: 'Inter', sans-serif;
      color: var(--ink-mid);
      cursor: pointer;
      background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='10' height='6' viewBox='0 0 10 6'%3E%3Cpath fill='%236B7280' d='M1 1l4 4 4-4'/%3E%3C/svg%3E");
      background-repeat: no-repeat;
      background-position: right 0.65rem center;
      transition: border-color .2s;
    }
    .courses-filters select:focus {
      outline: none;
      border-color: var(--gold);
    }

    .course-count-badge {
      font-size: 0.8rem;
      font-weight: 600;
      color: var(--ink-soft);
      padding: 0.35rem 0.85rem;
      background: var(--cream);
      border-radius: 20px;
      white-space: nowrap;
    }

    /* ─── Courses Table ────────────────────────────────── */
    .courses-card {
      background: var(--white);
      border-radius: var(--radius-lg);
      box-shadow: var(--shadow);
      border: 1px solid rgba(229,224,216,0.5);
      overflow: hidden;
    }
    .courses-table {
      width: 100%;
      border-collapse: collapse;
    }
    .courses-table thead {
      background: #FAFAF8;
    }
    body.dark-theme .courses-table thead {
      background: rgba(255,255,255,0.04);
    }
    .courses-table th {
      text-align: left;
      font-size: 0.7rem;
      font-weight: 600;
      text-transform: uppercase;
      letter-spacing: 0.08em;
      color: var(--ink-soft);
      padding: 1rem 1.25rem;
      border-bottom: 1px solid var(--border);
    }
    .courses-table td {
      padding: 0.9rem 1.25rem;
      border-bottom: 1px solid rgba(229,224,216,0.4);
      font-size: 0.85rem;
      color: var(--ink-mid);
      vertical-align: middle;
    }
    .courses-table tbody tr {
      transition: background .15s;
    }
    .courses-table tbody tr:hover {
      background: var(--cream);
    }
    .courses-table tbody tr:last-child td {
      border-bottom: none;
    }

    /* Course entity (icon + title/desc) */
    .course-entity {
      display: flex;
      align-items: flex-start;
      gap: 0.75rem;
    }
    .course-thumb-icon {
      width: 40px; height: 40px;
      border-radius: 10px;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 1.1rem;
      flex-shrink: 0;
      background: var(--gold-lt);
    }
    .course-info {}
    .course-title {
      font-weight: 600;
      color: var(--ink);
      display: block;
      line-height: 1.3;
    }
    .course-desc {
      font-size: 0.75rem;
      color: var(--ink-soft);
      display: -webkit-box;
      -webkit-line-clamp: 2;
      -webkit-box-orient: vertical;
      overflow: hidden;
      line-height: 1.4;
      margin-top: 0.15rem;
    }

    /* Price badge */
    .price-badge {
      font-weight: 700;
      font-family: 'Playfair Display', serif;
      font-size: 0.95rem;
      color: var(--ink);
      white-space: nowrap;
    }
    .price-badge.free {
      color: var(--green);
      font-size: 0.8rem;
    }

    /* Status badge */
    .status-badge {
      display: inline-flex;
      align-items: center;
      gap: 0.3rem;
      font-size: 0.72rem;
      font-weight: 600;
      padding: 0.25rem 0.75rem;
      border-radius: 20px;
      white-space: nowrap;
    }
    .status-badge.published {
      background: var(--green-bg);
      color: var(--green);
    }
    .status-badge.draft {
      background: var(--yellow-bg);
      color: #92400E;
    }
    .status-badge.archived {
      background: var(--red-bg);
      color: var(--red);
    }

    .org-name {
      font-size: 0.8rem;
      color: var(--ink-soft);
    }
    .org-name strong {
      color: var(--ink-mid);
    }

    .enroll-count {
      font-weight: 600;
      font-size: 0.9rem;
      color: var(--ink);
    }

    /* Action buttons */
    .course-actions {
      display: flex;
      gap: 0.4rem;
    }
    .course-btn {
      padding: 0.3rem 0.65rem;
      border-radius: 6px;
      font-size: 0.72rem;
      font-weight: 600;
      border: none;
      cursor: pointer;
      transition: opacity .2s, transform .15s;
      font-family: 'Inter', sans-serif;
      text-decoration: none;
      display: inline-flex;
      align-items: center;
      gap: 0.2rem;
    }
    .course-btn:hover {
      opacity: 0.8;
      transform: translateY(-1px);
    }
    .course-btn.view   { background: var(--blue-bg); color: var(--blue); }
    .course-btn.edit   { background: var(--gold-lt); color: var(--gold-dk); }

    /* Empty state */
    .empty-state {
      text-align: center;
      padding: 3rem 1.5rem;
      color: var(--ink-soft);
    }
    .empty-state-icon {
      font-size: 2.5rem;
      margin-bottom: 0.75rem;
    }
    .empty-state-text {
      font-size: 0.95rem;
    }

    /* ─── Responsive ───────────────────────────────────── */
    @media (max-width: 1100px) {
      .stat-row { grid-template-columns: repeat(2, 1fr); }
    }
    @media (max-width: 768px) {
      .dash-header { padding: 0 1rem; }
      .dash-content { padding: 0 1rem 2rem; }
      .stat-row { grid-template-columns: repeat(2, 1fr); }
      .courses-toolbar { flex-direction: column; align-items: stretch; }
      .courses-search { min-width: 0; }
      .courses-filters { flex-wrap: wrap; }
      .courses-table th:nth-child(3),
      .courses-table td:nth-child(3) { display: none; }
      .courses-table th:nth-child(5),
      .courses-table td:nth-child(5) { display: none; }
    }
    @media (max-width: 500px) {
      .stat-row { grid-template-columns: 1fr; }
      .courses-table th:nth-child(4),
      .courses-table td:nth-child(4) { display: none; }
    }
  </style>
</head>
<body>

<!-- ─── NAVBAR ─────────────────────────────────────────────── -->
<nav id="navbar" class="scrolled">
  <a href="admin-dashboard.php" class="nav-logo">Law<span>able</span></a>
  <ul class="nav-links">
    <li><a href="admin-dashboard.php">Dashboard</a></li>
    <li><a href="admin-users.php">Users</a></li>
    <li><a href="admin-courses.php" class="active">Courses</a></li>
    <li><a href="admin-verifications.php">Verifications</a></li>
    <li>
      <button class="theme-toggle" type="button" data-theme-toggle aria-label="Switch to dark theme" aria-pressed="false">
        <span class="theme-toggle-icon" aria-hidden="true">D</span>
        <span class="theme-toggle-text">Dark</span>
      </button>
    </li>
    <li><a href="../api/logout.php" class="nav-cta">Log out</a></li>
  </ul>
  <button class="nav-hamburger" id="hamburger" aria-label="Menu">
    <span></span><span></span><span></span>
  </button>
</nav>
<nav class="nav-drawer" id="drawer">
  <a href="admin-dashboard.php">Dashboard</a>
  <a href="admin-users.php">Users</a>
  <a href="admin-courses.php">Courses</a>
  <a href="admin-verifications.php">Verifications</a>
  <button class="theme-toggle drawer-theme-toggle" type="button" data-theme-toggle aria-label="Switch to dark theme" aria-pressed="false">
    <span class="theme-toggle-icon" aria-hidden="true">D</span>
    <span class="theme-toggle-text">Dark theme</span>
  </button>
  <a href="../api/logout.php" class="drawer-cta">Log out</a>
</nav>

<!-- ─── PAGE ───────────────────────────────────────────────── -->
<div class="dashboard-page">

  <!-- Header -->
  <div class="dash-header">
    <div class="dash-header-left">
      <a href="admin-dashboard.php" class="back-link">← Dashboard</a>
      <h1>Courses</h1>
    </div>
    <span class="course-count-badge"><?= number_format($total_courses) ?> total</span>
  </div>

  <div class="dash-content">

    <!-- Mini stats -->
    <div class="stat-row">
      <div class="stat-card">
        <div class="stat-card-icon courses-all">📚</div>
        <div class="stat-card-body">
          <div class="stat-card-label">Total Courses</div>
          <div class="stat-card-value"><?= number_format($total_courses) ?></div>
          <div class="stat-card-sub">
            <?= number_format($published_count) ?> published
            <?= $draft_count > 0 ? '· '.number_format($draft_count).' draft' : '' ?>
            <?= $archived_count > 0 ? '· '.number_format($archived_count).' archived' : '' ?>
          </div>
        </div>
      </div>
      <div class="stat-card">
        <div class="stat-card-icon published">✓</div>
        <div class="stat-card-body">
          <div class="stat-card-label">Published</div>
          <div class="stat-card-value" style="color:var(--green);"><?= number_format($published_count) ?></div>
          <div class="stat-card-sub">Active & available</div>
        </div>
      </div>
      <div class="stat-card">
        <div class="stat-card-icon draft">✎</div>
        <div class="stat-card-body">
          <div class="stat-card-label">Drafts</div>
          <div class="stat-card-value" style="color:#92400E;"><?= number_format($draft_count) ?></div>
          <div class="stat-card-sub">Awaiting publishing</div>
        </div>
      </div>
      <div class="stat-card">
        <div class="stat-card-icon enrollments">🎓</div>
        <div class="stat-card-body">
          <div class="stat-card-label">Total Enrollments</div>
          <div class="stat-card-value"><?= number_format($total_enrollments) ?></div>
          <div class="stat-card-sub">Across all courses</div>
        </div>
      </div>
    </div>

    <!-- Toolbar: search + filters -->
    <div class="courses-toolbar">
      <div class="courses-search">
        <input type="text" id="searchInput" placeholder="Search courses by title, organization…" oninput="filterTable()" />
      </div>
      <div class="courses-filters">
        <select id="statusFilter" onchange="filterTable()">
          <option value="all">All status</option>
          <option value="published">Published</option>
          <option value="draft">Draft</option>
          <option value="archived">Archived</option>
        </select>
        <select id="priceFilter" onchange="filterTable()">
          <option value="all">All prices</option>
          <option value="free">Free</option>
          <option value="paid">Paid</option>
        </select>
      </div>
    </div>

    <!-- Courses table -->
    <div class="courses-card">
      <table class="courses-table">
        <thead>
          <tr>
            <th>Course</th>
            <th>Organization</th>
            <th>Price</th>
            <th>Status</th>
            <th>Enrollments</th>
            <th>Created</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody id="coursesBody">
          <?php if (empty($all_courses)): ?>
            <tr>
              <td colspan="7">
                <div class="empty-state">
                  <div class="empty-state-icon">📚</div>
                  <div class="empty-state-text">No courses found.</div>
                </div>
              </td>
            </tr>
          <?php else: ?>
            <?php foreach ($all_courses as $c): ?>
              <?php
                $is_free = (float) $c['price'] <= 0;
                $org_display = !empty($c['organization_name'])
                  ? e($c['organization_name'])
                  : '<span style="color:var(--ink-soft);font-style:italic;">Platform</span>';
                $date_created = date('M j, Y', strtotime($c['created_at']));
              ?>
              <tr class="course-row"
                  data-status="<?= e($c['status']) ?>"
                  data-price="<?= $is_free ? 'free' : 'paid' ?>"
                  data-search="<?= e(strtolower($c['title'] . ' ' . ($c['organization_name'] ?? ''))) ?>">
                <td>
                  <div class="course-entity">
                    <div class="course-thumb-icon">⚖</div>
                    <div class="course-info">
                      <span class="course-title"><?= e($c['title']) ?></span>
                      <?php if (!empty($c['description'])): ?>
                        <span class="course-desc"><?= e(mb_substr($c['description'], 0, 100)) ?><?= mb_strlen($c['description']) > 100 ? '…' : '' ?></span>
                      <?php endif; ?>
                    </div>
                  </div>
                </td>
                <td><span class="org-name"><?= $org_display ?></span></td>
                <td>
                  <span class="price-badge <?= $is_free ? 'free' : '' ?>">
                    <?= $is_free ? 'Free' : '₹'.number_format((float)$c['price']) ?>
                  </span>
                </td>
                <td>
                  <span class="status-badge <?= e($c['status']) ?>">
                    <?= match($c['status']) {
                      'published' => '✓ Published',
                      'draft'     => '✎ Draft',
                      'archived'  => '✕ Archived',
                      default     => e($c['status'])
                    } ?>
                  </span>
                </td>
                <td>
                  <span class="enroll-count"><?= number_format((int)$c['enrollment_count']) ?></span>
                </td>
                <td style="white-space:nowrap;font-size:0.8rem;color:var(--ink-soft);">
                  <?= $date_created ?>
                </td>
                <td>
                  <div class="course-actions">
                    <a href="#" class="course-btn view">👁 View</a>
                    <a href="#" class="course-btn edit">✎ Edit</a>
                  </div>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>

  </div>
</div>

<script src="../assets/js/script.js"></script>
<script>
(function() {
  'use strict';

  /* Hamburger already handled by script.js — no duplicate listener needed */
})();

/* ── Filter table ── */
function filterTable() {
  const search = document.getElementById('searchInput').value.toLowerCase().trim();
  const status = document.getElementById('statusFilter').value;
  const price  = document.getElementById('priceFilter').value;
  const rows   = document.querySelectorAll('.course-row');

  rows.forEach(function(row) {
    const rowStatus = row.getAttribute('data-status');
    const rowPrice  = row.getAttribute('data-price');
    const rowSearch = row.getAttribute('data-search');

    const matchStatus = status === 'all' || rowStatus === status;
    const matchPrice  = price === 'all' || rowPrice === price;
    const matchSearch = !search || rowSearch.indexOf(search) !== -1;

    row.style.display = (matchStatus && matchPrice && matchSearch) ? '' : 'none';
  });
}
</script>
</body>
</html>
