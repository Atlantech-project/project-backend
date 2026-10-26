<!-- Generated from docs/reference/database/PortSync.sql lines 130-133 by build-index.mjs. Do not edit: edit the source and rebuild. -->
CREATE TABLE `pilot_rank_limit` (
  `pilot_rank` ENUM ('FIRST', 'SECOND', 'THIRD') PRIMARY KEY,
  `max_vessel_loa` decimal(7,2) COMMENT 'Largest vessel LOA (m) this rank may pilot; NULL = no limit'
);

<!-- Generated from docs/reference/database/PortSync.md lines 459-474 by build-index.mjs. Do not edit: edit the source and rebuild. -->
## `pilot_rank_limit`

> Settings screen: one row per rank.

| Column | Type | Null | Default | Keys | Notes |
|---|---|---|---|---|---|
| `pilot_rank` | `ENUM(FIRST, SECOND, THIRD)` | NO |  | PK |  |
| `max_vessel_loa` | `decimal(7,2)` | YES |  |  | Largest vessel LOA (m) this rank may pilot; NULL = no limit |

**Check constraints**

| Name | Rule |
|---|---|
| `chk_pilot_rank_limit_loa` | `((max_vessel_loa is null) or (max_vessel_loa > 0))` |

---
