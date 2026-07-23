<?php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/firestore.php';
require_once __DIR__ . '/../includes/certificates.php';
start_secure_session();

if (!is_logged_in()) {
    redirect('pages/login.php');
}

$user = current_user();

// Admin → redirect to admin dashboard
if (($user['role'] ?? '') === 'admin') {
    redirect('pages/admin/dashboard.php');
}

// Organization → show org-specific view (placeholder for now)
$is_org = ($user['role'] ?? '') === 'organization';
$is_teacher = ($user['role'] ?? '') === 'teacher';

$db = get_firestore();
$student_id = (string) $user['id'];

/* ── Fetch Dashboard Data ────────────────────────────────── */
$profile_pct = 0;
$nudge_dismissed = false;
$student_messages = [];
$teacher_messages = [];

if (!$is_org) {
    // Handle Mark Message Read POST
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mark_msg_read'])) {
        try {
            verify_csrf_token($_POST['csrf_token'] ?? '');
            $msgId = trim((string) ($_POST['message_id'] ?? ''));
            if ($msgId !== '') {
                $msgDoc = $db->get('messages', $msgId);
                if ($msgDoc && ($msgDoc['receiverId'] ?? '') === $student_id) {
                    $msgDoc['isRead'] = true;
                    $db->set('messages', $msgDoc, $msgId);
                }
            }
            header('Location: dashboard.php');
            exit();
        } catch (Throwable $e) {
            // silent catch
        }
    }

    // Fetch messages
    $all_msg = $db->query('messages', [['receiverId', 'EQUAL', $student_id]], 100);
    if (!empty($all_msg)) {
        usort($all_msg, function($a, $b) {
            return strcmp($b['createdAt'] ?? '', $a['createdAt'] ?? '');
        });
        if ($is_teacher) {
            $teacher_messages = $all_msg;
        } else {
            $student_messages = $all_msg;
        }
    }
}

if (!$is_org) {
    // ─ 1. Profile completion ─
    $sp = $db->get('students', $student_id);
    if ($sp) {
        $nudge_dismissed = (bool) ($sp['completionNudgeDismissed'] ?? false);
        // Count filled optional fields
        $optional = ['gender','bio','dateOfBirth','institution','course','yearSemester','areasOfInterest','linkedinUrl','skills','resumeFile'];
        $filled = 0;
        foreach ($optional as $f) {
            if (!empty($sp[$f])) $filled++;
        }
        $profile_pct = (int) round(($filled / count($optional)) * 100);
    }
}

$org_courses_count = 0;
$org_total_students = 0;
$org_recent_courses = [];
$org_teachers_count = 0;

if ($is_org || $is_teacher) {
    $filter_field = $is_teacher ? 'teacherId' : 'organizationId';
    $org_courses = $db->query('courses', [
        [$filter_field, 'EQUAL', $student_id]
    ], 100);

    if (!empty($org_courses)) {
        $org_courses_count = count($org_courses);
        foreach ($org_courses as $c) {
            $org_total_students += (int) ($c['enrollment_count'] ?? 0);
        }
        
        usort($org_courses, function($a, $b) {
            return strcmp($b['createdAt'] ?? '', $a['createdAt'] ?? '');
        });
        
        $org_recent_courses = array_slice($org_courses, 0, 5);
    }

    if ($is_org) {
        try {
            $org_teachers = $db->query('teachers', [
                ['organizationId', 'EQUAL', $student_id]
            ], 100);
            if (!empty($org_teachers)) {
                $org_teachers_count = count($org_teachers);
            }
        } catch (Throwable $e) {
            // Ignore
        }
    }
}

// ─ 2. Continue learning (last accessed in-progress course) ─
$last_course = null;
if (!$is_org) {
    $progressRecords = $db->query('progress', [
        ['studentId', 'EQUAL', $student_id]
    ], 100);

    // Filter in-progress (< 100%) and sort by lastAccessedAt descending
    $inProgress = array_filter($progressRecords, function($p) {
        return (float) ($p['progressPercentage'] ?? 0.0) < 100.00;
    });

    if (!empty($inProgress)) {
        usort($inProgress, function($a, $b) {
            return strcmp($b['lastAccessedAt'] ?? '', $a['lastAccessedAt'] ?? '');
        });
        $lastProgress = reset($inProgress);
        $courseDoc = $db->get('courses', $lastProgress['courseId']);
        if ($courseDoc) {
            $last_course = [
                'course_id'           => $courseDoc['__id'],
                'title'               => $courseDoc['title'] ?? '',
                'description'         => $courseDoc['description'] ?? '',
                'progress_percentage' => $lastProgress['progressPercentage'] ?? 0.0,
                'completed_lessons'   => $lastProgress['completedLessons'] ?? 0,
                'total_lessons'       => $lastProgress['totalLessons'] ?? 0,
                'last_accessed_at'    => $lastProgress['lastAccessedAt'] ?? '',
                'category'            => $courseDoc['category'] ?? '',
            ];
        }
    }
}

// ─ 3. Enrolled courses with progress ─
$enrolled_courses = [];
if (!$is_org) {
    $enrollments = $db->query('enrollments', [['studentId', 'EQUAL', $student_id]], 100);

    // Sort enrollments: we'll fetch details and sort them in PHP
    foreach ($enrollments as $e) {
        $cId = $e['courseId'] ?? '';
        if (empty($cId)) continue;

        $courseDoc = $db->get('courses', $cId);
        if (!$courseDoc) continue;

        // Fetch corresponding progress
        $progressId = $student_id . '_' . $cId;
        $progressDoc = $db->get('progress', $progressId);

        $enrolled_courses[] = [
            'id'                  => $courseDoc['__id'],
            'title'               => $courseDoc['title'] ?? '',
            'description'         => $courseDoc['description'] ?? '',
            'price'               => (float) ($courseDoc['price'] ?? 0.0),
            'category'            => $courseDoc['category'] ?? '',
            'progress_percentage' => (float) ($progressDoc['progressPercentage'] ?? 0.0),
            'completed_lessons'   => (int) ($progressDoc['completedLessons'] ?? 0),
            'total_lessons'       => (int) ($progressDoc['totalLessons'] ?? 0),
            'last_accessed_at'    => $progressDoc['lastAccessedAt'] ?? '',
            'enrolled_at'         => $e['enrolledAt'] ?? '',
        ];
    }

    // Sort by last_accessed_at DESC, then enrolled_at DESC
    usort($enrolled_courses, function($a, $b) {
        $cmp = strcmp($b['last_accessed_at'], $a['last_accessed_at']);
        if ($cmp !== 0) return $cmp;
        return strcmp($b['enrolled_at'], $a['enrolled_at']);
    });
}

// ─ 4. Quick stats ─
$stats = [
    'enrolled'      => 0,
    'completed'     => 0,
    'credits'       => 0,
    'hours_studied' => 0,
];
if (!$is_org) {
    $stats['enrolled'] = count($enrolled_courses);
    foreach ($enrolled_courses as $ec) {
        if ((float) $ec['progress_percentage'] >= 100.0) $stats['completed']++;
    }

    // Total Credits from student profile
    if (!isset($sp) || $sp === null) {
        $sp = $db->get('students', $student_id);
    }
    $stats['credits'] = (int) ($sp['credits'] ?? 0);

    // Auto-generate certificates for any completed course and load user certificates
    foreach ($enrolled_courses as $ec) {
        if ((float) $ec['progress_percentage'] >= 100.0) {
            check_and_generate_certificate($student_id, $ec['id']);
        }
    }
    $user_certs = get_student_certificates($student_id);

    // Estimated hours studied (sum of lesson durations * progress pct)
    $total_minutes = 0;
    foreach ($enrolled_courses as $ec) {
        // Fetch full course doc to get lessons
        $courseDoc = $db->get('courses', $ec['id']);
        if ($courseDoc && !empty($courseDoc['lessons'])) {
            $course_minutes = 0;
            foreach ($courseDoc['lessons'] as $lesson) {
                $course_minutes += (int) ($lesson['durationMinutes'] ?? 0);
            }
            $total_minutes += (int) round(($course_minutes * $ec['progress_percentage']) / 100);
        }
    }
    $stats['hours_studied'] = round($total_minutes / 60, 1);
}

// ─ 5. Recommended / trending courses ─
$recommended = [];
if (!$is_org) {
    $allPublished = $db->query('courses', [['status', 'EQUAL', 'published']], 100);
    $enrolledIds = array_column($enrolled_courses, 'id');

    // Filter out enrolled courses
    $notEnrolled = array_filter($allPublished, function($c) use ($enrolledIds) {
        return !in_array($c['__id'], $enrolledIds);
    });

    // Simple sorting: by createdAt descending
    usort($notEnrolled, function($a, $b) {
        return strcmp($b['createdAt'] ?? '', $a['createdAt'] ?? '');
    });

    // Slice first 4
    $slice = array_slice($notEnrolled, 0, 4);
    foreach ($slice as $s) {
        $recommended[] = [
            'id'          => $s['__id'],
            'title'       => $s['title'] ?? '',
            'description' => $s['description'] ?? '',
            'price'       => (float) ($s['price'] ?? 0.0),
            'category'    => $s['category'] ?? '',
        ];
    }
}

// ─ 6. Announcements ─
$announcements = [];
$announceDocs = $db->query('announcements', [['status', 'EQUAL', 'published']], 100);
if (!empty($announceDocs)) {
    usort($announceDocs, function($a, $b) {
        return strcmp($b['createdAt'] ?? '', $a['createdAt'] ?? '');
    });
    $announceDocs = array_slice($announceDocs, 0, 3);
    foreach ($announceDocs as $ad) {
        $announcements[] = [
            'id'         => $ad['__id'],
            'title'      => $ad['title'] ?? '',
            'content'    => $ad['content'] ?? '',
            'created_at' => $ad['createdAt'] ?? '',
        ];
    }
}

// ─ 7. Certificates ─
$certificates = [];
if (!$is_org) {
    $certDocs = $db->query('certificates', [['studentId', 'EQUAL', $student_id]], 100);
    if (!empty($certDocs)) {
        usort($certDocs, function($a, $b) {
            return strcmp($b['issuedAt'] ?? '', $a['issuedAt'] ?? '');
        });
        $certDocs = array_slice($certDocs, 0, 4);
        foreach ($certDocs as $cd) {
            $certificates[] = [
                'certificate_number' => $cd['__id'],
                'issued_at'          => $cd['issuedAt'] ?? '',
                'course_title'       => $cd['courseName'] ?? '',
            ];
        }
    }
}

// ─ 8. Recent enrollments ─
$recent_enrollments = [];
if (!$is_org) {
    // Already sorted by enrolled_at/last_accessed_at, slice top 2
    $recent_enrollments = array_slice($enrolled_courses, 0, 2);
}

// ─ 9. Webinars ─
$org_webinars = [];
$upcoming_webinars = [];
$teacher_org_id = '';
$teacher_org_name = '';

if ($is_teacher) {
    try {
        $teacherDoc = $db->get('teachers', $student_id);
        if ($teacherDoc) {
            $teacher_org_id = $teacherDoc['organizationId'] ?? '';
            $teacher_org_name = $teacherDoc['organizationName'] ?? '';
            if (!empty($teacher_org_id) && $teacher_org_id !== 'none' && empty($teacher_org_name)) {
                $orgDoc = $db->get('organizations', $teacher_org_id);
                if ($orgDoc) {
                    $teacher_org_name = $orgDoc['organizationName'] ?? $orgDoc['contactPerson'] ?? '';
                }
            }
        }
    } catch (Throwable $e) {
        // Ignore
    }
}

if ($is_org || ($is_teacher && $teacher_org_id !== '')) {
    try {
        $targetOrgId = $is_org ? $student_id : $teacher_org_id;
        $org_webinars = $db->query('webinars', [
            ['organizationId', 'EQUAL', $targetOrgId]
        ], 100);
        
        // If teacher, filter to show only published webinars
        if ($is_teacher && !empty($org_webinars)) {
            $org_webinars = array_filter($org_webinars, function($w) {
                return ($w['status'] ?? 'draft') === 'published';
            });
        }

        if (!empty($org_webinars)) {
            usort($org_webinars, function($a, $b) {
                return strcmp($a['dateTime'] ?? '', $b['dateTime'] ?? '');
            });
        }
    } catch (Throwable $e) {
        // Ignore
    }
}

if (!$is_org && !$is_teacher) {
    try {
        $all_published_webinars = $db->query('webinars', [
            ['status', 'EQUAL', 'published']
        ], 100);
        if (!empty($all_published_webinars)) {
            $upcoming_webinars = array_filter($all_published_webinars, function($w) {
                $webinarTime = strtotime($w['dateTime'] ?? '');
                return $webinarTime > (time() - 7200); // Only show upcoming or recently started (last 2h)
            });
            usort($upcoming_webinars, function($a, $b) {
                return strcmp($a['dateTime'] ?? '', $b['dateTime'] ?? '');
            });
        }
    } catch (Throwable $e) {
        // Ignore
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>My Dashboard — Lawable</title>
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600;700;800&family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet" />
  <link rel="stylesheet" href="../assets/css/lawable.css?v=1.4" />
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
    .btn-pill { display: inline-flex; align-items: center; justify-content: center; padding: 0.75rem 1.75rem; font-size: 0.9rem; font-weight: 600; border-radius: 9999px; border: 1px solid transparent; cursor: pointer; transition: all 0.2s ease-in-out; text-decoration: none; min-width: 110px; }
    .btn-pill-primary { background: #A8732A; color: white; }
    .btn-pill-primary:hover { background: #8E5E1E; transform: translateY(-1px); }
    .btn-pill-outline { background: transparent; border-color: #E5E0D8; color: #4B5563; }
    .btn-pill-outline:hover { background: #F9F8F6; border-color: #C9933A; color: #A8732A; }
  </style>
</head>
<body>
<div class="cursor-glow" id="cursorGlow"></div>
<div class="progress-bar" id="progressBar"></div>
<button class="back-top" id="backTop" onclick="window.scrollTo({top:0,behavior:'smooth'})">↑</button>

<!-- ─── NAVBAR ─────────────────────────────────────────────── -->
<nav id="navbar">
  <a href="dashboard.php" class="nav-logo">Law<span>able</span></a>
  <ul class="nav-links">
    <li><a href="offerings.php">Offerings</a></li>
    <li class="nav-dropdown">
      <a href="courses.php" class="nav-dropdown-toggle">
        Courses <span class="nav-dropdown-chevron">▼</span>
      </a>
      <div class="nav-dropdown-menu">
        <a href="courses.php">Explore Courses</a>
        <a href="my-learnings.php">My Learnings</a>
      </div>
    </li>
    <li><a href="about.php">About</a></li>
    <li><a href="contact.php">Contact</a></li>
    <?php 
      $profileLink = '';
      if (($user['role'] ?? '') === 'user') {
          $profileLink = 'student/edit-profile.php';
      } elseif (($user['role'] ?? '') === 'teacher') {
          $profileLink = 'teacher/edit-profile.php';
      } elseif (($user['role'] ?? '') === 'organization') {
          $profileLink = 'organization/edit-profile.php';
      }
      if ($profileLink !== ''):
    ?>
    <li class="nav-profile-item">
      <a href="<?= $profileLink ?>" class="nav-profile" aria-label="Edit profile">
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
    <li><a href="../api/logout.php" class="nav-cta">Log out</a></li>
  </ul>
  <button class="nav-hamburger" id="hamburger" aria-label="Menu">
    <span></span><span></span><span></span>
  </button>
</nav>

<nav class="nav-drawer" id="drawer">
  <a href="offerings.php" onclick="closeDrawer()">Offerings</a>
  <a href="courses.php" onclick="closeDrawer()">Explore Courses</a>
  <a href="my-learnings.php" onclick="closeDrawer()">My Learnings</a>
  <a href="about.php" onclick="closeDrawer()">About</a>
  <a href="contact.php" onclick="closeDrawer()">Contact</a>
  <?php if (!$is_org): ?>
  <a href="student/edit-profile.php" onclick="closeDrawer()">Edit profile</a>
  <?php endif; ?>
  <button class="theme-toggle drawer-theme-toggle" type="button" data-theme-toggle aria-label="Switch to dark theme" aria-pressed="false">
    <span class="theme-toggle-icon" aria-hidden="true">D</span>
    <span class="theme-toggle-text">Dark theme</span>
  </button>
  <a href="../api/logout.php" class="drawer-cta">Log out</a>
</nav>

<!-- ─── DASHBOARD PAGE ────────────────────────────────────────── -->
<div class="dashboard-page">

  <?php if (!$is_org && !$is_teacher): ?>
  <div class="dash-header">
    <div class="dash-header-left">
      <h1>Welcome back, <?= e(explode(' ', $user['name'] ?? 'Student')[0]) ?> 👋</h1>
      <p>Here's your learning overview.</p>
    </div>
    <span class="profile-nav-indicator" onclick="window.location='student/edit-profile.php'">
      ✏️ Edit profile
    </span>
  </div>
  <?php endif; ?>

  <div class="dash-content">

<?php if ($is_org || $is_teacher): /* ─── ORGANIZATION / TEACHER VIEW ─── */ ?>
    <div class="dash-header" style="margin-bottom: 2rem;">
      <div class="dash-header-left">
        <h1>Welcome back, <?= e($is_teacher ? $user['name'] : ($user['organization_name'] ?? $user['name'])) ?> <?= $is_teacher ? '🎓' : '🏛' ?></h1>
        <p style="color:var(--ink-soft);font-size:0.95rem;margin-top:0.3rem;">
          Here is your <?= $is_teacher ? 'instructor' : "organization's" ?> course catalog overview.
        </p>
        <?php if ($is_teacher): ?>
          <?php if (!empty($teacher_org_name) && $teacher_org_name !== 'Independent (No Affiliation)' && !empty($teacher_org_id) && $teacher_org_id !== 'none'): ?>
            <div style="margin-top:0.6rem; display:inline-flex; align-items:center; gap:0.4rem; font-size:0.88rem; font-weight:600; color:var(--gold-dk); background:var(--gold-lt); padding:0.35rem 0.85rem; border-radius:50px; border:1px solid rgba(201,147,58,0.25);">
              🏛 Affiliated with: <strong><?= e($teacher_org_name) ?></strong>
            </div>
          <?php else: ?>
            <div style="margin-top:0.6rem; display:inline-flex; align-items:center; gap:0.4rem; font-size:0.82rem; font-weight:500; color:var(--ink-soft); background:var(--paper); padding:0.3rem 0.75rem; border-radius:50px; border:1px solid var(--border);">
              🎓 Independent Instructor (No Affiliation)
            </div>
          <?php endif; ?>
        <?php endif; ?>
      </div>
    </div>

    <!-- Quick Stats Row -->
    <div class="stat-row" style="margin-bottom: 2rem;">
      <div class="stat-card">
        <div class="stat-card-header">
          <div class="stat-card-icon enrolled">📖</div>
        </div>
        <div class="stat-card-label">Courses Created</div>
        <div class="stat-card-value"><?= number_format($org_courses_count) ?></div>
      </div>
      <div class="stat-card" onclick="window.location='organization/enrolled-students.php'" style="cursor:pointer; transition: transform 0.2s;">
        <div class="stat-card-header">
          <div class="stat-card-icon hours">🎓</div>
        </div>
        <div class="stat-card-label">Total Student Enrollments</div>
        <div class="stat-card-value"><?= number_format($org_total_students) ?></div>
      </div>
      <?php if ($is_org): ?>
      <div class="stat-card" onclick="window.location='organization/manage-teachers.php'" style="cursor:pointer; transition: transform 0.2s;">
        <div class="stat-card-header">
          <div class="stat-card-icon certificates">💼</div>
        </div>
        <div class="stat-card-label">Affiliated Instructors</div>
        <div class="stat-card-value"><?= number_format($org_teachers_count) ?></div>
      </div>
      <?php endif; ?>
    </div>

    <!-- Teacher Notifications -->
    <?php if ($is_teacher && !empty($teacher_messages)): ?>
    <h3 style="margin-bottom:1rem;font-size:1.1rem;color:var(--ink);">📩 Notifications & Organization Broadcasts</h3>
    <div style="display:flex; flex-direction:column; gap:1rem; margin-bottom:2.5rem;">
      <?php foreach ($teacher_messages as $msg): 
          $isMsgRead = (bool) ($msg['isRead'] ?? false);
      ?>
        <div style="background:var(--white); border:1px solid <?= $isMsgRead ? 'var(--border)' : 'var(--gold)' ?>; border-radius:16px; padding:1.25rem; box-shadow:var(--shadow); display:flex; justify-content:space-between; align-items:start; flex-wrap:wrap; gap:1rem;">
          <div style="flex:1;">
            <div style="display:flex; align-items:center; gap:0.5rem; flex-wrap:wrap;">
              <span style="font-weight:700; color:var(--ink); font-size:0.95rem;"><?= e($msg['senderName']) ?></span>
              <span style="font-size:0.75rem; background:var(--gold-lt); color:var(--ink); padding:0.15rem 0.5rem; border-radius:4px; font-weight:600;">Broadcast</span>
              <?php if (!$isMsgRead): ?>
                <span style="font-size:0.72rem; background:#DCFCE7; color:#15803D; padding:0.15rem 0.5rem; border-radius:4px; font-weight:700;">NEW</span>
              <?php endif; ?>
            </div>
            <p style="font-size:0.88rem; color:var(--ink-mid); margin-top:0.5rem; line-height:1.5; white-space:pre-line;"><?= e($msg['messageText']) ?></p>
            <div style="font-size:0.75rem; color:var(--ink-soft); margin-top:0.5rem;">
              Received <?= date('d-m-Y • h:i A', strtotime($msg['createdAt'])) ?>
            </div>
          </div>
          <?php if (!$isMsgRead): ?>
            <form method="POST" action="dashboard.php" style="margin:0;">
              <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>" />
              <input type="hidden" name="mark_msg_read" value="1" />
              <input type="hidden" name="message_id" value="<?= e($msg['__id']) ?>" />
              <button type="submit" class="btn-pill btn-pill-outline" style="padding:0.35rem 0.75rem; font-size:0.75rem; min-width:auto;">Mark as Read</button>
            </form>
          <?php endif; ?>
        </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- Action Shortcuts -->
    <h3 style="margin-bottom:1rem;font-size:1.1rem;color:var(--ink);">⚡ Quick Actions</h3>
    <div class="enrolled-grid" style="margin-bottom: 2.5rem; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));">
      <div class="enrolled-card" onclick="window.location='organization/create-course.php'" style="cursor:pointer; transition: transform 0.2s; padding:1.25rem;">
        <div class="enrolled-thumb" style="background:var(--gold-lt); font-size: 1.8rem; display:flex; align-items:center; justify-content:center;">➕</div>
        <div class="enrolled-body" style="padding-top:0.75rem;">
          <div class="enrolled-title" style="font-size:1rem; font-weight:600;">Create Course</div>
          <p style="font-size:0.78rem; color:var(--ink-soft); margin-top:0.25rem;">Draft and publish new courses for students.</p>
        </div>
      </div>
      <div class="enrolled-card" onclick="window.location='organization/manage-courses.php'" style="cursor:pointer; transition: transform 0.2s; padding:1.25rem;">
        <div class="enrolled-thumb" style="background:#E0F2FE; font-size: 1.8rem; display:flex; align-items:center; justify-content:center;">⚙️</div>
        <div class="enrolled-body" style="padding-top:0.75rem;">
          <div class="enrolled-title" style="font-size:1rem; font-weight:600;">Manage Courses</div>
          <p style="font-size:0.78rem; color:var(--ink-soft); margin-top:0.25rem;">View status, enrollments, and edit your courses.</p>
        </div>
      </div>
      <div class="enrolled-card" onclick="window.location='organization/enrolled-students.php'" style="cursor:pointer; transition: transform 0.2s; padding:1.25rem;">
        <div class="enrolled-thumb" style="background:#FAF7F2; font-size: 1.8rem; display:flex; align-items:center; justify-content:center;">👨‍🎓</div>
        <div class="enrolled-body" style="padding-top:0.75rem;">
          <div class="enrolled-title" style="font-size:1rem; font-weight:600;">Enrolled Students</div>
          <p style="font-size:0.78rem; color:var(--ink-soft); margin-top:0.25rem;">View students details and send feedback messages.</p>
        </div>
      </div>
      <?php if ($is_org): ?>
      <div class="enrolled-card" onclick="window.location='organization/manage-teachers.php'" style="cursor:pointer; transition: transform 0.2s; padding:1.25rem;">
        <div class="enrolled-thumb" style="background:#FAF2FA; font-size: 1.8rem; display:flex; align-items:center; justify-content:center;">💼</div>
        <div class="enrolled-body" style="padding-top:0.75rem;">
          <div class="enrolled-title" style="font-size:1rem; font-weight:600;">Manage Teachers</div>
          <p style="font-size:0.78rem; color:var(--ink-soft); margin-top:0.25rem;">Approve, review, and manage your teaching staff.</p>
        </div>
      </div>
      <div class="enrolled-card" onclick="window.location='organization/edit-profile.php'" style="cursor:pointer; transition: transform 0.2s; padding:1.25rem;">
        <div class="enrolled-thumb" style="background:#DCFCE7; font-size: 1.8rem; display:flex; align-items:center; justify-content:center;">🏢</div>
        <div class="enrolled-body" style="padding-top:0.75rem;">
          <div class="enrolled-title" style="font-size:1rem; font-weight:600;">Edit Profile</div>
          <p style="font-size:0.78rem; color:var(--ink-soft); margin-top:0.25rem;">Update contact info and official brand details.</p>
        </div>
      </div>
      <?php endif; ?>
      <?php if ($is_org || ($is_teacher && $teacher_org_id !== '')): ?>
      <div class="enrolled-card" onclick="window.location='organization/manage-webinars.php'" style="cursor:pointer; transition: transform 0.2s; padding:1.25rem;">
        <div class="enrolled-thumb" style="background:#FFF9DB; font-size: 1.8rem; display:flex; align-items:center; justify-content:center;">🎙️</div>
        <div class="enrolled-body" style="padding-top:0.75rem;">
          <div class="enrolled-title" style="font-size:1rem; font-weight:600;">Manage Webinars</div>
          <p style="font-size:0.78rem; color:var(--ink-soft); margin-top:0.25rem;">Schedule and host live Google Meet webinars.</p>
        </div>
      </div>
      <?php endif; ?>
    </div>

    <!-- Scheduled Webinars -->
    <?php if ($is_org || ($is_teacher && $teacher_org_id !== '')): ?>
    <div class="section-row" style="margin-top: 2rem;">
      <h2>📅 Scheduled Webinars</h2>
      <?php if (!empty($org_webinars) && $is_org): ?>
        <a href="organization/manage-webinars.php">Manage Webinars →</a>
      <?php endif; ?>
    </div>

    <div class="manage-table-card" style="background:var(--white); border:1px solid var(--border); border-radius:var(--radius-lg); box-shadow:var(--shadow); overflow:hidden; margin-bottom: 2rem;">
      <div style="overflow-x:auto;">
        <?php if (empty($org_webinars)): ?>
          <div style="text-align:center; padding:3rem; color:var(--ink-soft);">
            <div style="font-size:2.5rem; margin-bottom:0.75rem;">🎙️</div>
            <h3>No webinars scheduled yet</h3>
            <?php if ($is_org): ?>
              <p style="font-size:0.85rem; margin-top:0.3rem; margin-bottom:1.25rem;">Schedule a live video webinar to interact with your students.</p>
              <a href="organization/create-webinar.php" class="btn-primary" style="text-decoration:none; display:inline-flex;">+ Schedule Webinar</a>
            <?php else: ?>
              <p style="font-size:0.85rem; margin-top:0.3rem;">There are no webinars scheduled by your organization at this time.</p>
            <?php endif; ?>
          </div>
        <?php else: ?>
          <table style="width:100%; border-collapse:collapse; font-size:0.88rem; text-align:left;">
            <thead>
              <tr style="background:var(--page-bg); border-bottom:1px solid var(--border);">
                <th style="padding:0.85rem 1.25rem; font-size:0.72rem; color:var(--ink-soft); font-weight:600; text-transform:uppercase;">Webinar</th>
                <th style="padding:0.85rem 1.25rem; font-size:0.72rem; color:var(--ink-soft); font-weight:600; text-transform:uppercase;">Date & Time</th>
                <th style="padding:0.85rem 1.25rem; font-size:0.72rem; color:var(--ink-soft); font-weight:600; text-transform:uppercase;">Meet Link</th>
                <th style="padding:0.85rem 1.25rem; font-size:0.72rem; color:var(--ink-soft); font-weight:600; text-transform:uppercase;">Status</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach (array_slice($org_webinars, 0, 5) as $w): 
                $statusText = $w['status'] ?? 'draft';
                $statusClass = $statusText === 'published' ? 'background:#DCFCE7;color:#15803D;' : 'background:#F3F4F6;color:#4B5563;';
              ?>
                <tr style="border-bottom:1px solid var(--border);">
                  <td style="padding:0.85rem 1.25rem; font-weight:600; display:flex; align-items:center; gap:0.75rem;">
                    <span style="font-size:1.5rem;">📹</span>
                    <div>
                      <div><?= e($w['title']) ?></div>
                      <div style="font-size:0.75rem; color:var(--ink-soft); font-weight:normal;"><?= e(substr($w['description'], 0, 80)) ?><?= strlen($w['description']) > 80 ? '...' : '' ?></div>
                    </div>
                  </td>
                  <td style="padding:0.85rem 1.25rem; color:var(--ink-mid);"><?= date('d-m-Y • h:i A', strtotime($w['dateTime'])) ?></td>
                  <td style="padding:0.85rem 1.25rem;">
                    <a href="<?= e($w['meetLink']) ?>" target="_blank" rel="noopener noreferrer" style="color:var(--gold); text-decoration:none; font-weight:500;">
                      Join Meet
                    </a>
                  </td>
                  <td style="padding:0.85rem 1.25rem;">
                    <span style="display:inline-flex; align-items:center; padding:0.2rem 0.5rem; border-radius:50px; font-size:0.7rem; font-weight:600; text-transform:uppercase; <?= $statusClass ?>">
                      <?= e($statusText) ?>
                    </span>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        <?php endif; ?>
      </div>
    </div>
    <?php endif; ?>

    <!-- Recent Courses Table -->
    <div class="section-row">
      <h2>📁 Recently Created Courses</h2>
      <?php if ($org_courses_count > 0): ?>
        <a href="organization/manage-courses.php">View all (<?= $org_courses_count ?>) →</a>
      <?php endif; ?>
    </div>

    <div class="manage-table-card" style="background:var(--white); border:1px solid var(--border); border-radius:var(--radius-lg); box-shadow:var(--shadow); overflow:hidden;">
      <div style="overflow-x:auto;">
        <?php if (empty($org_recent_courses)): ?>
          <div style="text-align:center; padding:3rem; color:var(--ink-soft);">
            <div style="font-size:2.5rem; margin-bottom:0.75rem;">📖</div>
            <h3>No courses created yet</h3>
            <p style="font-size:0.85rem; margin-top:0.3rem; margin-bottom:1.25rem;">Create a course to begin.</p>
            <a href="organization/create-course.php" class="btn-primary" style="text-decoration:none; display:inline-flex;">+ Create Course</a>
          </div>
        <?php else: ?>
          <table style="width:100%; border-collapse:collapse; font-size:0.88rem; text-align:left;">
            <thead>
              <tr style="background:var(--page-bg); border-bottom:1px solid var(--border);">
                <th style="padding:0.85rem 1.25rem; font-size:0.72rem; color:var(--ink-soft); font-weight:600; text-transform:uppercase;">Course</th>
                <th style="padding:0.85rem 1.25rem; font-size:0.72rem; color:var(--ink-soft); font-weight:600; text-transform:uppercase;">Category</th>
                <th style="padding:0.85rem 1.25rem; font-size:0.72rem; color:var(--ink-soft); font-weight:600; text-transform:uppercase;">Enrollments</th>
                <th style="padding:0.85rem 1.25rem; font-size:0.72rem; color:var(--ink-soft); font-weight:600; text-transform:uppercase;">Status</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($org_recent_courses as $c):
                // Determine course image
                $courseImage = '';
                $titleLower = strtolower($c['title'] ?? '');
                $catLower = strtolower($c['category'] ?? '');
                if (!empty($c['imageUrl'])) {
                    $courseImage = $c['imageUrl'];
                } elseif (str_contains($titleLower, 'python') || str_contains($titleLower, 'data structure') || str_contains($titleLower, 'algorithm')) {
                    $courseImage = '../assets/images/dsa_python.png';
                } elseif (str_contains($titleLower, 'web dev') || str_contains($titleLower, 'bootcamp') || str_contains($titleLower, 'javascript') || str_contains($titleLower, 'html') || str_contains($titleLower, 'css')) {
                    $courseImage = '../assets/images/web_dev.png';
                } elseif (str_contains($titleLower, 'database') || str_contains($titleLower, 'sql')) {
                    $courseImage = '../assets/images/database_sql.png';
                } elseif (str_contains($catLower, 'law') || str_contains($catLower, 'justice')) {
                    $courseImage = '../assets/images/constitutional_law.png';
                } elseif (str_contains($catLower, 'technology') || str_contains($catLower, 'computer science')) {
                    $courseImage = '../assets/images/web_dev.png';
                } elseif (str_contains($catLower, 'business') || str_contains($catLower, 'compliance')) {
                    $courseImage = '../assets/images/business_compliance.png';
                } elseif (str_contains($catLower, 'personal') || str_contains($catLower, 'development') || str_contains($catLower, 'communication')) {
                    $courseImage = '../assets/images/personal_development.png';
                } else {
                    $courseImage = '../assets/images/constitutional_law.png';
                }

                $statusText = $c['status'] ?? 'draft';
                $statusClass = $statusText === 'published' ? 'background:#DCFCE7;color:#15803D;' : 'background:#F3F4F6;color:#4B5563;';
              ?>
                <tr style="border-bottom:1px solid var(--border);">
                  <td style="padding:0.85rem 1.25rem; font-weight:600; display:flex; align-items:center; gap:0.75rem;">
                    <div style="width:36px; height:36px; border-radius:6px; background-image:url('<?= e($courseImage) ?>'); background-size:cover; background-position:center; flex-shrink:0;"></div>
                    <span><?= e($c['title']) ?></span>
                  </td>
                  <td style="padding:0.85rem 1.25rem; color:var(--ink-mid);"><?= e($c['category'] ?? '') ?></td>
                  <td style="padding:0.85rem 1.25rem; font-weight:600;"><?= number_format((int)($c['enrollment_count'] ?? 0)) ?> students</td>
                  <td style="padding:0.85rem 1.25rem;">
                    <span style="display:inline-flex; align-items:center; padding:0.2rem 0.5rem; border-radius:50px; font-size:0.7rem; font-weight:600; text-transform:uppercase; <?= $statusClass ?>">
                      <?= e($statusText) ?>
                    </span>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        <?php endif; ?>
      </div>
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
        <a href="student/edit-profile.php" class="nudge-btn">Complete Profile</a>
        <button class="nudge-close" onclick="dismissNudge()" aria-label="Dismiss">&times;</button>
      </div>
    </div>
    <?php endif; ?>

    <?php /* ── Quick Stats Row ── */ ?>
    <div class="stat-row">
      <div class="stat-card" onclick="window.location='my-learnings.php?filter=all'" style="cursor:pointer; transition: transform 0.2s;" onmouseenter="this.style.transform='translateY(-2px)';" onmouseleave="this.style.transform='none';">
        <div class="stat-card-header">
          <div class="stat-card-icon enrolled">📚</div>
        </div>
        <div class="stat-card-label">Courses Enrolled</div>
        <div class="stat-card-value"><?= $stats['enrolled'] ?></div>
      </div>
      <div class="stat-card" onclick="window.location='my-learnings.php?filter=completed'" style="cursor:pointer; transition: transform 0.2s;" onmouseenter="this.style.transform='translateY(-2px)';" onmouseleave="this.style.transform='none';">
        <div class="stat-card-header">
          <div class="stat-card-icon completed">✅</div>
        </div>
        <div class="stat-card-label">Courses Completed</div>
        <div class="stat-card-value"><?= $stats['completed'] ?></div>
      </div>
      <div class="stat-card" onclick="window.location='student/edit-profile.php?tab=credits'" style="cursor:pointer; transition: transform 0.2s;" onmouseenter="this.style.transform='translateY(-2px)';" onmouseleave="this.style.transform='none';">
        <div class="stat-card-header">
          <div class="stat-card-icon cert">🪙</div>
        </div>
        <div class="stat-card-label">Total Credits</div>
        <div class="stat-card-value"><?= number_format($stats['credits']) ?></div>
      </div>
      <div class="stat-card">
        <div class="stat-card-header">
          <div class="stat-card-icon hours">⏱</div>
        </div>
        <div class="stat-card-label">Hours Studied</div>
        <div class="stat-card-value"><?= $stats['hours_studied'] ?></div>
      </div>
    </div>

    <?php /* ── Instructor Messages & Feedback Inbox ── */ ?>
    <?php if (!empty($student_messages)): ?>
    <div class="section-row" style="margin-top: 2rem;">
      <h2>📩 Instructor Feedback & Messages</h2>
    </div>
    <div style="display:flex; flex-direction:column; gap:1rem; margin-bottom:2rem;">
      <?php foreach ($student_messages as $msg): 
          $isMsgRead = (bool) ($msg['isRead'] ?? false);
      ?>
        <div style="background:var(--white); border:1px solid <?= $isMsgRead ? 'var(--border)' : 'var(--gold)' ?>; border-radius:16px; padding:1.25rem; box-shadow:var(--shadow); display:flex; justify-content:space-between; align-items:start; flex-wrap:wrap; gap:1rem; position:relative;">
          <div style="flex:1;">
            <div style="display:flex; align-items:center; gap:0.5rem; flex-wrap:wrap;">
              <span style="font-weight:700; color:var(--ink); font-size:0.95rem;"><?= e($msg['senderName']) ?></span>
              <span style="font-size:0.75rem; background:var(--gold-lt); color:var(--ink); padding:0.15rem 0.5rem; border-radius:4px; font-weight:600;">Course: <?= e($msg['courseTitle']) ?></span>
              <?php if (!$isMsgRead): ?>
                <span style="font-size:0.72rem; background:#DCFCE7; color:#15803D; padding:0.15rem 0.5rem; border-radius:4px; font-weight:700;">NEW</span>
              <?php endif; ?>
            </div>
            <p style="font-size:0.88rem; color:var(--ink-mid); margin-top:0.5rem; line-height:1.5; white-space:pre-line;"><?= e($msg['messageText']) ?></p>
            <div style="font-size:0.75rem; color:var(--ink-soft); margin-top:0.5rem;">
              Received <?= date('M j, Y • g:i A', strtotime($msg['createdAt'])) ?>
            </div>
          </div>
          <?php if (!$isMsgRead): ?>
            <form method="POST" action="dashboard.php">
              <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>" />
              <input type="hidden" name="mark_msg_read" value="1" />
              <input type="hidden" name="message_id" value="<?= e($msg['__id']) ?>" />
              <button type="submit" class="btn-pill btn-pill-outline" style="padding:0.35rem 0.75rem; font-size:0.75rem; min-width:auto;">Mark as Read</button>
            </form>
          <?php endif; ?>
        </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <?php /* ── Continue Learning ── */ ?>
    <?php if ($last_course): ?>
    <div class="section-row">
      <h2>▶ Continue Learning</h2>
      <a href="my-learnings.php">My Learnings →</a>
    </div>
    <div class="continue-card">
      <?php
        $lastCourseImage = '';
        $titleLower = strtolower($last_course['title'] ?? '');
        $catLower = strtolower($last_course['category'] ?? '');
        if (!empty($last_course['imageUrl'])) {
            $lastCourseImage = $last_course['imageUrl'];
        } elseif (str_contains($titleLower, 'python') || str_contains($titleLower, 'data structure') || str_contains($titleLower, 'algorithm')) {
            $lastCourseImage = '../assets/images/dsa_python.png';
        } elseif (str_contains($titleLower, 'web dev') || str_contains($titleLower, 'bootcamp') || str_contains($titleLower, 'javascript') || str_contains($titleLower, 'html') || str_contains($titleLower, 'css')) {
            $lastCourseImage = '../assets/images/web_dev.png';
        } elseif (str_contains($titleLower, 'database') || str_contains($titleLower, 'sql')) {
            $lastCourseImage = '../assets/images/database_sql.png';
        } elseif (str_contains($catLower, 'law') || str_contains($catLower, 'justice')) {
            $lastCourseImage = '../assets/images/constitutional_law.png';
        } elseif (str_contains($catLower, 'technology') || str_contains($catLower, 'computer science')) {
            $lastCourseImage = '../assets/images/web_dev.png';
        } elseif (str_contains($catLower, 'business') || str_contains($catLower, 'compliance')) {
            $lastCourseImage = '../assets/images/business_compliance.png';
        } elseif (str_contains($catLower, 'personal') || str_contains($catLower, 'development') || str_contains($catLower, 'communication')) {
            $lastCourseImage = '../assets/images/personal_development.png';
        } else {
            $lastCourseImage = '../assets/images/constitutional_law.png';
        }
      ?>
      <?php if (!empty($lastCourseImage)): ?>
        <div class="continue-icon" style="background-image: url('<?= e($lastCourseImage) ?>'); background-size: cover; background-position: center; font-size: 0; color: transparent;"></div>
      <?php else: ?>
        <div class="continue-icon">⚖️</div>
      <?php endif; ?>
      <div class="continue-info">
        <div class="continue-label">Pick up where you left off</div>
        <div class="continue-title"><?= e($last_course['title']) ?></div>
        <div class="continue-meta">
          <?= (int) $last_course['completed_lessons'] ?> / <?= (int) $last_course['total_lessons'] ?> lessons completed
          <?php if (!empty($last_course['last_accessed_at'])): ?>
            · Last accessed <?= date('M j', strtotime($last_course['last_accessed_at'])) ?>
          <?php endif; ?>
        </div>
      </div>
      <div class="continue-progress">
        <div class="continue-progress-bar">
          <div class="continue-progress-fill" style="width:<?= round((float) $last_course['progress_percentage']) ?>%"></div>
        </div>
        <div class="continue-progress-text"><?= round((float) $last_course['progress_percentage']) ?>% complete</div>
      </div>
      <a href="student/course-workspace.php?course_id=<?= e($last_course['course_id']) ?>" class="continue-btn">Continue →</a>
    </div>
    <?php elseif (empty($enrolled_courses)): ?>
    <div class="empty-state">
      <div class="empty-state-icon">📖</div>
      <div class="empty-state-text">You haven't started a course yet.</div>
      <a href="courses.php">Browse courses →</a>
    </div>
    <?php endif; ?>

    <?php /* ── Enrolled Courses ── */ ?>
    <?php if (!empty($enrolled_courses)): ?>
    <div class="section-row">
      <h2>📚 My Courses</h2>
      <a href="my-learnings.php">View all →</a>
    </div>
    <div class="enrolled-grid">
      <?php foreach (array_slice($enrolled_courses, 0, 5) as $ec):
        // Determine course image
        $courseImage = '';
        $titleLower = strtolower($ec['title'] ?? '');
        $catLower = strtolower($ec['category'] ?? '');
        if (!empty($ec['imageUrl'])) {
            $courseImage = $ec['imageUrl'];
        } elseif (str_contains($titleLower, 'python') || str_contains($titleLower, 'data structure') || str_contains($titleLower, 'algorithm')) {
            $courseImage = '../assets/images/dsa_python.png';
        } elseif (str_contains($titleLower, 'web dev') || str_contains($titleLower, 'bootcamp') || str_contains($titleLower, 'javascript') || str_contains($titleLower, 'html') || str_contains($titleLower, 'css')) {
            $courseImage = '../assets/images/web_dev.png';
        } elseif (str_contains($titleLower, 'database') || str_contains($titleLower, 'sql')) {
            $courseImage = '../assets/images/database_sql.png';
        } elseif (str_contains($catLower, 'law') || str_contains($catLower, 'justice')) {
            $courseImage = '../assets/images/constitutional_law.png';
        } elseif (str_contains($catLower, 'technology') || str_contains($catLower, 'computer science')) {
            $courseImage = '../assets/images/web_dev.png';
        } elseif (str_contains($catLower, 'business') || str_contains($catLower, 'compliance')) {
            $courseImage = '../assets/images/business_compliance.png';
        } elseif (str_contains($catLower, 'personal') || str_contains($catLower, 'development') || str_contains($catLower, 'communication')) {
            $courseImage = '../assets/images/personal_development.png';
        } else {
            $courseImage = '../assets/images/constitutional_law.png';
        }
      ?>
      <div class="enrolled-card">
        <a href="course-detail.php?id=<?= e($ec['id']) ?>" style="text-decoration:none;display:block;" tabindex="-1" aria-hidden="true">
        <?php if (!empty($courseImage)): ?>
          <div class="enrolled-thumb" style="background-image: url('<?= e($courseImage) ?>'); background-size: cover; background-position: center; font-size: 0; color: transparent; cursor: pointer;"></div>
        <?php else: ?>
          <div class="enrolled-thumb" style="cursor: pointer;">⚖️</div>
        <?php endif; ?>
        </a>
        <div class="enrolled-body">
          <a href="course-detail.php?id=<?= e($ec['id']) ?>" style="text-decoration:none;color:inherit;">
            <div class="enrolled-title" style="cursor:pointer;"><?= e($ec['title']) ?></div>
          </a>
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
            <a href="course-detail.php?id=<?= e($ec['id']) ?>" class="enrolled-continue">View Content →</a>
          </div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <?php /* ── Upcoming Webinars ── */ ?>
    <?php if (!empty($upcoming_webinars)): ?>
    <div class="section-row" style="margin-top: 2rem;">
      <h2>🎙️ Upcoming Live Webinars</h2>
    </div>
    <div class="enrolled-grid" style="margin-bottom: 2.5rem; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));">
      <?php foreach ($upcoming_webinars as $w): ?>
      <div class="enrolled-card" style="display:flex; flex-direction:column; justify-content:space-between; height: 100%;">
        <div class="enrolled-thumb" style="background:#FFF9DB; font-size: 2.2rem; display:flex; align-items:center; justify-content:center; height:120px;">
          📹
        </div>
        <div class="enrolled-body" style="flex:1; display:flex; flex-direction:column; justify-content:space-between; padding: 1.25rem;">
          <div>
            <span style="font-size:0.7rem; font-weight:700; color:var(--gold); text-transform:uppercase; letter-spacing:0.05em; display:block; margin-bottom:0.25rem;">
              Hosted by <?= e($w['organizationName']) ?>
            </span>
            <div class="enrolled-title" style="margin-bottom:0.4rem; font-size:1rem; font-weight:700; color:var(--ink); line-height: 1.3;"><?= e($w['title']) ?></div>
            <p style="font-size:0.8rem; color:var(--ink-soft); line-height:1.4; margin-bottom:1rem; display:-webkit-box; -webkit-line-clamp:3; -webkit-box-orient:vertical; overflow:hidden;">
              <?= e($w['description']) ?>
            </p>
          </div>
          <div>
            <div style="font-size:0.82rem; color:var(--ink-mid); font-weight:600; margin-bottom:0.75rem; display:flex; align-items:center; gap:0.35rem;">
              <span>📅</span> <?= date('d-m-Y • h:i A', strtotime($w['dateTime'])) ?>
            </div>
            <a href="<?= e($w['meetLink']) ?>" target="_blank" rel="noopener noreferrer" class="btn-pill btn-pill-primary" style="width:100%; display:flex; align-items:center; justify-content:center; gap:0.4rem; background:#0F9D58; border-color:#0F9D58; color:white; padding:0.6rem; font-size:0.85rem; border-radius:12px; text-decoration:none;">
              <span>📹</span> Join Google Meet
            </a>
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
          <h3>📜 Earned Certificates</h3>
          <?php if (!empty($user_certs)): ?>
            <a href="my-learnings.php?filter=certificates" class="dash-card-link">View all (<?= count($user_certs) ?>) →</a>
          <?php endif; ?>
        </div>
        <div class="cert-row">
          <?php if (empty($user_certs)): ?>
            <div class="cert-empty">Complete 100% of a course to earn your official certificate 🎯</div>
          <?php else: ?>
            <?php foreach (array_slice($user_certs, 0, 3) as $cert): ?>
            <div class="cert-card" onclick="window.open('student/view-certificate.php?id=<?= e($cert['__id'] ?? $cert['id']) ?>', '_blank')" style="cursor:pointer; transition: transform 0.2s;" onmouseenter="this.style.transform='translateY(-2px)';" onmouseleave="this.style.transform='none';">
              <div class="cert-icon">🏅</div>
              <div class="cert-course" style="font-weight:700; color:var(--ink); line-height:1.25;"><?= e($cert['courseTitle'] ?? 'Course Certificate') ?></div>
              <div class="cert-number" style="font-family:monospace; color:var(--gold-dk); font-weight:600; margin-top:0.25rem; font-size:0.72rem;"><?= e($cert['certNumber'] ?? '') ?></div>
              <div style="font-size:0.68rem; color:var(--ink-soft); margin-top:0.25rem;"><?= date('M j, Y', strtotime($cert['issuedAt'] ?? 'now')) ?></div>
              <div style="margin-top:0.4rem; font-size:0.75rem; color:var(--gold-dk); font-weight:700;">View & Print Certificate →</div>
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
      <a href="courses.php">Browse all →</a>
    </div>
    <div class="rec-track" style="margin-bottom:1.5rem;">
      <?php foreach ($recommended as $rc):
        // Determine course image
        $courseImage = '';
        $titleLower = strtolower($rc['title'] ?? '');
        $catLower = strtolower($rc['category'] ?? '');
        if (!empty($rc['imageUrl'])) {
            $courseImage = $rc['imageUrl'];
        } elseif (str_contains($titleLower, 'python') || str_contains($titleLower, 'data structure') || str_contains($titleLower, 'algorithm')) {
            $courseImage = '../assets/images/dsa_python.png';
        } elseif (str_contains($titleLower, 'web dev') || str_contains($titleLower, 'bootcamp') || str_contains($titleLower, 'javascript') || str_contains($titleLower, 'html') || str_contains($titleLower, 'css')) {
            $courseImage = '../assets/images/web_dev.png';
        } elseif (str_contains($titleLower, 'database') || str_contains($titleLower, 'sql')) {
            $courseImage = '../assets/images/database_sql.png';
        } elseif (str_contains($catLower, 'law') || str_contains($catLower, 'justice')) {
            $courseImage = '../assets/images/constitutional_law.png';
        } elseif (str_contains($catLower, 'technology') || str_contains($catLower, 'computer science')) {
            $courseImage = '../assets/images/web_dev.png';
        } elseif (str_contains($catLower, 'business') || str_contains($catLower, 'compliance')) {
            $courseImage = '../assets/images/business_compliance.png';
        } elseif (str_contains($catLower, 'personal') || str_contains($catLower, 'development') || str_contains($catLower, 'communication')) {
            $courseImage = '../assets/images/personal_development.png';
        } else {
            $courseImage = '../assets/images/constitutional_law.png';
        }
      ?>
      <div class="rec-card">
        <a href="course-detail.php?id=<?= e($rc['id']) ?>" style="text-decoration:none;display:block;" tabindex="-1" aria-hidden="true">
        <?php if (!empty($courseImage)): ?>
          <div class="rec-thumb" style="background-image: url('<?= e($courseImage) ?>'); background-size: cover; background-position: center; font-size: 0; color: transparent; cursor: pointer;"></div>
        <?php else: ?>
          <div class="rec-thumb" style="cursor: pointer;">⚖️</div>
        <?php endif; ?>
        </a>
        <div class="rec-body">
          <a href="course-detail.php?id=<?= e($rc['id']) ?>" style="text-decoration:none;color:inherit;">
            <div class="rec-title" style="cursor:pointer;"><?= e($rc['title']) ?></div>
          </a>
          <div class="rec-footer">
            <span class="rec-price">
              <?= (float) $rc['price'] > 0 ? '₹' . number_format((float) $rc['price']) : '<span class="free-label">Free</span>' ?>
            </span>
            <a href="course-detail.php?id=<?= e($rc['id']) ?>" class="rec-enroll">Explore →</a>
          </div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>

<?php endif; /* end student view */ ?>

  </div>
</div>

<script src="../assets/js/script.js"></script>
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
      xhr.open('POST', '../api/dashboard-ajax.php?action=dismiss_nudge', true);
      xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
      xhr.send('student_id=<?= $student_id ?>');
    }
  };
})();
</script>
</body>
</html>
