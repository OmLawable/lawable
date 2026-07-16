<?php

declare(strict_types=1);

/**
 * api/organization/create-course-api.php — Create a new course as an Organization.
 *
 * Accepts: POST (JSON body or form POST)
 * Returns: JSON { success, message, courseId }
 */

require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/firestore.php';

start_secure_session();

// ── Only accept POST ──────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['success' => false, 'message' => 'Method not allowed.'], 405);
}

// ── Authentication Check ──────────────────────────────────────────────────
if (!is_logged_in()) {
    json_response(['success' => false, 'message' => 'Please log in to continue.'], 401);
}

$user = current_user();
$is_org = ($user['role'] ?? '') === 'organization';
$is_teacher = ($user['role'] ?? '') === 'teacher';
if (!$is_org && !$is_teacher) {
    json_response(['success' => false, 'message' => 'Unauthorized. Only organizations or teachers can create courses.'], 403);
}

// ── Read Input ────────────────────────────────────────────────────────────
// Try reading JSON payload first, fallback to form POST
$body = (string) file_get_contents('php://input');
$payload = json_decode($body, true);

if (!is_array($payload)) {
    $payload = $_POST;
}

try {
    $title       = trim((string) ($payload['title'] ?? ''));
    $category    = trim((string) ($payload['category'] ?? ''));
    $difficulty  = trim((string) ($payload['difficulty'] ?? ''));
    $priceInput  = trim((string) ($payload['price'] ?? '0'));
    $description = trim((string) ($payload['description'] ?? ''));
    $imageUrl    = trim((string) ($payload['imageUrl'] ?? ''));
    $status      = trim((string) ($payload['status'] ?? 'draft'));

    // Validation
    if ($title === '') {
        throw new RuntimeException('Course title is required.');
    }
    if ($category === '') {
        throw new RuntimeException('Course category is required.');
    }
    if ($difficulty === '') {
        throw new RuntimeException('Course difficulty level is required.');
    }
    if ($description === '') {
        throw new RuntimeException('Course description is required.');
    }
    if (!in_array($status, ['draft', 'published'], true)) {
        throw new RuntimeException('Invalid course status.');
    }

    $price = (float) $priceInput;
    if ($price < 0.0) {
        throw new RuntimeException('Price cannot be negative.');
    }

    // Create course record
    $courseId = 'course_' . bin2hex(random_bytes(6));
    $now = date('c');

    $courseData = [
        'title'            => $title,
        'category'         => $category,
        'price'            => $price,
        'difficulty'       => $difficulty,
        'description'      => $description,
        'imageUrl'         => $imageUrl,
        'status'           => $status,
        'organizationId'   => $is_org ? $user['id'] : '',
        'organizationName' => $is_org ? ($user['organization_name'] ?? $user['name'] ?? 'Platform') : '',
        'teacherId'        => $is_teacher ? $user['id'] : '',
        'teacherName'      => $is_teacher ? ($user['name'] ?? 'Instructor') : '',
        'createdAt'        => $now,
        'updatedAt'        => $now,
        'enrollment_count' => 0,
        'rating'           => 0.0,
        'totalLessons'     => 0,
    ];

    $db = get_firestore();
    $db->set('courses', $courseData, $courseId);

    json_response([
        'success' => true,
        'message' => 'Course created successfully.',
        'courseId' => $courseId
    ]);

} catch (Throwable $e) {
    json_response(['success' => false, 'message' => $e->getMessage()], 400);
}
