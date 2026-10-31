<?php
session_start();
if (empty($_SESSION['username'])) {
  header('location: ../../login.php?pesan=2');
  exit;
}
require_once '../../config/database.php';
require_once __DIR__ . '/schema.php';
inventory_ensure_schema($mysqli);

function inventory_redirect($query)
{
  header('location: ../../main.php?module=inventory&' . $query);
  exit;
}

function inventory_post($name)
{
  return isset($_POST[$name]) ? trim($_POST[$name]) : '';
}

$action = isset($_REQUEST['aksi']) ? $_REQUEST['aksi'] : '';
$allowedCategories = array('APD/Safety', 'Tools', 'Consumable', 'Elektronik');
$allowedConditions = array('Baik', 'Rusak');

try {
  if ($action === 'tambah_gudang') {
    $code = strtoupper(inventory_post('kode_gudang'));
    $name = inventory_post('nama_gudang');
    $person = inventory_post('penanggung_jawab');
    if ($code === '' || $name === '' || $person === '') {
      throw new InvalidArgumentException('Semua data gudang wajib diisi.');
    }
    $statement = mysqli_prepare($mysqli, 'INSERT INTO tbl_gudang (kode_gudang, nama_gudang, penanggung_jawab) VALUES (?, ?, ?)');
    mysqli_stmt_bind_param($statement, 'sss', $code, $name, $person);
    if (!mysqli_stmt_execute($statement)) {
      $message = mysqli_stmt_errno($statement) === 1062 ? 'Kode gudang sudah digunakan.' : mysqli_stmt_error($statement);
      mysqli_stmt_close($statement);
      throw new RuntimeException($message);
    }
    mysqli_stmt_close($statement);
    inventory_redirect('pesan=sukses');
  }

  if ($action === 'hapus_gudang') {
    $id = filter_var(isset($_GET['id']) ? $_GET['id'] : 0, FILTER_VALIDATE_INT);
    if (!$id || $id < 1) {
      throw new InvalidArgumentException('Gudang tidak valid.');
    }
    $countResult = mysqli_query($mysqli, 'SELECT COUNT(*) AS total FROM tbl_gudang');
    $count = mysqli_fetch_assoc($countResult);
    if ((int) $count['total'] <= 1) {
      throw new RuntimeException('Minimal harus ada satu gudang.');
    }
    $statement = mysqli_prepare($mysqli, 'DELETE FROM tbl_gudang WHERE id_gudang = ?');
    mysqli_stmt_bind_param($statement, 'i', $id);
    mysqli_stmt_execute($statement);
    mysqli_stmt_close($statement);
    inventory_redirect('pesan=sukses');
  }

  if (in_array($action, array('tambah_item', 'ubah_item'), true)) {
    $warehouseId = filter_var(inventory_post('gudang_id'), FILTER_VALIDATE_INT);
    $name = inventory_post('nama_item');
    $qty = filter_var(inventory_post('qty'), FILTER_VALIDATE_FLOAT);
    $unit = inventory_post('satuan');
    $category = inventory_post('kategori');
    $condition = inventory_post('kondisi');
    $takenDate = inventory_post('tanggal_pengambilan');
    $note = inventory_post('keterangan');
    if (!$warehouseId || $warehouseId < 1 || $name === '' || $qty === false || $qty < 0 || $unit === '' || !in_array($category, $allowedCategories, true) || !in_array($condition, $allowedConditions, true)) {
      throw new InvalidArgumentException('Data item belum lengkap atau tidak valid.');
    }
    if ($takenDate !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $takenDate)) {
      throw new InvalidArgumentException('Format tanggal pengambilan tidak valid.');
    }
    if ($action === 'tambah_item') {
      $statement = mysqli_prepare($mysqli, 'INSERT INTO tbl_inventory (gudang_id, nama_item, kategori, qty, satuan, kondisi, tanggal_pengambilan, keterangan, created_by) VALUES (?, ?, ?, ?, ?, ?, NULLIF(?, ""), ?, ?)');
      $userId = isset($_SESSION['id_user']) ? (int) $_SESSION['id_user'] : 0;
      mysqli_stmt_bind_param($statement, 'issdssssi', $warehouseId, $name, $category, $qty, $unit, $condition, $takenDate, $note, $userId);
    } else {
      $itemId = filter_var(inventory_post('id_inventory'), FILTER_VALIDATE_INT);
      if (!$itemId || $itemId < 1) {
        throw new InvalidArgumentException('Item tidak valid.');
      }
      $statement = mysqli_prepare($mysqli, 'UPDATE tbl_inventory SET gudang_id = ?, nama_item = ?, kategori = ?, qty = ?, satuan = ?, kondisi = ?, tanggal_pengambilan = NULLIF(?, ""), keterangan = ? WHERE id_inventory = ?');
      mysqli_stmt_bind_param($statement, 'issdssssi', $warehouseId, $name, $category, $qty, $unit, $condition, $takenDate, $note, $itemId);
    }
    if (!mysqli_stmt_execute($statement)) {
      $message = mysqli_stmt_error($statement);
      mysqli_stmt_close($statement);
      throw new RuntimeException($message);
    }
    mysqli_stmt_close($statement);
    inventory_redirect('gudang_id=' . $warehouseId . '&pesan=sukses');
  }

  if ($action === 'hapus_item') {
    $id = filter_var(isset($_GET['id']) ? $_GET['id'] : 0, FILTER_VALIDATE_INT);
    $warehouseId = filter_var(isset($_GET['gudang_id']) ? $_GET['gudang_id'] : 0, FILTER_VALIDATE_INT);
    if (!$id || $id < 1) {
      throw new InvalidArgumentException('Item tidak valid.');
    }
    $statement = mysqli_prepare($mysqli, 'DELETE FROM tbl_inventory WHERE id_inventory = ?');
    mysqli_stmt_bind_param($statement, 'i', $id);
    mysqli_stmt_execute($statement);
    mysqli_stmt_close($statement);
    inventory_redirect('gudang_id=' . ($warehouseId ? $warehouseId : 0) . '&pesan=sukses');
  }
  throw new InvalidArgumentException('Aksi inventory tidak dikenali.');
} catch (Throwable $exception) {
  inventory_redirect('pesan=error&detail=' . rawurlencode($exception->getMessage()));
}
