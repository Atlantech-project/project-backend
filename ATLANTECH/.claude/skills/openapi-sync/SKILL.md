---
name: openapi-sync
description: Compare the port API's real routes, requests and responses with the OpenAPI file (CT-02; source ATLANTECH/openapi/port-api.yaml, published to PortHub contracts/openapi/port-api.yaml) and update the file for the endpoints a branch adds or changes. Use in every task that adds or changes an endpoint (as a plan step), before a PR, or when asked "is the OpenAPI up to date".
---

# OpenAPI sync (CT-02)

Ownership follows DEC-13, closed 2026-10-29 with option 1:
- The Backend Team is the single owner of the file.
- The source is `ATLANTECH/openapi/port-api.yaml` in project-backend.
- Every PR that adds or changes an endpoint updates the source in that same PR.
- PortHub `contracts/openapi/port-api.yaml` (CT-02, `docs/reference/split/contracts/CT-02.md`) is a published copy. It is written only by `composer contracts:openapi`, through a **paired PortHub PR** opened together with the backend PR and linked from it.

Two checks enforce the rule:
- `tests/Feature/Api/OpenApiDriftTest.php` checks that routes and `paths` match, that the error codes match `ErrorCode`, and that the pagination bounds match `PageRequest`.
- The CI job `openapi` runs Spectral with `ATLANTECH/.spectral.yaml`.

Mapping rules: `reference/mapping.md`. Follow `context-rules`.

## 1. Find what changed

- The file: `openapi/port-api.yaml` on the branch. Its first version (P1-A4, `info.version` 0.1.0) holds only the shared CT-01 components.
- The endpoints: run `git diff origin/develop...HEAD --stat` (routes, controllers, Form Requests, API Resources, enums) and `php artisan route:list --path=api --json`.

## 2. Compare each changed endpoint

Using `reference/mapping.md`, for each method and path:

| Code side | OpenAPI side |
| --- | --- |
| route method, URI, middleware (auth web / mobile) | path, operation, `security`, header parameters |
| Form Request `rules()` | `requestBody` schema: required list, types, formats, patterns, enums |
| API Resource `toArray()` | response schema, including `version` (the `updated_at` value, the version token, date-time) |
| status codes the controller and tests produce | `responses`, errors as `$ref` to `components/responses/<ErrorCode case>` |
| pagination | `Page` / `PerPage` parameters and `{ data, meta: PageMeta }` |

Also list paths that exist on one side only: the drift test fails on them.

## 3. Update (mode C)

1. Edit `openapi/port-api.yaml` on the task branch.
   - Change only this task's operations and schemas, and reuse the components.
   - Keep names in the SQL / enum vocabulary (CT-21).
   - Bump `info.version` minor. It is 1.0.0 since CT-02; a breaking change is a major bump and `/api/v2`.
2. With the first path in the file, turn `oas3-unused-component` and `openapi-tags` back on in `.spectral.yaml`, and add the tag.
3. Run the drift test, then run the lint. In `ATLANTECH/`, the lint command is `npx --yes @stoplight/spectral-cli@<version in ci.yml> lint openapi/port-api.yaml --fail-severity=warn`.
4. Show the diff to the lead.
5. After the lead's OK, on PortHub branch `feature/<task-id>-openapi`:
   - Run `composer contracts:openapi`, which writes `../../contracts/openapi/port-api.yaml`.
   - Commit the copy, and push and open the PortHub PR to `main`.
   - Link that PR in the backend PR description (`backend-pr-prepare`) and in the readiness pack (`api-readiness-pack`, CT-23 links the OpenAPI diff).

## 4. Report

Use this format: `Endpoint | Change | OpenAPI before → after | PortHub commit`. Add anything still undocumented and why.

Never describe behaviour the code does not have: the file follows the code and CT-01, never the other way round.
