# Laravel → OpenAPI 3.0 mapping

## Paths and security

| Laravel | OpenAPI |
| --- | --- |
| `Route::get('/berthing-requests/{berthingRequest}')` under `/api/v1` | `paths: /api/v1/berthing-requests/{berthingRequest}: get:` with a path parameter |
| route name | `operationId` (same words, camelCase) |
| `auth:sanctum` on web routes (cookie + `X-XSRF-TOKEN`, CT-03) | `security: [{ cookieAuth: [] }]` |
| mobile token routes (CT-04) | `security: [{ bearerAuth: [] }]` plus header parameters `X-App-Version`, `X-Device-Id`, and `Idempotency-Key` on writes |
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
| `date` / ISO datetime | `type: string, format: date-time` (UTC, CT-01) |
| `regex:/^[0-9]{7}$/` | `pattern: '^[0-9]{7}$'` |
| `Rule::enum(X::class)` / `in:` | `enum: [...]` with the SQL names (CT-21) |
| `array`, `*.field` | `type: array, items: { ... }` |
| `exists:table,id` | `type: integer` + description of the referenced resource |

## Responses

| Code | OpenAPI |
| --- | --- |
| `JsonResource` / `ResourceCollection` | `components/schemas/<Name>`; collections in the CT-01 list shape |
| `version` field | `type: integer` (record version, CT-01) |
| 201 / 200 / 202 / 204 | success responses as the controller returns them |
| 401 · 403 · 404 · 409 · 422 · 423 · 426 · 429 · 500 · 503 | `$ref: '#/components/responses/<Name>'`, each an RFC 9457 body with `code` and `message_key` from the CT-01 error catalogue; 409 includes `current`, 422 includes `errors` |

## Checklist per endpoint

- [ ] Path, method, `operationId`, tag, security.
- [ ] Every request field with type, format, required, enum.
- [ ] Every success and error status the code or its tests can produce.
- [ ] Response schema matches the API Resource field by field, including `version`.
- [ ] Lists use the CT-01 pagination shape.
- [ ] Enum values are the SQL names.
