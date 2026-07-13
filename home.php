<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/functions.php';
start_secure_session();

if (!is_logged_in()) {
    redirect('pages/login.php');
}

$user = current_user();

// Admin → redirect to admin dashboard
if (($user['role'] ?? '') === 'admin') {
    redirect('pages/admin-dashboard.php');
}

// Organization → show org-specific view (placeholder for now)
$is_org = ($user['role'] ?? '') === 'organization';

$pdo = get_pdo();

/* ── Fetch Dashboard Data ────────────────────────────────── */
$student_id = (int) $user['id'];

// ─ 1. Profile completion ─
$profile_pct = 0;
$nudge_dismissed = false;
if (!$is_org) {
    $stmt = $pdo->prepare("
        SELECT sp.bio, sp.city, sp.institution, sp.course, sp.year_semester,
               sp.areas_of_interest, sp.skills, sp.linkedin_url, sp.resume_file,
               sp.profile_completed, sp.completion_nudge_dismissed
        FROM student_profiles sp WHERE sp.student_id = :sid
        LIMIT 1
    ");
    $stmt->execute([':sid' => $student_id]);
    $sp = $stmt->fetch();

    if ($sp) {
        $nudge_dismissed = (bool) ($sp['completion_nudge_dismissed'] ?? false);
        // Count filled optional fields
        $optional = ['city','bio','date_of_birth','institution','course','year_semester','areas_of_interest','linkedin_url','skills','resume_file'];
        $filled = 0;
        foreach ($optional as $f) {
            if (!empty($sp[$f])) $filled++;
        }
        $profile_pct = (int) round(($filled / count($optional)) * 100);
    }
}

// ─ 2. Continue learning (last accessed in-progress course) ─
$last_course = null;
if (!$is_org) {
    $stmt = $pdo->prepare("
        SELECT cp.progress_percentage, cp.last_accessed_at, cp.completed_lessons, cp.total_lessons,
               c.id AS course_id, c.title, c.description
        FROM course_progress cp
        JOIN courses c ON c.id = cp.course_id
        WHERE cp.student_id = :sid AND cp.progress_percentage < 100.00
        ORDER BY cp.last_accessed_at DESC
        LIMIT 1
    ");
    $stmt->execute([':sid' => $student_id]);
    $last_course = $stmt->fetch();
}

// ─ 3. Enrolled courses with progress ─
$enrolled_courses = [];
if (!$is_org) {
    $stmt = $pdo->prepare("
        SELECT
            c.id, c.title, c.description, c.price,
            COALESCE(cp.progress_percentage, 0) AS progress_percentage,
            COALESCE(cp.completed_lessons, 0) AS completed_lessons,
            COALESCE(cp.total_lessons, 0) AS total_lessons,
            cp.last_accessed_at,
            ce.enrolled_at
        FROM course_enrollments ce
        JOIN courses c ON c.id = ce.course_id
        LEFT JOIN course_progress cp ON cp.student_id = ce.student_id AND cp.course_id = ce.course_id
        WHERE ce.student_id = :sid
        ORDER BY cp.last_accessed_at DESC, ce.enrolled_at DESC
    ");
    $stmt->execute([':sid' => $student_id]);
    $enrolled_courses = $stmt->fetchAll();
}

// ─ 4. Quick stats ─
$stats = [
    'enrolled'    => 0,
    'completed'   => 0,
    'certificates'=> 0,
    'hours_studied' => 0,
];
if (!$is_org) {
    $stats['enrolled'] = count($enrolled_courses);
    foreach ($enrolled_courses as $ec) {
        if ((float) $ec['progress_percentage'] >= 100.0) $stats['completed']++;
    }
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM certificates WHERE student_id = :sid");
    $stmt->execute([':sid' => $student_id]);
    $stats['certificates'] = (int) $stmt->fetchColumn();

    // Estimated hours studied from completed lessons
    $stmt = $pdo->prepare("
        SELECT COALESCE(SUM(cl.duration_minutes), 0) AS total_minutes
        FROM course_progress cp
        JOIN course_lessons cl ON cl.course_id = cp.course_id
        WHERE cp.student_id = :sid
    ");
    $stmt->execute([':sid' => $student_id]);
    $total_minutes = (int) $stmt->fetchColumn();
    $stats['hours_studied'] = round($total_minutes / 60, 1);
}

// ─ 5. Recommended / trending courses ─
$recommended = [];
if (!$is_org) {
    // Pick courses the student is NOT enrolled in, ordered by popularity (most enrolled)
    $stmt = $pdo->prepare("
        SELECT c.id, c.title, c.description, c.price,
               COUNT(ce.id) AS enrollment_count
        FROM courses c
        LEFT JOIN course_enrollments ce ON ce.course_id = c.id
        WHERE c.status = 'published'
          AND c.id NOT IN (
              SELECT course_id FROM course_enrollments WHERE student_id = :sid
          )
        GROUP BY c.id
        ORDER BY enrollment_count DESC, c.created_at DESC
        LIMIT 4
    ");
    $stmt->execute([':sid' => $student_id]);
    $recommended = $stmt->fetchAll();

    // Fallback to any published if none recommended
    if (empty($recommended)) {
        $stmt = $pdo->prepare("SELECT id, title, description, price, 0 AS enrollment_count FROM courses WHERE status = 'published' ORDER BY created_at DESC LIMIT 4");
        $stmt->execute();
        $recommended = $stmt->fetchAll();
    }
}

// ─ 6. Announcements ─
$stmt = $pdo->query("SELECT id, title, content, created_at FROM announcements WHERE status='published' ORDER BY created_at DESC LIMIT 3");
$announcements = $stmt->fetchAll();

// ─ 7. Certificates ─
$certificates = [];
if (!$is_org) {
    $stmt = $pdo->prepare("
        SELECT cert.certificate_number, cert.issued_at, c.title AS course_title
        FROM certificates cert
        JOIN courses c ON c.id = cert.course_id
        WHERE cert.student_id = :sid
        ORDER BY cert.issued_at DESC
        LIMIT 4
    ");
    $stmt->execute([':sid' => $student_id]);
    $certificates = $stmt->fetchAll();
}

// ─ 8. Upcoming deadlines (course_enrollments have no due dates, so use recent enrollments as proxy) ─
$recent_enrollments = [];
if (!$is_org) {
    $stmt = $pdo->prepare("
        SELECT c.title, ce.enrolled_at
        FROM course_enrollments ce
        JOIN courses c ON c.id = ce.course_id
        WHERE ce.student_id = :sid
        ORDER BY ce.enrolled_at DESC
        LIMIT 2
    ");
    $stmt->execute([':sid' => $student_id]);
    $recent_enrollments = $stmt->fetchAll();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>My Dashboard — Lawable</title>
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600;700;800&family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet" />
  <link rel="stylesheet" href="assets/css/lawable.css" />
  <style>
    /* ─── Student Dashboard Reset ──────────────── */
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

    /* ─── Dash Header ───────────────────────────── */
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
    .dash-header-left h1 {
      font-family: 'Playfair Display', serif;
      font-size: 1.75rem;
      font-weight: 700;
      color: var(--ink);
      margin: 0;
    }
    .dash-header-left p {
      font-size: 0.9rem;
      color: var(--ink-soft);
      margin-top: 0.2rem;
    }

    .dash-content {
      max-width: 1440px;
      margin: 0 auto;
      padding: 0 2rem 3rem;
    }

    /* ─── Profile Nudge Banner ───────────────────── */
    .nudge-banner {
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 1rem;
      padding: 1rem 1.5rem;
      background: var(--white);
      border: 1px solid var(--border);
      border-radius: var(--radius-lg);
      box-shadow: var(--shadow);
      margin-bottom: 1.5rem;
      flex-wrap: wrap;
    }
    .nudge-left {
      display: flex;
      align-items: center;
      gap: 0.75rem;
    }
    .nudge-icon {
      width: 36px; height: 36px;
      border-radius: 50%;
      background: var(--gold-lt);
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 1rem;
      flex-shrink: 0;
    }
    .nudge-text {
      font-size: 0.9rem;
      color: var(--ink-mid);
    }
    .nudge-text strong { color: var(--ink); }
    .nudge-bar {
      width: 120px;
      height: 6px;
      background: var(--border);
      border-radius: 3px;
      overflow: hidden;
    }
    .nudge-bar-fill {
      height: 100%;
      background: var(--gold);
      border-radius: 3px;
      transition: width .4s;
    }
    .nudge-pct {
      font-size: 0.8rem;
      font-weight: 700;
      color: var(--gold);
    }
    .nudge-actions {
      display: flex;
      align-items: center;
      gap: 0.75rem;
    }
    .nudge-btn {
      background: var(--gold);
      color: var(--white);
      padding: 0.5rem 1.25rem;
      border-radius: 8px;
      font-size: 0.8rem;
      font-weight: 600;
      font-family: 'Inter', sans-serif;
      border: none;
      cursor: pointer;
      text-decoration: none;
      transition: background .2s, transform .15s;
    }
    .nudge-btn:hover { background: var(--gold-dk); transform: translateY(-1px); }
    .nudge-close {
      background: none;
      border: none;
      font-size: 1.2rem;
      color: var(--ink-soft);
      cursor: pointer;
      padding: 0.2rem;
      line-height: 1;
      transition: color .2s;
    }
    .nudge-close:hover { color: var(--ink); }

    /* ─── Stat Cards ──────────────────────────────── */
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
    .stat-card-icon.enrolled { background: var(--blue-bg); }
    .stat-card-icon.completed { background: var(--green-bg); }
    .stat-card-icon.cert { background: var(--gold-lt); }
    .stat-card-icon.hours { background: var(--purple-bg); }
    body.dark-theme .stat-card-icon.enrolled { background: #1E3A5F; }
    body.dark-theme .stat-card-icon.completed { background: #064E3B; }
    body.dark-theme .stat-card-icon.cert { background: #3A3022; }
    body.dark-theme .stat-card-icon.hours { background: #3B2070; }
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

    /* ─── Section Headers ─────────────────────────── */
    .section-row {
      display: flex;
      align-items: center;
      justify-content: space-between;
      margin-bottom: 1rem;
    }
    .section-row h2 {
      font-family: 'Playfair Display', serif;
      font-size: 1.25rem;
      font-weight: 700;
      color: var(--ink);
      margin: 0;
    }
    .section-row a {
      font-size: 0.8rem;
      font-weight: 600;
      color: var(--gold);
      text-decoration: none;
      transition: opacity .2s;
    }
    .section-row a:hover { opacity: 0.7; }

    /* ─── Continue Learning ────────────────────────── */
    .continue-card {
      display: flex;
      align-items: center;
      gap: 1.5rem;
      background: var(--white);
      border: 1px solid var(--border);
      border-radius: var(--radius-lg);
      padding: 1.5rem;
      box-shadow: var(--shadow);
      margin-bottom: 1.5rem;
      transition: box-shadow .2s, transform .2s;
    }
    .continue-card:hover {
      box-shadow: var(--shadow-lg);
      transform: translateY(-2px);
    }
    .continue-icon {
      width: 56px; height: 56px;
      border-radius: 14px;
      background: var(--gold-lt);
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 1.6rem;
      flex-shrink: 0;
    }
    .continue-info { flex: 1; min-width: 0; }
    .continue-label {
      font-size: 0.72rem;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: 0.1em;
      color: var(--gold);
      margin-bottom: 0.25rem;
    }
    .continue-title {
      font-family: 'Playfair Display', serif;
      font-size: 1.1rem;
      font-weight: 700;
      color: var(--ink);
      margin-bottom: 0.3rem;
    }
    .continue-meta {
      font-size: 0.82rem;
      color: var(--ink-soft);
    }
    .continue-progress {
      flex: 0 0 200px;
    }
    .continue-progress-bar {
      height: 8px;
      background: var(--border);
      border-radius: 4px;
      overflow: hidden;
      margin-bottom: 0.4rem;
    }
    .continue-progress-fill {
      height: 100%;
      background: var(--gold);
      border-radius: 4px;
      transition: width .5s;
    }
    .continue-progress-text {
      font-size: 0.78rem;
      color: var(--ink-soft);
      font-weight: 600;
    }
    .continue-btn {
      flex-shrink: 0;
      background: var(--ink);
      color: var(--white);
      padding: 0.6rem 1.5rem;
      border-radius: 8px;
      font-size: 0.82rem;
      font-weight: 600;
      text-decoration: none;
      transition: background .2s, transform .15s;
      display: inline-flex;
      align-items: center;
      gap: 0.4rem;
    }
    .continue-btn:hover {
      background: var(--gold);
      transform: translateY(-1px);
    }

    .empty-state {
      text-align: center;
      padding: 2.5rem 1.5rem;
      background: var(--white);
      border: 1px dashed var(--border);
      border-radius: var(--radius-lg);
      color: var(--ink-soft);
      margin-bottom: 1.5rem;
    }
    .empty-state-icon { font-size: 2.5rem; margin-bottom: 0.75rem; }
    .empty-state-text { font-size: 0.95rem; margin-bottom: 1rem; }
    .empty-state a { color: var(--gold); font-weight: 600; text-decoration: none; }
    .empty-state a:hover { text-decoration: underline; }

    /* ─── Course Grid ──────────────────────────────── */
    .enrolled-grid {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(260px, 1fr));
      gap: 1rem;
      margin-bottom: 1.5rem;
    }
    .enrolled-card {
      background: var(--white);
      border: 1px solid var(--border);
      border-radius: var(--radius-lg);
      overflow: hidden;
      transition: box-shadow .25s, transform .25s;
    }
    .enrolled-card:hover {
      box-shadow: var(--shadow-lg);
      transform: translateY(-3px);
    }
    .enrolled-thumb {
      height: 100px;
      background: var(--gold-lt);
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 2.2rem;
      position: relative;
    }
    .enrolled-thumb::after {
      content: '';
      position: absolute; inset: 0;
      background: linear-gradient(135deg, transparent 60%, rgba(201,147,58,0.15));
    }
    .enrolled-body {
      padding: 1rem 1.25rem 1.25rem;
    }
    .enrolled-title {
      font-family: 'Playfair Display', serif;
      font-size: 0.95rem;
      font-weight: 700;
      color: var(--ink);
      line-height: 1.3;
      margin-bottom: 0.6rem;
      display: -webkit-box;
      -webkit-line-clamp: 2;
      -webkit-box-orient: vertical;
      overflow: hidden;
    }
    .enrolled-progress {
      margin-bottom: 0.6rem;
    }
    .enrolled-progress-bar {
      height: 6px;
      background: var(--border);
      border-radius: 3px;
      overflow: hidden;
      margin-bottom: 0.25rem;
    }
    .enrolled-progress-fill {
      height: 100%;
      background: var(--gold);
      border-radius: 3px;
      transition: width .4s;
    }
    .enrolled-progress-label {
      font-size: 0.72rem;
      color: var(--ink-soft);
      font-weight: 600;
    }
    .enrolled-footer {
      display: flex;
      justify-content: space-between;
      align-items: center;
    }
    .enrolled-price {
      font-family: 'Playfair Display', serif;
      font-size: 0.85rem;
      font-weight: 700;
      color: var(--ink);
    }
    .enrolled-price .free-label { color: var(--green); font-size: 0.75rem; }
    .enrolled-continue {
      font-size: 0.75rem;
      font-weight: 700;
      color: var(--gold);
      text-decoration: none;
      display: inline-flex;
      align-items: center;
      gap: 0.3rem;
      transition: gap .2s;
    }
    .enrolled-continue:hover { gap: 0.6rem; }

    /* ─── Sidebar Layout ────────────────────────────── */
    .dash-two-col {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 1.25rem;
      margin-bottom: 1.5rem;
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
      margin-bottom: 1rem;
    }
    .dash-card-header h3 {
      font-family: 'Playfair Display', serif;
      font-size: 1.05rem;
      font-weight: 700;
      margin: 0;
      color: var(--ink);
    }
    .dash-card-link {
      font-size: 0.75rem;
      font-weight: 600;
      color: var(--gold);
      text-decoration: none;
    }
    .dash-card-link:hover { opacity: 0.7; }

    /* ─── Announcements ────────────────────────────── */
    .announcement-item {
      padding: 0.85rem 0;
      border-bottom: 1px solid var(--border);
    }
    .announcement-item:last-child { border-bottom: none; }
    .announcement-title {
      font-weight: 600;
      font-size: 0.9rem;
      color: var(--ink);
      margin-bottom: 0.2rem;
    }
    .announcement-excerpt {
      font-size: 0.82rem;
      color: var(--ink-soft);
      line-height: 1.5;
      display: -webkit-box;
      -webkit-line-clamp: 2;
      -webkit-box-orient: vertical;
      overflow: hidden;
    }
    .announcement-date {
      font-size: 0.7rem;
      color: var(--ink-soft);
      margin-top: 0.3rem;
    }
    .announcement-empty {
      text-align: center;
      padding: 1.5rem 0;
      color: var(--ink-soft);
      font-size: 0.9rem;
    }

    /* ─── Certificates Showcase ──────────────────────── */
    .cert-row {
      display: flex;
      gap: 0.85rem;
      overflow-x: auto;
      padding-bottom: 0.3rem;
    }
    .cert-card {
      flex: 0 0 160px;
      background: var(--cream);
      border: 1px solid var(--border);
      border-radius: var(--radius);
      padding: 1rem;
      text-align: center;
      transition: transform .2s, box-shadow .2s;
    }
    .cert-card:hover {
      transform: translateY(-2px);
      box-shadow: var(--shadow);
    }
    .cert-icon {
      font-size: 2rem;
      margin-bottom: 0.5rem;
    }
    .cert-course {
      font-size: 0.75rem;
      font-weight: 600;
      color: var(--ink);
      line-height: 1.3;
      margin-bottom: 0.3rem;
    }
    .cert-number {
      font-size: 0.62rem;
      color: var(--ink-soft);
    }
    .cert-empty {
      text-align: center;
      padding: 1.5rem 0;
      color: var(--ink-soft);
      font-size: 0.9rem;
      width: 100%;
    }

    /* ─── Recommended Track ─────────────────────────── */
    .rec-track {
      display: flex;
      gap: 1rem;
      overflow-x: auto;
      padding-bottom: 0.3rem;
    }
    .rec-card {
      flex: 0 0 240px;
      background: var(--white);
      border: 1px solid var(--border);
      border-radius: var(--radius);
      overflow: hidden;
      transition: box-shadow .2s, transform .2s;
    }
    .rec-card:hover {
      box-shadow: var(--shadow-lg);
      transform: translateY(-3px);
    }
    .rec-thumb {
      height: 80px;
      background: var(--gold-lt);
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 1.8rem;
    }
    .rec-body {
      padding: 0.85rem 1rem 1rem;
    }
    .rec-title {
      font-family: 'Playfair Display', serif;
      font-size: 0.85rem;
      font-weight: 700;
      color: var(--ink);
      line-height: 1.3;
      margin-bottom: 0.4rem;
    }
    .rec-footer {
      display: flex;
      justify-content: space-between;
      align-items: center;
    }
    .rec-price {
      font-size: 0.8rem;
      font-weight: 700;
      color: var(--ink);
    }
    .rec-price .free-label { color: var(--green); font-size: 0.7rem; }
    .rec-enroll {
      font-size: 0.72rem;
      font-weight: 700;
      color: var(--gold);
      text-decoration: none;
    }
    .rec-enroll:hover { text-decoration: underline; }
    .rec-empty {
      text-align: center;
      padding: 1.5rem 0;
      color: var(--ink-soft);
      font-size: 0.9rem;
      width: 100%;
    }

    /* ─── Upcoming / Reminders ─────────────────────── */
    .reminder-item {
      display: flex;
      align-items: center;
      gap: 0.75rem;
      padding: 0.65rem 0;
      border-bottom: 1px solid var(--border);
      font-size: 0.85rem;
    }
    .reminder-item:last-child { border-bottom: none; }
    .reminder-dot {
      width: 8px; height: 8px;
      border-radius: 50%;
      flex-shrink: 0;
    }
    .reminder-dot.gold { background: var(--gold); }
    .reminder-dot.green { background: var(--green); }
    .reminder-dot.blue { background: var(--blue); }
    .reminder-text { color: var(--ink-mid); flex: 1; }
    .reminder-text strong { color: var(--ink); }
    .reminder-date {
      font-size: 0.72rem;
      color: var(--ink-soft);
      white-space: nowrap;
    }

    /* ─── Responsive ──────────────────────────────── */
    @media (max-width: 1100px) {
      .stat-row { grid-template-columns: repeat(2, 1fr); }
      .dash-two-col { grid-template-columns: 1fr; }
    }
    @media (max-width: 768px) {
      .dash-header { padding: 0 1rem; }
      .dash-content { padding: 0 1rem 2rem; }
      .stat-row { grid-template-columns: repeat(2, 1fr); }
      .continue-card { flex-wrap: wrap; }
      .continue-progress { flex: 1 1 100%; }
      .enrolled-grid { grid-template-columns: 1fr; }
    }
    @media (max-width: 500px) {
      .stat-row { grid-template-columns: 1fr; }
    }

    /* ─── Org-specific ─────────────────────────────── */
    .org-welcome {
      text-align: center;
      padding: 3rem 2rem;
      background: var(--white);
      border: 1px solid var(--border);
      border-radius: var(--radius-lg);
      box-shadow: var(--shadow);
    }
    .org-welcome h2 {
      font-family: 'Playfair Display', serif;
      font-size: 1.5rem;
      color: var(--ink);
      margin-bottom: 0.75rem;
    }
    .org-welcome p {
      color: var(--ink-soft);
      margin-bottom: 1.5rem;
    }

    .profile-nav-indicator {
      display: inline-flex;
      align-items: center;
      gap: 0.4rem;
      font-size: 0.75rem;
      color: var(--gold);
      font-weight: 600;
      cursor: pointer;
    }
    .profile-nav-indicator:hover { text-decoration: underline; }
  </style>
</head>
<body>
<div class="cursor-glow" id="cursorGlow"></div>
<div class="progress-bar" id="progressBar"></div>
<button class="back-top" id="backTop" onclick="window.scrollTo({top:0,behavior:'smooth'})">↑</button>

<!-- ─── NAVBAR ─────────────────────────────────────────────── -->
<nav id="navbar">
  <a href="home.php" class="nav-logo">Law<span>able</span></a>
  <ul class="nav-links">
    <li><a href="pages/offerings.php">Offerings</a></li>
    <li><a href="pages/courses.php">Courses</a></li>
    <li><a href="pages/about.php">About</a></li>
    <li><a href="pages/contact.php">Contact</a></li>
    <?php if (!$is_org): ?>
    <li class="nav-profile-item">
      <a href="edit-profile.php" class="nav-profile" aria-label="Edit profile">
        <span aria-hidden="true">👤</span>
      </a>
    </li>
    <?php endif; ?>
    <li>
      <button class="theme-toggle" type="button" data-theme-toggle aria-label="Switch to dark theme" aria-pressed="false">
        <span class="theme-toggle-icon" aria-hidden="true">D</span>
        <span class="theme-toggle-text">Dark</span>
      </button>
    </li>
    <li><a href="api/logout.php" class="nav-cta">Log out</a></li>
  </ul>
  <button class="nav-hamburger" id="hamburger" aria-label="Menu">
    <span></span><span></span><span></span>
  </button>
</nav>

<nav class="nav-drawer" id="drawer">
  <a href="pages/offerings.php" onclick="closeDrawer()">Offerings</a>
  <a href="pages/courses.php" onclick="closeDrawer()">Courses</a>
  <a href="pages/about.php" onclick="closeDrawer()">About</a>
  <a href="pages/contact.php" onclick="closeDrawer()">Contact</a>
  <?php if (!$is_org): ?>
  <a href="edit-profile.php" onclick="closeDrawer()">Edit profile</a>
  <?php endif; ?>
  <button class="theme-toggle drawer-theme-toggle" type="button" data-theme-toggle aria-label="Switch to dark theme" aria-pressed="false">
    <span class="theme-toggle-icon" aria-hidden="true">D</span>
    <span class="theme-toggle-text">Dark theme</span>
  </button>
  <a href="api/logout.php" class="drawer-cta">Log out</a>
</nav>

<!-- ─── DASHBOARD PAGE ────────────────────────────────────────── -->
<div class="dashboard-page">

  <div class="dash-header">
    <div class="dash-header-left">
      <h1>Welcome back, <?= e(explode(' ', $user['name'] ?? 'Student')[0]) ?> 👋</h1>
      <p>Here's your learning overview.</p>
    </div>
    <span class="profile-nav-indicator" onclick="window.location='edit-profile.php'">
      ✏️ Edit profile
    </span>
  </div>

  <div class="dash-content">

<?php if ($is_org): /* ─── ORGANIZATION VIEW ─── */ ?>
    <div class="org-welcome">
      <h2>🏛 Organization Dashboard</h2>
      <p>Welcome, <?= e($user['organization_name'] ?? $user['name']) ?>. Organization management features are coming soon.</p>
      <a href="edit-org-profile.php" class="btn-primary">Manage Organization</a>
    </div>

<?php else: /* ─── STUDENT VIEW ─── */ ?>

    <?php /* ── Profile Completion Nudge ── */ ?>
    <?php if ($profile_pct > 0 && $profile_pct < 100 && !$nudge_dismissed): ?>
    <div class="nudge-banner" id="profileNudge">
      <div class="nudge-left">
        <div class="nudge-icon">📋</div>
        <div>
          <div class="nudge-text">Complete your profile to get personalized recommendations and unlock your full potential.</div>
          <div style="display:flex;align-items:center;gap:0.75rem;margin-top:0.3rem;">
            <div class="nudge-bar"><div class="nudge-bar-fill" style="width:<?= $profile_pct ?>%"></div></div>
            <span class="nudge-pct"><?= $profile_pct ?>%</span>
          </div>
        </div>
      </div>
      <div class="nudge-actions">
        <a href="edit-profile.php" class="nudge-btn">Complete Profile</a>
        <button class="nudge-close" onclick="dismissNudge()" aria-label="Dismiss">&times;</button>
      </div>
    </div>
    <?php endif; ?>

    <?php /* ── Quick Stats Row ── */ ?>
    <div class="stat-row">
      <div class="stat-card">
        <div class="stat-card-header">
          <div class="stat-card-icon enrolled">📚</div>
        </div>
        <div class="stat-card-label">Courses Enrolled</div>
        <div class="stat-card-value"><?= $stats['enrolled'] ?></div>
      </div>
      <div class="stat-card">
        <div class="stat-card-header">
          <div class="stat-card-icon completed">✅</div>
        </div>
        <div class="stat-card-label">Courses Completed</div>
        <div class="stat-card-value"><?= $stats['completed'] ?></div>
      </div>
      <div class="stat-card">
        <div class="stat-card-header">
          <div class="stat-card-icon cert">🏅</div>
        </div>
        <div class="stat-card-label">Certificates Earned</div>
        <div class="stat-card-value"><?= $stats['certificates'] ?></div>
      </div>
      <div class="stat-card">
        <div class="stat-card-header">
          <div class="stat-card-icon hours">⏱</div>
        </div>
        <div class="stat-card-label">Hours Studied</div>
        <div class="stat-card-value"><?= $stats['hours_studied'] ?></div>
      </div>
    </div>

    <?php /* ── Continue Learning ── */ ?>
    <?php if ($last_course): ?>
    <div class="section-row">
      <h2>▶ Continue Learning</h2>
      <a href="pages/courses.php">All courses →</a>
    </div>
    <div class="continue-card">
      <div class="continue-icon">⚖️</div>
      <div class="continue-info">
        <div class="continue-label">Pick up where you left off</div>
        <div class="continue-title"><?= e($last_course['title']) ?></div>
        <div class="continue-meta">
          <?= (int) $last_course['completed_lessons'] ?> / <?= (int) $last_course['total_lessons'] ?> lessons completed
          · Last accessed <?= date('M j', strtotime($last_course['last_accessed_at'])) ?>
        </div>
      </div>
      <div class="continue-progress">
        <div class="continue-progress-bar">
          <div class="continue-progress-fill" style="width:<?= round((float) $last_course['progress_percentage']) ?>%"></div>
        </div>
        <div class="continue-progress-text"><?= round((float) $last_course['progress_percentage']) ?>% complete</div>
      </div>
      <a href="pages/courses.php?course_id=<?= (int) $last_course['course_id'] ?>" class="continue-btn">Continue →</a>
    </div>
    <?php elseif (empty($enrolled_courses)): ?>
    <div class="empty-state">
      <div class="empty-state-icon">📖</div>
      <div class="empty-state-text">You haven't started a course yet.</div>
      <a href="pages/courses.php">Browse courses →</a>
    </div>
    <?php endif; ?>

    <?php /* ── Enrolled Courses ── */ ?>
    <?php if (!empty($enrolled_courses)): ?>
    <div class="section-row">
      <h2>📚 My Courses</h2>
      <a href="pages/courses.php">View all →</a>
    </div>
    <div class="enrolled-grid">
      <?php foreach ($enrolled_courses as $ec): ?>
      <div class="enrolled-card">
        <div class="enrolled-thumb">⚖️</div>
        <div class="enrolled-body">
          <div class="enrolled-title"><?= e($ec['title']) ?></div>
          <div class="enrolled-progress">
            <div class="enrolled-progress-bar">
              <div class="enrolled-progress-fill" style="width:<?= min(100, round((float) $ec['progress_percentage'])) ?>%"></div>
            </div>
            <div class="enrolled-progress-label">
              <?= min(100, round((float) $ec['progress_percentage'])) ?>%
              <?php if ((float) $ec['progress_percentage'] >= 100): ?>✅ Completed<?php endif; ?>
            </div>
          </div>
          <div class="enrolled-footer">
            <span class="enrolled-price">
              <?= (float) $ec['price'] > 0 ? '₹' . number_format((float) $ec['price']) : '<span class="free-label">Free</span>' ?>
            </span>
            <?php if ((float) $ec['progress_percentage'] < 100): ?>
            <a href="pages/courses.php?course_id=<?= (int) $ec['id'] ?>" class="enrolled-continue">Continue →</a>
            <?php else: ?>
            <span style="font-size:0.72rem;color:var(--green);font-weight:600;">✓ Completed</span>
            <?php endif; ?>
          </div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <?php /* ── Two Column: Announcements + Upcoming / Certificates ── */ ?>
    <div class="dash-two-col">

      <?php /* ── Announcements ── */ ?>
      <div class="dash-card">
        <div class="dash-card-header">
          <h3>📢 Announcements</h3>
          <a href="#" class="dash-card-link">View all →</a>
        </div>
        <?php if (empty($announcements)): ?>
          <div class="announcement-empty">No announcements yet.</div>
        <?php else: ?>
          <?php foreach ($announcements as $a): ?>
          <div class="announcement-item">
            <div class="announcement-title"><?= e($a['title']) ?></div>
            <div class="announcement-excerpt"><?= e(substr($a['content'], 0, 120)) ?><?= strlen($a['content']) > 120 ? '…' : '' ?></div>
            <div class="announcement-date"><?= date('M j, Y', strtotime($a['created_at'])) ?></div>
          </div>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>

      <?php /* ── Certificates / Achievements ── */ ?>
      <div class="dash-card">
        <div class="dash-card-header">
          <h3>🏅 Certificates & Achievements</h3>
        </div>
        <div class="cert-row">
          <?php if (empty($certificates)): ?>
            <div class="cert-empty">Complete a course to earn your first certificate 🎯</div>
          <?php else: ?>
            <?php foreach ($certificates as $cert): ?>
            <div class="cert-card">
              <div class="cert-icon">🏅</div>
              <div class="cert-course"><?= e($cert['course_title']) ?></div>
              <div class="cert-number"><?= e($cert['certificate_number']) ?></div>
              <div style="font-size:0.62rem;color:var(--ink-soft);margin-top:0.2rem;"><?= date('M Y', strtotime($cert['issued_at'])) ?></div>
            </div>
            <?php endforeach; ?>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <?php /* ── Upcoming / Reminders ── */ ?>
    <div class="dash-card" style="margin-bottom:1.5rem;">
      <div class="dash-card-header">
        <h3>📅 Upcoming & Reminders</h3>
      </div>
      <?php if (empty($recent_enrollments) && empty($enrolled_courses)): ?>
        <div style="text-align:center;padding:1rem 0;color:var(--ink-soft);font-size:0.9rem;">
          No upcoming deadlines. Start a course to get started!
        </div>
      <?php else: ?>
        <?php foreach ($recent_enrollments as $re): ?>
        <div class="reminder-item">
          <span class="reminder-dot gold"></span>
          <span class="reminder-text">Enrolled in <strong><?= e($re['title']) ?></strong></span>
          <span class="reminder-date"><?= date('M j', strtotime($re['enrolled_at'])) ?></span>
        </div>
        <?php endforeach; ?>
        <?php if (!empty($enrolled_courses)): ?>
        <div class="reminder-item">
          <span class="reminder-dot blue"></span>
          <span class="reminder-text"><strong><?= count($enrolled_courses) ?></strong> active course<?= count($enrolled_courses) !== 1 ? 's' : '' ?> in progress</span>
          <span class="reminder-date">Keep going!</span>
        </div>
        <?php endif; ?>
      <?php endif; ?>
    </div>

    <?php /* ── Recommended Courses ── */ ?>
    <?php if (!empty($recommended)): ?>
    <div class="section-row">
      <h2>🔥 Recommended for You</h2>
      <a href="pages/courses.php">Browse all →</a>
    </div>
    <div class="rec-track" style="margin-bottom:1.5rem;">
      <?php foreach ($recommended as $rc): ?>
      <div class="rec-card">
        <div class="rec-thumb">⚖️</div>
        <div class="rec-body">
          <div class="rec-title"><?= e($rc['title']) ?></div>
          <div class="rec-footer">
            <span class="rec-price">
              <?= (float) $rc['price'] > 0 ? '₹' . number_format((float) $rc['price']) : '<span class="free-label">Free</span>' ?>
            </span>
            <a href="pages/courses.php?course_id=<?= (int) $rc['id'] ?>" class="rec-enroll">Enroll →</a>
          </div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>

<?php endif; /* end student view */ ?>

  </div>
</div>

<script src="assets/js/script.js"></script>
<script>
(function() {
  'use strict';

  // Profile nudge dismiss
  window.dismissNudge = function() {
    var nudge = document.getElementById('profileNudge');
    if (nudge) {
      nudge.style.transition = 'opacity .3s, transform .3s';
      nudge.style.opacity = '0';
      nudge.style.transform = 'translateY(-12px)';
      setTimeout(function() { nudge.style.display = 'none'; }, 300);

      // AJAX dismiss
      var xhr = new XMLHttpRequest();
      xhr.open('POST', 'backend/dashboard.php?action=dismiss_nudge', true);
      xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
      xhr.send('student_id=<?= $student_id ?>');
    }
  };
})();
</script>
</body>
</html>
