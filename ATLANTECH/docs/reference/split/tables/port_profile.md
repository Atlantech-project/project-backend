<!-- Generated from docs/reference/database/PortSync.sql lines 44-56 by build-index.mjs. Do not edit: edit the source and rebuild. -->
CREATE TABLE `port_profile` (
  `id` tinyint PRIMARY KEY DEFAULT 1,
  `public_id` char(36) UNIQUE NOT NULL COMMENT 'Same value as SaaS platform_port_registry.public_id (read-only)',
  `port_code` varchar(10) COMMENT 'UN/LOCODE, from SaaS',
  `governorate_code` varchar(6) NOT NULL COMMENT 'ISO 3166-2:EG, same codes as SaaS governorate',
  `name` varchar(150) NOT NULL,
  `latitude` decimal(9,6) NOT NULL COMMENT 'Reference point for weather/tide pulls',
  `longitude` decimal(9,6) NOT NULL,
  `timezone` varchar(64) NOT NULL COMMENT 'IANA, e.g. Africa/Cairo',
  `boundary_coordinates` json COMMENT 'GeoJSON Polygon, [lon, lat]',
  `created_at` datetime NOT NULL DEFAULT (CURRENT_TIMESTAMP),
  `updated_at` datetime NOT NULL DEFAULT (CURRENT_TIMESTAMP)
);

<!-- Generated from docs/reference/database/PortSync.md lines 206-232 by build-index.mjs. Do not edit: edit the source and rebuild. -->
## `port_profile`

> Singleton per deployment. GM is a user_role_assignment, not a column here.

| Column | Type | Null | Default | Keys | Notes |
|---|---|---|---|---|---|
| `id` | `tinyint` | NO | `1` | PK |  |
| `public_id` | `char(36)` | NO |  | UQ | Same value as SaaS platform_port_registry.public_id (read-only) |
| `port_code` | `varchar(10)` | YES |  |  | UN/LOCODE, from SaaS |
| `governorate_code` | `varchar(6)` | NO |  |  | ISO 3166-2:EG, same codes as SaaS governorate |
| `name` | `varchar(150)` | NO |  |  |  |
| `latitude` | `decimal(9,6)` | NO |  |  | Reference point for weather/tide pulls |
| `longitude` | `decimal(9,6)` | NO |  |  |  |
| `timezone` | `varchar(64)` | NO |  |  | IANA, e.g. Africa/Cairo |
| `boundary_coordinates` | `json` | YES |  |  | GeoJSON Polygon, [lon, lat] |
| `created_at` | `datetime` | NO | `CURRENT_TIMESTAMP` |  |  |
| `updated_at` | `datetime` | NO | `CURRENT_TIMESTAMP` |  |  |

**Check constraints**

| Name | Rule |
|---|---|
| `chk_port_profile_lat` | `(latitude between -(90) and 90)` |
| `chk_port_profile_lon` | `(longitude between -(180) and 180)` |
| `chk_port_profile_singleton` | `(id = 1)` |

---
