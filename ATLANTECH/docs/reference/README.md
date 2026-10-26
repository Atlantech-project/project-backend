# Backend reference documents

Copies of the project documents the backend is built from. The originals live in the team's shared documents folder; `manifest.json` records where each file came from. The backend skills (`backend-task-plan`, `backend-task-execute`, `backend-task-review`) read from here.

**Before relying on these files**, check that they match the local originals:

```bash
node docs/reference/sync-check.mjs            # report differences
node docs/reference/sync-check.mjs --apply    # copy newer local files in, then commit the diff
```

Set `ATLANTECH_DOCS_ROOT` if the documents folder is not at `manifest.sourceRoot`. Exit code 1 means a local file changed and the repository copy is out of date.

## Source of truth

1. **A referenced document is missing** (not in this folder, or only an older version is here): the general plan, the **Master Roadmap** (`roadmap/ATLANTECH_Master_Roadmap.pdf`, with its task data in `roadmap/roadmap-tasks.json`), is the source of truth for that point: scope, owner team, sprint, order and dependencies, and "done when". Phase references the roadmap does not use (for example the AI plan's `P0`–`P10`) are read through the sprint in which the roadmap places the same task. Write in the task plan that the roadmap was used because the document is missing.
2. **Two present documents disagree:** an approved contract (`contracts/`) or a Closed decision (`decisions/`) wins on what it settles; otherwise the Master Roadmap wins on scope, timing and dependencies; the team documents (backend tasks, AI tasks and dependencies, requirements, diagrams, schema) supply the detail. Text marked PROPOSED never overrides any of these.
3. **Nothing covers the point:** it is an open question for the user (and the owning team), not a choice to make silently.

## Where to look

| Question | Look in | IDs used there |
| --- | --- | --- |
| What is the task, its effort, what must merge first, which AI item it links to | `backend/atlantech_backend_tasks.md` | `P<phase>-<package><n>` (P3-G2), `AIBD-n`, `AI-nnn`, `D-nn` |
| Sprint, feature, plain-language "done when", and **every team's** tasks with dependencies | `roadmap/roadmap-tasks.json` (`tasks[]`, `sprints{}`, `features{}`) | same task IDs, plus `AI-nnn`, `FE-nnn`, `MB-…`, `UX-…`, `QA-…`, `DEC-nn`, `CT-nn`, `FT-nn` |
| Phase goals, exit checks, overall backend design | `backend/AtlanTech_Backend_Plan.pdf`, `backend/AtlanTech_Backend_Architecture.pdf` | |
| Contract with another team: shape, owner, reviewers, output file | `contracts/ATLANTECH_Contracts.md` | `CT-01` … `CT-26` |
| Is a question already decided, still open, or in conflict | `decisions/ATLANTECH_Decisions.md` (summary table has Owner, Status, Close by) | `DEC-01` … `DEC-56` |
| Tables, columns, enums, keys | `database/PortSync.sql` (port DB), `database/SaaS.sql` (central SaaS DB); explained in `database/PortSync.md`, `database/SaaS.md` | table names |
| Approved schema changes not to miss | `database/ATLANTECH_DB_Edit_Sheet.md`, `database/PortSync_base_ddl_fixes.sql`, `database/Report_A_…`, `database/Report_B_…`, `database/DBML_CHANGE_REPORT.md`, `database/FCM_*` (post-MVP push) | |
| Rules the database does not enforce, derived values, row versions, transactions and locks | `backend/Coding_Notes_and_Mapping.md` (sections 2–6) | |
| Functional behaviour and validation rules | `requirements/Functional Requirements.md` | `FR-<AREA>-n` |
| Performance, security, audit, availability targets | `requirements/Non Functional Requirements.md` | `NFR-<AREA>-n` |
| Roles and what each may do | `requirements/Users & Actors.md` | role codes |
| Status life cycles | `uml/ATLANTECH_State_Diagrams.md` | `SM-n` |
| Step-by-step flows between Laravel, workers, web and mobile | `uml/ATLANTECH_Sequence_Diagrams.md`, `uml/ATLANTECH_Activity_Diagrams.md` | |
| Use cases | `uml/ATLANTECH_Use_Case_Diagrams.md`, `uml/UML_Context.md` | `UC-n` |
| Classes and relations per area | `uml/class/` (1 SaaS, 2A access and marine, 2B terminal and yard, 2C request, BAPLIE and Part 1, 2D Part 2, plan and dispatch, 3 execution and learning) | |
| What the backend must provide the AI, per dependency: contract, prerequisites, failure behaviour; table ownership; per-workflow duties; acceptance checklist; open integration decisions | `ai/ATLANTECH_AI_BACKEND_DEPENDENCIES.md` | `AIBD-nnn`, `OD-nn`, `PS-nn`, `WF-n` |
| The AI team's tasks (what each `AI-nnn` link means, its phase and dependencies) | `ai/ATLANTECH_AI_TASKS.md` | `AI-001` … `AI-106`, phases `P0`–`P10` |
| AI architecture and the earlier phase plan | `ai/ATLANTECH_AI_Architecture.pdf`; older generation (Oct 19, `T<phase>.<n>` IDs): `ai/ATLANTECH_AI_Implementation_Plan.md`, `ai/ATLANTECH_AI_Phase_Deliverables_and_Backend_Map.md` | `T<phase>.<n>`, `BD-n` |
| Architecture overview, components, deployment | `architecture/ATLANTECH_System_Architecture.md`, `architecture/System_Architecture.pdf` | `AD-n` |
| Project overview | `project/Master_Context.md` | |
| Screens that call the API | `ui/ATLANTECH_Screens.pdf` | `P-nn` page IDs |

## Known gaps

- `ATLANTECH_AI_TASKS.md` and `ATLANTECH_AI_BACKEND_DEPENDENCIES.md` (v1.0, 22 Oct) are derived from `ATLANTECH_AI_IMPLEMENTATION_PLAN.md` v1.0 (phases P0–P10, open decisions `OD-xx`), which is not in the shared folder. The implementation plan here is the older 19 Oct version with `T<phase>.<n>` IDs and is not used as a source. Under rule 1 above, wherever the AI documents rely on the v1.0 plan, the Master Roadmap is the source of truth.
- The dependencies document marks most payloads and names **PROPOSED — REQUIRES APPROVAL**; rule 2 above applies. `OD-xx` in the AI documents corresponds to the `D-nn` rows in the backend tasks file and to `DEC-nn` in the decisions file.
- The `contracts/` folder that `ATLANTECH_Contracts.md` proposes for the PortHub repository does not exist yet. Until it does, the contract text above is the source of truth.
