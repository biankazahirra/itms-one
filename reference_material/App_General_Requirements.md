# General Application Requirements

## 1. User and access
- Versi awal aplikasi digunakan oleh satu pengguna.
- Tidak diperlukan pembagian role/hak akses untuk versi awal.
- Satu akun dapat mengakses seluruh modul.

## 2. Master Data
Master Data adalah data referensi yang digunakan berulang kali oleh modul transaksi.
Master Data tahap awal:
- Master Vendor.
- Master Unit.
- Master Departemen.

Detail field dan aturan masing-masing master mengikuti dokumen requirement modul terkait.

## 3. Recycle Bin
- Satu Recycle Bin bersama digunakan untuk seluruh modul: PR, RAB, Kontrak, dan LHSTP.
- Data yang dihapus melalui aplikasi masuk ke Recycle Bin, bukan langsung dihapus permanen.
- Aturan pemulihan dan penghapusan permanen masih perlu dirinci.

## 4. Dashboard utama
Dashboard menjadi halaman ringkasan administrasi dengan komponen:
- Ringkasan jumlah PR, RAB, Kontrak, dan LHSTP berdasarkan status.
- Ringkasan kontrak Aktif, Nonaktif, dan Archived.
- Indikator kontrak yang akan berakhir dalam 30 hari ke depan.
- Ringkasan LHSTP: jumlah Draft, Final, Archived, dan total nilai pembayaran berdasarkan periode.
- Aktivitas terbaru, seperti dokumen yang dibuat, diubah, atau difinalisasi.
- Akses cepat untuk membuat PR, RAB, Kontrak, dan LHSTP.
- Dashboard dibuat sederhana; belum memerlukan grafik tren.

## 5. Output LHSTP
- LHSTP dapat dihasilkan dalam format PDF dan DOCX.
- Kedua format menggunakan data dan snapshot versi LHSTP yang sama.
- Kedua format mengikuti template LHSTP yang telah disepakati.
