<?php
session_start();
if (empty($_SESSION['username'])) {
  header('location: ../../login.php?pesan=2');
  exit;
}
require_once '../../config/database.php';
require_once __DIR__ . '/schema.php';
inventory_ensure_schema($mysqli);

function inventory_redirect($query, $metadata = array())
{
  if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
    parse_str($query, $params);
    $success = isset($params['pesan']) && $params['pesan'] === 'sukses';
    http_response_code($success ? 200 : 422);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(array_merge(array(
      'ok' => $success,
      'message' => $success ? 'Aksi berhasil diproses.' : (isset($params['detail']) ? $params['detail'] : 'Aksi tidak dapat diproses.'),
      'gudang_id' => isset($params['gudang_id']) ? (int) $params['gudang_id'] : 0
    ), $metadata));
    exit;
  }
  header('location: ../../main.php?module=inventory&' . $query);
  exit;
}

function inventory_post($name)
{
  return isset($_POST[$name]) ? trim($_POST[$name]) : '';
}

$action = isset($_REQUEST['aksi']) ? $_REQUEST['aksi'] : '';
$allowedCategories = array('APD/Safety', 'Tools', 'Consumable', 'Elektronik');
$transactionStarted = false;

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
    $warehouseId = mysqli_insert_id($mysqli);
    inventory_redirect('gudang_id=' . $warehouseId . '&pesan=sukses', array('gudang_id' => $warehouseId));
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
    $goodInput = str_replace(',', '.', inventory_post('qty_baik'));
    $damagedInput = str_replace(',', '.', inventory_post('qty_rusak'));
    $goodQty = filter_var($goodInput, FILTER_VALIDATE_FLOAT);
    $damagedQty = filter_var($damagedInput, FILTER_VALIDATE_FLOAT);
    $qty = $goodQty !== false && $damagedQty !== false ? $goodQty + $damagedQty : false;
    $unit = inventory_post('satuan');
    $category = inventory_post('kategori');
    $takenDate = inventory_post('tanggal_pengambilan');
    $receivedDate = inventory_post('tanggal_masuk');
    $note = inventory_post('keterangan');
    if (!$warehouseId || $warehouseId < 1 || $name === '' || $qty === false || $goodQty < 0 || $damagedQty < 0 || $qty <= 0 || $unit === '' || !in_array($category, $allowedCategories, true)) {
      throw new InvalidArgumentException('Data item belum lengkap atau tidak valid.');
    }
    if ($takenDate !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $takenDate)) {
      throw new InvalidArgumentException('Format tanggal pengambilan tidak valid.');
    }
    if ($action === 'tambah_item' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $receivedDate)) {
      throw new InvalidArgumentException('Tanggal masuk wajib diisi dengan format yang valid.');
    }
    $takenDate = $takenDate === '' ? null : $takenDate;
    if ($action === 'tambah_item') {
      mysqli_begin_transaction($mysqli);
      $transactionStarted = true;
      $warehouseLock = mysqli_prepare($mysqli, 'SELECT id_gudang FROM tbl_gudang WHERE id_gudang = ? FOR UPDATE');
      mysqli_stmt_bind_param($warehouseLock, 'i', $warehouseId);
      mysqli_stmt_execute($warehouseLock);
      $warehouseResult = mysqli_stmt_get_result($warehouseLock);
      $warehouseExists = mysqli_fetch_assoc($warehouseResult);
      mysqli_stmt_close($warehouseLock);
      if (!$warehouseExists) {
        throw new InvalidArgumentException('Gudang tidak ditemukan.');
      }

      $existingStatement = mysqli_prepare($mysqli, 'SELECT id_inventory, kategori, satuan, qty, qty_baik, qty_rusak FROM tbl_inventory WHERE gudang_id = ? AND LOWER(TRIM(nama_item)) = LOWER(TRIM(?)) LIMIT 1 FOR UPDATE');
      mysqli_stmt_bind_param($existingStatement, 'is', $warehouseId, $name);
      mysqli_stmt_execute($existingStatement);
      $existingItem = mysqli_fetch_assoc(mysqli_stmt_get_result($existingStatement));
      mysqli_stmt_close($existingStatement);
      $userId = isset($_SESSION['id_user']) ? (int) $_SESSION['id_user'] : 0;
      if ($existingItem) {
        if ($existingItem['kategori'] !== $category || strcasecmp($existingItem['satuan'], $unit) !== 0) {
          throw new InvalidArgumentException('Nama item sudah ada, tetapi kategori atau satuannya berbeda. Gunakan data yang sama agar stok dapat digabung.');
        }
        $itemId = (int) $existingItem['id_inventory'];
        $newGoodQty = (float) $existingItem['qty_baik'] + $goodQty;
        $newDamagedQty = (float) $existingItem['qty_rusak'] + $damagedQty;
        $newQty = $newGoodQty + $newDamagedQty;
        $condition = $newDamagedQty > 0 && $newGoodQty <= 0 ? 'Rusak' : 'Baik';
        $updateStatement = mysqli_prepare($mysqli, 'UPDATE tbl_inventory SET qty = ?, qty_baik = ?, qty_rusak = ?, kondisi = ? WHERE id_inventory = ?');
        mysqli_stmt_bind_param($updateStatement, 'dddsi', $newQty, $newGoodQty, $newDamagedQty, $condition, $itemId);
        if (!mysqli_stmt_execute($updateStatement)) {
          throw new RuntimeException(mysqli_stmt_error($updateStatement));
        }
        mysqli_stmt_close($updateStatement);
      } else {
        $itemCondition = $damagedQty > 0 && $goodQty <= 0 ? 'Rusak' : 'Baik';
        $insertStatement = mysqli_prepare($mysqli, 'INSERT INTO tbl_inventory (gudang_id, nama_item, kategori, qty, qty_baik, qty_rusak, satuan, kondisi, tanggal_pengambilan, keterangan, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
        mysqli_stmt_bind_param($insertStatement, 'issdddssssi', $warehouseId, $name, $category, $qty, $goodQty, $damagedQty, $unit, $itemCondition, $takenDate, $note, $userId);
        $inserted = mysqli_stmt_execute($insertStatement);
        if (!$inserted) {
          $error = mysqli_stmt_error($insertStatement);
          mysqli_stmt_close($insertStatement);
          throw new RuntimeException($error);
        }
        $itemId = mysqli_insert_id($mysqli);
        mysqli_stmt_close($insertStatement);
      }

      $historyStatement = mysqli_prepare($mysqli, 'INSERT INTO tbl_inventory_riwayat (inventory_id, tanggal_masuk, qty_baik, qty_rusak, keterangan, created_by) VALUES (?, ?, ?, ?, ?, ?)');
      mysqli_stmt_bind_param($historyStatement, 'isddsi', $itemId, $receivedDate, $goodQty, $damagedQty, $note, $userId);
      if (!mysqli_stmt_execute($historyStatement)) {
        throw new RuntimeException(mysqli_stmt_error($historyStatement));
      }
      mysqli_stmt_close($historyStatement);
      mysqli_commit($mysqli);
      $transactionStarted = false;
    } else {
      $itemId = filter_var(inventory_post('id_inventory'), FILTER_VALIDATE_INT);
      if (!$itemId || $itemId < 1) {
        throw new InvalidArgumentException('Item tidak valid.');
      }
      $duplicateStatement = mysqli_prepare($mysqli, 'SELECT id_inventory FROM tbl_inventory WHERE gudang_id = ? AND LOWER(TRIM(nama_item)) = LOWER(TRIM(?)) AND id_inventory <> ? LIMIT 1');
      mysqli_stmt_bind_param($duplicateStatement, 'isi', $warehouseId, $name, $itemId);
      mysqli_stmt_execute($duplicateStatement);
      $duplicate = mysqli_fetch_assoc(mysqli_stmt_get_result($duplicateStatement));
      mysqli_stmt_close($duplicateStatement);
      if ($duplicate) {
        throw new InvalidArgumentException('Nama item sudah digunakan di gudang ini. Ubah stok item yang sudah ada.');
      }
      $condition = $damagedQty > 0 && $goodQty <= 0 ? 'Rusak' : 'Baik';
      $statement = mysqli_prepare($mysqli, 'UPDATE tbl_inventory SET gudang_id = ?, nama_item = ?, kategori = ?, qty = ?, qty_baik = ?, qty_rusak = ?, satuan = ?, kondisi = ?, tanggal_pengambilan = ?, keterangan = ? WHERE id_inventory = ?');
      mysqli_stmt_bind_param($statement, 'issdddssssi', $warehouseId, $name, $category, $qty, $goodQty, $damagedQty, $unit, $condition, $takenDate, $note, $itemId);
      if (!mysqli_stmt_execute($statement)) {
        $message = mysqli_stmt_error($statement);
        mysqli_stmt_close($statement);
        throw new RuntimeException($message);
      }
      mysqli_stmt_close($statement);
    }
    inventory_redirect('gudang_id=' . $warehouseId . '&pesan=sukses', array('item_id' => $itemId, 'nama_item' => $name));
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
    inventory_redirect('gudang_id=' . ($warehouseId ? $warehouseId : 0) . '&pesan=sukses', array('item_id' => $id));
  }
  throw new InvalidArgumentException('Aksi inventory tidak dikenali.');
} catch (Throwable $exception) {
  if ($transactionStarted) {
    mysqli_rollback($mysqli);
  }
  inventory_redirect('pesan=error&detail=' . rawurlencode($exception->getMessage()));
}
