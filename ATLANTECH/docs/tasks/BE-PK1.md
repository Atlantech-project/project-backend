# BE-PK1 · API readiness pack for FRR 2

| | |
| --- | --- |
| Status | In review |
| Phase / package | Readiness packs |
| Sprint · feature | Sprint 1 · FT-13 Integration, quality and release |
| Effort (plan) | 0.5 |
| Branch | `feature/BE-PK1-ft-02-readiness` here and in PortHub |
| Depends on | CT-23 (template drafted, PortHub PR not merged) |
| Blocked by | nothing |
| Unblocks | PL-FRR2 · BE-PK2 |

## 1. What is asked

Add the API readiness pack for FRR 2 (Marwan's message, 2026-10-31; `roadmap/roadmap-tasks.json:4842-4850`).

**Done when:**

- [x] `contracts/readiness/FT-02.md` follows the CT-23 template. *`contract-checker`: no mismatches.*
- [x] Anything not yet true is under Known gaps. *Same check.*
- [ ] The lead approved the pack and the PortHub PR is merged, before FRR 2.

**Out of scope:** a pack for FT-01, which has no endpoints (Q1) · building P1-C3, P1-C4 mobile, P1-C5, P1-B6.

## 2. Sources read

| Source | Section / ID | What it says for this task |
| --- | --- | --- |
| `docs/reference/roadmap/roadmap-tasks.json:263-271` | PL-FRR2 | Leads check the pack for each feature planned for Sprint 2 |
| `docs/reference/split/contracts/CT-23.md` | CT-23 | The pack lines |
| PortHub `contracts/auth-web.md` | CT-03 | Flow and profile examples |
| `openapi/port-api.yaml:30-158` | CT-02 | The four paths and their responses |
| `app/Modules/Shared/Errors/ErrorCode.php`, `lang/en/errors.php` | CT-01 | Error titles and texts |
| `docs/reference/split/decisions/DEC-47.md` | DEC-47 (Closed) | No shared dev server yet; local Docker Compose |
| `database/seeders/DatabaseSeeder.php` | P1-B6 | One test user until P1-B6 |
| `docs/tasks/P1-C1.md:234`, `docs/tasks/P1-C2.md:104` | PRs | The merged PRs |

## 3. Design

No endpoints, data or behaviour change. One new PortHub file.

## 4. Steps

| # | Step | Mode | Why this mode |
| --- | --- | --- | --- |
| 1 | Write the pack, run `contract-checker` | C | The skill sets mode C; the lead reviews before it is shared |
| 2 | After the PortHub merge: reference sync, `team-handoff` | B | Same as earlier contract tasks |

### Tests

None: no code.

## 5. Other teams

**Needed from other teams:** nothing.

**This task changes something other teams use:**

| Team | What changes | Where it is published |
| --- | --- | --- |
| Frontend · Mobile · QA | What they review at FRR 2 | `contracts/readiness/FT-02.md` |

## 6. Impact on other backend work

BE-PK2 starts from this pack's Known gaps.

## 7. Conflicts and risks

| Finding | Sources that disagree | Proposed resolution | Decided by |
| --- | --- | --- | --- |
| Examples are from the test client, not a deployed server | Skill vs no dev server | Stated in the pack | Marwan, relayed by Mariam 2026-10-31; not yet on the PR |
| The template asks for one block per endpoint and status | Template vs identical error bodies | One body per status plus a status table | Marwan, relayed by Mariam 2026-10-31; not yet on the PR |
| FRR 2 scope: Sprint 2 also has other features (FT-01, FT-03) | PL-FRR2 vs Q1 | FT-02 only | Marwan, relayed by Mariam 2026-10-31; not yet on the PR |

## 8. Open questions

| # | Question | Answer | Answered by / date |
| --- | --- | --- | --- |
| 1 | Which features does the pack cover? | FT-02 only | Mariam · 2026-10-31; Marwan agrees, relayed by Mariam |

## 9. Progress log

- 2026-10-31 · Step 1 drafted on local branches, not pushed. `contract-checker`: no mismatches (read-only, nothing run). Waiting for approval (mode C).
- 2026-10-31 · Error bodies captured through the test client on MySQL; they matched the pack. The temporary capture test was deleted and not committed. The Examples gap was removed from the pack.
- 2026-10-31 · Review fixes applied: pack title. Mariam reports that Marwan agrees.

## 10. Review

2026-10-31 · `task-reviewer`: 1 Must fix, the FT-02-only scope, now agreed. Should fix: the pack title is now the roadmap name. It could not open the PR links; their numbers match the P1-C1 and P1-C2 plans. CT-23 merges first.
