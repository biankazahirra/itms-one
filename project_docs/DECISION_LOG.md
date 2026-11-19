# Decision Log — ITMS One

## 2026-11-19 — Phase 7: Dasar total pembayaran LHSTP pada dashboard

- **Current specification:** Blueprint §5.5: "Sum `total_payment` from Final LHSTP versions", difilter menurut `lhstp_date`.
- **Issue:** satu LHSTP dapat memiliki versi Final lama setelah revisi. Menjumlah semua versi Final menghitung pembayaran yang sama lebih dari sekali.
- **Decision:** Opsi A — jumlahkan hanya versi aktif (`lhstps.current_version_id`) dari LHSTP berstatus Final, difilter menurut `lhstp_date` pada versi tersebut.
- **Rationale:** mencegah penggandaan pembayaran hasil revisi; satu LHSTP menyumbang paling banyak satu jumlah per periode.
- **Implementation:** `DashboardQueryService::paymentSummary()`.
- **Impact:** versi Final lama yang bukan versi aktif tidak dihitung. Bila keputusan berubah ke Opsi B, ubah satu query tersebut.
- **Status:** Final, disetujui pengguna.
