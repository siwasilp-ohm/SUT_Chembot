-- Add password reset token columns to users table
ALTER TABLE users
    ADD COLUMN reset_token VARCHAR(64) DEFAULT NULL,
    ADD COLUMN reset_token_expires TIMESTAMP NULL DEFAULT NULL;
