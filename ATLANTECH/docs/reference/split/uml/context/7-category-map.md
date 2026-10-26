<!-- Generated from docs/reference/uml/UML_Context.md lines 105-113 by build-index.mjs. Do not edit: edit the source and rebuild. -->
## 7. Category map

| Category | Use case | Sequence | Activity | State |
|---|---|---|---|---|
| 1. Central SaaS, Licensing and Billing | UC-1 | 1.1–1.14 | AD-1–AD-7, AD-20 | SM-1 License, SM-2 Port lifecycle, SM-3 Port-side license mode |
| 2. Port Setup, Operations and Recommendation | UC-2 | 2.1–2.17 | AD-8–AD-14, AD-18, AD-19 | SM-4 Berthing request, SM-5 Operational plan |
| 3. Execution Feedback and Model Learning | UC-3 | 3.1–3.4 | AD-15, AD-16, AD-17 | SM-6 Outbox event |

The end-to-end story across the categories: a port is registered, provisioned and licensed (category 1); it sets up its users and master data, receives a vessel's berthing request and BAPLIE, gets an entry window from AI Part 1 and a recommendation from AI Part 2, confirms the plan, dispatches pilots and tugs and plans the yard (category 2); field crews report what really happens from offline-capable phones, deviations trigger re-plans, and AI Part 3 learns from trusted outcomes (category 3). Every day, underneath, the port checks its license and decides whether it may write.
