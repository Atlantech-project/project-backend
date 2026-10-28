# CT-15-edi-retry-count · Grant `w_edi` UPDATE on `baplie_parse_runs.retry_count`

| | |
| --- | --- |
| Status | Done |
| Phase / package | Follow-up of P1-B1 · B Database and models |
| Sprint · feature | Sprint 1 · FT-01 Platform foundation and contracts |
| Effort (plan) | 0.1 day |
| Branch | `feature/CT-15-edi-retry-count` |
| Depends on | P1-B1 (merged to `develop`, PR #6) |
| Blocked by | nothing |
| Unblocks | the Data Ownership Matrix (`contracts/ai/table-ownership.md`, CT-15 output) |

## 1. What is asked

Found while building the Data Ownership Matrix: CT-15 v1.1 lists `retry_count` among the AI columns of `baplie_parse_runs`, but the DEC-08 final grants (and so `database/grants/workers.sql`) do not grant it, so `w_edi` would be refused when it counts a retry.

**Done when:**

- [x] `r_edi` has UPDATE on `baplie_parse_runs.retry_count`. *Proven: `WorkerGrantsTest` "the BAPLIE worker counts its own retries (CT-15)".*
- [x] `w_edi` still cannot update any other column of `baplie_parse_runs` (e.g. `triggered_by`). *Same test.*

**Out of scope:** the matrix itself (PortHub PR); the DEC-08 text (dated note added in the same PortHub PR).

## 2. Sources read

| Source | Section / ID | What it says |
| --- | --- | --- |
| `contracts/ATLANTECH_Contracts.md:1053-1091` | CT-15 v1.1 (Approved) | `baplie_parse_runs` AI columns: `status`, `outcome`, `error_category`, `retry_count`, `container_count`, `result_detail`, `worker_version`, `started_at`, `finished_at` |
| `decisions/ATLANTECH_Decisions.md:257-313` | DEC-08 (Closed) | `w_edi` UPDATE on `baplie_parse_runs` without `retry_count` |

## 3. Design

One column added to the `r_edi` UPDATE grant in `database/grants/workers.sql`. Applied with `php artisan atlantech:worker-accounts` (re-runnable: `REVOKE ALL` first). No migration.

## 4. Steps

| # | Step | Mode | Why this mode |
| --- | --- | --- | --- |
| 1 | Add `retry_count` to the grant, add the test | C | Security boundary between Laravel and the workers; approved by the lead on 2026-10-28 ("CT-15 is right: add grant") |

## 5. Other teams

| Team | What changes | Where it is published |
| --- | --- | --- |
| AI | `w_edi` may now update `baplie_parse_runs.retry_count` | This PR (`ai-impact`) · `contracts/ai/table-ownership.md` |

## 7. Conflicts and risks

| Finding | Sources that disagree | Proposed resolution | Decided by |
| --- | --- | --- | --- |
| `retry_count` on `baplie_parse_runs` | CT-15 v1.1 vs DEC-08 final grants | CT-15 wins: grant it; dated note on DEC-08 in PortHub | Lead, 2026-10-28 |

## 8. Open questions

| # | Question | Answer | Answered by / date |
| --- | --- | --- | --- |
| 1 | CT-15 or DEC-08? | CT-15 is right: add the grant | Lead, 2026-10-28 |

## 9. Progress log

- 2026-10-28 · Step 1 (C, approved in advance) · Grant changed at `workers.sql:404`; test added. Grants tests 5 passed; full suite 48 passed; Pint clean.
- 2026-10-28 · PR · Merged to `develop` at 07:19 (PR #7, `1f5399d`) with `ai-impact`. The matrix and the dated DEC-08 note merged in PortHub PR #12.
- 2026-10-28 · PortHub · `contracts/db/schema.sql` regenerated with the new grant (one line) and the `project-backend` pointer moved to `1f5399d`, on PortHub `feature/contracts-schema-sql-retry-count`.

## 10. Review

2026-10-28 · Scope is one grant column and one test. Matches CT-15 v1.1; DEC-08 gets a dated note in the PortHub PR. The PR must carry `ai-impact` and one AI Team approval (CT-17 §6). **Verdict: Ready for PR.**
