-- phpMyAdmin SQL Dump
-- version 5.0.2
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Waktu pembuatan: 16 Jul 2026 pada 10:00
-- Versi server: 10.4.13-MariaDB
-- Versi PHP: 7.4.8

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `rkfvbtuu_gudang`
--

CREATE DATABASE IF NOT EXISTS `rkfvbtuu_gudang`;
USE `rkfvbtuu_gudang`;

SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS `tbl_barang_keluar_header`;
DROP TABLE IF EXISTS `tbl_barang_keluar_detail`;
DROP TABLE IF EXISTS `rkfvbtuu_gudang1`;
DROP TABLE IF EXISTS `tbl_barang_keluar`;
DROP TABLE IF EXISTS `tbl_barang_masuk`;
DROP TABLE IF EXISTS `tbl_penyesuaian_stok`;
DROP TABLE IF EXISTS `tbl_barang`;
DROP TABLE IF EXISTS `tbl_jenis`;
DROP TABLE IF EXISTS `tbl_satuan`;
DROP TABLE IF EXISTS `tbl_user`;
SET FOREIGN_KEY_CHECKS = 1;

-- --------------------------------------------------------

--
-- Struktur dari tabel `tbl_barang`
--

CREATE TABLE `tbl_barang` (
  `id_barang` varchar(5) NOT NULL,
  `nama_barang` varchar(100) NOT NULL,
  `jenis` int(11) NOT NULL,
  `stok_minimum` int(11) NOT NULL,
  `stok` int(11) NOT NULL DEFAULT 0,
  `satuan` int(11) NOT NULL,
  `foto` varchar(100) DEFAULT NULL,
  PRIMARY KEY (`id_barang`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Struktur dari tabel `tbl_barang_masuk`
--

CREATE TABLE `tbl_barang_masuk` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `id_transaksi` varchar(10) NOT NULL,
  `tanggal` date NOT NULL,
  `barang` varchar(5) NOT NULL,
  `jumlah` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `id_transaksi` (`id_transaksi`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

DELIMITER $$
CREATE TRIGGER `hapus_stok_masuk` BEFORE DELETE ON `tbl_barang_masuk`
FOR EACH ROW BEGIN
  UPDATE tbl_barang
  SET stok = stok - OLD.jumlah
  WHERE id_barang = OLD.barang;
END$$
DELIMITER ;

DELIMITER $$
CREATE TRIGGER `stok_masuk` AFTER INSERT ON `tbl_barang_masuk`
FOR EACH ROW BEGIN
  UPDATE tbl_barang
  SET stok = stok + NEW.jumlah
  WHERE id_barang = NEW.barang;
END$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Struktur dari tabel `tbl_barang_keluar`
--

CREATE TABLE `tbl_barang_keluar` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `id_transaksi` varchar(10) NOT NULL,
  `tanggal` date NOT NULL,
  `barang` varchar(5) NOT NULL,
  `jumlah` int(11) DEFAULT NULL,
  `keterangan` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `id_transaksi` (`id_transaksi`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

DELIMITER $$
CREATE TRIGGER `hapus_stok_keluar` BEFORE DELETE ON `tbl_barang_keluar`
FOR EACH ROW BEGIN
  UPDATE tbl_barang
  SET stok = stok + OLD.jumlah
  WHERE id_barang = OLD.barang;
END$$
DELIMITER ;

DELIMITER $$
CREATE TRIGGER `stok_keluar` AFTER INSERT ON `tbl_barang_keluar`
FOR EACH ROW BEGIN
  UPDATE tbl_barang
  SET stok = stok - NEW.jumlah
  WHERE id_barang = NEW.barang;
END$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Struktur dari tabel `tbl_penyesuaian_stok`
--

CREATE TABLE `tbl_penyesuaian_stok` (
  `id_penyesuaian` varchar(10) NOT NULL,
  `tanggal` date NOT NULL,
  `barang` varchar(5) NOT NULL,
  `stok_awal` int(11) NOT NULL,
  `stok_baru` int(11) NOT NULL,
  `selisih` int(11) NOT NULL,
  `keterangan` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id_penyesuaian`),
  KEY `barang` (`barang`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Struktur dari tabel `tbl_jenis`
--

CREATE TABLE `tbl_jenis` (
  `id_jenis` int(11) NOT NULL AUTO_INCREMENT,
  `nama_jenis` varchar(50) NOT NULL,
  PRIMARY KEY (`id_jenis`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Struktur dari tabel `tbl_satuan`
--

CREATE TABLE `tbl_satuan` (
  `id_satuan` int(11) NOT NULL AUTO_INCREMENT,
  `nama_satuan` varchar(30) NOT NULL,
  PRIMARY KEY (`id_satuan`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Struktur dari tabel `tbl_user`
--

CREATE TABLE `tbl_user` (
  `id_user` int(11) NOT NULL AUTO_INCREMENT,
  `nama_user` varchar(30) NOT NULL,
  `username` varchar(30) NOT NULL,
  `password` varchar(255) NOT NULL,
  `hak_akses` enum('Administrator','Admin Gudang','Kepala Gudang') NOT NULL,
  PRIMARY KEY (`id_user`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

--
-- Dumping data untuk tabel `tbl_user`
--

INSERT INTO `tbl_user` (`id_user`, `nama_user`, `username`, `password`, `hak_akses`) VALUES
(1, 'Admin', 'administrator', '$2y$12$Yi/I5f1jPoQNQnh6lWoVfuz.RtZ3OHcKN6PU.I62P0fYK1tJ7xMRi', 'Administrator'),
(2, 'Admin Gudang', 'admin gudang', '$2y$12$BeRYh13zfPXej97VgcfeNucYJGTElha5sRyIUQm1278D2u2Aqf6DS', 'Admin Gudang'),
(3, 'Kepala Gudang', 'kepala gudang', '$2y$12$odXcPs.RLJJH6Ghv3s42c.5zg5qAOz/S3Adr0lXGNcVSJ6f1hHS6G', 'Kepala Gudang');

-- --------------------------------------------------------
-- Struktur Data Inventory Gudang
-- --------------------------------------------------------

CREATE TABLE IF NOT EXISTS `tbl_gudang` (
  `id_gudang` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `kode_gudang` varchar(20) NOT NULL,
  `nama_gudang` varchar(100) NOT NULL,
  `penanggung_jawab` varchar(100) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id_gudang`),
  UNIQUE KEY `uq_gudang_kode` (`kode_gudang`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `tbl_inventory` (
  `id_inventory` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `gudang_id` int(10) unsigned NOT NULL,
  `nama_item` varchar(150) NOT NULL,
  `kategori` varchar(30) NOT NULL,
  `qty` decimal(12,2) NOT NULL DEFAULT 0.00,
  `satuan` varchar(40) NOT NULL,
  `kondisi` enum('Baik','Rusak') NOT NULL DEFAULT 'Baik',
  `tanggal_pengambilan` date DEFAULT NULL,
  `keterangan` text DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id_inventory`),
  KEY `idx_inventory_gudang` (`gudang_id`),
  KEY `idx_inventory_kategori` (`kategori`),
  CONSTRAINT `fk_inventory_gudang` FOREIGN KEY (`gudang_id`) REFERENCES `tbl_gudang` (`id_gudang`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `tbl_gudang` (`kode_gudang`, `nama_gudang`, `penanggung_jawab`)
SELECT 'G-001', 'Gudang Utama', 'Belum ditentukan'
WHERE NOT EXISTS (SELECT 1 FROM `tbl_gudang` LIMIT 1);
INSERT INTO `tbl_gudang` (`kode_gudang`, `nama_gudang`, `penanggung_jawab`)
SELECT 'G-002', 'Gudang APD & Safety', 'Belum ditentukan'
WHERE (SELECT COUNT(*) FROM `tbl_gudang`) = 1;
INSERT INTO `tbl_gudang` (`kode_gudang`, `nama_gudang`, `penanggung_jawab`)
SELECT 'G-003', 'Gudang Elektronik', 'Belum ditentukan'
WHERE (SELECT COUNT(*) FROM `tbl_gudang`) = 2;

COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
