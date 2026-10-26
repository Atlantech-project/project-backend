<!-- Generated from docs/reference/ai/ATLANTECH_AI_BACKEND_DEPENDENCIES.md lines 88-88 by build-index.mjs. Do not edit: edit the source and rebuild. -->
| AIBD-034 | Backend ↔ Part 1 | Window confirmation re-check ("still passes") | Required | DOCUMENTED rule; mechanism OPEN (OD-03) | AI-004, AI-048 |

<!-- Generated from docs/reference/ai/ATLANTECH_AI_BACKEND_DEPENDENCIES.md lines 158-158 by build-index.mjs. Do not edit: edit the source and rebuild. -->
**AIBD-034 — Window re-check.** At confirmation the server checks that the window belongs to the latest result and "still passes" (2.11). Recommended: Backend checks "latest result" and that the underlying forecast run is still the newest usable; a full re-assessment is a recompute (C-01) [PROPOSED].
