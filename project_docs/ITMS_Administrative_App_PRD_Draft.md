# Product Requirements Document (PRD)
## ITMS Administrative Management Web Application

**Document status:** Draft for stakeholder review  
**Version:** 0.5  
**Date:** 16 November 2026  
**Primary user:** IT & Management System (ITMS) administrative user  
**Product type:** Local web application
**Approved addendum:** 19 November 2026 — PR Tracking, Document Archive & Payment Tracking (Section 16)

---

## 1. Product Overview

### 1.1 Purpose

The application will replace the current Microsoft Access-based administrative workflow used by the IT & Management System (ITMS) function at PT Krakatau Sarana Properti.

The product will centralize the creation, management, revision, archiving, and document generation of:

- Purchase Requisitions (PR)
- Budget Plans (Rencana Anggaran Biaya / RAB)
- Contract Management records
- Work Handover Reports (Laporan Hasil Serah Terima Pekerjaan / LHSTP)

The application will also provide shared master data, a dashboard, document history, and a shared Recycle Bin.

### 1.2 Product Goals

1. Centralize ITMS administrative records in one application.
2. Reduce repeated data entry by reusing related records and master data.
3. Preserve historical document versions and generated files.
4. Generate documents that closely follow the existing company templates.
5. Make contract and LHSTP payment information easier to track.
6. Provide a simple overview of document and contract status.

### 1.3 Initial Scope and Constraints

- The first release is intended for one user.
- One account can access all modules; role-based access control is not required for the initial release.
- The application is a centralized local web application, initially hosted on one computer and designed to be accessible from additional computers over the trusted office LAN.
- The selected technical baseline is Laravel 13.x / PHP 8.5.x stable / MySQL 8.4 LTS / Apache 2.4, with Blade and Tailwind CSS. Exact patch versions and installation configuration will be confirmed during environment setup.
- Contract data is entered manually. OCR and automatic extraction from contract PDFs are out of scope.
- The initial dashboard should be simple and does not require trend charts.

### 1.4 Out of Scope for the Initial Release

- Multi-user role and permission management.
- OCR or AI-based extraction from uploaded contract documents.
- Automatic import of contract data from PDF, Word, or spreadsheet files.
- Unapproved integrations with external accounting, procurement, or HR systems.
- Technical architecture and implementation decisions, which will be documented separately.

---

## 2. Users and Access

### 2.1 Primary User

A single ITMS administrative user who creates and manages PR, RAB, contract, and LHSTP records.

### 2.2 Access Requirements

- The initial release uses one account.
- The account has access to all application modules and features.
- No role separation is required for the initial release.

---

## 3. Application Structure

The application contains the following areas:

1. Dashboard
2. Purchase Requisition (PR)
3. Rencana Anggaran Biaya (RAB)
4. Contract Management
5. LHSTP
6. Master Data
7. Recycle Bin

### 3.1 Master Data

Master data contains reusable reference information used by transaction modules.

Initial master data:

- **Master Vendor:** company name, company address, optional NPWP, optional phone number, and optional email address.
- **Master Unit:** reusable budget units. Users can add new units when preparing an RAB.
- **Master Department:** department references used by the application, including the agreed PR recipient options.

A company must have only one Master Vendor entry. The application should warn users when a duplicate company name is entered.

Vendor representative name and position are not stored in Master Vendor. They are stored per contract because the same vendor may use different representatives for different contracts.

### 3.2 Shared Recycle Bin

- PR, RAB, Contract, and LHSTP records use one shared Recycle Bin.
- Deleting a record through the application sends it to the Recycle Bin rather than immediately deleting it permanently.
- Users can restore an item from the Recycle Bin.
- Users can permanently delete an item from the Recycle Bin.
- The application must show an additional confirmation before permanently deleting an item.
- Items are automatically and permanently deleted 30 days after entering the Recycle Bin. Manual Restore and Delete Permanently actions remain available; permanent deletion requires an additional confirmation.

---

## 4. Dashboard Requirements

The dashboard is the main administrative overview.

It should display:

- Counts of PR, RAB, Contract, and LHSTP records by status.
- Contract counts by Active, Inactive, and Archived status.
- Contracts whose end dates fall within the next 30 days.
- LHSTP counts by Draft, Final, and Archived status.
- Total LHSTP payment value for a selectable period. The default period is the current calendar month; users can select a month/year or custom date range. Only Final LHSTP versions are included in the payment total. Draft and Archived records are excluded from the payment total but remain included in their respective status counts.
- Recent activity, including record creation, edits, and finalization.
- Quick actions to create a PR, RAB, Contract, or LHSTP.

The initial dashboard should remain simple and should not include trend charts.

---

## 5. Purchase Requisition (PR)

### 5.1 Purpose

The PR module records ITMS purchase requests. A PR may exist independently or may optionally be used as the source for one RAB.

### 5.2 PR Form

| Field | Requirement |
|---|---|
| From (`Dari`) | Fixed department: ITMS / IT & Management System Department. PDF label should follow the selected source template. |
| To (`Kepada`) | Manually selected dropdown: Accounting Department or Human Capital Department. The recipient is not derived automatically from the PR category. |
| Subject (`Perihal`) | Manually entered text. |
| Date (`Tanggal`) | Date picker; user may select any date. |
| Category | Optional, single selection: Investment, Services, Office Supplies, Fixed Asset, Maintenance. |
| PR Number | Automatically incrementing numeric identifier; unique and never reused after deletion. Display format: `PR-000001`, `PR-000002`, and so on. The sequence does not reset annually. |

### 5.3 Budget Items

A PR may contain multiple budget rows.

| Field | Requirement |
|---|---|
| Budget Name (`Nama Anggaran`) | Required. |
| Quantity (`Qty`) | Required. |
| Unit Price (`Harga Satuan`) | Entered and stored internally. |
| Estimated Price (`Estimasi Harga`) | Automatically calculated as Quantity × Unit Price. This is the line total. |

The PR grand total is the sum of all line estimated prices.

Validation defaults: Quantity must be greater than zero and may contain up to three decimal places. Unit Price cannot be negative; zero is permitted but should trigger a non-blocking review warning. Calculations use stored numeric precision; rounding is applied for display and document output only, according to currency formatting rules.

The PR detail page may show Unit Price. The generated PR PDF must not show Unit Price.

### 5.4 PR PDF

The generated PDF must follow the supplied PR form template.

Expected columns:

- No
- Nama Anggaran
- Qty
- Estimasi Harga
- GL Account*
- Cost Center*
- No IO**

GL Account, Cost Center, and No IO are not main PR form inputs. Until a verified data source is defined, these columns remain blank in the generated PDF; the application must never invent or infer accounting values.

### 5.5 PR and RAB Relationship

- A PR can be created without an RAB.
- An RAB can be created without a PR.
- An RAB may be created later from a saved PR.
- One RAB may be linked to only one PR.
- When an RAB is created from a PR, all PR items are imported.
- Mapping:
  - PR Budget Name → RAB Budget Name
  - PR Quantity → RAB Quantity
  - Stored PR Unit Price → RAB Price
  - PR Estimated Price → initial RAB Amount
- The stored PR Unit Price must be copied directly. It must never be recalculated by dividing Estimated Price by Quantity.
- RAB Description and Unit are completed in the RAB.
- Imported RAB items remain editable, and additional RAB items may be added.
- RAB item names may be more detailed than the original PR item names.
- If linked, the RAB total must equal the PR total before RAB finalization. A warning is displayed when totals differ.
- Changes made in RAB do not update PR. PR remains the original request record.

### 5.6 PR Lifecycle and Revisions

Statuses:

- **Draft:** editable and deletable.
- **Final:** not directly editable; changes require a new revision. The PR number remains the same.
- **Archived:** retained as an archive and not permanently deleted.

Revision history must be preserved. Final and Archived PR records must not be permanently deleted through ordinary record deletion.

The document statuses above are unchanged by the addendum. A separate tracking status dimension is defined in Section 16; it does not replace or repurpose Draft, Final, or Archived.

### 5.7 PR PDF Generation

After finalization or revision, ask whether the user wants to generate a PDF.

- If Yes, generate and save the PDF.
- If No, save the record without generating a new PDF.
- Each revision's PDF is stored separately; older PDFs are never overwritten.
- Historical PDFs remain downloadable from the PR detail page.

---

## 6. Budget Plan (RAB)

### 6.1 Purpose and Relationships

RAB is used to prepare detailed budget breakdowns for ITMS work.

- RAB may be standalone.
- RAB may be created from one saved PR.
- One RAB may link to exactly one PR; multiple PRs cannot be combined into one RAB.

### 6.2 Main RAB Fields

| Field | Requirement |
|---|---|
| RAB Number | Automatically incrementing numeric identifier; remains the same across revisions and is never reused. |
| Work Name | If linked to a PR, defaults to the PR Subject (`Perihal`); otherwise entered manually. |
| Date | If linked to a PR, defaults to the PR date but remains editable. For standalone RAB, defaults to today and remains editable. |
| Location | Fixed as Cilegon. |
| Related PR | Optional; at most one PR. |

### 6.3 RAB Budget Items

| Field | Requirement |
|---|---|
| Budget Name (`Nama Anggaran`) | Required. |
| Description (`Deskripsi`) | Optional. |
| Quantity (`Qty`) | Required. |
| Unit (`Unit`) | Required; selected from Master Unit. Users can add new units, which are saved for future use. |
| Price (`Harga`) | Unit price. |
| Amount (`Jumlah`) | Automatically calculated as Quantity × Price. |

The RAB total is the sum of all line amounts.

Validation defaults: Quantity must be greater than zero and may contain up to three decimal places. Unit Price cannot be negative; zero is permitted but should trigger a non-blocking review warning. Calculations use stored numeric precision; rounding is applied for display and document output only, according to currency formatting rules.

### 6.4 Import from PR

When creating an RAB from a PR:

- Import all PR items.
- Copy PR Budget Name, Quantity, stored Unit Price, and Estimated Price to the corresponding RAB fields.
- Copy the stored Unit Price directly; do not derive it by division.
- Description and Unit are completed in RAB.
- Imported items can be edited, and new items can be added.

### 6.5 PR/RAB Total Validation

For an RAB linked to a PR:

- The RAB total must equal the PR total.
- Display a warning if totals differ.
- Prevent RAB finalization while totals differ.
- RAB edits do not modify the PR.
- No automatic synchronization from RAB back to PR.

Standalone RAB records do not require comparison against a PR total.

### 6.6 RAB Lifecycle and Revisions

Statuses:

- **Draft:** editable and deletable.
- **Final:** cannot be edited directly; changes require a new revision.
- **Archived:** retained and not permanently deleted.

The RAB number remains the same across revisions. Previous versions remain accessible. Recommended technical model: maintain one stable RAB record with separately stored immutable version snapshots. Each version stores its complete header, budget rows, totals, and generated document reference. Creating a revision creates a new version; it never overwrites a prior version.

### 6.7 RAB PDF

- After finalization or revision, ask whether to generate a PDF.
- If Yes, generate and save the PDF.
- If No, save without a new PDF.
- Each revision's PDF is stored separately and never overwrites an earlier PDF.
- Historical PDFs remain downloadable from the RAB detail page.
- The PDF follows the supplied RAB template.

Fixed signatories:

- Approved by: **Tb. Benny Kurniawan — ITMS Manager**
- Prepared by: **Lazuardi Arsy — IT Senior Engineer**

### 6.8 RAB List

The list displays:

- RAB Number
- Work Name
- Related PR Number, if any
- RAB Date
- Total Budget
- Status

---

## 7. Contract Management

### 7.1 Purpose and Data Entry

Contract Management is the source of contract information used by LHSTP.

- Contract records are entered manually through the application.
- OCR and automatic data extraction from uploaded documents are not included.
- The PO Number is the primary contract identifier; no separate Contract Number is required.

### 7.2 Contract Identity and Vendor Relationship

- PO Number is required.
- One PO Number represents one contract and one vendor.
- A vendor may have multiple contracts, each with a different PO Number.
- PO Number must be unique. If a user enters a PO Number that already exists, the application displays a warning and rejects the duplicate; confirmation does not allow the duplicate contract to be saved.

### 7.3 Master Vendor

Each company must have one Master Vendor record.

| Field | Requirement |
|---|---|
| Company Name | Required; duplicate company names should trigger a warning. |
| Company Address | Required. |
| NPWP | Optional. |
| Phone Number | Optional. |
| Company Email | Optional. |

Vendor representative name and position are stored on each contract, not in Master Vendor.

### 7.4 Contract Fields

Each contract stores:

- PO Number
- Contract Date
- Vendor selected from Master Vendor
- Work Name
- Vendor Representative Name
- Vendor Representative Position
- Contract Start Date
- Contract End Date
- Contract Value
- PPN inclusion status: `Termasuk PPN` or `Belum termasuk PPN`
- Payment Method and related payment data
- One or more original contract PDF attachments

Contract start and end dates support duration calculation and LHSTP realization-period validation.

### 7.5 Contract Attachments

- A contract may have multiple PDF attachments, such as the main contract and addenda.
- Attachments are stored for documentation.
- Users can open or download attachments from the contract detail page.
- PDFs are not parsed automatically.

### 7.6 Payment Methods

Supported payment methods:

1. **Bulanan (Monthly):** monthly payment amount is stored on the contract.
2. **Termin (Milestone/Term):** each termin stores its name or sequence, percentage, and nominal amount.
3. **Full Financing:** full payment amount is stored on the contract.

Payment method is separate from PPN inclusion status.

### 7.6.1 Contract Validation Defaults

- Contract Date, Contract Start Date, and Contract End Date are required.
- Contract End Date must be on or after Contract Start Date. Contract Date is not required to fall on or after the start date; contracts may be signed before work begins.
- Contract Value must be greater than zero.
- For Bulanan, the monthly payment amount is required and must be greater than zero.
- For Full Financing, the full payment amount is required and must be greater than zero.
- For Termin, each termin percentage must be greater than zero and the sum of all termin percentages must equal exactly 100%.
- Termin nominal amounts are calculated from Contract Value × termin percentage. Any currency rounding remainder is assigned to the final termin so the sum of termin amounts equals Contract Value.
- Required payment data must be complete before the contract can be saved.

Dedicated contract fields for payment terms and work conclusions are not required in Contract Management. Both values are entered manually when creating an LHSTP (see Section 8).

### 7.7 Contract Status

Statuses:

- **Aktif (Active)**
- **Nonaktif (Inactive)**
- **Archived**

Rules:

- A contract automatically becomes Nonaktif after its end date has passed.
- Archived is set manually.
- Archived contracts do not automatically change to Nonaktif.
- Nonaktif contracts may still be used to create LHSTP.
- Archived contracts cannot be used to create new LHSTP.

### 7.8 Contract Updates and LHSTP Snapshots

- Contract data may be edited even after LHSTP records have been created.
- Existing LHSTP versions retain snapshots of the relevant contract data as it existed when each LHSTP version was created.
- Contract changes do not alter existing LHSTP versions.
- New LHSTPs use the latest contract data.

### 7.9 Contract and LHSTP Payment Controls

- Total LHSTP payments may exceed the contract value.
- If total LHSTP payments exceed the contract value, display a confirmation before the user proceeds.
- A termin with a Final LHSTP is considered used and cannot be selected for a new LHSTP.
- A revision of the same LHSTP may continue to use its original termin; it is not treated as a new payment or termin.

### 7.10 Contract Deletion and Activity History

- Deleting a contract sends it to the shared Recycle Bin.
- Contract activity history is required.
- History records changes to contract value, contract dates/period, vendor or representative, payment method, and other changed contract data.
- Each activity records the time and actor.

### 7.11 Contract List

The contract list displays:

- PO Number
- Work Name
- Vendor
- Contract Date
- Contract Period
- Contract End Date
- Contract Value
- Payment Method
- Status

---

## 8. LHSTP

### 8.1 Purpose and Relationship to Contracts

LHSTP is created from an existing contract. Contract information is pulled into the LHSTP rather than re-entered.

- One contract may have multiple LHSTPs.
- One LHSTP covers exactly one termin when the contract payment method is Termin.
- Multiple termini require separate LHSTP records.
- Contract details are snapshotted per LHSTP version.

### 8.2 Data Pulled from Contract

LHSTP pulls:

- PO Number
- Contract Date
- Vendor/Company Name
- Work Name
- Contract Value
- Contract Duration
- Contract Start and End Dates
- Payment Method and payment amount/termin details
- PPN inclusion status
- Vendor representative name and position

The following content is entered manually in the LHSTP form, not stored as dedicated Contract Management fields:
- Work conclusion (`Kesimpulan Pekerjaan`).
- Payment terms paragraph (`Ketentuan Pembayaran`).

Vendor representative details are maintained per contract. Existing LHSTP versions preserve the values captured when created; future LHSTPs use updated contract values.

### 8.3 LHSTP Inputs

| Field | Requirement |
|---|---|
| LHSTP Number | Manually entered; unique across distinct LHSTP records. Revisions of the same LHSTP reuse the number. |
| LHSTP Date | Manually entered, based on invoice date. |
| Work Period (`Periode Pekerjaan`) | Free text; prefilled with month and year based on realization date, but editable. |
| Realization Start Date | Manually entered and required. |
| Realization End Date | Manually entered and required. |
| Reference Document Type | Dropdown: Surat Berita Acara, Surat Purchase Order, Dokumen Lainnya. |
| Other Document Name | Required when Dokumen Lainnya is selected. |
| Reference Document Number | Manually entered and required. |
| Reference Document Date | Manually entered and required. |
| PPN Rate | Defaults to 12%; editable for each LHSTP. |
| Cost Center | Defaults to 3111440; editable for each LHSTP. |
| Opname Date | Defaults to LHSTP Date; manually editable. |
| Signature Date | Defaults to LHSTP Date; manually editable. |

The generated reference sentence adapts to the selected reference document type.

### 8.4 Payment Amount

- Bulanan: monthly amount is stored on the contract and prefilled in LHSTP.
- Termin: user selects one contract termin; exactly one termin per LHSTP.
- Full Financing: full payment amount is stored on the contract and prefilled in LHSTP.
- The prefilled amount may be adjusted in LHSTP if invoice conditions require.
- Payment amount is required and must be greater than zero.

### 8.5 PPN Calculation

Let `N` be the entered payment amount and `r` be the PPN rate expressed as a decimal (default `0.12`).

**When the contract is Belum termasuk PPN:**

- DPP LAIN = `N × 11/12`
- PPN = `DPP LAIN × r`
- Total Payment = `N + PPN`

Example: `N = Rp12,000,000`, `r = 12%`:
- DPP LAIN = Rp11,000,000
- PPN = Rp1,320,000
- Total Payment = Rp13,320,000

**When the contract is Termasuk PPN:**

- DPP LAIN = `(N ÷ (1 + (11/12 × r))) × 11/12`
- PPN = `DPP LAIN × r`
- Total Payment = `N`

Example: `N = Rp13,320,000`, `r = 12%`:
- DPP LAIN = Rp11,000,000
- PPN = Rp1,320,000
- Total Payment = Rp13,320,000

Additional rules:

- `Terbilang` is generated automatically from Total Payment.
- The applied PPN rate is saved per LHSTP version.
- The PPN wording in the generated document reflects the actual applied rate.
- Only the final Total Payment is rounded to the nearest whole rupiah and displayed with two decimal places (`.00` in English formatting; Indonesian document formatting should retain `,00`).
- Intermediate DPP LAIN and PPN values are not rounded before calculating the total.

### 8.6 Realization Duration and Date Validation

- Realization duration is calculated in calendar days, including both start and end dates.
- Example: 1–5 November equals 5 calendar days.
- Realization dates outside the contract period trigger a warning but do not block LHSTP creation.
- Realization End Date cannot be earlier than Realization Start Date.
- Both realization dates are required.

### 8.7 LHSTP Status and Revisions

Statuses:

- **Draft:** editable and deletable.
- **Final:** cannot be edited directly.
- **Archived:** retained and not permanently deleted.

Rules:

- Corrections to a Final LHSTP require a new revision.
- The LHSTP number remains the same across revisions.
- Previous versions remain accessible.
- Each version stores a snapshot of relevant LHSTP and contract data, including payment amount, applied PPN rate, Cost Center, vendor representative, dates, and other relevant fields.
- Each revision's generated documents are kept separately and do not overwrite earlier versions.
- Deleted Draft LHSTPs use the shared Recycle Bin.

### 8.8 Finalization and Document Generation

After finalization, ask whether the user wants to generate a document.

- If Yes, generate and save the selected output.
- If No, save the LHSTP as Final without generating a new document.
- If document generation was declined, the user can generate it later from the LHSTP detail page without creating a new revision.

Supported output formats:

- PDF
- Microsoft Word (`.docx`)

Both formats use the same LHSTP version data and snapshot. Both should follow the supplied two-page LHSTP template as closely as possible. PDF layout must preserve the original structure, logos, typography/formatting, paragraphs, payment section, and signature placement. DOCX should preserve the same structure and layout as closely as practical.

### 8.9 LHSTP Template Content and Signatories

The template includes:

- Title: LAPORAN HASIL SERAH TERIMA PEKERJAAN
- Recipient: Manager Accounting, PT Krakatau Sarana Properti
- Work Name, PO Number, Contract Date, Vendor
- Reference document sentence
- Opname date
- Contract duration and contract start/end dates
- Realization duration and realization start/end dates
- Work conclusion and payment terms
- Contract Value, Payment Value, DPP LAIN, PPN, Total Payment, Terbilang
- Cost Center
- Signature section

Fixed first-party signatory:

- Tb. Benny Kurniawan
- IT & Management System Manager
- PT Krakatau Sarana Properti

Second-party company comes from Master Vendor/contract data. Representative name and position come from the contract and are snapshotted per LHSTP version.

### 8.10 LHSTP Validation and Warnings

- LHSTP number must be unique across distinct LHSTP records; revisions of the same record may reuse it.
- Payment amount must be greater than zero.
- A selected termin must belong to the contract and must not already have a Final LHSTP, except when revising that same LHSTP.
- Reference document number and date are required.
- If a reference document number is already used by another LHSTP, show a warning but allow the user to continue.
- If another LHSTP under the same contract has an overlapping work period, show a warning but allow the user to continue.
- Check overlapping work periods both when saving a Draft and when finalizing.
- If realization dates fall outside the contract period, show a warning but allow the user to continue.

### 8.11 LHSTP List, Search, and Filters

List columns:

- LHSTP Number
- Work Name
- PO Number
- Vendor
- Work Period
- LHSTP Date
- Payment Value
- Status

Search by:

- LHSTP Number
- Work Name
- PO Number
- Vendor

Filters:

- Status
- Date period

---

## 9. Cross-Module Business Rules

1. Contract is the source record for LHSTP contract details.
2. Contract updates do not modify existing LHSTP snapshots.
3. PR and RAB are optionally related; either may exist independently.
4. One RAB may be linked to at most one PR.
5. A linked RAB must equal its PR total before finalization.
6. RAB edits do not update PR.
7. One contract may have multiple LHSTPs.
8. One termin is billed by one LHSTP; a revision of the same LHSTP is not a second billing.
9. Contract payment totals exceeding contract value require confirmation, not automatic blocking.
10. Archived contracts cannot be used for new LHSTP creation; Nonaktif contracts may still be used.

---

## 10. Document and File Management

- PR and RAB PDFs are generated per revision, stored separately, and never overwritten.
- LHSTP supports PDF and DOCX generation per version.
- Contract records may store multiple original PDF attachments.
- Historical versions and generated documents remain accessible from the relevant record detail page, subject to the final retention policy.
- Shared Recycle Bin is used for deletable records across all modules.

---

## 11. Technical Architecture Reference

The technical baseline is documented separately in `ITMS_Administrative_App_Technical_Design.md`.

Selected baseline:
- Laravel 13.x with PHP 8.5.x stable.
- MySQL 8.4 LTS.
- Apache HTTP Server 2.4.
- Laravel Blade and Tailwind CSS.
- Centralized local web application, initially hosted on one computer and LAN-ready for future access from additional computers.
- New database schema: `itms_admin_db`. The existing database will not be reused as the application schema; it may be retained as a reference or migration source.

The Technical Design Document describes the proposed logical data model, revision/snapshot strategy, private file storage, security, backup, testing, and implementation phases. Exact patch versions, Windows configuration, and final document-rendering libraries will be verified during environment setup and template proof-of-concept testing.

## 12. Recommended Defaults for Stakeholder Review

The following defaults are recommended by the product analyst based on common administrative application practices and the business rules already agreed. They are provisional until accepted or amended by the user.

### 11.1 PR and RAB Number Formats

- PR display format: `PR-000001`, incrementing globally without annual reset.
- RAB display format: `RAB-000001`, incrementing globally without annual reset.
- Numbers are unique, never reused, and remain unchanged across revisions.

### 11.2 PR PDF Accounting Columns

GL Account, Cost Center, and No IO remain in the template but are left blank until a verified source and mapping are defined. The application must not invent or infer accounting values.

### 11.3 Dashboard LHSTP Payment Summary

- Default period: current calendar month.
- Period filters: selectable month/year and custom date range.
- Payment totals include Final LHSTP versions only. Draft and Archived records are excluded from payment totals but included in status counts.

### 11.4 Contract Validation

- Contract Date, Start Date, and End Date are required.
- End Date must be on or after Start Date. Contract Date may precede Start Date.
- Contract Value must be greater than zero.
- Monthly and Full Financing amounts must be greater than zero when their respective payment methods are selected.
- Each Termin percentage must be greater than zero; all Termin percentages must total exactly 100%.
- Termin nominal values are calculated from Contract Value × percentage. Any currency rounding remainder is assigned to the final Termin so the sum matches Contract Value.

### 11.5 PR and RAB Quantity/Price Validation

- Quantity must be greater than zero and may have up to three decimal places.
- Unit Price cannot be negative. Zero is allowed but triggers a non-blocking review warning.
- Calculations retain numeric precision; rounding is applied for display and document output only.

### 11.6 RAB Revision Storage

Use one stable RAB record and immutable version snapshots. Each version stores its complete header, budget rows, totals, and generated document reference. New revisions create new versions and never overwrite previous versions.

### 11.7 Recycle Bin Retention

Items are automatically and permanently deleted 30 days after entering the Recycle Bin. Manual Restore and Delete Permanently actions remain available; permanent deletion requires an additional confirmation.

### 11.8 Document Template Fidelity

The supplied files are the primary visual references. Preserve logos, typography, table structure, paragraphs, signature placement, and page count as closely as practical. Validate generated output against the references during development. Exact pixel-level identity is not guaranteed because PDF and DOCX rendering can vary by fonts and rendering engines.

## 13. Source Documents

The requirements were compiled from the user's decisions and the following supplied references:

- `Form PR.pdf` and `cth_pr.pdf` — PR form and filled example.
- `Report_RAB.pdf` and `cth_RAB.pdf` — RAB report template and filled example.
- `LHSTP_template.pdf` — LHSTP two-page template.
- `PR_Requirements.md`
- `RAB_Requirements.md`
- `LHSTP_Requirements.md`
- `Contract_Requirements.md`
- `App_General_Requirements.md`

Where the source requirement notes contain inconsistencies, these are explicitly listed in Section 11 rather than silently resolved.

---

## 14. Confirmed Decisions from Consolidation Review

- Work Conclusion and Payment Terms are entered manually in the LHSTP form; they are not dedicated fields in Contract Management.
- RAB changes do not synchronize back to PR, either automatically or through a confirmation-based update.
- Duplicate PO Numbers are rejected; a confirmation cannot override the uniqueness rule.

## 15. Technical Design Package

The following companion documents complete the initial design package:

1. `ITMS_Administrative_App_Technical_Design.md` — selected technology, deployment architecture, application layers, security, backup, and test strategy.
2. `ITMS_Administrative_App_ERD.md` — logical entities and relationships.
3. `ITMS_Administrative_App_Database_Schema.md` — proposed physical tables, columns, data types, constraints, indexes, and migration order.
4. `ITMS_Administrative_App_Implementation_Blueprint.md` — navigation, screen specifications, lifecycle rules, validations, calculation services, document generation, scheduled tasks, and acceptance criteria.
5. `ITMS_Administrative_App_UI_UX_Specification.md` — visual system, navigation layout, reusable components, form layouts, dashboard structure, and usability standards.

These documents are design baselines. Implementation-specific details requiring environment execution or template rendering tests remain subject to verification during build setup.

## 15. Approval Status

This document is a consolidated draft for stakeholder review. Section 11 contains recommended defaults proposed by the product analyst; the user may accept or amend them. The initial technical baseline has now been documented in the separate Technical Design Document. The PRD and technical design remain reviewable baselines and may be amended when stakeholder feedback or implementation tests identify a necessary change.

---

## 16. Approved Addendum — PR Tracking, Document Archive & Payment Tracking

**Status:** Approved by the user, 19 November 2026.
**Source:** Requirements Addendum "PR Tracking, Document Archive & Payment Tracking".
**Effect:** This section adds requirements. Earlier sections remain in force unless explicitly changed here.

### 16.1 Overall workflow

1. PR Submission Form
2. PR Tracking
3. Multiple Contract/PO and Vendor records
4. LHSTP
5. Payment Tracking

A PR may be associated with multiple Contracts/POs. The existing Contract record represents the PO. A separate Purchase Orders entity is not introduced.

### 16.2 Two independent PR status dimensions

**Document Status** — unchanged from Section 5.6: Draft, Final, Archived. These statuses must not be replaced or repurposed.

**Tracking Status** — a new, separate dimension with these values:

- `Not Tracked`
- `In Progress`
- `Done`

Rules:

- Tracking status must not change document status.
- Document archival must not automatically mark a PR as Done.
- A PR becomes Done when goods have been received or services have been completed.
- All 99 existing PR records (96 Archived, 3 Final) have no stage history and therefore derive `Not Tracked`. Do not infer historical progress or fabricate stage history.
- Payment completion must not modify tracking status.
- Tracking Status is derived from the append-only `pr_stage_updates` history; no `tracking_status` column exists in `purchase_requests`: no stage records → `Not Tracked`; Stage 1–4 → `In Progress`; Stage 5 → `Done`. Stage history is authoritative.

### 16.3 PR Tracking workflow

Five manually updated stages, in order:

1. Submission to HC/Accounting
2. ITMS Approval
3. HC/Accounting Verification
4. Procurement Submission
5. Goods/Services Received

Rules:

- Stages progress sequentially.
- Tracking begins only when a user manually confirms Stage 1. A PR becoming Final does not start tracking.
- Every update and correction is appended to an immutable history. Previous updates are never removed or overwritten.
- Each history entry records timestamp, actor, and an optional note.
- A Stage 5 record yields tracking status `Done` under the derived mapping.
- Payment status has no effect on tracking status.

### 16.4 PR to Contract/PO relationship

- Add a nullable `purchase_request_id` relationship to the existing `contracts` entity.
- One PR can have multiple Contracts/POs.
- One Contract/PO can belong to at most one PR.
- A Contract/PO may remain unlinked to a PR.
- Existing PO Number uniqueness is unchanged.
- Each Contract/PO retains its existing single-vendor relationship.
- The relationship must be visible and manageable from the PR and Contract interfaces.
- Permanent deletion of a PR is restricted while linked Contracts/POs exist. A Contract may be unlinked only if it has no LHSTP records and no Payment Records; otherwise unlinking is blocked. Every successful unlink is recorded in Activity History. A PR may be permanently deleted only after all linked Contracts have been safely unlinked. Unlinking and PR deletion must never cascade-delete Contracts, LHSTPs, payment records or historical records.

### 16.5 PR Document Archive

Create a dedicated `pr_documents` entity. It is distinct from system-generated documents in `generated_documents`.

Fixed category list:

1. PR Submission Form
2. Supporting Documents
3. Official PR Document
4. Procurement Documents
5. Other Documents

Each document retains: associated PR, category, original filename, storage path, MIME type, file size, checksum, uploaded by, upload timestamp, and optional notes.

File handling:

- Private storage outside the public web root.
- Authorized download for supported formats.
- Preview for PDF, JPG, and PNG. Other supported formats may be downloaded only.
- Download and preview must enforce record-level authorization. Arbitrary private storage paths must not be exposed.
- Deletion integrates with the Shared Recycle Bin.
- Permanent purge removes both the database record and the physical file.
- User-uploaded documents remain distinct from system-generated documents.

Integrity: uploading or deleting an archived document must not modify or overwrite a finalized PR version or a generated historical document.

### 16.6 Payment Tracking

- Payment tracking is independent of LHSTP document status. A Final LHSTP does not prove that the vendor was actually paid.
- Payment records are stored in `payment_records`, each associated with the relevant Contract/PO and payment term, not only with the parent PR. Supporting files are stored in `payment_evidences`.
- Payment statuses: `Unpaid`, `Partially Paid`, `Paid`.
- Payment records support payment date, payment amount, payment reference, and supporting payment evidence.
- A contract may have multiple payment records. Each payment record may have multiple evidence attachments.
- Payment obligations are the accumulated total of Final LHSTP amounts for the relevant contract. Draft and Archived LHSTPs do not contribute.
- Payment status is calculated from confirmed payment records. Overpayments exceeding the outstanding obligation are rejected.
- Status values are `Unpaid`, `Partially Paid`, and `Paid`. Individual LHSTP payment progress should be visible where applicable; contract-level status summarizes the overall position.
- New payment records start as `Draft`. Draft payments do not contribute to paid totals or payment status. Only `Confirmed` payments count as actual payments.
- Confirmed payment records cannot be directly edited or deleted. Corrections are recorded as new correction entries linked to the original record; originals and their history remain preserved.
- Evidence formats are limited to PDF, JPG, JPEG and PNG, with a maximum of 10 MB per file. Evidence deletion uses the Shared Recycle Bin, is restorable, and permanent purge removes both database records and physical files.
- Revisions or archiving of a Final LHSTP are blocked if the change would cause Confirmed payments to exceed the revised payment obligation. Resolve the discrepancy through a recorded payment correction first. Original LHSTP records and revision history remain preserved.
- A PR-level payment summary, if displayed, is derived from linked contracts and their payment records. It is not a separately maintained source of truth.
- Payment tracking must not automatically modify PR tracking status.

### 16.7 Existing module preservation

The addendum preserves: PR document lifecycle and version history, PR numbering, RAB relationships and behavior, Contract PO uniqueness, Contract-to-Vendor relationships, Contract payment-term behavior, LHSTP snapshots/version history/calculations/document generation, Shared Recycle Bin behavior, and existing dashboard calculations unless explicitly approved for extension.

### 16.8 Known existing technical concern

`ContractService::update()` deletes and recreates contract payment terms. This may fail when a term is already referenced by a Final LHSTP. It must be investigated and fixed in a separately scoped, approved task. Unrelated Contract Management behavior must not be silently refactored.

### 16.9 Open decisions

Decisions 6–9 resolved the first set of open items; Decisions 10–14 resolved tracking storage, payment confirmation/correction, evidence formats and limits, contract unlinking, and LHSTP obligation-change conflicts. Only one detail remains unresolved and must not be assumed. See `DECISION_LOG.md` for the full list.

1. Additional payment edge cases not explicitly approved.
