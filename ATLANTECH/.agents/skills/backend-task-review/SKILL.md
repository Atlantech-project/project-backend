---
name: backend-task-review
description: Review an ATLANTECH backend task's branch against its plan, the team contracts, the decisions, the database schema and the rules in the reference documents, and report what is missing, wrong or in conflict. Use when the user says "review task X", "راجع التاسك", before opening a PR, or when backend-task-execute finishes. Works from docs/tasks/<ID>.md and the branch diff against develop.
---

# Backend task review

Checks that a task's code does what was agreed and nothing the other teams don't expect. It reports; it does not fix unless the user asks.

## 1. Gather

1. Read `docs/tasks/<ID>.md`: done-when list, design, steps, other-team tables, answers in section 8.
2. Run `node docs/reference/sync-check.mjs`. If a cited source changed after the plan, review against the newer text and say so.
3. Fetch, then get the change: `git diff origin/develop...HEAD` and `git log origin/develop..HEAD`. Read every changed file in full, not only the hunks.
4. Activate `laravel-best-practices` and `testing-best-practices` for the code-quality pass, and follow `context-rules`: check each rule against the plan's cited `file:lines` and the split files from `docs/reference/lookup.mjs`, not against whole documents.
5. Run the plan's tests. A failing test is a Blocker.

## 2. Check

Go through every area below. For each finding, note the code file and line and quote the source it breaks with its `file:lines` (from the lookup), never a title or summary. Judge against the **Source of truth** rules in `docs/reference/README.md`: where a document is missing, the Master Roadmap decides; where present documents disagree, contracts and Closed decisions first, then the roadmap, then team documents; PROPOSED text never wins.

**Plan**
- Every done-when line is met and proven by a test or demo; every step in the log; nothing outside the scope without a recorded reason.
- Every answer in section 8 is reflected in the code.

**Contracts** (`contracts/ATLANTECH_Contracts.md`)
- CT-01: `/api/v1`, UTC ISO 8601, RFC 9457 body with `code` and `message_key`, only the listed status codes, `version` returned and checked (409), pagination shape, mobile headers and `Idempotency-Key`.
- CT-02: OpenAPI updated in this branch for every endpoint added or changed, matching the real request, responses and errors.
- CT-03/04: sign-in flows and payloads. CT-05/06: only listed event names and shapes; events carry IDs, not data. CT-07: sync results and reason codes.
- CT-08 to CT-14: queue names, envelope fields, `contract_version`, payload fields and `reason` / `trigger_type` values exactly as written; push after commit.
- CT-15: the backend writes only the columns it owns; AI-owned result rows are read, not written (except the confirm / reject fields the table allows).
- CT-17/21: `schema.sql` and the status vocabulary updated when migrations or enums change; enum values match the SQL names.
- CT-19/23: mock scenarios and readiness pack updated when the feature reaches the FRR.

**AI dependencies** (`ai/ATLANTECH_AI_BACKEND_DEPENDENCIES.md`)
- For each `AIBD-nnn` the task links to: the prerequisites, failure behaviour and table ownership in §2.2 and §4 hold, the workflow duties in §5 are done, and the matching lines of the §7 acceptance checklist would pass. Where the document says PROPOSED, the code follows the approved contract or decision, not the proposal.

**Decisions** (`decisions/ATLANTECH_Decisions.md`)
- The code follows every Closed decision on the topic. Nothing assumes an answer to an Open one unless section 8 records the user's choice.

**Database** (`database/`)
- Migrations match `PortSync.sql` / `SaaS.sql` after the edit sheet, DDL fixes and reports A/B: table and column names, types, lengths, nullability, defaults, enums, foreign keys and delete rules, unique keys, indexes, CHECKs, generated columns.
- Models: relations, casts to PHP enums, fillable or guarded, no column the table lacks.
- `Coding_Notes_and_Mapping.md`: derived values computed, not stored (§2); rules the DB does not enforce are enforced (§3); row version done in code (§4); transaction boundaries and locks as listed (§5); things to watch (§6).

**Behaviour**
- Status changes go through the one transition service and follow the state diagram (`SM-n`); history and audit rows are written in the same transaction.
- The write chain runs in the fixed order (P2-D2); roles and terminal scope match `Users & Actors.md`; licence mode gives 423 except on the allowlist; refused writes are audited as `REJECTED_ATTEMPT`.
- Notifications, SSE events, WebSocket messages and AI jobs are sent only after commit.
- Behaviour matches the FR text and the sequence or activity diagram for the flow; NFR targets that apply (audit, performance) are not broken.

**Tests and code quality**
- Each error code, each role refusal, the licence-mode case and the 409 path have a test. Tests use factories and assert the database state, not only the response.
- No N+1 queries, mass-assignment holes or missing authorisation; Pint clean; follows `CLAUDE.md` and sibling-file conventions.

**Other teams**
- Everything in the plan's "this task changes something other teams use" table is published (OpenAPI, event list, vocabulary, schema, mock) and nothing else changed for them without being listed.

## 3. Report

Write the result into section 10 of the plan:

| Severity | Meaning |
| --- | --- |
| **Blocker** | Breaks a contract, a Closed decision, the schema, a test, security or data integrity. Must be fixed before the PR |
| **Must fix** | Done-when line not met, rule from the documents not enforced, missing test for an error path |
| **Should fix** | Quality or convention issue with no behaviour change |
| **Note** | Observation, follow-up for a later task, or something to tell another team |

List conflicts separately: places where the code is right by one source and wrong by another, or where the sources disagree with each other. Ask the user to decide each conflict with the AskUserQuestion tool and record the decision in section 7 of the plan. A conflict involving another team's contract or an Open decision goes to that team too; say who.

End with a short verdict to the user: **Ready for PR**, or **Not ready**, with the count per severity and the first things to fix. Offer to fix Must-fix and Should-fix items (re-using the modes from `backend-task-execute`); after fixes, review again. When the verdict is Ready for PR, set the plan's Status to **Done** only after the user merges it.
