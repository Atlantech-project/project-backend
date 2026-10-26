<!-- Generated from docs/reference/database/PortSync.sql lines 446-454 by build-index.mjs. Do not edit: edit the source and rebuild. -->
CREATE TABLE `berthing_request_cargo` (
  `berthing_request_id` bigint NOT NULL,
  `terminal_id` bigint NOT NULL COMMENT 'Copy of the request terminal, enforced by composite FK',
  `cargo_type_id` bigint NOT NULL,
  `operation` ENUM ('DISCHARGE', 'LOAD') NOT NULL,
  `quantity` decimal(14,2) NOT NULL,
  `quantity_unit` ENUM ('TONNES', 'CUBIC_METRES', 'UNITS') NOT NULL,
  PRIMARY KEY (`berthing_request_id`, `cargo_type_id`, `operation`)
);

<!-- Generated from docs/reference/database/PortSync.md lines 1492-1527 by build-index.mjs. Do not edit: edit the source and rebuild. -->
## `berthing_request_cargo`

> Cargo of non-container calls (container calls use the BAPLIE manifest).

| Column | Type | Null | Default | Keys | Notes |
|---|---|---|---|---|---|
| `berthing_request_id` | `bigint` | NO |  | PK · FK → `berthing_request.id, requested_terminal_id` |  |
| `terminal_id` | `bigint` | NO |  | FK → `berthing_request.id, requested_terminal_id` | Copy of the request terminal, enforced by composite FK |
| `cargo_type_id` | `bigint` | NO |  | PK · FK → `terminal_cargo_type.terminal_id, cargo_type_id` |  |
| `operation` | `ENUM(DISCHARGE, LOAD)` | NO |  | PK |  |
| `quantity` | `decimal(14,2)` | NO |  |  |  |
| `quantity_unit` | `ENUM(TONNES, CUBIC_METRES, UNITS)` | NO |  |  |  |

**Composite primary key:** `berthing_request_id`, `cargo_type_id`, `operation`

**Check constraints**

| Name | Rule |
|---|---|
| `chk_brc_quantity` | `(quantity > 0)` |

**Indexes**

| Name | Unique | Columns |
|---|---|---|
| `fk_brc_request` | No | berthing_request_id, terminal_id |
| `fk_brc_terminal_cargo` | No | terminal_id, cargo_type_id |

**Foreign keys**

| Constraint | Column(s) | References | Rules |
|---|---|---|---|
| `fk_brc_request` | berthing_request_id, terminal_id | [`berthing_request`](#berthing_request) (id, requested_terminal_id) |  |
| `fk_brc_terminal_cargo` | terminal_id, cargo_type_id | [`terminal_cargo_type`](#terminal_cargo_type) (terminal_id, cargo_type_id) |  |

---
