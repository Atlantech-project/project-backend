<!-- Generated from docs/reference/uml/UML_Context.md lines 133-151 by build-index.mjs. Do not edit: edit the source and rebuild. -->
## 9. Architecture and technology

| Layer | Technology | Notes |
|---|---|---|
| Clients | Vue.js web dashboard; Flutter mobile app | Web over HTTPS with SSE; mobile over HTTPS and WebSocket, offline-first. |
| Entry | NGINX | Load balancer and TLS termination (TLS 1.3 only). |
| Application | Laravel modular monolith (one or more app instances) | Domain modules with one-way dependencies; terminal modules never import each other. |
| Cache and jobs | Redis | Cache, hot state, and separate job queues per worker (`queue:part1`, `queue:part2`, `queue:edi`). |
| Workers | Python satellites: EDI Parser (BAPLIE D.17A, deterministic, not AI), AI Part 1, AI Part 2 (OR-Tools CP-SAT), AI Part 3 | Read state from and write results to the database. |
| Data | MySQL primary (writes) and replica (reads); dedicated file volume for BAPLIE files | Two fully separate databases: Central SaaS registry DB and the port operational DB. |
| Central SaaS | Vue.js SaaS dashboard, Laravel SaaS API, MySQL registry DB | Licenses, plans, billing, port registry only. No runtime calls into ports, no operational data, no AI models. |

**Isolation policy.** Every port is a full, dedicated installation of the same system on its own server and network. No operational data, model weights or learning updates ever move between ports or between a port and the SaaS; the only link is license verification. Cross-port (federated) learning was explicitly rejected. Inside one port, competing terminals are separated by terminal-level row security: a terminal user cannot see another terminal's cargo, stowage, yard plans or costs (answer 403).

**Roles.** One hierarchy repeats in every domain: Administrator or Manager (master data) → Officer or Planner (receives and dispatches recommendations) → Supervisor or Field Executor (reports actual status). Plus the SaaS Admin (platform only, no port data) and the Marine Safety Officer (safety constraints before recommendation).

**AI in three parts.** Part 1 (predictive and environmental) is mostly physics and rules and outputs only constraints: feasible entry-time windows. Part 2 (AI Engine) is the only allocation authority: rule-based filtering plus CP-SAT, fed by two ML predictions (service time and delay risk), with template-based rationale (not an LLM). Part 3 (feedback loop) evaluates real outcomes and retrains the models under human-approved, local-only governance. A Vision module was designed but is excluded from the current scope.

**Team workflow.** Three repositories under the `Atlantech-project` GitHub organisation: `project-backend`, `project-frontend`, `project-mobile`. Each task is a `feature/<task-name>` branch from `develop`, merged back to `develop` by pull request.
