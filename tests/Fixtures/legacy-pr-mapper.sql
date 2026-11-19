-- Fixture for legacy import mapping tests.
CREATE TABLE `vendor` (
  `id_vendor` int(11) NOT NULL,
  `nama_vendor` varchar(255) NOT NULL
);
INSERT INTO `vendor` (`id_vendor`, `nama_vendor`) VALUES
(1, 'PT Krakatau Information Technology'),
(2, 'PT Krakatau Information Technology'),
(3, 'PT Kosera Karya Prima');

CREATE TABLE `purchase_requisition` (
  `id_pr` int(11) NOT NULL,
  `kepada` varchar(100) DEFAULT NULL,
  `dari` varchar(100) DEFAULT NULL,
  `perihal` varchar(200) DEFAULT NULL,
  `tanggal` date DEFAULT NULL,
  `pr_investment` tinyint(1) DEFAULT NULL,
  `pr_office_supplies` tinyint(1) DEFAULT NULL,
  `pr_maintenance` tinyint(1) DEFAULT NULL,
  `pr_services` tinyint(1) DEFAULT NULL,
  `pr_fixed_asset` tinyint(1) DEFAULT NULL,
  `status_dokumen` varchar(20) DEFAULT 'Draft'
);
INSERT INTO `purchase_requisition` (`id_pr`, `kepada`, `dari`, `perihal`, `tanggal`, `pr_investment`, `pr_office_supplies`, `pr_maintenance`, `pr_services`, `pr_fixed_asset`, `status_dokumen`) VALUES
(30, 'Divisi Accounting', 'Divisi ITMS', 'Lisensi Office 365, Autocad, & Power BI', '2025-08-05', 0, 0, 0, -1, 0, 'Draft'),
(31, 'Human Capital Department', 'IT & Management System Department', 'Pengadaan ATK', '2025-08-06', 0, -1, 0, 0, 0, 'Draft'),
(33, 'Divisi HC', 'Divisi ITMS', 'Pengadaan ATK', '2025-08-07', 0, -1, 0, 0, 0, 'Draft'),
(34, 'Accounting Department', 'Divisi ITMS', 'Pulpen dan Pensil', '2025-08-08', 0, 0, 0, 0, 0, 'Draft');

CREATE TABLE `detail_anggaran` (
  `id_detail` int(11) NOT NULL,
  `id_pr` int(11) DEFAULT NULL,
  `nama_anggaran` varchar(200) DEFAULT NULL,
  `qty` int(11) DEFAULT NULL,
  `estimasi_harga` decimal(15,2) DEFAULT NULL,
  `unit` varchar(50) DEFAULT NULL,
  `spesifikasi` text DEFAULT NULL
);
INSERT INTO `detail_anggaran` (`id_detail`, `id_pr`, `nama_anggaran`, `qty`, `estimasi_harga`, `unit`, `spesifikasi`) VALUES
(52, 30, 'Lisensi Office 365', 1, 300000000.00, NULL, NULL),
(53, 31, 'Pulpen', 10, 2500.00, NULL, NULL),
(54, 33, 'Pensil', 10, NULL, NULL, NULL),
(55, 34, 'Aqua Galon', 0, 21000.00, NULL, NULL);

CREATE TABLE `kontrak` (
  `id_kontrak` int(11) NOT NULL,
  `id_vendor` int(11) NOT NULL,
  `nama_pekerjaan` varchar(255) NOT NULL,
  `nomor_purchase_order` varchar(100) NOT NULL,
  `tanggal_kontrak` date NOT NULL,
  `nilai_kontrak` decimal(18,2) NOT NULL,
  `jangka_waktu_bulan` int(11) DEFAULT NULL,
  `tanggal_mulai_kontrak` date DEFAULT NULL,
  `tanggal_selesai_kontrak` date DEFAULT NULL,
  `nama_penandatangan_vendor` varchar(255) DEFAULT NULL,
  `jabatan_penandatangan_vendor` varchar(255) DEFAULT NULL
);
INSERT INTO `kontrak` (`id_kontrak`, `id_vendor`, `nama_pekerjaan`, `nomor_purchase_order`, `tanggal_kontrak`, `nilai_kontrak`, `jangka_waktu_bulan`, `tanggal_mulai_kontrak`, `tanggal_selesai_kontrak`, `nama_penandatangan_vendor`, `jabatan_penandatangan_vendor`) VALUES
(1, 1, 'Managed Service SAP', '3550002557', '2025-02-25', 2452635612.00, 12, '2025-01-01', '2025-12-31', 'Deehan', 'DT Manager'),
(2, 2, 'Managed Service SAP', '3550002557', '2025-02-25', 2452635612.00, 12, '2025-01-01', '2025-07-01', 'Aurora', 'Infra Manager'),
(3, 3, 'Pengadaan CCTV', '3250058411', '2026-09-01', 45982000.00, NULL, '2026-09-23', NULL, 'Muhamad Samsudin', 'Direktur');

CREATE TABLE `lhstp` (
  `id_lhstp` int(11) NOT NULL,
  `id_kontrak` int(11) NOT NULL,
  `nomor_lhstp` varchar(100) NOT NULL,
  `tanggal_lhstp` date NOT NULL,
  `nilai_pembayaran` decimal(18,2) DEFAULT NULL,
  `persentase_ppn` decimal(5,2) DEFAULT 12.00
);
INSERT INTO `lhstp` (`id_lhstp`, `id_kontrak`, `nomor_lhstp`, `tanggal_lhstp`, `nilai_pembayaran`, `persentase_ppn`) VALUES
(1, 2, 'KU/0225/2026', '2026-09-23', 40000000.00, 12.00),
(2, 3, 'KU/0228/2026', '2026-09-21', 45982000.00, 12.00),
(3, 2, '', '0000-00-00', 35652326.00, 12.00);
