# Technical Design Document (TDD)
## ITMS Administrative Management Web Application

**Document status:** Initial technical baseline  
**Version:** 0.3  
**Date:** 16 November 2026  
**Related document:** ITMS Administrative App PRD Draft v0.2  
**Architecture style:** Centralized local web application, LAN-ready

---

## 1. Executive Decision

The application will use a conventional server-rendered Laravel architecture backed by MySQL and served by Apache.

| Layer | Selected technology | Purpose |
|---|---|---|
| Backend framework | Laravel 13.x | Routing, business rules, validation, authentication, database access, background/scheduled tasks |
| Language | PHP 8.5.x stable release | Application language |
| Database | MySQL 8.4 LTS | Relational data storage |
| Web server | Apache HTTP Server 2.4 | HTTP serving and PHP integration |
| Frontend rendering | Laravel Blade | Server-rendered application pages |
| CSS | Tailwind CSS 4.x | Consistent responsive interface |
| Browser scripting | Vanilla JavaScript, with Alpine.js only where useful | Small interactive behaviors |
| PDF generation | Dompdf initially, subject to template-fidelity proof of concept | PR and RAB PDFs; LHSTP PDF if layout tests pass |
| DOCX generation | PHPWord / TemplateProcessor | LHSTP Word output using a controlled DOCX template |
| Database schema management | Laravel Migrations | Repeatable, version-controlled schema changes |
| Dependency management | Composer and npm | PHP and frontend dependencies |
| Version control | Git | Source history and safe development |
| Testing | PHPUnit or Pest (choose one during project bootstrap; default Pest) | Automated feature and calculation tests |

### Version policy

- Use the latest stable patch release of PHP 8.5 at environment setup; do not use PHP 8.6 pre-release builds.
- Use Laravel 13.x, provided all required libraries and the deployment environment are compatible.
- Use MySQL 8.4 LTS rather than a short-lived innovation release.
- Pin dependency versions in `composer.lock` and `package-lock.json`.
- Re-check supported versions before installation; security support and library compatibility take priority over choosing the newest number.

### Why this stack

- It matches the user's existing familiarity with Apache and MySQL.
- Laravel provides a structured foundation for validation, database transactions, authentication, migrations, scheduling, and file handling.
- A server-rendered Blade interface avoids unnecessary frontend complexity for a form- and document-oriented administrative system.
- MySQL supports the relational integrity needed across PR, RAB, contracts, LHSTP, vendors, termin payments, and revision histories.
- The architecture can start on one Windows computer and later be accessed by other computers on the same LAN without redesigning the application.

---

## 2. Deployment Architecture

### 2.1 Initial deployment

One designated computer acts as the application server. It runs:

- Apache
- PHP runtime
- Laravel application
- MySQL database
- Private document storage
- Scheduled maintenance/backup tasks

The user accesses the application through a browser at a local address such as `http://localhost/`. The exact URL and installation paths are environment configuration, not application assumptions.

### 2.2 LAN expansion

When additional computers need access:

- Keep the application and database on the designated server computer.
- Other computers access the server through its reserved LAN IP address or local DNS name.
- Install only a browser on client computers.
- Do not expose MySQL directly to client computers; only the Laravel application connects to MySQL.
- Restrict Apache access to the trusted office network or approved devices.
- Use a stable/reserved server IP and configure Windows Firewall or equivalent network controls.

### 2.3 Internet dependency

The application must work for normal business operations without requiring an external SaaS service. Internet access may be used for installation, dependency updates, and optional future integrations. No essential PR/RAB/Contract/LHSTP workflow should depend on an external API.

### 2.4 Logical request flow

`Browser → Apache → Laravel → MySQL`

Laravel also reads and writes private files in application storage. Generated documents are delivered to the browser through authorized Laravel routes; private storage folders must not be directly web-accessible.

### 2.5 Important operational constraint

The server computer must remain powered on and connected to the office network whenever other computers need to use the application. A server shutdown or network interruption makes the application temporarily unavailable to LAN clients.

---

## 3. Application Architecture

Use a modular monolith, not microservices.

A modular monolith means one Laravel application and one database, with clear internal boundaries for each business module. This is simpler to deploy and maintain for the initial single-user application while preserving room for growth.

Recommended application layers:

1. **Presentation layer:** Blade views, Tailwind CSS, limited JavaScript.
2. **HTTP layer:** routes, controllers, request validation, authentication middleware.
3. **Application/service layer:** business workflows such as finalization, revision creation, PR-to-RAB import, payment calculations, and document generation.
4. **Domain/model layer:** Eloquent models, relationships, status rules, and domain-level validation.
5. **Infrastructure layer:** MySQL, private file storage, PDF/DOCX libraries, scheduler, logging, and backup scripts.

Controllers should remain thin. Business rules must not be duplicated in Blade templates or scattered across controllers.

### 3.1 Proposed module boundaries

- Dashboard
- Purchase Requisition
- RAB
- Contract Management
- LHSTP
- Master Data
- Recycle Bin
- Document Generation
- Activity History
- Authentication and Settings

---

## 4. Database Design Principles

### 4.1 Database

Database name: `itms_admin_db`

Use MySQL with the InnoDB storage engine and `utf8mb4` character set/collation.

All schema changes must be created through Laravel Migrations. phpMyAdmin may be used for inspection and administration, but it is not the source of truth for schema design.

### 4.2 Naming conventions

- Table names: plural `snake_case`, e.g. `purchase_requests`.
- Column names: `snake_case`, e.g. `contract_start_date`.
- Primary keys: unsigned BIGINT auto-increment `id`, except where a special identifier is justified.
- Foreign keys: `{related_table_singular}_id`.
- Timestamps: `created_at`, `updated_at`.
- Soft deletion timestamp: `deleted_at` where applicable.
- Business document numbers are separate from database primary keys.

### 4.3 Data types and precision

- Currency: `DECIMAL(18,2)`; never use floating-point types for money.
- Quantity: `DECIMAL(15,3)` to support up to three decimal places.
- Percentages/rates: `DECIMAL(7,4)` or a suitable fixed-precision decimal.
- Dates: `DATE` for business dates.
- Activity timestamps: `DATETIME`/`TIMESTAMP` stored consistently; display in the configured local timezone (Asia/Jakarta).
- Long descriptions and document text: `TEXT` where appropriate.

Store numeric values as numbers, not formatted strings containing `Rp`, periods, or commas.

### 4.4 Referential integrity

- Use foreign keys for required relationships.
- Use restrictive delete behavior for business records with history or dependencies.
- Avoid cascading hard deletes across PR/RAB/Contract/LHSTP records.
- Master data referenced by transactions should normally be deactivated/archived rather than physically deleted.
- Use database transactions for multi-table operations such as finalization, revision creation, PR-to-RAB import, and termin reservation.

---

## 5. Proposed Relational Data Model

This is the initial logical schema. Exact column definitions and migration order will be refined before implementation.

### 5.1 System and master data

| Table | Purpose / key fields |
|---|---|
| `users` | Initial single login account; name, email/username, password hash, active state |
| `departments` | Department reference data; seed Accounting Department, Human Capital Department, and ITMS as required |
| `vendors` | Company name, address, NPWP, phone, email, active state |
| `units` | Reusable RAB units; unit name, active state |
| `app_settings` | Configurable application settings such as timezone and document defaults; avoid hardcoding operational preferences |

### 5.2 Purchase Requisition

| Table | Purpose / key fields |
|---|---|
| `purchase_requests` | Stable PR identity: PR number, current status, current version reference, deleted/archive metadata |
| `purchase_request_versions` | Immutable PR header snapshot per revision: recipient department, subject, date, category, total, version number, finalized timestamp |
| `purchase_request_items` | Budget rows belonging to a PR version: budget name, quantity, stored unit price, estimated line total, display order |
| `purchase_request_documents` | Generated PDF metadata linked to a PR version |

### 5.3 RAB

| Table | Purpose / key fields |
|---|---|
| `budgets` | Stable RAB identity: RAB number, optional linked PR, current status, current version reference |
| `budget_versions` | Immutable RAB header snapshot per revision: work name, date, location, related PR version/reference, total, version number |
| `budget_items` | Rows belonging to a RAB version: budget name, description, quantity, unit reference/name snapshot, unit price, line amount, display order |
| `budget_documents` | Generated PDF metadata linked to a RAB version |

### 5.4 Contract Management

| Table | Purpose / key fields |
|---|---|
| `contracts` | Contract identity and current data: unique PO number, contract date, vendor, work name, representative, dates, value, PPN inclusion, payment method, status |
| `contract_termins` | Termin name/sequence, percentage, calculated nominal amount, ordering, and usage state derived from finalized LHSTPs |
| `contract_attachments` | Original contract PDF attachments and addenda; private storage path, original filename, size, upload timestamp |
| `contract_activity_logs` | Before/after values for important contract changes, actor, timestamp, and change description |

Contract data is editable as agreed. Existing LHSTP versions must not be changed by later contract edits.

### 5.5 LHSTP

| Table | Purpose / key fields |
|---|---|
| `lhstps` | Stable LHSTP identity: unique LHSTP number, linked contract, current status, current version reference |
| `lhstp_versions` | Immutable snapshot of LHSTP inputs and contract data: work period, dates, reference document, vendor/representative snapshot, payment method/amount, PPN status/rate, DPP, PPN, total, Cost Center, conclusion, payment terms, version number |
| `lhstp_version_termins` | Links an LHSTP version to the single selected contract termin when applicable; preserves the selected termin snapshot |
| `lhstp_documents` | PDF and DOCX files generated for a particular LHSTP version, with format and storage metadata |

### 5.6 Shared application services

| Table | Purpose / key fields |
|---|---|
| `activity_logs` | General activity history: actor, action, entity type/id, summary, timestamp, optional before/after payload |
| `recycle_bin_items` | Shared deletion registry: entity type/id, original deletion timestamp, expiry timestamp, deleted by, optional metadata |
| `generated_documents` (optional consolidation) | May replace module-specific document tables if a single document registry proves simpler; stores entity/version reference, type, format, path, checksum, created timestamp |

The implementation should choose either module-specific document tables or one shared `generated_documents` table, not both for the same files. The default recommendation is one shared document registry if it can preserve strict entity/version relationships cleanly.

---

### 5.7 ERD Reference

The proposed logical ERD is maintained in the companion file `ITMS_Administrative_App_ERD.md`. It defines the parent/version model for PR, RAB, and LHSTP; contract/vendor/termin relationships; shared generated-document metadata; activity history; and the shared Recycle Bin registry. The ERD is a logical design and must be reviewed before Laravel migrations are created.

## 6. Relationship Overview

- One vendor can have many contracts.
- One contract can have many contract attachments.
- One contract can have many termin records when Payment Method is Termin.
- One contract can have many LHSTP records.
- One LHSTP record can have many revisions.
- One LHSTP version can reference at most one contract termin.
- A termin may be used by at most one Final LHSTP record; a revision of that same LHSTP retains its original termin.
- One PR can be linked to zero or one RAB.
- One RAB can be linked to zero or one PR.
- One PR has many PR versions; each PR version has many PR items.
- One RAB has many RAB versions; each RAB version has many RAB items.
- Each generated document belongs to a specific business record and version.
- Master Unit and Vendor records are referenced by transaction data; snapshots preserve the relevant text where historical display must remain unchanged.

### 6.1 Revision model

Use a stable parent record plus immutable version records for PR, RAB, and LHSTP.

- The parent stores the permanent business number and current status/current-version pointer.
- A version stores a complete snapshot of the data at that revision.
- Finalized versions are immutable.
- Creating a revision creates a new version; it does not overwrite the previous version.
- Draft editing may update the current draft version until finalization.
- Generated PDFs/DOCX files are linked to the exact version that produced them.

Contract does not use the same document revision model in the initial release. Contract edits update the current contract record and create activity log entries. LHSTP versions copy contract fields needed for historical accuracy.

---

## 7. Business Workflow Implementation

### 7.1 PR to RAB import

The import must execute in a database transaction.

- Copy all PR items from the selected PR version.
- Copy the stored PR unit price directly into RAB unit price.
- Copy PR estimated line total into the initial RAB line amount where needed.
- Do not calculate unit price by dividing estimated total by quantity.
- Store the source PR and source PR version reference for traceability.
- Validate linked PR/RAB total equality before RAB finalization.
- Changes to RAB must never write back to PR.

### 7.2 Finalization and revisions

Finalization must be an atomic operation:

1. Validate all required fields and business rules.
2. Verify linked records and constraints.
3. Mark the version Final and update the parent current-version/status pointer.
4. Write an activity log.
5. Commit the database transaction.
6. Ask whether to generate the document. Document generation can be retried separately if declined or if generation fails.

Document-generation failure must not silently undo an already finalized business record. Show a clear error and allow document generation to be retried from the detail page.

### 7.3 Termin reservation

When an LHSTP is finalized against a termin, the system must ensure no other Final LHSTP has already consumed that termin. Enforce this in application logic within a database transaction and, where practical, with a database uniqueness strategy. Revisions of the same LHSTP retain the same termin and do not consume it twice.

### 7.4 Contract status

Contract status is Active, Inactive, or Archived. Inactive status is determined from the contract end date; Archived is manually assigned. The application should calculate the effective status when reading data and/or use a scheduled task to synchronize stored status. Archived must never be overwritten by automatic Inactive processing.

### 7.5 Recycle Bin

- Use a shared recycle-bin interface across supported modules.
- On deletion, register entity type/id, deleted timestamp, expiry timestamp (30 days), and actor.
- Restore removes the recycle-bin registration and restores the record only if its dependencies remain valid.
- Manual permanent deletion requires an additional confirmation.
- A scheduled Laravel task permanently deletes eligible records after 30 days.
- Permanent deletion must respect historical dependencies. If a record is legally/technically protected by retained history, the system must either delete dependent data safely as one unit or block and explain why; do not leave orphaned references.
- Final and Archived records remain protected according to the PRD lifecycle rules.

---

## 8. File and Document Storage

### 8.1 Storage layout

Store documents outside the public web root, for example under Laravel's private `storage/app` area.

Suggested logical folders:

- `contracts/{contract_id}/attachments/`
- `pr/{pr_id}/versions/{version_id}/`
- `rab/{rab_id}/versions/{version_id}/`
- `lhstp/{lhstp_id}/versions/{version_id}/`

Store file metadata in MySQL: generated/original filename, private relative path, MIME type, size, checksum, entity/version reference, created timestamp, and document format.

### 8.2 Access

- Files are served through authenticated Laravel download/view routes.
- Validate permissions and entity relationships before returning a file.
- Validate file extension, MIME type, and file size on upload.
- Contract attachments are PDF-only in the initial scope.
- Never trust the original uploaded filename as a filesystem path.
- Use generated unique filenames to avoid collisions.

### 8.3 Document generation

- PR and RAB generate PDF.
- LHSTP generates both PDF and DOCX.
- Every output is tied to an immutable version.
- Do not overwrite an older document when a new revision is created.
- If the user declines document generation after finalization, the finalized record remains saved and document generation can be triggered later without a new revision.

### 8.4 Template fidelity proof of concept

Before building all document workflows, create a small proof of concept using the supplied PR, RAB, and two-page LHSTP templates.

Evaluate:
- Indonesian fonts and character rendering.
- Page breaks and two-page LHSTP structure.
- Logos and image placement.
- Tables, paragraph spacing, margins, and signature blocks.
- Long work names, vendor names, and descriptions.
- DOCX opening and editing in Microsoft Word.
- PDF output consistency.

If Dompdf cannot reproduce the LHSTP layout sufficiently, select a more suitable renderer before implementing the full document module. Do not compromise the template requirement merely to keep one library.

---

## 9. Security, Reliability, and Backup

### 9.1 Authentication

- Require login even for the initial single-user release.
- Store passwords using Laravel's password hashing.
- Use secure session settings and CSRF protection.
- No public registration.
- The initial release has one account and no role-based permissions.

### 9.2 Network security

- Bind MySQL to localhost or the server's private interface as required; do not expose it to the wider network.
- Allow web access only from trusted office devices/network.
- Keep `.env`, credentials, logs, and private storage outside the public web root.
- Do not commit secrets or database passwords to Git.

### 9.3 Backup

Recommended initial policy:

- Daily automated database backup.
- Daily backup of uploaded and generated documents.
- Keep at least 7 daily backups and 4 weekly backups, subject to available storage and company policy.
- Keep backup copies on a separate drive or approved network location, not only on the server's primary disk.
- Test restoration periodically; a backup that has never been restored is not proven.
- Document a simple restore procedure.

### 9.4 Logging and error handling

- Record important business actions in activity logs.
- Keep technical errors in application logs, separate from user-facing activity history.
- Show understandable messages to the user; do not expose stack traces or database details.
- Use transactions for multi-record business operations.

---

## 10. Testing Strategy

### 10.1 Unit tests

Test isolated calculations and rules:

- PR and RAB line totals and grand totals.
- PR/RAB equality validation.
- Termin percentage and nominal calculations.
- LHSTP PPN calculations for both inclusion statuses and editable rates.
- Final payment rounding and Terbilang.
- Inclusive realization-day calculation.
- Contract status rules.
- 30-day Recycle Bin expiry.

### 10.2 Feature tests

Test end-to-end business flows:

- Create, edit, finalize, revise, archive, and delete PR.
- Create standalone RAB and RAB from PR.
- Ensure RAB edits never alter PR.
- Prevent finalization when linked totals differ.
- Create contracts for each payment method.
- Reject duplicate PO numbers.
- Create LHSTP from contract data and verify snapshots.
- Prevent reuse of a termin already consumed by a Final LHSTP.
- Allow revision of the same LHSTP with its original termin.
- Confirm document generation can be retried after finalization.
- Restore and permanently delete items from the shared Recycle Bin.

### 10.3 Document tests

Compare generated files against supplied templates using representative short and long content. Validate both visual appearance and text correctness.

### 10.4 LAN tests

Before multi-computer rollout, test:
- Access from at least two client computers.
- Simultaneous page access and safe updates.
- Server restart and application recovery.
- Backup and restore.
- File download authorization.

---

## 11. Development Structure

Proposed Laravel project organization:

```text
app/
  Http/
    Controllers/
    Requests/
  Models/
  Services/
    Documents/
    Payments/
    Revisions/
    RecycleBin/
  Actions/
  Policies/
  Console/
    Commands/

resources/
  views/
    layouts/
    dashboard/
    pr/
    rab/
    contracts/
    lhstp/
    master-data/
    recycle-bin/
  css/
  js/

database/
  migrations/
  seeders/
  factories/

routes/
  web.php
  console.php

storage/
  app/
    private/
      contracts/
      pr/
      rab/
      lhstp/
      backups/
```

The exact structure may evolve, but module boundaries and separation of responsibilities must remain clear.

---

## 12. Implementation Phases

1. **Environment and project bootstrap:** PHP, Composer, Laravel, Apache, MySQL, Git, environment checks.
2. **Database foundation:** migrations, seeders, master data, authentication, activity logging, shared Recycle Bin.
3. **Application shell:** navigation, layout, dashboard, settings.
4. **PR module:** form, items, numbering, revisions, PDF.
5. **RAB module:** standalone and PR-based creation, import, equality validation, revisions, PDF.
6. **Contract module:** vendors, contracts, attachments, payment methods, termin validation, history.
7. **LHSTP module:** contract snapshots, payment calculations, termin usage, warnings, revisions, PDF and DOCX.
8. **Hardening:** automated tests, document template comparisons, backup/restore, LAN access testing.

---

## 13. Technical Decisions and Deferred Details

### Confirmed technical baseline

- Laravel + PHP + MySQL + Apache.
- Blade + Tailwind CSS; limited JavaScript.
- Centralized server architecture, initially one computer and LAN-ready.
- New database: `itms_admin_db`.
- Existing database is not reused as the application schema; it may be retained as a read-only reference/source for any future data migration.
- Private file storage outside the public web root.
- Laravel migrations are the source of truth for schema.
- Modular monolith architecture.

### Deferred until environment setup or proof of concept

- Exact Windows installation paths and Apache virtual host configuration.
- Exact patch versions and PHP extensions available in the selected Windows package.
- Final PDF rendering library if the template proof of concept shows Dompdf is insufficient.
- Whether a single shared generated-document table or module-specific document tables is simpler after final ERD review.
- Exact LAN IP/DNS and firewall configuration.
- Backup destination and retention approval under company policy.

---

## 14. References

Version and support choices should be rechecked at implementation time against official documentation:

- PHP supported versions: https://www.php.net/supported-versions.php
- PHP downloads: https://www.php.net/downloads.php
- Laravel release notes and support policy: https://laravel.com/docs/releases
- Laravel documentation: https://laravel.com/docs
- MySQL 8.4 Reference Manual: https://dev.mysql.com/doc/refman/8.4/en/
- Apache HTTP Server documentation: https://httpd.apache.org/docs/2.4/
- PHPWord documentation: https://phpoffice.github.io/PHPWord/
- Dompdf project documentation: https://github.com/dompdf/dompdf


## 15. Companion ERD

See `ITMS_Administrative_App_ERD.md` for the detailed Mermaid ERD, table groups, proposed constraints, and deletion/retention notes.


## 16. Companion Design Specifications

- `ITMS_Administrative_App_ERD.md` — logical entity relationship model.
- `ITMS_Administrative_App_Database_Schema.md` — proposed physical table and column specification, constraints, indexes, and migration order.
- `ITMS_Administrative_App_Implementation_Blueprint.md` — navigation, screen behavior, lifecycle transitions, validations, calculations, document pipeline, scheduled tasks, and acceptance criteria.

- `ITMS_Administrative_App_UI_UX_Specification.md` — visual and interaction design baseline.
