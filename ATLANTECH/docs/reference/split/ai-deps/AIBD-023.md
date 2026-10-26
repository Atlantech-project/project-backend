<!-- Generated from docs/reference/ai/ATLANTECH_AI_BACKEND_DEPENDENCIES.md lines 77-77 by build-index.mjs. Do not edit: edit the source and rebuild. -->
| AIBD-023 | Backend | Licence-mode gating before enqueue; rules for scheduled AI jobs in `READ_ONLY` | Required | DOCUMENTED for user actions; OPEN for scheduled jobs (OD-18) | AI-002, AI-082 |

<!-- Generated from docs/reference/ai/ATLANTECH_AI_BACKEND_DEPENDENCIES.md lines 136-136 by build-index.mjs. Do not edit: edit the source and rebuild. -->
**AIBD-023 — Licence gating.** Backend refuses enqueue for creation/regeneration in `READ_ONLY` (423) and records the reason for yard requests (AD-14). Scheduled pulls and Part 3 in `READ_ONLY`: OPEN (OD-18).
