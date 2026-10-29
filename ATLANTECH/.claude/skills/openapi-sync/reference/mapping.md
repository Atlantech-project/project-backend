# Laravel → OpenAPI 3.0 mapping

## Paths and security

| Laravel | OpenAPI |
| --- | --- |
| `Route::get('/berthing-requests/{berthingRequest}')` under `/api/v1` | `paths: /api/v1/berthing-requests/{berthingRequest}: get:` with a path parameter |
| route name | `operationId` (same words, camelCase) |
| `auth:sanctum` on web routes (cookie + `X-XSRF-TOKEN`, CT-03) | `security: [{ cookieAuth: [] }]`; writes add `$ref: '#/components/parameters/XsrfToken'` |
| mobile token routes (CT-04) | `security: [{ bearerAuth: [] }]` plus `$ref` parameters `AppVersion`, `DeviceId`, and `IdempotencyKey` on writes |
| module | `tags: [<module or feature>]` |

## Requests (Form Request `rules()`)

| Rule | Schema |
| --- | --- |
| `required` | listed in `required: []` |
| `nullable` | `nullable: true` |
| `string`, `max:n` | `type: string, maxLength: n` |
| `integer`, `min:a`, `max:b` | `type: integer, minimum: a, maximum: b` |
| `numeric` | `type: number` |
| `boolean` | `type: boolean` |
| `ZonedDateTime::rules()` | `$ref: '#/components/schemas/DateTime'` (UTC, `Z` or offset required, CT-01) |
| `regex:/^[0-9]{7}$/` | `pattern: '^[0-9]{7}$'` |
| `Rule::enum(X::class)` / `in:` | `enum: [...]` with the SQL names (CT-21) |
| `array`, `*.field` | `type: array, items: { ... }` |
| `exists:table,id` | `type: integer` + description of the referenced resource |

## Responses

| Code | OpenAPI |
| --- | --- |
| `JsonResource` / `ResourceCollection` | `components/schemas/<Name>`; collections in the CT-01 list shape |
| `updated_at` (version token) | `$ref: '#/components/schemas/DateTime'`; it is the record version sent back on update (CT-01, `DATETIME(6)`) |
| 201 / 200 / 202 / 204 | success responses as the controller returns them |
| any `ErrorCode` the endpoint can return | `$ref: '#/components/responses/<ErrorCode case>'` (e.g. `ValidationFailed`, `VersionConflict`, `Forbidden`); 409 carries `current`, 422 `errors`, 426 `min_app_version`. A new `ErrorCode` case needs a new response component (the drift test checks it) |
| pagination (`PageRequest` + `PaginatedCollection`) | `$ref` parameters `Page`, `PerPage`; response `{ data: [...], meta: { $ref: PageMeta } }` |

## Checklist per endpoint

- [ ] Path, method, `operationId`, tag, security.
- [ ] Every request field with type, format, required, enum.
- [ ] Every success and error status the code or its tests can produce.
- [ ] Response schema matches the API Resource field by field, including `updated_at` on editable records.
- [ ] Lists use the CT-01 pagination shape.
- [ ] Enum values are the SQL names.
