-- Migration: add must_change_password flag to users table
-- Existing users default to 0 (not forced). New users / admin password resets
-- should explicitly set this to 1 so the user is forced to set a new password
-- on their next login.

ALTER TABLE `users`
  ADD COLUMN `must_change_password` TINYINT(1) NOT NULL DEFAULT 0 AFTER `password`;
