# Project Brief --- Purchase Requisition (PR)

**Status:** Requirement analysis\
**Scope:** Modul Purchase Requisition untuk aplikasi web lokal ITMS\
**Last updated:** 16 November 2026

> Dokumen ini menjadi catatan acuan kebutuhan PR. Jika ada keputusan
> atau perubahan baru, perbarui bagian terkait tanpa menghapus keputusan
> sebelumnya yang masih berlaku. Tandai perubahan penting di bagian
> *Riwayat Perubahan*.

## 1. Tujuan dan ruang lingkup

Modul PR dibuat untuk membantu pekerjaan administrasi IT & Management
System (ITMS) dalam aplikasi web lokal pengganti proses Microsoft
Access.

-   Pengguna awal: satu pengguna (admin ITMS).
-   Cakupan penggunaan: kebutuhan administrasi ITMS saja.
-   PR dapat dibuat dan disimpan tanpa harus memiliki RAB.
-   PR dapat menjadi sumber data untuk membuat RAB, tetapi hubungan
    tersebut tidak wajib.

## 2. Data pada formulir PR

  -----------------------------------------------------------------------
  Field                               Ketentuan
  ----------------------------------- -----------------------------------
  Dari                                Diisi tetap: **Divisi ITMS / IT &
                                      Management System Department**.
                                      Untuk PDF, ikuti label pada
                                      template sumber yang digunakan.

  Kepada                              Dropdown manual: **Accounting
                                      Department** atau **Human Capital
                                      Department**. Pilihan tidak
                                      ditentukan otomatis berdasarkan
                                      kategori.

  Perihal                             Input teks manual.

  Tanggal                             Date picker. Pengguna bebas memilih
                                      tanggal, tidak otomatis harus
                                      tanggal hari ini.

  Kategori                            Pilihan tunggal dan boleh kosong.
                                      Opsi: Investment, Services, Office
                                      Supplies, Fixed Asset, Maintenance.

  Nomor PR                            ID numerik otomatis yang bertambah.
                                      Nomor bersifat permanen/unik dan
                                      tidak digunakan ulang setelah data
                                      dihapus. Format khusus belum
                                      ditentukan.
  -----------------------------------------------------------------------

## 3. Rincian anggaran PR

Satu PR dapat memiliki beberapa baris anggaran.

  -----------------------------------------------------------------------
  Field                               Ketentuan
  ----------------------------------- -----------------------------------
  Nama Anggaran                       Wajib diisi.

  Qty                                 Wajib diisi.

  Harga Satuan                        Diinput dan disimpan secara
                                      internal pada data PR.

  Estimasi Harga                      Dihitung otomatis:
                                      `Qty × Harga Satuan`. Ini adalah
                                      total harga untuk satu baris, bukan
                                      harga satuan.
  -----------------------------------------------------------------------

**Grand Total PR** adalah penjumlahan seluruh nilai Estimasi Harga.

### Perbedaan tampilan detail dan PDF

-   Halaman detail PR boleh menampilkan **Harga Satuan**.
-   PDF PR harus mengikuti template Form PR yang diberikan.
-   PDF PR **tidak boleh menampilkan Harga Satuan**.
-   Kolom pada template PDF meliputi: No, Nama Anggaran, Qty, Estimasi
    Harga, GL Account*, Cost Center*, dan No IO\*\*.
-   GL Account, Cost Center, dan No IO bukan input pada formulir utama
    aplikasi. Ketiganya hanya ditampilkan pada output PDF sesuai
    template; belum ada sumber nilai yang ditentukan untuk diisikan.

## 4. Hubungan PR dengan RAB

Hubungan PR dan RAB bersifat opsional:

-   PR dapat dibuat tanpa RAB.
-   RAB dapat dibuat tanpa PR.
-   RAB dapat dibuat berdasarkan PR.
-   RAB boleh dibuat belakangan setelah PR disimpan.

Jika membuat RAB berdasarkan PR:

1.  Semua item dari PR diimpor ke RAB; bukan hanya sebagian item.
2.  Pemetaan data:
    -   PR `Nama Anggaran` → RAB `Nama Anggaran`
    -   PR `Qty` → RAB `Qty`
    -   PR `Harga Satuan` yang tersimpan → RAB `Harga`
    -   PR `Estimasi Harga` → nilai awal RAB `Jumlah` (nilainya sama
        dengan Qty × Harga Satuan).
3.  **Harga Satuan PR harus dipindahkan langsung ke Harga RAB. Jangan
    menghitungnya kembali dengan membagi Estimasi Harga dengan Qty.**
4.  Deskripsi dan Unit RAB dilengkapi pada proses RAB; keduanya bukan
    field pada rincian PR yang telah disepakati.

### Sinkronisasi perubahan RAB ke PR

-   Jika total RAB berbeda dari PR, aplikasi meminta konfirmasi sebelum
    memperbarui PR.
-   Cakupan pembaruan yang pernah disepakati mencakup total dan nama
    anggaran yang relevan.
-   Sinkronisasi hanya memengaruhi PR yang memang terhubung dengan RAB
    tersebut.
-   Riwayat revisi harus tersedia.
-   Detail aturan sinkronisasi per item dan dampaknya terhadap revisi
    masih perlu dirinci sebelum implementasi.

## 5. Status dan siklus hidup PR

Status PR yang disepakati:

  -----------------------------------------------------------------------
  Status                              Ketentuan
  ----------------------------------- -----------------------------------
  Draft                               Dapat diedit dan dihapus.

  Final                               Tidak dihapus permanen. Perubahan
                                      dilakukan melalui revisi baru dan
                                      nomor PR tetap sama.

  Archived                            Tetap tersimpan sebagai arsip dan
                                      tidak dihapus permanen.
  -----------------------------------------------------------------------

Riwayat revisi harus dipertahankan.

## 6. Pembuatan dan penyimpanan PDF

-   Setelah PR difinalisasi atau direvisi, aplikasi meminta konfirmasi
    sebelum membuat PDF.
-   Jika pengguna menyetujui, PDF dibuat dan disimpan.
-   Jika pengguna tidak menyetujui, data tetap tersimpan tetapi tidak
    dibuat PDF baru.
-   PDF dari setiap revisi disimpan sebagai versi terpisah; PDF lama
    tidak ditimpa.
-   Dari halaman detail PR, pengguna dapat mengunduh PDF historis kapan
    saja.

## 7. Acuan dokumen sumber

Dokumen yang telah dibahas sebagai acuan PR:

-   `Form PR.pdf` --- template formulir PR.
-   `cth_pr.pdf` --- contoh PR yang telah diisi.

Ketentuan yang bersumber dari template/contoh harus dibedakan dari
keputusan tambahan untuk aplikasi. Contohnya, penyimpanan Harga Satuan
secara internal merupakan kebutuhan aplikasi; kolom Harga Satuan tidak
ditampilkan pada template PDF PR.

## 8. Hal yang belum diputuskan

Jangan menganggap hal-hal berikut sudah disepakati:

-   Format tampilan nomor PR selain ID numerik otomatis.
-   Nilai GL Account, Cost Center, dan No IO pada PDF.
-   Aturan rinci sinkronisasi item RAB ke PR, termasuk konflik dengan
    revisi PR.
-   Detail teknis implementasi dan struktur database.

## 9. Riwayat Perubahan

  -----------------------------------------------------------------------
  Tanggal                             Perubahan
  ----------------------------------- -----------------------------------
  16 November 2026                    Penyusunan awal rangkuman kebutuhan
                                      PR berdasarkan keputusan diskusi
                                      yang telah disepakati.

  -----------------------------------------------------------------------
