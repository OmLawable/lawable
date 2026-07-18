<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/firestore.php';
start_secure_session();
$user = require_login('admin');
$db   = get_firestore();

$docs = $db->query('support_queries', [], 500);
usort($docs, fn($a, $b) => strcmp($b['createdAt'] ?? '', $a['createdAt'] ?? ''));

$all = [];
foreach ($docs as $d) {
    $all[] = [
        'id'         => $d['__id'],
        'name'       => $d['name']      ?? 'Unknown',
        'email'      => $d['email']     ?? '',
        'role'       => $d['role']      ?? 'user',
        'subject'    => $d['subject']   ?? '',
        'message'    => $d['message']   ?? '',
        'status'     => $d['status']    ?? 'new',
        'created_at' => $d['createdAt'] ?? '',
    ];
}

$total = count($all);
$new_count = 0; $inprog_count = 0; $resolved_count = 0;
foreach ($all as $q) {
    if ($q['status'] === 'new') $new_count++;
    elseif ($q['status'] === 'in_progress') $inprog_count++;
    elseif ($q['status'] === 'resolved') $resolved_count++;
}

$per_page      = 10;
$status_filter = $_GET['status'] ?? 'all';
$page          = max(1, (int)($_GET['page'] ?? 1));
$filtered      = ($status_filter === 'all') ? $all : array_values(array_filter($all, fn($q) => $q['status'] === $status_filter));
$total_flt     = count($filtered);
$total_pages   = max(1, (int)ceil($total_flt / $per_page));
$page          = min($page, $total_pages);
$paged         = array_slice($filtered, ($page - 1) * $per_page, $per_page);

function fmt_inq_date(string $iso): string {
    if (!$iso) return '-';
    try { return (new DateTime($iso))->format('M j, Y'); } catch (\Throwable $e) { return $iso; }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width,initial-scale=1.0"/>
  <title>Inquiries &mdash; Lawable Admin</title>
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600;700;800&family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet"/>
  <link rel="stylesheet" href="../../assets/css/lawable.css"/>
  <style>
    :root{--gold:#C9933A;--gold-dk:#A8732A;--gold-lt:#F4E4C3;--cream:#FCF8F1;--page-bg:#F6EEF6;--white:#FFFFFF;--ink:#0D1117;--ink-mid:#374151;--ink-soft:#6B7280;--border:#E5E0D8;--green:#16a34a;--green-bg:#DCFCE7;--yellow:#EAB308;--yellow-bg:#FEF9C3;--red:#DC2626;--red-bg:#FEE2E2;--blue:#2563EB;--blue-bg:#DBEAFE;--orange:#EA580C;--orange-bg:#FED7AA;--purple:#7C3AED;--purple-bg:#EDE9FE;--nav-h:68px;--radius:12px;--radius-lg:16px;--shadow:0 4px 24px rgba(13,17,23,.08);--shadow-lg:0 12px 40px rgba(13,17,23,.12);}
    body.dark-theme{--gold:#D8A84F;--gold-dk:#F0C56D;--gold-lt:#3A3022;--cream:#111827;--page-bg:#0F172A;--white:#1E293B;--ink:#F8FAFC;--ink-mid:#CBD5E1;--ink-soft:#94A3B8;--border:#334155;--green:#22C55E;--green-bg:#064E3B;--yellow:#EAB308;--yellow-bg:#422006;--red:#EF4444;--red-bg:#450A0A;--blue:#60A5FA;--blue-bg:#1E3A5F;--shadow:0 4px 24px rgba(0,0,0,.40);--shadow-lg:0 12px 40px rgba(0,0,0,.50);}
    body{background:var(--page-bg);font-family:Inter,sans-serif;color:var(--ink);min-height:100vh;}
    .dashboard-page{padding-top:calc(var(--nav-h) + 24px);min-height:100vh;}
    .dash-header{display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:1rem;padding:0 2rem;margin:0 auto 1.5rem;max-width:1440px;}
    .dash-header-left{display:flex;align-items:center;gap:1rem;}
    .dash-header-left h1{font-family:'Playfair Display',serif;font-size:1.75rem;font-weight:700;color:var(--ink);margin:0;}
    .sub{font-size:.85rem;color:var(--ink-soft);margin-top:.2rem;}
    .back-link{font-size:.85rem;font-weight:500;color:var(--ink-soft);text-decoration:none;display:inline-flex;align-items:center;gap:.35rem;padding:.35rem .75rem;border-radius:6px;border:1px solid var(--border);transition:border-color .2s,color .2s;}
    .back-link:hover{border-color:var(--gold);color:var(--gold);}
    .dash-content{max-width:1440px;margin:0 auto;padding:0 2rem 3rem;}
    .stat-row{display:grid;grid-template-columns:repeat(4,1fr);gap:1rem;margin-bottom:1.5rem;}
    .stat-card{background:var(--white);border-radius:var(--radius-lg);padding:1.25rem 1.5rem;box-shadow:var(--shadow);border:1px solid var(--border);display:flex;align-items:flex-start;gap:1rem;transition:transform .2s,box-shadow .2s;}
    .stat-card:hover{transform:translateY(-2px);box-shadow:var(--shadow-lg);}
    .stat-card-icon{width:44px;height:44px;border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:1.3rem;flex-shrink:0;}
    .s-total{background:var(--purple-bg);}.s-new{background:var(--blue-bg);}.s-inprog{background:var(--yellow-bg);}.s-resolved{background:var(--green-bg);}
    .stat-card-label{font-size:.8rem;font-weight:500;color:var(--ink-soft);margin-bottom:.15rem;}
    .stat-card-value{font-family:'Playfair Display',serif;font-size:1.75rem;font-weight:700;color:var(--ink);line-height:1.2;}
    .filter-bar{display:flex;gap:.5rem;flex-wrap:wrap;margin-bottom:1.25rem;}
    .filter-tab{padding:.5rem 1.25rem;border-radius:20px;font-size:.85rem;font-weight:600;font-family:Inter,sans-serif;border:1.5px solid var(--border);background:var(--white);color:var(--ink-mid);cursor:pointer;text-decoration:none;transition:all .22s;}
    .filter-tab:hover{border-color:var(--gold);color:var(--gold);}
    .filter-tab.active{background:var(--ink);color:var(--white);border-color:var(--ink);}
    .tab-count{display:inline-flex;align-items:center;justify-content:center;min-width:18px;height:18px;padding:0 5px;border-radius:9px;font-size:.65rem;font-weight:700;margin-left:.35rem;background:rgba(255,255,255,.2);}
    .dash-card{background:var(--white);border-radius:var(--radius-lg);padding:1.5rem;box-shadow:var(--shadow);border:1px solid var(--border);}
    .dash-card-header{display:flex;align-items:center;justify-content:space-between;margin-bottom:1.25rem;}
    .dash-card-header h2{font-family:'Playfair Display',serif;font-size:1.15rem;font-weight:700;margin:0;color:var(--ink);}
    .result-count{font-size:.8rem;color:var(--ink-soft);}
    .inq-table{width:100%;border-collapse:collapse;}
    .inq-table th{text-align:left;font-size:.7rem;font-weight:600;text-transform:uppercase;letter-spacing:.08em;color:var(--ink-soft);padding-bottom:.75rem;border-bottom:1px solid var(--border);}
    .inq-table td{padding:.85rem 0;border-bottom:1px solid var(--border);font-size:.85rem;color:var(--ink-mid);vertical-align:middle;}
    .inq-table tr:last-child td{border-bottom:none;}
    .inq-table tbody tr:hover td{background:rgba(201,147,58,.04);}
    .sender-cell{display:flex;align-items:center;gap:.65rem;}
    .sender-avatar{width:34px;height:34px;border-radius:50%;background:var(--gold-lt);display:flex;align-items:center;justify-content:center;font-size:.75rem;font-weight:700;color:var(--gold-dk);flex-shrink:0;}
    .sender-name{font-weight:600;color:var(--ink);display:block;font-size:.875rem;}
    .sender-subj{font-size:.75rem;color:var(--ink-soft);display:block;}
    .status-badge{display:inline-flex;align-items:center;gap:.3rem;font-size:.72rem;font-weight:600;padding:.25rem .75rem;border-radius:20px;white-space:nowrap;}
    .status-badge.new{background:var(--blue-bg);color:#1E40AF;}
    body.dark-theme .status-badge.new{color:#93C5FD;}
    .status-badge.in_progress{background:var(--yellow-bg);color:#92400E;}
    body.dark-theme .status-badge.in_progress{color:#FBBF24;}
    .status-badge.resolved{background:var(--green-bg);color:var(--green);}
    .btn-view{padding:.35rem .85rem;border-radius:8px;font-size:.78rem;font-weight:600;border:1.5px solid var(--border);background:transparent;color:var(--ink-mid);cursor:pointer;transition:all .2s;text-decoration:none;display:inline-block;font-family:Inter,sans-serif;}
    .btn-view:hover{border-color:var(--gold);color:var(--gold);background:var(--gold-lt);}
    .pagination{display:flex;align-items:center;justify-content:space-between;margin-top:1.5rem;flex-wrap:wrap;gap:.75rem;}
    .page-info{font-size:.82rem;color:var(--ink-soft);}
    .page-btns{display:flex;gap:.35rem;}
    .page-btn{min-width:34px;height:34px;padding:0 .5rem;border-radius:8px;border:1.5px solid var(--border);background:var(--white);color:var(--ink-mid);font-size:.82rem;font-weight:600;cursor:pointer;text-decoration:none;display:inline-flex;align-items:center;justify-content:center;transition:all .2s;font-family:Inter,sans-serif;}
    .page-btn:hover{border-color:var(--gold);color:var(--gold);}
    .page-btn.active{background:var(--ink);color:var(--white);border-color:var(--ink);}
    .page-btn.disabled{opacity:.4;pointer-events:none;}
    .empty-state{text-align:center;padding:3rem 1rem;color:var(--ink-soft);}
    .empty-state-icon{font-size:2.5rem;margin-bottom:.75rem;}
    .empty-state-text{font-size:.9rem;}
    #toast{position:fixed;top:1.5rem;right:1.5rem;max-width:360px;padding:1rem 1.25rem;border-radius:14px;background:var(--white);box-shadow:var(--shadow-lg);border-left:4px solid var(--gold);font-size:.88rem;z-index:9999;display:none;font-family:Inter,sans-serif;}
    #toast.success{border-left-color:#4A7C59;}#toast.error{border-left-color:#C0604A;}
    @media(max-width:1100px){.stat-row{grid-template-columns:repeat(2,1fr);}}
    @media(max-width:768px){.dash-header{padding:0 1rem;}.dash-content{padding:0 1rem 2rem;}.inq-table th:nth-child(3),.inq-table td:nth-child(3){display:none;}}
    @media(max-width:500px){.stat-row{grid-template-columns:1fr;}}
  </style>
</head>
<body>
<div id="toast"></div>
<nav id="navbar" class="scrolled">
  <a href="dashboard.php" class="nav-logo">Law<span>able</span></a>
  <ul class="nav-links">
    <li><a href="dashboard.php">Dashboard</a></li>
    <li><a href="users.php">Users</a></li>
    <li><a href="courses.php">Courses</a></li>
    <li><a href="verifications.php">Verifications</a></li>
    <li><a href="inquiries.php" class="active">Inquiries</a></li>
    <li>
      <button class="theme-toggle" type="button" data-theme-toggle aria-label="Switch to dark theme" aria-pressed="false">
        <span class="theme-toggle-icon" aria-hidden="true">D</span>
        <span class="theme-toggle-text">Dark</span>
      </button>
    </li>
    <li><a href="../../api/logout.php" class="nav-cta">Log out</a></li>
  </ul>
  <button class="nav-hamburger" id="hamburger" aria-label="Menu"><span></span><span></span><span></span></button>
</nav>
<nav class="nav-drawer" id="drawer">
  <a href="dashboard.php">Dashboard</a>
  <a href="users.php">Users</a>
  <a href="courses.php">Courses</a>
  <a href="verifications.php">Verifications</a>
  <a href="inquiries.php" class="active">Inquiries</a>
  <button class="theme-toggle drawer-theme-toggle" type="button" data-theme-toggle aria-label="Switch to dark theme" aria-pressed="false">
    <span class="theme-toggle-icon" aria-hidden="true">D</span>
    <span class="theme-toggle-text">Dark theme</span>
  </button>
  <a href="../../api/logout.php" class="drawer-cta">Log out</a>
</nav>

<div class="dashboard-page">
  <div class="dash-header">
    <div class="dash-header-left">
      <a href="dashboard.php" class="back-link">&larr; Dashboard</a>
      <div>
        <h1>Inquiry Management</h1>
        <div class="sub">Track and respond to messages submitted via the Contact page.</div>
      </div>
    </div>
    <span style="font-size:.8rem;font-weight:600;color:var(--ink-soft);padding:.35rem .85rem;background:var(--cream);border-radius:20px;white-space:nowrap;">
      <?php echo (int)$new_count; ?> new
    </span>
  </div>

  <div class="dash-content">
    <div class="stat-row">
      <div class="stat-card">
        <div class="stat-card-icon s-total">&#128233;</div>
        <div><div class="stat-card-label">Total Inquiries</div><div class="stat-card-value"><?php echo number_format($total); ?></div></div>
      </div>
      <div class="stat-card">
        <div class="stat-card-icon s-new">&#128309;</div>
        <div><div class="stat-card-label">New</div><div class="stat-card-value" style="color:#1E40AF;"><?php echo number_format($new_count); ?></div></div>
      </div>
      <div class="stat-card">
        <div class="stat-card-icon s-inprog">&#9203;</div>
        <div><div class="stat-card-label">In Progress</div><div class="stat-card-value" style="color:#92400E;"><?php echo number_format($inprog_count); ?></div></div>
      </div>
      <div class="stat-card">
        <div class="stat-card-icon s-resolved">&#10003;</div>
        <div><div class="stat-card-label">Resolved</div><div class="stat-card-value" style="color:var(--green);"><?php echo number_format($resolved_count); ?></div></div>
      </div>
    </div>

    <div class="filter-bar">
      <?php
      $tabs = ['all' => ['All', $total], 'new' => ['New', $new_count], 'in_progress' => ['In Progress', $inprog_count], 'resolved' => ['Resolved', $resolved_count]];
      foreach ($tabs as $key => [$label, $count]):
      ?>
      <a href="?status=<?php echo $key; ?>&amp;page=1" class="filter-tab <?php echo $status_filter === $key ? 'active' : ''; ?>">
        <?php echo $label; ?> <span class="tab-count"><?php echo $count; ?></span>
      </a>
      <?php endforeach; ?>
    </div>

    <div class="dash-card">
      <div class="dash-card-header">
        <h2>Inquiries</h2>
        <span class="result-count">
          <?php
          $start = max(1, ($page - 1) * $per_page + 1);
          $end   = min($page * $per_page, $total_flt);
          echo "Showing {$start}&ndash;{$end} of " . number_format($total_flt);
          ?>
        </span>
      </div>

      <table class="inq-table">
        <thead>
          <tr>
            <th>Sender / Subject</th>
            <th>Status</th>
            <th>Date Submitted</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
        <?php if (empty($paged)): ?>
          <tr><td colspan="4">
            <div class="empty-state">
              <div class="empty-state-icon">&#128237;</div>
              <div class="empty-state-text">No inquiries found.</div>
            </div>
          </td></tr>
        <?php else: ?>
          <?php foreach ($paged as $q):
            $nm = $q['name'];
            $sp = strpos($nm, ' ');
            $initials = strtoupper(substr($nm, 0, 1) . ($sp !== false ? substr($nm, $sp + 1, 1) : ''));
            $subj_preview = mb_strlen($q['subject']) > 55 ? mb_substr($q['subject'], 0, 55) . '...' : $q['subject'];
            $status_label = match($q['status']) {
              'new'         => 'New',
              'in_progress' => 'In Progress',
              'resolved'    => 'Resolved',
              default       => ucfirst($q['status']),
            };
          ?>
          <tr>
            <td>
              <div class="sender-cell">
                <div class="sender-avatar"><?php echo htmlspecialchars($initials); ?></div>
                <div>
                  <span class="sender-name"><?php echo htmlspecialchars($q['name']); ?></span>
                  <span class="sender-subj"><?php echo htmlspecialchars($subj_preview); ?></span>
                </div>
              </div>
            </td>
            <td><span class="status-badge <?php echo htmlspecialchars($q['status']); ?>"><?php echo htmlspecialchars($status_label); ?></span></td>
            <td style="white-space:nowrap;"><?php echo fmt_inq_date($q['created_at']); ?></td>
            <td><a href="inquiry-detail.php?id=<?php echo urlencode($q['id']); ?>" class="btn-view">View &rarr;</a></td>
          </tr>
          <?php endforeach; ?>
        <?php endif; ?>
        </tbody>
      </table>

      <?php if ($total_pages > 1): ?>
      <div class="pagination">
        <span class="page-info">Page <?php echo $page; ?> of <?php echo $total_pages; ?></span>
        <div class="page-btns">
          <?php if ($page > 1): ?>
            <a href="?status=<?php echo $status_filter; ?>&amp;page=<?php echo $page - 1; ?>" class="page-btn">&lsaquo;</a>
          <?php else: ?>
            <span class="page-btn disabled">&lsaquo;</span>
          <?php endif; ?>
          <?php
          $rs = max(1, $page - 2); $re = min($total_pages, $page + 2);
          if ($rs > 1) {
            echo "<a href='?status={$status_filter}&amp;page=1' class='page-btn'>1</a>";
            if ($rs > 2) echo "<span class='page-btn disabled'>...</span>";
          }
          for ($i = $rs; $i <= $re; $i++) {
            echo "<a href='?status={$status_filter}&amp;page={$i}' class='page-btn " . ($i === $page ? 'active' : '') . "'>{$i}</a>";
          }
          if ($re < $total_pages) {
            if ($re < $total_pages - 1) echo "<span class='page-btn disabled'>...</span>";
            echo "<a href='?status={$status_filter}&amp;page={$total_pages}' class='page-btn'>{$total_pages}</a>";
          }
          ?>
          <?php if ($page < $total_pages): ?>
            <a href="?status=<?php echo $status_filter; ?>&amp;page=<?php echo $page + 1; ?>" class="page-btn">&rsaquo;</a>
          <?php else: ?>
            <span class="page-btn disabled">&rsaquo;</span>
          <?php endif; ?>
        </div>
      </div>
      <?php endif; ?>
    </div>
  </div>
</div>

<script src="../../assets/js/script.js"></script>
<script>
  const toast = document.getElementById('toast');
  function showToast(msg, type) {
    toast.textContent = msg; toast.className = type || 'success'; toast.style.display = 'block';
    clearTimeout(toast._t); toast._t = setTimeout(function(){ toast.style.display='none'; }, 4000);
  }
  var p = new URLSearchParams(location.search);
  if (p.get('updated') === '1') showToast('Inquiry status updated.', 'success');
  if (p.get('deleted') === '1') showToast('Inquiry deleted.', 'success');
</script>
</body>
</html>