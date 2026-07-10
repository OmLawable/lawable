<?php

declare(strict_types=1);

require_once __DIR__ . '/../backend/includes/functions.php';
require_once __DIR__ . '/../backend/includes/db.php';

start_secure_session();
$user = require_login('admin');

$pdo = get_pdo();

$studentsStmt = $pdo->prepare(
    "SELECT s.id, s.name, s.username, s.email, s.phone, s.status,
            sp.city, sp.institution, sp.course, sp.year_semester
     FROM students s
     LEFT JOIN student_profiles sp ON sp.student_id = s.id
     ORDER BY s.created_at DESC"
);
$studentsStmt->execute();
$students = $studentsStmt->fetchAll();

$flash = get_flash();
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Teacher — Students — Lawable</title>
  <link rel="stylesheet" href="../assets/css/lawable.css" />
  <style>
    body { margin:0; font-family: Inter, sans-serif; background:#07111f; color:#f7f7f2; }
    .wrap { max-width: 1100px; margin: 2rem auto; padding: 0 1.2rem; }
    .topbar { display:flex; justify-content:space-between; gap:1rem; align-items:flex-start; margin-bottom:1rem; }
    h1 { margin:0; }
    .meta { color: rgba(247,247,242,0.75); font-size: 0.95rem; }
    .flash { padding:0.8rem 1rem; border-radius:12px; background:#14532d; border:1px solid rgba(255,255,255,0.12); margin-top: 1rem; }

    .card { background: rgba(255,255,255,0.08); border: 1px solid rgba(255,255,255,0.10); border-radius: 18px; padding: 1rem; }
    table { width:100%; border-collapse: collapse; margin-top: 0.8rem; }
    th, td { padding: 0.65rem 0.6rem; border-bottom: 1px dashed rgba(255,255,255,0.12); text-align:left; vertical-align: top; }
    th { color: #f2c94c; font-weight:700; font-size: 0.9rem; }
    td { font-size: 0.92rem; color: rgba(247,247,242,0.9); }
    tr:last-child td { border-bottom:0; }

    .btn { display:inline-block; padding:0.6rem 0.9rem; border-radius:999px; background:#f2c94c; color:#07111f; font-weight:700; text-decoration:none; }
    .btn-ghost { background: transparent; border:1px solid rgba(242,201,76,0.6); color:#f2c94c; }

    @media (max-width: 900px) {
      th, td { font-size: 0.85rem; }
    }
  </style>
</head>
<body>
  <div class="wrap">
    <div class="topbar">
      <div>
        <h1>Teacher Dashboard</h1>
        <div class="meta">Logged in as <?= e($user['name'] ?? 'Admin') ?> • <?= e($user['email'] ?? '') ?></div>
        <?php if ($flash): ?>
          <div class="flash"><?= e($flash['message']) ?></div>
        <?php endif; ?>
      </div>
      <a class="btn btn-ghost" href="../backend/logout.php">Logout</a>
    </div>

    <div class="card">
      <h2 style="margin:0 0 0.3rem; color:#f2c94c;">Students</h2>
      <div class="meta">Total: <?= count($students) ?></div>

      <table>
        <thead>
          <tr>
            <th>ID</th>
            <th>Name</th>
            <th>Username</th>
            <th>Email</th>
            <th>Phone</th>
            <th>Status</th>
            <th>City</th>
            <th>Institution</th>
            <th>Course</th>
            <th>Year/Semester</th>
          </tr>
        </thead>
        <tbody>
          <?php if (!$students): ?>
            <tr><td colspan="10" class="meta">No students found.</td></tr>
          <?php else: ?>
            <?php foreach ($students as $s): ?>
              <tr>
                <td><?= e($s['id'] ?? '') ?></td>
                <td><?= e($s['name'] ?? '') ?></td>
                <td><?= e($s['username'] ?? '') ?></td>
                <td><?= e($s['email'] ?? '') ?></td>
                <td><?= e($s['phone'] ?? '') ?></td>
                <td><?= e($s['status'] ?? '') ?></td>
                <td><?= e($s['city'] ?? '') ?></td>
                <td><?= e($s['institution'] ?? '') ?></td>
                <td><?= e($s['course'] ?? '') ?></td>
                <td><?= e($s['year_semester'] ?? '') ?></td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</body>
</html>

