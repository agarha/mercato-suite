-- Provider email verification. Token issued at signup time, sent via wp_mail
-- with a /verify-email?token=... link. Confirming flips email_verified_at,
-- which the admin approval queue surfaces as a trust signal.
--
-- Token is opaque random hex (32 chars / 128 bits) — collision-safe at any
-- realistic scale. Stored on the vendor row (1:1 relationship). Re-issues
-- overwrite the column atomically.
ALTER TABLE `{prefix}mercato_vendors`
  ADD COLUMN `email_verification_token` CHAR(64) DEFAULT NULL AFTER `verified_at`,
  ADD COLUMN `email_verified_at` DATETIME(3) DEFAULT NULL AFTER `email_verification_token`,
  ADD COLUMN `email_verification_sent_at` DATETIME(3) DEFAULT NULL AFTER `email_verified_at`,
  ADD INDEX `idx_verification_token` (`email_verification_token`);
