-- Lawable Dashboard Tables
-- Additional tables for admin dashboard functionality
-- Run this after the main schema

USE lawable_db;

-- ────────── COURSES ──────────
CREATE TABLE IF NOT EXISTS courses (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    description TEXT DEFAULT NULL,
    organization_id INT UNSIGNED DEFAULT NULL,
    price DECIMAL(10,2) DEFAULT 0.00,
    status ENUM('draft','published','archived') NOT NULL DEFAULT 'draft',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_courses_org
        FOREIGN KEY (organization_id) REFERENCES organizations(id)
        ON DELETE SET NULL,
    INDEX idx_courses_status (status),
    INDEX idx_courses_org (organization_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ────────── COURSE ENROLLMENTS ──────────
CREATE TABLE IF NOT EXISTS course_enrollments (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    course_id INT UNSIGNED NOT NULL,
    student_id INT UNSIGNED NOT NULL,
    enrolled_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    completed_at TIMESTAMP NULL DEFAULT NULL,
    CONSTRAINT fk_enrollments_course
        FOREIGN KEY (course_id) REFERENCES courses(id)
        ON DELETE CASCADE,
    CONSTRAINT fk_enrollments_student
        FOREIGN KEY (student_id) REFERENCES students(id)
        ON DELETE CASCADE,
    UNIQUE KEY uk_enrollment (course_id, student_id),
    INDEX idx_enrollments_course (course_id),
    INDEX idx_enrollments_student (student_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ────────── VERIFICATION DOCUMENTS ──────────
CREATE TABLE IF NOT EXISTS verification_documents (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    organization_id INT UNSIGNED NOT NULL,
    document_type VARCHAR(100) NOT NULL DEFAULT 'registration',
    file_path VARCHAR(255) DEFAULT NULL,
    status ENUM('pending','under_review','verified','rejected') NOT NULL DEFAULT 'pending',
    admin_notes TEXT DEFAULT NULL,
    submitted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    reviewed_by INT UNSIGNED DEFAULT NULL,
    reviewed_at TIMESTAMP NULL DEFAULT NULL,
    CONSTRAINT fk_verification_org
        FOREIGN KEY (organization_id) REFERENCES organizations(id)
        ON DELETE CASCADE,
    CONSTRAINT fk_verification_reviewer
        FOREIGN KEY (reviewed_by) REFERENCES admins(id)
        ON DELETE SET NULL,
    INDEX idx_verification_status (status),
    INDEX idx_verification_org (organization_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ────────── ANNOUNCEMENTS ──────────
CREATE TABLE IF NOT EXISTS announcements (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    content TEXT NOT NULL,
    created_by INT UNSIGNED NOT NULL,
    status ENUM('published','draft','archived') NOT NULL DEFAULT 'published',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_announcements_creator
        FOREIGN KEY (created_by) REFERENCES admins(id)
        ON DELETE CASCADE,
    INDEX idx_announcements_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ────────── CONTENT REPORTS ──────────
CREATE TABLE IF NOT EXISTS content_reports (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    reported_by_type ENUM('student','organization','admin') NOT NULL,
    reported_by_id INT UNSIGNED NOT NULL,
    target_type VARCHAR(100) NOT NULL DEFAULT 'course',
    target_id INT UNSIGNED DEFAULT NULL,
    reason TEXT NOT NULL,
    status ENUM('open','under_review','resolved','dismissed') NOT NULL DEFAULT 'open',
    reviewed_by INT UNSIGNED DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    resolved_at TIMESTAMP NULL DEFAULT NULL,
    CONSTRAINT fk_reports_reviewer
        FOREIGN KEY (reviewed_by) REFERENCES admins(id)
        ON DELETE SET NULL,
    INDEX idx_reports_status (status),
    INDEX idx_reports_type (target_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ────────── SUPPORT TICKETS ──────────
CREATE TABLE IF NOT EXISTS support_tickets (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    message TEXT NOT NULL,
    user_type ENUM('student','organization','admin') NOT NULL,
    user_id INT UNSIGNED NOT NULL,
    priority ENUM('low','medium','high','urgent') NOT NULL DEFAULT 'medium',
    status ENUM('open','in_progress','resolved','closed') NOT NULL DEFAULT 'open',
    assigned_to INT UNSIGNED DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_tickets_assignee
        FOREIGN KEY (assigned_to) REFERENCES admins(id)
        ON DELETE SET NULL,
    INDEX idx_tickets_status (status),
    INDEX idx_tickets_priority (priority)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ────────── ACTIVITY LOGS ──────────
CREATE TABLE IF NOT EXISTS activity_logs (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    action VARCHAR(100) NOT NULL,
    description TEXT NOT NULL,
    user_type ENUM('student','organization','admin','system') NOT NULL DEFAULT 'system',
    user_id INT UNSIGNED DEFAULT NULL,
    priority ENUM('low','medium','high') NOT NULL DEFAULT 'low',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_activity_created (created_at),
    INDEX idx_activity_priority (priority),
    INDEX idx_activity_action (action)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ────────── SAMPLE DATA FOR DASHBOARD DEMO ──────────

-- Sample courses
INSERT INTO courses (title, description, organization_id, price, status) VALUES
('Constitutional Law Fundamentals', 'A comprehensive introduction to constitutional law in India.', NULL, 0.00, 'published'),
('Contract Drafting Masterclass', 'Learn to draft legally binding contracts with confidence.', NULL, 2999.00, 'published'),
('Legal Research Methods', 'Master the art of legal research using modern tools.', NULL, 1499.00, 'published'),
('Criminal Procedure Code 101', 'Understanding the CrPC framework for practicing lawyers.', NULL, 0.00, 'published'),
('Corporate Law & Compliance', 'Navigate corporate regulations and compliance requirements.', NULL, 4999.00, 'published'),
('IPR Essentials', 'Intellectual property rights for creators and businesses.', NULL, 1999.00, 'draft'),
('Alternative Dispute Resolution', 'Mediation, arbitration, and conciliation techniques.', NULL, 2499.00, 'published'),
('Legal Writing & Drafting', 'Professional legal writing skills for court submissions.', NULL, 0.00, 'published');

-- Sample enrollments (link to existing students - won't fail if students table empty since no FK constraint issue)
INSERT INTO course_enrollments (course_id, student_id, enrolled_at) VALUES
(1, 1, NOW() - INTERVAL 25 DAY),
(2, 1, NOW() - INTERVAL 20 DAY),
(3, 1, NOW() - INTERVAL 15 DAY),
(1, 2, NOW() - INTERVAL 28 DAY),
(4, 2, NOW() - INTERVAL 12 DAY),
(5, 3, NOW() - INTERVAL 8 DAY),
(2, 3, NOW() - INTERVAL 5 DAY),
(3, 4, NOW() - INTERVAL 22 DAY),
(6, 4, NOW() - INTERVAL 3 DAY),
(1, 5, NOW() - INTERVAL 10 DAY),
(7, 5, NOW() - INTERVAL 2 DAY),
(4, 6, NOW() - INTERVAL 18 DAY),
(8, 6, NOW() - INTERVAL 7 DAY);

-- Sample verification documents
INSERT INTO verification_documents (organization_id, document_type, status, submitted_at) VALUES
(1, 'registration', 'verified', NOW() - INTERVAL 60 DAY),
(2, 'registration', 'under_review', NOW() - INTERVAL 5 DAY),
(3, 'registration', 'pending', NOW() - INTERVAL 2 DAY),
(4, 'registration', 'verified', NOW() - INTERVAL 90 DAY),
(5, 'registration', 'pending', NOW() - INTERVAL 1 DAY),
(6, 'registration', 'under_review', NOW() - INTERVAL 3 DAY),
(7, 'registration', 'rejected', NOW() - INTERVAL 10 DAY);

-- Sample content reports
INSERT INTO content_reports (reported_by_type, reported_by_id, target_type, target_id, reason, status, created_at) VALUES
('student', 1, 'course', 2, 'Contains outdated legal references that may mislead students.', 'open', NOW() - INTERVAL 1 DAY),
('organization', 2, 'course', 5, 'Copyright infringement - course material matches our proprietary content.', 'under_review', NOW() - INTERVAL 3 DAY),
('student', 3, 'course', 3, 'Inaccurate citation of Supreme Court judgments.', 'open', NOW() - INTERVAL 6 HOUR);

-- Sample support tickets
INSERT INTO support_tickets (title, message, user_type, user_id, priority, status, created_at) VALUES
('Login issue after password reset', 'I cannot log in after resetting my password last night.', 'student', 1, 'high', 'open', NOW() - INTERVAL 2 DAY),
('Organization profile not updating', 'Changes to our organization description are not reflecting.', 'organization', 2, 'medium', 'in_progress', NOW() - INTERVAL 1 DAY),
('Payment gateway error', 'Getting error 502 when trying to enroll in a paid course.', 'student', 4, 'urgent', 'open', NOW() - INTERVAL 4 HOUR),
('Course creation request', 'We would like to submit a new course for approval.', 'organization', 3, 'low', 'resolved', NOW() - INTERVAL 7 DAY);

-- Sample announcements
INSERT INTO announcements (title, content, created_by, status) VALUES
('New Academic Year 2026-27', 'We are pleased to announce the start of the new academic year with expanded course offerings including advanced constitutional law and cyber law modules.', 1, 'published'),
('Platform Maintenance Scheduled', 'The platform will be undergoing maintenance on Saturday from 2 AM to 5 AM IST. Some features may be unavailable during this time.', 1, 'published'),
('Faculty Recruitment Drive', 'Lawable is looking for experienced legal professionals to join our faculty. Apply through the organization portal.', 1, 'draft');

-- Sample activity logs
INSERT INTO activity_logs (action, description, user_type, user_id, priority, created_at) VALUES
('org_registered', 'New organization registered: NLU Delhi', 'system', NULL, 'medium', NOW() - INTERVAL 2 HOUR),
('content_reported', 'Content reported by student: Course "Corporate Law" flagged for outdated material', 'student', 1, 'medium', NOW() - INTERVAL 3 HOUR),
('login_failed', 'Failed login attempts detected for user "admin@lawable.in" from IP 192.168.1.100', 'system', NULL, 'high', NOW() - INTERVAL 6 HOUR),
('enrollment_completed', 'Student enrolled in "Legal Research Methods" course', 'student', 3, 'low', NOW() - INTERVAL 8 HOUR),
('org_verified', 'Organization verification completed for "ABC Law College"', 'admin', 1, 'low', NOW() - INTERVAL 1 DAY),
('ticket_created', 'Urgent support ticket raised: Payment gateway error', 'student', 4, 'high', NOW() - INTERVAL 4 HOUR),
('course_published', 'New course "Cyber Law & Digital Rights" published', 'system', NULL, 'low', NOW() - INTERVAL 1 DAY),
('profile_updated', 'Organization "XYZ Law Firm" updated their profile information', 'organization', 2, 'low', NOW() - INTERVAL 2 DAY),
('password_reset', 'Password reset requested for student account "rahul.kumar"', 'student', 5, 'medium', NOW() - INTERVAL 3 DAY),
('document_uploaded', 'Verification documents uploaded by "National Law Institute"', 'organization', 4, 'medium', NOW() - INTERVAL 5 DAY);
