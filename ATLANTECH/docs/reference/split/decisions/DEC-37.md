<!-- Generated from docs/reference/decisions/ATLANTECH_Decisions.md lines 1039-1063 by build-index.mjs. Do not edit: edit the source and rebuild. -->
### DEC-37 · "Reject with reason" on P-44 and P-47

| | |
| --- | --- |
| Source | Screens P-44/P-47 · P5-M2 |
| Owner | Backend Team |
| Status | Open |
| Closing task | DEC-37 · Sprint 6 |
| Affects | P5-M2 · FE-067 · FE-070 |
| Decision | ______ |

<div dir="rtl">

**القصة.** الشاشة فيها زرار "رفض بسبب" للمرشد المقترح، بس الـ Backend مفيهوش endpoint للرفض، وفيه تأكيد أو استبدال بس. لو الـ Frontend عمل الزرار، مش هيلاقي حاجة يكلمها.

</div>

**Topic.** The Backend tasks only have confirm or replace.

**Options:**

1. Add reject with a reason (new endpoint and component status).
2. Confirm or replace only.

**Recommendation.** Option 2: replace covers the case and no new status is needed.
