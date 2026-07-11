<?php
declare(strict_types=1);

require_once __DIR__ . '/../backend/includes/functions.php';
start_secure_session();

$user = require_login('admin');

$pdo = get_pdo();

/* ── Combined users query: students + organizations ── */
$stmt = $pdo->query("
    SELECT
        'student' AS type,
        id,
        name AS display_name,
        username,
        email,
        phone,
        status,
        created_at
    FROM students
    UNION ALL
    SELECT
        'organization' AS type,
        id,
        organization_name AS display_name,
        username,
        email,
        phone,
        status,
        created_at
    FROM organizations
    ORDER BY created_at DESC
");
$all_users = $stmt->fetchAll();

/* Stats for page header */
$total_students = (int) $pdo->query("SELECT COUNT(*) FROM students")->fetchColumn();
$total_orgs = (int) $pdo->query("SELECT COUNT(*) FROM organizations")->fetchColumn();
$total_users = $total_students + $total_orgs;
$active_users = (int) $pdo->query("SELECT (SELECT COUNT(*) FROM students WHERE status='active') + (SELECT COUNT(*) FROM organizations WHERE status='active')")->fetchColumn();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Users — Lawable Admin</title>
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
      grid-template-columns: repeat(3, 1fr);
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
    .stat-card-icon {
      width: 40px; height: 40px;
      border-radius: 10px;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 1.2rem;
      margin-bottom: 0.5rem;
    }
    .stat-card-icon.users { background: #DBEAFE; }
    .stat-card-icon.students { background: #DCFCE7; }
    .stat-card-icon.orgs { background: #FEF3C7; }
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
    .users-toolbar {
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
    .users-search {
      display: flex;
      align-items: center;
      gap: 0.5rem;
      flex: 1;
      min-width: 200px;
    }
    .users-search input {
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
    .users-search input:focus {
      border-color: var(--gold);
      box-shadow: 0 0 0 3px rgba(201,147,58,0.12);
    }
    .users-search input::placeholder {
      color: var(--ink-soft);
    }
    .users-filters {
      display: flex;
      align-items: center;
      gap: 0.5rem;
    }
    .users-filters select {
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
    .users-filters select:focus {
      outline: none;
      border-color: var(--gold);
    }

    .user-count-badge {
      font-size: 0.8rem;
      font-weight: 600;
      color: var(--ink-soft);
      padding: 0.35rem 0.85rem;
      background: var(--cream);
      border-radius: 20px;
      white-space: nowrap;
    }

    /* ─── Users Table ──────────────────────────────────── */
    .users-card {
      background: var(--white);
      border-radius: var(--radius-lg);
      box-shadow: var(--shadow);
      border: 1px solid rgba(229,224,216,0.5);
      overflow: hidden;
    }
    .users-table {
      width: 100%;
      border-collapse: collapse;
    }
    .users-table thead {
      background: #FAFAF8;
    }
    .users-table th {
      text-align: left;
      font-size: 0.7rem;
      font-weight: 600;
      text-transform: uppercase;
      letter-spacing: 0.08em;
      color: var(--ink-soft);
      padding: 1rem 1.25rem;
      border-bottom: 1px solid var(--border);
    }
    .users-table td {
      padding: 0.9rem 1.25rem;
      border-bottom: 1px solid rgba(229,224,216,0.4);
      font-size: 0.85rem;
      color: var(--ink-mid);
      vertical-align: middle;
    }
    .users-table tbody tr {
      transition: background .15s;
    }
    .users-table tbody tr:hover {
      background: var(--cream);
    }
    .users-table tbody tr:last-child td {
      border-bottom: none;
    }

    /* User entity (avatar + name/email) */
    .user-entity {
      display: flex;
      align-items: center;
      gap: 0.75rem;
    }
    .user-avatar {
      width: 36px; height: 36px;
      border-radius: 50%;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 0.75rem;
      font-weight: 600;
      flex-shrink: 0;
    }
    .user-avatar.student {
      background: var(--blue-bg);
      color: var(--blue);
    }
    .user-avatar.org {
      background: var(--gold-lt);
      color: var(--gold-dk);
    }
    .user-info {}
    .user-name {
      font-weight: 600;
      color: var(--ink);
      display: block;
    }
    .user-email {
      font-size: 0.75rem;
      color: var(--ink-soft);
    }

    /* Type badge */
    .type-badge {
      display: inline-flex;
      align-items: center;
      gap: 0.3rem;
      font-size: 0.72rem;
      font-weight: 600;
      padding: 0.25rem 0.75rem;
      border-radius: 20px;
      white-space: nowrap;
    }
    .type-badge.student {
      background: var(--blue-bg);
      color: var(--blue);
    }
    .type-badge.org {
      background: var(--yellow-bg);
      color: #92400E;
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
    .status-badge.active {
      background: var(--green-bg);
      color: var(--green);
    }
    .status-badge.inactive {
      background: var(--red-bg);
      color: var(--red);
    }

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
      .stat-row { grid-template-columns: repeat(3, 1fr); }
    }
    @media (max-width: 768px) {
      .dash-header { padding: 0 1rem; }
      .dash-content { padding: 0 1rem 2rem; }
      .stat-row { grid-template-columns: repeat(3, 1fr); }
      .users-toolbar { flex-direction: column; align-items: stretch; }
      .users-search { min-width: 0; }
      .users-filters { flex-wrap: wrap; }
      .users-table th:nth-child(4),
      .users-table td:nth-child(4) { display: none; }
    }
    @media (max-width: 500px) {
      .stat-row { grid-template-columns: 1fr; }
      .users-table th:nth-child(3),
      .users-table td:nth-child(3) { display: none; }
    }
  </style>
</head>
<body>

<!-- ─── NAVBAR ─────────────────────────────────────────────── -->
<nav id="navbar" class="scrolled">
  <a href="admin-dashboard.php" class="nav-logo">Law<span>able</span></a>
  <ul class="nav-links">
    <li><a href="admin-dashboard.php">Dashboard</a></li>
    <li><a href="admin-users.php" class="active">Users</a></li>
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
  <a href="admin-users.php" class="active">Users</a>
  <a href="admin-courses.php">Courses</a>
  <a href="admin-verifications.php">Verifications</a>
  <button class="theme-toggle drawer-theme-toggle" type="button" data-theme-toggle aria-label="Switch to dark theme" aria-pressed="false">
    <span class="theme-toggle-icon" aria-hidden="true">D</span>
    <span class="theme-toggle-text">Dark theme</span>
  </button>
  <a href="../backend/logout.php" class="drawer-cta">Log out</a>
</nav>

<!-- ─── PAGE ───────────────────────────────────────────────── -->
<div class="dashboard-page">

  <!-- Header -->
  <div class="dash-header">
    <div class="dash-header-left">
      <a href="admin-dashboard.php" class="back-link">← Dashboard</a>
      <h1>Users</h1>
    </div>
    <span class="user-count-badge"><?= number_format($total_users) ?> total</span>
  </div>

  <div class="dash-content">

    <!-- Mini stats -->
    <div class="stat-row">
      <div class="stat-card">
        <div class="stat-card-icon users">👥</div>
        <div class="stat-card-label">Total Users</div>
        <div class="stat-card-value"><?= number_format($total_users) ?></div>
        <div class="stat-card-sub"><?= number_format($active_users) ?> active</div>
      </div>
      <div class="stat-card">
        <div class="stat-card-icon students">🎓</div>
        <div class="stat-card-label">Students</div>
        <div class="stat-card-value"><?= number_format($total_students) ?></div>
      </div>
      <div class="stat-card">
        <div class="stat-card-icon orgs">🏛</div>
        <div class="stat-card-label">Organizations</div>
        <div class="stat-card-value"><?= number_format($total_orgs) ?></div>
      </div>
    </div>

    <!-- Toolbar: search + filters -->
    <div class="users-toolbar">
      <div class="users-search">
        <input type="text" id="searchInput" placeholder="Search by name, email, or username…" oninput="filterTable()" />
      </div>
      <div class="users-filters">
        <select id="typeFilter" onchange="filterTable()">
          <option value="all">All types</option>
          <option value="student">Students</option>
          <option value="organization">Organizations</option>
        </select>
        <select id="statusFilter" onchange="filterTable()">
          <option value="all">All status</option>
          <option value="active">Active</option>
          <option value="inactive">Inactive</option>
        </select>
      </div>
    </div>

    <!-- Users table -->
    <div class="users-card">
      <table class="users-table">
        <thead>
          <tr>
            <th>User</th>
            <th>Username</th>
            <th>Type</th>
            <th>Status</th>
            <th>Joined</th>
          </tr>
        </thead>
        <tbody id="usersBody">
          <?php if (empty($all_users)): ?>
            <tr>
              <td colspan="5">
                <div class="empty-state">
                  <div class="empty-state-icon">👥</div>
                  <div class="empty-state-text">No users found.</div>
                </div>
              </td>
            </tr>
          <?php else: ?>
            <?php foreach ($all_users as $u): ?>
              <?php
                $is_student = $u['type'] === 'student';
                $avatar_initials = $is_student
                  ? strtoupper(substr($u['display_name'], 0, 2))
                  : strtoupper(substr($u['display_name'], 0, 2));
              ?>
              <tr class="user-row"
                  data-type="<?= e($u['type']) ?>"
                  data-status="<?= e($u['status']) ?>"
                  data-search="<?= e(strtolower($u['display_name'] . ' ' . $u['email'] . ' ' . $u['username'])) ?>">
                <td>
                  <div class="user-entity">
                    <div class="user-avatar <?= $is_student ? 'student' : 'org' ?>">
                      <?= $avatar_initials ?>
                    </div>
                    <div class="user-info">
                      <span class="user-name"><?= e($u['display_name']) ?></span>
                      <span class="user-email"><?= e($u['email']) ?></span>
                    </div>
                  </div>
                </td>
                <td><span style="color:var(--ink-soft);font-size:0.8rem;">@<?= e($u['username']) ?></span></td>
                <td>
                  <span class="type-badge <?= $is_student ? 'student' : 'org' ?>">
                    <?= $is_student ? '🎓 Student' : '🏛 Organization' ?>
                  </span>
                </td>
                <td>
                  <span class="status-badge <?= e($u['status']) ?>">
                    <?= $u['status'] === 'active' ? '✓' : '✕' ?> <?= ucfirst(e($u['status'])) ?>
                  </span>
                </td>
                <td style="white-space:nowrap;font-size:0.8rem;color:var(--ink-soft);">
                  <?= date('M j, Y', strtotime($u['created_at'])) ?>
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
  const type = document.getElementById('typeFilter').value;
  const status = document.getElementById('statusFilter').value;
  const rows = document.querySelectorAll('.user-row');

  rows.forEach(function(row) {
    const rowType = row.getAttribute('data-type');
    const rowStatus = row.getAttribute('data-status');
    const rowSearch = row.getAttribute('data-search');

    const matchType = type === 'all' || rowType === type;
    const matchStatus = status === 'all' || rowStatus === status;
    const matchSearch = !search || rowSearch.indexOf(search) !== -1;

    row.style.display = (matchType && matchStatus && matchSearch) ? '' : 'none';
  });
}
</script>
</body>
</html>
