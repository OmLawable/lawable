<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/functions.php';
start_secure_session();

// Only logged-in students can enroll
$user = require_login('user');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    json_response(['success' => false, 'message' => 'Method not allowed.'], 405);
}

$course_id = (int) ($_POST['course_id'] ?? 0);
if ($course_id < 1) {
    json_response(['success' => false, 'message' => 'Invalid course ID.'], 400);
}

$student_id = (int) $user['id'];
$pdo = get_pdo();

// Check course exists and is published
$stmt = $pdo->prepare("SELECT id FROM courses WHERE id = :cid AND status = 'published' LIMIT 1");
$stmt->execute([':cid' => $course_id]);
if (!$stmt->fetch()) {
    json_response(['success' => false, 'message' => 'Course not found.'], 404);
}

// Check not already enrolled
$stmt = $pdo->prepare("SELECT id FROM course_enrollments WHERE student_id = :sid AND course_id = :cid LIMIT 1");
$stmt->execute([':sid' => $student_id, ':cid' => $course_id]);
if ($stmt->fetch()) {
    json_response(['success' => false, 'message' => 'Already enrolled in this course.'], 409);
}

// Insert enrollment
$stmt = $pdo->prepare("INSERT INTO course_enrollments (course_id, student_id) VALUES (:cid, :sid)");
$stmt->execute([':cid' => $course_id, ':sid' => $student_id]);

// Get course lesson count for progress tracking
$stmt = $pdo->prepare("SELECT COUNT(*) FROM course_lessons WHERE course_id = :cid");
$stmt->execute([':cid' => $course_id]);
$lesson_count = (int) $stmt->fetchColumn();

// Create progress record
$stmt = $pdo->prepare("
    INSERT INTO course_progress (student_id, course_id, progress_percentage, completed_lessons, total_lessons)
    VALUES (:sid, :cid, 0.00, 0, :lessons)
    ON DUPLICATE KEY UPDATE total_lessons = VALUES(total_lessons)
");
$stmt->execute([':sid' => $student_id, ':cid' => $course_id, ':lessons' => $lesson_count]);

json_response(['success' => true, 'message' => 'Enrolled successfully!']);
