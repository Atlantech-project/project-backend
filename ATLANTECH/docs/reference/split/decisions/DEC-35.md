<!-- Generated from docs/reference/decisions/ATLANTECH_Decisions.md lines 992-1016 by build-index.mjs. Do not edit: edit the source and rebuild. -->
### DEC-35 · Sign-in identifier

| | |
| --- | --- |
| Source | Screens P-16/P-52 · FR-AC-01 · FR-AC-09 |
| Owner | Backend Team |
| Status | Closed |
| Closing task | DEC-35 · Sprint 1 |
| Affects | P1-C1 · P1-C3 · FE-025 · MB-P52 |
| Decision | Closed (2026-10-28): Option 1. Users sign in with `username` on the web (P-16) and the tablet (P-52); the `users` table has no email column. `username` is unique and stored lowercase; `phone` is contact only and never used for sign-in. P-16 shows a Username field instead of Email. CT-03 and CT-04 use `{ "username", "password" }`. |

<div dir="rtl">

**القصة.** شاشة الدخول على الويب بتطلب email، بس الـ requirements بتقول إن المستخدم بيتعمل بـ username وتليفون ومن غير email خالص. يعني المستخدم مش هيلاقي حاجة يدخل بيها.

</div>

**Topic.** Screens: email on the web, username on the tablet. The FRD creates users with name, username and phone, with no email.

**Options:**

1. Username on web and tablet.
2. Add an email to users and use it on the web.

**Recommendation.** Option 1 because it matches the requirements. P-16 uses username instead of email.
