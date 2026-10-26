---
name: contract-change
description: Change an ATLANTECH team contract (CT-xx) after it was approved: work out the impact on both sides, choose the new version, draft the decision-log entry, edit the contract on a PortHub branch, get both teams' approval and tell the other team. Use when a task, review or decision needs a contract to say something different, or when asked to "change CT-xx". Not for contracts still in draft: those are edited in their own CT task.
---

# Contract change

Rule (`contracts/ATLANTECH_Contracts.md`, intro): after approval, a change needs **both sides to agree, an entry in the decision log, and a new version number**. Checklist: `reference/change-checklist.md`. Follow `context-rules`; every step is mode C (it binds another team).

## 1. Establish the current contract

- `node docs/reference/lookup.mjs CT-xx`: owner (`from`), receivers (`to`), `status` (approved version), linked decisions; read `split/contracts/CT-xx.md`.
- The published file is in PortHub `contracts/` (for example `contracts/api-conventions.md` for CT-01). If the contract is not approved yet, stop: edit it in its CT task instead.

## 2. Describe the change and its impact

Write, in the plan file of the task that needs the change (section 7), or in a new note if no task does:

- **What changes:** quote the current text (`file:lines`) and give the new text.
- **Why:** the task, review finding or decision that requires it.
- **Impact on each side:** code already built on the current text (search this repo; ask the other team for theirs); tasks of any team that depend on the contract (`lookup.mjs CT-xx`, `← unblocks`); artefacts that must change with it (OpenAPI, event list, vocabulary, schemas, mocks, readiness packs).
- **Version:** breaking for any consumer (a field, code, name or value removed or changed, a new required input) → next major (`2.0`); additive and optional → next minor (`1.1`); wording or examples only → patch (`1.0.1`).

## 3. Decision-log entry

Draft a new `DEC-nn` (next free number: `node docs/reference/lookup.mjs --find "DEC-"` or the last one in the Decisions summary) in the format of the decisions document: Source `CT-xx`, Owner, Status Open, Closing task, Affects, Topic, Options, Recommendation, Decision blank. The lead adds it to the shared Decisions file; then `node docs/reference/sync-check.mjs --apply` brings it into the repository. Use `decision-close` when it is decided.

## 4. Edit the contract (after the lead agrees to propose it)

On a PortHub branch `feature/CT-xx-vX.Y`: change the text, set the header to `Proposed vX.Y (DEC-nn)`, add a change-log line (date, version, summary, DEC-nn). Push and open the PR only after the lead's OK (PortHub PRs target `main`).

## 5. Approval and hand-off

- Approval from both sides in the way the team uses for contracts (CT-01: in a team meeting and as GitHub reviews from each receiving team).
- Offer `team-handoff` to tell the receiving teams what changes, why, the version, and what they must do.
- After approval: header `Approved vX.Y · <date> (DEC-nn)`, the decision Closed, the shared Contracts file updated by the lead, `sync-check --apply`, and follow-up tasks or plan steps for every code change listed in step 2.

Never change an approved contract silently, and never let code ship against a proposed version before it is approved.
