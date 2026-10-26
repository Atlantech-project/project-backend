<!-- Generated from docs/reference/decisions/ATLANTECH_Decisions.md lines 1117-1140 by build-index.mjs. Do not edit: edit the source and rebuild. -->
### DEC-40 · Mobile Acknowledge event type and payload

| | |
| --- | --- |
| Source | Flutter R1 |
| Owner | Backend Team |
| Status | Open |
| Closing task | DEC-40 · Sprint 6 |
| Affects | P5-O3 · MB-P55 · MB-P19 · CT-07 |
| Decision | ______ |

<div dir="rtl">

**القصة.** المرشد بيدوس "استلمت" على الإشعار وهو مش متصل بالنت، فالحدث بيتحفظ على الجهاز ويتبعت بعدين. لو شكل الحدث مش متفق عليه، الـ Backend هيرفضه، والنظام هيفضل يصعّد كأن المرشد ماستلمش.

</div>

**Topic.** Acknowledge goes through the Outbox and needs an agreed event type.

**Options:**

1. `event_type = "ACKNOWLEDGE"` and `payload = {notification_id}`.

**Recommendation.** This option, documented in CT-07.
