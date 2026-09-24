# Timed build rules (this file is protected: never edit it)
@PROJECT.md

Goal: a fully working core flow, finished early, so I can test it end to end by hand.
Time is the scarcest resource. Correct required behavior beats everything else.

## Pipeline (run every phase without waiting for me, except the marked STOP)
0. Read the requirements fully. Ask unclear questions with the question tool in ONE batch,
   only for ambiguities that change the data model or core behavior and have no sensible
   default. Never ask later. Log every default you choose in ASSUMPTIONS.md.
1. TASKS.md: every requirement as a checkable line. Turn every example in the requirements
   into a concrete test case. Split into slices: one user-visible behavior each, ~5-8 min,
   each with a "done when" line. Riskiest logic first.
2. SCHEMA.md: tables, columns, relations, statuses. Make likely extensions cheap (multiple
   rows instead of single fields, status columns instead of deleting, compute derived data
   on the fly instead of storing it).
3. CONTRACT.md: every endpoint with method, path, auth, request body, success response and
   error responses.
4. Check: map every requirement and example in TASKS.md to a contract endpoint or rule. Where
   a requirement says something should HAPPEN automatically, implement that behavior; do not
   just reject the action. Fix gaps now.
5. Update PROJECT.md with facts only (stack and pinned versions, database, auth, run
   commands), with a one-line reason per change.
   STOP: tell me PROJECT.md, SCHEMA.md and CONTRACT.md are ready for review. Wait for my go.
6. Implement the backend slice by slice from CONTRACT.md. After each slice: one quick check,
   tick it in TASKS.md, git commit, continue without asking. If a check fails twice, log it
   in BLOCKERS.md and move on.
7. Implement the frontend from CONTRACT.md with the same slice loop. Never guess field names
   or response shapes. If the contract must change, update CONTRACT.md first.
8. Final verification: run the core-logic tests and a smoke test of the main flow, check
   server logs for exceptions, fix what fails. Then report what works, BLOCKERS.md and
   assumptions.

## Spend time only where it changes the outcome
- Before any extra work (tests, docs, refactors, styling, abstractions, new libraries) ask:
  "If I skip this, could a required feature break or be wrong?" If no, skip it.
- Automated tests only for logic with rules, conditions or state changes (scheduling,
  conflicts, calculations, status transitions, permissions). Never for plain CRUD, auth or
  list endpoints; verify those with one quick request.
- No README or docs, no refactor passes, no features beyond the requirements.
- Start dev servers in the background and check them with curl; never block on a server.

## API responses
- One shape: success {data, message}; error {message, errors?}.
- `message` is human-readable and specific to the cause (e.g. "Doctor is not available at
  11:00"), never a bare status or "Unprocessable content".
- Status codes: 422 validation or business rule, 404 not found, 409 conflict, 401/403 auth,
  500 unexpected. Never return an empty body.
- Catch unexpected exceptions globally: log the real error, return {message: "Server error"}.
- Frontend: one shared request wrapper. Show `message` (and field `errors`) for 4xx; show
  "Server error, please try again" for 5xx or network failure. No per-page error handling.

## Quality floor
- Validate input, never let the API crash, no silent failures.
- UI: plain, consistent and usable. No polish time.
- Provide a seeder or command that creates demo data and login credentials, and print them,
  so I can test the whole flow quickly.

## Change requests
- Commit first. Re-read the new requirement, write CR-TASKS.md listing what changes and what
  must keep working, then run the same pipeline (questions only if truly blocking).
- Existing flows must keep passing the smoke test.

## Subagents
- Only for independent work (e.g. frontend vs backend once CONTRACT.md exists). Give each a
  self-contained brief: goal, files to read, files it owns, "done when", what not to touch.

## PROJECT.md
- You may edit PROJECT.md (facts only) during step 5 and afterwards only to correct a wrong
  fact. Never change workflow or rules; they live in this file.