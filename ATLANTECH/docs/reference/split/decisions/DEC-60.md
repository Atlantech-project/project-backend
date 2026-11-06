<!-- Generated from docs/reference/decisions/ATLANTECH_Decisions.md lines 1864-1888 by build-index.mjs. Do not edit: edit the source and rebuild. -->
### DEC-60 · Mobile sign-in from a revoked device

| | |
| --- | --- |
| Source | P1-C3 · lead's answer L2 (Marwan, 2026-11-01) |
| Owner | Backend Team |
| Status | Closed |
| Closing task | P1-C3 |
| Affects | P1-C3 · P2-E3 · MB-P52 · CT-01 (`error-codes.md`) · CT-04 |
| Decision | Closed (2026-11-01): Option 1. A device the Port Admin revoked (`user_devices.revoked_at` set) is refused at mobile sign-in with 403 `DEVICE_REVOKED` (`errors.device_revoked`), audited, and no token is issued. A token of a revoked device is revoked on its next use and gets 401. CT-04 v1.2 carries the detail; it waits for Mobile Team approval, and P1-C3 does not merge before the contract change. |

<div dir="rtl">

**القصة.** الـ Port Admin بيلغي جهاز (tablet) اتسرق أو ضاع، وبعدين اليوزر يحاول يسجّل دخول من نفس الجهاز. العقد ماكانش بيقول إيه اللي يحصل، والـ error codes مفيهاش كود للحالة دي.

</div>

**Topic.** CT-04 lists no refusal for a sign-in from a revoked device, and `error-codes.md` has no code for it.

**Options:**

1. Refuse the sign-in with a new stable code, `DEVICE_REVOKED` 403.
2. Let the user sign in again and clear `revoked_at`.

**Recommendation.** Option 1, chosen by the Backend lead on 2026-11-01: revoking a lost or stolen tablet only works if the device cannot sign itself back in. The code name was proposed by the developer on 2026-11-05.
