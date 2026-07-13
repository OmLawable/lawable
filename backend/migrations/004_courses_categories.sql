-- Lawable Courses Categories & Expanded Catalog
-- Run after 003_student_dashboard_tables.sql
--
-- Adds: category, difficulty, rating columns to courses table
-- Adds: 15 new courses across Technology, Business, Personal Development categories
-- Updates: existing courses with proper category/difficulty/rating

USE lawable_db;

-- ────────── ADD COLUMNS ──────────
ALTER TABLE courses
    ADD COLUMN IF NOT EXISTS category VARCHAR(80) DEFAULT NULL AFTER price,
    ADD COLUMN IF NOT EXISTS difficulty ENUM('beginner','intermediate','advanced','all-levels') NOT NULL DEFAULT 'all-levels' AFTER category,
    ADD COLUMN IF NOT EXISTS rating DECIMAL(2,1) DEFAULT 4.5 AFTER difficulty;

-- ────────── UPDATE EXISTING COURSES ──────────
UPDATE courses SET category = 'Law & Justice', difficulty = 'advanced', rating = 4.8 WHERE title LIKE 'Constitutional Law%';
UPDATE courses SET category = 'Law & Justice', difficulty = 'intermediate', rating = 4.7 WHERE title LIKE 'Contract Drafting%';
UPDATE courses SET category = 'Law & Justice', difficulty = 'beginner', rating = 4.6 WHERE title LIKE 'Legal Research%';
UPDATE courses SET category = 'Law & Justice', difficulty = 'intermediate', rating = 4.8 WHERE title LIKE 'Criminal Procedure%';
UPDATE courses SET category = 'Business & Compliance', difficulty = 'advanced', rating = 4.5 WHERE title LIKE 'Corporate Law%';
UPDATE courses SET category = 'Business & Compliance', difficulty = 'intermediate', rating = 4.4 WHERE title = 'IPR Essentials';
UPDATE courses SET category = 'Law & Justice', difficulty = 'advanced', rating = 4.6 WHERE title LIKE 'Alternative Dispute%';
UPDATE courses SET category = 'Law & Justice', difficulty = 'beginner', rating = 4.9 WHERE title LIKE 'Legal Writing%';

-- ────────── ADD NEW COURSES ──────────
INSERT IGNORE INTO courses (title, description, category, difficulty, price, status, rating) VALUES
('Introduction to Computer Science & Programming', 'A beginner-friendly course covering CS fundamentals, programming logic, and computational thinking — no prior experience needed.', 'Technology & Computer Science', 'beginner', 0.00, 'published', 4.7),
('Data Structures & Algorithms in Python', 'Master arrays, linked lists, trees, graphs, sorting, and searching with hands-on Python implementations.', 'Technology & Computer Science', 'intermediate', 2499.00, 'published', 4.8),
('Web Development Bootcamp — HTML, CSS & JavaScript', 'Build responsive, interactive websites from scratch. Learn modern CSS, DOM manipulation, and async JS.', 'Technology & Computer Science', 'beginner', 3999.00, 'published', 4.6),
('Database Management & SQL', 'Design relational databases, write complex queries, and understand normalization, indexing, and transactions.', 'Technology & Computer Science', 'intermediate', 1999.00, 'published', 4.5),
('Machine Learning with Python', 'Explore supervised/unsupervised learning, neural networks, and real-world ML pipelines using scikit-learn.', 'Technology & Computer Science', 'advanced', 5999.00, 'published', 4.9),
('Digital Marketing & Brand Strategy', 'SEO, social media marketing, content strategy, and analytics for modern businesses.', 'Business & Compliance', 'beginner', 1499.00, 'published', 4.4),
('Financial Accounting & Analysis', 'Understand balance sheets, P&L statements, cash flow analysis, and ratio interpretation.', 'Business & Compliance', 'intermediate', 2999.00, 'published', 4.5),
('Public Speaking & Communication Skills', 'Overcome stage fear, structure persuasive speeches, and master the art of storytelling.', 'Personal Development', 'all-levels', 999.00, 'published', 4.7),
('Creative Writing & Content Creation', 'Develop your voice across fiction, non-fiction, blogging, and copywriting.', 'Personal Development', 'beginner', 1499.00, 'published', 4.3),
('UI/UX Design Principles', 'Learn user research, wireframing, prototyping, and design systems using Figma.', 'Technology & Computer Science', 'beginner', 2999.00, 'published', 4.6),
('Artificial Intelligence & Ethics', 'Examine the societal impact of AI, algorithmic bias, privacy, and regulatory frameworks.', 'Technology & Computer Science', 'intermediate', 0.00, 'published', 4.8),
('Entrepreneurship & Startup Law', 'Legal essentials every founder must know: incorporation, IP, funding, contracts, and compliance.', 'Business & Compliance', 'all-levels', 1999.00, 'published', 4.5),
('Cybersecurity Fundamentals', 'Understand threats, encryption, network security, and ethical hacking basics.', 'Technology & Computer Science', 'intermediate', 3499.00, 'published', 4.7),
('Emotional Intelligence & Leadership', 'Develop self-awareness, empathy, conflict resolution, and team leadership skills.', 'Personal Development', 'all-levels', 0.00, 'published', 4.9),
('Artificial Intelligence for Legal Professionals', 'How AI is transforming legal research, contract analysis, due diligence, and litigation prediction.', 'Law & Justice', 'intermediate', 2499.00, 'published', 4.6);
