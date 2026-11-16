# Database Schema Specification
## ITMS Administrative Management Web Application

**Status:** Proposed physical schema specification  
**Version:** 0.1  
**Database:** `itms_admin_db`  
**Engine:** MySQL 8.4 LTS / InnoDB / utf8mb4  
**Related:** PRD v0.2, Technical Design v0.2, ERD v0.1

This document translates the logical ERD into implementation-ready table specifications. It is a design artifact; migrations should be generated only after this schema and the companion ERD are reviewed for consistency.

## 1. Conventions

- All tables use InnoDB and `utf8mb4`.
- Primary keys use `BIGINT UNSIGNED AUTO_INCREMENT`.
- Currency uses `DECIMAL(18,2)`.
- Quantity uses `DECIMAL(15,3)`.
- Percentage and tax rates use `DECIMAL(7,4)`.
- Business dates use `DATE`; event timestamps use `DATETIME`.
- Tables with normal mutable records use `created_at` and `updated_at`.
- Version tables are immutable after finalization.
- Use explicit status strings validated by Laravel; optionally add MySQL CHECK constraints for simple row-level constraints.
- Foreign keys use restrictive deletion by default. Avoid `ON DELETE CASCADE` for business history.
- All tables and fields should use English snake_case names; UI labels remain Indonesian where appropriate.

## 2. System and Master Data

### 2.1 `users`

| Column | Type | Null | Rule |
|---|---|---:|---|
| id | BIGINT UNSIGNED PK | No | Auto increment |
| name | VARCHAR(150) | No | |
| username | VARCHAR(80) | No | Unique |
| email | VARCHAR(190) | Yes | Unique when present |
| password | VARCHAR(255) | No | Laravel password hash |
| is_active | BOOLEAN | No | Default true |
| last_login_at | DATETIME | Yes | |
| created_at / updated_at | DATETIME | No | |

One account is seeded for the initial release. Do not store plaintext passwords.

### 2.2 `departments`

| Column | Type | Null | Rule |
|---|---|---:|---|
| id | BIGINT UNSIGNED PK | No | |
| name | VARCHAR(150) | No | Unique |
| is_active | BOOLEAN | No | Default true |
| created_at / updated_at | DATETIME | No | |

Seed ITMS, Accounting Department, and Human Capital Department. The PR recipient remains a manual selection, not inferred from category.

### 2.3 `vendors`

| Column | Type | Null | Rule |
|---|---|---:|---|
| id | BIGINT UNSIGNED PK | No | |
| company_name | VARCHAR(200) | No | Duplicate-name warning; do not silently merge |
| company_address | TEXT | No | |
| npwp | VARCHAR(40) | Yes | |
| phone | VARCHAR(40) | Yes | |
| email | VARCHAR(190) | Yes | |
| is_active | BOOLEAN | No | Default true |
| created_at / updated_at | DATETIME | No | |

Vendor representative name and position belong to the contract, not this table.

### 2.4 `units`

| Column | Type | Null | Rule |
|---|---|---:|---|
| id | BIGINT UNSIGNED PK | No | |
| unit_name | VARCHAR(80) | No | Unique |
| is_active | BOOLEAN | No | Default true |
| created_at / updated_at | DATETIME | No | |

Units can be added from the RAB workflow and reused.

### 2.5 `app_settings`

| Column | Type | Null | Rule |
|---|---|---:|---|
| id | BIGINT UNSIGNED PK | No | |
| setting_key | VARCHAR(100) | No | Unique |
| setting_value | TEXT | Yes | |
| updated_by | BIGINT UNSIGNED FK users.id | Yes | |
| created_at / updated_at | DATETIME | No | |

Use for editable defaults only when appropriate (e.g. default Cost Center). Do not put secrets in this table.

## 3. Number Sequences

### 3.1 `number_sequences`

| Column | Type | Null | Rule |
|---|---|---:|---|
| id | BIGINT UNSIGNED PK | No | |
| sequence_key | VARCHAR(30) | No | Unique (`PR`, `RAB`) |
| next_value | BIGINT UNSIGNED | No | Starts at 1 |
| updated_at | DATETIME | No | |

Generate business numbers inside a transaction with a row lock (`SELECT ... FOR UPDATE`) to prevent duplicate numbers under concurrent requests. Format as `PR-000001` and `RAB-000001`. Do not reuse numbers after deletion. LHSTP number is manually entered and unique across distinct LHSTP parent records; revisions remain under the same parent.

## 4. Purchase Requisition

### 4.1 `purchase_requests`

| Column | Type | Null | Rule |
|---|---|---:|---|
| id | BIGINT UNSIGNED PK | No | |
| pr_number | VARCHAR(30) | No | Unique, generated |
| status | VARCHAR(20) | No | `Draft`, `Final`, `Archived` |
| current_version_id | BIGINT UNSIGNED | Yes | FK to purchase_request_versions.id; validated to belong to this parent |
| finalized_at | DATETIME | Yes | |
| archived_at | DATETIME | Yes | |
| created_by | BIGINT UNSIGNED FK users.id | No | |
| updated_by | BIGINT UNSIGNED FK users.id | No | |
| created_at / updated_at | DATETIME | No | |
| deleted_at | DATETIME | Yes | Soft delete only when lifecycle permits |

### 4.2 `purchase_request_versions`

| Column | Type | Null | Rule |
|---|---|---:|---|
| id | BIGINT UNSIGNED PK | No | |
| purchase_request_id | BIGINT UNSIGNED FK | No | |
| version_number | INT UNSIGNED | No | Unique with parent |
| recipient_department_id | BIGINT UNSIGNED FK departments.id | No | |
| subject | VARCHAR(255) | No | |
| pr_date | DATE | No | User-selected |
| category | VARCHAR(40) | Yes | One of agreed categories |
| grand_total | DECIMAL(18,2) | No | Snapshot total |
| created_by | BIGINT UNSIGNED FK users.id | No | |
| finalized_by | BIGINT UNSIGNED FK users.id | Yes | |
| finalized_at | DATETIME | Yes | |
| created_at / updated_at | DATETIME | No | Draft may change; Final immutable |

Unique: `(purchase_request_id, version_number)`.

### 4.3 `purchase_request_items`

| Column | Type | Null | Rule |
|---|---|---:|---|
| id | BIGINT UNSIGNED PK | No | |
| purchase_request_version_id | BIGINT UNSIGNED FK | No | |
| budget_name | VARCHAR(500) | No | |
| quantity | DECIMAL(15,3) | No | Greater than zero |
| unit_price | DECIMAL(18,2) | No | Zero allowed with warning; not negative |
| estimated_total | DECIMAL(18,2) | No | Quantity × unit price |
| sort_order | INT UNSIGNED | No | |
| created_at / updated_at | DATETIME | No | |

PR PDF intentionally excludes `unit_price`. The stored unit price remains available for RAB import.

### 4.4 `purchase_request_versions` document rules

PR PDF metadata is stored in the shared `generated_documents` table, linked to the exact PR version. Do not store generated PDF bytes in MySQL.

## 5. RAB

### 5.1 `budgets`

| Column | Type | Null | Rule |
|---|---|---:|---|
| id | BIGINT UNSIGNED PK | No | |
| rab_number | VARCHAR(30) | No | Unique, generated |
| purchase_request_id | BIGINT UNSIGNED FK purchase_requests.id | Yes | Zero or one source PR per RAB |
| status | VARCHAR(20) | No | `Draft`, `Final`, `Archived` |
| current_version_id | BIGINT UNSIGNED | Yes | FK to budget_versions.id; same-parent validation |
| finalized_at | DATETIME | Yes | |
| archived_at | DATETIME | Yes | |
| created_by / updated_by | BIGINT UNSIGNED FK users.id | No | |
| created_at / updated_at | DATETIME | No | |
| deleted_at | DATETIME | Yes | |

A PR may be associated with multiple RAB records; an individual RAB may refer to at most one PR. This supports the agreed rule that one RAB cannot aggregate multiple PRs.

### 5.2 `budget_versions`

| Column | Type | Null | Rule |
|---|---|---:|---|
| id | BIGINT UNSIGNED PK | No | |
| budget_id | BIGINT UNSIGNED FK budgets.id | No | |
| version_number | INT UNSIGNED | No | Unique with parent |
| source_pr_version_id | BIGINT UNSIGNED FK purchase_request_versions.id | Yes | Exact imported version |
| work_name | VARCHAR(255) | No | Auto from PR subject on linked creation; editable thereafter per PRD |
| rab_date | DATE | No | |
| location | VARCHAR(120) | No | Default `Cilegon` |
| grand_total | DECIMAL(18,2) | No | |
| created_by | BIGINT UNSIGNED FK users.id | No | |
| finalized_by | BIGINT UNSIGNED FK users.id | Yes | |
| finalized_at | DATETIME | Yes | |
| created_at / updated_at | DATETIME | No | |

### 5.3 `budget_items`

| Column | Type | Null | Rule |
|---|---|---:|---|
| id | BIGINT UNSIGNED PK | No | |
| budget_version_id | BIGINT UNSIGNED FK budget_versions.id | No | |
| budget_name | VARCHAR(500) | No | |
| description | TEXT | Yes | |
| quantity | DECIMAL(15,3) | No | Greater than zero |
| unit_id | BIGINT UNSIGNED FK units.id | Yes | |
| unit_name_snapshot | VARCHAR(80) | Yes | Preserve printed historical unit |
| unit_price | DECIMAL(18,2) | No | Zero allowed with warning; not negative |
| line_total | DECIMAL(18,2) | No | Quantity × unit price |
| sort_order | INT UNSIGNED | No | |
| created_at / updated_at | DATETIME | No | |

The item table belongs to a specific version. A revised RAB receives a new complete set of items.

## 6. Contract Management

### 6.1 `contracts`

| Column | Type | Null | Rule |
|---|---|---:|---|
| id | BIGINT UNSIGNED PK | No | |
| po_number | VARCHAR(100) | No | Unique; duplicate blocks save |
| contract_date | DATE | No | |
| vendor_id | BIGINT UNSIGNED FK vendors.id | No | |
| work_name | VARCHAR(255) | No | |
| representative_name | VARCHAR(200) | No | |
| representative_position | VARCHAR(200) | No | |
| start_date | DATE | No | |
| end_date | DATE | No | On/after start |
| contract_value | DECIMAL(18,2) | No | Greater than zero |
| ppn_status | VARCHAR(30) | No | `Termasuk PPN` / `Belum termasuk PPN` |
| payment_method | VARCHAR(30) | No | `Bulanan` / `Termin` / `Full Financing` |
| monthly_payment_amount | DECIMAL(18,2) | Yes | Required and positive for Bulanan |
| full_financing_amount | DECIMAL(18,2) | Yes | Required and positive for Full Financing |
| status | VARCHAR(20) | No | `Aktif` / `Nonaktif` / `Archived` |
| archived_at | DATETIME | Yes | Manual archive |
| created_by / updated_by | BIGINT UNSIGNED FK users.id | No | |
| created_at / updated_at | DATETIME | No | |
| deleted_at | DATETIME | Yes | |

For methods not selected, their corresponding amount field must be null. Application validation enforces conditional fields. Contract may be edited after LHSTP exists; old LHSTP snapshots remain unchanged.

### 6.2 `contract_termins`

| Column | Type | Null | Rule |
|---|---|---:|---|
| id | BIGINT UNSIGNED PK | No | |
| contract_id | BIGINT UNSIGNED FK contracts.id | No | |
| termin_name | VARCHAR(120) | No | e.g. Termin 1 |
| percentage | DECIMAL(7,4) | No | Greater than zero |
| nominal_amount | DECIMAL(18,2) | No | Calculated from contract value |
| sort_order | INT UNSIGNED | No | |
| created_at / updated_at | DATETIME | No | |

For Termin payment method, total percentage must equal exactly 100.00%. Calculate nominal values to currency precision and assign any rounding remainder to the last termin. Prevent editing/deleting a termin already consumed by a Final LHSTP unless a safe business correction workflow is explicitly implemented.

### 6.3 `contract_attachments`

| Column | Type | Null | Rule |
|---|---|---:|---|
| id | BIGINT UNSIGNED PK | No | |
| contract_id | BIGINT UNSIGNED FK contracts.id | No | |
| original_filename | VARCHAR(255) | No | |
| storage_path | VARCHAR(500) | No | Private relative path |
| mime_type | VARCHAR(100) | No | PDF only initially |
| file_size | BIGINT UNSIGNED | No | |
| checksum_sha256 | CHAR(64) | No | |
| uploaded_by | BIGINT UNSIGNED FK users.id | No | |
| uploaded_at | DATETIME | No | |

### 6.4 `contract_activity_logs`

| Column | Type | Null | Rule |
|---|---|---:|---|
| id | BIGINT UNSIGNED PK | No | |
| contract_id | BIGINT UNSIGNED FK contracts.id | No | |
| user_id | BIGINT UNSIGNED FK users.id | No | |
| action | VARCHAR(60) | No | |
| before_values | JSON | Yes | |
| after_values | JSON | Yes | |
| created_at | DATETIME | No | |

Record changes to contract value, dates/period, vendor/representative, payment method, and other contract fields.

## 7. LHSTP

### 7.1 `lhstps`

| Column | Type | Null | Rule |
|---|---|---:|---|
| id | BIGINT UNSIGNED PK | No | |
| lhstp_number | VARCHAR(100) | No | Manually entered; unique across distinct LHSTP parents |
| contract_id | BIGINT UNSIGNED FK contracts.id | No | Exactly one contract |
| status | VARCHAR(20) | No | `Draft` / `Final` / `Archived` |
| current_version_id | BIGINT UNSIGNED | Yes | FK to lhstp_versions.id; same-parent validation |
| finalized_at | DATETIME | Yes | |
| archived_at | DATETIME | Yes | |
| created_by / updated_by | BIGINT UNSIGNED FK users.id | No | |
| created_at / updated_at | DATETIME | No | |
| deleted_at | DATETIME | Yes | |

### 7.2 `lhstp_versions`

| Column | Type | Null | Rule |
|---|---|---:|---|
| id | BIGINT UNSIGNED PK | No | |
| lhstp_id | BIGINT UNSIGNED FK lhstps.id | No | |
| version_number | INT UNSIGNED | No | Unique with parent |
| po_number_snapshot | VARCHAR(100) | No | |
| contract_date_snapshot | DATE | No | |
| vendor_name_snapshot | VARCHAR(200) | No | |
| vendor_address_snapshot | TEXT | Yes | Useful for historical document if required |
| work_name_snapshot | VARCHAR(255) | No | |
| contract_value_snapshot | DECIMAL(18,2) | No | |
| contract_start_snapshot / contract_end_snapshot | DATE | No | |
| representative_name_snapshot | VARCHAR(200) | No | |
| representative_position_snapshot | VARCHAR(200) | No | |
| payment_method_snapshot | VARCHAR(30) | No | |
| ppn_status_snapshot | VARCHAR(30) | No | |
| lhstp_date | DATE | No | |
| work_period | VARCHAR(150) | No | |
| realization_start / realization_end | DATE | No | End >= start |
| realization_days | INT UNSIGNED | No | Inclusive calendar days |
| reference_document_type | VARCHAR(40) | No | Agreed dropdown |
| reference_document_name | VARCHAR(200) | Yes | Required only for Dokumen Lainnya |
| reference_document_number | VARCHAR(150) | No | |
| reference_document_date | DATE | No | |
| work_conclusion | TEXT | No | Manually entered |
| payment_terms | TEXT | No | Manually entered |
| payment_amount | DECIMAL(18,2) | No | Greater than zero |
| ppn_rate | DECIMAL(7,4) | No | Default 12%; editable |
| dpp_lain | DECIMAL(18,2) | No | Calculation snapshot |
| ppn_amount | DECIMAL(18,2) | No | Calculation snapshot |
| total_payment | DECIMAL(18,2) | No | Final rounded amount snapshot |
| terbilang | TEXT | No | Snapshot of generated wording |
| cost_center | VARCHAR(40) | No | Default 3111440; editable |
| opname_date | DATE | No | Default LHSTP date; editable |
| signature_date | DATE | No | Default LHSTP date; editable |
| created_by | BIGINT UNSIGNED FK users.id | No | |
| finalized_by | BIGINT UNSIGNED FK users.id | Yes | |
| finalized_at | DATETIME | Yes | |
| created_at / updated_at | DATETIME | No | |

Snapshot every contract value that is printed or affects payment/document output. Finalized versions are immutable.

### 7.3 `lhstp_version_termins`

| Column | Type | Null | Rule |
|---|---|---:|---|
| id | BIGINT UNSIGNED PK | No | |
| lhstp_version_id | BIGINT UNSIGNED FK lhstp_versions.id | No | Unique: one termin per version |
| contract_termin_id | BIGINT UNSIGNED FK contract_termins.id | No | |
| termin_name_snapshot | VARCHAR(120) | No | |
| percentage_snapshot | DECIMAL(7,4) | No | |
| nominal_snapshot | DECIMAL(18,2) | No | |
| created_at | DATETIME | No | |

A Termin-based LHSTP selects exactly one termin. A Final LHSTP reserves it. A revision of the same LHSTP may retain that termin; another LHSTP cannot consume it again.

## 8. Shared System Tables

### 8.1 `generated_documents`

| Column | Type | Null | Rule |
|---|---|---:|---|
| id | BIGINT UNSIGNED PK | No | |
| entity_type | VARCHAR(40) | No | `purchase_request`, `budget`, `lhstp` |
| entity_id | BIGINT UNSIGNED | No | Parent record ID |
| version_id | BIGINT UNSIGNED | No | Exact version ID |
| document_format | VARCHAR(10) | No | `PDF` / `DOCX` |
| document_kind | VARCHAR(40) | No | e.g. final output |
| original_filename | VARCHAR(255) | No | |
| storage_path | VARCHAR(500) | No | Private path |
| mime_type | VARCHAR(100) | No | |
| file_size | BIGINT UNSIGNED | No | |
| checksum_sha256 | CHAR(64) | No | |
| generated_by | BIGINT UNSIGNED FK users.id | No | |
| generated_at | DATETIME | No | |

Because this is a polymorphic relationship, Laravel must validate that the entity and version belong together. Do not create a misleading single foreign key to multiple version tables.

### 8.2 `activity_logs`

| Column | Type | Null | Rule |
|---|---|---:|---|
| id | BIGINT UNSIGNED PK | No | |
| user_id | BIGINT UNSIGNED FK users.id | No | |
| entity_type | VARCHAR(40) | No | |
| entity_id | BIGINT UNSIGNED | No | |
| action | VARCHAR(60) | No | |
| description | VARCHAR(500) | No | |
| before_values / after_values | JSON | Yes | |
| created_at | DATETIME | No | |

### 8.3 `recycle_bin_items`

| Column | Type | Null | Rule |
|---|---|---:|---|
| id | BIGINT UNSIGNED PK | No | |
| entity_type | VARCHAR(40) | No | |
| entity_id | BIGINT UNSIGNED | No | |
| deleted_by | BIGINT UNSIGNED FK users.id | No | |
| deleted_at | DATETIME | No | |
| expires_at | DATETIME | No | deleted_at + 30 days |
| restore_metadata | JSON | Yes | Optional metadata needed for safe restore |

The entity must also be soft-deleted. A scheduled task checks expired entries daily. It must not orphan retained history or bypass the Final/Archived protection rules.

## 9. Indexes and uniqueness

Required unique indexes:
- `users.username`
- `departments.name`
- `units.unit_name`
- `purchase_requests.pr_number`
- `budgets.rab_number`
- `contracts.po_number`
- `lhstps.lhstp_number`
- `(purchase_request_id, version_number)`
- `(budget_id, version_number)`
- `(lhstp_id, version_number)`
- `number_sequences.sequence_key`

Recommended lookup indexes:
- PR/RAB/LHSTP status and created/date columns.
- Contract `vendor_id`, `end_date`, `status`, `work_name`.
- LHSTP `contract_id`, `lhstp_date`, `work_period`.
- Activity logs `(entity_type, entity_id, created_at)`.
- Recycle bin `expires_at`.

Avoid enforcing vendor company-name uniqueness as a hard database constraint in the first release; use normalized duplicate detection and a warning, because spelling variants and legal-name differences may exist.

## 10. Cross-table integrity rules

Some rules cannot be expressed as a simple foreign key or row-level CHECK. Enforce them in Laravel service classes and database transactions:

1. `current_version_id` must point to a version owned by the same parent.
2. Finalized versions are immutable.
3. A PR-based RAB source version must belong to the PR linked to the RAB.
4. RAB total equals linked PR total before RAB finalization.
5. Contract payment-method fields match the selected method.
6. Termin percentages total exactly 100% before saving/finalizing a Termin contract.
7. Termin nominal values sum exactly to contract value after rounding remainder adjustment.
8. One termin can be reserved by only one distinct Final LHSTP.
9. A revision of the same LHSTP may retain its original termin.
10. LHSTP snapshot values must be copied from contract at version creation.
11. Realization end date cannot precede start date; inclusive duration is calculated and stored.
12. PPN formulas and rounding follow the PRD.
13. A deleted entity's 30-day purge must respect dependencies and protected lifecycle states.
14. Every generated document references a valid parent and exact version.

## 11. Migration order

Create Laravel migrations in this dependency order:

1. users
2. departments, vendors, units, app_settings, number_sequences
3. purchase_requests
4. purchase_request_versions
5. purchase_request_items
6. budgets
7. budget_versions
8. budget_items
9. contracts
10. contract_termins, contract_attachments, contract_activity_logs
11. lhstps
12. lhstp_versions
13. lhstp_version_termins
14. generated_documents
15. activity_logs
16. recycle_bin_items

Circular `current_version_id` foreign keys may need to be added in a later migration after parent and version tables both exist.

## 12. Important implementation notes

- Never store formatted currency text as the authoritative value.
- Store calculated totals on finalized version records as immutable snapshots; recalculate and verify during tests.
- Use database transactions and row locks for number generation, finalization, and termin reservation.
- Do not hard-delete a parent while version history or documents still require it.
- The exact retention behavior for an expired Recycle Bin item with dependent history must be implemented conservatively: block purge and log the reason until a safe aggregate deletion rule exists.
