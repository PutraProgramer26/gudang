<?php
function spp_ensure_schema($mysqli)
{
  $queries = array(
    "CREATE TABLE IF NOT EXISTS tbl_spp_project (
      id int unsigned NOT NULL AUTO_INCREMENT,
      nama_project varchar(160) NOT NULL,
      dibuat_oleh varchar(120) NOT NULL,
      dibuat_pada timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
      PRIMARY KEY (id),
      UNIQUE KEY nama_project (nama_project)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
    "CREATE TABLE IF NOT EXISTS tbl_spp (
      id int unsigned NOT NULL AUTO_INCREMENT,
      project_id int unsigned NOT NULL,
      nomor_spp varchar(80) NOT NULL,
      tanggal date NOT NULL,
      share_token char(64) NOT NULL,
      dibuat_oleh varchar(120) NOT NULL,
      dibuat_pada timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
      diperbarui_pada timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
      PRIMARY KEY (id),
      UNIQUE KEY nomor_spp (nomor_spp),
      UNIQUE KEY share_token (share_token),
      KEY project_id (project_id),
      CONSTRAINT fk_spp_project FOREIGN KEY (project_id)
        REFERENCES tbl_spp_project (id) ON DELETE RESTRICT
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
    "CREATE TABLE IF NOT EXISTS tbl_spp_item (
      id int unsigned NOT NULL AUTO_INCREMENT,
      spp_id int unsigned NOT NULL,
      nomor_item int unsigned NOT NULL,
      nama_item varchar(160) NOT NULL,
      spesifikasi varchar(255) NOT NULL DEFAULT '',
      jumlah_diminta decimal(14,3) NOT NULL,
      jumlah_diterima decimal(14,3) NOT NULL DEFAULT 0,
      satuan varchar(40) NOT NULL,
      untuk_pekerjaan varchar(160) NOT NULL DEFAULT '',
      keterangan varchar(255) NOT NULL DEFAULT '',
      PRIMARY KEY (id),
      KEY spp_id (spp_id),
      CONSTRAINT fk_spp_item_header FOREIGN KEY (spp_id)
        REFERENCES tbl_spp (id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
  );

  foreach ($queries as $query) {
    if (!mysqli_query($mysqli, $query)) {
      throw new RuntimeException('Gagal menyiapkan tabel SPP: ' . mysqli_error($mysqli));
    }
  }
}