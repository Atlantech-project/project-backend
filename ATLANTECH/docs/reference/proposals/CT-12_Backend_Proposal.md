# CT-12 · C-04 · BAPLIE parse — Backend proposal for AI review

The Backend Team proposes the full contract below. Please reply per section with **Approved** or your change. Sections marked 🟨 touch the parser internals and are proposals only: the AI Team has the final word on them.

---

## 1) Message (queue:edi)

The schema `c04-baplie.schema.json` describes the **payload only**, referenced from `envelope.schema.json` with `if job_type = BAPLIE_PARSE` (same pattern as C-01 to C-03).

```json
{
  "contract_version": "1.0",
  "job_type": "BAPLIE_PARSE",
  "job_id": "d9a…",
  "run_id": 318,
  "enqueued_at": "2026-11-02T08:05:00Z",
  "triggered_by_user_id": 41,
  "payload": { "baplie_file_id": 205, "source": "FILE" }
}
```

| Field | Type | Rule |
| --- | --- | --- |
| `baplie_file_id` | integer ≥ 1 | Required |
| `source` | `FILE` · `STAGED_PAYLOAD` | Required. `FILE` = parse the uploaded file. `STAGED_PAYLOAD` = re-validate and commit the staff-corrected `staged_payload` of a QUARANTINED file (see §6) |

`additionalProperties: false`. `run_id` is `baplie_parse_run.id` (see §2), not `baplie_file_id`.

---

## 2) Run tracking: new `baplie_parse_run` table (Backend adds it through CT-17)

Same reasoning and pattern as `yard_run` in CT-11: business status (`parse_status`) stays apart from the technical run state, and every attempt keeps its history.

```sql
CREATE TABLE `baplie_parse_run` (
  `id` bigint PRIMARY KEY AUTO_INCREMENT,
  `baplie_file_id` bigint NOT NULL,
  `source` ENUM ('FILE', 'STAGED_PAYLOAD') NOT NULL DEFAULT 'FILE',
  `triggered_by` bigint NULL,
  `status` ENUM ('QUEUED', 'RUNNING', 'DONE', 'FAILED', 'REPLACED') NOT NULL DEFAULT 'QUEUED',
  `outcome` ENUM ('PARSED', 'QUARANTINED', 'REJECTED') NULL,
  `error_category` ENUM ('INVALID_ENVELOPE', 'PRECONDITION_FAILED', 'DATA_UNAVAILABLE',
                         'CONFIGURATION_ERROR', 'TIMEOUT', 'INTERNAL_ERROR') NULL,
  `retry_count` tinyint NOT NULL DEFAULT 0,
  `container_count` int NULL,
  `result_detail` json NULL,
  `worker_version` varchar(40) NULL,
  `queued_at` datetime NOT NULL DEFAULT (CURRENT_TIMESTAMP),
  `started_at` datetime NULL,
  `finished_at` datetime NULL,
  `active_key` bigint GENERATED ALWAYS AS
    (IF(status IN ('QUEUED', 'RUNNING'), baplie_file_id, NULL)) STORED,
  UNIQUE KEY `uq_bpr_active` (`active_key`),
  KEY `idx_bpr_file` (`baplie_file_id`),
  CONSTRAINT `fk_bpr_file` FOREIGN KEY (`baplie_file_id`) REFERENCES `baplie_file` (`id`),
  CONSTRAINT `fk_bpr_user` FOREIGN KEY (`triggered_by`) REFERENCES `user` (`id`),
  CONSTRAINT `chk_bpr_finished` CHECK (status IN ('QUEUED', 'RUNNING', 'REPLACED') OR finished_at IS NOT NULL),
  CONSTRAINT `chk_bpr_outcome` CHECK (
       (status = 'DONE' AND outcome IS NOT NULL)
    OR (status <> 'DONE' AND outcome IS NULL)),
  CONSTRAINT `chk_bpr_error` CHECK ((status = 'FAILED') = (error_category IS NOT NULL))
);
```

**Run flow:**

| Step | Who | What |
| --- | --- | --- |
| 1 | Backend | Inserts `baplie_parse_run` (QUEUED) and RPUSH with `run_id`, in one transaction with the upload (or the correction save) |
| 2 | AI | Claims: `UPDATE … SET status='RUNNING' WHERE id=? AND status='QUEUED'`. 0 rows → ignore |
| 3 | AI | One all-or-nothing transaction: writes data (§4, §7, §8), `baplie_parse_issue` rows, `baplie_file.parse_status` (and `staged_payload` when QUARANTINED), and sets the run DONE or FAILED |
| 4 | AI | Sends C-06 with `job_type = BAPLIE_PARSE` and the outcome |

---

## 3) Who changes `baplie_file.status`: the Backend

The worker writes **only** `parse_status` (and `staged_payload`). It never touches `status`.

On C-06 with outcome `PARSED`, the Backend in one transaction sets the new file `ACTIVE` and the previous ACTIVE file `REPLACED`. On `QUARANTINED`, `REJECTED` or a FAILED run, the file stays `STAGED` and the previous ACTIVE file stays ACTIVE.

---

## 4) 🟨 Outcomes and rules

| Run result | `parse_status` | Meaning | What the user does |
| --- | --- | --- | --- |
| DONE · `PARSED` | `PARSED` | Data committed. May carry `WARNING` issues | Nothing, or fix incomplete lines |
| DONE · `QUARANTINED` | `QUARANTINED` | Readable BAPLIE with at least one `ERROR` issue. Nothing committed; extracted data in `staged_payload` | Correct on the quarantine screen |
| DONE · `REJECTED` | `FAILED` | Not a usable BAPLIE: not UN/EDIFACT, not a BAPLIE message, unsupported version, or corrupted | Upload a new file |
| FAILED · `error_category` | `FAILED` | Technical error (timeout, DB unavailable, crash). Retried once (DEC-04) | Retry, or contact support |

**Proposed ERROR issues (→ QUARANTINED):**

- The IMO in the file does not match `vessel.imo_number` of the request.
- The same container number appears twice in the file.
- Unknown ISO size-type code (no `container_size_type` row).
- `imdg_class` not found in `imdg_class`.
- Malformed value (non-numeric weight, invalid UN/LOCODE, temperature outside −40 … 40).
- Reefer container without a set temperature.

**Proposed WARNING issues (→ still PARSED):**

- Container number check digit is wrong (matches the DB comment on `container.container_number`).
- `full_empty` missing → written as NULL.
- `port_of_discharge` missing → written as NULL.
- Vessel name in the file differs from `vessel.name`.
- An existing container has a different size type (see §7).

The Backend blocks customs approval and yard recommendations while any line has a NULL `full_empty` or a NULL `port_of_discharge` (already agreed in CT-11).

**Please send us the list of supported BAPLIE versions** (for example D.95B / SMDG 2.2 and 3.1).

---

## 5) Where failure reasons live

- Parse problems (including `REJECTED` reasons) → `baplie_parse_issue` rows (`severity`, `segment_ref`, `message`). No column is added to `baplie_file`.
- Technical failures → `baplie_parse_run.error_category` and `result_detail.failure_reason = { "error_category", "message" }`, the same shape as C-02 and C-03. C-06 carries the same `error_category`.

**`result_detail` example:**

```json
{
  "container_count": 1840,
  "discharged_here_count": 612,
  "issue_counts": { "ERROR": 0, "WARNING": 3 },
  "vessel_fields_filled": ["call_sign"],
  "size_class": "MEDIUM",
  "parse_time_ms": 4210,
  "failure_reason": null
}
```

---

## 6) 🟨 Quarantine and correction

**`staged_payload` shape (written by the worker, edited on the quarantine screen):**

```json
{
  "format_version": "1.0",
  "vessel": { "imo_number": "9811000", "name": "MSC EXAMPLE", "call_sign": "3FAB7" },
  "lines": [
    {
      "line_no": 1,
      "segment_ref": "LOC+147+0010282",
      "container_number": "MSCU1234565",
      "iso_size_type": "45G1",
      "full_empty": "FULL",
      "weight_kg": 24500,
      "imdg_class": null,
      "oog": { "front_cm": null, "back_cm": null, "right_cm": null, "left_cm": null, "height_cm": null },
      "reefer_set_temperature": null,
      "stowage": { "bay": 1, "row": 2, "tier": 82 },
      "port_of_load": "CNSHA",
      "port_of_discharge": "EGDAM",
      "issues": [ { "severity": "ERROR", "code": "UNKNOWN_SIZE_TYPE", "message": "Size type 45X9 not found" } ]
    }
  ]
}
```

**Correction flow:**

1. Staff edit lines on the quarantine screen. The Backend saves the edited `staged_payload` and sets `is_manually_corrected`, `corrected_by` and `corrected_at` (audited).
2. The Backend enqueues a new run with `source = STAGED_PAYLOAD`.
3. The worker re-validates `staged_payload` with the **same rules** as §4 (no raw-file parse). Still has ERROR issues → QUARANTINED again with refreshed issues. Clean → commits exactly like a PARSED file.

So all commit logic for `container` / `container_manifest_entry` / `vessel` stays in the worker. The Backend never writes those rows from a BAPLIE. This also answers the "BAPLIE correction" part of DEC-03.

`staged_payload` is commercially sensitive: the Backend shows it only to the owning agent and the terminal (§42 isolation).

---

## 7) 🟨 `container` rows

- Upsert by `container_number` (uppercase, no spaces).
- New number → insert with `size_type_id`.
- Existing number → no update. If the size type in the file differs, keep the stored value and add a WARNING issue.
- One `container_manifest_entry` per line, linked to this `baplie_file_id`.

---

## 8) 🟨 `vessel` rows

- The worker fills **only NULL** columns, and only `call_sign` and `flag_state`. It never overwrites a non-NULL value.
- `imo_number` mismatch → ERROR (→ QUARANTINED). `name` mismatch → WARNING only.
- Filled columns are listed in `result_detail.vessel_fields_filled`.

---

## 9) 🟨 Performance size classes (NFR-PERF-2)

| Class | Containers in file | Target |
| --- | --- | --- |
| Small | ≤ 500 | 3 s |
| Medium | ≤ 5,000 | 10 s |
| Large | ≤ 20,000 | 25 s |
| Over 20,000 | — | Processed with no target; `size_class = OVERSIZE` in `result_detail` |

---

## 10) Backend rules (for your information)

- **Upload:** the Backend stores the file and its SHA-256, inserts `baplie_file` (`STAGED`, `PENDING`) and `baplie_parse_run`, then RPUSH. `triggered_by_user_id = uploaded_by`.
- **Upload limits:** max file size 10 MB; extensions `.edi` · `.txt` · `.baplie`.
- **Duplicate file:** same hash for the same request → 409 at upload (`uq_bf_request_live_hash`). It never reaches the worker.
- **One parse at a time per request:** no new upload for a request while one of its files is `PENDING` → 409.
- **After a revision becomes ACTIVE:** the Backend regenerates the yard recommendation if it is not yet accepted (CT-11) and re-checks the incomplete-line rule.
- **Retries:** technical failures are retried once (DEC-04). `REJECTED` and `QUARANTINED` are never retried.
- The Backend validates every message against the schema before RPUSH.

**Impact on other contracts:** CT-17 adds `baplie_parse_run`. CT-13 / DEC-03: BAPLIE correction validation is done by the worker through `source = STAGED_PAYLOAD`. CT-14: C-06 for BAPLIE carries `outcome` = `PARSED` · `QUARANTINED` · `REJECTED`, or `error_category` on a FAILED run.
