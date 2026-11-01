<!-- Generated from docs/reference/decisions/ATLANTECH_Decisions.md lines 712-735 by build-index.mjs. Do not edit: edit the source and rebuild. -->
### DEC-24 · Part 1 settings: horizon, time step, minimum window, slot

| | |
| --- | --- |
| Source | OD-09 |
| Owner | AI Team |
| Status | Open |
| Closing task | DEC-24 · Sprint 4 |
| Affects | AI-047 · AI-048 |
| Decision | ______ |

<div dir="rtl">

**القصة.** الـ AI بيدوّر على نوافذ دخول آمنة. بيدوّر لقدّام قد إيه؟ بيفحص كل كام دقيقة؟ وأقل نافذة مقبولة طولها قد إيه؟ من غير الأرقام دي الحساب مش هيبدأ.

</div>

**Topic.** The window computation needs fixed values set by the Marine Safety Officer and staff.

**Options:**

1. Horizon 72 hours, step 30 minutes, minimum window 60 minutes, demand slot 60 minutes (proposed).

**Recommendation.** Approve the proposed values as `port_settings` keys, changeable later without code.
