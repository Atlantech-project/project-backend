## <ID> · <task title>

<One or two sentences: what the task delivers, from plan section 1.>

**Plan, progress log and review:** `ATLANTECH/docs/tasks/<ID>.md`

### Done when

- [x] <done-when line> — <how it was proven: test name, command, demo>
- [x] …

### Changes

- <module or area>: <what changed> (`<commit>`)
- …

### Deviations from the plan

- <deviation and reason, from the progress log> · or "none"

### Contracts and other teams

| Artefact / team | Change | Where |
| --- | --- | --- |
| <OpenAPI CT-02 / events CT-05 / vocabulary CT-21 / schema.sql CT-17 / mock CT-19 / readiness pack CT-23 / team> | <what changed> | <file or message> |

or "No contract or other-team change."

### Review

Verdict: **Ready for PR** · Blocker 0 · Must fix 0 · Should fix <n> · Notes <n>
Open notes: <short list or "none">

### Tests

- `php artisan test --compact`: <n> passed (<where: Herd / container / CI>)
- <other checks: Pint, arch tests, infra group>
