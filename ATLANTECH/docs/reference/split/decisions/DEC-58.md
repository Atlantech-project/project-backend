<!-- Generated from docs/reference/decisions/ATLANTECH_Decisions.md lines 1527-1551 by build-index.mjs. Do not edit: edit the source and rebuild. -->
### DEC-58 · Field task details from the CT-07 Mobile review

| | |
| --- | --- |
| Source | CT-07 review on PortHub PR #27 |
| Owner | Backend Team |
| Status | Closed |
| Closing task | — |
| Affects | P1-C5 · P5-N3 · P5-O1 · P5-O3 · MB-P19 · MB-P54 · MB-P55 · CT-04 · CT-07 |
| Decision | Closed (2026-10-31): (1) Acknowledging any notification of a re-sent task marks all its `SENT` rows `ACKNOWLEDGED`. (2) `notification_logs.status` stays `SENT` · `FAILED` · `ACKNOWLEDGED`; the `ESCALATED` value in the CT-21 example is corrected there. (3) Tasks carry a server-set `system_label` for the P-55 labels (CT-07). (4) `GET /api/v1/system/info` is the connectivity check, with no `GET /system/ping` (CT-04 v1.1). |

<div dir="rtl">

**القصة.** الـ Mobile راجع CT-07 ولقى حاجات مش متحددة: الإشعار بيتبعت أكتر من مرة، فالتأكيد يسري على مين؟ والشاشة بتعرض labels ملهاش مصدر، والـ UML بيعتمد على ping مش موجود في أي عقد.

</div>

**Topic.** The review found gaps between CT-07, CT-21, the P-55 screen and the UML.

**Options:**

1. For the labels: the app works them out from its cache, with a `reason` on `removed`.
2. For the labels: the server sends `system_label`.

**Recommendation.** The Backend recommended Option 1 to save time. The lead chose Option 2, so every client gets the same answer.
