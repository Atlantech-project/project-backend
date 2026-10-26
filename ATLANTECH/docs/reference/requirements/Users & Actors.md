# ATLANTECH System Actors & Responsibilities

Actor names follow the UML diagrams (Use Case, Sequence, Activity and State diagrams). An actor appears here only if it appears in the diagrams.

Terminal type: CT = CONTAINER, NCT = NON_CONTAINER in the database. **Role code** is the role value stored in `user_role_assignment.role`.

---

## 1. Central Platform (ATLANTECH side)

| Actor | Role code | Responsibilities |
|---|---|---|
| **SaaS Admin** | no port role | Runs the central platform. Signs in with a password plus an email one-time code. Manages SaaS Admin accounts (each carries a logical role: Platform, License or Billing), plan parameters, the governorate list and the platform audit log, and monitors the port fleet and license status. Owns the port registry and lifecycle: registers a port deployment, updates the registry record, rotates the port API credential, and activates/reinstates, suspends or offboards a port. Issues, renews early, suspends, reinstates and revokes licenses and license tokens; generates and delivers manual token files; rotates the signing key. Records billing transactions, payment outcomes, failed payments, refunds and cancellations, and reviews billing history (payment itself happens outside the system). Has no access to any port's operational data. |
| **Support Engineer** | no port role | ATLANTECH's technical person. Provisions the port server with the signed build, signs the port's TLS certificate with the offline ATLANTECH Root CA after checking the CSR against the registry, bootstraps the port instance and seeds the first Port Admin with a one-time password. Gives remote install and support sessions only inside a session opened by the port. |

---

## 2. Port Administration and IT

| Actor | Role code | Responsibilities |
|---|---|---|
| **Port Admin** | `PORT_GM` | The port's application administrator. Manages port users, assigns roles and terminal scope, manages devices and revokes tokens. Maintains the port profile and channel, terminals and berths, and views the port audit log (master-data history is the audit log filtered by entity). Installs the initial license, triggers *Check In Now*, uploads a manual license token and views the license status. Is the only actor who can cancel a plan after execution has started, as a recorded **safety exception** with a mandatory reason (allowed even in read-only mode). Reviews a flagged AI model candidate. |
| **Port IT Administrator** | no port role | The port's own IT person. Prepares the server and network: firewall rules, internal split DNS, Root CA trust on office PCs, VPN gateway, and connectivity troubleshooting. Deploys the build and verifies its signature, generates the server key and CSR (the private key never leaves the server), installs and renews the port TLS certificate. Opens, closes and audits onboarding and support sessions. Enrolls devices in MDM, revokes device certificates and performs selective wipes. |

---

## 3. Port Authority

| Actor | Role code | Responsibilities |
|---|---|---|
| **Port Authority Staff** | `PORT_STAFF` | Handles each vessel call end to end. Creates, edits or cancels the berthing request; records preliminary approval, returns or rejects a request, and records Customs approval. Confirms, rejects all or reopens the computed entry-time window, and can submit manual environmental data. Uploads the BAPLIE file, reviews its diagnostics, corrects staged data or withdraws the upload. Reviews the AI recommendation and its rationale, overrides components, confirms the operational plan or cancels it before execution starts. Browses deviations and reviews clock-skew-suspect events. |
| **Marine Safety Officer** | `MARINE_SAFETY_OFFICER` | Defines safety constraints and environmental limits, the chart datum and the under-keel clearance used in the port's entry decisions. Acts **before** the recommendation is generated, and these limits apply to every vessel and terminal in the port. |

---

## 4. Pilotage Department

| Actor | Role code | Responsibilities |
|---|---|---|
| **Pilotage Dept. Manager** | `PILOTAGE_MANAGER` | Manages the pilot roster. |
| **Pilotage Dept. Officer** | `PILOTAGE_OFFICER` | Confirms or replaces the proposed pilot and assigns the pilotage component of the confirmed plan. |
| **Marine Pilot** | `MARINE_PILOT` | Receives pilotage tasks on the mobile app, acknowledges notifications and reports actual task status, including offline through the app's Outbox. |

---

## 5. Tug Department

| Actor | Role code | Responsibilities |
|---|---|---|
| **Tug Dept. Manager** | `TUG_MANAGER` | Manages the tugboat fleet and the Tug Masters. |
| **Tug Dept. Officer** | `TUG_OFFICER` | Confirms or replaces the proposed tug and Tug Master and assigns the tug component of the confirmed plan. |
| **Tug Master** | `TUG_MASTER` | Receives tug tasks on the mobile app, acknowledges notifications and reports actual task status, including offline through the app's Outbox. |

> In Use Case Diagram 2, Marine Pilot and Tug Master are drawn as one actor: **Marine Pilot / Tug Master**.

---

## 6. Container Terminal (CT)

| Actor | Role code | Responsibilities |
|---|---|---|
| **CT Administrator** | `TERMINAL_ADMIN` (CT unit) | Manages CT equipment and cranes, and the container yard. |
| **CT Operation Planner** | `TERMINAL_OFFICER` | Accepts, adjusts or rejects the yard placement recommendation, then enters the TOS-ready plan into the terminal's TOS by hand and records the export (there is no TOS integration). |

---

## 7. Non-Container Terminal (NCT)

| Actor | Role code | Responsibilities |
|---|---|---|
| **NCT Administrator** | `TERMINAL_ADMIN` (`NCT` unit) | Manages NCT storage master data. |

---

## 8. Generic Users

These actors stand for any port user acting through a given channel. A real person (for example a Marine Pilot) can act as several of them.

| Actor | Role code | Responsibilities |
|---|---|---|
| **Office User** | any port role | Any staff member on the web dashboard. Logs in and out, views the unified port state and environmental conditions, and receives live dashboard updates. |
| **Mobile User** | any port role | Any staff member on the mobile app. Logs in and out, identifies the port on first launch, views assigned tasks, works offline with local data, synchronizes the Outbox, re-authenticates and resumes the Outbox, and receives realtime updates. |
| **Remote Field User** | `MARINE_PILOT` or `TUG_MASTER` | A field user outside port Wi-Fi (pilot, tug master). Connects through the per-app always-on VPN, updates actual task status, and keeps realtime updates alive in the background. |
| **On-site Field User** | `TERMINAL_SUPERVISOR` | A field user inside the terminal. Updates actual task status and terminal actual status. |
| **Port User** | any port role | Any port user who sees the token-expiry or read-only banner, or attempts a write action that license mode or plan limits restrict. |

---

## 9. System Actors

| Actor | Role |
|---|---|
| **Scheduler** | Updates license status by date, runs the port's daily check-in, sends task notifications and escalations, and polls weather data every 6 hours. |
| **KMS / License Signer** | Signs license tokens and holds the rotating signing key. |
| **Port Check-in Client** | The port server's side of the check-in, processed by the central SaaS. |
| **Central SaaS Check-in API** | Receives the port's daily check-in and returns a fresh token and port status. |
| **VPN Gateway** | Opens and closes onboarding/support sessions and validates device certificates for the always-on VPN. |
| **EDI Parser** | Parses and validates BAPLIE files. |
| **AI Part 1 Engine** | Computes the entry-time window, using on-demand weather and sea level and the astronomical tide. |
| **AI Part 2 Engine** | Generates port-wide recommendations, re-generations and delay re-plans, and yard placement recommendations. |
| **AI Part 3 Engine** | Evaluates outcomes, drift and forecast bias, and retrains and conditionally deploys models. |
| **Weather and Sea-Level Providers** | External sources for scheduled and on-demand weather and sea-level data. |
| **MDM Platform** | Issues device certificates, distributes the app and VPN profile, and performs certificate revocation and selective wipe. |
| **Managed Mobile Device** | Receives the app and VPN profile from MDM. |
| **Port Server Sync Endpoint** | Applies Outbox events idempotently, rejecting invalid ones and flagging clock-skew-suspect ones. |
| **Realtime Server** | Pushes realtime updates to the mobile app and live updates to the dashboard. |
