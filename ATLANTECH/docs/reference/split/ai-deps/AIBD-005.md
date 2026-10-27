<!-- Generated from docs/reference/ai/ATLANTECH_AI_BACKEND_DEPENDENCIES.md lines 59-59 by build-index.mjs. Do not edit: edit the source and rebuild. -->
| AIBD-005 | Backend → Part 2 | Enqueue delay re-plan (`REPLAN`) on significant deviation | Required | DOCUMENTED; payload PROPOSED | AI-059 |

<!-- Generated from docs/reference/ai/ATLANTECH_AI_BACKEND_DEPENDENCIES.md lines 108-108 by build-index.mjs. Do not edit: edit the source and rebuild. -->
**AIBD-005 — Delay re-plan.** Backend → Part 2. Purpose: proposal version `DELAY_REPLAN` after a significant deviation. Required. Contract C-02 with `trigger_type = REPLAN`. Prerequisites: AIBD-020 significance rule; plan `CONFIRMED`; mode not `READ_ONLY` (else staff are notified, no proposal). Failure: no proposal; current plan stays. If unavailable: delays are handled manually by cancellation and new recommendation.
