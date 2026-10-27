<!-- Generated from docs/reference/uml/UML_Context.md lines 18-29 by build-index.mjs. Do not edit: edit the source and rebuild. -->
## 1. The system in brief

ATLANTECH is a port operations platform that plans how vessels enter a port and berth: the safe entry-time window, the berth, the tugs and the marine pilot, and, for container vessels, where containers go in the yard. It has two sides.

- **Central SaaS** (run by ATLANTECH). The SaaS Admin manages admin accounts, plan parameters, the governorate list, the registry of ports, the annual licenses and the billing records. A Check-in API answers the ports' daily license check-in, and a License Signer signs license tokens with a key held in a KMS. The SaaS never sees a port's operational data.
- **Port Server** (one per port, installed on premises in the port's LAN). It runs the API and web dashboard, the port database, Redis queues, a realtime server (Reverb: SSE to the web dashboard, WebSocket to the mobile app), the EDI Parser for BAPLIE files, a scheduler, and three Python AI workers:
  - **AI Part 1 — Predictive and Environmental**: fetches weather, wave and sea-level data (primary and secondary provider, every 6 hours), computes the astronomical tide daily, and proposes feasible entry-time windows. It never allocates a berth, tug or pilot.
  - **AI Part 2 — AI Engine**: predicts service time and delay risk and solves the berth, tug and pilot allocation with CP-SAT; on a technical solver failure it builds a FIFO fallback plan. It also generates yard placement recommendations.
  - **AI Part 3 — Feedback Loop**: evaluates outcomes on trusted data, watches model drift, applies forecast bias correction and retrains models under guard.
- **Mobile app** for field users (pilots, tug masters, terminal supervisors). It is offline-first: every change is written to a local Outbox and synchronized later. Phones are managed devices (MDM) and reach the server from outside the port through a per-app always-on VPN; the fixed address `app.atlantech.io` resolves to the port server through split DNS.

The port server contacts the Central SaaS only for the license (daily check-in), or receives a manual token file when it has no internet. Operational and training data never leave the port.
