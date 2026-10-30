<!-- Generated from docs/reference/decisions/ATLANTECH_Decisions.md lines 1017-1040 by build-index.mjs. Do not edit: edit the source and rebuild. -->
### DEC-36 · One role per user vs role history

| | |
| --- | --- |
| Source | Screens P-24 · P2-E2 |
| Owner | Backend Team |
| Status | Closed |
| Closing task | DEC-36 · Sprint 2 |
| Affects | P2-E2 · FE-045 |
| Decision | Closed (2026-10-28): Option 1. A user has one active role at a time. Assigning a new role ends the current one (`active_to` set) and inserts the new row in the same transaction; rows are never deleted, so `user_role_assignments` keeps the full history. Schema: `uq_ura_active` becomes unique on (`user_id`, `active_assignment_key`) so the database refuses a second active role. P-24 shows the active role and the history list. |

<div dir="rtl">

**القصة.** الشاشة بتقول إن كل مستخدم ليه دور واحد، والـ Backend معمول إنه يدّي أدوار وينهيها بتاريخ. لو موظف اتنقل من Tug Officer لـ Pilotage Officer، هل بيبقى معاه الاتنين؟ ولا القديم بينتهي؟

</div>

**Topic.** The screens say one role; the Backend assigns and ends roles with dates.

**Options:**

1. One active role at a time; history kept with `active_to`.

**Recommendation.** This option.
