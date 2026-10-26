# TOOLING · Backend workflow tooling and token saving

| | |
| --- | --- |
| Status | In progress |
| Owner | Marwan (requested 2026-10-26) |
| Branch | `feature/workflow-tooling`, on top of `feature/P1-A1-modules-docker` (P1-A1 touches the same skill files and is not merged yet); its PR into `develop` opens after P1-A1 merges |
| Resumes afterwards | CT-01 execution (plan Ready on `feature/CT-01-plan`) |

## Goal

1. Every Claude session in this repo follows the task workflow automatically.
2. Add the missing skills and agents around the workflow.
3. Cut token use when reading the project documents without losing meaning: the original text stays the only source for decisions.

## Steps

| # | Step | Mode |
| --- | --- | --- |
| 1.1 | `docs/reference/build-index.mjs` (run by `sync-check --apply`): `docs/reference/index/index.json` with one node per ID (type, title, status, source file and line range) and the edges (depends / unblocks, task → CT / DEC / AIBD / AI, DEC → affected tasks, table → writer, AIBD → AI tasks, OD ↔ D ↔ DEC); titles and pointers only, no summaries; stale index fails the check; test that every ID in the backend tasks resolves | C |
| 1.2 | Split files from the same script: `split/decisions/DEC-xx.md`, `split/contracts/CT-xx.md`, `split/ai-deps/AIBD-xxx.md`, `split/tables/<table>.md`, `split/uml/…`; each starts with its source file and line range | B |
| 1.3 | Skill `context-rules` (index first, split files or line ranges, quote originals for decisions, one session per task with `docs/tasks/<ID>.md` as memory, Boost tools first, small output); the three backend skills point to it and use the index | C |
| 2.1 | Short "ATLANTECH workflow" section in `CLAUDE.md`, outside the `<laravel-boost-guidelines>` markers | C |
| 2.2 | Mirror the backend skills to `.agents/skills/` and `.junie/skills/`; check that the copies match | A |
| 2.3 | claude.ai Project instruction text and the list of files to upload | D (Marwan) |
| 3.1 | Agent `docs-navigator` (Haiku, read-only), answering a test query (P3-G2) from the index | C |
| 3.2 | Agents `task-reviewer`, `schema-auditor`, `contract-checker` (read-only) | B |
| 4.1–4.6 | Skills `backend-pr-prepare` (B), `api-readiness-pack` (B), `contract-change` (C), `openapi-sync` (B), `decision-close` (C), `team-handoff` (B); one commit each, short SKILL.md, long checklists in `reference/` | as listed |
| 5 | Before/after note: tokens read by `backend-task-plan` on one task with and without the index | A |

Later, not now: try Graphify or a code-graph MCP once the code is large (research first, install nothing without approval). Never add an agent that rewrites or replaces the original documents with summaries.

## Done when

- [ ] `index.json` and split files generate from sync-check; every backend-task ID resolves; a stale index fails the check.
- [ ] The three backend skills use the index and `context-rules`.
- [ ] `CLAUDE.md` section added outside the Boost markers; skill copies in `.agents` and `.junie`.
- [ ] Four agents exist and `docs-navigator` answers a test query (P3-G2) from the index only.
- [ ] Six new skills committed.
- [ ] Marwan has the Project instruction text and the upload list.
- [ ] A short before/after token note.

## Progress log

- 2026-10-26 · Plan received; modes kept as listed; branch created on top of P1-A1 (Marwan's choice). CT-01 plan saved on `feature/CT-01-plan` (11e8dde) before starting.
- 2026-10-26 · Step 1.1 done: `docs/reference/build-index.mjs` writes `docs/reference/index/index.json` (one node per line, 846 nodes: 346 tasks of all teams, 56 decisions, 26 contracts + 6 payload aliases C-01…C-06, 40 AIBD, 11 D-nn, 10 OD, 190 FR, 36 NFR, 6 SM, 8 WF, 13 features, 98 tables); titles verbatim, source file + line range, links out (depends_on, refs, affects, same_as, ai_tasks, blocks, decided_by) and in (unblocks, referenced_by, affected_by, …), CT-15 writer per table. Added `docs/reference/lookup.mjs` (one ID → ~1 KB with linked titles; `--find` by title) because reading the 260 KB index whole would defeat the purpose. `sync-check` now reports a stale index (exit 1) and rebuilds it after `--apply`. Verified: pointers land on the right lines; stale source → exit 1; planted unresolved ID → exit 1; 13 `OD-xx` are listed as "not defined in reference" (they live in the missing AI implementation plan v1.0). Found while testing: `Code Notes/ATLANTECH_Decisions.md` changed locally today (DEC-14 and DEC-15 approved): not applied yet, waiting for Marwan.
- 2026-10-26 · Step 1.1 approved. Documents synced in their own commit (ae893ae): Decisions (DEC-14, DEC-15 decision rows filled) and Contracts (CT-10 approved v1.0 under DEC-15; it had also changed locally and `--apply` copies every changed file, so it went in the same commit; the message names only Decisions). Note for Marwan: the Decisions summary table still lists DEC-14 and DEC-15 as Open.
- 2026-10-26 · Step 1.2 done: the builder also writes 295 verbatim split files under `docs/reference/split/` (26 contracts, 56 decisions, 40 AIBD = summary row + detail paragraph, 98 tables = CREATE TABLE + explained section, 6 state machines, 20 activity diagrams `AD-n`, 35 sequence diagrams `SEQ-n.m`, 3 use-case diagrams `UCD-n`, 11 UML Context sections `CTX-n`), each starting with a comment naming the source file and line range. Index now 915 nodes; nodes carry `split` paths, contract `status`, and a `decision_recorded` flag that the lookup turns into a warning when the summary still says Open. `--check` also fails on edited, missing or orphaned split files (tested).
- 2026-10-26 · Step 1.3 done: skill `.claude/skills/context-rules/SKILL.md` (35 lines: lookup first, read only the split file or line range, never whole large documents or the index, cite original lines, plan file as task memory, Boost tools first, small output). `backend-task-plan` steps 2–3 now start from `lookup.mjs` (waits-for / unblocks come from it; sources recorded as `file:lines`); `backend-task-execute` and `backend-task-review` follow `context-rules` and cite `file:lines`. `docs/reference/README.md` gained an "Index and split files" section.
- 2026-10-26 · Step 1.3 approved. Step 2.1 done: "ATLANTECH workflow" section (12 lines) appended to `CLAUDE.md` after `</laravel-boost-guidelines>` (line 150): plan → execute → review, task memory, sources and Source of truth, context-rules, module layout and order, git flow, language. It also states that plan files are exempt from Boost's "only create documentation when asked" rule. `AGENTS.md` (same Boost block, for other tools) gets the same section in step 2.2 with the skill mirrors.
- 2026-10-26 · Step 2.1 approved. Step 2.2 done: our skills (every `.claude/skills` entry not listed in `boost.json`: the three backend skills and `context-rules`) mirrored to `.agents/skills/` and `.junie/skills/`; the "ATLANTECH workflow" section mirrored into `AGENTS.md`. `sync-check.mjs` reports out-of-date copies (exit 1) and refreshes them with `--apply`; Boost's own skills are never touched. Tested: 9 stale copies found, refreshed, re-check clean.
