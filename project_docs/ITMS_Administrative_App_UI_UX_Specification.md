# UI/UX Design Specification
## ITMS Administrative Management Web Application

**Status:** Proposed visual and interaction baseline  
**Version:** 0.2  
**Language:** Indonesian user interface  
**Related:** PRD v0.5, Technical Design v0.4, Implementation Blueprint v0.2
**Addendum:** 19 November 2026 — PR Tracking, Document Archive & Payment Tracking (Section 10)

## 1. Design Direction

Create a clean, restrained, professional internal administration application. The interface should feel familiar to office users and prioritize clarity, legibility, predictable forms, and fast data entry over decorative effects.

### Design principles

1. Clear information hierarchy.
2. Consistent forms and tables across all modules.
3. Important statuses are visible but not visually overwhelming.
4. Destructive actions require clear confirmation.
5. Long data-entry forms are divided into meaningful sections.
6. Currency, dates, and document numbers use consistent Indonesian formatting.
7. Desktop-first for office workflows, responsive enough for laptop/tablet use.

## 2. Visual System

### 2.1 Color tokens

Use a restrained neutral palette with one primary blue accent. Use semantic colors only for status and feedback.

| Token | Suggested value | Use |
|---|---|---|
| Primary | `#2563EB` | Primary actions, active navigation, links |
| Primary hover | `#1D4ED8` | Hover state |
| App background | `#F8FAFC` | Main page background |
| Surface | `#FFFFFF` | Cards, forms, tables |
| Border | `#E2E8F0` | Dividers and outlines |
| Text primary | `#0F172A` | Main text |
| Text secondary | `#64748B` | Help text and metadata |
| Success | `#15803D` | Successful actions / Final state |
| Warning | `#B45309` | Warnings / approaching expiry |
| Danger | `#B91C1C` | Blocking errors / permanent deletion |
| Neutral | `#64748B` | Draft / inactive secondary metadata |

Status colors should always be accompanied by text labels; never communicate status by color alone.

### 2.2 Typography

- Use a readable sans-serif system font stack.
- Page title: 24–28 px, semibold.
- Section heading: 16–18 px, semibold.
- Body: 14–16 px.
- Table: 13–14 px, with comfortable line height.
- Form labels: 13–14 px, medium.
- Numeric values use tabular numerals where supported.

### 2.3 Spacing and surfaces

- 8 px base spacing rhythm.
- Card radius: 10–12 px.
- Input height: approximately 40 px.
- Button height: approximately 40 px.
- Use subtle borders rather than heavy shadows.
- Avoid excessive gradients, animation, and decorative charts.

## 3. Global Layout

### Desktop

- Fixed/collapsible left sidebar approximately 240–260 px.
- Top bar with page title/breadcrumb, account menu, and logout.
- Main content with a comfortable maximum width and consistent page padding.
- On wide screens, use multi-column dashboard cards and form sections.
- List tables occupy the main content width.

### Navigation order

1. Dashboard
2. Purchase Requisition
3. RAB
4. Contract Management
5. LHSTP
6. Master Data
7. Recycle Bin
8. Activity History
9. Settings

Use simple line icons consistently. Active module is highlighted with a pale primary background and primary text.

### Responsive behavior

- Sidebar collapses to a drawer on narrow screens.
- Tables may scroll horizontally rather than compressing important values.
- Form sections stack vertically.
- Primary actions remain visible near the page heading or sticky form footer where appropriate.

## 4. Reusable Components

### 4.1 Page header

Contains:
- Page title.
- Optional breadcrumb.
- Short description where useful.
- One primary action (e.g. `Buat PR`).

### 4.2 Status badge

- Draft: neutral gray.
- Final: green.
- Archived: slate/gray.
- Contract Active: green.
- Contract Inactive: amber or neutral.
- Contract Archived: gray.
- Tracking `Not Tracked`: neutral gray.
- Tracking `In Progress`: amber.
- Tracking `Done`: green.
- Payment `Unpaid`: neutral; `Partially Paid`: amber; `Paid`: green.

Always show the status text. Tracking status badges must remain visually distinct from document status badges.

### 4.3 Data table

- Clear column headings.
- Right-align currency and numeric columns.
- Left-align names and descriptions.
- Keep action menu in a consistent final column.
- Support search, filters, pagination, and empty states.
- Show loading and error states.
- Default sort: newest/most recently updated first, unless a module-specific requirement says otherwise.

### 4.4 Form controls

- Labels above controls.
- Required indicator for mandatory fields.
- Inline validation message below the field.
- Help text for non-obvious rules.
- Numeric input supports Indonesian display formatting while storing normalized decimal values.
- Date picker displays a clear local date format.
- Dropdowns support search when the option list grows.

### 4.5 Confirmation dialog

Use for:
- Finalization.
- Archive/unarchive.
- Restore.
- Permanent deletion.
- Actions that replace or create a new version.

Dialog must clearly state the consequence. Permanent deletion uses a danger action and explicit confirmation.

### 4.6 Notification feedback

- Success toast for completed actions.
- Warning message for non-blocking warnings.
- Error message for blocked actions.
- Do not use toast alone for important validation; keep the issue visible on the page.

## 5. Dashboard Layout

Top section:
- Welcome/page title.
- Current period selector for payment summary.

First row:
- PR status summary.
- RAB status summary.
- Contract status summary.
- LHSTP status summary.

Second row:
- Contracts ending within 30 days.
- LHSTP payment total for selected period.

Lower section:
- Recent activity list.
- Quick-create actions.

No trend charts in the initial release, consistent with the agreed preference for a simple dashboard.

## 6. Module Form Layouts

### PR form sections

1. PR Information: From (fixed ITMS), To, Subject, Date, Category.
2. Budget Items: Budget Name, Qty, Unit Price.
3. Summary: Grand Total.
4. Actions: Save Draft, Finalize, Cancel.

PR PDF preview must hide Unit Price and preserve the supplied template columns.

### RAB form sections

1. RAB Information: Work Name, Date, Location, optional source PR.
2. Budget Details: Name, Description, Qty, Unit, Price, Amount.
3. Summary: Grand Total and linked PR comparison when applicable.
4. Actions: Save Draft, Finalize, Cancel.

If created from PR, show imported items and make their origin clear without preventing edits.

### Contract form sections

1. Contract Identity: PO Number, Contract Date, Work Name.
2. Vendor: select Master Vendor and show company details.
3. Representative: Name and Position.
4. Contract Period: Start Date and End Date.
5. Financial Terms: Contract Value, PPN status, Payment Method.
6. Payment Schedule: conditional fields for Bulanan, Termin, or Full Financing.
7. Attachments: upload multiple PDF files.
8. Actions: Save, Archive, Cancel.

### LHSTP form sections

1. Contract Selection and Snapshot: PO, vendor, work, dates, representative.
2. LHSTP Information: manual number, date, work period.
3. Realization: start/end dates, calculated calendar days.
4. Reference Document: type, number, date, conditional name for Dokumen Lainnya.
5. Work Conclusion and Payment Terms: manual text.
6. Payment: method-specific amount/termin, PPN status/rate, DPP, PPN, total, Terbilang.
7. Other Document Values: Cost Center, Opname Date, Signature Date.
8. Preview and Actions: Save Draft, Finalize.

The calculation preview should update when payment amount or PPN rate changes. Clearly label the contract's PPN inclusion status and the formula path used.

## 7. Detail Pages

Each detail page should use tabs or clearly separated sections:
- Overview
- Items/Payment Details
- Version History (where applicable)
- Documents
- Activity

Do not hide critical information behind many tabs. For PR/RAB/LHSTP, current version and status should be visible near the top.

## 8. Accessibility and Usability

- Maintain sufficient text/background contrast.
- All controls have visible labels.
- Keyboard focus is visible.
- Buttons have clear action names.
- Do not rely on color alone.
- Confirmation dialogs are keyboard accessible.
- Avoid tiny click targets.
- Use clear Indonesian wording for warnings and errors.

## 9. Visual QA Checklist

- Same sidebar and top bar across modules.
- Same button hierarchy and form spacing.
- Currency and date formatting consistent.
- Tables readable at standard desktop width.
- Long vendor/work/item names wrap without hiding values.
- Draft/Final/Archived states consistently displayed.
- Destructive actions visually distinct and confirmed.
- Document previews and download links are easy to find.
- Layout remains usable at laptop and tablet widths.

---

## 10. Addendum UI (approved 19 November 2026)

### 10.1 PR list and detail

- PR list shows Document Status and derived Tracking Status as separate columns; filters for both are separate. Tracking Status comes from stage history, not a stored field.
- PR detail shows a tracking timeline of five sequential stages with timestamp, actor and note, plus an update form. Completed stages remain visible; corrections appear as additional entries.
- Tracking starts only after manual confirmation of Stage 1. Finalization must not imply tracking has started.
- PR detail shows a Document Archive section with category, filename, upload date, notes, preview and download.
- PR detail shows linked Contracts/POs and a derived payment summary with contract-level status and individual LHSTP progress where applicable.
- Permanent deletion of a PR with linked Contracts shows a blocking reason and the required resolution path.
- Archival must not visually imply completion; `Done` appears only after stage 5.

### 10.2 Contract screens

- Contract create/edit includes an optional PR selection field.
- Contract detail shows the linked PR and payment records with their `Draft` / `Confirmed` state and derived status.
- Unlink action is disabled with an explanatory reason when the Contract has LHSTP records or Payment Records; successful unlink is recorded in Activity History.
- PR permanent deletion shows the required unlink steps while linked Contracts remain.

### 10.3 Document previews

- Preview only for PDF, JPG and PNG. Other supported formats show download only.
- Preview and download links are resolved by record ID; no storage path is shown to the user.

### 10.4 Recycle Bin

- PR documents appear as their own entity type with category and filename.
- Payment evidence appears as its own entity type and can be restored; permanent purge removes the file.
- Restore and Delete Permanently use the existing confirmation pattern.

### 10.5 Payments and evidence

- A payment record shows multiple evidence attachments with filename, upload date and download action.
- Evidence upload accepts PDF, JPG, JPEG and PNG only, up to 10 MB per file; invalid files show a clear error.
- Payments show `Draft` or `Confirmed` state. Draft payments are visibly excluded from paid totals. Confirmed records are read-only; a correction creates a new linked entry.
- Overpayment attempts show a blocking error explaining the outstanding obligation.
- Final LHSTP revision or archiving that would create an overpayment shows a blocking error requiring a recorded payment correction.
