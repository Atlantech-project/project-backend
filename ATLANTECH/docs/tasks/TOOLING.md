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
