# Implementation Blueprint
## ITMS Administrative Management Web Application

**Status:** Proposed build specification  
**Version:** 0.1  
**Related documents:** PRD v0.2, Technical Design v0.2, ERD v0.1, Database Schema Specification v0.1

This document completes the application-level design needed before implementation. It defines navigation, screens, workflows, state transitions, calculation services, file handling, testing gates, and deployment behavior. It does not contain executable application code.

## 1. Application Structure

### 1.1 Main navigation

1. Dashboard
2. Purchase Requisition
3. RAB
4. Contract Management
5. LHSTP
6. Master Data
   - Vendors
   - Units
   - Departments
7. Recycle Bin
8. Activity History
9. Settings

### 1.2 Global interface elements

- Left sidebar navigation on desktop; collapsible on smaller screens.
- Top bar with page title, logged-in account, and logout.
- Consistent page header with primary action.
- Search, filters, pagination, and status indicators on list pages.
- Indonesian user-facing labels and date/currency formatting.
- English code, database names, and technical documentation.
- Confirmation dialogs for destructive or irreversible actions.
- Clear validation messages beside the relevant fields.

## 2. Screen Specifications

### 2.1 Dashboard

Cards:
- PR counts by Draft, Final, Archived.
- RAB counts by Draft, Final, Archived.
- Contract counts by Active, Inactive, Archived.
- Contracts ending within the next 30 days.
- LHSTP counts by Draft, Final, Archived.

Payment summary:
- Default period is current calendar month.
- User can select month/year or custom date range.
- Sum Final LHSTP total payments only; exclude Draft and Archived.
- Use the LHSTP date as the date basis unless a later business decision specifies another date.
- Recent activity list.
- Quick-create shortcuts for PR, RAB, Contract, and LHSTP.

### 2.2 PR list

Columns:
- PR Number
- Subject
- Recipient Department
- PR Date
- Category
- Total
- Status
- Latest Version

Search:
- PR Number and Subject.

Filters:
- Status, category, recipient department, date range.

Actions:
- View details.
- Create new PR.
- Edit Draft.
- Create revision from Final.
- Archive.
- Delete eligible Draft into shared Recycle Bin.
- Download version-specific PDF.

PR detail:
- Header data and item table.
- Unit price may be visible in the application detail.
- Version history and document history.
- Do not display unit price in generated PR PDF.

### 2.3 RAB list

Columns:
- RAB Number
- Work Name
- Linked PR Number (if any)
- RAB Date
- Total Budget
- Status
- Latest Version

Search:
- RAB Number, Work Name, Linked PR Number.

Filters:
- Status, linked/standalone, date range.

Actions:
- Create standalone RAB.
- Create from PR.
- Edit Draft.
- Create revision from Final.
- Archive.
- Delete eligible Draft.
- Download version-specific PDF.

RAB detail:
- Items include Name, Description, Qty, Unit, Price, Amount.
- Show linked PR and total comparison when applicable.
- Block Finalization if linked PR total and RAB total differ.

### 2.4 Contract list

Columns:
- PO Number
- Work Name
- Vendor
- Contract Date
- Contract Period
- Contract End Date
- Contract Value
- Payment Method
- Status

Search:
- PO Number, Work Name, Vendor.

Filters:
- Status, vendor, payment method, contract end-date range.

Contract detail:
- Contract information.
- Vendor and representative details.
- Payment method details and termin schedule.
- Attachments with open/download actions.
- Contract activity history.
- Related LHSTP list.

Actions:
- Create contract.
- Edit contract.
- Archive/unarchive according to allowed lifecycle.
- Delete to shared Recycle Bin where eligible.
- Upload/remove attachment subject to history and retention rules.

### 2.5 LHSTP list

Columns:
- LHSTP Number
- Work Name
- PO Number
- Vendor
- Work Period
- LHSTP Date
- Payment Amount
- Status

Search:
- LHSTP Number, Work Name, PO Number, Vendor.

Filters:
- Status and date range.

LHSTP detail:
- Header and contract snapshot.
- Work period and realization dates.
- Reference document.
- Payment calculation breakdown.
- Work conclusion and payment terms.
- Version history.
- PDF/DOCX history with generate/download actions.

### 2.6 Master Data

Vendor fields:
- Company Name (required)
- Company Address (required)
- NPWP (optional)
- Phone (optional)
- Email (optional)

Duplicate company name:
- Warn user and allow review; do not silently merge or block solely on approximate name match.

Unit:
- Unit Name (required, unique after normalized comparison).

Department:
- Department Name (required, unique).

### 2.7 Recycle Bin

- One shared list for deleted PR, RAB, Contract, and LHSTP records.
- Show module, document number, description, deleted date, expiry date, and deleted by.
- Restore action.
- Delete Permanently action with additional confirmation.
- Expired records are processed by a scheduled daily task after 30 days.
- If safe deletion is blocked by protected history/dependencies, keep the item and show/log the reason; do not silently orphan dependent records.

### 2.8 Activity History

- Newest first.
- Filter by module, action, and date range.
- Record actor, timestamp, entity, action, and concise summary.
- Contract-specific history includes before/after values for agreed important fields.
- Do not expose technical stack traces in user activity history.

## 3. Lifecycle and State Transitions

### 3.1 PR, RAB, LHSTP

Draft:
- Editable.
- Eligible for deletion to Recycle Bin.
- Can be finalized after validation.

Final:
- Not directly editable.
- Revision action creates a new Draft version based on the latest Final version.
- Finalization of the new version makes it the current Final version.
- Old Final versions remain accessible and immutable.
- Document generation is optional after finalization; if declined, it can be triggered later without a new revision.

Archived:
- Retained and read-only.
- Not permanently deleted through normal UI.
- LHSTP Archived and RAB/PR Archived are excluded from payment totals where relevant.

### 3.2 Contract

- Active while contract is within its effective period.
- Automatically becomes Inactive when end date passes.
- Archived is manually set and must not be overwritten by automatic expiry logic.
- Inactive contracts may still be selected for new LHSTP.
- Archived contracts cannot be selected for new LHSTP.
- Contract edits are allowed even when related LHSTPs exist.
- Existing LHSTP versions retain snapshots; future LHSTPs use current contract data.

### 3.3 Termin state

- A termin is available until consumed by a Final LHSTP.
- Finalization reserves the termin.
- A revision of the same LHSTP may retain that termin.
- A different LHSTP cannot consume an already-used termin.
- Draft LHSTP does not permanently consume the termin.

## 4. Business Services

Business rules should be implemented in dedicated Laravel service/action classes, not in Blade templates.

Proposed services:
- `DocumentNumberService`
- `PurchaseRequestService`
- `BudgetService`
- `PrToRabImportService`
- `ContractService`
- `ContractStatusService`
- `TerminAllocationService`
- `LhstpService`
- `LhstpPaymentCalculator`
- `DocumentGenerationService`
- `RecycleBinService`
- `ActivityLogService`
- `DashboardQueryService`

## 5. Calculation Specifications

### 5.1 PR and RAB line totals

`line_total = quantity × unit_price`

Grand total is the sum of line totals. Use decimal-safe arithmetic. Do not use binary floating point. Display currency using Indonesian Rupiah formatting.

### 5.2 Contract termin amounts

For each termin:
`raw_amount = contract_value × percentage / 100`

Round each nominal amount to two decimal places. Assign any remaining currency rounding difference to the final termin so the sum of all termin amounts equals the contract value exactly.

Total percentage must equal exactly 100.0000%.

### 5.3 LHSTP realization days

Inclusive calendar days:
`realization_days = DATEDIFF(realization_end, realization_start) + 1`

End date must not precede start date. If dates fall outside contract period, show a warning but allow the user to continue.

### 5.4 LHSTP PPN

Let:
- `N` = entered payment amount
- `r` = selected PPN rate as a decimal; default 0.12
- `D` = DPP LAIN
- `P` = PPN

When contract PPN status is **Belum termasuk PPN**:
- `D = N × 11/12`
- `P = D × r`
- `Total = N + P`

When contract PPN status is **Termasuk PPN**:
- `D = (N ÷ (1 + (11/12 × r))) × 11/12`
- `P = D × r`
- `Total = N`

Only the final Total Payment is rounded to the nearest whole rupiah and displayed with `,00`. Intermediate DPP and PPN calculations are not rounded before calculating the total. Store calculation snapshots at the precision required by the approved formula; verify output against the PRD examples in automated tests.

Terbilang is generated from the final rounded Total Payment and stored on the version.

### 5.5 Dashboard payment summary

- Sum `total_payment` from Final LHSTP versions.
- Filter by `lhstp_date` in selected period.
- Exclude Draft and Archived.
- Counts by status remain separate from payment sum.

## 6. Validation and Warning Matrix

| Area | Validation / warning | Behavior |
|---|---|---|
| PR/RAB number | Generated sequence uniqueness | Blocking system rule |
| PR recipient | Required | Block save |
| PR category | Optional, single value | Allow blank |
| PR/RAB quantity | Greater than zero, max 3 decimals | Block invalid value |
| PR/RAB unit price | Cannot be negative | Block negative; zero shows warning |
| Linked RAB total | Must equal PR total to finalize | Warning while editing; block finalization |
| Contract PO number | Duplicate PO | Warn and block save; confirmation cannot override |
| Contract date | Required | Block save |
| Contract end date | Must be on/after start | Block save |
| Contract value | Greater than zero | Block save |
| Payment method amount | Required for selected method | Block save |
| Termin percentage | Positive; sum exactly 100% | Block finalization/save of complete schedule |
| LHSTP payment amount | Greater than zero | Block save |
| LHSTP reference number/date | Required | Block save |
| LHSTP duplicate reference number | Duplicate in another LHSTP | Warn; allow continue |
| LHSTP work period overlap | Overlap within same contract | Warn; allow continue |
| Realization outside contract period | Date warning | Warn; allow continue |
| Termin already used | Used by another Final LHSTP | Block finalization/selection |
| PPN rate | Default 12%, editable; nonnegative and bounded by configured validation | Block invalid rate |
| Recycle Bin purge | Protected dependencies | Block purge; log reason |

## 7. Document Generation Pipeline

1. Load the exact finalized version and its snapshots.
2. Build a normalized document data object from stored values.
3. Render into the approved PR/RAB/LHSTP template.
4. Validate output MIME type and file size.
5. Save to private storage using a unique version-specific path.
6. Calculate SHA-256 checksum.
7. Insert `generated_documents` metadata row.
8. Return a secure download route.
9. On failure, preserve finalized business data and show a retry action.

Do not generate a new revision merely to regenerate a missing document.

### 7.1 Template proof-of-concept gate

Before full module development, render representative PR, RAB, and LHSTP examples and compare them with supplied source templates. LHSTP must remain two pages and include the correct signature placement. If Dompdf cannot satisfy the fidelity requirement, select another rendering approach before proceeding.

## 8. File Naming

Suggested generated filenames:
- `PR-000001_V01.pdf`
- `RAB-000001_V01.pdf`
- `LHSTP-<manual-number>_V01.pdf`
- `LHSTP-<manual-number>_V01.docx`

Original uploaded contract files retain their original display filename but use generated unique physical storage names.

## 9. Scheduled Tasks

Daily:
- Process expired Recycle Bin entries, subject to dependency/protection checks.
- Update or reconcile contract status to Inactive when end date has passed, without overwriting Archived.
- Run database and file backup jobs (exact execution time configured for the server).

On demand:
- Generate documents.
- Restore backup.
- Rebuild dashboard cache if caching is later introduced.

For the first release, avoid complex queues unless document generation or backup duration proves it necessary. Use Laravel Scheduler and Windows Task Scheduler on the Windows server.

## 10. Error Handling

- Validation errors are shown next to the relevant fields.
- Business-rule warnings explain why the action is risky and whether the user can continue.
- Blocking errors prevent the action and preserve existing data.
- Document-generation failures do not undo finalization.
- Database errors are logged privately and surfaced as a generic helpful message.
- All multi-table workflows use transactions and rollback on failure.

## 11. Acceptance Criteria

The design is ready for implementation when:
- All required modules and screens are represented.
- Schema and ERD agree on parent/version relationships.
- Number generation is concurrency-safe and never reuses numbers.
- PR-to-RAB import mapping is exact and no reverse unit-price calculation occurs.
- RAB linked total equality is enforced at finalization.
- Contract payment method and termin validations are covered.
- LHSTP calculations match the approved examples.
- LHSTP contract snapshots survive later contract edits.
- One Final LHSTP termin cannot be used by another LHSTP.
- PDF/DOCX generation is version-specific and retryable.
- Recycle Bin restore/purge protects retained history.
- Backup and restore are tested.
- LAN access is tested before multi-computer rollout.

## 12. Build Sequence

1. Confirm schema/ERD consistency.
2. Bootstrap Laravel project and local environment.
3. Implement migrations, seeders, authentication, and shared services.
4. Implement application shell and master data.
5. Implement PR and test PDF.
6. Implement RAB and PR import.
7. Implement Contract Management and attachments.
8. Implement LHSTP and payment calculations.
9. Implement PDF/DOCX template pipeline.
10. Implement dashboard, activity history, and Recycle Bin polish.
11. Run regression, backup/restore, and LAN acceptance tests.

## 13. Design Completion Boundary

At this point, the product requirements, technical baseline, logical ERD, physical schema specification, application workflows, screen structure, validation matrix, calculation rules, document pipeline, and implementation sequence have been designed.

Implementation-specific items that cannot responsibly be finalized without running the environment or testing templates are intentionally deferred:
- exact patch versions and PHP extensions actually available on the target machine;
- final document rendering engine after template proof of concept;
- exact LAN IP, firewall rules, backup path and Windows Task Scheduler configuration;
- visual UI styling details after the first clickable application shell is built.

These are environment verification and implementation tasks, not unresolved product requirements.
