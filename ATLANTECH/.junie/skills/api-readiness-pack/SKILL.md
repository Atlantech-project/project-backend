---
name: api-readiness-pack
description: Build the CT-23 API readiness pack for one feature (FT-xx) before a Feature Readiness Review: endpoints merged to develop, OpenAPI diff, example requests and responses per status, seed data, events, the mock behind it and known gaps, written to PortHub contracts/readiness/FT-xx.md. Use for the roadmap's BE-PKn tasks, before an FRR, or when asked for "the readiness pack for FT-xx".
---

# API readiness pack (CT-23)

The pack tells Frontend, Mobile and QA exactly what they can build and test against for one feature. Shape: `docs/reference/split/contracts/CT-23.md`. Template and checklist: `reference/pack-template.md`.

## 1. Scope the feature

- `node docs/reference/lookup.mjs FT-xx` for its title; its backend tasks: `grep -F '"feature":"FT-xx"' docs/reference/index/index.json | grep -F '"team":"BE"' | cut -c1-120`.
- For each task: merged to `develop`? (plan file Status Done, or code on `origin/develop`). Unmerged tasks go under "Known gaps", not under endpoints.
- If the roadmap has a `BE-PKn` task for this FRR, its plan file holds this work; otherwise record progress in the feature's last task plan.

## 2. Collect, from develop only

Work on a clean checkout of `origin/develop` (or a worktree). Follow `context-rules`.

| Pack line | How |
| --- | --- |
| Endpoints | `php artisan route:list --path=api --json`, filtered to this feature's controllers |
| OpenAPI diff | the merged PRs of the feature's tasks that changed the OpenAPI file (CT-02) |
| Examples | one request and response per endpoint for each status it can return (201/200/202/204 and 401/403/404/409/422/423/426 as applicable), taken from the feature tests or from a run against seed data; error bodies must match CT-01 and the error catalogue |
| Seed data | what the dev server's seeders create for this feature (counts per status, files) |
| Events | SSE (CT-05) and WebSocket (CT-06) events the feature emits, names exactly as in the contract |
| Behind it | the mock worker queue and scenario (CT-19) each AI-triggering endpoint relies on, and how to select another scenario |
| Known gaps | unmerged tasks, endpoints not yet on the dev server, contracts still draft, open decisions that change behaviour |

## 3. Write and hand over

1. Write `contracts/readiness/FT-xx.md` in the PortHub repository from the template (branch `feature/FT-xx-readiness`, PR to PortHub `main`), mode C: the lead reviews before it is shared.
2. Ask the `contract-checker` agent to compare the pack's endpoints, codes and events with the contracts; fix the pack or report mismatches as known gaps.
3. Offer `team-handoff` to tell Frontend, Mobile and QA the pack is ready for the FRR.

Never invent an endpoint, field, code or event: every line comes from develop or from a contract, and anything not yet true goes under "Known gaps".
