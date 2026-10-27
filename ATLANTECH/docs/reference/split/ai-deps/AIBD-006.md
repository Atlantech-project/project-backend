<!-- Generated from docs/reference/ai/ATLANTECH_AI_BACKEND_DEPENDENCIES.md lines 60-60 by build-index.mjs. Do not edit: edit the source and rebuild. -->
| AIBD-006 | Backend → Part 2 | Enqueue yard placement on plan confirmation (container vessel with ACTIVE BAPLIE) or planner request | Required | DOCUMENTED; payload PROPOSED | AI-066 |

<!-- Generated from docs/reference/ai/ATLANTECH_AI_BACKEND_DEPENDENCIES.md lines 110-110 by build-index.mjs. Do not edit: edit the source and rebuild. -->
**AIBD-006 — Yard job.** Backend → Part 2. Purpose: yard placement. Required for container terminals. Contract C-03. Prerequisites: plan confirmed, container vessel, ACTIVE BAPLIE, AIBD-014, mode `FULL` (else reason recorded and planner notified, AD-14). Failure: `GENERATION_FAILED` with reason. If unavailable: planner plans the yard manually; plan yard region stays `YARD_PENDING`.
