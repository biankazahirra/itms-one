# Project Brief --- Rencana Anggaran Biaya (RAB)

**Status:** Requirement analysis\
**Scope:** Modul RAB untuk aplikasi web lokal ITMS\
**Last updated:** 16 November 2026

> Dokumen ini menjadi acuan kebutuhan modul RAB. Perbarui bagian terkait
> ketika ada keputusan baru. Jangan mengubah keputusan yang sudah
> disepakati tanpa konfirmasi.

## 1. Tujuan dan ruang lingkup

Modul RAB digunakan untuk menyusun rincian anggaran pekerjaan ITMS.

RAB dapat: - Dibuat mandiri tanpa PR. - Dibuat berdasarkan satu PR yang
sudah tersimpan. - Dibuat setelah PR selesai dibuat.

**Relasi:** Satu RAB hanya boleh terhubung dengan satu PR. Satu RAB
tidak boleh menggabungkan beberapa PR.

## 2. Data utama RAB

  -----------------------------------------------------------------------
  Field                               Ketentuan
  ----------------------------------- -----------------------------------
  Nomor RAB                           ID numerik otomatis yang terus
                                      bertambah. Nomor tetap sama saat
                                      revisi dan tidak digunakan ulang.

  Nama pekerjaan                      Jika terhubung ke PR, otomatis
                                      mengikuti `Perihal` PR. Jika RAB
                                      mandiri, diisi manual.

  Tanggal                             Jika terhubung ke PR, otomatis
                                      mengikuti tanggal PR, tetapi dapat
                                      diubah manual. Jika RAB mandiri,
                                      otomatis menggunakan tanggal hari
                                      ini dan dapat diubah manual.

  Lokasi                              Tetap: **Cilegon**.

  PR terkait                          Opsional. Jika RAB dibuat
                                      berdasarkan PR, hanya satu PR yang
                                      dapat dipilih.
  -----------------------------------------------------------------------

## 3. Rincian anggaran

Kolom RAB mengikuti template sumber:

  -----------------------------------------------------------------------
  Field                               Ketentuan
  ----------------------------------- -----------------------------------
  Nama Anggaran                       Wajib diisi.

  Deskripsi                           Opsional, boleh kosong.

  Qty                                 Wajib diisi.

  Unit                                Wajib diisi. Menggunakan dropdown,
                                      tetapi pengguna dapat menambahkan
                                      unit baru. Unit baru disimpan
                                      sebagai master data untuk
                                      penggunaan berikutnya.

  Harga                               Harga satuan.

  Jumlah                              Dihitung otomatis: `Qty × Harga`.
  -----------------------------------------------------------------------

**Total RAB** adalah penjumlahan seluruh nilai `Jumlah`.

## 4. Impor item dari PR

Jika RAB dibuat berdasarkan PR:

1.  Semua item PR diimpor ke RAB; pengguna tidak memilih sebagian item
    saja.
2.  Pemetaan awal:
    -   PR `Nama Anggaran` → RAB `Nama Anggaran`
    -   PR `Qty` → RAB `Qty`
    -   PR `Harga Satuan` yang tersimpan di PR → RAB `Harga`
    -   PR `Estimasi Harga` → nilai awal RAB `Jumlah`
3.  Harga Satuan PR harus dipindahkan langsung ke Harga RAB. Jangan
    menghitungnya kembali dengan membagi Estimasi Harga dengan Qty.
4.  Deskripsi dan Unit dilengkapi pada RAB.
5.  Setelah diimpor, seluruh item boleh diedit di RAB: Nama Anggaran,
    Qty, dan Harga.
6.  Pengguna boleh menambahkan item/nama anggaran baru yang tidak ada di
    PR.
7.  Nama anggaran RAB boleh lebih terperinci daripada PR. Contoh:
    `Spare part IT (bundle)` pada PR dapat diperinci menjadi beberapa
    komponen di RAB.

## 5. Kesesuaian nilai PR dan RAB

Jika RAB terhubung ke PR:

-   Total nilai RAB wajib sama dengan total nilai PR.
-   Jika total berbeda, aplikasi menampilkan peringatan.
-   RAB tidak dapat difinalisasi selama totalnya belum sama dengan total
    PR.
-   Perubahan rincian RAB tidak mengubah data PR.
-   Tidak ada sinkronisasi otomatis dari RAB ke PR.

Dengan demikian, PR tetap menjadi rekaman pengajuan awal, sedangkan RAB
menjadi rincian anggaran pekerjaan.

RAB mandiri tanpa PR tidak memiliki pemeriksaan kesamaan total terhadap
PR.

## 6. Status dan siklus hidup RAB

RAB menggunakan tiga status:

  -----------------------------------------------------------------------
  Status                              Ketentuan
  ----------------------------------- -----------------------------------
  Draft                               Dapat diedit dan dihapus.

  Final                               Tidak diedit langsung. Perubahan
                                      dilakukan melalui revisi baru.

  Archived                            Tetap tersimpan sebagai arsip dan
                                      tidak dihapus permanen.
  -----------------------------------------------------------------------

Ketentuan revisi: - Nomor RAB tetap sama saat direvisi. - Setiap revisi
memiliki riwayat tersendiri. - Versi sebelumnya tetap tersimpan dan
dapat diakses.

## 7. Pembuatan dan penyimpanan PDF

Mekanisme PDF mengikuti PR:

-   Setelah RAB difinalisasi atau direvisi, aplikasi meminta konfirmasi
    sebelum membuat PDF.
-   Jika disetujui, PDF dibuat dan disimpan.
-   Jika tidak disetujui, data tetap tersimpan tanpa PDF baru.
-   PDF dari setiap revisi disimpan sebagai versi terpisah dan tidak
    menimpa versi sebelumnya.
-   Semua PDF historis dapat diunduh dari halaman detail RAB.

PDF mengikuti template RAB yang diberikan, termasuk susunan kolom,
total, lokasi/tanggal, dan area tanda tangan.

## 8. Tanda tangan dan identitas pada PDF

Nama penandatangan menggunakan nama tetap seperti pada contoh RAB:

-   **Disetujui oleh:** Tb. Benny Kurniawan --- ITMS Manager.
-   **Disiapkan oleh:** Lazuardi Arsy --- IT Senior Engineer.

Nama tersebut otomatis digunakan pada PDF, tidak perlu diisi ulang
setiap membuat RAB.

Lokasi tanda tangan tetap **Cilegon**.

## 9. Daftar RAB

Halaman daftar RAB menampilkan informasi berikut:

-   Nomor RAB
-   Nama pekerjaan
-   Nomor PR terkait (jika ada)
-   Tanggal RAB
-   Total anggaran
-   Status RAB

## 10. Acuan dokumen sumber

Dokumen yang telah dibahas sebagai acuan: - `Report_RAB.pdf` ---
template RAB. - `cth_RAB.pdf` --- contoh RAB yang telah diisi.

Ketentuan tambahan aplikasi yang disepakati: - Unit wajib diisi meskipun
kolom Unit pada contoh sumber belum terisi. - Unit menggunakan dropdown
dengan master data yang dapat ditambah pengguna. - Item RAB hasil impor
PR boleh diubah dan boleh ditambah.

## 11. Hal yang belum diputuskan

Jangan menganggap hal-hal berikut sudah disepakati: - Format tampilan
nomor RAB selain ID numerik otomatis. - Aturan teknis detail tentang
bagaimana versi revisi disimpan di database. - Detail teknis
implementasi dan struktur database. - Apakah ada validasi tambahan untuk
nilai Qty atau Harga selain kewajiban pengisian.

## 12. Riwayat Perubahan

  -----------------------------------------------------------------------
  Tanggal                             Perubahan
  ----------------------------------- -----------------------------------
  16 November 2026                    Penyusunan awal rangkuman kebutuhan
                                      RAB berdasarkan keputusan diskusi
                                      yang telah disepakati.

  -----------------------------------------------------------------------
