<!-- Generated from docs/reference/uml/UML_Context.md lines 31-47 by build-index.mjs. Do not edit: edit the source and rebuild. -->
## 2. Actors

| Actor | Side | Role in the diagrams |
|---|---|---|
| SaaS Admin | Central | Runs the platform: admins, plans, governorates, port registry and lifecycle, licenses, tokens, billing records, fleet monitoring. Signs in with password and email one-time code. |
| ATLANTECH Support Engineer | Central | Deploys the signed build, validates and signs the port's TLS certificate with the offline Root CA, bootstraps the port instance, gives remote support within an approved ticket. |
| Port IT Administrator | Port | Prepares the network, firewall, split DNS, VPN and MDM; generates the server key and CSR; installs certificates; opens and closes onboarding and support sessions; enrolls and revokes devices. |
| Port Admin | Port | Application administrator: users, roles, devices and tokens, port profile, terminals and berths; installs or uploads the license token; triggers a check-in; the only one who can use the safety exception; reviews rejected model candidates. |
| Port Authority Staff | Port | Handle each vessel call: berthing request, approvals, Customs, entry window, BAPLIE, recommendation review and plan confirmation, deviations. |
| Department managers and administrators | Port | Tug and Pilotage Department Managers keep fleets and rosters; CT Administrator keeps cranes, equipment and yard; NCT Administrator keeps NCT storage; Marine Safety Officer sets safety constraints, environmental limits, chart datum and under-keel clearance. |
| Pilotage and Tug Department Officers | Port | Confirm or replace the proposed pilot, tug master and tug, and assign the components. |
| Marine Pilot, Tug Master | Port (field) | Receive tasks, acknowledge them and report actual status from the mobile app. |
| CT Operation Planner | Port | Reviews, adjusts, accepts or rejects yard placement and enters the plan into the terminal's TOS. |
| Office User, Mobile User, Field User | Port | Generic users of the web dashboard and of the mobile app (remote field user outside port Wi-Fi, on-site field user inside the terminal). |
| System actors | Both | Scheduler, KMS / License Signer, Check-in Client and API, EDI Parser, AI Part 1/2/3, weather and marine data providers, Realtime Server, MDM platform, VPN gateway, managed device. |

There is no external berthing-request source, no payment gateway and no TOS integration: requests are entered by staff, payment happens outside the system and is only recorded, and the TOS receives the yard plan by hand.
