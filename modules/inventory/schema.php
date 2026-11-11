<?php

function inventory_ensure_schema($mysqli)
{
  $tableResult = mysqli_query($mysqli, "SELECT COUNT(*) AS total FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tbl_inventory'");
  $tableExists = $tableResult && (int) mysqli_fetch_assoc($tableResult)['total'] > 0;
  $needsGoodMigration = false;
  $needsDamagedMigration = false;
  if ($tableExists) {
    $columnResult = mysqli_query($mysqli, "SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tbl_inventory'");
    $existingColumns = array();
    while ($column = mysqli_fetch_assoc($columnResult)) {
      $existingColumns[$column['COLUMN_NAME']] = true;
    }
    $needsGoodMigration = !isset($existingColumns['qty_baik']);
    $needsDamagedMigration = !isset($existingColumns['qty_rusak']);
  }
  $queries = array(
    "CREATE TABLE IF NOT EXISTS tbl_gudang (
      id_gudang INT UNSIGNED NOT NULL AUTO_INCREMENT,
      kode_gudang VARCHAR(20) NOT NULL,
      nama_gudang VARCHAR(100) NOT NULL,
      penanggung_jawab VARCHAR(100) NOT NULL,
      created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
      PRIMARY KEY (id_gudang),
      UNIQUE KEY uq_gudang_kode (kode_gudang)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
    "CREATE TABLE IF NOT EXISTS tbl_inventory (
      id_inventory INT UNSIGNED NOT NULL AUTO_INCREMENT,
      gudang_id INT UNSIGNED NOT NULL,
      nama_item VARCHAR(150) NOT NULL,
      kategori VARCHAR(30) NOT NULL,
      qty DECIMAL(12,2) NOT NULL DEFAULT 0,
      qty_baik DECIMAL(12,2) NOT NULL DEFAULT 0,
      qty_rusak DECIMAL(12,2) NOT NULL DEFAULT 0,
      satuan VARCHAR(40) NOT NULL,
      kondisi ENUM('Baik','Rusak') NOT NULL DEFAULT 'Baik',
      tanggal_pengambilan DATE NULL,
      keterangan TEXT NULL,
      created_by INT NULL,
      created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
      updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
      PRIMARY KEY (id_inventory),
      KEY idx_inventory_gudang (gudang_id),
      KEY idx_inventory_kategori (kategori),
      CONSTRAINT fk_inventory_gudang FOREIGN KEY (gudang_id) REFERENCES tbl_gudang (id_gudang) ON UPDATE CASCADE ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
    "CREATE TABLE IF NOT EXISTS tbl_inventory_riwayat (
      id_riwayat INT UNSIGNED NOT NULL AUTO_INCREMENT,
      inventory_id INT UNSIGNED NOT NULL,
      tanggal_masuk DATE NOT NULL,
      qty_baik DECIMAL(12,2) NOT NULL DEFAULT 0,
      qty_rusak DECIMAL(12,2) NOT NULL DEFAULT 0,
      keterangan TEXT NULL,
      created_by INT NULL,
      created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
      PRIMARY KEY (id_riwayat),
      KEY idx_inventory_riwayat_item (inventory_id, tanggal_masuk),
      CONSTRAINT fk_inventory_riwayat_item FOREIGN KEY (inventory_id) REFERENCES tbl_inventory (id_inventory) ON UPDATE CASCADE ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
  );

  foreach ($queries as $query) {
    if (!mysqli_query($mysqli, $query)) {
      throw new RuntimeException('Gagal menyiapkan tabel Data Inventory Gudang: ' . mysqli_error($mysqli));
    }
  }

  if (!mysqli_query($mysqli, "ALTER TABLE tbl_inventory ADD COLUMN IF NOT EXISTS kategori VARCHAR(30) NOT NULL DEFAULT 'Consumable' AFTER nama_item")) {
    throw new RuntimeException('Gagal memperbarui struktur tabel inventory: ' . mysqli_error($mysqli));
  }
  if (!mysqli_query($mysqli, "ALTER TABLE tbl_inventory ADD COLUMN IF NOT EXISTS qty_baik DECIMAL(12,2) NOT NULL DEFAULT 0 AFTER qty")) {
    throw new RuntimeException('Gagal menambahkan jumlah item baik: ' . mysqli_error($mysqli));
  }
  if (!mysqli_query($mysqli, "ALTER TABLE tbl_inventory ADD COLUMN IF NOT EXISTS qty_rusak DECIMAL(12,2) NOT NULL DEFAULT 0 AFTER qty_baik")) {
    throw new RuntimeException('Gagal menambahkan jumlah item rusak: ' . mysqli_error($mysqli));
  }

  if ($needsGoodMigration || $needsDamagedMigration) {
    $goodValue = $needsGoodMigration ? "IF(kondisi = 'Baik', qty, 0)" : 'qty_baik';
    $damagedValue = $needsDamagedMigration ? "IF(kondisi = 'Rusak', qty, 0)" : 'qty_rusak';
    if (!mysqli_query($mysqli, "UPDATE tbl_inventory SET qty_baik = $goodValue, qty_rusak = $damagedValue")) {
      throw new RuntimeException('Gagal memigrasikan kondisi item inventory: ' . mysqli_error($mysqli));
    }
  }

  $countResult = mysqli_query($mysqli, 'SELECT COUNT(*) AS total FROM tbl_gudang');
  if (!$countResult) {
    throw new RuntimeException('Gagal memeriksa data gudang: ' . mysqli_error($mysqli));
  }
  $count = mysqli_fetch_assoc($countResult);
  if ((int) $count['total'] === 0) {
    $seed = mysqli_prepare($mysqli, 'INSERT INTO tbl_gudang (kode_gudang, nama_gudang, penanggung_jawab) VALUES (?, ?, ?)');
    if (!$seed) {
      throw new RuntimeException('Gagal menyiapkan data awal gudang: ' . mysqli_error($mysqli));
    }
    $warehouses = array(
      array('G-001', 'Gudang Utama', 'Belum ditentukan'),
      array('G-002', 'Gudang APD & Safety', 'Belum ditentukan'),
      array('G-003', 'Gudang Elektronik', 'Belum ditentukan')
    );
    foreach ($warehouses as $warehouse) {
      mysqli_stmt_bind_param($seed, 'sss', $warehouse[0], $warehouse[1], $warehouse[2]);
      if (!mysqli_stmt_execute($seed)) {
        mysqli_stmt_close($seed);
        throw new RuntimeException('Gagal membuat data awal gudang: ' . mysqli_stmt_error($seed));
      }
    }
    mysqli_stmt_close($seed);
  }
}
