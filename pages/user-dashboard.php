<?php
declare(strict_types=1);

require_once __DIR__ . '/../backend/includes/functions.php';
require_once __DIR__ . '/../backend/includes/db.php';

start_secure_session();
$user = require_login('user');

$pdo = get_pdo();
$studentId = (int)($user['id'] ?? 0);

// My Courses
$coursesStmt = $pdo->prepare(
    "SELECT c.id, c.title, c.slug, e.progress, e.status, o.organization_name
     FROM enrollments e
     JOIN courses c ON c.id = e.course_id
     LEFT JOIN organizations o ON o.id = c.organization_id
     WHERE e.student_id = :sid
     ORDER BY e.enrolled_at DESC"
);
$coursesStmt->execute([':sid' => $studentId]);
$myCourses = $coursesStmt->fetchAll();

// My Certifications
$certStmt = $pdo->prepare(
    "SELECT ct.id, ct.title, ct.issued_at, c.title AS course_title, c.slug AS course_slug
     FROM certifications ct
     JOIN courses c ON c.id = ct.course_id
     WHERE ct.student_id = :sid
     ORDER BY ct.issued_at DESC"
);
$certStmt->execute([':sid' => $studentId]);
$myCerts = $certStmt->fetchAll();

// Credits
$creditsStmt = $pdo->prepare(
    "SELECT id, amount, reason, created_at
     FROM credits
     WHERE student_id = :sid
     ORDER BY created_at DESC"
);
$creditsStmt->execute([':sid' => $studentId]);
$creditRows = $creditsStmt->fetchAll();

$totalCredits = 0;
foreach ($creditRows as $r) {
    $totalCredits += (int)($r['amount'] ?? 0);
}

// Friends (accepted)
$friendsStmt = $pdo->prepare(
    "SELECT s.id, s.name, s.username, s.email, sp.city
     FROM student_friends f
     JOIN students s ON s.id = f.friend_id
     LEFT JOIN student_profiles sp ON sp.student_id = s.id
     WHERE f.student_id = :sid AND f.status = 'accepted'
     ORDER BY s.name ASC"
);
$friendsStmt->execute([':sid' => $studentId]);
$friends = $friendsStmt->fetchAll();

// Messages (inbox)
$msgStmt = $pdo->prepare(
    "SELECT m.id, m.from_student_id, m.content, m.is_read, m.created_at,
            fs.name AS from_name
     FROM messages m
     JOIN students fs ON fs.id = m.from_student_id
     WHERE m.to_student_id = :sid
     ORDER BY m.created_at DESC
     LIMIT 20"
);
$msgStmt->execute([':sid' => $studentId]);
$messages = $msgStmt->fetchAll();

// Simple quizzes list (based on enrolled courses)
$quizStmt = $pdo->prepare(
    "SELECT DISTINCT q.id, q.title, q.description
     FROM quizzes q
     JOIN enrollments e ON e.course_id = q.course_id
     WHERE e.student_id = :sid
     ORDER BY q.created_at DESC
     LIMIT 10"
);
$quizStmt->execute([':sid' => $studentId]);
$quizzes = $quizStmt->fetchAll();

$flash = get_flash();
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>User Dashboard — Lawable</title>
  <link rel="stylesheet" href="../assets/css/lawable.css" />
  <style>
    body { margin:0; font-family: Inter, sans-serif; background:#07111f; color:#f7f7f2; }
    .wrap { max-width: 1100px; margin: 2rem auto; padding: 0 1.2rem; }
    .hero {
      display:flex; align-items:flex-start; justify-content:space-between;
      gap:1rem; padding: 1.4rem; background: rgba(255,255,255,0.08);
      border-radius: 18px; border: 1px solid rgba(255,255,255,0.10);
    }
    h1 { margin:0 0 0.4rem; }
    .grid { display:grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-top: 1rem; }
    @media (max-width: 900px) { .grid { grid-template-columns: 1fr; } }
    .card {
      background: rgba(255,255,255,0.08);
      border: 1px solid rgba(255,255,255,0.10);
      border-radius: 18px;
      padding: 1rem;
    }
    .card h2 { font-size: 1.05rem; margin:0 0 0.8rem; color: #f2c94c; }
    .item { padding: 0.7rem 0; border-bottom: 1px dashed rgba(255,255,255,0.12); }
    .item:last-child { border-bottom: 0; }
    .meta { color: rgba(247,247,242,0.75); font-size: 0.9rem; }
    a { color:#f2c94c; text-decoration:none; }
    a:hover { text-decoration:underline; }
    .btn { display:inline-block; padding:0.6rem 0.9rem; border-radius:999px; background:#f2c94c; color:#07111f; font-weight:700; }
    .btn-ghost { background: transparent; border:1px solid rgba(242,201,76,0.6); color:#f2c94c; }
    .flash { padding:0.8rem 1rem; border-radius:12px; background:#14532d; border:1px solid rgba(255,255,255,0.12); margin-top: 1rem; }
  </style>
</head>
<body>
  <div class="wrap">
    <div class="hero">
      <div>
        <h1>Welcome, <?= e($user['name']) ?></h1>
        <div class="meta">Student Dashboard • <?= e($user['email'] ?? '') ?></div>
        <?php if ($flash): ?>
          <div class="flash"><?= e($flash['message']) ?></div>
        <?php endif; ?>
      </div>
      <div style="display:flex; flex-direction:column; gap:0.6rem; align-items:flex-end;">
        <a class="btn btn-ghost" href="../backend/logout.php">Logout</a>
      </div>
    </div>

    <div class="grid">
      <div class="card">
        <h2>My Courses</h2>
        <?php if (!$myCourses): ?>
          <div class="meta">No enrollments yet.</div>
        <?php else: ?>
          <?php foreach ($myCourses as $c): ?>
            <div class="item">
              <div style="font-weight:700;"><?= e($c['title'] ?? '') ?></div>
              <div class="meta">
                Progress: <?= e($c['progress'] ?? 0) ?> • Status: <?= e($c['status'] ?? '') ?>
                <?php if (!empty($c['organization_name'])): ?>
                  • Org: <?= e($c['organization_name']) ?>
                <?php endif; ?>
              </div>
            </div>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>

      <div class="card">
        <h2>My Certifications</h2>
        <?php if (!$myCerts): ?>
          <div class="meta">No certifications yet.</div>
        <?php else: ?>
          <?php foreach ($myCerts as $ct): ?>
            <div class="item">
              <div style="font-weight:700;"><?= e($ct['title'] ?: $ct['course_title'] ?? '') ?></div>
              <div class="meta">Issued: <?= e($ct['issued_at'] ?? '') ?></div>
            </div>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>

      <div class="card">
        <h2>Credits & Redeem</h2>
        <div style="font-size: 2rem; font-weight: 800; color:#f2c94c;">₹ <?= e($totalCredits) ?></div>
        <div class="meta">Redeem feature UI can be connected to credits later.</div>
        <div style="margin-top: 0.9rem;">
          <?php if (!$creditRows): ?>
            <div class="meta">No credit transactions yet.</div>
          <?php else: ?>
            <?php foreach (array_slice($creditRows, 0, 6) as $cr): ?>
              <div class="item">
                <div style="font-weight:700;">+<?= e($cr['amount'] ?? 0) ?> Credits</div>
                <div class="meta">Reason: <?= e($cr['reason'] ?? '—') ?> • <?= e($cr['created_at'] ?? '') ?></div>
              </div>
            <?php endforeach; ?>
          <?php endif; ?>
        </div>
      </div>

      <div class="card" style="grid-column: 1 / -1;">
        <h2>Upcoming / Assignments</h2>
        <div class="meta">Using assignments tables (if you add items in DB, they will appear here).</div>
        <div class="item" style="border-bottom:0;">
          <div class="meta">This section is prepared; update DB assignments for real data.</div>
        </div>
      </div>
    </div>
  </div>
</body>
</html>

