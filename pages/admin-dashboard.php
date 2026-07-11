<?php
declare(strict_types=1);

require_once __DIR__ . '/../backend/includes/functions.php';
start_secure_session();

$user = require_login('admin');

/* ── Fetch Dashboard Data ────────────────────────────────── */
$pdo   = get_pdo();

/* Stat Cards */
$total_users = (int) $pdo->query("SELECT (SELECT COUNT(*) FROM students WHERE status='active') + (SELECT COUNT(*) FROM organizations)")->fetchColumn();
$total_users_prev = (int) $pdo->query("SELECT (SELECT COUNT(*) FROM students WHERE status='active' AND created_at < NOW() - INTERVAL 30 DAY) + (SELECT COUNT(*) FROM organizations WHERE created_at < NOW() - INTERVAL 30 DAY)")->fetchColumn();
$user_change = $total_users_prev > 0 ? round(($total_users - $total_users_prev) / $total_users_prev * 100, 1) : 0;

$active_students = (int) $pdo->query("SELECT COUNT(*) FROM students WHERE status='active'")->fetchColumn();
$active_students_prev = (int) $pdo->query("SELECT COUNT(*) FROM students WHERE status='active' AND created_at < NOW() - INTERVAL 30 DAY")->fetchColumn();
$student_change = $active_students_prev > 0 ? round(($active_students - $active_students_prev) / $active_students_prev * 100, 1) : 0;

$total_orgs = (int) $pdo->query("SELECT COUNT(*) FROM organizations")->fetchColumn();
$total_orgs_prev = (int) $pdo->query("SELECT COUNT(*) FROM organizations WHERE created_at < NOW() - INTERVAL 30 DAY")->fetchColumn();
$org_change = $total_orgs_prev > 0 ? round(($total_orgs - $total_orgs_prev) / $total_orgs_prev * 100, 1) : 0;

$total_verified_orgs = (int) $pdo->query("SELECT COUNT(*) FROM organizations")->fetchColumn(); // placeholder
$pending_verifications_total = (int) $pdo->query("SELECT COUNT(*) FROM verification_documents WHERE status IN ('pending','under_review')")->fetchColumn();

$total_courses = (int) $pdo->query("SELECT COUNT(*) FROM courses WHERE status='published'")->fetchColumn();
$total_courses_prev = (int) $pdo->query("SELECT COUNT(*) FROM courses WHERE status='published' AND created_at < NOW() - INTERVAL 30 DAY")->fetchColumn();
$courses_change = $total_courses_prev > 0 ? round(($total_courses - $total_courses_prev) / $total_courses_prev * 100, 1) : 0;

/* Platform Growth – last 30 days daily signups & enrollments */
$growth_data = $pdo->query("
    SELECT
        DATE(d.date) AS day,
        COALESCE(s.cnt, 0) AS signups,
        COALESCE(e.cnt, 0) AS enrollments
    FROM (
        SELECT CURDATE() - INTERVAL(a.a + (10*b.a) + (100*c.a)) DAY AS date
        FROM (SELECT 0 AS a UNION ALL SELECT 1 UNION ALL SELECT 2 UNION ALL SELECT 3 UNION ALL SELECT 4 UNION ALL SELECT 5 UNION ALL SELECT 6 UNION ALL SELECT 7 UNION ALL SELECT 8 UNION ALL SELECT 9) AS a
        CROSS JOIN (SELECT 0 AS a UNION ALL SELECT 1 UNION ALL SELECT 2 UNION ALL SELECT 3) AS b
        CROSS JOIN (SELECT 0 AS a UNION ALL SELECT 1 UNION ALL SELECT 2) AS c
    ) AS d
    LEFT JOIN (
        SELECT DATE(created_at) AS dt, COUNT(*) AS cnt FROM students
        WHERE created_at >= NOW() - INTERVAL 30 DAY GROUP BY DATE(created_at)
    ) s ON d.date = s.dt
    LEFT JOIN (
        SELECT DATE(enrolled_at) AS dt, COUNT(*) AS cnt FROM course_enrollments
        WHERE enrolled_at >= NOW() - INTERVAL 30 DAY GROUP BY DATE(enrolled_at)
    ) e ON d.date = e.dt
    WHERE d.date >= CURDATE() - INTERVAL 29 DAY
    ORDER BY d.date
")->fetchAll();

$growth_labels = [];
$growth_signups = [];
$growth_enrollments = [];
$total_signups_30d = 0;
$total_enrollments_30d = 0;
foreach ($growth_data as $row) {
    $growth_labels[] = date('M d', strtotime($row['day']));
    $growth_signups[] = (int) $row['signups'];
    $growth_enrollments[] = (int) $row['enrollments'];
    $total_signups_30d += (int) $row['signups'];
    $total_enrollments_30d += (int) $row['enrollments'];
}
$signup_change_30d = $total_signups_30d > 0
    ? round(($total_signups_30d - ($total_users - $total_signups_30d)) / ($total_users - $total_signups_30d > 0 ? ($total_users - $total_signups_30d) : 1) * 100, 1)
    : 0;
$enroll_change_30d = $total_enrollments_30d > 0
    ? round(($total_enrollments_30d - 0) / ($total_enrollments_30d) * 100, 1)
    : 0;

/* Verification rate */
$total_verifications = (int) $pdo->query("SELECT COUNT(*) FROM verification_documents")->fetchColumn();
$verified_count = (int) $pdo->query("SELECT COUNT(*) FROM verification_documents WHERE status='verified'")->fetchColumn();
$verification_rate = $total_verifications > 0 ? round($verified_count / $total_verifications * 100) : 88;

/* Admin action items */
$pending_org_verifs = (int) $pdo->query("SELECT COUNT(*) FROM verification_documents WHERE status='pending'")->fetchColumn();
$under_review_verifs = (int) $pdo->query("SELECT COUNT(*) FROM verification_documents WHERE status='under_review'")->fetchColumn();
$flagged_content = (int) $pdo->query("SELECT COUNT(*) FROM content_reports WHERE status='open'")->fetchColumn();
$open_tickets = (int) $pdo->query("SELECT COUNT(*) FROM support_tickets WHERE status IN ('open','in_progress')")->fetchColumn();

/* Pending Verifications table */
$pending_verifs = $pdo->query("
    SELECT v.id, o.organization_name, o.email, o.contact_person, v.document_type, v.status, v.submitted_at, v.id AS doc_id
    FROM verification_documents v
    JOIN organizations o ON v.organization_id = o.id
    WHERE v.status IN ('pending','under_review','verified')
    ORDER BY v.submitted_at DESC
    LIMIT 10
")->fetchAll();

/* Recent Activity */
$activities = $pdo->query("
    SELECT action, description, priority, created_at
    FROM activity_logs
    ORDER BY created_at DESC
    LIMIT 3
")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Admin Dashboard — Lawable</title>
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600;700;800&family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet" />
  <link rel="stylesheet" href="../assets/css/lawable.css" />
  <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
  <style>
    /* ─── Admin Dashboard Reset / Layout ──────────────── */
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
      --chart-navy: #1E3A5F;
      --chart-green: #4ADE80;
      --nav-h: 68px;
      --radius: 12px;
      --radius-lg: 16px;
      --shadow: 0 4px 24px rgba(13,17,23,0.08);
      --shadow-lg: 0 12px 40px rgba(13,17,23,0.12);
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

    /* ─── Dashboard Header ───────────────────────────── */
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
    .dash-filter {
      position: relative;
    }
    .dash-filter select {
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
    .dash-filter select:focus {
      outline: none;
      border-color: var(--gold);
    }
    .btn-announce {
      background: var(--gold);
      color: var(--white);
      border: none;
      padding: 0.6rem 1.5rem;
      border-radius: 8px;
      font-family: 'Inter', sans-serif;
      font-size: 0.875rem;
      font-weight: 600;
      cursor: pointer;
      display: inline-flex;
      align-items: center;
      gap: 0.5rem;
      transition: background .2s, transform .15s, box-shadow .2s;
      text-decoration: none;
    }
    .btn-announce:hover {
      background: var(--gold-dk);
      transform: translateY(-1px);
      box-shadow: 0 4px 16px rgba(201,147,58,0.3);
    }

    /* ─── Dashboard Content ───────────────────────────── */
    .dash-content {
      max-width: 1440px;
      margin: 0 auto;
      padding: 0 2rem 3rem;
    }

    /* ─── Stat Cards ──────────────────────────────────── */
    .stat-row {
      display: grid;
      grid-template-columns: repeat(5, 1fr);
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
    }
    .stat-card:hover {
      transform: translateY(-2px);
      box-shadow: var(--shadow-lg);
    }
    .stat-card-header {
      display: flex;
      align-items: center;
      justify-content: space-between;
      margin-bottom: 0.5rem;
    }
    .stat-card-icon {
      width: 40px; height: 40px;
      border-radius: 10px;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 1.2rem;
    }
    .stat-card-icon.users { background: #DBEAFE; }
    .stat-card-icon.students { background: #DCFCE7; }
    .stat-card-icon.orgs { background: #FEF3C7; }
    .stat-card-icon.verifications { background: #FCE7F3; }
    .stat-card-icon.courses { background: #EDE9FE; }
    .stat-badge {
      font-size: 0.7rem;
      font-weight: 600;
      padding: 0.2rem 0.65rem;
      border-radius: 20px;
      display: inline-flex;
      align-items: center;
      gap: 0.2rem;
    }
    .stat-badge.up { background: var(--green-bg); color: var(--green); }
    .stat-badge.down { background: var(--red-bg); color: var(--red); }
    .stat-badge.warn { background: var(--yellow-bg); color: #92400E; }
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

    /* ─── Middle Row ──────────────────────────────────── */
    .mid-row {
      display: grid;
      grid-template-columns: 1.5fr 1fr;
      gap: 1.25rem;
      margin-bottom: 1.5rem;
    }
    .dash-card {
      background: var(--white);
      border-radius: var(--radius-lg);
      padding: 1.5rem;
      box-shadow: var(--shadow);
      border: 1px solid rgba(229,224,216,0.5);
    }
    .dash-card-header {
      display: flex;
      align-items: center;
      justify-content: space-between;
      margin-bottom: 1.25rem;
    }
    .dash-card-header h2 {
      font-family: 'Playfair Display', serif;
      font-size: 1.15rem;
      font-weight: 700;
      margin: 0;
      color: var(--ink);
    }
    .dash-card-link {
      font-size: 0.8rem;
      font-weight: 600;
      color: var(--gold);
      text-decoration: none;
      transition: opacity .2s;
    }
    .dash-card-link:hover { opacity: 0.7; }

    /* Growth summary rows */
    .growth-summary {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 0.75rem;
      margin-bottom: 1.25rem;
    }
    .growth-metric {
      display: flex;
      align-items: center;
      gap: 0.75rem;
      padding: 0.75rem 1rem;
      background: var(--cream);
      border-radius: var(--radius);
      border: 1px solid var(--border);
    }
    .growth-metric-icon {
      width: 36px; height: 36px;
      border-radius: 8px;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 1rem;
      flex-shrink: 0;
    }
    .growth-metric-icon.signups { background: var(--blue-bg); }
    .growth-metric-icon.enrolls { background: var(--green-bg); }
    .growth-metric-info {}
    .growth-metric-label {
      font-size: 0.75rem;
      color: var(--ink-soft);
      font-weight: 500;
    }
    .growth-metric-value {
      font-weight: 700;
      font-size: 1.1rem;
      color: var(--ink);
    }
    .growth-metric-change {
      font-size: 0.7rem;
      font-weight: 600;
      margin-left: auto;
    }
    .growth-chart-wrap {
      height: 200px;
      position: relative;
    }

    /* System Health / Donut */
    .health-donut-wrap {
      display: flex;
      flex-direction: column;
      align-items: center;
      margin-bottom: 1.25rem;
    }
    .donut-container {
      width: 160px; height: 160px;
      position: relative;
      margin-bottom: 0.75rem;
    }
    .donut-center {
      position: absolute;
      inset: 0;
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
    }
    .donut-center strong {
      font-family: 'Playfair Display', serif;
      font-size: 2rem;
      font-weight: 700;
      color: var(--ink);
      line-height: 1;
    }
    .donut-center span {
      font-size: 0.75rem;
      color: var(--ink-soft);
      margin-top: 0.15rem;
    }
    .health-checklist {
      list-style: none;
      padding: 0;
      margin: 0;
      display: flex;
      flex-direction: column;
      gap: 0.65rem;
    }
    .health-item {
      display: flex;
      align-items: center;
      gap: 0.65rem;
      font-size: 0.85rem;
      color: var(--ink-mid);
      padding: 0.5rem 0.75rem;
      border-radius: 8px;
      background: #FAFAF8;
      transition: background .2s;
    }
    .health-item:hover { background: var(--cream); }
    .health-item-icon {
      flex-shrink: 0;
      width: 20px; height: 20px;
      border-radius: 50%;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 0.7rem;
    }
    .health-item-icon.done { background: var(--green-bg); color: var(--green); }
    .health-item-icon.half { background: var(--yellow-bg); color: #92400E; }
    .health-item-icon.pending { background: var(--border); color: var(--ink-soft); }
    .health-item-text {
      flex: 1;
    }
    .health-item-count {
      font-size: 0.75rem;
      font-weight: 600;
      color: var(--ink-soft);
      white-space: nowrap;
    }

    /* ─── Bottom Row ──────────────────────────────────── */
    .bottom-row {
      display: grid;
      grid-template-columns: 1.5fr 1fr;
      gap: 1.25rem;
    }

    /* Verifications Table */
    .verif-table {
      width: 100%;
      border-collapse: collapse;
    }
    .verif-table th {
      text-align: left;
      font-size: 0.7rem;
      font-weight: 600;
      text-transform: uppercase;
      letter-spacing: 0.08em;
      color: var(--ink-soft);
      padding-bottom: 0.75rem;
      border-bottom: 1px solid var(--border);
    }
    .verif-table td {
      padding: 0.75rem 0;
      border-bottom: 1px solid rgba(229,224,216,0.4);
      font-size: 0.85rem;
      color: var(--ink-mid);
      vertical-align: middle;
    }
    .verif-table tr:last-child td { border-bottom: none; }
    .verif-entity {
      display: flex;
      align-items: center;
      gap: 0.65rem;
    }
    .verif-avatar {
      width: 32px; height: 32px;
      border-radius: 50%;
      background: var(--gold-lt);
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 0.75rem;
      font-weight: 600;
      color: var(--gold-dk);
      flex-shrink: 0;
    }
    .verif-entity-info {}
    .verif-entity-name {
      font-weight: 600;
      color: var(--ink);
      display: block;
    }
    .verif-entity-email {
      font-size: 0.75rem;
      color: var(--ink-soft);
    }
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
    .status-badge.pending { background: var(--yellow-bg); color: #92400E; }
    .status-badge.under_review, .status-badge.under-review { background: var(--blue-bg); color: #1E40AF; }
    .status-badge.verified { background: var(--green-bg); color: var(--green); }
    .status-badge.rejected { background: var(--red-bg); color: var(--red); }

    .verif-actions {
      display: flex;
      gap: 0.4rem;
    }
    .verif-btn {
      padding: 0.3rem 0.65rem;
      border-radius: 6px;
      font-size: 0.72rem;
      font-weight: 600;
      border: none;
      cursor: pointer;
      transition: opacity .2s, transform .15s;
      font-family: 'Inter', sans-serif;
    }
    .verif-btn:hover { opacity: 0.8; transform: translateY(-1px); }
    .verif-btn.approve { background: var(--green-bg); color: var(--green); }
    .verif-btn.reject  { background: var(--red-bg); color: var(--red); }

    .doc-link {
      color: var(--gold);
      text-decoration: none;
      font-weight: 600;
      font-size: 0.8rem;
    }
    .doc-link:hover { text-decoration: underline; }

    /* Activity Feed */
    .activity-list {
      display: flex;
      flex-direction: column;
      gap: 0.85rem;
    }
    .activity-item {
      display: flex;
      gap: 0.85rem;
      padding: 0.85rem;
      border-radius: var(--radius);
      background: #FAFAF8;
      transition: background .2s;
    }
    .activity-item:hover { background: var(--cream); }
    .activity-icon {
      width: 36px; height: 36px;
      border-radius: 10px;
      flex-shrink: 0;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 1rem;
    }
    .activity-icon.org    { background: #FEF3C7; }
    .activity-icon.flag   { background: var(--red-bg); }
    .activity-icon.alert  { background: var(--yellow-bg); }
    .activity-icon.check  { background: var(--green-bg); }
    .activity-icon.ticket { background: var(--blue-bg); }
    .activity-content {
      flex: 1;
      min-width: 0;
    }
    .activity-text {
      font-size: 0.85rem;
      color: var(--ink-mid);
      line-height: 1.5;
    }
    .activity-meta {
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 0.5rem;
      margin-top: 0.3rem;
    }
    .activity-time {
      font-size: 0.72rem;
      color: var(--ink-soft);
    }
    .priority-badge {
      font-size: 0.6rem;
      font-weight: 700;
      padding: 0.15rem 0.5rem;
      border-radius: 10px;
      text-transform: uppercase;
      letter-spacing: 0.06em;
    }
    .priority-badge.high   { background: var(--red-bg); color: var(--red); }
    .priority-badge.medium { background: var(--yellow-bg); color: #92400E; }
    .priority-badge.low    { background: var(--green-bg); color: var(--green); }

    /* ─── Responsive ──────────────────────────────────── */
    @media (max-width: 1100px) {
      .stat-row { grid-template-columns: repeat(3, 1fr); }
      .mid-row, .bottom-row { grid-template-columns: 1fr; }
    }
    @media (max-width: 768px) {
      .dash-header { padding: 0 1rem; }
      .dash-content { padding: 0 1rem 2rem; }
      .stat-row { grid-template-columns: repeat(2, 1fr); }
      .growth-summary { grid-template-columns: 1fr; }
      .verif-table th:nth-child(3),
      .verif-table td:nth-child(3) { display: none; }
    }
    @media (max-width: 500px) {
      .stat-row { grid-template-columns: 1fr; }
    }
  </style>
</head>
<body>

<!-- ─── NAVBAR (same as home.php) ─────────────────────────────── -->
<nav id="navbar" class="scrolled">
  <a href="admin-dashboard.php" class="nav-logo">Law<span>able</span></a>
  <ul class="nav-links">
    <li><a href="admin-dashboard.php" class="active">Dashboard</a></li>
    <li><a href="admin-users.php">Users</a></li>
    <li><a href="admin-courses.php">Courses</a></li>
    <li><a href="admin-verifications.php">Verifications</a></li>
    <li>
      <button class="theme-toggle" type="button" data-theme-toggle aria-label="Switch to dark theme" aria-pressed="false">
        <span class="theme-toggle-icon" aria-hidden="true">D</span>
        <span class="theme-toggle-text">Dark</span>
      </button>
    </li>
    <li><a href="../backend/logout.php" class="nav-cta">Log out</a></li>
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
  <a href="../backend/logout.php" class="drawer-cta">Log out</a>
</nav>

<!-- ─── DASHBOARD PAGE ────────────────────────────────────────── -->
<div class="dashboard-page">

  <!-- Header row -->
  <div class="dash-header">
    <div class="dash-header-left">
      <h1>Dashboard</h1>
      <div class="dash-filter">
        <select>
          <option>Last 30 days</option>
          <option>Last 7 days</option>
          <option>Last 90 days</option>
          <option>All time</option>
        </select>
      </div>
    </div>
    <a href="#" class="btn-announce">+ New Announcement</a>
  </div>

  <div class="dash-content">

    <!-- ─── Stat Cards Row ────────────────────────── -->
    <div class="stat-row">
      <div class="stat-card">
        <div class="stat-card-header">
          <div class="stat-card-icon users">👥</div>
          <span class="stat-badge <?= $user_change >= 0 ? 'up' : 'down' ?>">
            <?= ($user_change >= 0 ? '↑' : '↓') ?> <?= abs($user_change) ?>%
          </span>
        </div>
        <div class="stat-card-label">Total Users</div>
        <div class="stat-card-value"><?= number_format($total_users) ?></div>
      </div>

      <div class="stat-card">
        <div class="stat-card-header">
          <div class="stat-card-icon students">🎓</div>
          <span class="stat-badge <?= $student_change >= 0 ? 'up' : 'down' ?>">
            <?= ($student_change >= 0 ? '↑' : '↓') ?> <?= abs($student_change) ?>%
          </span>
        </div>
        <div class="stat-card-label">Active Students</div>
        <div class="stat-card-value"><?= number_format($active_students) ?></div>
      </div>

      <div class="stat-card">
        <div class="stat-card-header">
          <div class="stat-card-icon orgs">🏛</div>
          <span class="stat-badge <?= $org_change >= 0 ? 'up' : 'down' ?>">
            <?= ($org_change >= 0 ? '↑' : '↓') ?> <?= abs($org_change) ?>%
          </span>
        </div>
        <div class="stat-card-label">Registered Organizations</div>
        <div class="stat-card-value"><?= number_format($total_orgs) ?></div>
      </div>

      <div class="stat-card">
        <div class="stat-card-header">
          <div class="stat-card-icon verifications">📋</div>
          <span class="stat-badge <?= $pending_verifications_total > 0 ? 'warn' : 'up' ?>">
            <?= $pending_verifications_total > 0 ? '⚠' : '✓' ?> <?= $pending_verifications_total ?> pending
          </span>
        </div>
        <div class="stat-card-label">Pending Verifications</div>
        <div class="stat-card-value"><?= number_format($pending_verifications_total) ?></div>
      </div>

      <div class="stat-card">
        <div class="stat-card-header">
          <div class="stat-card-icon courses">📚</div>
          <span class="stat-badge <?= $courses_change >= 0 ? 'up' : 'down' ?>">
            <?= ($courses_change >= 0 ? '↑' : '↓') ?> <?= abs($courses_change) ?>%
          </span>
        </div>
        <div class="stat-card-label">Published Courses</div>
        <div class="stat-card-value"><?= number_format($total_courses) ?></div>
      </div>
    </div>

    <!-- ─── Middle Row ─────────────────────────────── -->
    <div class="mid-row">

      <!-- Platform Growth -->
      <div class="dash-card">
        <div class="dash-card-header">
          <h2>📈 Platform Growth</h2>
          <a href="#" class="dash-card-link">View all →</a>
        </div>

        <div class="growth-summary">
          <div class="growth-metric">
            <div class="growth-metric-icon signups">👤</div>
            <div class="growth-metric-info">
              <div class="growth-metric-label">New Signups</div>
              <div class="growth-metric-value"><?= number_format($total_signups_30d) ?></div>
            </div>
            <span class="growth-metric-change <?= $signup_change_30d >= 0 ? '' : '' ?>" style="color:<?= $signup_change_30d >= 0 ? 'var(--green)' : 'var(--red)' ?>">
              <?= ($signup_change_30d >= 0 ? '↑' : '↓') ?> <?= abs($signup_change_30d) ?>%
            </span>
          </div>
          <div class="growth-metric">
            <div class="growth-metric-icon enrolls">📝</div>
            <div class="growth-metric-info">
              <div class="growth-metric-label">Course Enrollments</div>
              <div class="growth-metric-value"><?= number_format($total_enrollments_30d) ?></div>
            </div>
            <span class="growth-metric-change" style="color:var(--green)">
              ↑ <?= abs($enroll_change_30d) ?>%
            </span>
          </div>
        </div>

        <div class="growth-chart-wrap">
          <canvas id="growthChart"></canvas>
        </div>
      </div>

      <!-- System Health -->
      <div class="dash-card">
        <div class="dash-card-header">
          <h2>🛡 System Health</h2>
        </div>

        <div class="health-donut-wrap">
          <div class="donut-container">
            <canvas id="donutChart"></canvas>
            <div class="donut-center">
              <strong><?= $verification_rate ?>%</strong>
              <span>Verified</span>
            </div>
          </div>
        </div>

        <ul class="health-checklist">
          <li class="health-item">
            <span class="health-item-icon <?= $pending_org_verifs > 0 ? 'half' : 'done' ?>">
              <?= $pending_org_verifs > 0 ? '◐' : '✓' ?>
            </span>
            <span class="health-item-text">Review pending org verifications</span>
            <span class="health-item-count"><?= $under_review_verifs ?><?= $pending_org_verifs ? '/'.$pending_org_verifs : '' ?></span>
          </li>
          <li class="health-item">
            <span class="health-item-icon <?= $flagged_content > 0 ? 'half' : 'done' ?>">
              <?= $flagged_content > 0 ? '◐' : '✓' ?>
            </span>
            <span class="health-item-text">Approve flagged content</span>
            <span class="health-item-count"><?= $flagged_content ? '0/'.$flagged_content : '0' ?></span>
          </li>
          <li class="health-item">
            <span class="health-item-icon <?= $open_tickets > 0 ? 'half' : 'done' ?>">
              <?= $open_tickets > 0 ? '◐' : '✓' ?>
            </span>
            <span class="health-item-text">Resolve support tickets</span>
            <span class="health-item-count"><?= $open_tickets ? '0/'.$open_tickets : '0' ?></span>
          </li>
        </ul>
      </div>
    </div>

    <!-- ─── Bottom Row ─────────────────────────────── -->
    <div class="bottom-row">
      <!-- Pending Verifications Table -->
      <div class="dash-card">
        <div class="dash-card-header">
          <h2>⏳ Pending Verifications</h2>
          <a href="#" class="dash-card-link">View all →</a>
        </div>

        <table class="verif-table">
          <thead>
            <tr>
              <th>Name</th>
              <th>Type</th>
              <th>Submitted</th>
              <th>Documents</th>
              <th>Status</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($pending_verifs)): ?>
            <tr>
              <td colspan="6" style="text-align:center; padding:2rem 0; color:var(--ink-soft); font-size:0.9rem;">
                No verifications found.
              </td>
            </tr>
            <?php else: ?>
              <?php foreach ($pending_verifs as $pv): ?>
              <tr>
                <td>
                  <div class="verif-entity">
                    <div class="verif-avatar"><?= strtoupper(substr($pv['organization_name'], 0, 2)) ?></div>
                    <div class="verif-entity-info">
                      <span class="verif-entity-name"><?= e($pv['organization_name']) ?></span>
                      <span class="verif-entity-email"><?= e($pv['email']) ?></span>
                    </div>
                  </div>
                </td>
                <td><span style="font-size:0.8rem;">Organization</span></td>
                <td><span style="font-size:0.8rem; white-space:nowrap;"><?= date('M j, Y', strtotime($pv['submitted_at'])) ?></span></td>
                <td><a href="#" class="doc-link">View docs</a></td>
                <td>
                  <span class="status-badge <?= $pv['status'] ?>">
                    <?= match($pv['status']) {
                      'pending' => 'Pending',
                      'under_review' => 'Under Review',
                      'verified' => 'Verified',
                      'rejected' => 'Rejected',
                      default => $pv['status']
                    } ?>
                  </span>
                </td>
                <td>
                  <div class="verif-actions">
                    <?php if ($pv['status'] !== 'verified'): ?>
                    <form method="post" action="../backend/handle_verification.php" style="display:inline;">
                      <input type="hidden" name="doc_id" value="<?= (int) $pv['id'] ?>" />
                      <input type="hidden" name="action" value="approve" />
                      <button type="submit" class="verif-btn approve">✓ Approve</button>
                    </form>
                    <form method="post" action="../backend/handle_verification.php" style="display:inline;">
                      <input type="hidden" name="doc_id" value="<?= (int) $pv['id'] ?>" />
                      <input type="hidden" name="action" value="reject" />
                      <button type="submit" class="verif-btn reject">✕ Reject</button>
                    </form>
                    <?php else: ?>
                    <span style="font-size:0.75rem;color:var(--green);font-weight:600;">✓ Complete</span>
                    <?php endif; ?>
                  </div>
                </td>
              </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>

      <!-- Recent Activity -->
      <div class="dash-card">
        <div class="dash-card-header">
          <h2>📋 Recent Activity</h2>
          <a href="#" class="dash-card-link">View all →</a>
        </div>

        <div class="activity-list">
          <?php if (empty($activities)): ?>
            <div style="text-align:center;padding:2rem 0;color:var(--ink-soft);font-size:0.9rem;">
              No recent activity.
            </div>
          <?php else: ?>
            <?php foreach ($activities as $act): ?>
              <?php
              $icon = '🔔';
              $iconClass = 'alert';
              if (str_contains($act['action'], 'org_registered')) { $icon = '🏛'; $iconClass = 'org'; }
              elseif (str_contains($act['action'], 'content_reported')) { $icon = '🚩'; $iconClass = 'flag'; }
              elseif (str_contains($act['action'], 'login_failed')) { $icon = '⚠'; $iconClass = 'alert'; }
              elseif (str_contains($act['action'], 'enrollment')) { $icon = '📝'; $iconClass = 'check'; }
              elseif (str_contains($act['action'], 'ticket')) { $icon = '🎫'; $iconClass = 'ticket'; }
              elseif (str_contains($act['action'], 'document')) { $icon = '📄'; $iconClass = 'org'; }
              ?>
              <div class="activity-item">
                <div class="activity-icon <?= $iconClass ?>"><?= $icon ?></div>
                <div class="activity-content">
                  <div class="activity-text"><?= e($act['description']) ?></div>
                  <div class="activity-meta">
                    <span class="activity-time"><?= date('M j, g:i A', strtotime($act['created_at'])) ?></span>
                    <span class="priority-badge <?= $act['priority'] ?>"><?= e($act['priority']) ?></span>
                  </div>
                </div>
              </div>
            <?php endforeach; ?>
          <?php endif; ?>
        </div>
      </div>
    </div>

  </div>
</div>

<script src="../assets/js/script.js"></script>
<script>
(function() {
  'use strict';

  /* Hamburger already handled by script.js — no duplicate listener needed */

  /* ── Bar Chart: Platform Growth ── */
  var ctxBar = document.getElementById('growthChart');
  if (ctxBar) {
    var labels = <?= json_encode($growth_labels) ?>;
    var signups = <?= json_encode($growth_signups) ?>;
    var enrollments = <?= json_encode($growth_enrollments) ?>;

    new Chart(ctxBar.getContext('2d'), {
      type: 'bar',
      data: {
        labels: labels,
        datasets: [
          {
            label: 'Signups',
            data: signups,
            backgroundColor: '#1E3A5F',
            borderRadius: 4,
            barPercentage: 0.35,
            categoryPercentage: 0.7,
          },
          {
            label: 'Enrollments',
            data: enrollments,
            backgroundColor: '#4ADE80',
            borderRadius: 4,
            barPercentage: 0.35,
            categoryPercentage: 0.7,
          }
        ]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: {
            display: true,
            position: 'top',
            align: 'end',
            labels: {
              boxWidth: 10,
              boxHeight: 10,
              usePointStyle: true,
              pointStyle: 'circle',
              font: { size: 11, family: 'Inter' },
              color: '#6B7280',
            }
          },
          tooltip: {
            backgroundColor: '#0D1117',
            titleFont: { size: 12, family: 'Inter' },
            bodyFont: { size: 11, family: 'Inter' },
            padding: 10,
            cornerRadius: 8,
          }
        },
        scales: {
          x: {
            grid: { display: false },
            ticks: {
              font: { size: 9, family: 'Inter' },
              color: '#9CA3AF',
              maxTicksLimit: 8,
            }
          },
          y: {
            beginAtZero: true,
            grid: {
              color: 'rgba(229,224,216,0.4)',
              drawBorder: false,
            },
            ticks: {
              font: { size: 9, family: 'Inter' },
              color: '#9CA3AF',
              precision: 0,
            }
          }
        }
      }
    });
  }

  /* ── Donut Chart: Verification Rate ── */
  var ctxDonut = document.getElementById('donutChart');
  if (ctxDonut) {
    var rate = <?= (int) $verification_rate ?>;
    var remaining = 100 - rate;
    new Chart(ctxDonut.getContext('2d'), {
      type: 'doughnut',
      data: {
        labels: ['Verified', 'Pending / Other'],
        datasets: [{
          data: [rate, remaining],
          backgroundColor: ['#C9933A', '#E5E0D8'],
          borderWidth: 0,
          hoverOffset: 6,
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: true,
        cutout: '82%',
        plugins: {
          legend: { display: false },
          tooltip: {
            backgroundColor: '#0D1117',
            titleFont: { size: 11, family: 'Inter' },
            bodyFont: { size: 11, family: 'Inter' },
            padding: 8,
            cornerRadius: 6,
          }
        },
        animation: {
          animateRotate: true,
        }
      }
    });
  }

})();
</script>
</body>
</html>
