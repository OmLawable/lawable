-- Lawable Database Schema
-- Separate tables for students, organizations, and admins

CREATE DATABASE IF NOT EXISTS lawable_db;
USE lawable_db;

-- Drop old monolithic users table if it exists
DROP TABLE IF EXISTS users;

-- ────────── STUDENTS ──────────
CREATE TABLE IF NOT EXISTS students (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    firebase_uid VARCHAR(128) NULL UNIQUE,
    name VARCHAR(150) NOT NULL,
    username VARCHAR(100) NOT NULL UNIQUE,
    email VARCHAR(255) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NULL DEFAULT NULL, -- nullable: Firebase owns credentials
    phone VARCHAR(30) DEFAULT NULL,
    status ENUM('active','inactive') NOT NULL DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_student_email (email),
    INDEX idx_student_status (status),
    INDEX idx_student_firebase_uid (firebase_uid)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS student_profiles (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    student_id INT UNSIGNED NOT NULL UNIQUE,
    city VARCHAR(120) DEFAULT NULL,
    bio TEXT DEFAULT NULL,
    date_of_birth DATE DEFAULT NULL,
    institution VARCHAR(120) DEFAULT NULL,
    course VARCHAR(120) DEFAULT NULL,
    year_semester VARCHAR(120) DEFAULT NULL,
    areas_of_interest TEXT DEFAULT NULL,
    resume_file VARCHAR(255) DEFAULT NULL,
    linkedin_url VARCHAR(255) DEFAULT NULL,
    skills TEXT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_student_profiles_student
        FOREIGN KEY (student_id) REFERENCES students(id)
        ON DELETE CASCADE,
    INDEX idx_student_profiles_student_id (student_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ────────── ORGANIZATIONS ──────────
CREATE TABLE IF NOT EXISTS organizations (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    firebase_uid VARCHAR(128) NULL UNIQUE,
    organization_name VARCHAR(255) NOT NULL,
    contact_person VARCHAR(150) NOT NULL,
    username VARCHAR(100) NOT NULL UNIQUE,
    email VARCHAR(255) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NULL DEFAULT NULL, -- nullable: Firebase owns credentials
    phone VARCHAR(30) DEFAULT NULL,
    status ENUM('active','inactive') NOT NULL DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_org_email (email),
    INDEX idx_org_status (status),
    INDEX idx_org_firebase_uid (firebase_uid)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ────────── ORGANIZATION PROFILES ──────────
CREATE TABLE IF NOT EXISTS organization_profiles (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    organization_id INT UNSIGNED NOT NULL UNIQUE,
    display_name VARCHAR(255) DEFAULT NULL,
    official_email VARCHAR(255) DEFAULT NULL,
    organization_type ENUM('Law Firm', 'Educational Institution', 'NGO', 'Corporate Legal Dept', 'Ed-tech', 'Government Body') DEFAULT NULL,
    tagline VARCHAR(255) DEFAULT NULL,
    about_description TEXT DEFAULT NULL,
    year_established INT DEFAULT NULL,
    website_url VARCHAR(255) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_org_profiles_org
        FOREIGN KEY (organization_id) REFERENCES organizations(id)
        ON DELETE CASCADE,
    INDEX idx_org_profiles_org_id (organization_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ────────── ADMINS ──────────
CREATE TABLE IF NOT EXISTS admins (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    firebase_uid VARCHAR(128) NULL UNIQUE,
    name VARCHAR(150) NOT NULL,
    username VARCHAR(100) NOT NULL UNIQUE,
    email VARCHAR(255) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NULL DEFAULT NULL, -- nullable: Firebase owns credentials
    status ENUM('active','inactive') NOT NULL DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_admin_email (email),
    INDEX idx_admin_firebase_uid (firebase_uid)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ────────── DEFAULT ADMIN ──────────
-- IMPORTANT: After setup, create admin@lawable.in in Firebase Console
-- (Authentication → Add user) and log in once to auto-link firebase_uid.
INSERT INTO admins (name, username, email, status)
VALUES (
    'System Administrator',
    'admin',
    'admin@lawable.in',
    'active'
)
ON DUPLICATE KEY UPDATE email = VALUES(email);
