# ATLANTECH backend tooling kit

These are the tools Claude Code (and other coding agents) use to work on ATLANTECH backend tasks. They keep each task tied to the team documents, make the lead approve the important steps, and keep token use low without losing anything from the original text.

The Backend Team maintains this kit. The plan it was built from, and the history of each change, are in `docs/tasks/TOOLING.md`.

## Quick start

```bash
node docs/reference/sync-check.mjs          # are the reference documents, index and mirrors up to date?
node docs/reference/lookup.mjs P3-G2        # everything linked to one ID, with file:lines
node docs/reference/lookup.mjs --find "berth"   # find IDs by title
```

Then ask Claude to "plan task P3-G2". It takes the task through plan → execute → review.

## How a task flows

```
backend-task-plan  →  docs/tasks/<ID>.md (Ready)  →  backend-task-execute  →  backend-task-review  →  backend-pr-prepare
                                                                                                      ↓
                                     team-handoff · openapi-sync · contract-change · decision-close (when the task needs them)
```

- **The plan file `docs/tasks/<ID>.md` is the task's memory.** It holds the sources, the answers to the open questions, the steps with their modes, the progress log and the review. A new session reads it and carries on from where the last one stopped. `docs/tasks/_TEMPLATE.md` is the starting shape.
- **Every step has a mode:**
  - **A:** Claude does it alone.
  - **B:** Claude does it and the lead skims it.
  - **C:** Claude stops and waits for the lead's approval.
  - **D:** the lead does it themselves.
- **Git:** branch `feature/<task-id>-<slug>` from `origin/develop`, with one commit per step. PRs target `develop`. Nothing is pushed, opened or merged without the lead's OK.

## What is in the kit

### Reference documents and index (`docs/reference/`)

| File | What it does | Why |
|---|---|---|
| `manifest.json` | Lists the 49 documents copied from the shared project folder, with each one's source path | Copies in the repo make every session and every teammate read the same version |
| `sync-check.mjs` | Reports documents that changed at the source, a stale index and stale mirrors (exit codes below). `--apply` copies the changed documents and rebuilds everything; `--mirrors` refreshes only the agent copies | Catches drift before a task is planned on old text |
| `build-index.mjs` | Builds `index/index.json` (915 nodes) and the verbatim `split/` files. `--check` fails if the index is stale, a split file was edited by hand, or a backend-task ID does not resolve | Gives the skills a map of IDs to exact line ranges |
| `index/index.json` | One node per ID: tasks, CT, DEC, AIBD, AI tasks, FR, NFR, state machines, diagrams, tables. Each node has its title, status, `file:lines`, and links in both directions | Titles and pointers only; there are no summaries, so the original text stays the only source |
| `lookup.mjs` | Shows one ID with its linked titles and a `read:` line naming the file or line range to open. `--find "words"` searches titles. Warns when a decision's row is filled but the summary table still says Open | About 1 KB per ID, instead of reading 260 KB of index or whole documents |
| `split/` | Each decision, contract, AIBD, table and UML section, cut out word for word | Lets Claude open just the part it needs |
| `README.md` | The source-of-truth rules and the document table | See below |

**Source of truth, in order:**

1. An approved CT, or a Closed DEC.
2. The Master Roadmap.
3. The team documents.

**PROPOSED text (`proposals/`) never wins.** If a document is missing, the Master Roadmap applies, and Claude says so. If nothing covers a question, Claude asks.

`sync-check` exit codes: `0` everything is in sync; `1` documents changed, the index is stale, or the mirrors are stale; `2` the local documents folder is not found.

### Skills (`.claude/skills/`)

| Skill | Use it for | Mode of its key step |
|---|---|---|
| `context-rules` | How to read documents and code with the fewest tokens: lookup first, then only the line range, and cite `file:lines` | (rules) |
| `backend-task-plan` | Collect the sources (through `docs-navigator`), list what other teams owe and what the task affects, ask the open questions, write `docs/tasks/<ID>.md` | C: the lead answers the questions |
| `backend-task-execute` | Carry out the plan step by step in modes A/B/C/D | C steps stop |
| `backend-task-review` | Check the branch against the plan, contracts, decisions and schema | A, report only |
| `backend-pr-prepare` | Check the task is ready, then write the PR description. Push and open the PR only on the lead's OK | C |
| `api-readiness-pack` | The CT-23 pack for an FRR (BE-PK1–7): endpoints, OpenAPI diff, examples, seeds, events, gaps | B |
| `openapi-sync` | Keep `contracts/openapi/port-api.yaml` (CT-02, in PortHub) in step with the routes, through a paired PortHub PR | B |
| `contract-change` | Change an approved CT: impact on both sides, new version, decision entry, both teams approve | C |
| `decision-close` | Close an Open DEC: options with sources, decision from the right owner, recorded in all three places | C |
| `team-handoff` | Draft one message per affected team: what changed, why, where, what to do, by when. Claude only drafts these; the lead sends them | B |

Long checklists and templates for each skill live in its `reference/` folder, so each `SKILL.md` stays short.

### Agents (`.claude/agents/`, all read-only)

| Agent | Model | Job |
|---|---|---|
| `docs-navigator` | Haiku | Answers "everything about X" from the index and split files, and returns pointers and short quotes |
| `task-reviewer` | inherit | Runs `backend-task-review` in its own context, so the main session gets back findings rather than whole diffs |
| `schema-auditor` | inherit | Compares migrations with PortSync.sql, the DB edit sheet and the reports, table by table |
| `contract-checker` | inherit | Checks code that touches a CT (endpoints, errors, auth, events, queues, payloads) before a PR |

Agents keep big reads out of the main conversation. They can't edit anything; Bash is limited to the commands their file lists.

### Session hook and project rules

- **`.claude/settings.json`:** a `SessionStart` hook runs `sync-check.mjs`, so every session starts by reporting any drift. It never blocks the session.
- **`CLAUDE.md`, the "ATLANTECH workflow" section:** the workflow, sources, reading rules, code layout, git and language. It sits **outside** the Laravel Boost markers, because Boost rewrites only the text between its markers.
- **Mirrors:**
  - Our skills are copied to `.agents/skills/` and `.junie/skills/`.
  - The workflow section is copied to `AGENTS.md`.

  This way other agents follow the same rules. `sync-check --mirrors` refreshes these copies. Don't edit them by hand.

## Everyday commands

```bash
node docs/reference/sync-check.mjs --apply     # source documents changed: copy them, rebuild index and mirrors (commit separately)
node docs/reference/sync-check.mjs --mirrors   # after editing a skill or the CLAUDE.md section
node docs/reference/build-index.mjs --check    # CI-style check of the index
grep -oE '"DEC-[0-9]+":' docs/reference/index/index.json | sort | tail -1   # last DEC number
```

## Changing the kit

- **Edit a skill:** change the file in `.claude/skills/<name>/`, run `sync-check --mirrors`, and commit both.
- **A new document type, or a new ID pattern:** extend `build-index.mjs`, then run `build-index.mjs --check`. Every backend-task ID must still resolve.
- **Never:**
  - Replace or rewrite an original document with a summary.
  - Edit the files in `split/` by hand.
  - Let a tool send messages, push or merge for the lead.

## Results

Measured on two tasks: the bytes `backend-task-plan` reads to collect its sources (tokens ≈ bytes / 4).

| Task | Whole documents | Through the index | Saving |
|---|---|---|---|
| P3-G2 | ≈ 164K tokens | ≈ 2.2K tokens | ~74× |
| P1-A2 | ≈ 140K tokens | ≈ 1.6K tokens | ~88× |

The method and its caveats are in `docs/tasks/TOOLING.md`, step 5.

## Later

Once there is a lot of code, try Graphify or a code-graph MCP. Research it first, and install nothing without approval.
