<?php
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/firestore.php';
start_secure_session();

$user = require_login('admin');
$db = get_firestore();

/* ── Fetch all verifications from Firestore ── */
$verifDocs = $db->query('verificationRequests', [], 100);
$all_verifications = [];

foreach ($verifDocs as $vd) {
    $orgId = $vd['organizationId'] ?? '';
    if (empty($orgId)) continue;

    $org = $db->get('organizations', $orgId);
    if (!$org) continue;

    $reviewerName = 'Admin';
    if (!empty($vd['reviewedBy'])) {
        $reviewer = $db->get('admins', $vd['reviewedBy']);
        if ($reviewer) {
            $reviewerName = $reviewer['name'] ?? 'Admin';
        }
    }

    $all_verifications[] = [
        'id'                => $vd['__id'],
        'document_type'     => $vd['documentType'] ?? 'registration',
        'file_path'         => $vd['filePath'] ?? '',
        'status'            => $vd['status'] ?? 'pending',
        'admin_notes'       => $vd['adminNotes'] ?? '',
        'submitted_at'      => $vd['submittedAt'] ?? '',
        'reviewed_at'       => $vd['reviewedAt'] ?? '',
        'org_id'            => $orgId,
        'organization_name' => $org['organizationName'] ?? '',
        'email'             => $org['email'] ?? '',
        'contact_person'    => $org['contactPerson'] ?? '',
        'phone'             => $org['phone'] ?? '',
        'org_created_at'    => $org['createdAt'] ?? '',
        'reviewer_name'     => $reviewerName,
    ];
}

// Sort verifications by submitted_at DESC
usort($all_verifications, function($a, $b) {
    return strcmp($b['submitted_at'], $a['submitted_at']);
});

/* Stats */
$total_verifications = count($all_verifications);
$pending_count = 0;
$under_review_count = 0;
$verified_count = 0;
$rejected_count = 0;

foreach ($all_verifications as $v) {
    switch ($v['status']) {
        case 'pending':      $pending_count++;      break;
        case 'under_review': $under_review_count++;  break;
        case 'verified':     $verified_count++;      break;
        case 'rejected':     $rejected_count++;      break;
    }
}

/* Support tickets (for the tickets section) */
$ticketDocs = $db->query('supportTickets', [], 100);

// Sort tickets by status priority then createdAt DESC in PHP
$priorityWeights = ['urgent' => 4, 'high' => 3, 'medium' => 2, 'low' => 1];
usort($ticketDocs, function($a, $b) use ($priorityWeights) {
    $pA = $priorityWeights[$a['priority'] ?? 'medium'] ?? 2;
    $pB = $priorityWeights[$b['priority'] ?? 'medium'] ?? 2;
    if ($pA !== $pB) {
        return $pB <=> $pA; // higher priority weight first
    }
    return strcmp($b['createdAt'] ?? '', $a['createdAt'] ?? '');
});

// Slice first 8
$ticketDocs = array_slice($ticketDocs, 0, 8);
$tickets = [];
foreach ($ticketDocs as $td) {
    $tickets[] = [
        'id'         => $td['__id'],
        'title'      => $td['title'] ?? '',
        'message'    => $td['message'] ?? '',
        'user_type'  => $td['userType'] ?? '',
        'user_id'    => $td['userId'] ?? '',
        'priority'   => $td['priority'] ?? 'medium',
        'status'     => $td['status'] ?? 'open',
        'created_at' => $td['createdAt'] ?? '',
    ];
}

$urgent_tickets = 0;
$open_tickets_count = 0;
foreach ($tickets as $t) {
    if ($t['priority'] === 'urgent' || $t['priority'] === 'high') {
        $urgent_tickets++;
    }
    if (in_array($t['status'], ['open', 'in_progress'], true)) {
        $open_tickets_count++;
    }
}

/* Content reports */
$reportDocs = $db->query('contentReports', [], 100);

// Sort reports: open/under_review first, then resolved/dismissed, then createdAt DESC
$statusWeights = ['open' => 4, 'under_review' => 3, 'resolved' => 2, 'dismissed' => 1];
usort($reportDocs, function($a, $b) use ($statusWeights) {
    $wA = $statusWeights[$a['status'] ?? 'open'] ?? 4;
    $wB = $statusWeights[$b['status'] ?? 'open'] ?? 4;
    if ($wA !== $wB) {
        return $wB <=> $wA;
    }
    return strcmp($b['createdAt'] ?? '', $a['createdAt'] ?? '');
});

// Slice first 6
$reportDocs = array_slice($reportDocs, 0, 6);
$reports = [];
foreach ($reportDocs as $rd) {
    $reports[] = [
        'id'               => $rd['__id'],
        'reason'           => $rd['reason'] ?? '',
        'target_type'      => $rd['targetType'] ?? '',
        'target_id'        => $rd['targetId'] ?? '',
        'status'           => $rd['status'] ?? 'open',
        'created_at'       => $rd['createdAt'] ?? '',
        'reported_by_type' => $rd['reportedByType'] ?? '',
        'reported_by_id'   => $rd['reportedById'] ?? '',
    ];
}

$open_reports = 0;
foreach ($reports as $r) {
    if (in_array($r['status'], ['open', 'under_review'], true)) {
        $open_reports++;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Verifications — Lawable Admin</title>
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600;700;800&family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet" />
  <link rel="stylesheet" href="../../assets/css/lawable.css" />
  <style>
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
      --orange: #EA580C;
      --orange-bg: #FED7AA;
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
      --orange: #FB923C;
      --orange-bg: #431407;
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

    .dash-content {
      max-width: 1440px;
      margin: 0 auto;
      padding: 0 2rem 3rem;
    }

    /* ─── Stat Cards ──────────────────────────────────── */
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
      border: 1px solid var(--border);
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
    .stat-card-icon.total     { background: var(--purple-bg); }
    .stat-card-icon.pending-icon  { background: var(--yellow-bg); }
    .stat-card-icon.verified-icon { background: var(--green-bg); }
    .stat-card-icon.reports   { background: var(--orange-bg); }
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
    .stat-card-sub strong {
      color: var(--ink-mid);
    }

    /* ─── Tab bar ─────────────────────────────────────── */
    .verif-tabs {
      display: flex;
      gap: 0.5rem;
      flex-wrap: wrap;
      margin-bottom: 1.25rem;
    }
    .verif-tab {
      padding: 0.5rem 1.25rem;
      border-radius: 20px;
      font-size: 0.85rem;
      font-weight: 600;
      font-family: 'Inter', sans-serif;
      border: 1.5px solid var(--border);
      background: var(--white);
      color: var(--ink-mid);
      cursor: pointer;
      transition: all .22s;
    }
    .verif-tab:hover {
      border-color: var(--gold);
      color: var(--gold);
    }
    .verif-tab.active {
      background: var(--ink);
      color: var(--white);
      border-color: var(--ink);
    }
    .verif-tab .tab-count {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      min-width: 18px;
      height: 18px;
      padding: 0 5px;
      border-radius: 9px;
      font-size: 0.65rem;
      font-weight: 700;
      margin-left: 0.35rem;
      background: rgba(255,255,255,0.2);
    }
    .verif-tab.active .tab-count {
      background: rgba(255,255,255,0.25);
    }

    /* ─── Verifications + Tickets grid ───────────────── */
    .verif-grid {
      display: grid;
      grid-template-columns: 1.5fr 1fr;
      gap: 1.25rem;
      margin-bottom: 1.25rem;
    }

    .dash-card {
      background: var(--white);
      border-radius: var(--radius-lg);
      padding: 1.5rem;
      box-shadow: var(--shadow);
      border: 1px solid var(--border);
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

    /* Verification table */
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
      border-bottom: 1px solid var(--border);
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
    body.dark-theme .status-badge.pending { color: #FBBF24; }
    .status-badge.under_review, .status-badge.under-review { background: var(--blue-bg); color: #1E40AF; }
    body.dark-theme .status-badge.under_review, body.dark-theme .status-badge.under-review { color: #93C5FD; }
    .status-badge.verified { background: var(--green-bg); color: var(--green); }
    .status-badge.rejected { background: var(--red-bg); color: var(--red); }
    .status-badge.open { background: var(--yellow-bg); color: #92400E; }
    body.dark-theme .status-badge.open { color: #FBBF24; }
    .status-badge.in_progress { background: var(--blue-bg); color: #1E40AF; }
    body.dark-theme .status-badge.in_progress { color: #93C5FD; }
    .status-badge.resolved { background: var(--green-bg); color: var(--green); }
    .status-badge.closed { background: var(--border); color: var(--ink-soft); }

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
    .verif-btn.review  { background: var(--blue-bg); color: var(--blue); }

    .doc-link {
      color: var(--gold);
      text-decoration: none;
      font-weight: 600;
      font-size: 0.8rem;
    }
    .doc-link:hover { text-decoration: underline; }

    /* ─── Tickets List ────────────────────────────────── */
    .ticket-list {
      display: flex;
      flex-direction: column;
      gap: 0.75rem;
    }
    .ticket-item {
      display: flex;
      gap: 0.75rem;
      padding: 0.85rem;
      border-radius: var(--radius);
      background: #FAFAF8;
      transition: background .2s;
      cursor: pointer;
    }
    .ticket-item:hover { background: var(--cream); }
    body.dark-theme .ticket-item { background: rgba(255,255,255,0.04); }
    body.dark-theme .ticket-item:hover { background: rgba(255,255,255,0.08); }
    .ticket-icon {
      width: 36px; height: 36px;
      border-radius: 10px;
      flex-shrink: 0;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 0.9rem;
    }
    .ticket-icon.urgent { background: var(--red-bg); }
    .ticket-icon.high   { background: var(--orange-bg); }
    .ticket-icon.medium { background: var(--yellow-bg); }
    .ticket-icon.low    { background: var(--green-bg); }
    .ticket-content { flex: 1; min-width: 0; }
    .ticket-title {
      font-weight: 600;
      font-size: 0.85rem;
      color: var(--ink);
      display: block;
      line-height: 1.3;
    }
    .ticket-meta {
      display: flex;
      align-items: center;
      gap: 0.5rem;
      margin-top: 0.25rem;
      font-size: 0.72rem;
      color: var(--ink-soft);
    }
    .priority-pill {
      font-size: 0.6rem;
      font-weight: 700;
      padding: 0.15rem 0.5rem;
      border-radius: 10px;
      text-transform: uppercase;
      letter-spacing: 0.06em;
    }
    .priority-pill.urgent { background: var(--red-bg); color: var(--red); }
    .priority-pill.high   { background: var(--orange-bg); color: #9A3412; }
    body.dark-theme .priority-pill.high { color: #FDBA74; }
    .priority-pill.medium { background: var(--yellow-bg); color: #92400E; }
    body.dark-theme .priority-pill.medium { color: #FBBF24; }
    .priority-pill.low    { background: var(--green-bg); color: var(--green); }

    /* ─── Bottom row: Reports ─────────────────────────── */
    .report-list {
      display: flex;
      flex-direction: column;
      gap: 0.75rem;
    }
    .report-item {
      display: flex;
      align-items: flex-start;
      gap: 0.75rem;
      padding: 0.85rem;
      border-radius: var(--radius);
      background: #FAFAF8;
      transition: background .2s;
    }
    .report-item:hover { background: var(--cream); }
    body.dark-theme .report-item { background: rgba(255,255,255,0.04); }
    body.dark-theme .report-item:hover { background: rgba(255,255,255,0.08); }
    .report-icon {
      width: 36px; height: 36px;
      border-radius: 10px;
      flex-shrink: 0;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 0.9rem;
      background: var(--red-bg);
    }
    .report-content { flex: 1; }
    .report-reason {
      font-size: 0.85rem;
      color: var(--ink-mid);
      line-height: 1.4;
    }
    .report-meta {
      display: flex;
      align-items: center;
      gap: 0.5rem;
      margin-top: 0.3rem;
      font-size: 0.72rem;
      color: var(--ink-soft);
    }

    .empty-state {
      text-align: center;
      padding: 2.5rem 1rem;
      color: var(--ink-soft);
    }
    .empty-state-icon {
      font-size: 2rem;
      margin-bottom: 0.5rem;
    }
    .empty-state-text {
      font-size: 0.9rem;
    }

    /* ─── Responsive ───────────────────────────────────── */
    @media (max-width: 1100px) {
      .stat-row { grid-template-columns: repeat(2, 1fr); }
      .verif-grid { grid-template-columns: 1fr; }
    }
    @media (max-width: 768px) {
      .dash-header { padding: 0 1rem; }
      .dash-content { padding: 0 1rem 2rem; }
      .stat-row { grid-template-columns: repeat(2, 1fr); }
      .verif-table th:nth-child(3),
      .verif-table td:nth-child(3) { display: none; }
    }
    @media (max-width: 500px) {
      .stat-row { grid-template-columns: 1fr; }
    }
  </style>
</head>
<body>

<!-- ─── NAVBAR ─────────────────────────────────────────────── -->
<nav id="navbar" class="scrolled">
  <a href="dashboard.php" class="nav-logo">Law<span>able</span></a>
  <ul class="nav-links">
    <li><a href="dashboard.php">Dashboard</a></li>
    <li><a href="users.php">Users</a></li>
    <li><a href="courses.php">Courses</a></li>
    <li><a href="verifications.php" class="active">Verifications</a></li>
    <li><a href="inquiries.php">Inquiries</a></li>
    <li>
      <button class="theme-toggle" type="button" data-theme-toggle aria-label="Switch to dark theme" aria-pressed="false">
        <span class="theme-toggle-icon" aria-hidden="true">D</span>
        <span class="theme-toggle-text">Dark</span>
      </button>
    </li>
    <li><a href="../../api/logout.php" class="nav-cta">Log out</a></li>
  </ul>
  <button class="nav-hamburger" id="hamburger" aria-label="Menu">
    <span></span><span></span><span></span>
  </button>
</nav>
<nav class="nav-drawer" id="drawer">
  <a href="dashboard.php">Dashboard</a>
  <a href="users.php">Users</a>
  <a href="courses.php">Courses</a>
  <a href="verifications.php" class="active">Verifications</a>
  <a href="inquiries.php">Inquiries</a>
  <button class="theme-toggle drawer-theme-toggle" type="button" data-theme-toggle aria-label="Switch to dark theme" aria-pressed="false">
    <span class="theme-toggle-icon" aria-hidden="true">D</span>
    <span class="theme-toggle-text">Dark theme</span>
  </button>
  <a href="../../api/logout.php" class="drawer-cta">Log out</a>
</nav>

<!-- ─── PAGE ───────────────────────────────────────────────── -->
<div class="dashboard-page">

  <div class="dash-header">
    <div class="dash-header-left">
      <a href="dashboard.php" class="back-link">← Dashboard</a>
      <h1>Verifications & Oversight</h1>
    </div>
    <span class="course-count-badge" style="font-size:0.8rem;font-weight:600;color:var(--ink-soft);padding:0.35rem 0.85rem;background:var(--cream);border-radius:20px;white-space:nowrap;">
      <?= number_format($pending_count + $under_review_count) ?> pending action
    </span>
  </div>

  <div class="dash-content">

    <!-- Stats -->
    <div class="stat-row">
      <div class="stat-card">
        <div class="stat-card-icon total">📋</div>
        <div class="stat-card-body">
          <div class="stat-card-label">Total Verifications</div>
          <div class="stat-card-value"><?= number_format($total_verifications) ?></div>
          <div class="stat-card-sub">
            <?= number_format($verified_count) ?> verified · <?= number_format($rejected_count) ?> rejected
          </div>
        </div>
      </div>
      <div class="stat-card">
        <div class="stat-card-icon pending-icon">⏳</div>
        <div class="stat-card-body">
          <div class="stat-card-label">Pending Review</div>
          <div class="stat-card-value" style="color:#92400E;"><?= number_format($pending_count) ?></div>
          <div class="stat-card-sub"><strong><?= number_format($under_review_count) ?></strong> under review</div>
        </div>
      </div>
      <div class="stat-card">
        <div class="stat-card-icon verified-icon">✓</div>
        <div class="stat-card-body">
          <div class="stat-card-label">Verified</div>
          <div class="stat-card-value" style="color:var(--green);"><?= number_format($verified_count) ?></div>
          <div class="stat-card-sub">Approved organizations</div>
        </div>
      </div>
      <div class="stat-card">
        <div class="stat-card-icon reports">🚩</div>
        <div class="stat-card-body">
          <div class="stat-card-label">Open Issues</div>
          <div class="stat-card-value" style="color:var(--red);"><?= number_format($open_reports + $open_tickets_count) ?></div>
          <div class="stat-card-sub">
            <?= number_format($open_reports) ?> reports · <?= number_format($open_tickets_count) ?> tickets
          </div>
        </div>
      </div>
    </div>

    <!-- Tabs -->
    <div class="verif-tabs">
      <button class="verif-tab active" onclick="switchTab(this,'all')">All <span class="tab-count"><?= $total_verifications ?></span></button>
      <button class="verif-tab" onclick="switchTab(this,'pending')">Pending <span class="tab-count"><?= $pending_count ?></span></button>
      <button class="verif-tab" onclick="switchTab(this,'under_review')">Under Review <span class="tab-count"><?= $under_review_count ?></span></button>
      <button class="verif-tab" onclick="switchTab(this,'verified')">Verified <span class="tab-count"><?= $verified_count ?></span></button>
      <button class="verif-tab" onclick="switchTab(this,'rejected')">Rejected <span class="tab-count"><?= $rejected_count ?></span></button>
    </div>

    <!-- Verifications + Tickets -->
    <div class="verif-grid">
      <!-- Verifications table -->
      <div class="dash-card">
        <div class="dash-card-header">
          <h2>📄 Verification Requests</h2>
          <a href="#" class="dash-card-link">View all →</a>
        </div>
        <table class="verif-table">
          <thead>
            <tr>
              <th>Organization</th>
              <th>Type</th>
              <th>Submitted</th>
              <th>Status</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($all_verifications)): ?>
            <tr>
              <td colspan="5" style="text-align:center;padding:2rem 0;color:var(--ink-soft);font-size:0.9rem;">
                No verification requests yet.
              </td>
            </tr>
            <?php else: ?>
              <?php foreach ($all_verifications as $v): ?>
              <tr class="verif-row" data-status="<?= e($v['status']) ?>">
                <td>
                  <div class="verif-entity">
                    <div class="verif-avatar"><?= strtoupper(substr($v['organization_name'], 0, 2)) ?></div>
                    <div class="verif-entity-info">
                      <span class="verif-entity-name"><?= e($v['organization_name']) ?></span>
                      <span class="verif-entity-email"><?= e($v['email']) ?></span>
                    </div>
                  </div>
                </td>
                <td><span style="font-size:0.8rem;text-transform:capitalize;"><?= e(str_replace('_', ' ', $v['document_type'])) ?></span></td>
                <td><span style="font-size:0.8rem;white-space:nowrap;"><?= date('M j, Y', strtotime($v['submitted_at'])) ?></span></td>
                <td>
                  <span class="status-badge <?= e($v['status']) ?>">
                    <?= match($v['status']) {
                      'pending'      => '⏳ Pending',
                      'under_review' => '🔍 Under Review',
                      'verified'     => '✓ Verified',
                      'rejected'     => '✕ Rejected',
                      default        => e($v['status'])
                    } ?>
                  </span>
                </td>
                <td>
                  <div class="verif-actions">
                    <?php if ($v['status'] === 'pending'): ?>
                      <form method="post" action="../../api/handle_verification.php" style="display:inline;">
                        <input type="hidden" name="doc_id" value="<?= e($v['id']) ?>" />
                        <input type="hidden" name="action" value="approve" />
                        <button type="submit" class="verif-btn approve">✓ Approve</button>
                      </form>
                      <form method="post" action="../../api/handle_verification.php" style="display:inline;">
                        <input type="hidden" name="doc_id" value="<?= e($v['id']) ?>" />
                        <input type="hidden" name="action" value="reject" />
                        <button type="submit" class="verif-btn reject">✕ Reject</button>
                      </form>
                    <?php elseif ($v['status'] === 'under_review'): ?>
                      <form method="post" action="../../api/handle_verification.php" style="display:inline;">
                        <input type="hidden" name="doc_id" value="<?= e($v['id']) ?>" />
                        <input type="hidden" name="action" value="approve" />
                        <button type="submit" class="verif-btn approve">✓ Approve</button>
                      </form>
                      <form method="post" action="../../api/handle_verification.php" style="display:inline;">
                        <input type="hidden" name="doc_id" value="<?= e($v['id']) ?>" />
                        <input type="hidden" name="action" value="reject" />
                        <button type="submit" class="verif-btn reject">✕ Reject</button>
                      </form>
                    <?php elseif ($v['status'] === 'verified'): ?>
                      <span style="font-size:0.75rem;color:var(--green);font-weight:600;">
                        ✓ by <?= e($v['reviewer_name'] ?? 'Admin') ?>
                      </span>
                    <?php else: ?>
                      <span style="font-size:0.75rem;color:var(--red);font-weight:600;">
                        ✕ <?= e($v['reviewer_name'] ?? 'Admin') ?>
                      </span>
                    <?php endif; ?>
                  </div>
                </td>
              </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>

      <!-- Support Tickets -->
      <div class="dash-card">
        <div class="dash-card-header">
          <h2>🎫 Support Tickets</h2>
          <a href="#" class="dash-card-link">View all →</a>
        </div>
        <div class="ticket-list">
          <?php if (empty($tickets)): ?>
            <div class="empty-state">
              <div class="empty-state-icon">🎫</div>
              <div class="empty-state-text">No support tickets.</div>
            </div>
          <?php else: ?>
            <?php foreach ($tickets as $t): ?>
              <?php
                $urgency = $t['priority'];
                $iconMap = ['urgent' => '🔴', 'high' => '🟠', 'medium' => '🟡', 'low' => '🟢'];
                $icon = $iconMap[$urgency] ?? '🔵';
              ?>
              <div class="ticket-item">
                <div class="ticket-icon <?= $urgency ?>"><?= $icon ?></div>
                <div class="ticket-content">
                  <span class="ticket-title"><?= e($t['title']) ?></span>
                  <div class="ticket-meta">
                    <span class="priority-pill <?= $urgency ?>"><?= e($urgency) ?></span>
                    <span class="status-badge <?= e($t['status']) ?>"><?= e(str_replace('_', ' ', $t['status'])) ?></span>
                    <span><?= date('M j', strtotime($t['created_at'])) ?></span>
                  </div>
                </div>
              </div>
            <?php endforeach; ?>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <!-- Content Reports -->
    <div class="dash-card">
      <div class="dash-card-header">
        <h2>🚩 Flagged Content Reports</h2>
        <a href="#" class="dash-card-link">View all →</a>
      </div>
      <div class="report-list">
        <?php if (empty($reports)): ?>
          <div class="empty-state">
            <div class="empty-state-icon">🚩</div>
            <div class="empty-state-text">No content reports.</div>
          </div>
        <?php else: ?>
          <?php foreach ($reports as $r): ?>
            <div class="report-item">
              <div class="report-icon">🚩</div>
              <div class="report-content">
                <div class="report-reason"><?= e(mb_substr($r['reason'], 0, 120)) ?><?= mb_strlen($r['reason']) > 120 ? '…' : '' ?></div>
                <div class="report-meta">
                  <span class="status-badge <?= e($r['status']) ?>"><?= e(str_replace('_', ' ', $r['status'])) ?></span>
                  <span><?= e(ucfirst($r['target_type'])) ?> #<?= (int) $r['target_id'] ?></span>
                  <span><?= date('M j, Y', strtotime($r['created_at'])) ?></span>
                </div>
              </div>
            </div>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>
    </div>

  </div>
</div>

<script src="../../assets/js/script.js"></script>
<script>
(function() {
  'use strict';
  /* Hamburger already handled by script.js — no duplicate listener needed */
})();

function switchTab(btn, status) {
  document.querySelectorAll('.verif-tab').forEach(t => t.classList.remove('active'));
  btn.classList.add('active');
  document.querySelectorAll('.verif-row').forEach(function(row) {
    if (status === 'all' || row.getAttribute('data-status') === status) {
      row.style.display = '';
    } else {
      row.style.display = 'none';
    }
  });
}
</script>
</body>
</html>
