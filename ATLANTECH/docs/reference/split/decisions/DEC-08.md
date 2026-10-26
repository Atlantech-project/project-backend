<!-- Generated from docs/reference/decisions/ATLANTECH_Decisions.md lines 257-281 by build-index.mjs. Do not edit: edit the source and rebuild. -->
### DEC-08 · Database read source and worker privileges

| | |
| --- | --- |
| Source | D-09 · OD-21 · AIBD-028 |
| Owner | Backend Team |
| Status | Open |
| Closing task | DEC-08 · Sprint 1 |
| Affects | P3-I5 · AI-013 |
| Decision | ______ |

<div dir="rtl">

**القصة.** لو كل الـ workers داخلين على الـ database بنفس الحساب بكل الصلاحيات، أي bug في الـ parser ممكن يمسح جدول الخطط. ولو قرأوا من نسخة متأخرة، ممكن يحسبوا على رصيف اتحجز من ثواني.

</div>

**Topic.** Each worker must read port state and write only its own results.

**Options:**

1. Read from the primary, one account per worker with limited rights, `audit_log` INSERT/SELECT only.
2. Read from the replica and tolerate lag.

**Recommendation.** Option 1 (agreed); the Backend writes the grants script.
