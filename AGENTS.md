# AGENT RULES — ITMS Administrative Application

These rules are mandatory for every AI agent working in this repository. They take precedence over generic agent habits, unless they conflict with an explicit user instruction or a documented safety requirement.

## 1. Source of truth
- Read `README_AGENT_HANDOFF.md` and the six documents in `project_docs/` before implementation.
- Treat the PRD, Technical Design, ERD, Database Schema, Implementation Blueprint, and UI/UX Specification as the approved project baseline.
- Use the original requirement notes and supplied PDF examples in `reference_material/` as supporting business/source material.
- Do not silently reinterpret, simplify, omit, or replace an agreed business rule.
- If two project documents conflict, do not guess. Report the exact conflicting statements and request a decision using the Change / Decision Request format below.
- Do not create new requirements from assumptions.

## 2. Work exactly to the requested scope
- Work only on the current approved phase and its listed deliverables.
- Do not add features, libraries, services, infrastructure, abstractions, UI patterns, or architectural changes merely because they seem useful.
- Do not conduct experiments, prototypes, benchmarks, alternate implementations, or proof-of-concepts unless the current phase explicitly requires them or the user approves them.
- Do not install, upgrade, remove, or replace dependencies without a clear requirement in the approved documents or explicit user approval.
- Do not change the selected stack or database design without approval.
- Do not create sample/demo business data beyond minimal test fixtures required by automated tests.
- Do not connect to external services or send project data outside the local development environment.

## 3. Ask before proposing changes
If you identify a possible improvement, alternative, missing business decision, or a conflict:
1. Stop only the affected decision/work item; continue other independent work in the approved scope where safe.
2. Do not implement the idea.
3. Present a concise decision request with:
   - What is currently specified.
   - What issue or opportunity you found.
   - Two or three concrete options, including the documented/default option.
   - Your recommendation and its practical impact.
   - Whether work is blocked.
4. Wait for the user's choice before changing the baseline.

Do not ask the user to decide ordinary implementation details that are already covered by the documents or can be safely resolved within the approved architecture. Make those decisions yourself and record them.

## 4. Efficient execution; no repeated work
- Inspect the repository and current state before acting. Reuse completed work.
- Never recreate, reformat, regenerate, or re-run work that is already complete unless there is a demonstrated defect, a dependency requires it, or the user requests it.
- Before running a command, identify its purpose and whether its output is already available.
- Do not repeat successful tests without a reason. Run only tests relevant to changed code, then the required phase gate.
- Do not repeatedly re-read all documents after the initial review. Keep a concise requirement/decision index and refer to the exact relevant sections when needed.
- Do not repeatedly ask questions already answered in the documents or earlier decisions.
- Do not make broad refactors while implementing a narrow task.
- Avoid unnecessary commentary, long progress narration, and duplicate reports.

## 5. Safe repository and data handling
- First inspect the existing repository, branch, working tree, installed runtime versions, and current project files.
- Never delete, overwrite, rename, migrate, or modify the user's legacy database. The new application database is `itms_admin_db`.
- Never run destructive database commands against an existing database.
- Do not reset, drop, truncate, or seed a non-test database.
- Use Laravel migrations as the schema source of truth. phpMyAdmin is for inspection/administration only.
- Keep uploaded and generated business documents in private storage outside the public web root; serve them through authorized application routes.
- Do not commit secrets, `.env`, credentials, private documents, database dumps, or user data.
- Before any destructive file operation, verify the exact target and preserve recoverable work.

## 6. Implementation quality
- Follow the approved stack: Laravel 13.x, PHP 8.5.x stable, MySQL 8.4 LTS, Apache 2.4, Blade, Tailwind CSS 4.x, limited vanilla JavaScript/Alpine.js only where useful, Composer, npm, Git, Laravel migrations, Pest by default.
- Use a modular monolith. Do not introduce microservices.
- Keep business logic out of Blade templates and controllers where a dedicated service/action is appropriate.
- Use database transactions for finalization, sequential number allocation, PR-to-RAB import, and termin reservation as specified.
- Preserve immutable historical versions and contract snapshots.
- Validate on the server. Client-side validation is supplementary only.
- Use decimal database types and explicit rounding rules from the documents; never use floating-point arithmetic for currency calculations.
- Add automated tests for business rules, calculations, state transitions, authorization, and data integrity.
- Do not claim a task is complete unless implementation and relevant tests have actually succeeded.

## 7. Phase control
- Follow `DEVELOPMENT_PHASES.md` in order.
- Complete one phase at a time. Do not start the next phase until the current phase's acceptance criteria pass.
- Do not bundle unrelated phases into one implementation task.
- At the end of each phase, provide a short completion report and wait for the user's instruction to proceed to the next phase.
- If a phase is blocked by a user decision, identify only the blocker and continue independent approved work if possible.

## 8. Required completion report
At the end of each task/phase, report only:
1. **Completed:** concrete files/features changed.
2. **Verification:** exact commands run and pass/fail result.
3. **Not completed / blockers:** only real remaining items.
4. **Decisions needed:** only items requiring user choice.
5. **Next step:** the next approved task, without starting it automatically.

Never report tests as passed if they were not run. Distinguish "not run" from "failed".

## 9. Change / Decision Request format
Use this exact compact format when user input is genuinely needed:

**Decision required — [short topic]**

- **Current specification:** [what the project documents say]
- **Issue:** [specific ambiguity, conflict, or proposed change]
- **Option A:** [documented/default behavior]
- **Option B:** [alternative]
- **Option C:** [optional alternative]
- **Recommendation:** [one option and why]
- **Impact:** [what changes or remains unchanged]
- **Blocked:** [yes/no and which task]

Do not implement any unapproved option.
