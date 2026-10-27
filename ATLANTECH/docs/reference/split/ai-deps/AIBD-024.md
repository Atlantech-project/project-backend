<!-- Generated from docs/reference/ai/ATLANTECH_AI_BACKEND_DEPENDENCIES.md lines 78-78 by build-index.mjs. Do not edit: edit the source and rebuild. -->
| AIBD-024 | Backend | Terminal-scoped exposure of AI outputs (403) | Required | DOCUMENTED | AI-083 |

<!-- Generated from docs/reference/ai/ATLANTECH_AI_BACKEND_DEPENDENCIES.md lines 138-138 by build-index.mjs. Do not edit: edit the source and rebuild. -->
**AIBD-024 — Terminal isolation.** Backend applies terminal-level row security to AI outputs: `terminal_recommendation` visible to its terminal only; manifest data stripped for other terminals (NFR-SEC-7). The AI computes port-wide and writes all rows; exposure filtering is the Backend's.
