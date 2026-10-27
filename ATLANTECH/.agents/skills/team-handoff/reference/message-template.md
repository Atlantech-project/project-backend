# Hand-off message template

```
To: <Team> (<lead name if known>)
Subject: <ID> — <what changed, in five to eight words>

What changed
- <item, named exactly as in the contract/OpenAPI>
- <item>

Why
- <task / decision / contract version with ID>

Where
- <link: PortHub contracts/…, OpenAPI PR, readiness pack, merged PR>

What you need to do
- <action> — before <sprint n / FRR date>
- If nothing is done: <what breaks, or "nothing breaks; optional">

Your tasks affected
- <ID> <title>
- <ID> <title>

Still open
- <draft contract / Open decision / proposal, or "nothing">
- Question for you: <one clear question, or "none">

— Backend Team
```

## Checklist

- [ ] One message per team; nothing that concerns only another team.
- [ ] Every name, code, field and event exactly as in the source.
- [ ] Links instead of pasted specifications.
- [ ] Draft / Open / proposed items labelled as such.
- [ ] The lead sends it; the plan's progress log records date, teams and channel.
