<!-- Generated from docs/reference/database/SaaS.sql lines 104-120 by build-index.mjs. Do not edit: edit the source and rebuild. -->
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

<!-- Generated from docs/reference/database/SaaS.md lines 261-301 by build-index.mjs. Do not edit: edit the source and rebuild. -->
## `billing_transaction`

> Subscription billing (ATLANTECH -> port). Port derived through license. Refunded/cancelled transactions free the period.

| Column | Type | Null | Default | Keys | Notes |
|---|---|---|---|---|---|
| `id` | `bigint` | NO |  | PK · AI |  |
| `license_id` | `bigint` | NO |  | FK → `license.id` |  |
| `period_start` | `date` | NO |  |  | Subscription year covered (annual billing) |
| `period_end` | `date` | NO |  |  |  |
| `live_period_key` | `date` | YES |  |  | GENERATED (stored): IF(status IN (PENDING, FAILED, PAID), period_start, NULL) |
| `amount` | `decimal(12,2)` | NO |  |  |  |
| `currency` | `char(3)` | NO |  |  |  |
| `billing_date` | `date` | NO |  |  |  |
| `status` | `ENUM(PENDING, PAID, FAILED, REFUNDED, CANCELLED)` | NO | `'PENDING'` |  |  |
| `paid_at` | `datetime` | YES |  |  |  |
| `external_reference` | `varchar(100)` | YES |  |  | Payment gateway / bank reference |
| `created_at` | `datetime` | NO | `CURRENT_TIMESTAMP` |  |  |
| `updated_at` | `datetime` | NO | `CURRENT_TIMESTAMP` |  |  |

**Check constraints**

| Name | Rule |
|---|---|
| `chk_billing_period` | `period_end >= period_start` |
| `chk_billing_amount` | `amount >= 0` |

**Indexes**

| Name | Unique | Columns |
|---|---|---|
| `uq_billing_license_live_period` | Yes | license_id, live_period_key |
| `idx_billing_status_date` | No | status, billing_date |

**Foreign keys**

| Constraint | Column(s) | References | Rules |
|---|---|---|---|
| — | license_id | [`license`](#license) (id) |  |

---
