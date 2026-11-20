# Development Phases — ITMS Administrative Application

This sequence is the approved delivery order. Work only on the current phase. Each phase must pass its acceptance criteria before the next begins.

Phases 0–8 cover the original application. Phases 9–14 implement the approved addendum of 19 November 2026 (PR Tracking, PR Document Archive & Payment Tracking). Every addendum phase requires explicit user approval before work starts. Decisions 1–15 are approved and recorded in `project_docs/DECISION_LOG.md`; no decision is open. Phase 9 is documentation and decision finalization. Phases 10–13 are implemented and their acceptance criteria pass. Phase 14 is implemented pending final user confirmation.

## Phase 0 — Repository and Environment Assessment
**Purpose:** establish the actual starting state without changing it.

Tasks:
- Inspect repository files, Git status/branch, existing application code, and project documentation.
- Check installed PHP, Composer, Node/npm, MySQL client/server, Apache, and Git versions.
- Identify whether a Laravel project already exists.
- Do not install, upgrade, initialize, overwrite, or delete anything in this phase.
- Compare environment compatibility against the Technical Design.

Deliverables:
- Short environment inventory.
- Existing repository inventory.
- Compatibility gaps, if any.
- Exact recommended next commands, with a short explanation.
- No code changes unless the user explicitly asks.

Acceptance:
- The user can see what already exists and exactly what is missing.
- No existing project, database, or files were changed.

## Phase 1 — Project Foundation
**Purpose:** create the minimal Laravel application foundation.

Tasks:
- Initialize the project only after Phase 0 findings are reviewed and the user directs the setup.
- Configure the application for `itms_admin_db` without touching the legacy database.
- Add the agreed authentication, base Blade layout, Tailwind setup, navigation shell, and baseline error handling.
- Add environment example/configuration guidance without committing secrets.
- Establish Pest and baseline automated tests.
- Create only migrations required for foundation/authentication and the initial agreed shared infrastructure.

Acceptance:
- Application boots locally.
- Login works; public registration is disabled.
- Authenticated layout/navigation renders.
- Database connection targets only `itms_admin_db`.
- Migrations run successfully on a new empty development database.
- Baseline tests pass.
- No business modules are partially implemented.

## Phase 2 — Shared Infrastructure and Master Data
**Purpose:** implement shared building blocks once.

Tasks:
- Master Vendor, Department, and Unit.
- Shared activity history infrastructure.
- Shared recycle-bin infrastructure and safe lifecycle foundations.
- Private file storage abstraction and authorized download route foundation.
- Shared validation, formatting, and common UI components.

Acceptance:
- CRUD and validation tests pass for each master data type.
- Duplicate/normalization behavior matches requirements.
- Files are not publicly accessible.
- Activity entries are recorded for required actions.
- Recycle-bin protections do not orphan dependencies.

## Phase 3 — Purchase Requisition (PR)
Tasks:
- PR parent/version/item model.
- Sequential non-reusable PR number allocation.
- Draft, Final, Archived lifecycle and revision history.
- PR item calculations and validations.
- Optional PDF generation using the approved template rules.
- Version-specific document history.

Acceptance:
- PR business rules and lifecycle tests pass.
- Number allocation is safe under concurrent requests.
- PR PDF excludes unit price.
- Historical versions/documents remain accessible and unchanged.

## Phase 4 — RAB
Tasks:
- RAB parent/version/item model.
- Standalone RAB and RAB creation from PR.
- Import mapping and editable imported rows.
- PR/RAB total equality validation before Final.
- Unit master integration.
- RAB PDF generation and version-specific document history.

Acceptance:
- Import mapping follows PR values exactly as specified.
- PR remains unchanged after RAB edits.
- Linked RAB cannot become Final when totals differ.
- Standalone RAB is not subject to PR total equality validation.
- Revision and document-history tests pass.

## Phase 5 — Contract Management
Tasks:
- Contract and vendor relationships.
- PO uniqueness enforcement.
- Contract fields and attachments.
- Bulanan, Termin, and Full Financing payment schedules.
- Contract status behavior and activity history.
- Snapshot-safe edits.

Acceptance:
- Duplicate PO is blocked at database and application levels.
- Payment schedule validations/calculations pass.
- Archived contracts cannot be used for new LHSTP.
- Inactive contracts remain eligible for LHSTP.
- Contract edits do not mutate historical LHSTP snapshots.

## Phase 6 — LHSTP
Tasks:
- LHSTP parent/version model and contract snapshot.
- One LHSTP per selected termin.
- Manual reference document, work period, realization dates, conclusion, and payment terms.
- PPN/DPP/total/Terbilang calculations exactly as documented.
- Inclusive realization-day calculation.
- Duplicate reference and overlapping-period warnings.
- Finalization, revisions, PDF and DOCX generation/history.

Acceptance:
- Calculation tests cover both PPN inclusion modes, rounding, and Terbilang.
- Termin cannot be consumed by a different Final LHSTP.
- Revision of the same LHSTP can retain/reuse its termin.
- Historical snapshot is immutable.
- PDF/DOCX layout is checked against supplied reference files.
- No claim of pixel-perfect fidelity without visual verification.

## Phase 7 — Dashboard and Application Integration
Tasks:
- Dashboard counts, upcoming contract endings, recent activity, shortcuts.
- Final LHSTP payment summary with specified filters/date basis.
- Cross-module navigation and integration checks.
- Search, filters, pagination, and empty/error states.

Acceptance:
- Payment summary includes only Final LHSTP and uses LHSTP date.
- Dashboard counts match underlying records.
- Integration tests pass.

## Phase 8 — Hardening, Backup, and Deployment
Tasks:
- Security review, validation review, error handling, logging.
- Backup and restore procedure for database and private files.
- Scheduler for safe 30-day recycle-bin purge.
- LAN deployment guidance and access restrictions.
- Performance and usability checks within the agreed local-app scope.

Acceptance:
- Backup and restore have been tested.
- Expired recycle-bin items are purged only when safe; protected dependencies/history remain.
- LAN clients use browser access; MySQL is not exposed directly.
- All required tests pass.
- Deployment and recovery instructions are documented.

---

## Phase 9 — Addendum Requirements and Technical Design

**Purpose:** finalize the approved addendum as an implementation-ready baseline before any code is written.

**Scope:**
- Update PRD, Technical Design, ERD, Database Schema, Implementation Blueprint, UI/UX Specification and this phase file.
- Record all fourteen approved decisions and the remaining open decision in `DECISION_LOG.md`.
- Define payment-record ownership, file authorization, recycle-bin behavior and regression requirements.

**Deliverables:**
- Consistent design documents reflecting the approved addendum.
- `DECISION_LOG.md` with approved decisions and open decisions.
- Approved phase sequence in this file.

**Dependencies:** none. Uses the user-approved addendum dated 19 November 2026.

**Acceptance criteria:**
- All new entities and relationships appear in PRD, ERD and Database Schema.
- Document status and tracking status are clearly separate everywhere.
- Open decisions are recorded, not guessed.
- Existing approved requirements remain unchanged outside the addendum scope.

**Tests:** documentation consistency review only. No code tests.

**Out of scope:** source code, migrations, database changes, dependency changes, implementation of any feature.

## Phase 10 — PR Tracking Data and Business Logic

**Purpose:** add the derived tracking status dimension and append-only stage history.

**Scope:**
- Add `pr_stage_updates` per the approved schema; no `tracking_status` column on `purchase_requests` (Decision 10).
- Implement `PrTrackingService` with stage recording, activity log and status derivation from history.
- The 99 existing PRs have no stage rows and therefore derive `Not Tracked`; no fabricated history.

**Deliverables:** migration, model, service, automated tests.

**Dependencies:** Phase 9. Decisions 6 and 10 are approved; neither a status-column migration nor a backfill is in scope.

**Acceptance criteria:**
- Stages progress sequentially; manual Stage 1 confirmation starts tracking; Finalization does not.
- Tracking status does not alter document status; archival never sets `Done`.
- History is append-only with timestamp, actor and optional note; corrections add rows without overwriting.
- Status is derived: no rows → `Not Tracked`; Stage 1–4 → `In Progress`; Stage 5 → `Done`. All existing PRs derive `Not Tracked`.

**Tests:** sequential transition tests, manual-Stage-1 test, Final-does-not-start test, derived-status mapping tests (none/1–4/5), append-only correction test, no-stored-column verification, independence from payment test.

**Out of scope:** PR document archive, contract relationship changes, payment tracking, UI integration beyond what the tracking service requires for tests.

## Phase 11 — PR and Contract Integration

**Purpose:** link Contracts/POs to PRs without changing PO uniqueness or vendor rules.

**Scope:**
- Add nullable `contracts.purchase_request_id`.
- Update Contract create/edit interfaces and PR detail display of linked Contracts/POs.
- Implement restricted unlinking: block when the Contract has LHSTP records or Payment Records; record every successful unlink in Activity History (Decision 13).

**Deliverables:** migration, model relationship, service/controller updates, views, tests.

**Dependencies:** Phase 9. Decisions 9 and 13 are approved. Payment-Record presence checks depend on the Phase 13 schema; until then, that unlink condition cannot be fully exercised.

**Acceptance criteria:**
- One PR has many Contracts; one Contract has at most one PR; unlinked Contracts remain valid.
- PO Number uniqueness and single-vendor relationship unchanged.
- Relationship visible and manageable from PR and Contract interfaces.
- PR permanent deletion is blocked until all linked Contracts are unlinked; unlinking is blocked when LHSTP or Payment Records exist; successful unlinks appear in Activity History; no cascade delete reaches Contracts, LHSTPs, payment records or history.

**Tests:** relationship tests, unlinked-contract test, duplicate-PO regression test, legacy-contract preservation test, unlink-blocking tests (LHSTP present, Payment Records present), activity-log test, PR-purge blocking test, no-cascade test.

**Out of scope:** tracking changes, document archive, payment tracking, contract termin refactor.

## Phase 12 — PR Document Archive

**Purpose:** store user-uploaded PR documents privately with category, notes and recycle-bin lifecycle.

**Scope:**
- Add `pr_documents` entity, service, upload/preview/download routes and recycle-bin registration.
- Replace arbitrary path-based file access with record-ID authorization.

**Deliverables:** migration, model, service, routes, controller, views, tests.

**Dependencies:** Phase 10 for PR context. Payment evidence decisions apply to Phase 13, not to this phase.

**Acceptance criteria:**
- Five approved categories enforced.
- Private storage outside public web root; record-level authorization enforced.
- Preview only for PDF/JPG/PNG; other formats download-only.
- Delete uses Shared Recycle Bin; restore keeps file; permanent purge removes metadata and physical file.
- Finalized PR versions and generated documents are never modified.

**Tests:** upload validation, category validation, authorization, preview allowlist, purge file deletion, restore test, generated-document integrity test.

**Out of scope:** contract attachments, payment evidence, changes to `generated_documents`.

## Phase 13 — Payment Tracking

**Purpose:** record actual payments against contracts/terms, independent of LHSTP status.

**Scope:**
- Add `payment_records` with `Draft`/`Confirmed` state, correction entries and `PaymentStatusCalculator` using Confirmed payments only (Decision 11).
- Add `payment_evidences` with PDF/JPG/JPEG/PNG formats and 10 MB per-file limit, recycle-bin delete/restore and purge (Decision 12).
- Add the Final LHSTP revision/archive guard against Confirmed-payment overpayment (Decision 14).
- Derive PR-level payment summary from linked contracts.

**Deliverables:** migrations, models, services, views, tests.

**Dependencies:** Phase 11 for the PR-to-contract link. Decisions 7, 8, 11, 12, 14 and 15 are approved; no payment edge case blocks this phase.

**Acceptance criteria:**
- Payment records belong to a contract and optionally a termin; a contract may have many records, each with multiple evidence attachments.
- Obligation = accumulated Final LHSTP totals for the contract; Draft and Archived LHSTPs do not contribute; overpayments are rejected.
- New records are `Draft` and excluded from totals; only Confirmed payments count. Confirmed records are immutable; corrections append linked entries preserving originals and history.
- Status values Unpaid / Partially Paid / Paid derived from Confirmed records. Individual LHSTP progress visible where applicable; contract status summarizes the position.
- Evidence accepts PDF/JPG/JPEG/PNG up to 10 MB per file; deletion uses the Shared Recycle Bin, restore keeps the file, permanent purge removes metadata and physical files.
- Final LHSTP revision or archiving is blocked if it would make Confirmed payments exceed the revised obligation; resolution requires a recorded payment correction.
- Final LHSTP does not imply payment; payment does not change tracking status. PR summary is derived, not a separate source of truth.

**Tests:** status calculation tests, Draft-exclusion test, Final-only obligation test, overpayment rejection test, confirmed-immutability test, correction-entry test, partial/full payment tests, multiple-payment tests, evidence format/size validation tests, multiple-evidence tests, evidence restore/purge tests, LHSTP revision/archive guard tests, LHSTP-independence test, tracking-independence test.

**Out of scope:** LHSTP calculation-formula changes, dashboard changes not explicitly approved, PR tracking status changes, unapproved payment edge cases.

## Phase 14 — UI Integration and Regression

**Purpose:** integrate all addendum features into PR and Contract screens and verify existing modules.

**Scope:**
- PR list/detail: tracking status, timeline, document archive, linked Contracts/POs, payment summary.
- Contract detail: linked PR and payment records.
- Recycle Bin: `pr_document` and `payment_evidence` entity types.
- Full regression of existing modules, including LHSTP revision/archive guard and Contract unlink restrictions, plus backup/restore verification.

**Deliverables:** view updates, regression test run, verification report.

**Dependencies:** Phases 10–13.

**Acceptance criteria:**
- All existing modules still pass their prior acceptance criteria.
- Recycle-bin restore and permanent purge work for all new deletable entities.
- Backup and restore cover database and private files.
- Existing dashboard calculations unchanged unless explicitly approved.

**Tests:** full suite, recycle-bin restore/purge tests, backup/restore verification, cross-module navigation checks.

**Out of scope:** new dashboard metrics, role-based access control, the separately scoped `ContractService::update()` termin defect fix.
