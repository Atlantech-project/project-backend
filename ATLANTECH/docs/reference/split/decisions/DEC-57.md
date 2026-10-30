<!-- Generated from docs/reference/decisions/ATLANTECH_Decisions.md lines 1506-1523 by build-index.mjs. Do not edit: edit the source and rebuild. -->
### DEC-57 · Port API status codes, error catalogue and version token

| | |
| --- | --- |
| Source | CT-01 draft and FR-GL-08 vs the code; NFR-CONC-1 vs FR-GL-05 |
| Owner | Backend Team |
| Status | Closed |
| Closing task | CT-01 |
| Affects | FR-GL-08 · NFR-CONC-1 · CT-01 · P1-A2 · P1-C1 · P2-D4 · FE-030 |
| Decision | Closed: (1) the API status set adds 400, 404, 405, 419, 429 and 500 to FR-GL-08's list, with 423 reserved (DEC-54). (2) Stable codes live in `contracts/error-codes.md`. (3) The version token is `updated_at DATETIME(6)`, returned as `version`, not an integer. (4) v1.0 of CT-01 is adopted by the Backend lead without team review. (5) The Backend code is the source of truth for CT-01 and CT-02: Frontend and Mobile review critical changes only, and QA tests without reviewing. |

<div dir="rtl">

**القصة.** الـ FR-GL-08 كان بيعد 7 أكواد بس، والـ API بيرجع أكواد تانية زي 404 و 405 و 419. فاتسجلت القايمة الكاملة، والأكواد الثابتة اتنقلت لملف لوحدها. وكمان الـ NFR-CONC-1 كان بيقول version رقم، والـ FR-GL-05 بيقول `updated_at`، فاتثبت `updated_at` بدقة الـ microseconds.

</div>

**Topic.** The full API status set, one error catalogue file, and one version token.
