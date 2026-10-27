---
name: openapi-sync
description: Compare the port API's real routes, requests and responses with the OpenAPI file (CT-02, PortHub contracts/openapi/port-api.yaml) and update the file for the endpoints a branch adds or changes. Use in every task that adds or changes an endpoint (as a plan step), before a PR, for P1-A4, or when asked "is the OpenAPI up to date".
---

# OpenAPI sync (CT-02)

CT-02 (`docs/reference/split/contracts/CT-02.md`): OpenAPI 3.0 file `contracts/openapi/port-api.yaml` in the **PortHub** repository, owned by the Backend Team, updated with every endpoint change. The code lives in project-backend, so "the same PR" means a **paired PortHub PR** opened together with the backend PR and linked from it. DEC-13 (owner and rule) may still be Open: check `node docs/reference/lookup.mjs DEC-13` and follow its decision once Closed. Mapping rules: `reference/mapping.md`. Follow `context-rules`.

## 1. Find what changed

- The file: PortHub `origin/main:contracts/openapi/port-api.yaml`. If it does not exist yet, P1-A4 has not been done: create it from the CT-02 example in that task, not silently in another.
- The endpoints: `git diff origin/develop...HEAD --stat` (routes, controllers, Form Requests, API Resources, enums) and `php artisan route:list --path=api --json`.

## 2. Compare each changed endpoint

Using `reference/mapping.md`, for each method and path:

| Code side | OpenAPI side |
| --- | --- |
| route method, URI, middleware (auth web / mobile) | path, operation, `security` |
| Form Request `rules()` | `requestBody` schema: required list, types, formats, patterns, enums |
| API Resource `toArray()` | response schema, including `version` (integer) |
| status codes the controller and tests produce | `responses`, errors as `$ref` to the CT-01 error shapes and codes |
| pagination | the CT-01 list shape `{ data, meta: { page, per_page, total } }` |

Also list paths that exist on one side only.

## 3. Update (mode C)

On a PortHub branch `feature/<task-id>-openapi`: edit only the operations and schemas of this task; reuse components; keep names in the SQL / enum vocabulary (CT-21). Parse the YAML before committing (`node -e` with a YAML parser only if one is already installed; otherwise ask before adding a tool). Show the diff to the lead; push and open the PortHub PR only after the OK, then link it in the backend PR description (`backend-pr-prepare`) and the readiness pack (`api-readiness-pack`).

## 4. Report

`Endpoint | Change | OpenAPI before → after | PortHub commit`, plus anything still undocumented and why. Never describe behaviour the code does not have: the file follows the code and CT-01, never the other way round.
