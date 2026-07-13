<?php
/**
 * Seed demo data for admin dashboard.
 * Run: php backend/seed_dashboard.php
 */
require_once __DIR__ . '/includes/db.php';

$pdo = get_pdo();

// Check what orgs we have
$orgs = $pdo->query("SELECT id FROM organizations")->fetchAll(PDO::FETCH_COLUMN);
echo "Organizations found: " . count($orgs) . "\n";

if (count($orgs) === 0) {
    echo "No organizations exist. Skipping verification_documents seeding.\n";
} else {
    foreach ($orgs as $oid) {
        $exists = $pdo->prepare("SELECT COUNT(*) FROM verification_documents WHERE organization_id = ?");
        $exists->execute([$oid]);
        if ($exists->fetchColumn() == 0) {
            $status = $oid == 1 ? 'verified' : ($oid == 2 ? 'under_review' : 'pending');
            $pdo->prepare("INSERT INTO verification_documents (organization_id, document_type, status, submitted_at) VALUES (?, 'registration', ?, NOW() - INTERVAL ? DAY)")
               ->execute([$oid, $status, rand(1, 60)]);
            echo "  Added verification for org #{$oid}: {$status}\n";
        }
    }
}

// Students check
$students = $pdo->query("SELECT id FROM students LIMIT 5")->fetchAll(PDO::FETCH_COLUMN);
echo "Students found: " . count($students) . "\n";

if (count($students) > 0) {
    // Enrollments - check if any exist
    $ecnt = $pdo->query("SELECT COUNT(*) FROM course_enrollments")->fetchColumn();
    if ($ecnt == 0) {
        $courses = $pdo->query("SELECT id FROM courses LIMIT 8")->fetchAll(PDO::FETCH_COLUMN);
        foreach ($students as $si) {
            foreach ($courses as $ci) {
                $exists = $pdo->prepare("SELECT COUNT(*) FROM course_enrollments WHERE course_id = ? AND student_id = ?");
                $exists->execute([$ci, $si]);
                if ($exists->fetchColumn() == 0 && rand(1, 3) == 1) {
                    $days = rand(1, 30);
                    $pdo->prepare("INSERT INTO course_enrollments (course_id, student_id, enrolled_at) VALUES (?, ?, NOW() - INTERVAL ? DAY)")
                       ->execute([$ci, $si, $days]);
                }
            }
        }
        echo "  Course enrollments seeded.\n";
    } else {
        echo "  Enrollments already exist: {$ecnt}\n";
    }
}

// Content reports
$rcnt = $pdo->query("SELECT COUNT(*) FROM content_reports")->fetchColumn();
if ($rcnt == 0) {
    $pdo->exec("INSERT INTO content_reports (reported_by_type, reported_by_id, target_type, target_id, reason, status, created_at) VALUES
        ('student', 1, 'course', 2, 'Contains outdated legal references that may mislead students.', 'open', NOW() - INTERVAL 1 DAY),
        ('student', 3, 'course', 3, 'Inaccurate citation of Supreme Court judgments.', 'open', NOW() - INTERVAL 6 HOUR)");
    echo "  Content reports seeded.\n";
}

// Support tickets
$tcnt = $pdo->query("SELECT COUNT(*) FROM support_tickets")->fetchColumn();
if ($tcnt == 0) {
    $pdo->exec("INSERT INTO support_tickets (title, message, user_type, user_id, priority, status, created_at) VALUES
        ('Login issue after password reset', 'Cannot log in after resetting password.', 'student', 1, 'high', 'open', NOW() - INTERVAL 2 DAY),
        ('Organization profile not updating', 'Changes not reflecting on profile.', 'organization', 2, 'medium', 'in_progress', NOW() - INTERVAL 1 DAY),
        ('Payment gateway error', 'Error 502 when enrolling in paid course.', 'student', 4, 'urgent', 'open', NOW() - INTERVAL 4 HOUR)");
    echo "  Support tickets seeded.\n";
}

// Announcements
$acnt = $pdo->query("SELECT COUNT(*) FROM announcements")->fetchColumn();
if ($acnt == 0) {
    $pdo->exec("INSERT INTO announcements (title, content, created_by, status) VALUES
        ('New Academic Year 2026-27', 'Expanded course offerings including advanced constitutional law and cyber law modules.', 1, 'published'),
        ('Platform Maintenance Scheduled', 'Saturday 2-5 AM IST. Some features may be unavailable.', 1, 'published')");
    echo "  Announcements seeded.\n";
}

// Activity logs
$lcnt = $pdo->query("SELECT COUNT(*) FROM activity_logs")->fetchColumn();
if ($lcnt == 0) {
    $pdo->exec("INSERT INTO activity_logs (action, description, user_type, user_id, priority, created_at) VALUES
        ('org_registered', 'New organization registered: NLU Delhi', 'system', NULL, 'medium', NOW() - INTERVAL 2 HOUR),
        ('content_reported', 'Content reported by student: Course flagged for outdated material', 'student', 1, 'medium', NOW() - INTERVAL 3 HOUR),
        ('login_failed', 'Failed login attempts detected for admin@lawable.in from IP 192.168.1.100', 'system', NULL, 'high', NOW() - INTERVAL 6 HOUR)");
    echo "  Activity logs seeded.\n";
}

echo "\nAll dashboard data seeded successfully.\n";

// Summary
$tables = ['verification_documents' => 'Verifications', 'content_reports' => 'Reports', 'support_tickets' => 'Tickets', 'announcements' => 'Announcements', 'activity_logs' => 'Logs'];
foreach ($tables as $table => $label) {
    $cnt = $pdo->query("SELECT COUNT(*) FROM {$table}")->fetchColumn();
    echo "  {$label}: {$cnt} rows\n";
}
</write_to_file>
