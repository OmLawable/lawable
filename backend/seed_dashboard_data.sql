-- Seed data for admin dashboard demo
-- Run after tables are created

USE lawable_db;

-- Verification documents (already inserted, this is for completeness)
INSERT IGNORE INTO verification_documents (organization_id, document_type, status, submitted_at) VALUES
(1, 'registration', 'verified', NOW() - INTERVAL 60 DAY),
(2, 'registration', 'under_review', NOW() - INTERVAL 5 DAY),
(4, 'registration', 'pending', NOW() - INTERVAL 2 DAY);

-- Course enrollments for dashboard demo (assumes students 1-6+ exist)
INSERT IGNORE INTO course_enrollments (course_id, student_id, enrolled_at) VALUES
(1, 1, NOW() - INTERVAL 25 DAY),
(2, 1, NOW() - INTERVAL 20 DAY),
(1, 2, NOW() - INTERVAL 28 DAY),
(2, 3, NOW() - INTERVAL 5 DAY),
(3, 4, NOW() - INTERVAL 22 DAY);

-- Content reports
INSERT IGNORE INTO content_reports (reported_by_type, reported_by_id, target_type, target_id, reason, status, created_at) VALUES
('student', 1, 'course', 2, 'Contains outdated legal references that may mislead students.', 'open', NOW() - INTERVAL 1 DAY),
('student', 3, 'course', 3, 'Inaccurate citation of Supreme Court judgments.', 'open', NOW() - INTERVAL 6 HOUR);

-- Support tickets
INSERT IGNORE INTO support_tickets (title, message, user_type, user_id, priority, status, created_at) VALUES
('Login issue after password reset', 'Cannot log in after resetting password.', 'student', 1, 'high', 'open', NOW() - INTERVAL 2 DAY),
('Organization profile not updating', 'Changes not reflecting on profile.', 'organization', 2, 'medium', 'in_progress', NOW() - INTERVAL 1 DAY),
('Payment gateway error', 'Error 502 when enrolling in paid course.', 'student', 4, 'urgent', 'open', NOW() - INTERVAL 4 HOUR);

-- Announcements
INSERT IGNORE INTO announcements (title, content, created_by, status) VALUES
('New Academic Year 2026-27', 'Expanded course offerings including advanced constitutional law and cyber law modules.', 1, 'published'),
('Platform Maintenance Scheduled', 'Saturday 2-5 AM IST. Some features may be unavailable.', 1, 'published');

-- Activity logs
INSERT IGNORE INTO activity_logs (action, description, user_type, user_id, priority, created_at) VALUES
('org_registered', 'New organization registered: NLU Delhi', 'system', NULL, 'medium', NOW() - INTERVAL 2 HOUR),
('content_reported', 'Content reported by student: Course flagged for outdated material', 'student', 1, 'medium', NOW() - INTERVAL 3 HOUR),
('login_failed', 'Failed login attempts detected for admin@lawable.in from IP 192.168.1.100', 'system', NULL, 'high', NOW() - INTERVAL 6 HOUR);
</write_to_file>
