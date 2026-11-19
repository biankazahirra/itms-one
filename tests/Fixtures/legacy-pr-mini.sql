-- Minimal phpMyAdmin-style fixture for legacy import tests.
CREATE TABLE `vendor` (
  `id_vendor` int(11) NOT NULL,
  `nama_vendor` varchar(255) NOT NULL
);

INSERT INTO `vendor` (`id_vendor`, `nama_vendor`) VALUES
(1, 'PT Krakatau Information Technology'),
(2, 'PT Kosera Karya Prima');

CREATE TABLE `purchase_requisition` (
  `id_pr` int(11) NOT NULL,
  `kepada` varchar(100) DEFAULT NULL,
  `dari` varchar(100) DEFAULT NULL,
  `perihal` varchar(200) DEFAULT NULL,
  `tanggal` date DEFAULT NULL,
  `status_dokumen` varchar(20) DEFAULT 'Draft'
);

INSERT INTO `purchase_requisition` (`id_pr`, `kepada`, `dari`, `perihal`, `tanggal`, `status_dokumen`) VALUES
(30, 'Divisi Accounting', 'Divisi ITMS', 'Lisensi Office 365, Autocad, & Power BI', '2025-08-05', 'Draft');

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
(52, 30, 'Lisensi Office 365', 1, 300000000.00, NULL, 'Merk A\nBaris 2');
