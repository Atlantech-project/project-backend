<!-- Generated from docs/reference/database/SaaS.sql lines 1-15 by build-index.mjs. Do not edit: edit the source and rebuild. -->
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

<!-- Generated from docs/reference/database/SaaS.md lines 31-58 by build-index.mjs. Do not edit: edit the source and rebuild. -->
## `license_plan`

> Limits/price are copied onto each licence at issue time.

| Column | Type | Null | Default | Keys | Notes |
|---|---|---|---|---|---|
| `id` | `bigint` | NO |  | PK · AI |  |
| `plan_name` | `varchar(80)` | NO |  | UQ |  |
| `description` | `varchar(255)` | YES |  |  |  |
| `max_terminals` | `int` | NO |  |  |  |
| `max_berths` | `int` | NO |  |  |  |
| `max_users` | `int` | NO |  |  |  |
| `price_per_year` | `decimal(12,2)` | NO |  |  | Annual subscription price |
| `currency` | `char(3)` | NO | `'USD'` |  | ISO 4217 |
| `is_active` | `boolean` | NO | `true` |  | false = not offered for new licences |
| `created_at` | `datetime` | NO | `CURRENT_TIMESTAMP` |  |  |
| `updated_at` | `datetime` | NO | `CURRENT_TIMESTAMP` |  |  |

**Check constraints**

| Name | Rule |
|---|---|
| `chk_plan_limits` | `max_terminals >= 0 AND max_berths >= 0 AND max_users >= 0` |
| `chk_plan_price` | `price_per_year >= 0` |

**Referenced by:** [`license`](#license).license_plan_id

---
