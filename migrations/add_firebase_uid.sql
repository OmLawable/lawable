-- Migration: Add firebase_uid to students, organizations, and admins
-- Run this once against your lawable_db database
-- Date: 2026-07-14

USE lawable_db;

-- Add firebase_uid to students (nullable so existing rows are not broken)
ALTER TABLE students
    ADD COLUMN firebase_uid VARCHAR(128) NULL UNIQUE AFTER id,
    ADD INDEX idx_student_firebase_uid (firebase_uid);

-- Add firebase_uid to organizations
ALTER TABLE organizations
    ADD COLUMN firebase_uid VARCHAR(128) NULL UNIQUE AFTER id,
    ADD INDEX idx_org_firebase_uid (firebase_uid);

-- Add firebase_uid to admins
ALTER TABLE admins
    ADD COLUMN firebase_uid VARCHAR(128) NULL UNIQUE AFTER id,
    ADD INDEX idx_admin_firebase_uid (firebase_uid);
