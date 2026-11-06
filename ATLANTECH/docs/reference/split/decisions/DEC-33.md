<!-- Generated from docs/reference/decisions/ATLANTECH_Decisions.md lines 1223-1247 by build-index.mjs. Do not edit: edit the source and rebuild. -->
### DEC-33 · Manual-planning baseline

| | |
| --- | --- |
| Source | OD-20 |
| Owner | AI Team |
| Status | Open |
| Closing task | DEC-33 · Sprint 7 |
| Affects | AI-084 |
| Decision | ______ |
| Proposed change | 2026-10-31 · AI-008 · Draft manual-planning baseline procedure (option 1): (1) **Scenarios:** agreed replay scenarios built from the reference-port data (CT-25, AI-022), each value labelled published or synthetic. (2) **Manual plan:** each scenario is planned with documented manual planning rules agreed with a domain expert. **These rules are still pending** (QA-AI1). (3) **AI and FIFO:** the same scenarios run through the AI recommendation and the FIFO fallback. (4) **Indicators:** the three plans are compared on the six Master_Context §4 indicators: conflicts detected and resolved; resource utilisation (berths, tugs, pilots, equipment); avoidable idle time; estimated operational cost; feasibility and quality of recommendations; downstream effect on re-handling where demonstrated. (5) **Roles:** the QA Team prepares the procedure and scenarios, a domain expert agrees the manual rules, and the AI Team runs the comparison in AI-084 · Reason: AI-008 acceptance criterion "Manual baseline procedure documented"; AI-084 needs a defined baseline · Evidence: Master_Context §4 · Proposal §12 (Table 8) · DEC-33 option 1 · AI-084 · Impact: AI-062 · AI-084; QA Team (QA-AI1, CT-25) · Awaiting: AI Team owner; QA Team and a domain expert for the manual rules |

<div dir="rtl">

**القصة.** عشان نقول إن الـ AI أحسن من التخطيط اليدوي، لازم نعرف التخطيط اليدوي بيتعمل إزاي بالظبط ونقيسه على نفس السيناريوهات. من غير كده المقارنة مالهاش معنى.

</div>

**Topic.** Evaluating the AI needs a comparison with manual planning.

**Options:**

1. Replay scenarios with documented manual rules agreed with an expert.

**Recommendation.** This option; the QA Team prepares the procedure (QA-AI1).
