# <TASK-ID> · <task title>

| | |
| --- | --- |
| Status | Draft · Ready · In progress · In review · Done |
| Phase / package | Phase <n> <name> · <letter> <package name> |
| Sprint · feature | Sprint <n> · FT-<nn> <feature name> |
| Effort (plan) | <days> |
| Branch | `feature/<task-id>-<slug>` |
| Depends on | <ID> (<state: merged / in progress / not started>) · … |
| Blocked by | <open DEC / missing contract / other team item, or "nothing"> |
| Unblocks | <tasks of any team that list this task as a dependency> |

## 1. What is asked

<The task in plain words, from the tasks file and the roadmap "done when".>

**Done when** (each line is checkable by a test or a demo):

- [ ] …

**Out of scope:** <things that look related but belong to another task, with its ID>.

## 2. Sources read

| Source | Section / ID | What it says for this task |
| --- | --- | --- |
| `backend/atlantech_backend_tasks.md` | <ID> | |
| `requirements/Functional Requirements.md` | FR-… | |
| `uml/ATLANTECH_State_Diagrams.md` | SM-… | |
| `contracts/ATLANTECH_Contracts.md` | CT-… | |
| `decisions/ATLANTECH_Decisions.md` | DEC-… (Open / Closed) | |
| `database/PortSync.sql` + edit sheet | <tables> | |
| `backend/Coding_Notes_and_Mapping.md` | § … | |

## 3. Design

### Endpoints

| Method and path | Role(s) and scope | Request | Success | Errors (code) |
| --- | --- | --- | --- | --- |

### Data

- Tables and columns read / written (and who owns each, CT-15): …
- Migrations: <none / new / change — a change to a table the AI writes needs AI approval (CT-17)>.
- Enums and status values (CT-21): …
- Rules the database does not enforce, enforced in code: …

### Behaviour

- Write chain steps that apply (P2-D2 order): auth · password gate · scope · app version · licence mode · version token · rules · plan limits.
- Transaction boundary and locks: …
- Audit rows (same transaction, refused attempts as `REJECTED_ATTEMPT`): …
- Licence mode: allowed in READ_ONLY? SETUP? (423 otherwise)
- Events and messages: SSE (CT-05) · Reverb (CT-06) · notifications · AI jobs (CT-08 to CT-14) — sent after commit.

## 4. Steps

Each step has a mode (see the `backend-task-execute` skill):
**A** Claude does it, no review · **B** Claude does it, you skim the summary · **C** Claude drafts, stops for your approval · **D** you do it.

| # | Step | Mode | Why this mode |
| --- | --- | --- | --- |
| 1 | | | |

### Tests

| Test | Covers |
| --- | --- |

## 5. Other teams

**Needed from other teams before or during this task:**

| Team | What | Contract / task | Status |
| --- | --- | --- | --- |

**This task changes something other teams use:**

| Team | What changes | Where it is published |
| --- | --- | --- |

Contract artefacts to update in the same PR when they apply: OpenAPI (CT-02) · SSE event list (CT-05) · status vocabulary (CT-21) · `schema.sql` (CT-17) · mock worker scenarios (CT-19) · readiness pack (CT-23).

## 6. Impact on other backend work

<Later tasks that build on this one, shared services this task creates or changes, and anything they must not break.>

## 7. Conflicts and risks

| Finding | Sources that disagree | Proposed resolution | Decided by |
| --- | --- | --- | --- |

## 8. Open questions

| # | Question | Answer | Answered by / date |
| --- | --- | --- | --- |

## 9. Progress log

<Filled by `backend-task-execute`: date, step, result, any deviation from the plan and why.>

## 10. Review

<Filled by `backend-task-review`.>
