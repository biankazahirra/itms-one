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
