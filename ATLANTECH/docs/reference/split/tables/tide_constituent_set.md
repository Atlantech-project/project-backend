<!-- Generated from docs/reference/database/PortSync.sql lines 559-566 by build-index.mjs. Do not edit: edit the source and rebuild. -->
CREATE TABLE `tide_constituent_set` (
  `id` bigint PRIMARY KEY AUTO_INCREMENT,
  `version_label` varchar(40) UNIQUE NOT NULL,
  `datum_reference` varchar(40) NOT NULL COMMENT 'Must equal navigation_channel / berth depth_datum (backend check on activation)',
  `valid_from` date NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 0,
  `active_key` tinyint UNIQUE COMMENT 'GENERATED (stored): if(is_active,1,NULL)'
);

<!-- Generated from docs/reference/database/PortSync.md lines 1834-1847 by build-index.mjs. Do not edit: edit the source and rebuild. -->
## `tide_constituent_set`

| Column | Type | Null | Default | Keys | Notes |
|---|---|---|---|---|---|
| `id` | `bigint` | NO |  | PK · AI |  |
| `version_label` | `varchar(40)` | NO |  | UQ |  |
| `datum_reference` | `varchar(40)` | NO |  |  | Must equal navigation_channel / berth depth_datum (backend check on activation) |
| `valid_from` | `date` | NO |  |  |  |
| `is_active` | `tinyint(1)` | NO | `0` |  |  |
| `active_key` | `tinyint` | YES |  | UQ | GENERATED (stored): if(is_active,1,NULL) |

**Referenced by:** [`tide_constituent`](#tide_constituent).constituent_set_id, [`tide_prediction_run`](#tide_prediction_run).constituent_set_id

---
