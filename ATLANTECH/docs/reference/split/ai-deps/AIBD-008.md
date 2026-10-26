<!-- Generated from docs/reference/ai/ATLANTECH_AI_BACKEND_DEPENDENCIES.md lines 62-62 by build-index.mjs. Do not edit: edit the source and rebuild. -->
| AIBD-008 | Backend ↔ Part 2 / EDI | Validation of overrides, yard adjustments, BAPLIE corrections | Required | OPEN (OD-03) | AI-004, AI-031, AI-060, AI-067 |

<!-- Generated from docs/reference/ai/ATLANTECH_AI_BACKEND_DEPENDENCIES.md lines 114-114 by build-index.mjs. Do not edit: edit the source and rebuild. -->
**AIBD-008 — Validation requests.** Backend ↔ AI. Purpose: validate staff override (2.13), yard adjustment (AD-14 A17), BAPLIE correction (2.8). Required. Contract C-05 (if OD-03 option (a)). Status OPEN. Failure: validation unavailable → Backend must refuse the change (fail closed) [DERIVED: hard constraints are never bypassed]. If unavailable: overrides and adjustments cannot be accepted.
