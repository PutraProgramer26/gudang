<?php
session_start();
if (empty($_SESSION['username'])) {
  header('location: ../../login.php?pesan=2');
  exit;
}

require_once dirname(__DIR__, 2) . '/config/database.php';
require_once __DIR__ . '/schema.php';
spp_ensure_schema($mysqli);

function spp_redirect_error($message, $projectId = null)
{
  $url = '../../main.php?module=spp&error=' . urlencode($message);
  if ($projectId && (int) $projectId > 0) {
    $url .= '&project_id=' . (int) $projectId;
  }
  header('location: ' . $url);
  exit;
}

function spp_is_manager()
{
  return isset($_SESSION['hak_akses']) && in_array($_SESSION['hak_akses'], array('Administrator', 'Admin Gudang'), true);
}

function spp_parse_quantity($value)
{
  if (!is_string($value) && !is_numeric($value)) {
    return false;
  }
  $value = trim((string) $value);
  if (!preg_match('/^\d+(?:[.,]\d{1,3})?$/', $value)) {
    return false;
  }
  return (float) str_replace(',', '.', $value);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_SESSION['csrf_spp']) || empty($_POST['csrf_token']) ||
    !hash_equals($_SESSION['csrf_spp'], $_POST['csrf_token'])) {
  spp_redirect_error('Permintaan tidak valid. Muat ulang halaman dan coba lagi.');
}

$action = isset($_POST['aksi']) ? $_POST['aksi'] : '';
if (!spp_is_manager()) {
  spp_redirect_error('Role Anda tidak memiliki izin untuk mengubah data SPP.');
}

if ($action === 'buat_project') {
  $projectName = trim(isset($_POST['nama_project']) ? $_POST['nama_project'] : '');
  if ($projectName === '' || strlen($projectName) > 160) {
    spp_redirect_error('Nama project wajib diisi dan maksimal 160 karakter.');
  }
  $statement = mysqli_prepare($mysqli, 'INSERT INTO tbl_spp_project (nama_project, dibuat_oleh) VALUES (?, ?)');
  if (!$statement) {
    spp_redirect_error('Project tidak dapat disimpan.');
  }
  $username = $_SESSION['username'];
  mysqli_stmt_bind_param($statement, 'ss', $projectName, $username);
  $saved = mysqli_stmt_execute($statement);
  $newProjectId = mysqli_insert_id($mysqli);
  $errorCode = mysqli_stmt_errno($statement);
  mysqli_stmt_close($statement);
  if (!$saved) {
    spp_redirect_error($errorCode === 1062 ? 'Nama project tersebut sudah terdaftar.' : 'Project tidak dapat disimpan.');
  }
  header('location: ../../main.php?module=spp&project_id=' . (int) $newProjectId . '&pesan=1');
  exit;
}

if ($action === 'buat_spp') {
  $projectId = filter_var(isset($_POST['project_id']) ? $_POST['project_id'] : null, FILTER_VALIDATE_INT);
  $number = trim(isset($_POST['nomor_spp']) ? $_POST['nomor_spp'] : '');
  $date = isset($_POST['tanggal']) ? $_POST['tanggal'] : '';
  $dateObject = DateTime::createFromFormat('!Y-m-d', $date);
  if (!$projectId || $projectId < 1 || $number === '' || strlen($number) > 80 ||
      !preg_match('~^[A-Za-z0-9-]+(?:/[A-Za-z0-9-]+){2,4}$~', $number) ||
      !$dateObject || $dateObject->format('Y-m-d') !== $date) {
    spp_redirect_error('Pilih project dan masukkan nomor SPP serta tanggal dengan format yang valid.', $projectId);
  }

  $projectStatement = mysqli_prepare($mysqli, 'SELECT id FROM tbl_spp_project WHERE id = ?');
  mysqli_stmt_bind_param($projectStatement, 'i', $projectId);
  mysqli_stmt_execute($projectStatement);
  $projectResult = mysqli_stmt_get_result($projectStatement);
  $projectExists = mysqli_fetch_assoc($projectResult);
  mysqli_stmt_close($projectStatement);
  if (!$projectExists) {
    spp_redirect_error('Project tidak ditemukan. Pilih project yang tersedia.');
  }

  $names = isset($_POST['nama_item']) && is_array($_POST['nama_item']) ? $_POST['nama_item'] : array();
  $specifications = isset($_POST['spesifikasi']) && is_array($_POST['spesifikasi']) ? $_POST['spesifikasi'] : array();
  $quantities = isset($_POST['jumlah_diminta']) && is_array($_POST['jumlah_diminta']) ? $_POST['jumlah_diminta'] : array();
  $units = isset($_POST['satuan']) && is_array($_POST['satuan']) ? $_POST['satuan'] : array();
  $workList = isset($_POST['untuk_pekerjaan']) && is_array($_POST['untuk_pekerjaan']) ? $_POST['untuk_pekerjaan'] : array();
  $notes = isset($_POST['keterangan']) && is_array($_POST['keterangan']) ? $_POST['keterangan'] : array();
  $rowCount = count($names);
  if ($rowCount < 1 || $rowCount > 100 || count($specifications) !== $rowCount || count($quantities) !== $rowCount || count($units) !== $rowCount || count($workList) !== $rowCount || count($notes) !== $rowCount) {
    spp_redirect_error('SPP harus memiliki 1 sampai 100 item dengan seluruh kolom yang sesuai.', $projectId);
  }

  $items = array();
  for ($index = 0; $index < $rowCount; $index++) {
    $name = trim($names[$index]);
    $specification = trim($specifications[$index]);
    $quantity = spp_parse_quantity($quantities[$index]);
    $unit = trim($units[$index]);
    $work = trim($workList[$index]);
    $note = trim($notes[$index]);
    if ($name === '' || strlen($name) > 160 || $quantity === false || $quantity <= 0 || $quantity > 99999999999.999 ||
        strlen($specification) > 255 || $unit === '' || strlen($unit) > 40 || strlen($work) > 160 || strlen($note) > 255) {
      spp_redirect_error('Periksa nama, jumlah, satuan, dan panjang keterangan setiap item.', $projectId);
    }
    $items[] = array('nama' => $name, 'spesifikasi' => $specification, 'jumlah' => $quantity, 'satuan' => $unit, 'pekerjaan' => $work, 'keterangan' => $note);
  }

  if (!mysqli_begin_transaction($mysqli)) {
    spp_redirect_error('Transaksi penyimpanan SPP tidak dapat dimulai.', $projectId);
  }
  try {
    $token = bin2hex(random_bytes(32));
    $username = $_SESSION['username'];
    $headerStatement = mysqli_prepare($mysqli, 'INSERT INTO tbl_spp (project_id, nomor_spp, tanggal, share_token, dibuat_oleh) VALUES (?, ?, ?, ?, ?)');
    if (!$headerStatement) {
      throw new RuntimeException('SPP tidak dapat disimpan.');
    }
    mysqli_stmt_bind_param($headerStatement, 'issss', $projectId, $number, $date, $token, $username);
    if (!mysqli_stmt_execute($headerStatement)) {
      $statementError = mysqli_stmt_errno($headerStatement);
      mysqli_stmt_close($headerStatement);
      throw new RuntimeException($statementError === 1062 ? 'Nomor SPP tersebut sudah digunakan.' : 'SPP tidak dapat disimpan.');
    }
    $sppId = mysqli_insert_id($mysqli);
    mysqli_stmt_close($headerStatement);

    $itemStatement = mysqli_prepare($mysqli, 'INSERT INTO tbl_spp_item (spp_id, nomor_item, nama_item, spesifikasi, jumlah_diminta, satuan, untuk_pekerjaan, keterangan) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
    if (!$itemStatement) {
      throw new RuntimeException('Rincian item SPP tidak dapat disimpan.');
    }
    foreach ($items as $index => $item) {
      $itemNumber = $index + 1;
      mysqli_stmt_bind_param($itemStatement, 'iissdsss', $sppId, $itemNumber, $item['nama'], $item['spesifikasi'], $item['jumlah'], $item['satuan'], $item['pekerjaan'], $item['keterangan']);
      if (!mysqli_stmt_execute($itemStatement)) {
        mysqli_stmt_close($itemStatement);
        throw new RuntimeException('Rincian item SPP tidak dapat disimpan.');
      }
    }
    mysqli_stmt_close($itemStatement);
    if (!mysqli_commit($mysqli)) {
      throw new RuntimeException('SPP tidak dapat diselesaikan di database.');
    }
  } catch (Exception $exception) {
    mysqli_rollback($mysqli);
    spp_redirect_error($exception->getMessage(), $projectId);
  }

  header('location: ../../main.php?module=spp&project_id=' . (int) $projectId . '&spp_id=' . (int) $sppId . '&pesan=2');
  exit;
}

if ($action === 'update_penerimaan') {
  $projectId = filter_var(isset($_POST['project_id']) ? $_POST['project_id'] : null, FILTER_VALIDATE_INT);
  $sppId = filter_var(isset($_POST['spp_id']) ? $_POST['spp_id'] : null, FILTER_VALIDATE_INT);
  $itemIds = isset($_POST['item_id']) && is_array($_POST['item_id']) ? $_POST['item_id'] : array();
  $receivedList = isset($_POST['jumlah_diterima']) && is_array($_POST['jumlah_diterima']) ? $_POST['jumlah_diterima'] : array();
  if (!$projectId || $projectId < 1 || !$sppId || $sppId < 1 || count($itemIds) < 1 || count($itemIds) !== count($receivedList) || count($itemIds) > 100) {
    spp_redirect_error('Data pembaruan penerimaan tidak valid.', $projectId);
  }
  if (!mysqli_begin_transaction($mysqli)) {
    spp_redirect_error('Transaksi pembaruan tidak dapat dimulai.', $projectId);
  }
  try {
    $headerStatement = mysqli_prepare($mysqli, 'SELECT id FROM tbl_spp WHERE id = ? AND project_id = ? FOR UPDATE');
    mysqli_stmt_bind_param($headerStatement, 'ii', $sppId, $projectId);
    mysqli_stmt_execute($headerStatement);
    $headerResult = mysqli_stmt_get_result($headerStatement);
    $headerExists = mysqli_fetch_assoc($headerResult);
    mysqli_stmt_close($headerStatement);
    if (!$headerExists) {
      throw new RuntimeException('SPP tidak ditemukan pada project yang dipilih.');
    }

    $itemStatement = mysqli_prepare($mysqli, 'SELECT jumlah_diminta FROM tbl_spp_item WHERE id = ? AND spp_id = ? FOR UPDATE');
    $updateStatement = mysqli_prepare($mysqli, 'UPDATE tbl_spp_item SET jumlah_diterima = ? WHERE id = ? AND spp_id = ?');
    if (!$itemStatement || !$updateStatement) {
      throw new RuntimeException('Data item SPP tidak dapat diproses.');
    }
    foreach ($itemIds as $index => $itemIdRaw) {
      $itemId = filter_var($itemIdRaw, FILTER_VALIDATE_INT);
      $received = spp_parse_quantity($receivedList[$index]);
      if (!$itemId || $itemId < 1 || $received === false || $received < 0) {
        throw new RuntimeException('Jumlah barang diterima harus nol atau lebih.');
      }
      mysqli_stmt_bind_param($itemStatement, 'ii', $itemId, $sppId);
      mysqli_stmt_execute($itemStatement);
      $itemResult = mysqli_stmt_get_result($itemStatement);
      $itemData = mysqli_fetch_assoc($itemResult);
      if (!$itemData || $received > (float) $itemData['jumlah_diminta']) {
        throw new RuntimeException('Jumlah diterima tidak boleh melebihi jumlah permintaan.');
      }
      mysqli_stmt_bind_param($updateStatement, 'dii', $received, $itemId, $sppId);
      if (!mysqli_stmt_execute($updateStatement)) {
        throw new RuntimeException('Jumlah penerimaan tidak berhasil disimpan.');
      }
    }
    mysqli_stmt_close($itemStatement);
    mysqli_stmt_close($updateStatement);
    if (!mysqli_commit($mysqli)) {
      throw new RuntimeException('Pembaruan penerimaan tidak dapat diselesaikan.');
    }
  } catch (Exception $exception) {
    mysqli_rollback($mysqli);
    spp_redirect_error($exception->getMessage(), $projectId);
  }
  header('location: ../../main.php?module=spp&project_id=' . (int) $projectId . '&spp_id=' . (int) $sppId . '&pesan=3');
  exit;
}

spp_redirect_error('Aksi SPP tidak dikenali.');