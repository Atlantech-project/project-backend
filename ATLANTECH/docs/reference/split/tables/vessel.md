<!-- Generated from docs/reference/database/PortSync.sql lines 190-202 by build-index.mjs. Do not edit: edit the source and rebuild. -->
CREATE TABLE `vessel` (
  `id` bigint PRIMARY KEY AUTO_INCREMENT,
  `imo_number` char(7) UNIQUE NOT NULL COMMENT '7 digits, no "IMO" prefix; backend validates the check digit',
  `name` varchar(120) NOT NULL,
  `vessel_type_id` bigint NOT NULL,
  `loa` decimal(7,2) NOT NULL COMMENT 'Length overall, m',
  `beam` decimal(6,2) NOT NULL COMMENT 'Breadth, m. Needed for squat (blockage)',
  `max_draft` decimal(5,2) NOT NULL COMMENT 'Summer (max) draft, m; arrival_draft must not exceed it',
  `net_tonnage` int COMMENT 'NT. Nullable: not in BAPLIE; backend requires it at Preliminary Approval',
  `moulded_depth` decimal(5,2) COMMENT 'm. Nullable, same rule',
  `call_sign` varchar(10) COMMENT 'Nullable',
  `flag_state` char(2) COMMENT 'ISO 3166-1 alpha-2. Nullable'
);

<!-- Generated from docs/reference/database/PortSync.md lines 659-693 by build-index.mjs. Do not edit: edit the source and rebuild. -->
## `vessel`

> Block coefficient (Cb), GT and DWT are per call: they live on berthing_request.

| Column | Type | Null | Default | Keys | Notes |
|---|---|---|---|---|---|
| `id` | `bigint` | NO |  | PK · AI |  |
| `imo_number` | `char(7)` | NO |  | UQ | 7 digits, no "IMO" prefix; backend validates the check digit |
| `name` | `varchar(120)` | NO |  |  |  |
| `vessel_type_id` | `bigint` | NO |  | FK → `vessel_type.id` |  |
| `loa` | `decimal(7,2)` | NO |  |  | Length overall, m |
| `beam` | `decimal(6,2)` | NO |  |  | Breadth, m. Needed for squat (blockage) |
| `max_draft` | `decimal(5,2)` | NO |  |  | Summer (max) draft, m; arrival_draft must not exceed it |
| `net_tonnage` | `int` | YES |  |  | NT. Nullable: not in BAPLIE; backend requires it at Preliminary Approval |
| `moulded_depth` | `decimal(5,2)` | YES |  |  | m. Nullable, same rule |
| `call_sign` | `varchar(10)` | YES |  |  | Nullable |
| `flag_state` | `char(2)` | YES |  |  | ISO 3166-1 alpha-2. Nullable |

**Check constraints**

| Name | Rule |
|---|---|
| `chk_vessel_dims` | `(loa > 0) and (beam > 0) and (beam < loa) and (max_draft > 0)` |
| `chk_vessel_imo` | `regexp_like(imo_number, '^[0-9]{7}$')` |
| `chk_vessel_tonnage` | `(net_tonnage IS NULL OR net_tonnage > 0) and (moulded_depth IS NULL OR moulded_depth > 0)` |

**Foreign keys**

| Constraint | Column(s) | References | Rules |
|---|---|---|---|
| `fk_vessel_type` | vessel_type_id | [`vessel_type`](#vessel_type) (id) |  |

**Referenced by:** [`berthing_request`](#berthing_request).vessel_id, [`baplie_file`](#baplie_file).vessel_id

---
