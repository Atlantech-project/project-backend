<!-- Generated from docs/reference/decisions/ATLANTECH_Decisions.md lines 1553-1577 by build-index.mjs. Do not edit: edit the source and rebuild. -->
### DEC-59 · `port_settings` min and max, and CHAMPION `deployed_at`

| | |
| --- | --- |
| Source | CT-18 · `reference-seed.json` |
| Owner | Backend Team |
| Status | Open |
| Closing task | DEC-59 · Sprint 2 |
| Affects | P1-B6 · P2-F9 · P2 settings page (P-62) · AI-009 · CT-17 · CT-18 |
| Decision | ______ |

<div dir="rtl">

**القصة.** CT-18 بيطلب إن الـ min والـ max يتطبقوا لما الـ setting يتحفظ، بس جدول `port_settings` مفيهوش أعمدة ليهم. وكمان مثال الـ CHAMPION كان ناقصه `deployed_at`، فالصف ماكانش هيدخل الجدول أصلًا.

</div>

**Topic.** CT-18 needs min and max stored and enforced, and the schema has no columns for them. The CT-18 example also breaks the `chk_mlv_champion_deployed` check.

**Options:**

1. Add two columns to `port_settings`.
2. Read min and max from the seed files at runtime.

**Recommendation.** Option 1, chosen by the Backend lead on 2026-10-31, so the app does not depend on contract files in production. It closes when the AI Team approves CT-18 v1.1. A `CHAMPION` row in `seed-spec.json` also carries `deployed_at`.
