-- Add official_email column to organization_profiles
ALTER TABLE organization_profiles
ADD COLUMN official_email VARCHAR(255) DEFAULT NULL AFTER organization_id;
