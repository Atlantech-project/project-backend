<!-- Generated from docs/reference/ai/ATLANTECH_AI_BACKEND_DEPENDENCIES.md lines 63-63 by build-index.mjs. Do not edit: edit the source and rebuild. -->
| AIBD-009 | Backend → Part 1 | Manual environmental data stored as a forecast run, then recompute | Required | DOCUMENTED | AI-041, AI-048 |

<!-- Generated from docs/reference/ai/ATLANTECH_AI_BACKEND_DEPENDENCIES.md lines 116-116 by build-index.mjs. Do not edit: edit the source and rebuild. -->
**AIBD-009 — Manual environmental data.** Backend → Part 1 through the database. Purpose: allow assessment when providers fail. Required. Backend validates completeness and horizon coverage (422 otherwise), stores `forecast_run` (`pull_type = MANUAL`, `provider_rank = MANUAL`, `submitted_by`, unit columns) and points, audits, then enqueues C-01 with `reason = MANUAL_DATA`. Data passes the same safety constraints — no bypass (AD-10). If unavailable: requests wait until provider data returns.
