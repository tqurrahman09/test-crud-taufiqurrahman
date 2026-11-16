-- =====================================================================
-- Database Inventaris Barang Gudang
-- Relasi:
--   kategori (1) ──< barang >── (1) satuan
--   barang   (1) ──< transaksi
-- =====================================================================
SET NAMES utf8mb4;
SET time_zone = '+07:00';

CREATE DATABASE IF NOT EXISTS db_gudang
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE db_gudang;

SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS transaksi;
DROP TABLE IF EXISTS barang;
DROP TABLE IF EXISTS satuan;
DROP TABLE IF EXISTS kategori;
SET FOREIGN_KEY_CHECKS = 1;

-- ---------------------------------------------------------------------
-- Master: kategori barang
-- ---------------------------------------------------------------------
CREATE TABLE kategori (
  id_kategori    INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  kode_kategori  VARCHAR(10)   NOT NULL,
  nama_kategori  VARCHAR(50)   NOT NULL,
  keterangan     VARCHAR(255)  NULL,
  created_at     TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id_kategori),
  UNIQUE KEY uk_kategori_kode (kode_kategori),
  UNIQUE KEY uk_kategori_nama (nama_kategori)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Master: satuan barang (pcs, box, rim, ...)
-- ---------------------------------------------------------------------
CREATE TABLE satuan (
  id_satuan    INT UNSIGNED NOT NULL AUTO_INCREMENT,
  kode_satuan  VARCHAR(10)  NOT NULL,
  nama_satuan  VARCHAR(30)  NOT NULL,
  PRIMARY KEY (id_satuan),
  UNIQUE KEY uk_satuan_kode (kode_satuan),
  UNIQUE KEY uk_satuan_nama (nama_satuan)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Tabel utama: barang
-- stok hanya berubah melalui transaksi (masuk/keluar)
-- ---------------------------------------------------------------------
CREATE TABLE barang (
  id_barang      INT UNSIGNED   NOT NULL AUTO_INCREMENT,
  kode_barang    VARCHAR(20)    NOT NULL,
  nama_barang    VARCHAR(100)   NOT NULL,
  id_kategori    INT UNSIGNED   NOT NULL,
  id_satuan      INT UNSIGNED   NOT NULL,
  stok           INT UNSIGNED   NOT NULL DEFAULT 0,
  stok_minimum   INT UNSIGNED   NOT NULL DEFAULT 0,
  harga_beli     DECIMAL(15,2)  NOT NULL DEFAULT 0.00,
  harga_jual     DECIMAL(15,2)  NOT NULL DEFAULT 0.00,
  lokasi_rak     VARCHAR(20)    NOT NULL,
  tanggal_masuk  DATE           NOT NULL COMMENT 'tanggal barang pertama kali didaftarkan',
  created_at     TIMESTAMP      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at     TIMESTAMP      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id_barang),
  UNIQUE KEY uk_barang_kode (kode_barang),
  UNIQUE KEY uk_barang_nama (nama_barang),
  KEY idx_barang_kategori (id_kategori),
  KEY idx_barang_satuan (id_satuan),
  CONSTRAINT fk_barang_kategori FOREIGN KEY (id_kategori)
    REFERENCES kategori (id_kategori) ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT fk_barang_satuan FOREIGN KEY (id_satuan)
    REFERENCES satuan (id_satuan) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Transaksi barang masuk / keluar
-- ON DELETE RESTRICT: barang yang punya riwayat transaksi tidak bisa dihapus
-- ---------------------------------------------------------------------
CREATE TABLE transaksi (
  id_transaksi  INT UNSIGNED            NOT NULL AUTO_INCREMENT,
  no_transaksi  VARCHAR(25)             NOT NULL COMMENT 'format IN/OUT-YYYYMMDD-NNNN',
  id_barang     INT UNSIGNED            NOT NULL,
  jenis         ENUM('masuk','keluar')  NOT NULL,
  tanggal       DATE                    NOT NULL,
  jumlah        INT UNSIGNED            NOT NULL,
  harga_satuan  DECIMAL(15,2)           NOT NULL,
  keterangan    VARCHAR(255)            NOT NULL COMMENT 'supplier / tujuan / catatan',
  created_at    TIMESTAMP               NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id_transaksi),
  UNIQUE KEY uk_transaksi_no (no_transaksi),
  KEY idx_transaksi_barang_tanggal (id_barang, tanggal),
  KEY idx_transaksi_tanggal (tanggal),
  CONSTRAINT fk_transaksi_barang FOREIGN KEY (id_barang)
    REFERENCES barang (id_barang) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================================
-- Data contoh
-- =====================================================================
INSERT INTO kategori (id_kategori, kode_kategori, nama_kategori, keterangan) VALUES
(1, 'ELK', 'Elektronik',           'Perangkat elektronik & aksesoris komputer'),
(2, 'ATK', 'Alat Tulis Kantor',    'Kertas, pulpen, dan perlengkapan kantor'),
(3, 'PRK', 'Perkakas',             'Peralatan kerja dan perkakas teknik'),
(4, 'KMS', 'Kemasan',              'Bahan pengemasan barang'),
(5, 'KBR', 'Kebersihan',           'Perlengkapan kebersihan');

INSERT INTO satuan (id_satuan, kode_satuan, nama_satuan) VALUES
(1, 'PCS',  'Pcs'),
(2, 'BOX',  'Box'),
(3, 'RIM',  'Rim'),
(4, 'ROLL', 'Roll'),
(5, 'UNIT', 'Unit'),
(6, 'PSG',  'Pasang');

-- stok = total masuk - total keluar pada tabel transaksi di bawah
INSERT INTO barang (id_barang, kode_barang, nama_barang, id_kategori, id_satuan, stok, stok_minimum, harga_beli, harga_jual, lokasi_rak, tanggal_masuk) VALUES
(1,  'BRG-0001', 'Laptop Lenovo ThinkPad E14',     1, 5,  10,   3, 11500000, 12750000, 'A-01', '2026-10-01'),
(2,  'BRG-0002', 'Kertas HVS A4 70gsm',            2, 3,  95,  20,    45000,    52000, 'B-02', '2026-10-01'),
(3,  'BRG-0003', 'Bor Listrik Makita HP1630',      3, 5,   5,   2,   650000,   780000, 'C-01', '2026-10-03'),
(4,  'BRG-0004', 'Kardus Box 40x30x30 cm',         4, 1, 180, 100,     6500,     8500, 'D-03', '2026-10-05'),
(5,  'BRG-0005', 'Lakban Bening 2 inch',           4, 4, 140,  50,     9000,    12000, 'D-01', '2026-10-05'),
(6,  'BRG-0006', 'Mouse Wireless Logitech M185',   1, 1,   8,  10,   145000,   175000, 'A-02', '2026-10-07'),
(7,  'BRG-0007', 'Cairan Pembersih Lantai 5 L',    5, 1,   2,   5,    85000,    99000, 'E-01', '2026-10-10'),
(8,  'BRG-0008', 'Pulpen Standard AE7 Hitam',      2, 2,  25,  10,    22000,    27000, 'B-01', '2026-10-12'),
(9,  'BRG-0009', 'Printer Epson L3210',            1, 5,   0,   2,  2150000,  2450000, 'A-03', '2026-11-14'),
(10, 'BRG-0010', 'Sarung Tangan Kerja Katun',      3, 6,   0,  24,     7500,    10000, 'C-02', '2026-11-15');

INSERT INTO transaksi (no_transaksi, id_barang, jenis, tanggal, jumlah, harga_satuan, keterangan) VALUES
('IN-20261001-0001',  1, 'masuk',  '2026-10-01',  10, 11500000, 'Stok awal'),
('IN-20261001-0002',  2, 'masuk',  '2026-10-01', 100,    45000, 'Stok awal'),
('IN-20261003-0001',  3, 'masuk',  '2026-10-03',   8,   650000, 'Stok awal'),
('IN-20261005-0001',  4, 'masuk',  '2026-10-05', 500,     6500, 'Stok awal'),
('IN-20261005-0002',  5, 'masuk',  '2026-10-05', 200,     9000, 'Stok awal'),
('IN-20261007-0001',  6, 'masuk',  '2026-10-07',  30,   145000, 'Stok awal'),
('IN-20261010-0001',  7, 'masuk',  '2026-10-10',  12,    85000, 'Stok awal'),
('IN-20261012-0001',  8, 'masuk',  '2026-10-12',  25,    22000, 'Stok awal'),
('OUT-20261015-0001', 1, 'keluar', '2026-10-15',   3, 12750000, 'Pengiriman ke PT Sinar Jaya'),
('OUT-20261020-0001', 2, 'keluar', '2026-10-20',  40,    52000, 'Permintaan divisi Keuangan'),
('OUT-20261025-0001', 4, 'keluar', '2026-10-25', 200,     8500, 'Packing pesanan online'),
('IN-20261102-0001',  1, 'masuk',  '2026-11-02',   5, 11500000, 'Pembelian dari PT Datascrip'),
('OUT-20261103-0001', 5, 'keluar', '2026-11-03',  60,    12000, 'Packing pesanan online'),
('IN-20261105-0001',  2, 'masuk',  '2026-11-05',  50,    45000, 'Supplier CV Kertas Nusantara'),
('OUT-20261108-0001', 3, 'keluar', '2026-11-08',   3,   780000, 'Proyek renovasi cabang Bekasi'),
('OUT-20261109-0001', 6, 'keluar', '2026-11-09',  22,   175000, 'Pengadaan kantor cabang Bandung'),
('OUT-20261110-0001', 1, 'keluar', '2026-11-10',   2, 12750000, 'Pengiriman ke CV Maju Bersama'),
('OUT-20261111-0001', 4, 'keluar', '2026-11-11', 120,     8500, 'Packing pesanan online'),
('OUT-20261112-0001', 2, 'keluar', '2026-11-12',  15,    52000, 'Permintaan divisi HRD'),
('OUT-20261113-0001', 7, 'keluar', '2026-11-13',  10,    99000, 'Kebutuhan kebersihan gedung A');
