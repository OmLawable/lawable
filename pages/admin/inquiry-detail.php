<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/firestore.php';
start_secure_session();
$user = require_login('admin');
$db   = get_firestore();

$id = trim($_GET['id'] ?? '');
if ($id === '') { header('Location: inquiries.php'); exit; }
$q = $db->get('support_queries', $id);
if (!$q) { header('Location: inquiries.php'); exit; }

$status     = $q['status']    ?? 'new';
$name       = $q['name']      ?? 'Unknown';
$email      = $q['email']     ?? '';
$role       = $q['role']      ?? 'user';
$subject    = $q['subject']   ?? '';
$message    = $q['message']   ?? '';
$created_at = $q['createdAt'] ?? '';
$admin_note = $q['adminNote'] ?? '';

$nm = $name;
$sp = strpos($nm, ' ');
$initials = strtoupper(substr($nm, 0, 1) . ($sp !== false ? substr($nm, $sp + 1, 1) : ''));

function fmt_full_date_inq(string $iso): string {
    if (!$iso) return '-';
    try { return (new DateTime($iso))->format('M j, Y \a\t g:i A'); } catch (\Throwable $e) { return $iso; }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width,initial-scale=1.0"/>
  <title>Inquiry Detail &mdash; Lawable Admin</title>
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600;700;800&family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet"/>
  <link rel="stylesheet" href="../../assets/css/lawable.css"/>
  <style>
    :root{--gold:#C9933A;--gold-dk:#A8732A;--gold-lt:#F4E4C3;--cream:#FCF8F1;--page-bg:#F6EEF6;--white:#FFFFFF;--ink:#0D1117;--ink-mid:#374151;--ink-soft:#6B7280;--border:#E5E0D8;--green:#16a34a;--green-bg:#DCFCE7;--yellow:#EAB308;--yellow-bg:#FEF9C3;--red:#DC2626;--red-bg:#FEE2E2;--blue:#2563EB;--blue-bg:#DBEAFE;--nav-h:68px;--radius:12px;--radius-lg:16px;--shadow:0 4px 24px rgba(13,17,23,.08);--shadow-lg:0 12px 40px rgba(13,17,23,.12);}
    body.dark-theme{--gold:#D8A84F;--gold-dk:#F0C56D;--gold-lt:#3A3022;--cream:#111827;--page-bg:#0F172A;--white:#1E293B;--ink:#F8FAFC;--ink-mid:#CBD5E1;--ink-soft:#94A3B8;--border:#334155;--green:#22C55E;--green-bg:#064E3B;--yellow:#EAB308;--yellow-bg:#422006;--red:#EF4444;--red-bg:#450A0A;--blue:#60A5FA;--blue-bg:#1E3A5F;--shadow:0 4px 24px rgba(0,0,0,.40);--shadow-lg:0 12px 40px rgba(0,0,0,.50);}
    body{background:var(--page-bg);font-family:Inter,sans-serif;color:var(--ink);min-height:100vh;}
    .dashboard-page{padding-top:calc(var(--nav-h) + 24px);min-height:100vh;}
    .dash-header{display:flex;align-items:flex-start;justify-content:space-between;flex-wrap:wrap;gap:1rem;padding:0 2rem;margin:0 auto 1.5rem;max-width:1200px;}
    .dash-header-left{display:flex;align-items:flex-start;gap:.85rem;flex-direction:column;}
    .back-link{font-size:.85rem;font-weight:500;color:var(--ink-soft);text-decoration:none;display:inline-flex;align-items:center;gap:.35rem;padding:.35rem .75rem;border-radius:6px;border:1px solid var(--border);transition:border-color .2s,color .2s;}
    .back-link:hover{border-color:var(--gold);color:var(--gold);}
    .dash-header-left h1{font-family:'Playfair Display',serif;font-size:1.6rem;font-weight:700;color:var(--ink);margin:0;}
    .header-actions{display:flex;gap:.75rem;flex-wrap:wrap;align-items:center;}
    .dash-content{max-width:1200px;margin:0 auto;padding:0 2rem 3rem;}
    .detail-grid{display:grid;grid-template-columns:1fr 320px;gap:1.5rem;align-items:start;}
    .dash-card{background:var(--white);border-radius:var(--radius-lg);padding:1.5rem;box-shadow:var(--shadow);border:1px solid var(--border);}
    .msg-header{display:flex;align-items:center;gap:1rem;margin-bottom:1.25rem;}
    .msg-avatar{width:44px;height:44px;border-radius:50%;background:var(--gold-lt);display:flex;align-items:center;justify-content:center;font-size:1rem;font-weight:700;color:var(--gold-dk);flex-shrink:0;}
    .msg-sender-name{font-weight:700;font-size:1rem;color:var(--ink);}
    .msg-meta{font-size:.78rem;color:var(--ink-soft);margin-top:.15rem;}
    .msg-tag{display:inline-block;font-size:.68rem;font-weight:600;padding:.15rem .55rem;border-radius:8px;background:var(--gold-lt);color:var(--gold-dk);margin-left:auto;flex-shrink:0;}
    .msg-divider{border:none;border-top:1px solid var(--border);margin:1.25rem 0;}
    .msg-body{font-size:.92rem;color:var(--ink-mid);line-height:1.75;white-space:pre-wrap;word-break:break-word;}
    .status-badge{display:inline-flex;align-items:center;gap:.3rem;font-size:.72rem;font-weight:600;padding:.25rem .75rem;border-radius:20px;white-space:nowrap;}
    .status-badge.new{background:var(--blue-bg);color:#1E40AF;}
    body.dark-theme .status-badge.new{color:#93C5FD;}
    .status-badge.in_progress{background:var(--yellow-bg);color:#92400E;}
    body.dark-theme .status-badge.in_progress{color:#FBBF24;}
    .status-badge.resolved{background:var(--green-bg);color:var(--green);}
    .note-card{margin-top:1.5rem;}
    .note-label{font-size:.8rem;font-weight:600;color:var(--ink-soft);text-transform:uppercase;letter-spacing:.07em;margin-bottom:.75rem;}
    .note-textarea{width:100%;min-height:110px;padding:.85rem 1rem;border-radius:var(--radius);border:1.5px solid var(--border);background:var(--cream);font-family:Inter,sans-serif;font-size:.88rem;color:var(--ink);resize:vertical;outline:none;transition:border-color .2s;box-sizing:border-box;}
    .note-textarea:focus{border-color:var(--gold);}
    body.dark-theme .note-textarea{background:#1E293B;}
    .sidebar-section{margin-bottom:1.25rem;}
    .sidebar-section:last-child{margin-bottom:0;}
    .sidebar-label{font-size:.7rem;font-weight:600;text-transform:uppercase;letter-spacing:.08em;color:var(--ink-soft);margin-bottom:.65rem;}
    .info-row{display:flex;align-items:center;gap:.6rem;margin-bottom:.55rem;font-size:.84rem;color:var(--ink-mid);}
    .info-row a{color:var(--gold);text-decoration:none;}
    .info-row a:hover{text-decoration:underline;}
    .sidebar-divider{border:none;border-top:1px solid var(--border);margin:1.25rem 0;}
    .ticket-row{display:flex;justify-content:space-between;align-items:center;margin-bottom:.65rem;font-size:.84rem;}
    .ticket-key{color:var(--ink-soft);}
    .ticket-val{font-weight:600;color:var(--ink);text-align:right;max-width:180px;font-size:.82rem;}
    .btn-action{display:inline-flex;align-items:center;gap:.4rem;padding:.45rem 1rem;border-radius:9999px;font-size:.82rem;font-weight:600;border:none;cursor:pointer;transition:all .2s;font-family:Inter,sans-serif;text-decoration:none;}
    .btn-action.resolve{background:var(--green-bg);color:var(--green);}
    .btn-action.resolve:hover{background:var(--green);color:#fff;}
    .btn-action.inprogress{background:var(--yellow-bg);color:#92400E;}
    body.dark-theme .btn-action.inprogress{color:#FBBF24;}
    .btn-action.inprogress:hover{background:var(--yellow);color:#fff;}
    .btn-action.reopen{background:var(--blue-bg);color:#1E40AF;}
    .btn-action.reopen:hover{background:var(--blue);color:#fff;}
    .btn-action.danger{background:var(--red-bg);color:var(--red);}
    .btn-action.danger:hover{background:var(--red);color:#fff;}
    .btn-primary{background:var(--ink);color:var(--white);padding:.5rem 1.25rem;border-radius:9999px;font-size:.85rem;font-weight:600;border:none;cursor:pointer;transition:background .2s;font-family:Inter,sans-serif;width:100%;margin-top:.75rem;}
    .btn-primary:hover{background:var(--gold-dk);}
    #toast{position:fixed;top:1.5rem;right:1.5rem;max-width:360px;padding:1rem 1.25rem;border-radius:14px;background:var(--white);box-shadow:var(--shadow-lg);border-left:4px solid var(--gold);font-size:.88rem;z-index:9999;display:none;font-family:Inter,sans-serif;}
    #toast.success{border-left-color:#4A7C59;}#toast.error{border-left-color:#C0604A;}
    .confirm-overlay{position:fixed;inset:0;background:rgba(0,0,0,.45);z-index:8000;display:none;align-items:center;justify-content:center;}
    .confirm-overlay.open{display:flex;}
    .confirm-box{background:var(--white);border-radius:var(--radius-lg);padding:2rem;max-width:400px;width:90%;box-shadow:var(--shadow-lg);}
    .confirm-title{font-family:'Playfair Display',serif;font-size:1.15rem;font-weight:700;color:var(--ink);margin:0 0 .75rem;}
    .confirm-text{font-size:.88rem;color:var(--ink-soft);margin:0 0 1.5rem;line-height:1.55;}
    .confirm-actions{display:flex;gap:.75rem;justify-content:flex-end;}
    .btn-cancel{padding:.5rem 1.25rem;border-radius:9999px;font-size:.85rem;font-weight:600;border:1.5px solid var(--border);background:transparent;color:var(--ink-mid);cursor:pointer;font-family:Inter,sans-serif;}
    .btn-confirm-del{padding:.5rem 1.25rem;border-radius:9999px;font-size:.85rem;font-weight:600;border:none;background:var(--red);color:#fff;cursor:pointer;font-family:Inter,sans-serif;}
    @media(max-width:900px){.detail-grid{grid-template-columns:1fr;}}
    @media(max-width:768px){.dash-header{padding:0 1rem;}.dash-content{padding:0 1rem 2rem;}}
  </style>
</head>
<body>
<div id="toast"></div>
<div class="confirm-overlay" id="confirmOverlay">
  <div class="confirm-box">
    <div class="confirm-title">Delete this inquiry?</div>
    <div class="confirm-text">This action cannot be undone. The inquiry from <strong><?php echo htmlspecialchars($name); ?></strong> will be permanently removed.</div>
    <div class="confirm-actions">
      <button class="btn-cancel" onclick="closeConfirm()">Cancel</button>
      <button class="btn-confirm-del" onclick="confirmDelete()">Yes, Delete</button>
    </div>
  </div>
</div>

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
      <a href="inquiries.php" class="back-link">&larr; Back to Inquiries</a>
      <h1><?php echo htmlspecialchars($subject); ?></h1>
    </div>
    <div class="header-actions">
      <?php if ($status !== 'in_progress'): ?>
      <button class="btn-action inprogress" onclick="setStatus('in_progress')">&#9203; Mark In Progress</button>
      <?php endif; ?>
      <?php if ($status !== 'resolved'): ?>
      <button class="btn-action resolve" onclick="setStatus('resolved')">&#10003; Mark Resolved</button>
      <?php endif; ?>
      <?php if ($status !== 'new'): ?>
      <button class="btn-action reopen" onclick="setStatus('new')">&#8617; Reopen</button>
      <?php endif; ?>
      <button class="btn-action danger" onclick="openConfirm()">Delete</button>
    </div>
  </div>

  <div class="dash-content">
    <div class="detail-grid">
      <div>
        <div class="dash-card">
          <div class="msg-header">
            <div class="msg-avatar"><?php echo htmlspecialchars($initials); ?></div>
            <div style="flex:1;min-width:0;">
              <div class="msg-sender-name"><?php echo htmlspecialchars($name); ?></div>
              <div class="msg-meta">Sent <?php echo fmt_full_date_inq($created_at); ?></div>
            </div>
            <span class="msg-tag">Contact Form</span>
          </div>
          <hr class="msg-divider"/>
          <div class="msg-body"><?php echo htmlspecialchars($message); ?></div>
        </div>

        <div class="dash-card note-card">
          <div class="note-label">Admin Note (Internal)</div>
          <textarea class="note-textarea" id="adminNote" placeholder="Add an internal note about this inquiry..."><?php echo htmlspecialchars($admin_note); ?></textarea>
          <button class="btn-primary" onclick="saveNote()">Save Note</button>
        </div>
      </div>

      <div>
        <div class="dash-card">
          <div class="sidebar-section">
            <div class="sidebar-label">Sender Info</div>
            <div class="info-row"><?php echo htmlspecialchars($name); ?></div>
            <div class="info-row"><a href="mailto:<?php echo htmlspecialchars($email); ?>"><?php echo htmlspecialchars($email); ?></a></div>
            <div class="info-row"><?php echo htmlspecialchars(ucfirst($role)); ?> account</div>
          </div>
          <hr class="sidebar-divider"/>
          <div class="sidebar-section">
            <div class="sidebar-label">Ticket Info</div>
            <div class="ticket-row">
              <span class="ticket-key">Status</span>
              <span class="status-badge <?php echo htmlspecialchars($status); ?>">
                <?php echo match($status) {
                  'new'         => 'New',
                  'in_progress' => 'In Progress',
                  'resolved'    => 'Resolved',
                  default       => ucfirst($status),
                }; ?>
              </span>
            </div>
            <div class="ticket-row">
              <span class="ticket-key">Category</span>
              <span class="ticket-val"><?php echo htmlspecialchars($subject); ?></span>
            </div>
            <div class="ticket-row">
              <span class="ticket-key">Submitted</span>
              <span class="ticket-val"><?php echo fmt_full_date_inq($created_at); ?></span>
            </div>
            <div class="ticket-row">
              <span class="ticket-key">ID</span>
              <span style="font-size:.72rem;color:var(--ink-soft);font-family:monospace;word-break:break-all;"><?php echo htmlspecialchars($id); ?></span>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<script src="../../assets/js/script.js"></script>
<script>
  var INQ_ID = <?php echo json_encode($id); ?>;
  var toast  = document.getElementById('toast');
  function showToast(msg, type) {
    toast.textContent = msg; toast.className = type || 'success'; toast.style.display = 'block';
    clearTimeout(toast._t); toast._t = setTimeout(function(){ toast.style.display='none'; }, 4000);
  }
  function setStatus(newStatus) {
    fetch('../../api/update_inquiry.php', {
      method: 'POST', headers: {'Content-Type':'application/json'},
      body: JSON.stringify({id: INQ_ID, status: newStatus})
    }).then(function(r){ return r.json(); }).then(function(d){
      if (d.success) { showToast('Status updated to: ' + newStatus.replace('_',' '), 'success'); setTimeout(function(){ location.reload(); }, 1200); }
      else showToast(d.message || 'Update failed.', 'error');
    }).catch(function(){ showToast('Network error.', 'error'); });
  }
  function saveNote() {
    var note = document.getElementById('adminNote').value;
    fetch('../../api/update_inquiry.php', {
      method: 'POST', headers: {'Content-Type':'application/json'},
      body: JSON.stringify({id: INQ_ID, admin_note: note})
    }).then(function(r){ return r.json(); }).then(function(d){
      if (d.success) showToast('Note saved.', 'success');
      else showToast(d.message || 'Failed to save note.', 'error');
    }).catch(function(){ showToast('Network error.', 'error'); });
  }
  function openConfirm()  { document.getElementById('confirmOverlay').classList.add('open'); }
  function closeConfirm() { document.getElementById('confirmOverlay').classList.remove('open'); }
  function confirmDelete() {
    closeConfirm();
    fetch('../../api/update_inquiry.php', {
      method: 'POST', headers: {'Content-Type':'application/json'},
      body: JSON.stringify({id: INQ_ID, action: 'delete'})
    }).then(function(r){ return r.json(); }).then(function(d){
      if (d.success) { showToast('Inquiry deleted.', 'success'); setTimeout(function(){ location.href='inquiries.php?deleted=1'; }, 1000); }
      else showToast(d.message || 'Delete failed.', 'error');
    }).catch(function(){ showToast('Network error.', 'error'); });
  }
</script>
</body>
</html>