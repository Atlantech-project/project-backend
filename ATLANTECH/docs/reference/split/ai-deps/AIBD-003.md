<!-- Generated from docs/reference/ai/ATLANTECH_AI_BACKEND_DEPENDENCIES.md lines 57-57 by build-index.mjs. Do not edit: edit the source and rebuild. -->
| AIBD-003 | Backend → Part 2 | Enqueue regeneration (`MANUAL`) before confirmation | Required | DOCUMENTED; payload PROPOSED | AI-059 |

<!-- Generated from docs/reference/ai/ATLANTECH_AI_BACKEND_DEPENDENCIES.md lines 104-104 by build-index.mjs. Do not edit: edit the source and rebuild. -->
**AIBD-003 — Regenerate (MANUAL).** Backend → Part 2. Purpose: staff ask for a new recommendation before confirmation. Required. Contract C-02 with `trigger_type = MANUAL`. Prerequisites: no confirmed plan (409 otherwise), mode `FULL`. Failure: previous version stays current (2.13). If unavailable: staff can only override or confirm the current version.
