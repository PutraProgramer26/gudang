<?php
function surat_jalan_ensure_schema($mysqli)
{
  $queries = array(
    "CREATE TABLE IF NOT EXISTS tbl_surat_jalan_settings (
      id tinyint unsigned NOT NULL,
      template_path varchar(255) DEFAULT NULL,
      template_name varchar(180) DEFAULT NULL,
      template_fields longtext NOT NULL,
      table_config longtext NOT NULL,
      nomor_prefix varchar(40) NOT NULL DEFAULT 'SJ',
      next_number int unsigned NOT NULL DEFAULT 1,
      number_digits tinyint unsigned NOT NULL DEFAULT 5,
      updated_at timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
      PRIMARY KEY (id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
    "CREATE TABLE IF NOT EXISTS tbl_surat_jalan (
      id int unsigned NOT NULL AUTO_INCREMENT,
      nomor_surat varchar(100) NOT NULL,
      tanggal date NOT NULL,
      pemohon varchar(160) NOT NULL,
      data_fields longtext NOT NULL,
      template_name varchar(180) NOT NULL,
      pdf_path varchar(255) DEFAULT NULL,
      dibuat_oleh varchar(120) NOT NULL,
      dibuat_pada timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
      PRIMARY KEY (id),
      UNIQUE KEY nomor_surat (nomor_surat),
      KEY tanggal (tanggal)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
    "CREATE TABLE IF NOT EXISTS tbl_surat_jalan_detail (
      id int unsigned NOT NULL AUTO_INCREMENT,
      surat_jalan_id int unsigned NOT NULL,
      nomor_item int unsigned NOT NULL,
      nama_barang varchar(180) NOT NULL,
      jumlah decimal(14,3) NOT NULL,
      satuan varchar(50) NOT NULL DEFAULT '',
      keterangan varchar(255) NOT NULL DEFAULT '',
      PRIMARY KEY (id),
      KEY surat_jalan_id (surat_jalan_id),
      CONSTRAINT fk_surat_jalan_detail_header
        FOREIGN KEY (surat_jalan_id) REFERENCES tbl_surat_jalan (id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
  );
  foreach ($queries as $query) {
    if (!mysqli_query($mysqli, $query)) {
      throw new RuntimeException('Gagal menyiapkan tabel Surat Jalan: ' . mysqli_error($mysqli));
    }
    $columns = array(
      'tbl_surat_jalan_settings' => array(
        'template_path' => "ALTER TABLE tbl_surat_jalan_settings ADD COLUMN template_path varchar(255) DEFAULT NULL AFTER id",
        'template_name' => "ALTER TABLE tbl_surat_jalan_settings ADD COLUMN template_name varchar(180) DEFAULT NULL AFTER template_path",
        'template_fields' => "ALTER TABLE tbl_surat_jalan_settings ADD COLUMN template_fields longtext NOT NULL AFTER template_name",
        'table_config' => "ALTER TABLE tbl_surat_jalan_settings ADD COLUMN table_config longtext NOT NULL AFTER template_fields"
      ),
      'tbl_surat_jalan' => array(
        'data_fields' => "ALTER TABLE tbl_surat_jalan ADD COLUMN data_fields longtext NOT NULL AFTER pemohon",
        'template_name' => "ALTER TABLE tbl_surat_jalan ADD COLUMN template_name varchar(180) NOT NULL DEFAULT '' AFTER data_fields",
        'pdf_path' => "ALTER TABLE tbl_surat_jalan ADD COLUMN pdf_path varchar(255) DEFAULT NULL AFTER template_name"
      ),
      'tbl_surat_jalan_detail' => array(
        'nomor_item' => "ALTER TABLE tbl_surat_jalan_detail ADD COLUMN nomor_item int unsigned NOT NULL DEFAULT 1 AFTER surat_jalan_id",
        'satuan' => "ALTER TABLE tbl_surat_jalan_detail ADD COLUMN satuan varchar(50) NOT NULL DEFAULT '' AFTER jumlah"
      )
    );
    foreach ($columns as $table => $tableColumns) {
      foreach ($tableColumns as $column => $alterQuery) {
        $columnResult = mysqli_query($mysqli, "SHOW COLUMNS FROM {$table} LIKE '{$column}'");
        if (!$columnResult) {
          throw new RuntimeException('Gagal memeriksa struktur tabel Surat Jalan: ' . mysqli_error($mysqli));
        }
        if (mysqli_num_rows($columnResult) === 0 && !mysqli_query($mysqli, $alterQuery)) {
          throw new RuntimeException('Gagal memperbarui struktur tabel Surat Jalan: ' . mysqli_error($mysqli));
        }
      }
    }
  }
  if (!mysqli_query($mysqli, "INSERT IGNORE INTO tbl_surat_jalan_settings (id, template_fields, table_config) VALUES (1, '', '')")) {
    throw new RuntimeException('Gagal menyiapkan pengaturan Surat Jalan: ' . mysqli_error($mysqli));
  }
}
