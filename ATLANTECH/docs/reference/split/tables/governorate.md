<!-- Generated from docs/reference/database/SaaS.sql lines 17-23 by build-index.mjs. Do not edit: edit the source and rebuild. -->
CREATE TABLE `governorate` (
  `id` smallint PRIMARY KEY AUTO_INCREMENT,
  `code` varchar(6) UNIQUE NOT NULL COMMENT 'ISO 3166-2:EG, e.g. EG-ALX',
  `name_en` varchar(60) UNIQUE NOT NULL,
  `name_ar` varchar(60) NOT NULL,
  `is_active` boolean NOT NULL DEFAULT true
);

<!-- Generated from docs/reference/database/SaaS.md lines 60-74 by build-index.mjs. Do not edit: edit the source and rebuild. -->
## `governorate`

> Egyptian governorates that have a seaport (platform is Egypt-only). Seeded: Alexandria, Beheira, Matrouh, Kafr El Sheikh, Damietta, Port Said, Ismailia, Suez, North Sinai, South Sinai, Red Sea.

| Column | Type | Null | Default | Keys | Notes |
|---|---|---|---|---|---|
| `id` | `smallint` | NO |  | PK · AI |  |
| `code` | `varchar(6)` | NO |  | UQ | ISO 3166-2:EG, e.g. EG-ALX |
| `name_en` | `varchar(60)` | NO |  | UQ |  |
| `name_ar` | `varchar(60)` | NO |  |  |  |
| `is_active` | `boolean` | NO | `true` |  |  |

**Referenced by:** [`platform_port_registry`](#platform_port_registry).governorate_id

---
