<!-- Generated from docs/reference/uml/UML_Context.md lines 86-96 by build-index.mjs. Do not edit: edit the source and rebuild. -->
## 5. Answer codes

| Code | Meaning |
|---|---|
| 401 | Not signed in, or the session or token is no longer valid (also a revoked token). |
| 403 | Signed in but not allowed: role, terminal scope, or `ACCOUNT_DISABLED`. |
| 409 | Conflict with the current state: duplicate BAPLIE, edit after approval, stale record version, regenerate on a confirmed plan, cancel after start. |
| 422 | Invalid input or business rule: no matching request, plan limit exceeded, live billing transaction exists, manual token for a non-active license. |
| 423 | `LICENSE_READ_ONLY`: the current license mode does not allow the action. |
| 426 | The mobile app is below `min_app_version`. |
| 503 | Token signing failed and no still-valid token exists (check-in). |
