<!-- Generated from docs/reference/database/PortSync.sql lines 114-123 by build-index.mjs. Do not edit: edit the source and rebuild. -->
CREATE TABLE `user_role_assignment` (
  `id` bigint PRIMARY KEY AUTO_INCREMENT,
  `user_id` bigint NOT NULL,
  `role` ENUM ('PORT_GM', 'PORT_STAFF', 'MARINE_SAFETY_OFFICER', 'PILOTAGE_MANAGER', 'PILOTAGE_OFFICER', 'MARINE_PILOT', 'TUG_MANAGER', 'TUG_OFFICER', 'TUG_MASTER', 'TERMINAL_ADMIN', 'TERMINAL_OFFICER', 'TERMINAL_SUPERVISOR') NOT NULL,
  `organization_unit_id` bigint NOT NULL COMMENT 'Terminal X, Marine Services, or Harbour Authority. Never empty.',
  `unit_type` ENUM ('HARBOUR_AUTHORITY', 'MARINE_SERVICES', 'TERMINAL') NOT NULL COMMENT 'Kept equal to organization_unit.unit_type by fk_ura_unit',
  `active_from` datetime NOT NULL DEFAULT (CURRENT_TIMESTAMP),
  `active_to` datetime COMMENT 'NULL = role active; set when the role is ended (never deleted)',
  `active_assignment_key` tinyint COMMENT 'GENERATED (stored): IF(active_to IS NULL, 1, NULL)'
);

<!-- Generated from docs/reference/database/PortSync.md lines 401-438 by build-index.mjs. Do not edit: edit the source and rebuild. -->
## `user_role_assignment`

> Who holds which role, in which organization unit. Roles are a fixed ENUM (P3-04).

| Column | Type | Null | Default | Keys | Notes |
|---|---|---|---|---|---|
| `id` | `bigint` | NO |  | PK · AI |  |
| `user_id` | `bigint` | NO |  | FK → `user.id` |  |
| `role` | `ENUM(PORT_GM, PORT_STAFF, MARINE_SAFETY_OFFICER, PILOTAGE_MANAGER, PILOTAGE_OFFICER, MARINE_PILOT, TUG_MANAGER, TUG_OFFICER, TUG_MASTER, TERMINAL_ADMIN, TERMINAL_OFFICER, TERMINAL_SUPERVISOR)` | NO |  |  |  |
| `organization_unit_id` | `bigint` | NO |  | FK → `organization_unit.id, unit_type` | Terminal X, Marine Services, or Harbour Authority. Never empty. |
| `unit_type` | `ENUM(HARBOUR_AUTHORITY, MARINE_SERVICES, TERMINAL)` | NO |  | FK → `organization_unit.id, unit_type` | Kept equal to organization_unit.unit_type by fk_ura_unit |
| `active_from` | `datetime` | NO | `CURRENT_TIMESTAMP` |  |  |
| `active_to` | `datetime` | YES |  |  | NULL = role active; set when the role is ended (never deleted) |
| `active_assignment_key` | `tinyint` | YES |  |  | GENERATED (stored): IF(active_to IS NULL, 1, NULL) |

**Check constraints**

| Name | Rule |
|---|---|
| `chk_ura_period` | `(active_to IS NULL OR active_to > active_from)` |
| `chk_ura_role_matches_unit` | `(((unit_type = 'HARBOUR_AUTHORITY') and (role in ('PORT_GM','PORT_STAFF','MARINE_SAFETY_OFFICER'))) or ((unit_type = 'MARINE_SERVICES') and (role in ('PILOTAGE_MANAGER','PILOTAGE_OFFICER','MARINE_PILOT','TUG_MANAGER','TUG_OFFICER','TUG_MASTER'))) or ((unit_type = 'TERMINAL') and (role in ('TERMINAL_ADMIN','TERMINAL_OFFICER','TERMINAL_SUPERVISOR'))))` |

**Indexes**

| Name | Unique | Columns |
|---|---|---|
| `fk_ura_unit` | No | organization_unit_id, unit_type |
| `idx_ura_unit` | No | organization_unit_id |
| `uq_ura_active` | Yes | user_id, role, organization_unit_id, active_assignment_key |

**Foreign keys**

| Constraint | Column(s) | References | Rules |
|---|---|---|---|
| `fk_ura_unit` | organization_unit_id, unit_type | [`organization_unit`](#organization_unit) (id, unit_type) |  |
| `fk_ura_user` | user_id | [`user`](#user) (id) |  |

---
