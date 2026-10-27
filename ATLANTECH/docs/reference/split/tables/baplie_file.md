<!-- Generated from docs/reference/database/PortSync.sql lines 472-488 by build-index.mjs. Do not edit: edit the source and rebuild. -->
CREATE TABLE `baplie_file` (
  `id` bigint PRIMARY KEY AUTO_INCREMENT,
  `vessel_id` bigint NOT NULL,
  `berthing_request_id` bigint NOT NULL COMMENT 'Must be a request for the same vessel (composite FK below)',
  `uploaded_by` bigint NOT NULL,
  `file_hash_sha256` char(64) NOT NULL COMMENT 'Not globally unique: the same file may be uploaded for a different request',
  `live_hash_key` char(64) COMMENT 'GENERATED (stored): IF(status = WITHDRAWN, NULL, file_hash_sha256)',
  `storage_path` varchar(255) NOT NULL,
  `parse_status` ENUM ('PENDING', 'PARSED', 'FAILED', 'QUARANTINED') NOT NULL DEFAULT 'PENDING',
  `status` ENUM ('STAGED', 'ACTIVE', 'REPLACED', 'WITHDRAWN') NOT NULL DEFAULT 'STAGED' COMMENT 'STAGED = uploaded, not yet in use; ACTIVE = in use; REPLACED = replaced by a revision; WITHDRAWN = uploaded by mistake',
  `active_request_key` bigint UNIQUE COMMENT 'GENERATED (stored): IF(status = ACTIVE, berthing_request_id, NULL). One ACTIVE file per request.',
  `staged_payload` json COMMENT 'Quarantine: extracted data of a QUARANTINED file, kept so staff can correct it. Commercially sensitive (terminal isolation rules)',
  `is_manually_corrected` boolean NOT NULL DEFAULT false,
  `corrected_by` bigint,
  `corrected_at` datetime,
  `uploaded_at` datetime NOT NULL DEFAULT (CURRENT_TIMESTAMP)
);

<!-- Generated from docs/reference/database/PortSync.md lines 1579-1632 by build-index.mjs. Do not edit: edit the source and rebuild. -->
## `baplie_file`

> Exactly one ACTIVE file per request (active_request_key). Replacing a revision: old ACTIVE -> REPLACED, new -> ACTIVE, in one transaction. Mistaken upload -> WITHDRAWN.
>
> Diagnostics rows live in baplie_parse_issue (no JSON copy here).

| Column | Type | Null | Default | Keys | Notes |
|---|---|---|---|---|---|
| `id` | `bigint` | NO |  | PK · AI |  |
| `vessel_id` | `bigint` | NO |  | FK → `berthing_request.id, vessel_id` |  |
| `berthing_request_id` | `bigint` | NO |  | FK → `berthing_request.id, vessel_id` | Must be a request for the same vessel (composite FK below) |
| `uploaded_by` | `bigint` | NO |  | FK → `user.id` |  |
| `file_hash_sha256` | `char(64)` | NO |  |  | Not globally unique: the same file may be uploaded for a different request |
| `live_hash_key` | `char(64)` | YES |  |  | GENERATED (stored): IF(status = WITHDRAWN, NULL, file_hash_sha256) |
| `storage_path` | `varchar(255)` | NO |  |  |  |
| `parse_status` | `ENUM(PENDING, PARSED, FAILED, QUARANTINED)` | NO | `'PENDING'` |  |  |
| `status` | `ENUM(STAGED, ACTIVE, REPLACED, WITHDRAWN)` | NO | `'STAGED'` |  | STAGED = uploaded, not yet in use; ACTIVE = in use; REPLACED = replaced by a revision; WITHDRAWN = uploaded by mistake |
| `active_request_key` | `bigint` | YES |  | UQ | GENERATED (stored): IF(status = ACTIVE, berthing_request_id, NULL). One ACTIVE file per request. |
| `staged_payload` | `json` | YES |  |  | Quarantine: extracted data of a QUARANTINED file, kept so staff can correct it. Commercially sensitive (terminal isolation rules) |
| `is_manually_corrected` | `boolean` | NO | `false` |  |  |
| `corrected_by` | `bigint` | YES |  | FK → `user.id` |  |
| `corrected_at` | `datetime` | YES |  |  |  |
| `uploaded_at` | `datetime` | NO | `CURRENT_TIMESTAMP` |  |  |

**Check constraints**

| Name | Rule |
|---|---|
| `chk_bf_hash` | `regexp_like(file_hash_sha256, '^[0-9a-f]{64}$', 'c')` |
| `chk_bf_correction` | `(is_manually_corrected = false) OR (corrected_by IS NOT NULL AND corrected_at IS NOT NULL)` |

**Indexes**

| Name | Unique | Columns |
|---|---|---|
| `fk_bf_request` | No | berthing_request_id, vessel_id |
| `fk_bf_user` | No | uploaded_by |
| `fk_bf_vessel` | No | vessel_id |
| `fk_bf_corrected_by` | No | corrected_by |
| `idx_bf_request` | No | berthing_request_id, uploaded_at |
| `uq_bf_request_live_hash` | Yes | berthing_request_id, live_hash_key |

**Foreign keys**

| Constraint | Column(s) | References | Rules |
|---|---|---|---|
| `fk_bf_request` | berthing_request_id, vessel_id | [`berthing_request`](#berthing_request) (id, vessel_id) |  |
| `fk_bf_user` | uploaded_by | [`user`](#user) (id) |  |
| `fk_bf_vessel` | vessel_id | [`vessel`](#vessel) (id) |  |
| `fk_cb_user` | corrected_by | [`user`](#user) (id) |  |

**Referenced by:** [`baplie_parse_issue`](#baplie_parse_issue).baplie_file_id, [`container_manifest_entry`](#container_manifest_entry).baplie_file_id

---
