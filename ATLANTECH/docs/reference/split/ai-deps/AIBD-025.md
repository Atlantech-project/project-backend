<!-- Generated from docs/reference/ai/ATLANTECH_AI_BACKEND_DEPENDENCIES.md lines 79-79 by build-index.mjs. Do not edit: edit the source and rebuild. -->
| AIBD-025 | Backend | Audit entries for state changes caused by AI results | Required | DOCUMENTED rule; mechanism OPEN (OD-02) | AI-058 |

<!-- Generated from docs/reference/ai/ATLANTECH_AI_BACKEND_DEPENDENCIES.md lines 140-140 by build-index.mjs. Do not edit: edit the source and rebuild. -->
**AIBD-025 — Audit.** Every state change is audited (UML rule 12); `audit_log.actor_id` NULL = system/AI worker. Recommended (OD-02): Backend writes the audit entry when it applies the AI result; AI does not write `audit_log` except where the decision assigns it.
