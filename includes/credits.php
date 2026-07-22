<?php
declare(strict_types=1);

require_once __DIR__ . '/firestore.php';

/**
 * Check and award 50 daily login credits once per day per student.
 */
function check_and_award_daily_login_credit(string $studentId): array
{
    if (empty($studentId)) return ['awarded' => false, 'reason' => 'Invalid student ID'];

    $db = get_firestore();
    $student = $db->get('students', $studentId);
    if (!$student) return ['awarded' => false, 'reason' => 'Student record not found'];

    $todayStr = date('Y-m-d');
    $lastAwardDate = $student['lastLoginCreditDate'] ?? '';

    if ($lastAwardDate === $todayStr) {
        return ['awarded' => false, 'reason' => 'Already awarded today', 'credits' => (int)($student['credits'] ?? 0)];
    }

    $amount = 50;
    $currentCredits = (int) ($student['credits'] ?? 0);
    $newTotal = $currentCredits + $amount;

    $txId = 'tx_login_' . $todayStr . '_' . substr(md5($studentId), 0, 8);
    $txDoc = [
        'studentId'   => $studentId,
        'type'        => 'daily_login',
        'credits'     => $amount,
        'description' => 'Daily Login Bonus 🎁',
        'createdAt'   => date('c'),
    ];

    try {
        $db->set('credit_transactions', $txDoc, $txId);
        $db->update('students', $studentId, [
            'credits'             => $newTotal,
            'lastLoginCreditDate' => $todayStr,
        ]);
        return ['awarded' => true, 'credits' => $amount, 'newTotal' => $newTotal];
    } catch (\Throwable $e) {
        return ['awarded' => false, 'reason' => $e->getMessage()];
    }
}

/**
 * Award credits upon course completion based on difficulty:
 * Beginner -> 200 credits, Intermediate -> 250 credits, Advanced -> 300 credits.
 */
function check_and_award_course_completion_credit(string $studentId, string $courseId): array
{
    if (empty($studentId) || empty($courseId)) return ['awarded' => false, 'reason' => 'Missing ID'];

    $db = get_firestore();
    $student = $db->get('students', $studentId);
    $course  = $db->get('courses', $courseId);

    if (!$student || !$course) return ['awarded' => false, 'reason' => 'Data not found'];

    $diff = strtolower($course['difficulty'] ?? 'beginner');
    $amount = match (true) {
        str_contains($diff, 'advanced')     => 300,
        str_contains($diff, 'intermediate') => 250,
        default                             => 200,
    };

    $txId = 'tx_course_' . $courseId . '_' . substr(md5($studentId), 0, 8);
    $existing = $db->get('credit_transactions', $txId);
    if ($existing) {
        return ['awarded' => false, 'reason' => 'Already awarded for this course'];
    }

    $currentCredits = (int) ($student['credits'] ?? 0);
    $newTotal = $currentCredits + $amount;

    $txDoc = [
        'studentId'   => $studentId,
        'type'        => 'course_completion',
        'courseId'    => $courseId,
        'courseTitle' => $course['title'] ?? 'Course',
        'credits'     => $amount,
        'description' => 'Completed Course: ' . ($course['title'] ?? 'Course') . ' (' . ucfirst($diff) . ') 🎓',
        'createdAt'   => date('c'),
    ];

    try {
        $db->set('credit_transactions', $txDoc, $txId);
        $db->update('students', $studentId, ['credits' => $newTotal]);
        return ['awarded' => true, 'credits' => $amount, 'newTotal' => $newTotal];
    } catch (\Throwable $e) {
        return ['awarded' => false, 'reason' => $e->getMessage()];
    }
}

/**
 * Fetch transaction history for student.
 */
function get_student_credit_history(string $studentId, int $limit = 50): array
{
    if (empty($studentId)) return [];
    $db = get_firestore();
    $txs = $db->query('credit_transactions', [['studentId', 'EQUAL', $studentId]], $limit);
    usort($txs, function($a, $b) {
        return strcmp($b['createdAt'] ?? '', $a['createdAt'] ?? '');
    });
    return $txs;
}

/**
 * Unlock a locked course using student credits (default 500 credits).
 */
function unlock_course_with_credits(string $studentId, string $courseId, int $cost = 500): array
{
    if (empty($studentId) || empty($courseId)) {
        return ['success' => false, 'message' => 'Missing student or course ID.'];
    }

    $db = get_firestore();
    $student = $db->get('students', $studentId);
    $course  = $db->get('courses', $courseId);

    if (!$student || !$course) {
        return ['success' => false, 'message' => 'Student or course record not found.'];
    }

    $unlocked = $student['unlockedCourses'] ?? [];
    if (!is_array($unlocked)) {
        $unlocked = [];
    }

    if (in_array($courseId, $unlocked, true)) {
        return ['success' => true, 'message' => 'Course is already unlocked.', 'alreadyUnlocked' => true];
    }

    $currentCredits = (int) ($student['credits'] ?? 0);
    if ($currentCredits < $cost) {
        return [
            'success' => false,
            'message' => "Insufficient credits! You need {$cost} credits to unlock this course (Current balance: {$currentCredits} credits).",
            'required' => $cost,
            'current' => $currentCredits,
        ];
    }

    $newTotal = $currentCredits - $cost;
    $unlocked[] = $courseId;

    $txId = 'tx_unlock_' . $courseId . '_' . substr(md5($studentId . '_' . time()), 0, 8);
    $txDoc = [
        'studentId'   => $studentId,
        'type'        => 'course_unlock',
        'courseId'    => $courseId,
        'courseTitle' => $course['title'] ?? 'Course',
        'credits'     => -$cost,
        'description' => 'Unlocked Course: ' . ($course['title'] ?? 'Course') . ' 🔓',
        'createdAt'   => date('c'),
    ];

    try {
        $db->set('credit_transactions', $txDoc, $txId);
        $db->update('students', $studentId, [
            'credits'         => $newTotal,
            'unlockedCourses' => array_values(array_unique($unlocked)),
        ]);

        // Auto-enroll student into the unlocked course
        $enrollmentId = $studentId . '_' . $courseId;
        $existingEnrollment = $db->get('enrollments', $enrollmentId);
        if ($existingEnrollment === null) {
            $now = FirestoreClient::now();
            $enrollmentDoc = [
                'studentId'      => $studentId,
                'courseId'       => $courseId,
                'courseName'     => $course['title'] ?? '',
                'organizationId' => $course['organizationId'] ?? '',
                'teacherId'      => $course['teacherId'] ?? '',
                'enrolledAt'     => $now,
                'completedAt'    => null
            ];
            $db->set('enrollments', $enrollmentDoc, $enrollmentId);

            $lessonCount = count($course['lessons'] ?? []);
            $progressDoc = [
                'studentId'          => $studentId,
                'courseId'           => $courseId,
                'progressPercentage' => 0.00,
                'completedLessons'   => 0,
                'totalLessons'       => $lessonCount,
                'lastAccessedAt'     => $now
            ];
            $db->set('progress', $progressDoc, $enrollmentId);
        }

        return [
            'success'  => true,
            'message'  => '🎉 Course unlocked & enrolled successfully! You can now start learning.',
            'newTotal' => $newTotal
        ];
    } catch (\Throwable $e) {
        return ['success' => false, 'message' => 'Unlock failed: ' . $e->getMessage()];
    }
}
