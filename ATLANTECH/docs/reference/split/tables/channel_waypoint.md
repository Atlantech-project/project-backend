<!-- Generated from docs/reference/database/PortSync.sql lines 1-8 by build-index.mjs. Do not edit: edit the source and rebuild. -->
CREATE TABLE `channel_waypoint` (
  `id` bigint PRIMARY KEY AUTO_INCREMENT,
  `channel_id` bigint NOT NULL,
  `sequence_order` int NOT NULL,
  `label` varchar(60) NOT NULL COMMENT 'Waypoint name entered by admin, e.g. Fairway Buoy',
  `latitude` decimal(9,6) NOT NULL,
  `longitude` decimal(9,6) NOT NULL
);

<!-- Generated from docs/reference/database/PortSync.md lines 113-145 by build-index.mjs. Do not edit: edit the source and rebuild. -->
## `channel_waypoint`

| Column | Type | Null | Default | Keys | Notes |
|---|---|---|---|---|---|
| `id` | `bigint` | NO |  | PK · AI |  |
| `channel_id` | `bigint` | NO |  | FK → `navigation_channel.id` |  |
| `sequence_order` | `int` | NO |  |  |  |
| `label` | `varchar(60)` | NO |  |  | Waypoint name entered by admin, e.g. Fairway Buoy |
| `latitude` | `decimal(9,6)` | NO |  |  |  |
| `longitude` | `decimal(9,6)` | NO |  |  |  |

**Check constraints**

| Name | Rule |
|---|---|
| `chk_waypoint_lat` | `(latitude between -(90) and 90)` |
| `chk_waypoint_lon` | `(longitude between -(180) and 180)` |
| `chk_waypoint_order` | `(sequence_order > 0)` |

**Indexes**

| Name | Unique | Columns |
|---|---|---|
| `uq_channel_waypoint_label` | Yes | channel_id, label |
| `uq_channel_waypoint_order` | Yes | channel_id, sequence_order |

**Foreign keys**

| Constraint | Column(s) | References | Rules |
|---|---|---|---|
| `fk_channel_waypoint_channel` | channel_id | [`navigation_channel`](#navigation_channel) (id) |  |

---
