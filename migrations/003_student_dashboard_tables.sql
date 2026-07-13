-- Lawable Student Dashboard Tables
-- Run after main schema and 002_add_dashboard_tables.sql
-- Adds tables needed for student home page dashboard features:
-- course_progress, certificates, course_lessons

USE lawable_db;

-- ────────── COURSE PROGRESS ──────────
CREATE TABLE IF NOT EXISTS course_progress (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    student_id INT UNSIGNED NOT NULL,
    course_id INT UNSIGNED NOT NULL,
    progress_percentage DECIMAL(5,2) NOT NULL DEFAULT 0.00,
    last_accessed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    completed_lessons INT UNSIGNED NOT NULL DEFAULT 0,
    total_lessons INT UNSIGNED NOT NULL DEFAULT 0,
    CONSTRAINT fk_progress_student
        FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
    CONSTRAINT fk_progress_course
        FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE,
    UNIQUE KEY uk_progress (student_id, course_id),
    INDEX idx_progress_last_access (last_accessed_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ────────── CERTIFICATES ──────────
CREATE TABLE IF NOT EXISTS certificates (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    student_id INT UNSIGNED NOT NULL,
    course_id INT UNSIGNED NOT NULL,
    certificate_number VARCHAR(50) NOT NULL UNIQUE,
    issued_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_certificates_student
        FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
    CONSTRAINT fk_certificates_course
        FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE,
    UNIQUE KEY uk_certificate (student_id, course_id),
    INDEX idx_certificates_student (student_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ────────── COURSE LESSONS / MODULES ──────────
CREATE TABLE IF NOT EXISTS course_lessons (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    course_id INT UNSIGNED NOT NULL,
    title VARCHAR(255) NOT NULL,
    duration_minutes INT UNSIGNED NOT NULL DEFAULT 0,
    sort_order INT UNSIGNED NOT NULL DEFAULT 0,
    CONSTRAINT fk_lessons_course
        FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE,
    INDEX idx_lessons_course_sort (course_id, sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Add profile completion tracking columns
ALTER TABLE student_profiles
    ADD COLUMN IF NOT EXISTS profile_completed TINYINT(1) NOT NULL DEFAULT 0 AFTER skills,
    ADD COLUMN IF NOT EXISTS completion_nudge_dismissed TINYINT(1) NOT NULL DEFAULT 0 AFTER profile_completed;

-- ────────── SAMPLE PROGRESS DATA ──────────
INSERT IGNORE INTO course_progress (student_id, course_id, progress_percentage, last_accessed_at, completed_lessons, total_lessons) VALUES
(1, 1, 65.00, NOW() - INTERVAL 1 DAY, 8, 12),
(1, 2, 30.00, NOW() - INTERVAL 3 DAY, 3, 10),
(1, 3, 10.00, NOW() - INTERVAL 7 DAY, 1, 8),
(2, 1, 85.00, NOW() - INTERVAL 2 DAY, 10, 12),
(2, 4, 45.00, NOW() - INTERVAL 4 DAY, 4, 9),
(3, 5, 100.00, NOW() - INTERVAL 15 DAY, 8, 8),
(3, 2, 20.00, NOW() - INTERVAL 6 DAY, 2, 10);

-- ────────── SAMPLE CERTIFICATES ──────────
INSERT IGNORE INTO certificates (student_id, course_id, certificate_number, issued_at) VALUES
(3, 5, 'LAW-CERT-2026-0001', NOW() - INTERVAL 15 DAY);

-- ────────── SAMPLE LESSONS ──────────
INSERT IGNORE INTO course_lessons (course_id, title, duration_minutes, sort_order) VALUES
(1, 'Introduction to Constitutional Law', 45, 1),
(1, 'Historical Background of Indian Constitution', 60, 2),
(1, 'Fundamental Rights: Part III', 55, 3),
(1, 'Directive Principles of State Policy', 50, 4),
(1, 'Union Executive & Legislature', 65, 5),
(1, 'State Executive & Legislature', 45, 6),
(1, 'Judiciary: Supreme Court & High Courts', 60, 7),
(1, 'Amendment Process & Basic Structure', 55, 8),
(1, 'Centre-State Relations', 50, 9),
(1, 'Emergency Provisions', 40, 10),
(1, 'Constitutional Remedies: Writs', 45, 11),
(1, 'Case Studies & Landmark Judgments', 60, 12),
(2, 'Essentials of a Valid Contract', 50, 1),
(2, 'Offer, Acceptance & Consideration', 55, 2),
(2, 'Contract Drafting: Structure & Clauses', 60, 3),
(2, 'Indemnity & Guarantee Clauses', 45, 4),
(2, 'Breach of Contract & Remedies', 50, 5),
(3, 'Introduction to Legal Research', 40, 1),
(3, 'Primary vs Secondary Sources', 45, 2),
(3, 'Using SCC Online & Manupatra', 55, 3),
(3, 'Citation Formats & Standards', 35, 4),
(3, 'Research Memo Writing', 50, 5),
(4, 'Overview of Criminal Procedure Code', 45, 1),
(4, 'Cognizable vs Non-Cognizable Offences', 40, 2),
(4, 'FIR & Investigation Procedure', 55, 3),
(4, 'Bail & Remand Provisions', 50, 4),
(4, 'Trial Procedure & Evidence', 60, 5);
