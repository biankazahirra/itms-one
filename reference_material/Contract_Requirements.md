# Contract Management Requirements

## 1. Tujuan dan ruang lingkup
Modul Manajemen Kontrak menjadi sumber data kontrak untuk proses LHSTP. Kontrak dicatat secara manual melalui aplikasi. Tidak ada fitur OCR maupun ekstraksi data otomatis dari dokumen.

## 2. Pencatatan kontrak dan identitas
- Kontrak dibuat melalui form input manual.
- Nomor PO menjadi identitas utama kontrak; tidak ada Nomor Kontrak terpisah.
- Nomor PO wajib diisi.
- Jika Nomor PO yang dimasukkan sudah terdaftar, aplikasi menampilkan konfirmasi/peringatan sebelum pengguna melanjutkan. Perilaku akhir setelah konfirmasi belum dirinci lebih lanjut.
- Satu Nomor PO mewakili satu kontrak dan satu vendor.
- Satu vendor dapat memiliki beberapa kontrak dengan Nomor PO berbeda.

## 3. Master Vendor
Master Vendor merupakan data terpisah dan satu perusahaan hanya boleh memiliki satu entri.
- Nama perusahaan: wajib.
- Alamat perusahaan: wajib.
- NPWP: opsional.
- Nomor telepon: opsional.
- Email perusahaan: opsional.
- Aplikasi sebaiknya memperingatkan jika nama perusahaan yang sama dimasukkan kembali.
- Nama dan jabatan perwakilan vendor bukan bagian dari Master Vendor; keduanya disimpan per kontrak karena satu vendor dapat memiliki perwakilan berbeda pada kontrak yang berbeda.

## 4. Data utama kontrak
Data yang dicatat pada setiap kontrak:
- Nomor PO (identitas utama).
- Tanggal kontrak.
- Vendor (dipilih dari Master Vendor).
- Nama pekerjaan.
- Nama perwakilan vendor.
- Jabatan perwakilan vendor.
- Tanggal mulai kontrak.
- Tanggal selesai kontrak.
- Nilai kontrak.
- Status PPN: `Termasuk PPN` atau `Belum termasuk PPN`.
- Metode pembayaran.
- Lampiran dokumen kontrak asli dalam format PDF.

Tanggal mulai dan tanggal selesai menjadi referensi durasi kontrak dan validasi periode realisasi pada LHSTP.

## 5. Lampiran dokumen
- Satu kontrak dapat memiliki lebih dari satu lampiran PDF.
- Lampiran digunakan sebagai dokumentasi, misalnya kontrak utama dan addendum.
- File dapat dibuka atau diunduh dari detail kontrak.
- Tidak ada OCR atau pengambilan data otomatis dari PDF.

## 6. Metode pembayaran
Kontrak mendukung tiga metode:
1. Bulanan.
2. Termin.
3. Full Financing.

Untuk metode Termin, setiap termin menyimpan:
- Nama atau urutan termin.
- Persentase pembayaran.
- Nominal pembayaran.

Data pembayaran kontrak menjadi sumber pengisian awal data pembayaran pada LHSTP, mengikuti aturan LHSTP yang telah disepakati.

Tidak diperlukan field khusus untuk syarat pembayaran maupun kesimpulan/ruang lingkup pekerjaan.

## 7. Status kontrak
Status kontrak:
- `Aktif`.
- `Nonaktif`.
- `Archived`.

Ketentuan:
- Status berubah otomatis menjadi Nonaktif ketika tanggal selesai kontrak terlewati.
- Archived dilakukan secara manual.
- Kontrak Archived tidak berubah otomatis menjadi Nonaktif.
- Kontrak Nonaktif tetap boleh digunakan untuk membuat LHSTP.
- Kontrak Archived tidak boleh digunakan untuk membuat LHSTP baru.

## 8. Perubahan data dan snapshot LHSTP
- Data kontrak boleh diedit meskipun sudah memiliki LHSTP.
- Perubahan data kontrak tidak mengubah LHSTP yang sudah dibuat karena LHSTP menyimpan snapshot data pada saat dibuat.
- LHSTP baru menggunakan data kontrak terbaru.

## 9. Hubungan kontrak dengan LHSTP
- Total pembayaran seluruh LHSTP untuk satu kontrak boleh melebihi Nilai Kontrak.
- Jika total pembayaran LHSTP melampaui Nilai Kontrak, aplikasi menampilkan konfirmasi sebelum pengguna melanjutkan.
- Termin yang sudah memiliki LHSTP berstatus Final dianggap sudah terpakai dan tidak dapat dipilih untuk LHSTP baru.
- Pengecualian: revisi dari LHSTP yang sama boleh tetap menggunakan termin tersebut.
- Revisi LHSTP tidak dianggap sebagai pembayaran/termin baru.

## 10. Penghapusan
- Penghapusan kontrak masuk ke Recycle Bin.
- Kontrak tidak langsung dihapus permanen melalui tindakan hapus biasa.

## 11. Riwayat aktivitas
Aplikasi menyediakan riwayat aktivitas kontrak, termasuk perubahan:
- Nilai kontrak.
- Tanggal kontrak/periode kontrak.
- Vendor atau perwakilan.
- Metode pembayaran.
- Data kontrak lainnya yang diubah.

Riwayat mencatat waktu dan pelaku perubahan.

## 12. Daftar kontrak
Kolom daftar kontrak:
- Nomor PO.
- Nama pekerjaan.
- Vendor.
- Tanggal kontrak.
- Periode kontrak.
- Tanggal berakhir kontrak.
- Nilai kontrak.
- Metode pembayaran.
- Status.

## 13. Hal yang belum diputuskan
- Perilaku persis setelah konfirmasi Nomor PO duplikat (misalnya apakah tetap boleh menyimpan).
- Aturan validasi rinci untuk nilai kontrak, tanggal, dan persentase/nominal termin.
- Apakah perlu fitur pencarian dan filter khusus pada daftar kontrak, selain kolom yang telah ditentukan.
- Kebijakan pemulihan dan penghapusan permanen item kontrak dari Recycle Bin.
