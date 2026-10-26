<!-- Generated from docs/reference/ai/ATLANTECH_AI_BACKEND_DEPENDENCIES.md lines 65-65 by build-index.mjs. Do not edit: edit the source and rebuild. -->
| AIBD-011 | Backend | Request status transitions, history rows and run-row creation around AI results | Required | OPEN (OD-02) | AI-002, AI-058 |

<!-- Generated from docs/reference/ai/ATLANTECH_AI_BACKEND_DEPENDENCIES.md lines 120-120 by build-index.mjs. Do not edit: edit the source and rebuild. -->
**AIBD-011 — Status transitions and run rows.** Backend. Purpose: keep every `berthing_request` transition with its history row in one transaction (PortSync `berthing_request` note) and keep SM-4 authoritative. Required. Status OPEN (OD-02). Recommended: Backend performs `ENTRY_WINDOW_CONFIRMED`, `RECOMMENDATION_GENERATED`, `PLAN_*` transitions on receipt of AIBD-010; AI never writes `berthing_request.status`. Consequence if undecided: two writers of the same state.
