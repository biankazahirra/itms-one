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
- **Status:** Desain dan rencana disetujui; kode import selesai dan diuji. `--apply` belum dijalankan ke `itms_admin_db`, menunggu instruksi terpisah setelah laporan ditinjau.
