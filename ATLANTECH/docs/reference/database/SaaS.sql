CREATE TABLE `license_plan` (
  `id` bigint PRIMARY KEY AUTO_INCREMENT,
  `plan_name` varchar(80) UNIQUE NOT NULL,
  `description` varchar(255),
  `max_terminals` int NOT NULL,
  `max_berths` int NOT NULL,
  `max_users` int NOT NULL,
  `price_per_year` decimal(12,2) NOT NULL COMMENT 'Annual subscription price',
  `currency` char(3) NOT NULL DEFAULT 'USD' COMMENT 'ISO 4217',
  `is_active` boolean NOT NULL DEFAULT true COMMENT 'false = not offered for new licences',
  `created_at` datetime NOT NULL DEFAULT (CURRENT_TIMESTAMP),
  `updated_at` datetime NOT NULL DEFAULT (CURRENT_TIMESTAMP),
  CONSTRAINT `chk_plan_limits` CHECK (max_terminals >= 0 AND max_berths >= 0 AND max_users >= 0),
  CONSTRAINT `chk_plan_price` CHECK (price_per_year >= 0)
);

CREATE TABLE `governorate` (
  `id` smallint PRIMARY KEY AUTO_INCREMENT,
  `code` varchar(6) UNIQUE NOT NULL COMMENT 'ISO 3166-2:EG, e.g. EG-ALX',
  `name_en` varchar(60) UNIQUE NOT NULL,
  `name_ar` varchar(60) NOT NULL,
  `is_active` boolean NOT NULL DEFAULT true
);

CREATE TABLE `platform_port_registry` (
  `id` bigint PRIMARY KEY AUTO_INCREMENT,
  `public_id` char(36) UNIQUE NOT NULL COMMENT 'Stable UUID shared with the Port deployment and carried in licence tokens',
  `port_code` varchar(10) UNIQUE COMMENT 'UN/LOCODE, e.g. EGALY',
  `port_name` varchar(150) NOT NULL,
  `governorate_id` smallint NOT NULL,
  `server_endpoint` varchar(255) NOT NULL COMMENT 'Management/support address of the port server (e.g. VPN). Not the app URL; no runtime calls',
  `api_credential_hash` varchar(255) COMMENT 'Credential hash for the daily licence pull',
  `api_credential_rotated_at` datetime,
  `last_check_in_at` datetime COMMENT 'Last successful licence pull',
  `onboarded_by` bigint NOT NULL,
  `onboarded_at` datetime NOT NULL DEFAULT (CURRENT_TIMESTAMP),
  `status` ENUM ('ONBOARDING', 'ACTIVE', 'SUSPENDED', 'OFFBOARDED') NOT NULL DEFAULT 'ONBOARDING',
  `created_at` datetime NOT NULL DEFAULT (CURRENT_TIMESTAMP),
  `updated_at` datetime NOT NULL DEFAULT (CURRENT_TIMESTAMP)
);

CREATE TABLE `license_token` (
  `id` bigint PRIMARY KEY AUTO_INCREMENT,
  `license_id` bigint NOT NULL,
  `jti` char(36) UNIQUE NOT NULL COMMENT 'Token id inside the signed token',
  `issued_at` datetime NOT NULL DEFAULT (CURRENT_TIMESTAMP),
  `expires_at` datetime NOT NULL COMMENT '~35 days, <= license.expires_at',
  `payload` json NOT NULL COMMENT 'Copy of signed claims',
  `token_hash` char(64) UNIQUE NOT NULL COMMENT 'SHA-256 of signed token; signing key not in DB',
  `delivery_method` ENUM ('PULL', 'MANUAL') NOT NULL,
  `delivered_at` datetime COMMENT 'NULL = not yet collected by the port',
  `revoked_at` datetime,
  CONSTRAINT `chk_license_token_validity` CHECK (expires_at > issued_at)
);

CREATE TABLE `platform_audit_log` (
  `id` bigint PRIMARY KEY AUTO_INCREMENT,
  `actor_type` ENUM ('ADMIN', 'SYSTEM') NOT NULL DEFAULT 'ADMIN',
  `saas_admin_id` bigint COMMENT 'Required when actor_type = ADMIN',
  `action` varchar(60) NOT NULL COMMENT 'Controlled vocabulary in the application',
  `target_port_registry_id` bigint,
  `target_type` varchar(40) COMMENT 'e.g. license, license_plan, billing_transaction',
  `target_id` bigint,
  `details` json,
  `occurred_at` datetime NOT NULL DEFAULT (CURRENT_TIMESTAMP),
  CONSTRAINT `chk_audit_actor` CHECK (actor_type = 'SYSTEM' OR saas_admin_id IS NOT NULL),
  CONSTRAINT `chk_audit_target_pair` CHECK ((target_type IS NULL) = (target_id IS NULL))
);

CREATE TABLE `saas_admin` (
  `id` bigint PRIMARY KEY AUTO_INCREMENT,
  `name` varchar(120) NOT NULL,
  `email` varchar(150) UNIQUE NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `roles` varchar(255) NOT NULL DEFAULT '' COMMENT 'SET(PLATFORM, LICENSE, BILLING) - logical admin roles; one admin may hold several (DBML has no SET type)',
  `is_active` boolean NOT NULL DEFAULT true,
  `last_login_at` datetime,
  `created_at` datetime NOT NULL DEFAULT (CURRENT_TIMESTAMP),
  `updated_at` datetime NOT NULL DEFAULT (CURRENT_TIMESTAMP)
);

CREATE TABLE `license` (
  `id` bigint PRIMARY KEY AUTO_INCREMENT,
  `port_registry_id` bigint NOT NULL,
  `license_plan_id` bigint NOT NULL,
  `max_terminals` int NOT NULL COMMENT 'Snapshot of plan at issue time',
  `max_berths` int NOT NULL COMMENT 'Snapshot',
  `max_users` int NOT NULL COMMENT 'Snapshot',
  `price_per_year` decimal(12,2) NOT NULL COMMENT 'Agreed annual price (snapshot)',
  `currency` char(3) NOT NULL COMMENT 'Snapshot',
  `issued_at` datetime NOT NULL DEFAULT (CURRENT_TIMESTAMP) COMMENT 'Administrative issue time',
  `valid_from` datetime NOT NULL COMMENT 'Start of validity; may be future for early renewals',
  `expires_at` datetime NOT NULL,
  `status` ENUM ('PENDING', 'ACTIVE', 'EXPIRED', 'SUSPENDED', 'REVOKED', 'SUPERSEDED') NOT NULL DEFAULT 'PENDING',
  `on_hold` boolean NOT NULL DEFAULT false COMMENT 'PENDING renewal held while the current licence is SUSPENDED',
  `active_port_key` bigint UNIQUE COMMENT 'GENERATED (stored): IF(status = ACTIVE, port_registry_id, NULL). One ACTIVE licence per port',
  `created_at` datetime NOT NULL DEFAULT (CURRENT_TIMESTAMP),
  `updated_at` datetime NOT NULL DEFAULT (CURRENT_TIMESTAMP),
  CONSTRAINT `chk_license_validity` CHECK (expires_at > valid_from),
  CONSTRAINT `chk_license_limits` CHECK (max_terminals >= 0 AND max_berths >= 0 AND max_users >= 0),
  CONSTRAINT `chk_license_price` CHECK (price_per_year >= 0)
);

CREATE TABLE `billing_transaction` (
  `id` bigint PRIMARY KEY AUTO_INCREMENT,
  `license_id` bigint NOT NULL,
  `period_start` date NOT NULL COMMENT 'Subscription year covered (annual billing)',
  `period_end` date NOT NULL,
  `live_period_key` date COMMENT 'GENERATED (stored): IF(status IN (PENDING, FAILED, PAID), period_start, NULL)',
  `amount` decimal(12,2) NOT NULL,
  `currency` char(3) NOT NULL,
  `billing_date` date NOT NULL,
  `status` ENUM ('PENDING', 'PAID', 'FAILED', 'REFUNDED', 'CANCELLED') NOT NULL DEFAULT 'PENDING',
  `paid_at` datetime,
  `external_reference` varchar(100) COMMENT 'Payment gateway / bank reference',
  `created_at` datetime NOT NULL DEFAULT (CURRENT_TIMESTAMP),
  `updated_at` datetime NOT NULL DEFAULT (CURRENT_TIMESTAMP),
  CONSTRAINT `chk_billing_period` CHECK (period_end >= period_start),
  CONSTRAINT `chk_billing_amount` CHECK (amount >= 0)
);

CREATE INDEX `idx_port_status` ON `platform_port_registry` (`status`);

CREATE INDEX `idx_port_governorate` ON `platform_port_registry` (`governorate_id`);

CREATE INDEX `idx_license_token_license_expires` ON `license_token` (`license_id`, `expires_at`);

CREATE INDEX `idx_audit_port_time` ON `platform_audit_log` (`target_port_registry_id`, `occurred_at`);

CREATE INDEX `idx_audit_target` ON `platform_audit_log` (`target_type`, `target_id`);

CREATE INDEX `idx_audit_time` ON `platform_audit_log` (`occurred_at`);

CREATE INDEX `idx_license_port_status` ON `license` (`port_registry_id`, `status`);

CREATE INDEX `idx_license_status_expires` ON `license` (`status`, `expires_at`);

CREATE UNIQUE INDEX `uq_billing_license_live_period` ON `billing_transaction` (`license_id`, `live_period_key`);

CREATE INDEX `idx_billing_status_date` ON `billing_transaction` (`status`, `billing_date`);

ALTER TABLE `license_plan` COMMENT = 'Limits/price are copied onto each licence at issue time.';

ALTER TABLE `governorate` COMMENT = 'Egyptian governorates that have a seaport (platform is Egypt-only). Seeded: Alexandria, Beheira, Matrouh, Kafr El Sheikh, Damietta, Port Said, Ismailia, Suez, North Sinai, South Sinai, Red Sea.';

ALTER TABLE `platform_port_registry` COMMENT = 'Customer ports / deployments. Never deleted: OFFBOARDED.';

ALTER TABLE `license_token` COMMENT = 'Signed licence tokens (hybrid: daily pull + manual fallback).';

ALTER TABLE `platform_audit_log` COMMENT = 'Append-only platform audit trail.';

ALTER TABLE `saas_admin` COMMENT = 'ATLANTECH platform administrators. Never deleted: deactivate with is_active.';

ALTER TABLE `license` COMMENT = 'Many licences per port over time. At most ONE ACTIVE per port (enforced in SQL via generated column active_port_key + UNIQUE).';

ALTER TABLE `billing_transaction` COMMENT = 'Subscription billing (ATLANTECH -> port). Port derived through license. Refunded/cancelled transactions free the period.';

ALTER TABLE `platform_port_registry` ADD FOREIGN KEY (`governorate_id`) REFERENCES `governorate` (`id`);

ALTER TABLE `platform_port_registry` ADD FOREIGN KEY (`onboarded_by`) REFERENCES `saas_admin` (`id`);

ALTER TABLE `license_token` ADD FOREIGN KEY (`license_id`) REFERENCES `license` (`id`);

ALTER TABLE `platform_audit_log` ADD FOREIGN KEY (`saas_admin_id`) REFERENCES `saas_admin` (`id`);

ALTER TABLE `platform_audit_log` ADD FOREIGN KEY (`target_port_registry_id`) REFERENCES `platform_port_registry` (`id`);

ALTER TABLE `license` ADD FOREIGN KEY (`port_registry_id`) REFERENCES `platform_port_registry` (`id`);

ALTER TABLE `license` ADD FOREIGN KEY (`license_plan_id`) REFERENCES `license_plan` (`id`);

ALTER TABLE `billing_transaction` ADD FOREIGN KEY (`license_id`) REFERENCES `license` (`id`);
