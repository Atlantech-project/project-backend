---
name: team-handoff
description: Draft the message that tells another ATLANTECH team (Frontend, Mobile, QA, AI, UI/UX, Leads Council) what a backend task, contract change, decision or readiness pack changes for them, what they must do and by when. Use after a task's PR is ready or merged, after contract-change or decision-close, for a readiness pack, or when asked to "tell the frontend/mobile/AI team". Drafts only: the lead sends it.
---

# Team hand-off

Source of every message: section 5 of `docs/tasks/<ID>.md` ("This task changes something other teams use"), or the impact list from `contract-change` / `decision-close`, or the readiness pack. Template: `reference/message-template.md`. Follow `context-rules`.

## 1. One message per team

For each team in that list, collect only what concerns it:

- **What changed:** endpoint, field, error code, event, queue, payload, schema, decision or contract version, in that team's terms (quote names exactly as in the contract).
- **Why:** the task, decision or contract version, with its ID.
- **Where it is:** the published place (PortHub `contracts/…`, the OpenAPI PR, the readiness pack, the merged PR). Link to it; never paste long specifications into the message.
- **What they must do, by when:** concrete actions (update a client, a test, a payload), the sprint or FRR it matters for, and whether anything breaks if they do nothing.
- **Their dependent tasks:** from `node docs/reference/lookup.mjs <ID>` (`← unblocks`, `→ affects`), listed by ID and title.
- **Open points:** anything still draft, proposed or Open (say so plainly), and the question you need them to answer, if any.

## 2. Tone and language

Short, factual, one screen at most; bullet points; IDs and names exactly as in the documents. Write in the language the lead asks for (English by default for written team channels; Egyptian Arabic with English technical terms when asked). No claims beyond what the sources say.

## 3. Hand over

Show the drafts to the lead, grouped by team, with the channel they usually use if known. Never send messages, post to chats, open issues or tag people yourself. After the lead sends them, add a line to the plan's progress log: date, teams told, channel.
