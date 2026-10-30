<?php
function jadwal_apd_ensure_schema($mysqli)
{
  $queries = array(
    "CREATE TABLE IF NOT EXISTS tbl_jadwal_apd (
      id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
      nama_karyawan VARCHAR(150) NOT NULL,
      site VARCHAR(120) NOT NULL,
      tanggal_pengambilan DATE NOT NULL,
      dibuat_oleh VARCHAR(120) NOT NULL,
      dibuat_pada TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
      PRIMARY KEY (id),
      KEY karyawan_site_tanggal (nama_karyawan, site, tanggal_pengambilan),
      KEY tanggal_pengambilan (tanggal_pengambilan)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
    "CREATE TABLE IF NOT EXISTS tbl_jadwal_apd_item (
      id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
      pengambilan_id BIGINT UNSIGNED NOT NULL,
      nama_apd VARCHAR(30) NOT NULL,
      ukuran VARCHAR(30) DEFAULT NULL,
      PRIMARY KEY (id),
      UNIQUE KEY pengambilan_apd (pengambilan_id, nama_apd),
      CONSTRAINT fk_jadwal_apd_item_pengambilan
        FOREIGN KEY (pengambilan_id) REFERENCES tbl_jadwal_apd (id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
  );

  foreach ($queries as $query) {
    if (!mysqli_query($mysqli, $query)) {
      throw new RuntimeException('Gagal menyiapkan tabel jadwal APD: ' . mysqli_error($mysqli));
    }
  }

  $columnResult = mysqli_query($mysqli, "SHOW COLUMNS FROM tbl_jadwal_apd_item LIKE 'ukuran'");
  if (!$columnResult) {
    throw new RuntimeException('Gagal memeriksa kolom ukuran APD: ' . mysqli_error($mysqli));
  }
  if (mysqli_num_rows($columnResult) === 0 &&
      !mysqli_query($mysqli, 'ALTER TABLE tbl_jadwal_apd_item ADD COLUMN ukuran VARCHAR(30) DEFAULT NULL AFTER nama_apd')) {
    throw new RuntimeException('Gagal menambahkan kolom ukuran APD: ' . mysqli_error($mysqli));
  }
}
