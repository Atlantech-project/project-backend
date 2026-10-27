<!-- Generated from docs/reference/decisions/ATLANTECH_Decisions.md lines 732-755 by build-index.mjs. Do not edit: edit the source and rebuild. -->
### DEC-25 · Uncertainty margin and window confidence

| | |
| --- | --- |
| Source | OD-11 |
| Owner | AI Team |
| Status | Open |
| Closing task | DEC-25 · Sprint 3 |
| Affects | AI-044 · AI-048 |
| Decision | ______ |

<div dir="rtl">

**القصة.** التوقعات الجوية مش مضمونة 100%. المفروض نسيب هامش أمان، ونعرض للموظف "واثقين من النافذة دي بنسبة كذا". بس حساب الهامش ده اتأجل لـ future work، فلازم نقرر نعرض إيه دلوقتي.

</div>

**Topic.** A6 (margin) is future work, so MVP behaviour must be defined.

**Options:**

1. Zero margin and confidence = NULL.

**Recommendation.** This option. P-37 shows no confidence value.
