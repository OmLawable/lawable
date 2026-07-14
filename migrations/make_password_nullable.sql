-- Migration: Make password_hash nullable now that Firebase owns credentials
-- Run once against lawable_db
-- Date: 2026-07-14

USE lawable_db;

-- Students: make password_hash nullable (Firebase handles auth)
ALTER TABLE students
    MODIFY COLUMN password_hash VARCHAR(255) NULL DEFAULT NULL;

-- Organizations: make password_hash nullable
ALTER TABLE organizations
    MODIFY COLUMN password_hash VARCHAR(255) NULL DEFAULT NULL;

-- Admins: make password_hash nullable
-- (Admin Firebase accounts must be created manually in Firebase Console)
ALTER TABLE admins
    MODIFY COLUMN password_hash VARCHAR(255) NULL DEFAULT NULL;
