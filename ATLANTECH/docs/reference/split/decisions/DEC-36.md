<!-- Generated from docs/reference/decisions/ATLANTECH_Decisions.md lines 1014-1037 by build-index.mjs. Do not edit: edit the source and rebuild. -->
### DEC-36 · One role per user vs role history

| | |
| --- | --- |
| Source | Screens P-24 · P2-E2 |
| Owner | Backend Team |
| Status | Open |
| Closing task | DEC-36 · Sprint 2 |
| Affects | P2-E2 · FE-045 |
| Decision | ______ |

<div dir="rtl">

**القصة.** الشاشة بتقول إن كل مستخدم ليه دور واحد، والـ Backend معمول إنه يدّي أدوار وينهيها بتاريخ. لو موظف اتنقل من Tug Officer لـ Pilotage Officer، هل بيبقى معاه الاتنين؟ ولا القديم بينتهي؟

</div>

**Topic.** The screens say one role; the Backend assigns and ends roles with dates.

**Options:**

1. One active role at a time; history kept with `active_to`.

**Recommendation.** This option.
