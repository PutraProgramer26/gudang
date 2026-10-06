<?php
function surat_jalan_ensure_schema($mysqli)
{
  $queries = array(
    "CREATE TABLE IF NOT EXISTS tbl_surat_jalan_settings (
      id tinyint unsigned NOT NULL,
      header_title varchar(160) NOT NULL DEFAULT 'SURAT JALAN',
      header_subtitle varchar(255) NOT NULL DEFAULT '',
      nomor_prefix varchar(30) NOT NULL DEFAULT 'SJ',
      next_number int unsigned NOT NULL DEFAULT 1,
      number_digits tinyint unsigned NOT NULL DEFAULT 5,
      logo_path varchar(255) DEFAULT NULL,
      updated_at timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
      PRIMARY KEY (id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
    "CREATE TABLE IF NOT EXISTS tbl_surat_jalan (
      id int unsigned NOT NULL AUTO_INCREMENT,
      nomor_surat varchar(80) NOT NULL,
      tanggal date NOT NULL,
      pemohon varchar(120) NOT NULL,
      driver varchar(120) NOT NULL,
      manager varchar(120) NOT NULL,
      tujuan varchar(255) NOT NULL DEFAULT '',
      dibuat_oleh varchar(120) NOT NULL,
      dibuat_pada timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
      PRIMARY KEY (id),
      UNIQUE KEY nomor_surat (nomor_surat),
      KEY tanggal (tanggal)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
    "CREATE TABLE IF NOT EXISTS tbl_surat_jalan_detail (
      id int unsigned NOT NULL AUTO_INCREMENT,
      surat_jalan_id int unsigned NOT NULL,
      nama_barang varchar(160) NOT NULL,
      jumlah decimal(12,2) NOT NULL,
      keterangan varchar(255) DEFAULT NULL,
      PRIMARY KEY (id),
      KEY surat_jalan_id (surat_jalan_id),
      CONSTRAINT fk_surat_jalan_detail_header FOREIGN KEY (surat_jalan_id)
        REFERENCES tbl_surat_jalan (id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
  );

  foreach ($queries as $query) {
    if (!mysqli_query($mysqli, $query)) {
      throw new RuntimeException('Gagal menyiapkan tabel surat jalan: ' . mysqli_error($mysqli));
    }
  }

  if (!mysqli_query($mysqli, "INSERT IGNORE INTO tbl_surat_jalan_settings (id) VALUES (1)")) {
    throw new RuntimeException('Gagal menyiapkan pengaturan surat jalan: ' . mysqli_error($mysqli));
  }
}