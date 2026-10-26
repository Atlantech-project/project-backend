<!-- Generated from docs/reference/uml/UML_Context.md lines 115-131 by build-index.mjs. Do not edit: edit the source and rebuild. -->
## 8. Project background

This section and the next three summarise the project documents (graduation proposal, business model canvas, system architecture, functional requirements and non-functional requirements) so the diagrams can be read against them. Where a document and the diagrams differ, section 11 says which one the diagrams follow.

**Why ATLANTECH exists.** A port is a group of independently run parties: the port authority, the pilotage and tug departments, container terminals (CT) and non-container terminals (NCT). Each plans its own resources but all compete for the same berths, navigation channel, tugs and pilots. The proposal names three root causes: isolated planning, no visibility of upcoming demand, and berthing that ignores tide, surge, wind, waves and visibility. The results are vessel waiting, missed tidal windows, re-handling, cascading delay and eroded safety margins.

**What it is.** A port-wide advisory, recommendation and coordination layer. Its defining rule: *ATLANTECH recommends and coordinates; existing terminal and port systems execute.* It never replaces a TOS (such as Navis N4) or a PMIS (such as SPS-Harbor), never controls equipment, and every recommendation needs human approval before dispatch.

**The coordination loop.** Data ingestion (BAPLIE, berthing requests, weather) → unified operational state → recommendation (AI Part 1 entry window, AI Part 2 allocation) → human review (approve or override) → plan confirmation and dispatch → actual status from the field → deviation tracking (plan vs actual) → operational feedback (AI Part 3, port-local) → updated state.

**Reference port.** Damietta Port: 8 terminals, 36 berths, about 26 vessels a day. It is a case study only; the product must work for any port through configuration (from 1 terminal and 5 berths up to 10 terminals and 50 berths) without code changes.

**Scope.** In scope: coordinated berth, tug and pilot allocation across terminals; environmental feasibility windows and entry-time recommendation; container-yard placement and timing for CT; storage, equipment and sequencing for NCT (built after CT); BAPLIE ingestion; human review; status tracking, deviations and notifications. Out of scope: vessel-to-terminal assignment (fixed by commercial contracts), customs systems (customs approval is only recorded by hand), ERP functions, direct equipment control, detailed mooring or bunkering, AIS or telemetry, and any sharing of data or AI models between ports.

**Business model.** B2G2B. The primary buyer is the port authority (through ministry tenders), secondary buyers are CT and NCT operators, end users are pilots, tug masters, planners and safety officers. Revenue: a one-time setup and integration fee, an annual core port license (3 to 5 year contracts, tiered by port size), optional annual terminal add-ons (container yard module, bulk and tank storage module) and an enterprise support SLA. There are no monthly subscriptions; all billing is annual. This is why the central SaaS keeps annual licenses and yearly billing records (category 1 diagrams).

**Evaluation.** Against a manual-planning baseline: conflicts detected and resolved, resource utilisation, avoidable idle time, estimated operating cost, feasibility and quality of recommendations, and downstream impact. The key target is whether the port can handle more than its current ~26 vessels a day.
