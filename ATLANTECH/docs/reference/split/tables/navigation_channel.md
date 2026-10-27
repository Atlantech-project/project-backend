<!-- Generated from docs/reference/database/PortSync.sql lines 32-42 by build-index.mjs. Do not edit: edit the source and rebuild. -->
CREATE TABLE `navigation_channel` (
  `id` bigint PRIMARY KEY AUTO_INCREMENT,
  `name` varchar(100) UNIQUE NOT NULL,
  `depth` decimal(6,2) NOT NULL COMMENT 'Controlling (minimum) charted depth, metres â€” input to UKC',
  `depth_datum` varchar(40) NOT NULL COMMENT 'e.g. LAT; must equal the active tide_constituent_set.datum_reference',
  `width` decimal(7,2) NOT NULL COMMENT 'metres â€” input to squat',
  `speed_limit_knots` decimal(4,1) NOT NULL COMMENT 'Max permitted transit speed, entered by admin; worst-case input to squat',
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT (CURRENT_TIMESTAMP),
  `updated_at` datetime NOT NULL DEFAULT (CURRENT_TIMESTAMP)
);

<!-- Generated from docs/reference/database/PortSync.md lines 182-204 by build-index.mjs. Do not edit: edit the source and rebuild. -->
## `navigation_channel`

| Column | Type | Null | Default | Keys | Notes |
|---|---|---|---|---|---|
| `id` | `bigint` | NO |  | PK · AI |  |
| `name` | `varchar(100)` | NO |  | UQ |  |
| `depth` | `decimal(6,2)` | NO |  |  | Controlling (minimum) charted depth, metres â€” input to UKC |
| `depth_datum` | `varchar(40)` | NO |  |  | e.g. LAT; must equal the active tide_constituent_set.datum_reference |
| `width` | `decimal(7,2)` | NO |  |  | metres â€” input to squat |
| `speed_limit_knots` | `decimal(4,1)` | NO |  |  | Max permitted transit speed, entered by admin; worst-case input to squat |
| `is_active` | `tinyint(1)` | NO | `1` |  |  |
| `created_at` | `datetime` | NO | `CURRENT_TIMESTAMP` |  |  |
| `updated_at` | `datetime` | NO | `CURRENT_TIMESTAMP` |  |  |

**Check constraints**

| Name | Rule |
|---|---|
| `chk_channel_dimensions` | `((depth > 0) and (width > 0) and (speed_limit_knots > 0))` |

**Referenced by:** [`channel_waypoint`](#channel_waypoint).channel_id

---
