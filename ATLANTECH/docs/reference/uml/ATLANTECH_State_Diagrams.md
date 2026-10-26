# ATLANTECH — State Diagrams

Mermaid code only, in three categories.

## Category 1 — Central SaaS, Licensing and Billing

### SM-1 — License (central SaaS)

```mermaid
stateDiagram-v2
    direction TB

    state license_choice <<choice>>
    [*] --> license_choice : Issue or renew license<br/>/ validate dates, snapshot plan limits and price, audit

    license_choice --> ACTIVE : [valid_from <= now and no other ACTIVE license]<br/>/ audit, no token
    license_choice --> PENDING : [valid_from > now]<br/>/ audit, no token

    PENDING --> ACTIVE : daily scheduler<br/>[now >= valid_from and not on_hold<br/>and no other ACTIVE license] / audit SYSTEM
    PENDING --> EXPIRED : daily scheduler [now >= expires_at]<br/>/ audit SYSTEM
    PENDING --> REVOKED : revoke / audit

    ACTIVE --> EXPIRED : daily scheduler [now >= expires_at]<br/>/ audit SYSTEM
    ACTIVE --> SUSPENDED : suspend<br/>/ token kept, PENDING renewal on_hold, audit
    ACTIVE --> REVOKED : revoke / revoke token if any, audit

    SUSPENDED --> ACTIVE : reinstate [no other ACTIVE license]<br/>/ release on_hold, no new token, audit
    SUSPENDED --> EXPIRED : daily scheduler [now >= expires_at]<br/>/ audit SYSTEM
    SUSPENDED --> REVOKED : revoke / revoke token if any, audit

    EXPIRED --> [*]
    REVOKED --> [*]

    note left of ACTIVE
        At most one ACTIVE license per port.
        The token is signed lazily, at the first check-in
        or as a manual token. It is refreshed at check-in
        when 14 days or less remain. No status change.
    end note

    note left of PENDING
        Billing records are created manually by the
        SaaS Admin. No transition is driven by billing.
        Offboarding the port revokes its licenses.
    end note

    note right of REVOKED
        Every transition is audited:
        admin actions as ADMIN, roll-overs as SYSTEM.
    end note
```

### SM-2 — Port lifecycle (central SaaS)

```mermaid
stateDiagram-v2
    direction TB

    [*] --> ONBOARDING : SaaS Admin registers the port<br/>/ public_id, API credential hash, audit

    ONBOARDING --> ACTIVE : activate [activation checklist complete]<br/>/ Port Admin readiness recorded, audit
    ACTIVE --> SUSPENDED : suspend port / reason, audit
    SUSPENDED --> ACTIVE : reinstate port / audit

    ONBOARDING --> OFFBOARDED : offboard<br/>/ revoke licenses and tokens, audit
    ACTIVE --> OFFBOARDED : offboard<br/>/ revoke licenses and tokens, audit
    SUSPENDED --> OFFBOARDED : offboard<br/>/ revoke licenses and tokens, audit

    OFFBOARDED --> [*]

    note right of ONBOARDING
        Checklist: token installed, essential settings,
        berths and resources, Port Admin account.
        A valid token may already be stored here:
        the port server runs in SETUP mode.
    end note

    note left of SUSPENDED
        Port status and license status are separate.
        Operations need both to be ACTIVE.
        The port server learns the port status at check-in.
    end note
```

### SM-3 — Port-side license mode

```mermaid
stateDiagram-v2
    direction TB

    state mode_choice <<choice>>
    [*] --> mode_choice : token stored or state checked<br/>/ port state decided first, then license state

    mode_choice --> SETUP : [port ONBOARDING]
    mode_choice --> FULL : [port ACTIVE and license ACTIVE<br/>and token valid]
    mode_choice --> READ_ONLY : [any other case] / reason shown

    SETUP --> FULL : port becomes ACTIVE<br/>[license ACTIVE and token valid]
    SETUP --> READ_ONLY : port leaves ONBOARDING<br/>[license not ACTIVE or token not valid] / reason shown

    FULL --> READ_ONLY : port SUSPENDED or OFFBOARDED, license not ACTIVE,<br/>or token missing, expired, revoked or invalid<br/>/ reason shown, new writes return 423
    READ_ONLY --> FULL : valid token by check-in or manual upload<br/>[port ACTIVE and license ACTIVE]

    note right of SETUP
        Only the first Port Admin, setup users,
        port configuration, berths and resources.
        Never operational records.
    end note

    note right of READ_ONLY
        SETUP is never entered from READ_ONLY.
        Allowed: status updates, acknowledgements,
        reject and return, deactivate user, revoke tokens,
        Port Admin safety exception.
    end note

    note left of FULL
        Recalculated at every check-in, manual token
        upload and local periodic check, from the last
        stored port and license state.
    end note
```

## Category 2 — Port Setup, Operations and Recommendation

### SM-4 — Berthing request

```mermaid
stateDiagram-v2
    direction TB

    [*] --> SUBMITTED : create request [FULL]<br/>/ find-or-create vessel by IMO

    SUBMITTED : SUBMITTED
    SUBMITTED : edit request [FULL]
    SUBMITTED --> PRELIMINARY_APPROVED : preliminary approval [FULL]
    SUBMITTED --> NEEDS_REVISION : return for revision / reason
    SUBMITTED --> REJECTED : reject / reason
    SUBMITTED --> CANCELLED : cancel [FULL] / reason

    NEEDS_REVISION : NEEDS_REVISION
    NEEDS_REVISION : edit request [FULL]
    NEEDS_REVISION --> SUBMITTED : creator resubmits [FULL]
    NEEDS_REVISION --> CANCELLED : cancel [FULL] / reason

    PRELIMINARY_APPROVED --> CUSTOMS_APPROVED : customs approval [FULL]<br/>/ enqueue Part 1 run

    CUSTOMS_APPROVED : CUSTOMS_APPROVED
    CUSTOMS_APPROVED : reject all windows / reason recorded
    CUSTOMS_APPROVED : recompute [FULL] / new Part 1 run, old run REPLACED
    CUSTOMS_APPROVED --> ENTRY_WINDOW_CONFIRMED : confirm window<br/>[FULL and server checks pass] / enqueue Part 2

    ENTRY_WINDOW_CONFIRMED --> CUSTOMS_APPROVED : reopen entry window<br/>[FULL and no confirmed plan] / reason, new Part 1 run
    ENTRY_WINDOW_CONFIRMED --> RECOMMENDATION_GENERATED : Part 2 produced a version<br/>(solver or FIFO)

    RECOMMENDATION_GENERATED : RECOMMENDATION_GENERATED
    RECOMMENDATION_GENERATED : regenerate [FULL] / new version PENDING_REVIEW, previous REPLACED
    RECOMMENDATION_GENERATED : override [FULL] / MODIFIED_BY_STAFF version
    RECOMMENDATION_GENERATED --> PLAN_CONFIRMED : confirm plan<br/>[FULL and availability re-check passes]<br/>/ book berth, tug, pilot, version CONFIRMED

    PLAN_CONFIRMED : PLAN_CONFIRMED
    PLAN_CONFIRMED : delay re-plan confirmed [FULL] / reserve new,
    PLAN_CONFIRMED : release unused, keep STARTED, old version REPLACED
    PLAN_CONFIRMED --> PLAN_CANCELLED : cancel plan [FULL and no component STARTED]<br/>/ release resources
    PLAN_CONFIRMED --> PLAN_CANCELLED : Port Admin safety exception [reason given]<br/>/ cancel remaining components, audit
    PLAN_CONFIRMED --> COMPLETED : all components completed

    PLAN_CANCELLED --> ENTRY_WINDOW_CONFIRMED : re-plan<br/>[call continues and FULL]
    PLAN_CANCELLED --> [*] : call does not continue

    COMPLETED --> [*]
    REJECTED --> [*]
    CANCELLED --> [*]

    note right of CUSTOMS_APPROVED
        ENV_DATA_UNAVAILABLE and
        NO_FEASIBLE_WINDOW are Part 1 run
        outcomes, not states: staff submit manual
        environmental data and recompute.
    end note

    note right of ENTRY_WINDOW_CONFIRMED
        NO_FEASIBLE_PLAN is an outcome: the request
        stays here (the vessel waits) and staff
        may regenerate.
    end note

    note left of SUBMITTED
        Reject, return, reject all windows and the
        safety exception are allowed in READ_ONLY.
        No automatic expiry of requests.
    end note
```

### SM-5 — Operational plan

```mermaid
stateDiagram-v2
    direction TB

    [*] --> CONFIRMED : plan confirmed [FULL]<br/>/ create plan, book berth, tug, pilot,<br/>request PLAN_CONFIRMED,<br/>send components, request yard<br/>recommendation if container terminal

    state CONFIRMED {
        [*] --> PILOT_UNASSIGNED
        PILOT_UNASSIGNED --> PILOT_ASSIGNED : officer confirms or replaces<br/>the proposal / notify pilot
        PILOT_ASSIGNED --> PILOT_STARTED : status started
        PILOT_STARTED --> PILOT_DONE : status completed

        --

        [*] --> TUG_UNASSIGNED
        TUG_UNASSIGNED --> TUG_ASSIGNED : officer confirms or replaces<br/>the proposal / notify Tug Master
        TUG_ASSIGNED --> TUG_STARTED : status started
        TUG_STARTED --> TUG_DONE : status completed

        --

        [*] --> YARD_PENDING : [container terminal]
        [*] --> YARD_NOT_APPLICABLE : [otherwise]
        YARD_PENDING --> YARD_ACCEPTED : yard recommendation<br/>accepted or adjusted
        YARD_ACCEPTED --> YARD_DONE : terminal status reported
    }

    CONFIRMED --> COMPLETED : last component completed<br/>[pilot DONE and tug DONE<br/>and yard DONE or NOT_APPLICABLE]<br/>/ request COMPLETED
    CONFIRMED --> CANCELLED : cancel [FULL and no component STARTED]<br/>/ release resources, request PLAN_CANCELLED
    CONFIRMED --> CANCELLED : Port Admin safety exception<br/>[reason given] / cancel remaining,<br/>release unused, request<br/>PLAN_CANCELLED, audit

    COMPLETED --> [*]
    CANCELLED --> [*]

    note right of CONFIRMED
        Internal transition: delay re-plan confirmed
        / changed components back to UNASSIGNED,
        STARTED components kept; plan stays CONFIRMED.
        Acknowledged is read from the notification,
        not a state. A rejected yard recommendation keeps
        YARD_PENDING. A significant deviation leads to a
        delay re-plan proposal; thresholds are Port Admin
        settings.
    end note
```

## Category 3 — Execution Feedback and Model Learning

### SM-6 — Outbox event (mobile app)

```mermaid
stateDiagram-v2
    direction TB

    [*] --> PENDING : user action (status update, acknowledge, field record)<br/>/ write to the Outbox first: idempotency_key,<br/>type, payload, recorded_at, user_id

    PENDING --> SENDING : sync cycle (connectivity change or manual)<br/>[ping OK within the cycle limit and session valid<br/>and user_id = current user] / send in order
    PENDING : PENDING
    PENDING : ping failed / backoff 2 s doubling, max 60 s,
    PENDING : after 5 tries end the cycle

    SENDING --> DONE : server reply: applied or duplicate
    SENDING --> REJECTED : validation error, or 423 for a creation event<br/>/ keep the reason in the rejected list
    SENDING --> PENDING : send failed, timeout or app restarted<br/>/ keep idempotency_key, end the cycle

    DONE --> [*] : purged after 30 days
    REJECTED --> [*] : purged after 30 days

    note right of SENDING
        READ_ONLY: status updates and acknowledgements
        are applied; only creation events are rejected.
        Events have no attempt limit.
    end note

    note left of PENDING
        Events not yet sent from a wiped or lost device
        are lost; the user enters them again.
    end note
```
