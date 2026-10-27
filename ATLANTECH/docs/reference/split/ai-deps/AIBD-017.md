<!-- Generated from docs/reference/ai/ATLANTECH_AI_BACKEND_DEPENDENCIES.md lines 71-71 by build-index.mjs. Do not edit: edit the source and rebuild. -->
| AIBD-017 | Backend → Part 2 | Reservations (`plan_component` RESERVED/RELEASED/CONSUMED) as occupancy truth; booking re-check at confirmation | Required | DOCUMENTED | AI-050 |

<!-- Generated from docs/reference/ai/ATLANTECH_AI_BACKEND_DEPENDENCIES.md lines 126-126 by build-index.mjs. Do not edit: edit the source and rebuild. -->
**AIBD-017 — Reservations and booking.** Backend → Part 2. Purpose: occupancy = RESERVED `plan_component` rows overlapping a window (D-20 note); plan confirmation re-checks and books in one transaction (2.14). Required. DOCUMENTED. Consequence: wrong occupancy → infeasible or conflicting recommendations.
