# TUG-LINK-01 · Clear the tug link when a tug master is deactivated or leaves the role through `/users`

## Decision, 2026-11-27 (replaces the design in sections 3, 4 and 6)

- **The normal case is already met:** when the Tug Manager deactivates a tug master (`POST /tug-masters/{tugMaster}/deactivate`), the tug link is cleared in the same transaction (FR-MD-08, P2-F3).
- **The exception:** when the Port Admin deactivates the user or changes the role through `/users`, the link is **not** cleared at that moment. It is handled from the tug side: when the Tug Manager links another tug master to that tug (create or relink) and the holder is inactive or no longer holds `TUG_MASTER`, the old link is cleared with its own audit row and the new one is saved, in one transaction. An active tug master still gives 422 `errors.tug_masters.tug_taken`.
- **Code:** `TugMasterService` only. No event, no listener, no change in `Access`. The holder's user and role are read with locking reads (plan-limit snapshot rule). The lock order stays profile, then user.
- **Until someone takes the tug:** reads still show the old tug master (`is_active` false when deactivated; nothing marks a role change). Planning is unaffected (DEC-32). A holder who is activated or gets the role back before that keeps the tug.
- Section 8: Q2, Q3, Q5 no longer apply; Q4 is covered by the rule above. `ResourceChanged` stays with P2-F7 (Q6). OpenAPI: two descriptions reworded, 1.13.1 (Q7).
- Decided by the user in chat, who asked for the code and the push. **Marwan's confirmation of this reading of FR-MD-08 is pending** (through the PR). Sections 1 and 3 to 8 below are the earlier proposal, kept for reference.

| | |
| --- | --- |
| Status | In review |
| Phase / package | Phase 2 Access control and master data · follow-up of P2-F3 (risk R10, review C1) |
| Sprint · feature | Sprint 3 · FT-05 Master data |
| Effort (plan) | 0.5 day |
| Branch | proposed `feature/TUG-LINK-01-clear-tug-link` from `origin/develop` after P2-F3 merges (Q1) |
| Depends on | P2-F3 (in review: `feature/P2-F3-marine-resources`, waiting for Marwan's merge) · P2-E1, P2-E2 (merged) |
| Blocked by | P2-F3 merge · section 8 |
| Unblocks | no listed task. P2-F7 touches the same place (section 6) |

The ID is proposed (Q1). It is not in the roadmap or the tasks file, so `lookup.mjs` does not know it.

## 1. What is asked

FR-MD-08: "Deactivating a tug master clears the tug link in the same transaction." Today only `POST /tug-masters/{tugMaster}/deactivate` does it (`TugMasterService::setActive`). Three other routes leave `tug_master_profiles.assigned_tugboat_id` set: `POST /users/{user}/deactivate` (`UserAdministrationService::deactivate`), `POST /users/{user}/roles` and `POST /users/{user}/roles/end` (`RoleAssignmentService::assign`, `::end`). Because one tug has at most one master, the stale link also blocks the tug: linking another master answers 422 `errors.tug_masters.tug_taken` until someone unlinks by hand.

**Done when** (each line is a test):

- [ ] `POST /users/{user}/deactivate` on a tug master with a tug sets `assigned_tugboat_id` to NULL in the same transaction, with one UPDATE audit row on `tug_master_profiles` (actor = the Port Admin).
- [ ] `POST /users/{user}/roles` that replaces `TUG_MASTER` with another role does the same.
- [ ] `POST /users/{user}/roles/end` on a tug master does the same.
- [ ] If clearing the link fails, nothing is saved: not the user, not the role, not the audit rows.
- [ ] No change and no extra audit row for: a user who is not a tug master, a tug master without a tug, a refused request (409, 422), a no-op (already inactive, same role, no role to end).
- [ ] The freed tug can be linked to another tug master at once.
- [ ] The `/users` routes and the `/tug-masters` writes take their row locks in one order (users, role assignment, tug master profile), so they cannot deadlock on the same person.
- [ ] `ModuleArchitectureTest` passes: `Access` does not import `MasterData`.

**Out of scope:** dispatching `ResourceChanged` (P2-F7, Q6) · restoring the link on activate or when the role is given back (the Tug Manager links again) · future `tug_master_schedules` rows, which are kept (P2-F3 Q4) · pilot profiles (no link to clear) · the licence allow-lists (P2-D5).

## 2. Sources read

Paths are under `docs/reference/` unless they start with `app/`, `docs/tasks/`, `database/` or `tests/`.

| Source | Section / ID | What it says for this task |
| --- | --- | --- |
| `requirements/Functional Requirements.md:171` | FR-MD-08 | Deactivating a tug master clears the tug link in the same transaction; a tug has at most one master |
| `requirements/Functional Requirements.md:175` | FR-MD-10 | A tug, tug master or pilot change that affects planning regenerates the pending recommendations that use it |
| `requirements/Functional Requirements.md:129-133` | FR-AC-10, 11, 12 | Deactivate (allowed in READ_ONLY), assign a role, end a role |
| `uml/ATLANTECH_Sequence_Diagrams.md:1058-1125` | SEQ-2.5 | Deactivate tug master: "clear the tug link, one transaction", audit, regenerate recommendations of the linked tug. Shows no `/users` path |
| `decisions/ATLANTECH_Decisions.md:1206-1275` | DEC-32 (Closed) | A tug is available only if its linked master is an active user with the active role `TUG_MASTER` |
| `decisions/ATLANTECH_Decisions.md:1358` onwards | DEC-36 (Closed) | One active role; replacing it ends the old one |
| `docs/tasks/P2-F3.md` | R10, review C1, Q4, Q10, deviation 5 | Known limitation and this follow-up (Mariam, 2026-11-27); schedules kept; one-per-tug is serialised on the tug row |
| `docs/tasks/P2-E1.md`, `docs/tasks/P2-E2.md` | §3 | Transaction and lock order of the three `/users` writes |
| `app/Modules/Access/Services/{UserAdministrationService,RoleAssignmentService,PortAdminGuard}.php` | code | Lock admin set and target `users` rows, then role rows. No event is raised today |
| `app/Modules/MasterData/Services/{TugMasterService,CrewMemberService}.php` | code | Lock the **profile** first, then the user and role. `CrewMemberService::setActive` updates `users` itself, so the `/tug-masters` path does not go through `UserAdministrationService::deactivate` |
| `app/Modules/Shared/Support/UserLimitCheck.php`, `MinAppVersionSource.php` | code | Existing pattern: interface in Shared, implemented by a later module |
| `app/Modules/Shared/Events/ResourceChanged.php` | code | Existing domain event, after commit (not usable here) |
| `tests/Unit/Modules/ModuleArchitectureTest.php:11-24` | code | Order Shared, Access, License, MasterData |

`sync-check.mjs`: the local documents folder was not found on this machine; the repository copies were used. The P2-F3 code was read on `feature/P2-F3-marine-resources` (`8740557`).

## 3. Design

### Endpoints

No new endpoint and no change to any request, response or error code.

| Method and path | Added effect |
| --- | --- |
| `POST /users/{user}/deactivate` | If the user's active role is `TUG_MASTER`, the tug link is cleared |
| `POST /users/{user}/roles` | If the replaced role is `TUG_MASTER`, the tug link is cleared |
| `POST /users/{user}/roles/end` | If the ended role is `TUG_MASTER`, the tug link is cleared |

### Mechanism: a synchronous event (recommended, Q2)

- **New event** `app/Modules/Access/Events/UserDutyEnded` (name open): `int $userId`, `Role $role` (the role the user no longer serves in), `int $actorId`. It does **not** implement `ShouldDispatchAfterCommit`; the class comment says why, because it is the opposite of `ResourceChanged`.
- **Access raises it** inside the existing `AuditService::transaction`, after every refusal check and after the write:
  - `deactivate`: only when the user really changes to inactive. The active role is read with a locking read (`activeRoleAssignment()->lockForUpdate()`); no event when there is none.
  - `assign`: after the old row is closed, when there was a current role and the new role is different. The no-op return stays before it.
  - `end`: after the row is closed.
- **MasterData listener** `app/Modules/MasterData/Listeners/ClearTugMasterLink`: returns at once unless `$role` is `TUG_MASTER`; then reads the profile `FOR UPDATE`; if there is a link, sets `assigned_tugboat_id` to NULL and calls `AuditService::recordUpdated($profile, $actorId)`. It is not `ShouldQueue` and not `ShouldHandleEventsAfterCommit`. An exception in it rolls the whole request back.
- **Registration:** `Event::listen(...)` in `MasterDataServiceProvider::boot()`. Laravel's discovery scans `app/Listeners` only, so a module listener must be registered by hand.
- **Why the role is in the event:** the listener touches `tug_master_profiles` only for tug masters. A `FOR UPDATE` on a missing key takes a gap lock, which would affect profile inserts for every user deactivation.
- **`/tug-masters` routes:** unchanged in behaviour. They raise no event and keep their own clearing, so there is no double write.

### Lock order (needed with any mechanism, Q3)

Without a change the new write creates a deadlock on one tug master:

| Request | Locks, in order |
| --- | --- |
| `/users/{u}/deactivate` or role change, with this task | `users` u, role rows, then **profile** u |
| `TugMasterService::update`, `setActive`, `assignTugboat` today | **profile** u, then `users` u, role row |

Two such requests at once can each hold what the other needs. InnoDB then kills one, and `AuditService::transaction` does not retry, so the caller would get a 500.

**Fix:** in `TugMasterService`, lock the user first (`CrewMemberService::lockMember`), then `VersionToken::lockAndAssert` on the profile. Unlinking (`tugboat_id` null) must still work for a former tug master, so it locks the user row **without** the role check (a small new method next to `lockMember`; today it takes no user lock at all). The no-op return of `assignTugboat` stays after both locks. Over HTTP the `version.token` middleware still answers 409 for a stale token before the service runs; only a race between the middleware and the service could now answer 404 instead of 409. The plan-limit snapshot rule of P2-F3 still holds, because both reads stay locking reads.

`create` keeps its order (tug row, holder's profile). It goes on only when the tug has no holder, so it never keeps another person's profile row while it waits for something else.

### Alternatives compared (Q2)

| | Option | For | Against | Verdict |
| --- | --- | --- | --- | --- |
| A | Synchronous event in Access, listener in MasterData | Keeps the module order; same transaction; Access knows nothing about tugs; more listeners can be added (P2-F7) | The link is implicit; a missing registration fails silently (only the HTTP tests catch it); easy to make after-commit by mistake | **Recommended** |
| B | Interface in Shared (or Access) implemented and bound by MasterData, like `UserLimitCheck` | Same guarantees; explicit call; a missing binding fails loudly; existing project pattern | One implementation per binding; Access names the hook in its constructors; the role argument must be a string if the interface sits in Shared | Close second |
| C | Eloquent observer in MasterData on `User` / `UserRoleAssignment` | No change in Access; catches every Eloquent write | Hidden; has no actor id; also fires for MasterData's own writes; misses query-builder updates | Rejected |
| D | Move the three `/users` writes behind a MasterData service or controller that calls Access, then clears | Explicit | User routes move to a later module; any other caller of the Access services skips the rule | Rejected |
| E | After-commit event or job | Simplest to add | Breaks "same transaction" (FR-MD-08) | Rejected |
| F | `DB::table('tug_master_profiles')` inside Access | Small | Passes the arch test only by hiding the dependency | Rejected |
| G | Refuse (409) deactivation or role change while a tug is linked | No cross-module write | Contradicts FR-MD-08; blocks an urgent deactivation | Rejected |
| H | Database trigger, or relaxing the arch rule | — | Schema change (CT-17) with no audit row; or loses the module order | Rejected |

### Data

- Written: `tug_master_profiles.assigned_tugboat_id` and `updated_at` (Backend-owned; workers read, CT-15). No migration, `schema.sql` unchanged, no enum.
- Because `updated_at` is the tug master's version token, a Tug Manager holding the old token gets 409 `VERSION_CONFLICT`. This is intended.

### Behaviour

- **Write chain:** unchanged on all three routes.
- **Audit:** the rows P2-E1 / P2-E2 write today, plus one UPDATE on `tug_master_profiles` (before: old tug id; after: null) when a link was cleared. `entity_id` is the user id (the profile key). A listener failure writes no `REJECTED_ATTEMPT`.
- **Licence:** the clearing is part of the request. Deactivate stays allowed in READ_ONLY with the link cleared (FR-AC-10); role changes stay normal writes. P2-D5 must not block the listener separately.
- **Events and messages:** nothing after commit in this task.

## 4. Steps

Modes: A Claude alone · B Claude, you skim · C Claude drafts, you approve · E you type (`manual-execution`). Proposed; the lead approves or changes each.

| # | Step | Mode | Why |
| --- | --- | --- | --- |
| 1 | Branch from `origin/develop` with P2-F3 merged | A | Setup |
| 2 | `TugMasterService` lock order and the user lock for unlink; existing tug master and concurrency tests stay green | E | Locks; comes first so no commit has the deadlock |
| 3 | `UserDutyEnded` event and the three dispatch points in Access | E | Transaction rules in security-sensitive services |
| 4 | `ClearTugMasterLink` listener and its registration | E | The business rule |
| 5 | Tests (below) | C | You approve the cases |
| 6 | Docblocks (`TugMasterService` and the two Access services), Pint, narrow tests, then you run the full suite | A | Mechanical |
| 7 | `backend-task-review`, then `backend-pr-prepare` | C | Before the PR. Consider Opus: locks and user administration |

### Tests

Proposed file `tests/Feature/MasterData/TugMasterLinkOnUserChangeTest.php` (through HTTP, which also proves the listener is registered); the lock proofs go in `MarineResourcesConcurrencyTest.php`.

| Test | Covers |
| --- | --- |
| Deactivate a linked tug master through `/users` | Link NULL, user inactive, one profile audit row with before / after and the Port Admin as actor, schedule rows kept |
| Replace `TUG_MASTER` by another role; end the role | Same, one case for each route |
| Rollback, one case for each of the three routes | A second listener that throws (or a failing audit write) leaves the user active, the role open, the link set and no new audit row |
| No effect | Pilot and office user deactivated; tug master without a link; stale `version` 409; already inactive; same role again; end with no role: link and audit rows unchanged |
| Refused request | A 409 from `/users` writes only what it writes today (use a case that reaches the service) |
| Tug is free again | After each path, `PUT /tug-masters/{id}/tugboat` links another master to that tug (200) |
| Version token | The tug master's old version answers 409 after the link was cleared |
| Give the role back, or activate again | The link stays NULL |
| `/tug-masters/{id}/deactivate` | Still exactly one profile audit row |
| Lock proof: profile row held by a second connection | The three `/users` writes wait (lock wait timeout) and save nothing |
| Lock proof: user row held | Unlink waits (today it does not); `update`, `setActive` and relink wait |
| Lock order | With `DB::listen`, the `for update` query on `users` comes before the one on `tug_master_profiles` in `update`, `setActive` and `assignTugboat` |
| `ModuleArchitectureTest`, `UserDeactivate*`, `UserRole*`, `TugMasterTest` | No regression |

A real two-process deadlock test is not planned; the query-order test is the proof of the rule.

## 5. Other teams

**Needed from other teams:** none.

**This task changes something other teams use:**

| Team | What changes | Where |
| --- | --- | --- |
| Frontend | A tug master removed through the user screens now shows no tug, and the tug can be given to someone else at once | Tell with the PR; API shapes unchanged |
| AI | No change in availability results (DEC-32 already excludes these masters); the stale link disappears | CT-15 unchanged |

Contract artefacts: OpenAPI (CT-02) no change proposed (Q7) · CT-01 none · SSE none · CT-21 none · `schema.sql` none · mock scenarios none · readiness pack: one line in the FT-05 pack when it is written.

## 6. Impact on other backend work

- **P2-F7:** after this task, a link can be cleared in two places: `TugMasterService` and `ClearTugMasterLink`. Both need `ResourceChanged` as `TUG` with the **old** tug id (CT-10), read before the update. A pilot deactivated or moved through `/users` also changes planning (FR-MD-10) and does not pass through `PilotService`; `UserDutyEnded` can serve that too. P2-F7's plan must list both (Q6).
- **P4-L1 / P5-M1:** no change; they must keep the DEC-32 checks on active user and role, because old data may hold stale links.
- **Later Access work:** any new path that deactivates a user or ends a role must raise `UserDutyEnded`. The event's class comment says so.
- **Do not break:** the last-Port-Admin rule and its lock order, the plan-limit snapshot rule in `CrewMemberService::lockMember`, the P2-F3 lock proofs.

## 7. Conflicts and risks

| # | Finding | Sources | Proposed resolution | Decided by |
| --- | --- | --- | --- | --- |
| R1 | FR-MD-08 and SEQ-2.5 speak about deactivating a tug master. Neither mentions a role change through `/users` | FR-MD-08, SEQ-2.5:1058-1125 | Treat leaving the role like deactivation: the profile no longer belongs to a tug master (D-29) and the link blocks the tug | Q4 |
| R2 | New lock inversion (deadlock, 500) | code, section 3 | Change the `TugMasterService` lock order | Q3 |
| R3 | `PortAdminGuard::isLastActiveAdmin` locks rows of `user_role_assignments` through a query on `role`, which has no index (P2-E2 review note), on every user deactivation. With the new profile write, a rare deadlock with an unrelated tug-master write (a role insert, or a gap lock on the `assigned_tugboat_id` index) cannot be ruled out by reading the code. Not reproduced | code, P2-E2 §10 | Record it; no retry and no index in this task. The reviewer checks it | Note only |
| R4 | Links already stale before this task are not cleared: a later `/users` call is a no-op or has no `TUG_MASTER` role to leave | design | Clear by hand with `PUT /tug-masters/{id}/tugboat` (null); no data migration | Q5 |
| R5 | A future listener made queued or after-commit would silently break "same transaction" | design | Class comments and the rollback tests | Note only |

## 8. Open questions

For Marwan. The proposal is Claude's; nothing is decided yet.

| # | Question | Proposal | Answer | Answered by / date |
| --- | --- | --- | --- | --- |
| Q1 | Task ID `TUG-LINK-01` and branch `feature/TUG-LINK-01-clear-tug-link`? Wait for the P2-F3 merge, or stack on its branch? | Yes to the names; wait for the merge | | |
| Q2 | Mechanism: synchronous event (A), or an interface bound by MasterData (B, like `UserLimitCheck`)? If A, is the name `UserDutyEnded` OK? | A: it can also serve P2-F7 for pilots; the HTTP tests cover the silent-registration risk | | |
| Q3 | Change the lock order in `TugMasterService` to user, then profile (including a user lock on unlink)? The other choice is to retry the transaction on deadlock | Change the order; no retry | | |
| Q4 | Clear the link on a role change and on role end too, although FR-MD-08 only says "deactivating"? | Yes | | |
| Q5 | Links that are already stale (made before this task): manual unlink only, or a one-time cleanup? | Manual only; there is no production data yet | | |
| Q6 | `ResourceChanged` on this path: left to P2-F7 (including pilots changed through `/users`)? If P2-F7 merges first, does this task add the dispatch in the listener? | Whichever merges second adds the listener's `TUG` dispatch; pilots through `/users` stay with P2-F7 | | |
| Q7 | Leave OpenAPI unchanged, since requests, responses and errors do not change? Or add one sentence to the three `/users` operations (version bump and PortHub PR)? | Leave it unchanged; tell the Frontend with the PR | | |
| Q8 | Anything you know about this task that the documents don't say? | | | |

## 9. Progress log

- 2026-11-27: plan drafted (Draft). No code, no commit. Written in the shared checkout while it was on `feature/P2-F3-marine-resources`; this file is untracked and belongs to this task's branch, not to the P2-F3 PR.
- 2026-11-27: implemented by Claude at the user's request (see the Decision at the top) on `feature/TUG-LINK-01-clear-tug-link`, stacked on `feature/P2-F3-marine-resources` (`8740557`). `claimTugboat` replaces `assertTugboatFree`; 7 new test cases. Pint clean; MasterData, Access, Api and Unit: 670 passed. Not run: full suite, Spectral. The PortHub OpenAPI copy (1.13.1) is not published. Pushed for the lead to open the PR after P2-F3 merges.

## 10. Review

Not run: `backend-task-review` has not been run on this branch.
