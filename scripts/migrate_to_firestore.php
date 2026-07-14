<?php

declare(strict_types=1);

/**
 * scripts/migrate_to_firestore.php
 *
 * Migration script to copy all structured data from MySQL to Cloud Firestore.
 * Maps MySQL auto-increment IDs to Firestore string IDs (using firebase_uid where possible).
 *
 * Run from terminal:
 *   php scripts/migrate_to_firestore.php
 */

require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/firestore.php';

// Prevent execution via web browser for security
if (php_sapi_name() !== 'cli') {
    die("This script can only be run from the command line.\n");
}

echo "=== Starting MySQL to Firestore Migration ===\n\n";

$pdo = get_pdo();
$db = get_firestore();

// ── 1. Fetch Users & Build ID Mappings ───────────────────────────────────

echo "Step 1: Reading users and building ID mapping...\n";

// Mappings from MySQL ID -> Firebase UID
$studentMap = [];
$orgMap = [];
$adminMap = [];

// A list of all writes to execute
$writes = [];

// Load Students
$students = $pdo->query("
    SELECT s.*, p.city, p.bio, p.date_of_birth, p.institution, p.course, 
           p.year_semester, p.areas_of_interest, p.resume_file, p.linkedin_url, p.skills
    FROM students s
    LEFT JOIN student_profiles p ON s.id = p.student_id
")->fetchAll();

foreach ($students as $s) {
    $uid = $s['firebase_uid'] ?? '';
    if (empty($uid)) {
        // Fallback for legacy students without firebase_uid
        $uid = 'legacy_student_' . $s['id'];
    }
    $studentMap[(int)$s['id']] = $uid;

    $doc = [
        'name' => $s['name'],
        'username' => $s['username'],
        'email' => $s['email'],
        'phone' => $s['phone'] ?? '',
        'status' => $s['status'],
        'city' => $s['city'] ?? '',
        'bio' => $s['bio'] ?? '',
        'dateOfBirth' => $s['date_of_birth'] ?? '',
        'institution' => $s['institution'] ?? '',
        'course' => $s['course'] ?? '',
        'yearSemester' => $s['year_semester'] ?? '',
        'areasOfInterest' => $s['areas_of_interest'] ?? '',
        'resumeFile' => $s['resume_file'] ?? '',
        'linkedinUrl' => $s['linkedin_url'] ?? '',
        'skills' => $s['skills'] ?? '',
        'createdAt' => $s['created_at'],
        'updatedAt' => $s['updated_at']
    ];
    $writes[] = ['students', $uid, $doc];
}
echo "Found " . count($students) . " students.\n";

// Load Organizations
$orgs = $pdo->query("
    SELECT o.*, p.display_name, p.official_email, p.organization_type, 
           p.tagline, p.about_description, p.year_established, p.website_url
    FROM organizations o
    LEFT JOIN organization_profiles p ON o.id = p.organization_id
")->fetchAll();

foreach ($orgs as $o) {
    $uid = $o['firebase_uid'] ?? '';
    if (empty($uid)) {
        $uid = 'legacy_org_' . $o['id'];
    }
    $orgMap[(int)$o['id']] = $uid;

    $doc = [
        'organizationName' => $o['organization_name'],
        'contactPerson' => $o['contact_person'],
        'username' => $o['username'],
        'email' => $o['email'],
        'phone' => $o['phone'] ?? '',
        'status' => $o['status'],
        'displayName' => $o['display_name'] ?? '',
        'officialEmail' => $o['official_email'] ?? '',
        'organizationType' => $o['organization_type'] ?? '',
        'tagline' => $o['tagline'] ?? '',
        'aboutDescription' => $o['about_description'] ?? '',
        'yearEstablished' => $o['year_established'] !== null ? (int)$o['year_established'] : null,
        'websiteUrl' => $o['website_url'] ?? '',
        'createdAt' => $o['created_at'],
        'updatedAt' => $o['updated_at']
    ];
    $writes[] = ['organizations', $uid, $doc];
}
echo "Found " . count($orgs) . " organizations.\n";

// Load Admins
$admins = $pdo->query("SELECT * FROM admins")->fetchAll();
foreach ($admins as $a) {
    $uid = $a['firebase_uid'] ?? '';
    if (empty($uid)) {
        $uid = 'legacy_admin_' . $a['id'];
    }
    $adminMap[(int)$a['id']] = $uid;

    $doc = [
        'name' => $a['name'],
        'username' => $a['username'],
        'email' => $a['email'],
        'status' => $a['status'],
        'createdAt' => $a['created_at'],
        'updatedAt' => $a['updated_at']
    ];
    $writes[] = ['admins', $uid, $doc];
}
echo "Found " . count($admins) . " admins.\n";


// Helper to find a user UID by MySQL user_id and user_type
$getUserUid = function(?int $userId, string $userType) use ($studentMap, $orgMap, $adminMap): string {
    if ($userId === null) return '';
    return match($userType) {
        'student', 'user' => $studentMap[$userId] ?? 'legacy_student_' . $userId,
        'organization' => $orgMap[$userId] ?? 'legacy_org_' . $userId,
        'admin' => $adminMap[$userId] ?? 'legacy_admin_' . $userId,
        default => 'system'
    };
};


// ── 2. Migrate Courses & Lessons ─────────────────────────────────────────

echo "\nStep 2: Migrating courses and lessons...\n";

// Load all lessons grouped by course_id
$lessonsRaw = $pdo->query("SELECT * FROM course_lessons ORDER BY course_id, sort_order ASC")->fetchAll();
$lessonsMap = [];
foreach ($lessonsRaw as $lesson) {
    $cId = (int)$lesson['course_id'];
    if (!isset($lessonsMap[$cId])) {
        $lessonsMap[$cId] = [];
    }
    $lessonsMap[$cId][] = [
        'title' => $lesson['title'],
        'durationMinutes' => (int)$lesson['duration_minutes'],
        'sortOrder' => (int)$lesson['sort_order']
    ];
}

$courses = $pdo->query("SELECT * FROM courses")->fetchAll();
$courseDocIds = []; // mapping from MySQL course_id -> Firestore document ID

foreach ($courses as $c) {
    $cId = (int)$c['id'];
    $firestoreCourseId = 'course_' . $cId;
    $courseDocIds[$cId] = $firestoreCourseId;

    // Get Organization Name from mapping
    $orgUid = $orgMap[(int)$c['organization_id']] ?? '';
    $orgName = '';
    if (!empty($orgUid)) {
        // Find org details from the organizations table list we just read
        foreach ($orgs as $o) {
            if (($o['firebase_uid'] ?? 'legacy_org_' . $o['id']) === $orgUid) {
                $orgName = $o['organization_name'];
                break;
            }
        }
    }

    $doc = [
        'title' => $c['title'],
        'description' => $c['description'] ?? '',
        'organizationId' => $orgUid,
        'organizationName' => $orgName,
        'price' => (float)$c['price'],
        'category' => $c['category'] ?? '',
        'difficulty' => $c['difficulty'],
        'rating' => (float)($c['rating'] ?? 4.5),
        'status' => $c['status'],
        'lessons' => $lessonsMap[$cId] ?? [],
        'createdAt' => $c['created_at'],
        'updatedAt' => $c['updated_at']
    ];
    $writes[] = ['courses', $firestoreCourseId, $doc];
}
echo "Prepared " . count($courses) . " courses.\n";


// ── 3. Migrate Enrollments, Progress & Certificates ──────────────────────

echo "\nStep 3: Migrating enrollments, progress & certificates...\n";

// Enrollments
$enrollments = $pdo->query("SELECT * FROM course_enrollments")->fetchAll();
foreach ($enrollments as $e) {
    $studentUid = $studentMap[(int)$e['student_id']] ?? '';
    $courseId = $courseDocIds[(int)$e['course_id']] ?? '';
    if (empty($studentUid) || empty($courseId)) continue;

    $docId = $studentUid . '_' . $courseId;

    // Find course title
    $courseTitle = '';
    foreach ($courses as $c) {
        if ('course_' . $c['id'] === $courseId) {
            $courseTitle = $c['title'];
            break;
        }
    }

    $doc = [
        'studentId' => $studentUid,
        'courseId' => $courseId,
        'courseName' => $courseTitle,
        'organizationId' => $orgMap[(int)$e['course_id']] ?? '', // Org ID associated with course
        'enrolledAt' => $e['enrolled_at'],
        'completedAt' => $e['completed_at']
    ];
    $writes[] = ['enrollments', $docId, $doc];
}
echo "Prepared " . count($enrollments) . " enrollments.\n";

// Progress
$progress = $pdo->query("SELECT * FROM course_progress")->fetchAll();
foreach ($progress as $p) {
    $studentUid = $studentMap[(int)$p['student_id']] ?? '';
    $courseId = $courseDocIds[(int)$p['course_id']] ?? '';
    if (empty($studentUid) || empty($courseId)) continue;

    $docId = $studentUid . '_' . $courseId;
    $doc = [
        'studentId' => $studentUid,
        'courseId' => $courseId,
        'progressPercentage' => (float)$p['progress_percentage'],
        'completedLessons' => (int)$p['completed_lessons'],
        'totalLessons' => (int)$p['total_lessons'],
        'lastAccessedAt' => $p['last_accessed_at']
    ];
    $writes[] = ['progress', $docId, $doc];
}
echo "Prepared " . count($progress) . " progress records.\n";

// Certificates
$certificates = $pdo->query("SELECT * FROM certificates")->fetchAll();
foreach ($certificates as $cert) {
    $studentUid = $studentMap[(int)$cert['student_id']] ?? '';
    $courseId = $courseDocIds[(int)$cert['course_id']] ?? '';
    if (empty($studentUid) || empty($courseId)) continue;

    $studentName = '';
    foreach ($students as $s) {
        if (($s['firebase_uid'] ?? 'legacy_student_' . $s['id']) === $studentUid) {
            $studentName = $s['name'];
            break;
        }
    }

    $courseTitle = '';
    foreach ($courses as $c) {
        if ('course_' . $c['id'] === $courseId) {
            $courseTitle = $c['title'];
            break;
        }
    }

    $doc = [
        'studentId' => $studentUid,
        'courseId' => $courseId,
        'studentName' => $studentName,
        'courseName' => $courseTitle,
        'issuedAt' => $cert['issued_at']
    ];
    $writes[] = ['certificates', $cert['certificate_number'], $doc];
}
echo "Prepared " . count($certificates) . " certificates.\n";


// ── 4. Migrate Platform Collections ──────────────────────────────────────

echo "\nStep 4: Migrating platform collections...\n";

// Announcements
$announcements = $pdo->query("SELECT * FROM announcements")->fetchAll();
foreach ($announcements as $ann) {
    $adminUid = $adminMap[(int)$ann['created_by']] ?? '';
    $adminName = '';
    foreach ($admins as $a) {
        if (($a['firebase_uid'] ?? 'legacy_admin_' . $a['id']) === $adminUid) {
            $adminName = $a['name'];
            break;
        }
    }

    $doc = [
        'title' => $ann['title'],
        'content' => $ann['content'],
        'createdBy' => $adminUid,
        'createdByName' => $adminName,
        'status' => $ann['status'],
        'createdAt' => $ann['created_at'],
        'updatedAt' => $ann['updated_at']
    ];
    $writes[] = ['announcements', 'announcement_' . $ann['id'], $doc];
}
echo "Prepared " . count($announcements) . " announcements.\n";

// Verification Documents
$verifs = $pdo->query("SELECT * FROM verification_documents")->fetchAll();
foreach ($verifs as $v) {
    $orgUid = $orgMap[(int)$v['organization_id']] ?? '';
    if (empty($orgUid)) continue;

    $doc = [
        'organizationId' => $orgUid,
        'documentType' => $v['document_type'],
        'filePath' => $v['file_path'] ?? '',
        'status' => $v['status'],
        'adminNotes' => $v['admin_notes'] ?? '',
        'submittedAt' => $v['submitted_at'],
        'reviewedBy' => $adminMap[(int)$v['reviewed_by']] ?? '',
        'reviewedAt' => $v['reviewed_at']
    ];
    $writes[] = ['verificationRequests', $orgUid, $doc];
}
echo "Prepared " . count($verifs) . " verification requests.\n";

// Support Tickets
$tickets = $pdo->query("SELECT * FROM support_tickets")->fetchAll();
foreach ($tickets as $t) {
    $userUid = $getUserUid((int)$t['user_id'], $t['user_type']);
    $doc = [
        'title' => $t['title'],
        'message' => $t['message'],
        'userType' => $t['user_type'],
        'userId' => $userUid,
        'priority' => $t['priority'],
        'status' => $t['status'],
        'assignedTo' => $adminMap[(int)$t['assigned_to']] ?? '',
        'createdAt' => $t['created_at'],
        'updatedAt' => $t['updated_at']
    ];
    $writes[] = ['supportTickets', 'ticket_' . $t['id'], $doc];
}
echo "Prepared " . count($tickets) . " support tickets.\n";

// Content Reports
$reports = $pdo->query("SELECT * FROM content_reports")->fetchAll();
foreach ($reports as $r) {
    $reportedByUid = $getUserUid((int)$r['reported_by_id'], $r['reported_by_type']);
    $targetId = $r['target_id'] !== null ? (int)$r['target_id'] : null;

    // If target is a course, map target_id to course document ID
    $targetDocId = ($r['target_type'] === 'course' && $targetId !== null) 
        ? ($courseDocIds[$targetId] ?? 'course_' . $targetId) 
        : (string)$targetId;

    $doc = [
        'reportedByType' => $r['reported_by_type'],
        'reportedById' => $reportedByUid,
        'targetType' => $r['target_type'],
        'targetId' => $targetDocId,
        'reason' => $r['reason'],
        'status' => $r['status'],
        'reviewedBy' => $adminMap[(int)$r['reviewed_by']] ?? '',
        'createdAt' => $r['created_at'],
        'resolvedAt' => $r['resolved_at']
    ];
    $writes[] = ['contentReports', 'report_' . $r['id'], $doc];
}
echo "Prepared " . count($reports) . " content reports.\n";

// Activity Logs (Cap to 30 days)
$cutoff = date('Y-m-d H:i:s', strtotime('-30 days'));
$stmt = $pdo->prepare("SELECT * FROM activity_logs WHERE created_at >= :cutoff");
$stmt->execute([':cutoff' => $cutoff]);
$logs = $stmt->fetchAll();

foreach ($logs as $l) {
    $userUid = $getUserUid((int)$l['user_id'], $l['user_type']);
    $doc = [
        'action' => $l['action'],
        'description' => $l['description'],
        'userType' => $l['user_type'],
        'userId' => $userUid,
        'priority' => $l['priority'],
        'createdAt' => $l['created_at']
    ];
    $writes[] = ['activityLogs', 'log_' . $l['id'], $doc];
}
echo "Prepared " . count($logs) . " activity logs (last 30 days).\n";


// ── 5. Run Batch Writes to Firestore ─────────────────────────────────────

echo "\nStep 5: Executing batch writes to Firestore...\n";
$total = count($writes);
echo "Total documents to write: $total\n";

try {
    $db->batchSet($writes);
    echo "\n=== Migration Completed Successfully! ===\n";
    echo "Wrote $total documents to Firestore.\n";
} catch (Throwable $e) {
    echo "\n❌ Migration Failed: " . $e->getMessage() . "\n";
    exit(1);
}
