# Recording a closed decision

## 1. Summary table (top of `ATLANTECH_Decisions.md`)

Before:

```
| DEC-xx | <title> | <Owner> | Open | Sprint n |
```

After:

```
| DEC-xx | <title> | <Owner> | Closed | Sprint n |
```

## 2 and 3. The decision's section

```
### DEC-xx · <title>

| | |
| --- | --- |
| Source | <unchanged> |
| Owner | <unchanged> |
| Status | Closed |
| Closing task | <unchanged> |
| Affects | <unchanged, add IDs only if the decision reaches new ones> |
| Decision | Closed (<YYYY-MM-DD>): <the decision in one to three sentences: what is chosen, what it replaces, where the detail lives (CT-xx vX.Y)>. |
```

Keep the story (`القصة`), topic, options and recommendation as they are: they explain why.

## Checks after recording

- [ ] All three places changed; `grep -nE '^\| DEC-xx ' ATLANTECH_Decisions.md` shows `Closed`.
- [ ] PortHub `contracts/ATLANTECH_Decisions.md` and `Code Notes/ATLANTECH_Decisions.md` are identical.
- [ ] `node docs/reference/sync-check.mjs --apply` run; `node docs/reference/lookup.mjs DEC-xx` shows `status: Closed` and no `!` warning.
- [ ] Plans in `docs/tasks/` that cite DEC-xx updated.
- [ ] Contracts and task texts that contradict the decision listed for the lead.
- [ ] Affected teams told (`team-handoff`).
