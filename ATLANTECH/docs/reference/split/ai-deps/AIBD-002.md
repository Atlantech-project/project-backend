<!-- Generated from docs/reference/ai/ATLANTECH_AI_BACKEND_DEPENDENCIES.md lines 56-56 by build-index.mjs. Do not edit: edit the source and rebuild. -->
| AIBD-002 | Backend → Part 2 | Enqueue initial solve (`NEW_REQUEST`) after window confirmation or plan cancellation with call continuing | Required | DOCUMENTED; payload PROPOSED | AI-003, AI-055, AI-058 |

<!-- Generated from docs/reference/ai/ATLANTECH_AI_BACKEND_DEPENDENCIES.md lines 102-102 by build-index.mjs. Do not edit: edit the source and rebuild. -->
**AIBD-002 — Initial solve job.** Backend → AI Part 2, async (`queue:part2`). Purpose: generate the first recommendation. Required. Contract C-02. Prerequisites: `ENTRY_WINDOW_CONFIRMED`, mode `FULL`, AIBD-013. Owner: Backend/AI. Status: DOCUMENTED interaction, PROPOSED payload. Failure: as AIBD-001. If unavailable: no recommendation; Gate 2 impossible.
