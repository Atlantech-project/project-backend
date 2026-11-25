# SHARED-01 · `ResourceChanged` event and the period-overlap helper (Sprint 3 day-one PR)

| | |
| --- | --- |
| Status | Ready (lead's answers 2026-11-25; approved by Marwan, sprint lead, by merging the plan PR #74) |
| Phase / package | Cross-cutting, outside the roadmap task list: the shared prerequisite carved out of P2-F7 (event class) and P2-F3 / P2-F5 / P2-F6 (overlap rule). No ID in `backend/atlantech_backend_tasks.md`; ID `SHARED-01` (Q1, Mariam 2026-11-25) |
| Sprint · feature | Sprint 3 · FT-05 (master data) |
| Effort (plan) | 0.5 day |
| Branch | `feature/SHARED-01-resource-changed-overlap` (from `origin/develop` `9576ba3`) |
| Depends on | P2-D2, P2-D3 (merged: write chain, `AuditService::transaction`) |
| Blocked by | Nothing in code; the answers in section 8 |
| Unblocks | P2-F3 (Mariam: port-shift overlap), P2-F5 (Marwan: safety-constraint overlap), P2-F6 (Menna: productivity-profile overlap), P2-F2 and P2-F7 (berth / tug / pilot / equipment dispatch), later P4-K5 (the listener) |

## 1. What is asked

Source: the Sprint 3 hand-off to Mariam (section 0, "PR أول يوم"): two pieces of the shared kernel must be on `develop` on day one, because Marwan (F2, F5) and Menna (F6) build on them in parallel.

1. **`ResourceChanged`** in `app/Modules/Shared`: a domain event announced when a berth, tug, pilot or equipment changes; dispatched **after the commit**, never inside the transaction. The listener (find pending recommendations that use the resource, enqueue C-02 `RESOURCE_CHANGE`) is P4-K5, Sprint 6.
2. **Overlap helper** in `Shared`: answers "do these two periods overlap?" for the three no-overlap rules of Sprint 3.

This PR adds the two classes and their tests only. No endpoint, no migration, and no service dispatches the event yet.

**Done when:**

- [ ] `ResourceChanged` exists in `App\Modules\Shared\Events`, carries the resource kind (a Shared enum: `BERTH`, `TUG`, `PILOT`, `EQUIPMENT`), the resource id and the actor id (Q3), and implements `ShouldDispatchAfterCommit`.
- [ ] Dispatched inside `AuditService::transaction()`, it reaches its listeners only after the commit; when the transaction rolls back (an exception after the dispatch), no listener runs. Proved by a feature test on the MySQL schema database.
- [ ] Dispatched outside any transaction, it reaches listeners at once.
- [ ] The overlap helper answers correctly for the three shapes used in Sprint 3 (section 3, "Overlap rule"): datetime periods with an exclusive end, date periods with an inclusive end, open ends (`NULL` = no end), and time-of-day shifts that cross midnight. One unit test per case, including touching edges (end of one = start of the other).
- [ ] Neither class imports another module (`ModuleArchitectureTest` green); Pint clean.
- [ ] `SharedServiceProvider` doc comment and `docs/guide/F15_not_built_yet_and_next.md` rows 48 and 50 no longer say the event is missing (Q6).

**Out of scope:** dispatching the event from the berth service (P2-F2, Marwan) and from the tug, pilot and equipment services (P2-F7, Mariam); the listener and the C-02 job (P4-K5, P3-I1); the actual no-overlap checks with their queries, locks and 409/422 answers (P2-F3, P2-F5, P2-F6); any SSE or Reverb broadcast of resource changes (Realtime tasks, CT-05).

## 2. Sources read

| Source | Section / ID | What it says for this task |
| --- | --- | --- |
| Sprint 3 hand-off to Mariam (`E:\الموضوع إياه\Sprint3_Handoff_Mariam.md:17-20`, outside the repo) | Day-one PR | The event in `Shared`, dispatched `afterCommit`; the overlap helper in `Shared`, used by F3, F5 (Marwan) and F6 (Menna) |
| `backend/atlantech_backend_tasks.md:94`, `roadmap/roadmap-tasks.json:801-809` | P2-F7 | "Announce internally whenever a berth, tug, pilot or equipment changes state, so pending recommendations that use it can be refreshed"; depends on P2-F2, P2-F3 |
| `backend/atlantech_backend_tasks.md:90`, `roadmap/roadmap-tasks.json:765-773` | P2-F3 | "shifts (no overlap)" |
| `backend/atlantech_backend_tasks.md:92` | P2-F5 | "Safety constraints (no overlap for same type and scope)" |
| `backend/atlantech_backend_tasks.md:93` | P2-F6 | "Terminal productivity profiles (no overlapping periods)" |
| `backend/atlantech_backend_tasks.md:164` | P4-K5 | The listener: "find pending recommendations that use the resource, enqueue C-02 RESOURCE_CHANGE" |
| `split/ai-deps/AIBD-004.md` (`ai/ATLANTECH_AI_BACKEND_DEPENDENCIES.md:58, 106`) | AIBD-004 | Regenerate pending recommendations "that use a changed berth, tug or pilot", including resources released by a cancellation; payload PROPOSED |
| PortHub `contracts/db/schema.sql`, `port_shifts` | P2-F3 | `start_time`/`end_time` TIME in port local time; "If end_time < start_time the shift ends the next day"; `CHECK (end_time <> start_time)`; "active shifts must not overlap" |
| PortHub `contracts/db/schema.sql`, `safety_constraints` | P2-F5 | `active_from` DATETIME UTC, `active_to` "NULL = open-ended", `CHECK (active_to > active_from)`; "Overlap of the same type + scope is rejected by the backend" |
| PortHub `contracts/db/schema.sql`, `terminal_productivity_profiles` | P2-F6 | `valid_from` DATE, `valid_to` DATE NULL, `CHECK (valid_to >= valid_from)`; "Overlapping periods rejected by the backend" |
| `app/Modules/Shared/SharedServiceProvider.php:14-16` | Shared kernel | The doc comment already promises "the ResourceChanged event" in Shared |
| `docs/guide/F15_not_built_yet_and_next.md:48-50` | F15 | No `ResourceChanged` event yet; P2-F7 defines it |
| `tests/Unit/Modules/ModuleArchitectureTest.php:11-24` | A4 | `Shared` is the first layer: it may import no other module |
| `vendor/laravel/framework` 13.38.0, `Events/Dispatcher.php:296-305`, `Support/Testing/Fakes/EventFake.php:358-370` | Laravel | `ShouldDispatchAfterCommit` defers listeners to the commit callback of the transaction manager; `Event::fake()` honours it too |
| `tests/Pest.php` `useSchemaDatabase()`, `tests/Feature/Access/UserCreateTest.php:16-25` | Tests | Feature tests wrap each test in a manual `DB::beginTransaction()` / `rollBack()` (see risk R1) |

| `contracts/ATLANTECH_Contracts.md:486, 489` (`split/contracts/CT-10.md:49`) | CT-10 (approved) | Trigger: "A berth, tug or pilot becomes unavailable or available again"; type `BERTH` · `TUG` · `PILOT` "(closed enum for v1)"; ids are `berths.id`, `tugboats.id`, the pilot's `users.id`; "no \"what changed\" field is sent" |
| `contracts/ATLANTECH_Contracts.md:501-502` | CT-10 | A change that affects no pending request is not enqueued; confirmed plans change only through REPLAN (listener side, P4-K5) |
| `decisions/ATLANTECH_Decisions.md:496` | DEC-15 (Closed) | Triggers arriving meanwhile are merged; `changed_resources` per type |
| `ai/ATLANTECH_AI_BACKEND_DEPENDENCIES.md:233` | AIBD-004 | Older payload `changed_resource {type, id}` (singular, PROPOSED); CT-10 wins (Source of truth) |
| `requirements/Functional Requirements.md:165, 175, 179` | FR-MD-06, FR-MD-10, FR-MD-11 | Changing a berth, or "a tug, tug master or pilot that affects planning", regenerates only pending recommendations that use it; FR-MD-11 equipment has no regeneration clause |
| `backend/atlantech_backend_tasks.md:171` | P4-L2 | "released resources fire RESOURCE_CHANGE" (cancel path dispatches the same event) |
| `backend/Coding_Notes_and_Mapping.md:34, 49-51, 81` | Coding notes | Do not use `tugboat.status` for planning; the three no-overlap rules; notifications only after commit |
| `architecture/ATLANTECH_System_Architecture.md:94` | Architecture | Realtime "after commit" |
| `split/decisions/DEC-32.md:48` | DEC-32 | Pilot/tug operation intervals: "Intervals are half-open, so back-to-back jobs do not overlap" |
| `database/PortSync.md:1359, 1408` | Table checks | `active_to > active_from` (strict) vs `valid_to >= valid_from` (inclusive) |

Missing document: none. Not stated anywhere: inclusive/exclusive ends for the three tables, which change fires the event, and where the event lives (only the provider comment says Shared). These are asked in section 8.

## 3. Design

### Endpoints

None.

### Data

- Tables read or written: none.
- Migrations: none.
- Enums (CT-21): one new PHP enum `App\Modules\Shared\Enums\ResourceKind` (`Berth = 'BERTH'`, `Tug = 'TUG'`, `Pilot = 'PILOT'`, `Equipment = 'EQUIPMENT'`). The first three values are the CT-10 `changed_resources[].type` values, so P4-K5 maps them one to one; `EQUIPMENT` is backend-only (R5). It is internal (not stored, not in the API), so it is not added to the CT-21 status vocabulary (Q4).

### `ResourceChanged` (agreed shape, Q3)

```php
namespace App\Modules\Shared\Events;

final class ResourceChanged implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(
        public readonly ResourceKind $kind,
        public readonly int $resourceId,
        public readonly ?int $actorId,
    ) {}
}
```

- Plain ids and an enum, no Eloquent model: Shared may not import MasterData models, and a listener queued later must not depend on a serialized model that may have changed again.
- `ShouldDispatchAfterCommit`: the services call `ResourceChanged::dispatch(...)` inside `AuditService::transaction()`; Laravel holds it until the outermost commit and drops it on rollback. Outside a transaction it runs at once.
- Ids as CT-10: `BERTH` = `berths.id`, `TUG` = `tugboats.id`, `PILOT` = the pilot's `users.id` (= `pilot_profiles.user_id`); `EQUIPMENT` = `equipment.id`.
- No "what changed" field, as CT-10 ("the worker reads the current resource state from the database").
- No listener is registered in this PR.

### Overlap rule (agreed, Q2)

One final class `App\Modules\Shared\Support\PeriodOverlap` with static pure functions, no database access. Each caller still writes its own query; the helper is the single definition of "overlap", used both in PHP and as the comparison the query mirrors.

| Function | For | Rule |
| --- | --- | --- |
| `instants(CarbonInterface $aStart, ?CarbonInterface $aEnd, CarbonInterface $bStart, ?CarbonInterface $bEnd): bool` | P2-F5 `safety_constraints` | Half-open `[start, end)`; `null` end = never ends. Overlap when `aStart < (bEnd ?? ∞)` and `bStart < (aEnd ?? ∞)`. Touching (`aEnd == bStart`) is **not** an overlap |
| `dates(CarbonInterface $aFrom, ?CarbonInterface $aTo, CarbonInterface $bFrom, ?CarbonInterface $bTo): bool` | P2-F6 `terminal_productivity_profiles` | Closed `[from, to]` by calendar day (time part ignored); `null` to = open. Overlap when `aFrom <= (bTo ?? ∞)` and `bFrom <= (aTo ?? ∞)`. Same day at the edge (`aTo == bFrom`) **is** an overlap |
| `timesOfDay(string $aStart, string $aEnd, string $bStart, string $bEnd): bool` | P2-F3 `port_shifts` | `HH:MM[:SS]` in port local time; `end < start` means the shift ends the next day. Each shift is turned into one or two half-open minute ranges on a 0–1440 day, and the shifts overlap when any pair of ranges overlaps. Touching (`06:00–14:00` and `14:00–22:00`) is **not** an overlap |

Also, so each caller writes the same query, the class doc gives the SQL form of each rule (for example `active_from < :bEnd AND (active_to IS NULL OR active_to > :bStart)`).

### Behaviour

- Write chain, audit, licence mode: not applicable (no endpoint, no write).
- Events: `ResourceChanged` itself, after commit.

## 4. Steps

Modes: **A** Claude does it, no review · **B** Claude does it, you skim the summary · **C** Claude drafts, stops for your approval · **D** you do it · **E** you type it, Claude prepares and reviews (`manual-execution`).

| # | Step | Mode | Why this mode |
| --- | --- | --- | --- |
| 1 | Branch `feature/SHARED-01-resource-changed-overlap` from `origin/develop`; commit this plan | A | Mechanical |
| 2 | `ResourceKind` enum and `ResourceChanged` event in Shared, with doc comments | E | Core shared contract Marwan, Menna and P4-K5 rely on; the lead must be able to explain the after-commit behaviour |
| 3 | Feature test `tests/Feature/Shared/ResourceChangedTest.php`: after commit, not on rollback, at once outside a transaction | C | The test needs the test-aware transaction manager (R1); approve the approach |
| 4 | `PeriodOverlap` helper with the three functions and the SQL forms in its doc | E | Core rule every overlap check will use |
| 5 | Unit tests `tests/Unit/Modules/Shared/PeriodOverlapTest.php` with datasets for each shape and edge | B | Pure function, datasets are easy to skim |
| 6 | Update `SharedServiceProvider` comment and `docs/guide/F15` rows (if Q6 yes); Pint; run the two test files and `ModuleArchitectureTest` | A | Mechanical |
| 7 | `backend-task-review`, then `backend-pr-prepare` (push and PR only on the lead's OK) | C | Workflow gate |

### Tests

| Test | Covers |
| --- | --- |
| `ResourceChangedTest` "reaches listeners only after the commit" | after-commit dispatch inside `AuditService::transaction()` |
| `ResourceChangedTest` "is dropped when the transaction rolls back" | rollback |
| `ResourceChangedTest` "reaches listeners at once outside a transaction" | no transaction |
| `ResourceChangedTest` "carries kind, id and actor" | payload |
| `PeriodOverlapTest` `instants` dataset | overlap, contained, touching, disjoint, open end on either side, both open |
| `PeriodOverlapTest` `dates` dataset | same-day edge overlaps, next-day disjoint, open ends |
| `PeriodOverlapTest` `timesOfDay` dataset | day shifts, touching shifts, night shift crossing midnight against a morning shift, two night shifts, a shift contained in another |
| `ModuleArchitectureTest` (existing) | Shared imports no other module |

## 5. Other teams

**Needed from other teams before or during this task:** none.

**This task changes something other teams use:**

| Team | What changes | Where it is published |
| --- | --- | --- |
| Backend (Marwan, Menna) | New shared classes they must use in P2-F2, P2-F5, P2-F6 | This PR; a short note in the team chat after merge (`team-handoff` not needed: same team) |

Contract artefacts: none (no endpoint, no table, no status value, no AI payload).

## 6. Impact on other backend work

- P2-F2 (berths) and P2-F7 (tugs, pilots, equipment) dispatch `ResourceChanged` from their services inside the write transaction.
- P2-F3, P2-F5, P2-F6 use `PeriodOverlap` for their no-overlap rules; changing its edge rules later changes three features at once.
- P4-K5 registers the listener; if the listener is queued, the event's plain payload is what it receives.
- AIBD-004 also mentions resources released by a cancellation (P4-L2): that task will dispatch the same event.

## 7. Conflicts and risks

| Finding | Sources that disagree | Proposed resolution | Decided by |
| --- | --- | --- | --- |
| R1. Feature tests open `DB::beginTransaction()` by hand, so with Laravel's normal transaction manager an after-commit event never fires inside a test (the outer test transaction is never committed) | `tests/Pest.php` and every feature test vs `ShouldDispatchAfterCommit` | In the event tests (and later in P2-F2/F7 tests), bind Laravel's testing manager `Illuminate\Foundation\Testing\DatabaseTransactionsManager` before `beginTransaction`, as the `DatabaseTransactions` trait does, through a small Pest helper next to `useSchemaDatabase()` | Lead (Q5) |
| R2. The three overlap rules have different edge semantics (exclusive datetime end, inclusive date end, midnight wrap) | `schema.sql` CHECKs of the three tables | One helper with three named functions instead of one generic function (section 3) | Lead (Q2) |
| R3. "Changes state" is not defined: any edit, or only a change that affects availability (status, quantities, assignment)? | P2-F7 roadmap `desc` vs AIBD-004 | The event class does not decide this; each service decides when to dispatch. Proposed default for P2-F7: dispatch on every create, update and status change of the four resources (the listener filters) | Lead (Q3) |
| R4. Tug masters are in FR-MD-10 ("a tug, tug master or pilot") but not in P2-F7 nor in CT-10 | `Functional Requirements.md:175` vs P2-F7 and CT-10:489 | No `TUG_MASTER` kind: a tug-master change is announced as `TUG` for the assigned tug (P2-F7 decides) | Lead (Q3) |
| R7. Port local time has DST (Egypt): on the changeover night a night shift lasts 7 or 9 hours. Does not affect `timesOfDay()` (it compares clock times); affects turning `shift_date` + `start_time` into UTC in P2-F3 schedules | `port_shifts` TIME in port local time vs UTC datetimes elsewhere | Handle in P2-F3 with the port time zone, not in this helper | P2-F3 plan |
| R5. P2-F7 includes equipment, CT-10's type enum is closed to `BERTH`, `TUG`, `PILOT` | `roadmap-tasks.json:804` vs `ATLANTECH_Contracts.md:489` | Keep `EQUIPMENT` in the backend enum (P2-F7 asks for it); P4-K5 does not send it to Part 2 under CT-10 v1. No contract change now | Lead (Q4) |
| R6. AIBD-004 payload `changed_resource` (singular) vs CT-10 `changed_resources[]` | `AI_BACKEND_DEPENDENCIES.md:233` vs `ATLANTECH_Contracts.md:464-466, 489` | CT-10 wins (approved contract over PROPOSED); the event carries one resource, P4-K5 merges them into the array (DEC-15) | Source of truth rule |

## 8. Open questions

| # | Question | Answer | Answered by / date |
| --- | --- | --- | --- |
| Q1 | The task has no roadmap ID. Name it `SHARED-01` (branch `feature/SHARED-01-resource-changed-overlap`)? | Yes: `SHARED-01`, branch `feature/SHARED-01-resource-changed-overlap` | Mariam · 2026-11-25; Marwan approved (merged PR #74) · 2026-11-25 |
| Q2 | Overlap edges: datetime end exclusive (touching allowed), date end inclusive (same day overlaps), shifts touching at the hour allowed, `NULL` end = open. Agreed? One helper with three functions? | Yes. `instants()` (P2-F5): touching is **not** an overlap. `dates()` (P2-F6): the same day at the edge **is** an overlap and is rejected. `timesOfDay()` (P2-F3): no date on `port_shifts` (it is the daily template; the date lives in `pilot_schedules` / `tug_master_schedules.shift_date`); keep the no-overlap check against typing mistakes, back-to-back shifts allowed. `NULL` end = open for ever: a new period overlapping an open one is rejected and the admin closes the old one first; the helper defines the rule, the error answer and endpoint are Marwan's (P2-F5). One class, three functions | Mariam · 2026-11-25; Marwan approved (merged PR #74) · 2026-11-25 |
| Q3 | Event payload `kind`, `resourceId`, `actorId` only (CT-10 sends no "what changed")? When P2-F2/F7 dispatch: every create and update, or only availability changes (status, quantities, assignment)? | Payload `kind`, `resourceId`, `actorId` only. When to dispatch is left to P2-F2 / P2-F7 (R3, R4) | Mariam · 2026-11-25; Marwan approved (merged PR #74) · 2026-11-25 |
| Q4 | `ResourceKind` = `BERTH`, `TUG`, `PILOT` (CT-10) plus backend-only `EQUIPMENT`; internal, not in CT-21? | Yes, `EQUIPMENT` included; the enum is extended later if a new resource kind appears. The event carries `equipment.id` only: the category (`equipment_categories.equipment_role` VESSEL_HANDLING / CARGO_TRANSPORT / TRANSFER_SYSTEM) is read from the database by the listener, so new categories need no enum change. Internal, not in CT-21 | Mariam · 2026-11-25; Marwan approved (merged PR #74) · 2026-11-25 |
| Q5 | Event tests: use the testing transaction manager through a Pest helper (R1)? | Yes | Mariam · 2026-11-25; Marwan approved (merged PR #74) · 2026-11-25 |
| Q6 | Update `docs/guide/F15` and the provider comment in this PR? | Yes, in this PR | Mariam · 2026-11-25; Marwan approved (merged PR #74) · 2026-11-25 |
| Q7 | Anything you know about this task that the documents don't say? | No | Mariam · 2026-11-25; Marwan approved (merged PR #74) · 2026-11-25 |

## 9. Progress log

- 2026-11-25 · Step 1 (A) · branch from `origin/develop` `9576ba3`; plan Ready after PR #74. Done.
- 2026-11-25 · E group prepared in a throwaway worktree: `ResourceKind`, `ResourceChanged`, `PeriodOverlap`; 53 tests pass (`PeriodOverlapTest`, `ResourceChangedTest`, `ModuleArchitectureTest`) on a private schema database; Pint clean. Mutation check: without `ShouldDispatchAfterCommit` 3 of the 4 event tests fail.
- 2026-11-25 · Step 3 (C) · `useTestingTransactionManager()` in `tests/Pest.php` and `tests/Feature/Shared/ResourceChangedTest.php` (4 tests, one more than planned: nested transactions wait for the outermost commit). Committed; fails until step 2 is typed. Waiting for the lead's approval of the approach.
- 2026-11-25 · Step 5 (B) · `tests/Unit/Modules/Shared/PeriodOverlapTest.php`: 9 + 8 + 11 cases each checked in both orders, plus invalid input. The shift function also rejects start = end and times that are not `HH:MM[:SS]` (`InvalidArgumentException`). Committed; fails until step 4 is typed.
- 2026-11-25 · Step 6 (A) · F15 rows 48, 50, 189 and the `SharedServiceProvider` comment updated. Committed.

## 10. Review

<Filled by `backend-task-review`.>
