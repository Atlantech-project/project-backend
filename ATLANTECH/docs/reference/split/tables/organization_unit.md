<!-- Generated from docs/reference/database/PortSync.sql lines 96-101 by build-index.mjs. Do not edit: edit the source and rebuild. -->
CREATE TABLE `organization_unit` (
  `id` bigint PRIMARY KEY AUTO_INCREMENT,
  `unit_type` ENUM ('HARBOUR_AUTHORITY', 'MARINE_SERVICES', 'TERMINAL') NOT NULL,
  `terminal_id` bigint COMMENT 'Set only for TERMINAL units; display name comes from terminal.name',
  `single_key` bigint COMMENT 'GENERATED (stored): coalesce(terminal_id,0)'
);

<!-- Generated from docs/reference/database/PortSync.md lines 343-376 by build-index.mjs. Do not edit: edit the source and rebuild. -->
## `organization_unit`

> Where a user works: the Harbour Authority, Marine Services, or one terminal. A TERMINAL row is created with each terminal.

| Column | Type | Null | Default | Keys | Notes |
|---|---|---|---|---|---|
| `id` | `bigint` | NO |  | PK · AI |  |
| `unit_type` | `ENUM(HARBOUR_AUTHORITY, MARINE_SERVICES, TERMINAL)` | NO |  |  |  |
| `terminal_id` | `bigint` | YES |  | FK → `terminal.id` | Set only for TERMINAL units; display name comes from terminal.name |
| `single_key` | `bigint` | YES |  |  | GENERATED (stored): coalesce(terminal_id,0) |

**Check constraints**

| Name | Rule |
|---|---|
| `chk_org_unit_terminal` | `((unit_type = 'TERMINAL') = (terminal_id is not null))` |

**Indexes**

| Name | Unique | Columns |
|---|---|---|
| `fk_org_unit_terminal` | No | terminal_id |
| `uq_org_unit_id_type` | Yes | id, unit_type |
| `uq_org_unit_type_key` | Yes | unit_type, single_key |

**Foreign keys**

| Constraint | Column(s) | References | Rules |
|---|---|---|---|
| `fk_org_unit_terminal` | terminal_id | [`terminal`](#terminal) (id) |  |

**Referenced by:** [`user_role_assignment`](#user_role_assignment).organization_unit_id, unit_type

---
