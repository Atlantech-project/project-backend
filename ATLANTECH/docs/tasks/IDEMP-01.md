# IDEMP-01 · `Idempotency-Key` for every mobile write (CT-01 section 4)

| | |
| --- | --- |
| Status | Ready (all questions answered by the lead, 2026-11-06) |
| Phase / package | Cross-cutting, outside the roadmap task list: a gap found by the Mobile Team in P1-C3's review. No ID in `backend/atlantech_backend_tasks.md` (the lookup has no entry); the lead named the task `IDEMP-01` |
| Sprint · feature | Sprint 2 · FT-02 (mobile sign-in) and every later mobile write |
| Effort (plan) | 1 day |
| Branch | `claude/idempotency-and-e-plans-e5b324` (one PR into `develop`; it also carries the plans of P2-E1, P2-E2, P2-E3) |
| Depends on | P1-C3, P1-C4 (merged: Bearer tokens, `EnsureTokenDevice`, logout) · P2-D2 (merged: `port.api` group, priority list) |
| Blocked by | Nothing |
| Unblocks | P2-E3 and later Bearer writes (they get the rule for free); `POST /sync/events` (CT-07) only needs the exemption that this task makes |

## 1. What is asked

**Source:** the Mobile Team's reply of 2026-11-06 to the P1-C3 hand-off (recorded in the P1-C3 progress log, row "Mobile Team told"): *"`Idempotency-Key` is not implemented on the server (open: build it for logout and change-password, or narrow CT-01 section 4)"*. The lead decided the same day to build it, for **every** write made with a mobile Bearer token, through one feature.

Today the server ignores the header: CT-01 section 4 is not implemented (`PortHub/contracts/api-conventions.md` lines 41-49), the OpenAPI component `IdempotencyKey` is used by no path, and `ErrorCode::IdempotencyKeyReused` is returned by nothing. Two real problems:

- a **retried logout** after a lost response answers 401 (the token is revoked) instead of replaying 204;
- a **retried change-password** after a lost response fails with 422 (the current password already changed).

**Done when:**

- [ ] A Bearer `POST`, `PUT`, `PATCH` or `DELETE` on a `port.api` route without a well-formed `Idempotency-Key` (UUID v4) answers 422 `VALIDATION_FAILED` (field `Idempotency-Key`), after authentication (a bad token is still 401 first).
- [ ] Same key and same request (method, path, body): the first response is replayed (status, body, content type) with `Idempotent-Replayed: true`, and the write does not run again (proved with a counter in a test route, and with change-password and logout).
- [ ] Same key with a different method, path or body → 422 `IDEMPOTENCY_KEY_REUSED`.
- [ ] A retried logout replays 204 even though the token is now revoked (the replay is found before `auth:sanctum`); a retried change-password replays 200.
- [ ] Two concurrent requests with the same key run the write once; the second waits briefly and replays (or answers 503 + `Retry-After` if the first is still running after the wait).
- [ ] A key used with another token (same user or not) never replays: it is a different request.
- [ ] Nothing is stored for a 5xx, for 401, or for the transient refusals 408, 423, 426, 429; a retry with the same key runs the write again. 2xx and the other 4xx are stored.
- [ ] The token is never stored (only its SHA-256), and the stored response is encrypted at rest.
- [ ] Web session requests (cookie) are untouched, with or without the header.
- [ ] Routes named in the configured exemption list (`sync.events.store`) are exempt; proved by a test route with that name.
- [ ] `WriteChainOrderTest` and the coverage tests are green; OpenAPI 1.8.0 documents the parameter and the 422 on the two Bearer write operations; drift test and Spectral green.

**Out of scope:** `POST /sync/events` and its `client_event_id` (CT-07; the route does not exist, only its exemption); `POST /auth/mobile/login` and `POST /broadcasting/auth` (not in `port.api`; CT-01 section 4 already excludes them); web requests; implementing P2-E1/E2/E3 (their plans come in this PR, no code).

## 2. Sources read

| Source | Section / ID | What it says for this task |
| --- | --- | --- |
| PortHub `contracts/api-conventions.md:35-49` | CT-01 section 3 and 4 | The header on every mobile write; UUID v4; kept 24 h; same body replays the first response; different body 422 `IDEMPOTENCY_KEY_REUSED`; missing or malformed 422 `VALIDATION_FAILED`; sync events keep `client_event_id`; mobile login and channel auth are excluded |
| PortHub `contracts/api-conventions.md:333` | CT-01 change log v1.3 | Sign-in and channel authorisation do not use the header |
| PortHub `contracts/error-codes.md` | CT-01 | `IDEMPOTENCY_KEY_REUSED` 422 exists; no new code is needed |
| `docs/tasks/P1-C3.md` progress log | P1-C3 | The Mobile Team's reply (source of this task) |
| `docs/guide/F11_write_chain.md`, `bootstrap/app.php:41-73` | FR-GL-01, P2-D2 | The `port.api` group, the priority list, and why order does not depend on how a route lists its middleware |
| `app/Modules/Access/Http/Middleware/EnsureTokenDevice.php` | P1-C3 | Bearer detection by `currentAccessToken()`; header 422s come right after authentication |
| `app/Modules/Access/Http/Controllers/AuthController.php:64-101` | P1-C2, P1-C4 | `changePassword` (200, revokes other tokens) and `logout` (204, revokes the token) |
| `app/Modules/Shared/Errors/ErrorCode.php` | CT-01 | `IdempotencyKeyReused` 422, `ServiceUnavailable` 503 already exist |
| `openapi/port-api.yaml:408-419, 164-232, 898` | CT-02 | Component `IdempotencyKey` (unused), the two Bearer write operations, response `IdempotencyKeyReused` |
| `docs/reference` DEC-57 (CT-01/CT-02 change rule) | DEC-57 | A clarification of CT-01 needs a new version and a change-log line, no decision-log entry, no team review |

Missing document: none.

## 3. Design

### Endpoints

No new endpoint. Behaviour added to every Bearer write route in `port.api`; today that is `POST /auth/logout` and `POST /auth/change-password`.

| Request | Result |
| --- | --- |
| Bearer write, valid key, first time | runs; response stored; returned unchanged |
| same key, same request | stored response, header `Idempotent-Replayed: true`; the write does not run |
| same key, different method, path or body | 422 `IDEMPOTENCY_KEY_REUSED` |
| key missing or not a UUID v4, token valid | 422 `VALIDATION_FAILED` on `Idempotency-Key` |
| key missing, token invalid | 401 (authentication comes first) |
| same key, still running after the wait | 503 `SERVICE_UNAVAILABLE` + `Retry-After: 1` (lead, 2026-11-06) |

### Data

- **No table, no migration** (so no `ai-impact`, no CT-17 bump). Storage is the cache store named by `config('idempotency.store')`, default the application's cache store (`CACHE_STORE=redis` in `.env.example`; `array` in tests, which supports atomic locks).
- Entry key: `idempotency:{sha256(bearer token)}:{key}`. Value (encrypted with `Crypt::encryptString`, because a response body can hold a one-time secret, for example the P2-E1 one-time password): `request_hash`, `status`, `content_type`, `body`. TTL 24 hours (`config('idempotency.ttl_hours')` = 24).
- `request_hash` = SHA-256 of `METHOD`, the path with its query string, and the raw body, joined with a separator.
- Lock key `idempotency-lock:{same scope}`; lock lifetime 30 s, wait 5 s (config).
- Config `config/idempotency.php`: `store`, `ttl_hours`, `lock_seconds`, `lock_wait_seconds`, `exempt_routes` (route names, `Str::is` patterns; default `['sync.events.store']`), `unstored_statuses` (`[401, 408, 423, 426, 429]`).
- Enums and status vocabulary (CT-21): none. Error codes: none new.

### Behaviour

Two small classes in `app/Modules/Shared/Http/Middleware` (a mobile write needs one step before `auth:sanctum` and one step after it), sharing a support class `app/Modules/Shared/Support/IdempotencyStore.php` (keys, hashing, encryption, lock). Both are internal to the one feature; the aliases are `idempotency` and `idempotency.key`.

1. **`IdempotentWrite`** (alias `idempotency`), first in the `port.api` group and in the priority list before `AuthenticatesRequests`. Applies when the request has a Bearer token, the method is POST, PUT, PATCH or DELETE, and the route name is not exempt; otherwise it passes. It needs the token only as a scope: it never trusts it.
   - Key header missing or not a UUID v4: pass; the second step answers 422 once the caller is known to be authenticated.
   - Look up the entry. Found with the same `request_hash` → replay. Found with another hash → throw `IDEMPOTENCY_KEY_REUSED`.
   - Not found: take the lock (`block(lock_wait_seconds)`); on timeout 503. After the lock, look up again (the winner may have stored it while we waited) and replay if so. Otherwise run the request.
   - After the response: store it when the second step marked the request as an authenticated mobile write and the status is not 5xx and not in `unstored_statuses`. Release the lock in `finally` (also when the request throws).
2. **`RequireIdempotencyKey`** (alias `idempotency.key`), right after `EnsureTokenDevice` in the group (same place as the other header 422s). Applies when the caller is authenticated by a Sanctum token that is not transient, the method is a write and the route is not exempt: validates the header (422 `VALIDATION_FAILED`), then sets a request attribute so step 1 knows the response may be stored. A web session request never gets the attribute, so nothing about it is stored or required, even if it carries a stray `Authorization` header.
3. **Why before authentication.** A retried logout comes with a token that the first request revoked, so `auth:sanctum` would answer 401. The entry exists only because the same token made that request while valid, and the entry key contains the hash of that exact token plus the secret UUID, so finding it proves the caller holds both. A different token never finds it (case 5).
4. **Replay response:** status, `Content-Type` and body of the first response, plus `Idempotent-Replayed: true`. Other headers (`Set-Cookie`, rate-limit counters) are not replayed.
5. **Write-chain order (FR-GL-01):** replay lookup · authentication 401 · device and app-version headers 422 · **key 422** · password gate 403 · ability 403 · scope · 426 · 423 · binding · version token 409 · endpoint. The replay answers before everything else, which is the point; a request that was refused in the chain (403, 409, 422) is stored like any other deterministic 4xx and replays the same refusal.
6. **Transaction boundary / audit:** none of its own. The stored response is written after the endpoint's transaction committed. Refusals of this feature (422, 503) are not audited: before authentication there is no user, and a bad header carries no state change (same as Form Request 422s).
7. **Licence mode:** not affected; a replayed write does not reach the licence step, a stored 423 is never replayed.
8. **Events and messages:** none.

### Exemption

`config('idempotency.exempt_routes')` lists route names; the first entry is `sync.events.store`, the name `POST /sync/events` must use (CT-07). The test registers a route with that name inside `port.api` and posts as a Bearer caller with no key: the route runs. When P5 builds the route it takes that name and needs no change here.

## 4. Steps

Each step has a mode (see the `backend-task-execute` skill):
**A** Claude does it, no review · **B** Claude does it, you skim the summary · **C** Claude drafts, stops for your approval · **D** you do it.

| # | Step | Mode | Why this mode |
| --- | --- | --- | --- |
| 1 | Bring in P2-E1, E2, E3 plans, fix what is stale, add "restore a revoked device" to E3; P1-C3 log row | B | Plans only, no code; the new endpoint is listed in the summary for the lead |
| 2 | `config/idempotency.php`, `IdempotencyStore`, unit tests for hashing, scope, encryption | C | New shared pattern; security (token hash, encryption) |
| 3 | `IdempotentWrite` + `RequireIdempotencyKey`, group and priority list in `bootstrap/app.php`, `WriteChainOrderTest` additions | C | Write chain, security, first instance of the pattern |
| 4 | Feature tests: logout and change-password replay, concurrency, token scope, 5xx and unstored statuses, web untouched, exemption | B | Proof of the done-when lines |
| 5 | OpenAPI 1.8.0: parameter and 422 on the two Bearer write operations; drift and Spectral | C | Contract artefact |
| 6 | CT-01 section 4 wording (PortHub branch, v1.5) and the paired PortHub OpenAPI copy PR | C | Contract change; PortHub PR is prepared, not merged |
| 7 | Pint, narrow then one full-suite run, `backend-task-review`, `backend-pr-prepare` | A | Mechanical |

### Tests

| Test | Covers |
| --- | --- |
| `tests/Feature/Api/IdempotencyStoreTest.php` (Unit tests do not boot the app here) | Key built from the token hash (the token is not in the key or value); request hash covers method, path+query, body; stored value is encrypted; TTL 24 h |
| `tests/Feature/Api/IdempotencyTest.php` | Missing and malformed key → 422 after 401 (bad token with no key is 401); same key and body replays status, body, content type and `Idempotent-Replayed`, the counter route ran once; different body, path and method → `IDEMPOTENCY_KEY_REUSED`; key of another token (same user and other user) does not replay and runs; 5xx, 401, 423, 426, 429 not stored (second call runs); 2xx and 422/403/409 stored; web session request with and without the header untouched, also with a stray `Authorization` header; exempt route `sync.events.store` runs without a key; GET/HEAD/OPTIONS pass |
| `tests/Feature/Api/IdempotencyConcurrencyTest.php` | A second request while the lock is held and the first has not stored: waits, then replays after the winner stores (simulated with a held `Cache::lock` released by a callback); still held after the wait → 503 + `Retry-After`; the lock is released when the request throws |
| `tests/Feature/Access/MobileLogoutTest.php` (added) | Logout twice with one key: 204 then replayed 204 (token revoked, header set); logout twice with two keys: 204 then 401 |
| `tests/Feature/Access/PasswordChangeTest.php` (added) | Change-password twice with one key: 200 then the same 200 replayed (no 422); the password changed once |
| `WriteChainOrderTest` (added) | Bearer write without key is 422 after 401/ and before the password gate 403 and ability 403 on the same request; the group order holds when a route lists the steps in reverse |
| `RouteAbilityCoverageTest`, `RouteVersionCoverageTest`, `OpenApiDriftTest`, `ModuleArchitectureTest` | No regression (Shared imports nothing from Access) |

## 5. Other teams

**Needed from other teams before or during this task:**

| Team | What | Contract / task | Status |
| --- | --- | --- | --- |
| Mobile | Sends `Idempotency-Key` (UUID v4) on every Bearer write and reuses it on retry | CT-01 section 4 | Contract already says so; the client can start now |

**This task changes something other teams use:**

| Team | What changes | Where it is published |
| --- | --- | --- |
| Mobile | The server now enforces the header (422 without it on logout and change-password), replays with `Idempotent-Replayed: true`, and answers `IDEMPOTENCY_KEY_REUSED`; 503 + `Retry-After` while the first request with that key still runs | OpenAPI 1.8.0, CT-01 v1.5 wording |
| Frontend | None (web sessions unaffected) | — |
| QA | Test cases above | Readiness pack note |

Contract artefacts: OpenAPI (CT-02) yes, 1.7.0 → 1.8.0 · CT-01 wording (exemption, replay header, what is stored) · SSE none · status vocabulary none · `schema.sql` none · mock scenarios none · readiness pack a note.

## 6. Impact on other backend work

- Every later Bearer write (P2-E3 device actions if called from mobile, P5 writes) gets the rule from the group; its OpenAPI operation must list `Idempotency-Key` and the 422.
- P5 sync (`POST /sync/events`) must be named `sync.events.store` or added to `idempotency.exempt_routes`.
- The guide files that describe this after the merge: `docs/guide` F03, F04, F11, F15 and the `Code Notes/Backend_Guide` copy (not edited in this task).
- Do not break: `EnsureTokenDevice`, the password gate, the throttle on change-password (a replay is answered before the throttle; the throttle still counts real attempts).

## 7. Conflicts and risks

| Finding | Sources that disagree | Proposed resolution | Decided by |
| --- | --- | --- | --- |
| C1: CT-01 section 4 says only "the first response is replayed"; storing 423/426/429 would replay a transient refusal for 24 h | CT-01 vs the lead's "non-5xx" | Store 2xx and the deterministic 4xx; not 5xx, 401, 408, 423, 426, 429 | Marwan, 2026-11-06 (Q1) |
| C2: logout replay must be found before `auth:sanctum` while the 422 for a missing key must come after it | Lead's brief (hard case 1, "after authentication") | Two steps: lookup/lock/store first, key validation after `EnsureTokenDevice` | Plan |
| C3: no 409 code for "first request still running" (`CONFLICT` is only planned in P2-E1) | CT-01 error catalogue | 503 `SERVICE_UNAVAILABLE` + `Retry-After: 1`; no new code | Marwan, 2026-11-06 (Q2) |
| C4: replay before authentication returns a stored body to whoever holds the token and the key, even if the token was revoked later | — | Accepted: both are secrets of the owner; the entry lives 24 h; the body is encrypted at rest; a revoked-by-logout token only ever sees 204. Documented | Plan |
| C5: a replay is answered before the device-id check and the throttle on change-password | `EnsureTokenDevice`, `throttle:5,1` | Accepted: it replays a result that was already produced, and runs no write | Plan |
| C6: a cache flush loses the entries, so a retry would run twice | — | Accepted for this contract (a cache, 24 h); the store is Redis in production. If the lead wants durable keys, a table is the alternative (migration, `ai-impact`, CT-17) | Plan |
| C7: refusals of this feature are not audited | FR-GL-02 | No user before authentication, no state change; same as Form Request 422s | Plan |
| C8: the key 422 is placed with the other header 422s, so a locked or password-gated user without a key sees 422 before 403 | FR-GL-01 | Same as `X-Device-Id`/`X-App-Version` today (P1-C3 review D-B) | Plan |

## 8. Open questions

| # | Question | Answer | Answered by / date |
| --- | --- | --- | --- |
| Q1 | Which responses are stored? | 2xx and deterministic 4xx; not 5xx, 401, 408, 423, 426, 429 | Marwan, 2026-11-06 |
| Q2 | What does the second concurrent request get if the first is still running after the wait? | 503 `SERVICE_UNAVAILABLE` with `Retry-After` | Marwan, 2026-11-06 |
| Q3 | Scope of the rule | Every Bearer write (POST, PUT, PATCH, DELETE) through one feature; `POST /sync/events` exempt by route name or list; web unaffected | Marwan, 2026-11-06 (brief) |
| Q4 | Storage | Redis cache store, 24 h TTL, no migration | Marwan, 2026-11-06 (brief) |
| Q5 | Anything the documents don't say? | See the brief | Marwan, 2026-11-06 |

## 9. Progress log

| Date | Step | Result |
| --- | --- | --- |
| 2026-11-06 | 1 | E1, E2, E3 plans brought in and fixed; restore added to E3; P1-C3 log row; this plan. Commit `cd12f9f`. |
| 2026-11-06 | 2 | `config/idempotency.php`, `IdempotencyStore`, `IdempotencyStoreTest` (27 tests pass on `atlantech_testing_id`). Docker Desktop was stopped; started it. Test file moved from Unit to Feature (Unit tests do not extend the app test case). Waiting for the lead's OK (mode C). |

## 10. Review

<Filled by `backend-task-review`.>
