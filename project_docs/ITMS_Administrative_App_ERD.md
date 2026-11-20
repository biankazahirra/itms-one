# Entity Relationship Diagram (ERD)
## ITMS Administrative Management Web Application

**Status:** Proposed logical data model for review  
**Version:** 0.2  
**Addendum:** 19 November 2026 — PR Tracking, Document Archive & Payment Tracking (Section 8)
**Related documents:** ITMS Administrative App PRD Draft v0.5; Technical Design Document v0.4

This ERD translates the approved business requirements into a proposed relational structure. It is a logical design, not yet an executable migration set. Physical column definitions, indexes, delete behavior, and constraints will be finalized before database migrations are written.

## 1. Design principles

1. Business document numbers are separate from database primary keys.
2. PR, RAB, and LHSTP use a stable parent record plus immutable version records.
3. Finalized versions are historical snapshots and must not be overwritten.
4. Contract edits update current contract data and are recorded in contract activity history. LHSTP versions preserve snapshots of the contract values used when they were created.
5. Money uses fixed-precision decimal types, never floating point.
6. Uploaded and generated files are stored outside the public web root; MySQL stores file metadata and private storage paths.
7. Shared Recycle Bin behavior is implemented through soft deletion and a central deletion registry. Permanent deletion must respect dependencies and retention rules.
8. This design does not reuse the legacy database schema. The new database will be `itms_admin_db`.

## 2. ERD

```mermaid
erDiagram
    USERS {
        bigint id PK
        string name
        string username UK
        string password
        boolean is_active
        datetime created_at
        datetime updated_at
    }

    DEPARTMENTS {
        bigint id PK
        string name UK
        boolean is_active
    }

    VENDORS {
        bigint id PK
        string company_name
        text company_address
        string npwp
        string phone
        string email
        boolean is_active
        datetime created_at
        datetime updated_at
    }

    UNITS {
        bigint id PK
        string unit_name UK
        boolean is_active
    }

    PURCHASE_REQUESTS {
        bigint id PK
        string pr_number UK
        string status
        bigint current_version_id FK
        datetime finalized_at
        datetime deleted_at
    }

    PURCHASE_REQUEST_VERSIONS {
        bigint id PK
        bigint purchase_request_id FK
        int version_number
        bigint recipient_department_id FK
        string subject
        date pr_date
        string category
        decimal grand_total
        bigint created_by FK
        datetime finalized_at
    }

    PURCHASE_REQUEST_ITEMS {
        bigint id PK
        bigint purchase_request_version_id FK
        string budget_name
        decimal quantity
        decimal unit_price
        decimal estimated_total
        int sort_order
    }

    PR_STAGE_UPDATES {
        bigint id PK
        bigint purchase_request_id FK
        string stage
        text note
        datetime occurred_at
        bigint created_by FK
        datetime created_at
    }

    PR_DOCUMENTS {
        bigint id PK
        bigint purchase_request_id FK
        string category
        string original_filename
        string storage_path
        string mime_type
        bigint file_size
        string checksum_sha256
        bigint uploaded_by FK
        datetime uploaded_at
        text notes
        datetime deleted_at
    }

    PAYMENT_RECORDS {
        bigint id PK
        bigint contract_id FK
        bigint contract_termin_id FK
        bigint corrects_payment_record_id FK
        string status
        date payment_date
        decimal payment_amount
        string payment_reference
        bigint created_by FK
        datetime created_at
        datetime updated_at
    }

    PAYMENT_EVIDENCES {
        bigint id PK
        bigint payment_record_id FK
        string original_filename
        string storage_path
        string mime_type
        bigint file_size
        string checksum_sha256
        bigint uploaded_by FK
        datetime uploaded_at
        datetime deleted_at
    }

    BUDGETS {
        bigint id PK
        string rab_number UK
        bigint purchase_request_id FK
        string status
        bigint current_version_id FK
        datetime finalized_at
        datetime deleted_at
    }

    BUDGET_VERSIONS {
        bigint id PK
        bigint budget_id FK
        int version_number
        bigint source_pr_version_id FK
        string work_name
        date rab_date
        string location
        decimal grand_total
        bigint created_by FK
        datetime finalized_at
    }

    BUDGET_ITEMS {
        bigint id PK
        bigint budget_version_id FK
        string budget_name
        text description
        decimal quantity
        bigint unit_id FK
        string unit_name_snapshot
        decimal unit_price
        decimal line_total
        int sort_order
    }

    CONTRACTS {
        bigint id PK
        string po_number UK
        date contract_date
        bigint vendor_id FK
        bigint purchase_request_id FK
        string work_name
        string representative_name
        string representative_position
        date start_date
        date end_date
        decimal contract_value
        string ppn_status
        string payment_method
        string status
        datetime deleted_at
    }

    CONTRACT_TERMIN {
        bigint id PK
        bigint contract_id FK
        string termin_name
        decimal percentage
        decimal nominal_amount
        int sort_order
    }

    CONTRACT_ATTACHMENTS {
        bigint id PK
        bigint contract_id FK
        string original_filename
        string storage_path
        string mime_type
        bigint file_size
        datetime uploaded_at
    }

    CONTRACT_ACTIVITY_LOGS {
        bigint id PK
        bigint contract_id FK
        bigint user_id FK
        string action
        json before_values
        json after_values
        datetime created_at
    }

    LHSTPS {
        bigint id PK
        string lhstp_number UK
        bigint contract_id FK
        string status
        bigint current_version_id FK
        datetime finalized_at
        datetime deleted_at
    }

    LHSTP_VERSIONS {
        bigint id PK
        bigint lhstp_id FK
        int version_number
        string po_number_snapshot
        date contract_date_snapshot
        string vendor_name_snapshot
        string work_name_snapshot
        decimal contract_value_snapshot
        date contract_start_snapshot
        date contract_end_snapshot
        string representative_name_snapshot
        string representative_position_snapshot
        string payment_method_snapshot
        string ppn_status_snapshot
        string work_period
        date lhstp_date
        date realization_start
        date realization_end
        int realization_days
        string reference_document_type
        string reference_document_name
        string reference_document_number
        date reference_document_date
        text work_conclusion
        text payment_terms
        decimal payment_amount
        decimal ppn_rate
        decimal dpp_lain
        decimal ppn_amount
        decimal total_payment
        string cost_center
        date opname_date
        date signature_date
        bigint created_by FK
        datetime finalized_at
    }

    LHSTP_VERSION_TERMIN {
        bigint id PK
        bigint lhstp_version_id FK
        bigint contract_termin_id FK
        string termin_name_snapshot
        decimal percentage_snapshot
        decimal nominal_snapshot
    }

    GENERATED_DOCUMENTS {
        bigint id PK
        string documentable_type
        bigint documentable_id
        bigint version_id
        string document_type
        string format
        string original_filename
        string storage_path
        string mime_type
        bigint file_size
        string checksum
        datetime generated_at
        bigint generated_by FK
    }

    ACTIVITY_LOGS {
        bigint id PK
        bigint user_id FK
        string entity_type
        bigint entity_id
        string action
        text description
        json before_values
        json after_values
        datetime created_at
    }

    RECYCLE_BIN_ITEMS {
        bigint id PK
        string entity_type
        bigint entity_id
        bigint deleted_by FK
        datetime deleted_at
        datetime expires_at
        json restore_metadata
    }

    USERS ||--o{ PURCHASE_REQUEST_VERSIONS : creates
    USERS ||--o{ BUDGET_VERSIONS : creates
    USERS ||--o{ LHSTP_VERSIONS : creates
    USERS ||--o{ GENERATED_DOCUMENTS : generates
    USERS ||--o{ ACTIVITY_LOGS : performs
    USERS ||--o{ CONTRACT_ACTIVITY_LOGS : performs
    USERS ||--o{ RECYCLE_BIN_ITEMS : deletes

    DEPARTMENTS ||--o{ PURCHASE_REQUEST_VERSIONS : recipient

    PURCHASE_REQUESTS ||--|{ PURCHASE_REQUEST_VERSIONS : has
    PURCHASE_REQUEST_VERSIONS ||--|{ PURCHASE_REQUEST_ITEMS : contains
    PURCHASE_REQUESTS ||--o{ BUDGETS : may_be_linked_to
    PURCHASE_REQUESTS ||--o{ PR_STAGE_UPDATES : records
    PURCHASE_REQUESTS ||--o{ PR_DOCUMENTS : archives
    PURCHASE_REQUESTS ||--o{ CONTRACTS : may_be_linked_to
    CONTRACTS ||--o{ PAYMENT_RECORDS : receives
    CONTRACT_TERMIN o|--o{ PAYMENT_RECORDS : applies_to
    PAYMENT_RECORDS ||--|{ PAYMENT_EVIDENCES : has
    PURCHASE_REQUEST_VERSIONS o|--o{ BUDGET_VERSIONS : source_for

    BUDGETS ||--|{ BUDGET_VERSIONS : has
    BUDGET_VERSIONS ||--|{ BUDGET_ITEMS : contains
    UNITS o|--o{ BUDGET_ITEMS : unit

    VENDORS ||--o{ CONTRACTS : supplies
    CONTRACTS ||--o{ CONTRACT_TERMIN : defines
    CONTRACTS ||--o{ CONTRACT_ATTACHMENTS : has
    CONTRACTS ||--o{ CONTRACT_ACTIVITY_LOGS : records

    CONTRACTS ||--o{ LHSTPS : has
    LHSTPS ||--|{ LHSTP_VERSIONS : has
    LHSTP_VERSIONS o|--o| LHSTP_VERSION_TERMIN : selects
    CONTRACT_TERMIN o|--o{ LHSTP_VERSION_TERMIN : selected_by

    PURCHASE_REQUEST_VERSIONS o|--o{ GENERATED_DOCUMENTS : document
    BUDGET_VERSIONS o|--o{ GENERATED_DOCUMENTS : document
    LHSTP_VERSIONS o|--o{ GENERATED_DOCUMENTS : document

    ACTIVITY_LOGS }o--|| USERS : actor
```

## 3. Table groups and key decisions

### 3.1 Master data

- `vendors`: reusable vendor/company details. Representative name and position are not stored here; they belong to each contract.
- `departments`: reusable department list used by PR recipient selection.
- `units`: reusable RAB units. RAB items also store `unit_name_snapshot` so old documents remain readable if a unit label is later renamed.
- `users`: one account for the initial release, with a structure that does not prevent adding more accounts later.

### 3.2 Purchase Requisition

- `purchase_requests` stores the permanent PR number and current lifecycle state.
- `purchase_request_versions` stores a complete header snapshot for each revision.
- `purchase_request_items` stores the line items for one version.
- `current_version_id` points to the currently active version; it must reference a version belonging to the same parent PR.
- A finalized version is immutable.

### 3.3 RAB

- `budgets` stores the permanent RAB number and optional link to one PR.
- `budget_versions` stores each RAB revision and the source PR version used for import, if any.
- `budget_items` stores item-level details.
- When RAB is created from PR, every PR item is imported. The stored PR unit price is copied directly; no unit price is reverse-calculated from the line total.
- RAB edits never update PR.
- If linked to a PR, the RAB total must equal the linked PR total before finalization.

### 3.4 Contract

- `contracts.po_number` is unique and is the primary business identifier.
- One vendor can have multiple contracts; each contract belongs to one vendor.
- Contract representative fields are stored on the contract, not the vendor.
- `contract_termin` is used for the Termin payment method.
- `contract_attachments` stores the main contract and addenda as separate PDF attachments.
- `contract_activity_logs` records the contract changes identified in the PRD.

### 3.5 LHSTP

- `lhstps` stores the permanent LHSTP number and links to one contract.
- `lhstp_versions` stores the full snapshot of contract values and all LHSTP-specific inputs/calculations for each version.
- `lhstp_version_termin` records the single selected termin for that LHSTP version, when the contract payment method is Termin.
- Each version can generate a PDF, DOCX, or both. Generated documents point to the exact version.
- Contract edits after an LHSTP is created must not change any existing LHSTP version.

### 3.6 Generated documents

A shared `generated_documents` table is proposed instead of separate PR/RAB/LHSTP document tables.

- `documentable_type` and `documentable_id` identify the parent business record.
- `version_id` identifies the exact PR/RAB/LHSTP version.
- `document_type` identifies the output purpose (e.g. final document).
- `format` identifies PDF or DOCX.
- A checksum helps detect accidental file replacement or corruption.

Because a polymorphic relationship cannot be protected by a conventional foreign key to several parent tables, the application service must validate the parent/version relationship before creating or deleting document records.

### 3.7 Recycle Bin

`recycle_bin_items` is a shared registry for soft-deleted records. The application must not hard-delete a record merely because its 30-day timer has expired if retained dependent records or legal/operational history would be orphaned. The purge workflow must verify dependency rules and either safely purge a complete eligible aggregate or report that the item is protected pending an explicit retention rule.

## 4. Important constraints and indexes

The following constraints are recommended:

- Unique: `purchase_requests.pr_number`.
- Unique: `budgets.rab_number`.
- Unique: `contracts.po_number`.
- Unique: `lhstps.lhstp_number` for distinct LHSTP parent records.
- Unique composite: `(purchase_request_id, version_number)`.
- Unique composite: `(budget_id, version_number)`.
- Unique composite: `(lhstp_id, version_number)`.
- Unique: `(vendors.company_name)` may be enforced after confirming the business rule for duplicate-name warnings; initially use normalized duplicate detection and a warning.
- Unique: `(units.unit_name)` and `(departments.name)`.
- Index foreign keys and commonly searched fields: PO number, vendor, work name, PR/RAB/LHSTP number, status, relevant dates.
- Contract termin percentages and nominal amounts must be nonnegative; the aggregate 100% rule is enforced by a transactional application service because a row-level CHECK cannot validate the sum of sibling rows.
- Quantities must be greater than zero; unit prices must not be negative.
- Currency uses `DECIMAL(18,2)`; quantity uses `DECIMAL(15,3)`.

## 5. Deletion and retention notes requiring implementation care

- Soft-delete eligible parent records and register them in `recycle_bin_items`.
- Restore must restore the same aggregate and preserve its children/history.
- The 30-day expiry is measured from `deleted_at`.
- Final and Archived PR/RAB/LHSTP records are protected from permanent deletion under the agreed lifecycle rules.
- Contract deletion must not destroy LHSTP snapshots or activity history.
- Before implementation, the exact aggregate purge policy must be mapped for each entity type. A safe default is to block permanent purge where dependencies cannot be removed without violating a retention rule.

## 6. Questions intentionally deferred

The following are implementation details, not blockers for the logical ERD:

- Exact physical column lengths and all nullable/default settings.
- Whether version numbers are generated with a database lock or another concurrency-safe method.
- Exact indexes for dashboard filters after query design.
- Whether `document_type` needs a lookup table or a constrained enum.
- Final strategy for purging records with cross-module dependencies.
- Whether the application stores all calculated totals or recalculates and verifies them on read. Recommended: store finalized totals as snapshots and verify calculations in tests.

## 7. Next step

Review the logical model and then produce Laravel migrations in dependency order. Before migrations are run, validate the few cross-module retention and deletion edge cases against the PRD.

---

## 8. Addendum entities and relationships (approved 19 November 2026)

### 8.1 PR stage updates (`pr_stage_updates`)

Append-only history for the five manual tracking stages. Each row stores the PR, stage, occurrence timestamp, actor, and optional note. The current stage is derived from history; no prior row is updated or deleted. Tracking Status is derived from this history and is not stored on `purchase_requests`: no stage rows → `Not Tracked`; Stage 1–4 → `In Progress`; Stage 5 (`Goods/Services Received`) → `Done` (Decision 10).

### 8.2 PR documents (`pr_documents`)

User-uploaded documents attached to one PR. Distinct from system-generated documents in `generated_documents`. Stores a fixed category, original filename, private storage path, MIME type, size, checksum, uploader, upload timestamp, and optional notes. Soft-deletable and registered in the shared Recycle Bin when deleted.

### 8.3 Payment records (`payment_records`)

Records actual payments against a Contract/PO. Optional `contract_termin_id` links a payment to a payment term for Termin contracts. Stores payment date, amount and reference. A contract may have many payment records. Each record has a `Draft` or `Confirmed` state; Draft records do not contribute to paid totals or payment status. Only Confirmed payments count as actual payments. Confirmed records cannot be directly edited or deleted; corrections are new records whose nullable `corrects_payment_record_id` links to the original record, preserving originals and history (Decision 11). Payment status (Unpaid / Partially Paid / Paid) is derived from Confirmed records against the accumulated total of Final LHSTP amounts for the contract; Draft and Archived LHSTPs are excluded and overpayments are rejected.

### 8.3.1 Payment evidences (`payment_evidences`)

Each payment record may have multiple evidence attachments. Each stores original filename, private storage path, MIME type, size, checksum, uploader and upload timestamp. Allowed formats are PDF, JPG, JPEG and PNG, with a maximum of 10 MB per file (Decision 12). Storage is private. Soft-deletable and registered in the shared Recycle Bin; restore keeps the file available, permanent purge removes metadata and physical file.

### 8.4 Relationship changes

- `contracts.purchase_request_id` is nullable: one PR → many Contracts/POs, one Contract/PO → at most one PR, unlinked Contracts remain valid.
- `pr_stage_updates` and `pr_documents` belong to one PR.
- `payment_records` belongs to one contract and optionally one contract termin; each has many `payment_evidences`. A correction record links back to an original payment record.
- Existing `contracts.po_number` uniqueness and single-vendor rule are unchanged.
- A Contract is unlinkable only if it has no LHSTP records and no Payment Records; every successful unlink is recorded in Activity History. A PR may be permanently deleted only after all linked Contracts are unlinked. No cascade delete reaches Contracts, LHSTPs, payment records or history (Decision 13).
- Revisions or archiving of a Final LHSTP are blocked when Confirmed payments would exceed the revised obligation; resolve through a recorded payment correction (Decision 14).

### 8.5 Constraints and indexes

- `pr_documents.category` constrained to the five approved categories.
- `pr_stage_updates.stage` constrained to the five approved stages; progression is sequential, Stage 1 must be manually confirmed, and Stage 5 yields `Done` (Decision 6). Tracking Status is derived; `purchase_requests` has no `tracking_status` column (Decision 10).
- Index `pr_stage_updates.purchase_request_id`, `pr_documents.purchase_request_id`, `payment_records.contract_id`, `payment_records.contract_termin_id`, `payment_records.corrects_payment_record_id`, and `contracts.purchase_request_id`.
- `payment_records.status` constrained to `Draft` / `Confirmed`; only Confirmed records count as payments (Decision 11).
- `payment_evidences` MIME type limited to PDF, JPG, JPEG, PNG; file size ≤ 10 MB per file (Decision 12).
- `contracts.purchase_request_id` uses restrictive delete: PR purge is blocked while links exist; unlink requires no LHSTP and no Payment Records (Decisions 9 and 13). No cascade delete on any dependent entity.

### 8.6 Immutability

Adding stage updates or documents must not modify finalized PR versions or generated documents. Confirmed payment records are immutable: corrections append new records only. LHSTP revisions and archival preserve original records and revision history while blocking changes that would create an overpayment condition. Payment records do not write to PR tracking status.
