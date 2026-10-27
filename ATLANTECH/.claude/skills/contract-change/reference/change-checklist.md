# Contract change checklist

## Before proposing

- [ ] The contract is approved (status `Approved vX.Y`); drafts are edited in their own CT task instead.
- [ ] Current text quoted with `file:lines`; new text written in full.
- [ ] Reason linked: task ID, review finding, or decision.
- [ ] Version chosen: major (breaking for any consumer) · minor (additive, optional) · patch (wording, examples).

## Impact

- [ ] This repository: code that uses the changed field, code, event, queue, enum or shape (search by name).
- [ ] Other teams: their tasks that depend on the contract (`lookup.mjs CT-xx`, `← unblocks`); ask their lead about code already written.
- [ ] Artefacts that change with it:
  - [ ] OpenAPI (CT-02)
  - [ ] SSE / WebSocket event list (CT-05, CT-06)
  - [ ] Sync event shapes (CT-07)
  - [ ] AI envelope and payload schemas (CT-08 to CT-14)
  - [ ] Table ownership (CT-15) and `schema.sql` (CT-17)
  - [ ] Mock worker scenarios (CT-19)
  - [ ] Status vocabulary (CT-21)
  - [ ] Error-code catalogue (CT-01)
  - [ ] Readiness packs already shared (CT-23)
- [ ] Decisions that settle the same point (`lookup.mjs CT-xx` → linked DEC); a Closed decision that the change contradicts needs its own reopening.

## Records

- [ ] New `DEC-nn` drafted (Source CT-xx, Options, Recommendation) and added by the lead to the shared Decisions file.
- [ ] Contract header `Proposed vX.Y (DEC-nn)` and change-log line on a PortHub branch.

## Approval

- [ ] Owner team agrees.
- [ ] Every receiving team approves (meeting and PR review, as for CT-01).
- [ ] Header `Approved vX.Y · <date> (DEC-nn)`; DEC-nn Closed with the decision text.
- [ ] Shared Contracts file updated; `sync-check --apply` run; index rebuilt.
- [ ] Message sent to the other teams (`team-handoff`).
- [ ] Follow-up tasks or plan steps exist for every code and artefact change.
