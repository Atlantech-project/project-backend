<!-- Generated from docs/reference/decisions/ATLANTECH_Decisions.md lines 153-177 by build-index.mjs. Do not edit: edit the source and rebuild. -->
### DEC-04 · Job envelope, versioning, retries and stale-run timeout

| | |
| --- | --- |
| Source | D-04 · OD-22 |
| Owner | AI Team |
| Status | Open |
| Closing task | DEC-04 · Sprint 1 |
| Affects | P3-I1 · AI-011 · CT-08 |
| Decision | ______ |

<div dir="rtl">

**القصة.** الـ Laravel بيحط الـ jobs في Redis بصيغة PHP، والـ worker مكتوب Python ومش هيعرف يقراها. ولو الـ worker وقع وهو شغال، الـ run هيفضل "RUNNING" للأبد. محتاجين نتفق على شكل الرسالة، ونعيد المحاولة كام مرة، وإمتى نعتبر الـ run مات.

</div>

**Topic.** The default Laravel queue format serialises PHP classes that Python cannot read.

**Options:**

1. JSON envelope pushed with RPUSH onto the documented queue name, `contract_version = "1.0"`.
2. Laravel job classes with a decoder in Python.

**Recommendation.** Option 1 (agreed), with unprefixed key names. Retries: Part 1 up to 3 (documented), others once. A run left RUNNING longer than `run_stale_after_seconds` is marked FAILED.
