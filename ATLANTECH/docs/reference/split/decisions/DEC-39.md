<!-- Generated from docs/reference/decisions/ATLANTECH_Decisions.md lines 1093-1117 by build-index.mjs. Do not edit: edit the source and rebuild. -->
### DEC-39 · CT Supervisor status list

| | |
| --- | --- |
| Source | Screens P-55 · Flutter tasks |
| Owner | Backend Team |
| Status | Open |
| Closing task | DEC-39 · Sprint 6 |
| Affects | P5-O8 · MB-P55 |
| Decision | ______ |

<div dir="rtl">

**القصة.** مشرف التيرمنال بيسجل حالة الشغل من التابلت، بس محدش حدد الحالات اللي هيختار منها. هل هي نفس حالات المرشد (بدأ وخلص)؟ ولا حالات خاصة بالتيرمنال؟

</div>

**Topic.** P-55 says the terminal status list needs confirmation.

**Options:**

1. The same task statuses: In progress and Executed.
2. Terminal-specific statuses from `status_types`.

**Recommendation.** Option 1 for the MVP: In progress and Executed on the terminal component.
