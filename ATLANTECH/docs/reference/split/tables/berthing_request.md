<!-- Generated from docs/reference/database/PortSync.sql lines 423-444 by build-index.mjs. Do not edit: edit the source and rebuild. -->
CREATE TABLE `berthing_request` (
  `id` bigint PRIMARY KEY AUTO_INCREMENT,
  `vessel_id` bigint NOT NULL,
  `requested_terminal_id` bigint NOT NULL,
  `source` ENUM ('MANUAL', 'EXTERNAL') NOT NULL,
  `external_system_id` bigint COMMENT 'Required when EXTERNAL. Optional for MANUAL: staff enter the EGMSW trip number (seed external_system row EGMSW)',
  `external_reference` varchar(100) COMMENT 'Pairs with external_system_id (both set or both empty for MANUAL); unique per external system',
  `submitted_by` bigint COMMENT 'Required when MANUAL; NULL when ingested by the external adapter',
  `requested_eta` datetime NOT NULL COMMENT 'UTC',
  `arrival_draft` decimal(5,2) NOT NULL COMMENT 'm, this call; backend checks > 0 and <= vessel.max_draft',
  `departure_draft` decimal(5,2) COMMENT 'm, expected draft on departure. Nullable (not known at arrival)',
  `previous_port` char(5) COMMENT 'UN/LOCODE, nullable',
  `next_port` char(5) COMMENT 'UN/LOCODE, nullable',
  `draft_source` ENUM ('MANUAL', 'EGMSW_FEED', 'REGISTRY') NOT NULL DEFAULT 'MANUAL',
  `eta_source` ENUM ('MANUAL', 'EGMSW_FEED', 'REGISTRY') NOT NULL DEFAULT 'MANUAL',
  `ports_source` ENUM ('MANUAL', 'EGMSW_FEED', 'REGISTRY') NOT NULL DEFAULT 'MANUAL',
  `block_coefficient` decimal(4,3) NOT NULL COMMENT 'Cb entered on the request form (D-44)',
  `gross_tonnage` int NOT NULL COMMENT 'GT, entered on the request form; input to the AI tug formula (OQ-15-1)',
  `deadweight_tonnes` int NOT NULL COMMENT 'DWT (tonnes), entered on the request form; input to the AI tug formula (OQ-15-1)',
  `status` ENUM ('SUBMITTED', 'NEEDS_REVISION', 'PRELIMINARY_APPROVED', 'CUSTOMS_APPROVED', 'ENTRY_WINDOW_CONFIRMED', 'RECOMMENDATION_GENERATED', 'PLAN_CONFIRMED', 'PLAN_CANCELLED', 'COMPLETED', 'REJECTED', 'CANCELLED') NOT NULL DEFAULT 'SUBMITTED',
  `created_at` datetime NOT NULL DEFAULT (CURRENT_TIMESTAMP)
);

<!-- Generated from docs/reference/database/PortSync.md lines 1429-1490 by build-index.mjs. Do not edit: edit the source and rebuild. -->
## `berthing_request`

> status = current state; history in berthing_request_status_history (same transaction). Return for revision only from SUBMITTED; ENTRY_WINDOW_CONFIRMED may be reopened to CUSTOMS_APPROVED while no plan is confirmed. Cancel only before Preliminary Approval.

| Column | Type | Null | Default | Keys | Notes |
|---|---|---|---|---|---|
| `id` | `bigint` | NO |  | PK · AI |  |
| `vessel_id` | `bigint` | NO |  | FK → `vessel.id` |  |
| `requested_terminal_id` | `bigint` | NO |  | FK → `terminal.id` |  |
| `source` | `ENUM(MANUAL, EXTERNAL)` | NO |  |  |  |
| `external_system_id` | `bigint` | YES |  | FK → `external_system.id` | Required when EXTERNAL. Optional for MANUAL: staff enter the EGMSW trip number (seed external_system row EGMSW) |
| `external_reference` | `varchar(100)` | YES |  |  | Pairs with external_system_id (both set or both empty for MANUAL); unique per external system |
| `submitted_by` | `bigint` | YES |  | FK → `user.id` | Required when MANUAL; NULL when ingested by the external adapter |
| `requested_eta` | `datetime` | NO |  |  | UTC |
| `arrival_draft` | `decimal(5,2)` | NO |  |  | m, this call; backend checks > 0 and <= vessel.max_draft |
| `departure_draft` | `decimal(5,2)` | YES |  |  | m, expected draft on departure. Nullable (not known at arrival) |
| `previous_port` | `char(5)` | YES |  |  | UN/LOCODE, nullable |
| `next_port` | `char(5)` | YES |  |  | UN/LOCODE, nullable |
| `draft_source` | `ENUM(MANUAL, EGMSW_FEED, REGISTRY)` | NO | `'MANUAL'` |  |  |
| `eta_source` | `ENUM(MANUAL, EGMSW_FEED, REGISTRY)` | NO | `'MANUAL'` |  |  |
| `ports_source` | `ENUM(MANUAL, EGMSW_FEED, REGISTRY)` | NO | `'MANUAL'` |  |  |
| `block_coefficient` | `decimal(4,3)` | NO |  |  | Cb entered on the request form (D-44) |
| `gross_tonnage` | `int` | NO |  |  | GT, entered on the request form; input to the AI tug formula (OQ-15-1) |
| `deadweight_tonnes` | `int` | NO |  |  | DWT (tonnes), entered on the request form; input to the AI tug formula (OQ-15-1) |
| `status` | `ENUM(SUBMITTED, NEEDS_REVISION, PRELIMINARY_APPROVED, CUSTOMS_APPROVED, ENTRY_WINDOW_CONFIRMED, RECOMMENDATION_GENERATED, PLAN_CONFIRMED, PLAN_CANCELLED, COMPLETED, REJECTED, CANCELLED)` | NO | `'SUBMITTED'` |  |  |
| `created_at` | `datetime` | NO | `CURRENT_TIMESTAMP` |  |  |

**Check constraints**

| Name | Rule |
|---|---|
| `chk_br_cb` | `(block_coefficient > 0.3) and (block_coefficient < 1.0)` |
| `chk_br_tonnage` | `(gross_tonnage > 0) and (deadweight_tonnes > 0)` |
| `chk_br_source` | `(source = 'EXTERNAL' AND external_system_id IS NOT NULL AND external_reference IS NOT NULL) OR (source = 'MANUAL' AND submitted_by IS NOT NULL AND (external_system_id IS NULL) = (external_reference IS NULL))` |
| `chk_br_departure_draft` | `departure_draft IS NULL OR departure_draft > 0` |
| `chk_br_ports` | `(previous_port IS NULL OR regexp_like(previous_port, '^[A-Z]{2}[A-Z2-9]{3}$')) AND (next_port IS NULL OR regexp_like(next_port, '^[A-Z]{2}[A-Z2-9]{3}$'))` |

**Indexes**

| Name | Unique | Columns |
|---|---|---|
| `fk_br_user` | No | submitted_by |
| `fk_br_vessel` | No | vessel_id |
| `idx_br_status` | No | status |
| `idx_br_terminal_status` | No | requested_terminal_id, status |
| `uq_br_external_ref` | Yes | external_system_id, external_reference |
| `uq_br_id_terminal` | Yes | id, requested_terminal_id |
| `uq_br_id_vessel` | Yes | id, vessel_id |
| `idx_br_vessel_eta` | No | vessel_id, requested_eta |

**Foreign keys**

| Constraint | Column(s) | References | Rules |
|---|---|---|---|
| `fk_br_external` | external_system_id | [`external_system`](#external_system) (id) |  |
| `fk_br_terminal` | requested_terminal_id | [`terminal`](#terminal) (id) |  |
| `fk_br_user` | submitted_by | [`user`](#user) (id) |  |
| `fk_br_vessel` | vessel_id | [`vessel`](#vessel) (id) |  |

**Referenced by:** [`part1_run`](#part1_run).berthing_request_id, [`berthing_request_cargo`](#berthing_request_cargo).berthing_request_id, terminal_id, [`berthing_request_status_history`](#berthing_request_status_history).berthing_request_id, [`baplie_file`](#baplie_file).berthing_request_id, vessel_id, [`forecast_run`](#forecast_run).berthing_request_id, [`entry_window_proposal`](#entry_window_proposal).berthing_request_id, [`recommendation`](#recommendation).berthing_request_id, terminal_id

---
