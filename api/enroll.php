<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/firestore.php';
start_secure_session();

// Only logged-in students can enroll
$user = require_login('user');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['success' => false, 'message' => 'Method not allowed.'], 405);
}

$course_id = trim((string) ($_POST['course_id'] ?? ''));
if ($course_id === '') {
    json_response(['success' => false, 'message' => 'Invalid course ID.'], 400);
}

$student_id = (string) $user['id'];
$db = get_firestore();

// Check course exists and is published
$course = $db->get('courses', $course_id);
if (!$course || ($course['status'] ?? '') !== 'published') {
    json_response(['success' => false, 'message' => 'Course not found.'], 404);
}

// Check not already enrolled (Compound ID prevents duplicates)
$enrollmentId = $student_id . '_' . $course_id;
$existingEnrollment = $db->get('enrollments', $enrollmentId);
if ($existingEnrollment !== null) {
    json_response(['success' => false, 'message' => 'Already enrolled in this course.'], 409);
}

$now = FirestoreClient::now();

// Insert enrollment
$enrollmentDoc = [
    'studentId'      => $student_id,
    'courseId'       => $course_id,
    'courseName'     => $course['title'] ?? '',
    'organizationId' => $course['organizationId'] ?? '',
    'teacherId'      => $course['teacherId'] ?? '',
    'enrolledAt'     => $now,
    'completedAt'    => null
];
$db->set('enrollments', $enrollmentDoc, $enrollmentId);

// Update enrollment count on course document
$current_count = (int) ($course['enrollment_count'] ?? 0);
$db->update('courses', $course_id, ['enrollment_count' => $current_count + 1]);

// Get course lesson count for progress tracking (embedded lessons array)
$lesson_count = count($course['lessons'] ?? []);

// Create progress record
$progressDoc = [
    'studentId'          => $student_id,
    'courseId'           => $course_id,
    'progressPercentage' => 0.00,
    'completedLessons'   => 0,
    'totalLessons'       => $lesson_count,
    'lastAccessedAt'     => $now
];
$db->set('progress', $progressDoc, $enrollmentId);

json_response(['success' => true, 'message' => 'Enrolled successfully!']);
