-- SQL to set up admin functionality
USE fintrack_db;

-- Add is_admin column to users table if it doesn't exist
ALTER TABLE users ADD COLUMN IF NOT EXISTS is_admin TINYINT(1) DEFAULT 0;

-- Add is_approved column to users table if it doesn't exist
ALTER TABLE users ADD COLUMN IF NOT EXISTS is_approved TINYINT(1) DEFAULT 0;

-- Set the first user as admin
UPDATE users SET is_admin = 1, is_approved = 1 WHERE id = 1;

-- Approve all existing users
UPDATE users SET is_approved = 1;