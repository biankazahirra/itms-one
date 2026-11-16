# Development Phases — ITMS Administrative Application

This sequence is the approved delivery order. Work only on the current phase. Each phase must pass its acceptance criteria before the next begins.

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
