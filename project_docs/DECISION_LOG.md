# Decision Log — ITMS One

## 2026-11-19 — Phase 7: Dasar total pembayaran LHSTP pada dashboard

- **Current specification:** Blueprint §5.5: "Sum `total_payment` from Final LHSTP versions", difilter menurut `lhstp_date`.
- **Issue:** satu LHSTP dapat memiliki versi Final lama setelah revisi. Menjumlah semua versi Final menghitung pembayaran yang sama lebih dari sekali.
- **Decision:** Opsi A — jumlahkan hanya versi aktif (`lhstps.current_version_id`) dari LHSTP berstatus Final, difilter menurut `lhstp_date` pada versi tersebut.
- **Rationale:** mencegah penggandaan pembayaran hasil revisi; satu LHSTP menyumbang paling banyak satu jumlah per periode.
- **Implementation:** `DashboardQueryService::paymentSummary()`.
- **Impact:** versi Final lama yang bukan versi aktif tidak dihitung. Bila keputusan berubah ke Opsi B, ubah satu query tersebut.
- **Status:** Final, disetujui pengguna.

## 2026-11-19 — Import data lama `pr_db.sql`

- **Decision:** Opsi C — tetapkan aturan pemetaan, jalankan dry-run, lalu impor hanya baris yang relevan lewat service aplikasi.
- **Conflict rules:** kontrak ID 2 dipertahankan untuk PO ganda; LHSTP tanpa nomor/tanggal/field wajib dilewati; nomor PR baru dialokasikan berurutan; item dengan harga atau quantity tidak valid dilewati saja.
- **Additional ruling:** semua kontrak dilewati karena dump tidak memuat aturan PPN dan metode pembayaran; nilai tersebut tidak direka.
- **Dry run result:** 99 baris siap, 134 baris dilewati alasan. Laporan: `storage/app/private/imports/legacy-pr-20261119095241-6afe6479e9cb4.csv`.
- **Status:** Desain dan rencana disetujui. `--apply` sudah dijalankan ke `itms_admin_db`: 94 PR, 2 vendor, dan 3 departemen masuk; kontrak/LHSTP tetap dilewati. PR lama berjumlah 5 tidak diubah. Buat cadangan sebelum apply: `C:\ITMS-Backup\20261119-095735`. Peta sumber→target tersebar di dua laporan apply karena dua PR menyusul setelah perbaikan bug laporan: `legacy-pr-20261119095754-6afe65b263f63.csv` (92 PR) dan `legacy-pr-20261119100125-6afe66855cc3a.csv` (2 PR + master). Kedua laporan disimpan; jangan dihapus.

## 2026-11-19 - Arsipkan PR hasil import

- **Current specification:** `PurchaseRequestService::archive()` only allows `Final` PR to become `Archived`.
- **Issue:** user requested all imported legacy PRs, which are `Draft`, to become `Archived` without creating new Final versions or documents.
- **Decision:** one-off direct status update for the 94 imported PRs only; set `status=Archived`, `archived_at`, `updated_by`, and write one `archived` activity log per PR. The five pre-existing PRs were not touched. Current versions remain marked Draft.
- **Impact:** the service rule still applies to normal user actions; only imported historical rows bypass it. Edit/finalize actions are unavailable for these rows because they are no longer Draft or Final.
- **Backup:** `C:\ITMS-Backup\20261119-100442` was taken before the update.
- **Verification:** 94 target IDs from the two apply CSVs were checked as existing Draft PRs before update; after update: 96 Archived, 3 Final, 0 Draft; 94 new `archived` activity logs with "data impor".
- **Status:** Final, disetujui pengguna.

## 2026-11-19 — PR Status Model (addendum)

- **Current specification:** `PurchaseRequest` status is `Draft` / `Final` / `Archived` in PRD §5.6, database schema and 99 existing records.
- **Issue:** new tracking dimension `Not Tracked` / `In Progress` / `Done` would conflict if it replaced the document lifecycle.
- **Decision:** keep Document Status unchanged; add a separate Tracking Status. Archival never marks `Done`. Tracking changes never alter document status.
- **Rationale:** preserves approved lifecycle, recycle-bin protections and existing data.
- **Impact:** tracking status is derived from `pr_stage_updates` (Decision 10); no `tracking_status` column on `purchase_requests`. No change to existing document status values or history.
- **Status:** Final, disetujui pengguna.

## 2026-11-19 — PR to Contract/PO relationship (addendum)

- **Current specification:** `contracts.po_number` is the PO identifier; one PO = one contract = one vendor; no PR foreign key exists.
- **Issue:** requirement "one PR can have multiple POs" needs a link without introducing a separate PO entity.
- **Decision:** add nullable `contracts.purchase_request_id`. One PR → many Contracts/POs; one Contract/PO → at most one PR; unlinked Contracts remain valid. PO uniqueness and single-vendor rule unchanged. No Purchase Orders entity.
- **Rationale:** smallest change consistent with approved PRD §7.1–7.2.
- **Impact:** Contract create/edit and PR detail show/manage the link; legacy contracts stay unlinked.
- **Status:** Final, disetujui pengguna.

## 2026-11-19 — Tracking status for existing PRs (addendum)

- **Current specification:** 99 existing PRs (96 Archived from import, 3 Final) have no tracking data.
- **Issue:** new tracking dimension needs an initial value without inventing history.
- **Decision:** set all 99 to `Not Tracked`. Do not infer procurement progress or fabricate stage history.
- **Rationale:** avoids unverifiable historical claims.
- **Impact:** no stage history is created, so the derived status is `Not Tracked` for all 99 records (Decision 10). Existing document statuses and versions unchanged.
- **Status:** Final, disetujui pengguna.

## 2026-11-19 — Payment Tracking independence (addendum)

- **Current specification:** payment information exists only as LHSTP document status and dashboard totals; a Final LHSTP is not evidence of actual payment.
- **Issue:** actual payment state must be tracked separately and tied to contract/payment term.
- **Decision:** add payment records associated with contract and optional contract termin. Payment status is derived from records; PR-level summary is derived from linked contracts. Payment never modifies PR tracking status.
- **Rationale:** prevents a second conflicting source of truth and keeps tracking independent.
- **Impact:** new `payment_records` entity and derived status calculation; LHSTP unchanged.
- **Status:** Final, disetujui pengguna.

## 2026-11-19 — PR Document Archive (addendum)

- **Current specification:** `generated_documents` holds system-generated PDF/DOCX only; no user-uploaded PR document entity exists.
- **Issue:** requirement for a dedicated archive with fixed categories, notes, private storage, preview and recycle-bin lifecycle.
- **Decision:** create `pr_documents`, distinct from generated documents. Fixed categories: PR Submission Form, Supporting Documents, Official PR Document, Procurement Documents, Other Documents. Preview for PDF/JPG/PNG; other formats download-only. Record-level authorization; deletion via Shared Recycle Bin; permanent purge removes metadata and physical file. Uploads never modify finalized versions or generated files.
- **Rationale:** keeps user uploads separate from immutable generated output and avoids orphaned private files.
- **Impact:** new table, model, service, routes and recycle-bin entity type.
- **Status:** Final, disetujui pengguna.

## 2026-11-19 — Decision 6: PR Tracking Transition Rules

- **Current specification:** addendum §16.3 defines five stages and append-only history. Open decision 2 covered trigger, ordering and corrections.
- **Issue:** transition trigger, stage ordering and correction handling were undefined.
- **Decision:** stages progress sequentially. Tracking begins only when a user manually confirms Stage 1. A PR becoming Final does not start tracking. Every stage update and correction is appended to history; prior records are never deleted or overwritten. Completion of Stage 5 sets Tracking Status to `Done`.
- **Rationale:** tracking reflects actual workflow evidence, not document lifecycle events.
- **Impact:** `PrTrackingService` enforces sequential progression and append-only corrections; archival and finalization do not trigger tracking.
- **Status:** Final, disetujui pengguna.

## 2026-11-19 — Decision 7: Payment Status Calculation

- **Current specification:** addendum §16.6 requires payment status derived from payment records, independent of LHSTP document status. Open decision 1 covered the calculation base.
- **Issue:** payment obligation base and overpayment behavior were undefined.
- **Decision:** obligation = accumulated total of Final LHSTP amounts for the relevant contract. Draft and Archived LHSTPs do not contribute. Status is calculated from confirmed payment records. Overpayments exceeding the outstanding obligation are rejected. Status values: `Unpaid`, `Partially Paid`, `Paid`. Individual LHSTP payment progress should be visible where applicable; contract-level status summarizes the overall position.
- **Rationale:** ties payment reality to financial documents that are Final, while keeping payment independent of LHSTP document status.
- **Impact:** `PaymentStatusCalculator` derives status from Final LHSTP totals and confirmed records. No stored PR payment status.
- **Status:** Final, disetujui pengguna.

## 2026-11-19 — Decision 8: Payment Evidence and Recycle Bin

- **Current specification:** addendum §16.6 requires supporting payment evidence; open decision 3 covered evidence structure and recycle-bin treatment.
- **Issue:** multiple attachments, restore and permanent purge behavior were undefined.
- **Decision:** each payment record may have multiple evidence attachments. Evidence deletion integrates with the Shared Recycle Bin. Evidence must be restorable. Permanent purge deletes both database records and physical files.
- **Rationale:** prevents orphaned private files and keeps evidence recoverable.
- **Impact:** new `payment_evidences` entity, recycle-bin registration, restore and purge handling.
- **Status:** Final, disetujui pengguna.

## 2026-11-19 — Decision 9: PR Permanent Deletion and Linked Contracts

- **Current specification:** addendum §16.4 allows a PR to have many linked Contracts/POs. Open decision 4 covered foreign-key behavior on PR purge.
- **Issue:** behavior when permanently deleting a PR with linked Contracts was undefined.
- **Decision:** permanent deletion of a PR is restricted while linked Contracts/POs exist. Users must resolve those relationships through an approved process before permanent PR deletion. Deleting a PR must never cascade-delete Contracts, LHSTPs, payment records or historical records.
- **Rationale:** protects financial and historical records from accidental loss.
- **Impact:** PR purge blocked with a clear reason while links exist; no cascade delete on any dependent entity.
- **Status:** Final, disetujui pengguna.

## 2026-11-19 — Decision 10: Tracking Status Storage

- **Selected option:** Option B — Derive Tracking Status from Stage History.
- **Approved rule:** Tracking Status is derived from the append-only `pr_stage_updates` history. No `tracking_status` column is introduced in `purchase_requests`. Mapping: no stage update records → `Not Tracked`; Stage 1–4 → `In Progress`; Stage 5 → `Done`. Stage history is the authoritative source.
- **Implications/constraints:** previously approved sequential progression and append-only correction history are preserved. Archival and finalization still do not set `Done`; payment does not affect tracking.
- **Status:** Approved.

## 2026-11-19 — Decision 11: Payment Record Confirmation and Correction Workflow

- **Selected option:** Option A — Draft and Confirmed Workflow.
- **Approved rule:** new payment records start as `Draft`. Draft payments do not contribute to paid totals or payment status. Only `Confirmed` payments count as actual payments. Confirmed payment records cannot be directly edited or deleted. Corrections to confirmed payments are recorded as new correction entries. Original payment records and their history remain preserved.
- **Implications/constraints:** payment status calculations use only Confirmed records. Correction entries link to the original payment record and remain append-only; no in-place mutation of confirmed records.
- **Status:** Approved.

## 2026-11-19 — Decision 12: Payment Evidence File Formats and Size Limits

- **Selected option:** Option A for both settings.
- **Approved rule:** allowed payment evidence formats are PDF, JPG, JPEG, PNG. Maximum file size is 10 MB per file.
- **Implications/constraints:** formats and limit must not be expanded. Multiple evidence attachments per payment record, Shared Recycle Bin deletion, restorable file availability, permanent purge of metadata and physical file, and private storage remain in force.
- **Status:** Approved.

## 2026-11-19 — Decision 13: Contract Unlinking Before PR Permanent Deletion

- **Selected option:** Option A — Restricted Unlinking.
- **Approved rule:** a Contract may be unlinked from a PR only if it has no LHSTP records and no Payment Records. If either condition is not met, unlinking is blocked. Every successful unlink is recorded in Activity History. A PR may proceed to permanent deletion only after all linked Contracts have been safely unlinked. Unlinking never cascade-deletes Contracts, LHSTPs, Payment Records or historical records.
- **Implications/constraints:** relationship cardinality is unchanged: one PR → many Contracts/POs, one Contract → at most one PR, unlinked Contracts allowed. PO uniqueness and single-vendor rule unchanged.
- **Status:** Approved.

## 2026-11-19 — Decision 14: Payment Obligation Changes After Confirmed Payments

- **Selected option:** Option A — Block Conflicting Changes.
- **Approved rule:** revisions or archiving of a Final LHSTP are blocked if the resulting change would cause confirmed payments to exceed the revised payment obligation. The discrepancy must first be resolved through a recorded payment correction.
- **Implications/constraints:** original LHSTP records and revision history, original payment records and correction history, and the confirmed-payments-only rule are preserved. Obligation remains the accumulated total of Final LHSTP amounts for the relevant Contract. No LHSTP change may silently create an overpayment condition.
- **Status:** Approved.

## 2026-11-20 — PR document upload formats and size limit (Phase 12)

- **Current specification:** PRD §16.5 and Blueprint §14.4 fix the preview allowlist (PDF, JPG, PNG) but do not list accepted upload formats or a size limit for `pr_documents`.
- **Issue:** Phase 12 upload validation cannot be implemented without an approved allowlist and limit.
- **Decision:** Option A — align with payment evidence (Decision 12): PDF, JPG, JPEG, PNG; maximum 10 MB per file. Formats and limit must not be expanded without approval.
- **Rationale:** reuses an already approved rule set, avoids inventing new formats, and keeps every accepted upload previewable.
- **Impact:** `PrDocumentController` validation and `PrDocument::MAX_FILE_SIZE_KB`. The "other supported formats are download-only" clause stays unused until a separate decision adds formats.
- **Status:** Approved by user, 2026-11-20.

## 2026-11-20 — Decision 15: Payment Edge Cases (Phase 13)

- **Current specification:** `payment_records.payment_amount` must be greater than zero; corrections append new records linked to the original; overpayments are rejected; only Confirmed records count.
- **Issue:** the documents did not define the meaning of a correction amount, when and at which level the overpayment check runs, or how totals are compared. Phase 13 was blocked on these details.
- **Decision (correction amount):** Option A — delta. A correction stores the signed difference: positive increases, negative decreases. Paid total = sum of Confirmed normal records plus sum of Confirmed corrections. The "greater than zero" rule becomes "not zero" for correction rows only.
- **Decision (overpayment check):** Option A — validated at confirmation, at contract level. New records stay free-form Draft. Confirmation of a record is rejected when the Confirmed total would exceed the contract obligation. Confirmation order determines which of several Draft records fails.
- **Decision (comparison):** Option A — exact comparison without rounding. Obligation is the sum of Final LHSTP `total_payment` values already rounded to whole rupiah; payments are `DECIMAL(18,2)`. Overpayment means paid > obligation, with no tolerance. `Paid` requires paid = obligation.
- **Implications/constraints:** Confirmed records remain immutable; corrections are new Draft entries that require their own confirmation. No adjustment records exist beyond the correction workflow.
- **Status:** Approved by user, 2026-11-20.

## Open decisions (addendum, 2026-11-19)

Decisions 10–15 resolved the previous open items on tracking storage, payment confirmation/correction, evidence formats and limits, contract unlinking and the remaining payment edge cases. No open decision currently blocks Phase 13.

## 2026-11-19 — Known existing technical concern

- **Current specification:** `ContractService::update()` deletes and recreates all `contract_termins`.
- **Issue:** `lhstp_version_termins.contract_termin_id` uses `restrictOnDelete`; editing a contract whose termin was consumed by a Final LHSTP may fail.
- **Decision:** investigate and fix in a separately scoped, approved task. No silent refactor of Contract Management.
- **Impact:** Phase 11 does not change this behavior. No change to LHSTP snapshots.
- **Status:** Open, separately scoped task required.
