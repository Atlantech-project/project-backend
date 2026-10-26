<!-- Generated from docs/reference/ai/ATLANTECH_AI_BACKEND_DEPENDENCIES.md lines 70-70 by build-index.mjs. Do not edit: edit the source and rebuild. -->
| AIBD-016 | Backend → AI | Master data maintained through Backend screens and validations | Required | DOCUMENTED | AI-050, AI-044, AI-045 |

<!-- Generated from docs/reference/ai/ATLANTECH_AI_BACKEND_DEPENDENCIES.md lines 124-124 by build-index.mjs. Do not edit: edit the source and rebuild. -->
**AIBD-016 — Master data.** Backend → AI. Purpose: every solver and Part 1 input. Required. Includes validations already documented: berth `max_vessel_loa ≤ length`, every berth has ≥ 1 `berth_terminal` row, non-overlapping safety constraints of the same type and scope, non-overlapping productivity periods, non-overlapping active shifts, one master per tug, datum equality on tide-set activation (AIBD-040). Failure: invalid master data → AI outcome with configuration reason. If unavailable: nothing can be computed.
