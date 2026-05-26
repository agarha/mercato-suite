ALTER TABLE `{prefix}mercato_kyc_cases`
  ADD COLUMN `verification_url` VARCHAR(512) DEFAULT NULL AFTER `provider_reference`,
  ADD COLUMN `failure_reason` VARCHAR(255) DEFAULT NULL AFTER `status`;
