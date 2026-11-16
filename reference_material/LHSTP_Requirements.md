# LHSTP Requirements

**Status:** Requirements gathering — compiled from decisions agreed so far  
**Scope:** Local web application for ITMS administration

## 1. General workflow
- LHSTP is created from an existing contract record; contract details are pulled automatically.
- One contract may have multiple LHSTPs (for example, one per month).
- For payment by termin, **one LHSTP covers exactly one termin**. Multiple termini require separate LHSTPs.

## 2. Data pulled from the contract
Automatically retrieve:
- PO number, contract date, vendor/company name, job name, contract value
- Contract duration, start date, and end date
- Payment method and its payment amount/termin details
- PPN inclusion status (Termasuk PPN / Belum termasuk PPN)
- Vendor representative name and position

### Vendor representative
- Representative name and position are maintained in the contract.
- If the representative changes, update the contract data.
- Existing LHSTP versions retain the representative details saved when that version was created.
- Future LHSTPs use the updated details.

## 3. Inputs when creating an LHSTP
- **LHSTP number:** manual entry.
- **LHSTP date:** manual entry, based on the invoice date.
- **Periode Pekerjaan:** free text, prefilled with month and year based on the realization date, but editable. Examples: `November 2026`, `1–15 November 2026`, or another description.
- **Actual realization start and end dates:** manual entry.
- **Reference document type:** dropdown: Surat Berita Acara, Surat Purchase Order, Dokumen Lainnya.
- If Dokumen Lainnya is selected, user enters the document name.
- Reference document number and date are entered manually.
- The generated PDF sentence adapts to the selected reference-document type.
- **PPN rate:** defaults to 12%, but can be changed when creating an LHSTP.
- **Cost Center:** defaults to `3111440`, but can be changed manually.

## 4. Payment method and amount
Payment method is stored in the contract and is separate from PPN inclusion status.

- **Bulanan:** monthly payment amount is stored in the contract and prefilled in the LHSTP.
- **Termin:** termin details are stored in the contract. Each termin can have an order/name, percentage, and nominal amount as applicable. User selects the termin being billed. One LHSTP is for one termin only.
- **Full Financing:** full payment amount is stored in the contract and prefilled in the LHSTP.

The prefilled amount can be adjusted in the LHSTP if invoice conditions require.

## 5. PPN calculation
The contract has a separate PPN status: **Termasuk PPN** or **Belum termasuk PPN**.

Let `N` be the nilai pembayaran entered and `r` be the selected PPN rate as a decimal (default `0.12`).

### A. Belum termasuk PPN
- DPP LAIN = `N × 11/12`
- PPN = `DPP LAIN × r`
- Total Pembayaran = `N + PPN`

Example: N = Rp12.000.000, r = 12%.
- DPP LAIN = Rp11.000.000
- PPN = Rp1.320.000
- Total = **Rp13.320.000**

### B. Termasuk PPN
The entered amount already includes PPN. Extract the tax without increasing the entered total:
- DPP LAIN = `(N ÷ (1 + (11/12 × r))) × 11/12`
- PPN = `DPP LAIN × r`
- Total Pembayaran = `N`

Example: N = Rp13.320.000, r = 12%.
- DPP LAIN = Rp11.000.000
- PPN = Rp1.320.000
- Total = **Rp13.320.000**

Agreed principle:
- Belum termasuk PPN: Total = Nilai Pembayaran + PPN.
- Termasuk PPN: PPN is extracted from the entered amount; total remains the entered amount.

- `Terbilang` is generated automatically from Total Pembayaran.
- The rate actually applied is saved on each LHSTP version. Later changes must not alter historical records.
- The template phrase referring to PPN 12% should be checked so it can reflect the actual applied rate when the rate is changed.

## 6. Realization duration and contract-period warning
- Duration is calculated automatically using calendar days, counting both start and end dates. Example: 1–5 November = 5 days.
- If realization dates fall outside the contract period, show a warning but still allow LHSTP creation.

## 7. Cost Center
- Default: `3111440`.
- Manually editable for exceptional cases.
- The applied value is saved with each LHSTP version.

## 8. Status and lifecycle
- **Draft:** editable and deletable.
- **Final:** cannot be edited directly.
- **Archived:** retained as an archive and not permanently deleted.

## 9. Finalization and PDF
- After finalization, ask whether to generate a PDF.
- If Yes, generate and save the PDF.
- If No, save the LHSTP as Final without generating a new PDF.

## 10. Revisions and history
- Corrections to a Final LHSTP are made through a new revision.
- LHSTP number stays the same across revisions.
- Previous versions remain accessible; a revision creates a new version and does not overwrite the old one.
- Each version stores a snapshot of its data, including payment amount, applied PPN rate, Cost Center, representative name/position, dates, and other relevant fields. Later contract changes must not alter existing versions.
- Each revision's PDF is stored separately. Older PDFs remain downloadable and are never overwritten.
- A version may exist without a PDF if the user declined PDF generation.

## 11. LHSTP number validation
- Number is entered manually.
- It must be unique across distinct LHSTP records.
- Revisions of the same LHSTP may reuse the number.
- Reject a number already assigned to another LHSTP.

## 12. Template-based output
The PDF follows the supplied `LHSTP_template.pdf`.

Template content identified:
- Title: LAPORAN HASIL SERAH TERIMA PEKERJAAN
- Recipient: Manager Accounting, PT Krakatau Sarana Properti
- Job name, PO number, contract date, and vendor
- Reference-document sentence with type, number, date, and work period
- Inspection/opname date
- Contract duration and contract start/end dates
- Realization duration and realization start/end dates
- Conclusion and payment terms
- Payment breakdown: Nilai Kontrak, Nilai Pembayaran, DPP LAIN, PPN, Total Pembayaran, and Terbilang
- Cost Center
- Signature section

Fixed first-party signatory:
- Tb. Benny Kurniawan
- IT & Management System Manager
- PT Krakatau Sarana Properti

Second-party details:
- Company name comes from the contract vendor.
- Representative name and position come from contract data and are snapshotted per LHSTP version.

## 13. Open questions
These have not yet been decided:
1. **Decided:** The inspection/opname date follows the LHSTP date automatically; it does not need a separate input.
2. **Conclusion and payment terms:** follow the selected contract; these details are maintained in contract data and pulled into the LHSTP.
3. **Signature date:** automatically follows the LHSTP date.
4. **Manual PDF generation:** yes. If PDF generation was declined at finalization, the user can later generate the PDF manually from the LHSTP detail page without creating a new revision.
5. **LHSTP list columns:** confirmed as Nomor LHSTP, Nama Pekerjaan, Nomor PO, Vendor, Periode Pekerjaan, Tanggal LHSTP, Nilai Pembayaran, and Status.
6. Are there additional validations for payment amounts, termin selection, or duplicate invoice/reference documents?
7. What currency rounding rule should be used (for example, whole rupiah)?
8. **PPN wording in PDF:** the phrase `Harga tersebut sudah termasuk PPN 12%` dynamically reflects the PPN rate applied to that LHSTP (for example, 11% if the selected rate is 11%).

---

This document records agreed business requirements and open questions. It does not finalize technical architecture, database design, or implementation details.


## Additional confirmed requirement
- Inspection/opname date defaults to the LHSTP date and remains manually editable for exceptional cases.


## Additional confirmed requirement
- Conclusion and payment terms follow the selected contract and are pulled from its data into each LHSTP.

- Signature date defaults to the LHSTP date but can be manually changed.

- If PDF generation was declined at finalization, the user can later generate it manually from the LHSTP detail page without creating a new revision.


## 14. LHSTP list page

The agreed main columns are:
- Nomor LHSTP
- Nama Pekerjaan
- Nomor PO
- Vendor
- Periode Pekerjaan
- Tanggal LHSTP
- Nilai Pembayaran
- Status


## Additional confirmed validation
- Nilai pembayaran wajib lebih dari nol. Nilai kosong, nol, atau negatif tidak diperbolehkan.


## Additional confirmed validation
- Termin yang dipilih harus terdaftar pada kontrak dan tidak boleh dipilih kembali jika sudah memiliki LHSTP berstatus Final, untuk mencegah penagihan ganda.


## Additional confirmed validation
- Nomor dan tanggal dokumen referensi wajib diisi.


## Additional confirmed validation
- Tanggal selesai realisasi tidak boleh lebih awal daripada tanggal mulai realisasi; jika demikian, sistem menolak penyimpanan.


## Additional confirmed currency formatting
- Only the final Total Pembayaran is rounded to the nearest whole rupiah and displayed with two decimal places as `,00`. Example: `Rp13.123.356,89` becomes `Rp13.123.357,00`. Intermediate components (DPP LAIN and PPN) are not rounded before calculating the total.


## Additional confirmed validation
- If a reference document number has already been used by another LHSTP, show a warning but allow the user to continue. This accommodates legitimate reuse, such as the same PO number appearing on multiple LHSTPs.


## Additional confirmed validation — overlapping work periods
- If another LHSTP under the same contract has an overlapping work period, the system displays a warning to help prevent duplicate billing for the same period.
- The warning does not automatically block creation; the user can review the overlap and decide whether to continue.

## Additional confirmed validation — warning timing
- The overlapping-period warning is checked both when saving a Draft and when finalizing an LHSTP.

## Additional confirmed lifecycle rule
- **Draft LHSTP may be deleted** if it will not be billed. Final and Archived LHSTPs are retained and are not permanently deleted.

## Additional confirmed deletion rule
- Deleted Draft LHSTPs use the application's existing, shared **Recycle Bin rules**. LHSTP does not define a separate retention, recovery, or permanent-deletion policy.


## 15. LHSTP search and filters

The LHSTP list page supports:
- Search by Nomor LHSTP, Nama Pekerjaan, Nomor PO, and Vendor.
- Filters by Status and date period.

## Confirmed template field mapping
- The inspection/opname sentence in the PDF uses the saved **Tanggal Opname** field (which defaults to Tanggal LHSTP but may be changed), not unconditionally the LHSTP date.

## Confirmed template field mapping — payment terms
- The payment-terms sentence/paragraph in the PDF is dynamic and must be populated from the selected contract's payment terms; it must not be hard-coded as monthly payment wording.

## Confirmed PDF layout requirement
- The generated LHSTP PDF should preserve the original template's two-page structure and closely match its visual layout, including logos, typography/formatting, paragraph layout, and signature-block placement.


## Format Output Dokumen
- LHSTP dapat dihasilkan dalam dua format: PDF dan Microsoft Word (`.docx`).
- Kedua format menggunakan data LHSTP yang sama, termasuk snapshot data pada versi LHSTP tersebut.
- Dokumen Word harus mengikuti struktur dan tata letak template LHSTP yang telah disepakati sedekat mungkin.
- Pembuatan DOCX tidak menggantikan PDF; pengguna dapat memilih format yang diperlukan.
- Ketersediaan pengunduhan format DOCX untuk versi lama mengikuti riwayat versi LHSTP, sehingga dokumen dapat dibuat/diunduh berdasarkan versi yang dipilih.
