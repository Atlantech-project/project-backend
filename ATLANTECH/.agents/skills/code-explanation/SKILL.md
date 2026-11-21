---
name: code-explanation
description: Explain how a piece of ATLANTECH backend code works as one full chain, from the entry point (route, command, job) through every class and method it calls, with the real inputs, outputs, data written and failure branches at each step, in right-to-left Egyptian Arabic with English technical terms. Use when the user says "اشرح لي الكود", "اشرح السيناريو بالكود", "code explanation", "trace this", "walk me through this flow", or asks how a feature, service, middleware or command works step by step. Not for planning, reviewing or changing code.
---

# Code explanation (the call chain, step by step)

The user wants the **code scenario**, not an events story: pick one concrete action, then follow the real code from the first line that runs to the last, saying for every function what it takes, what it does, what it returns and why it is built that way.

Follow `context-rules` for reading. This skill reads code and explains it; it never edits, commits or runs anything that writes to a database.

## 1. Pick the scenario

- Use the user's example ("the admin creates a user"). If they gave none, choose the most typical successful request of the thing they named and say which one you chose in one line.
- Name the entry point exactly (the route, the Artisan command, the job) and who calls it.

## 2. Read before you write (never explain from names)

- Find the entry point (`routes/api.php`, `php artisan list`, the provider) and open every class in the chain: the middleware, the Form Request, the controller, the service, the helpers, the models and their casts and attributes, the migration of each table written, and the tests that prove the behaviour.
- Take line numbers from the file itself (`grep -n` or `Read`), never from a concatenated listing: they shift. Cite them as `file:line`.
- Check the code on the branch the user is on (`git log -1`). Where a comment or a document disagrees with the code, trust the code and say so.
- If a step cannot be checked, write "Unverified" and what you would check. Never guess.

## 3. Write the answer

Language: the language of the user's message. For Arabic: Egyptian Arabic with English technical terms, all inside `<div dir="rtl"> ... </div>` so it renders right to left (code blocks stay left to right).

Shape, in this order:

1. **Title** with the scenario ("سيناريو كامل بالكود: ...") and one line saying what the user does.
2. **Phases** (`### المرحلة n: ...`), numbered, in execution order. Typical phases: what runs before (middleware, validation), the controller, how the service and its dependencies are built, the service method line by line, the helper classes it calls, the commit, the response.
3. **Inside each step**, as bold labels:
   - **بياخد**: the real parameters, with their types and what the caller passes (name the values, say when an `int` is passed instead of an object and why).
   - **بيعمل**: what the code does, line by line, in the order it runs (cite `file:line`).
   - **بيرجّع**: the return value and who receives it.
   - **ليه**: the reason it is built that way, with the decision id (`DEC-nn`, `CT-nn`, `FR-...`) when there is one.
4. **Dependency injection**: for a class built by the container, say what its constructor asks for, what the container gives (an interface and the class bound to it, and where), so the reader sees where each object comes from.
5. **Transaction and data**: say where the transaction opens and closes, what is rolled back on each failure, and exactly which tables and columns are written (a small table at the end).
6. **Failure branches**: for each early exit say what is thrown, which status and code the client gets, and what was or was not written (for example "no user and no audit row: the transaction rolled back").
7. **A closing table** of what was written to the database, then, when it helps, **a short comparison** with the nearest variant (the update path next to the create path, the refusal path next to the success path), with the differences that matter.
8. Mention what is **not** done by this code and where it happens instead (another middleware, another task) in a sentence, not a digression.

## 4. Style rules

- Plain, concrete, and complete: every function in the chain is named, with its inputs and outputs. Do not skip a helper because it looks small.
- Use the real names and values of the scenario (`$actorId`, `'users'`, `'CREATE'`), not placeholders.
- Short paragraphs, bold labels, tables for lists of facts, code blocks only for the lines being explained.
- Say what each piece protects against when the reason is a safety rule (the password never reaches the audit row, the append-only model, the guard that refuses a write outside a transaction).
- No invented behaviour. If the user asks "what if X fails?", answer from the code or say it is Unverified.
- End without a recap of the whole text; the closing table is enough. Offer one follow-up at most, only when something in the chain deserves a deeper look.
