---
name: context-rules
description: Rules for reading the ATLANTECH project documents and code with the fewest tokens without losing meaning. Every backend skill and agent follows them. Use whenever you need a fact from docs/reference (a task, decision, contract, AIBD, requirement, table, state machine, diagram) or are about to open a large document or code file.
---

# Context rules

## Find before you read

1. **Look up the ID first:** `node docs/reference/lookup.mjs <ID…>` (task, `DEC-xx`, `CT-xx`, `AIBD-xxx`, `AI-xxx`, `FR-…`, `NFR-…`, `SM-n`, `AD-n`, `SEQ-n.m`, a table name). It prints the title, status, the split file to read, exact source lines, and every linked ID with its title. For a topic without an ID: `node docs/reference/lookup.mjs --find "<words>"`.
2. **Read only what the lookup points to:** the `read:` split file, or the `source:` line range (`Read` with offset and limit). Never open a whole large document (`UML_Context.md`, `PortSync.md`, `ATLANTECH_Decisions.md`, `ATLANTECH_Contracts.md`, the roadmap JSON, the PDFs) unless the index has no entry for what you need, and then search it (`grep -n`) before reading a range.
3. **Never read `index/index.json` whole;** use the lookup, or `grep -F '"<ID>":' docs/reference/index/index.json` for one node.
4. If the lookup says an ID is "not defined in docs/reference", its document is missing: apply the Source of truth rules in `docs/reference/README.md` (Master Roadmap) and say so.

## Meaning comes from the originals

- Titles in the index and summaries anywhere are for navigation only.
- Every decision, plan line or review finding that depends on a document **quotes or cites the original lines** (file:line), never a summary or a title.
- Split files are verbatim copies; they cite their source in the first line. Never edit them; edit the source and run `node docs/reference/build-index.mjs`.

## One session per task

- `docs/tasks/<ID>.md` is the task's memory. A new session reads it first (status, answers in section 8, progress log) and continues; it does not re-derive what is recorded there.
- Record new facts, answers and deviations in the plan file as you go, so the next session does not need this one's context.

## Code and tools

- Prefer Laravel Boost tools over reading files: `database-schema` for tables, `search-docs` for framework APIs, `php artisan route:list` for routes, `database-query` for data.
- Read the specific class or method (`grep -n` for the line, then a range), not whole directories.

## Keep output small

- Tests: `php artisan test --compact` and the narrowest file or `--filter`.
- Commands: pipe long output through `grep`, `head` or `tail`; never print whole logs, lock files, migrations folders or the index.
- When a tool result is long, keep only the lines you need in your reply.
