-- Bankverbindung + Sozialmedien
SET NAMES utf8mb4;

ALTER TABLE dg_contacts
  ADD COLUMN IF NOT EXISTS social_linkedin VARCHAR(255) NOT NULL DEFAULT '' AFTER website,
  ADD COLUMN IF NOT EXISTS social_xing VARCHAR(255) NOT NULL DEFAULT '' AFTER social_linkedin,
  ADD COLUMN IF NOT EXISTS social_facebook VARCHAR(255) NOT NULL DEFAULT '' AFTER social_xing,
  ADD COLUMN IF NOT EXISTS social_instagram VARCHAR(255) NOT NULL DEFAULT '' AFTER social_facebook,
  ADD COLUMN IF NOT EXISTS social_x VARCHAR(255) NOT NULL DEFAULT '' AFTER social_instagram,
  ADD COLUMN IF NOT EXISTS social_youtube VARCHAR(255) NOT NULL DEFAULT '' AFTER social_x,
  ADD COLUMN IF NOT EXISTS social_tiktok VARCHAR(255) NOT NULL DEFAULT '' AFTER social_youtube,
  ADD COLUMN IF NOT EXISTS social_github VARCHAR(255) NOT NULL DEFAULT '' AFTER social_tiktok,
  ADD COLUMN IF NOT EXISTS bank_accounts JSON NULL AFTER social_github;
