<!-- Generated from docs/reference/decisions/ATLANTECH_Decisions.md lines 1246-1270 by build-index.mjs. Do not edit: edit the source and rebuild. -->
### DEC-45 · Display time zone

| | |
| --- | --- |
| Source | FE-OQ-14 |
| Owner | Frontend Team |
| Status | Open |
| Closing task | DEC-45 · Sprint 1 |
| Affects | FE-006 · MB-F1 |
| Decision | ______ |

<div dir="rtl">

**القصة.** الـ database بتسجل كل حاجة بتوقيت UTC. لو الشاشة عرضت الوقت زي ما هو، الموظف هيشوف السفينة جاية الساعة 7 وهي فعلًا جاية الساعة 10 بتوقيت مصر.

</div>

**Topic.** The database is UTC and shifts are local time.

**Options:**

1. Display in port time from `port_profiles.timezone`.
2. User device time.

**Recommendation.** Option 1 on web and mobile.
