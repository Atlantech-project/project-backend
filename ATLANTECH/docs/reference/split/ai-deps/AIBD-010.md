<!-- Generated from docs/reference/ai/ATLANTECH_AI_BACKEND_DEPENDENCIES.md lines 64-64 by build-index.mjs. Do not edit: edit the source and rebuild. -->
| AIBD-010 | AI → Backend | Completion signal so the Backend can push SSE and notifications | Required | OPEN (OD-01) | AI-002, AI-011 |

<!-- Generated from docs/reference/ai/ATLANTECH_AI_BACKEND_DEPENDENCIES.md lines 118-118 by build-index.mjs. Do not edit: edit the source and rebuild. -->
**AIBD-010 — Completion signal.** AI → Backend. Purpose: Backend learns a job finished, pushes SSE (≤ 5 s after commit, NFR-PERF-4) and creates notifications. Required. Contract C-06. Status OPEN (OD-01). Failure: missed signal → safety-net poller [PROPOSED]. If unavailable: users see results only after refresh.
