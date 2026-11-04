<?php
session_start();
if (empty($_SESSION['username'])) {
  header('location: ../../login.php?pesan=2');
  exit;
}
require_once dirname(__DIR__, 2) . '/config/database.php';
require_once __DIR__ . '/schema.php';
require_once __DIR__ . '/template.php';
surat_jalan_ensure_schema($mysqli);

function surat_jalan_redirect($message, $success = false)
{
  header('location: ../../main.php?module=surat_jalan&' . ($success ? 'pesan=' : 'error=') . urlencode($message));
  exit;
}
function surat_jalan_manager()
{
  return isset($_SESSION['hak_akses']) && in_array($_SESSION['hak_akses'], array('Administrator', 'Admin Gudang'), true);
}
function surat_jalan_load_items($mysqli, $documentId)
{
  $statement = mysqli_prepare($mysqli, 'SELECT nomor_item, nama_barang, jumlah, satuan, keterangan FROM tbl_surat_jalan_detail WHERE surat_jalan_id = ? ORDER BY nomor_item ASC');
  mysqli_stmt_bind_param($statement, 'i', $documentId);
  mysqli_stmt_execute($statement);
  $result = mysqli_stmt_get_result($statement);
  $items = array();
  while ($row = mysqli_fetch_assoc($result)) {
    $items[] = $row;
  }
  mysqli_stmt_close($statement);
  return $items;
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_SESSION['csrf_surat_jalan']) ||
    empty($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_surat_jalan'], $_POST['csrf_token'])) {
  surat_jalan_redirect('Permintaan tidak valid. Muat ulang halaman.');
}
if (!surat_jalan_manager()) {
  surat_jalan_redirect('Anda tidak memiliki izin mengelola Surat Jalan.');
}
$action = isset($_POST['aksi']) ? $_POST['aksi'] : '';
if (in_array($action, array('hapus', 'ubah'), true)) {
  $documentId = filter_var(isset($_POST['document_id']) ? $_POST['document_id'] : null, FILTER_VALIDATE_INT);
  if (!$documentId || $documentId < 1) {
    surat_jalan_redirect('Surat Jalan tidak valid.');
  }
  $documentStatement = mysqli_prepare($mysqli, 'SELECT * FROM tbl_surat_jalan WHERE id = ? LIMIT 1');
  mysqli_stmt_bind_param($documentStatement, 'i', $documentId);
  mysqli_stmt_execute($documentStatement);
  $document = mysqli_fetch_assoc(mysqli_stmt_get_result($documentStatement));
  mysqli_stmt_close($documentStatement);
  if (!$document) {
    surat_jalan_redirect('Surat Jalan tidak ditemukan.');
  }
  if ($action === 'hapus') {
    $delete = mysqli_prepare($mysqli, 'DELETE FROM tbl_surat_jalan WHERE id = ?');
    mysqli_stmt_bind_param($delete, 'i', $documentId);
    $deleted = mysqli_stmt_execute($delete);
    mysqli_stmt_close($delete);
    if (!$deleted) {
      surat_jalan_redirect('Surat Jalan gagal dihapus.');
    }
    $pdfPath = dirname(__DIR__, 2) . '/uploads/surat-jalan/' . basename((string) $document['pdf_path']);
    if (!empty($document['pdf_path']) && is_file($pdfPath)) {
      unlink($pdfPath);
    }
    surat_jalan_redirect('Surat Jalan berhasil dihapus.', true);
  }
  $settingsResult = mysqli_query($mysqli, 'SELECT * FROM tbl_surat_jalan_settings WHERE id = 1');
  $settings = mysqli_fetch_assoc($settingsResult);
  $tableConfig = surat_jalan_parse_json($settings['table_config'], array('table_index' => -1, 'columns' => array()));
  $tanggal = isset($_POST['tanggal']) ? $_POST['tanggal'] : '';
  $tanggalObject = DateTime::createFromFormat('!Y-m-d', $tanggal);
  $pemohon = trim(isset($_POST['pemohon']) ? $_POST['pemohon'] : '');
  if (!$tanggalObject || $tanggalObject->format('Y-m-d') !== $tanggal || $pemohon === '' || strlen($pemohon) > 160) {
    surat_jalan_redirect('Tanggal dan nama Pemohon wajib diisi dengan benar.');
  }
  $fieldValues = isset($_POST['field']) && is_array($_POST['field']) ? $_POST['field'] : array();
  $fields = surat_jalan_parse_json($settings['template_fields'], array());
  $cleanFields = array();
  foreach ($fields as $key => $label) {
    $value = isset($fieldValues[$key]) && is_string($fieldValues[$key]) ? trim($fieldValues[$key]) : '';
    if (strlen($value) > 500) {
      surat_jalan_redirect('Isi kolom ' . $label . ' terlalu panjang.');
    }
    $cleanFields[$key] = $value;
  }
  $names = isset($_POST['nama_barang']) && is_array($_POST['nama_barang']) ? $_POST['nama_barang'] : array();
  $quantities = isset($_POST['jumlah']) && is_array($_POST['jumlah']) ? $_POST['jumlah'] : array();
  $units = isset($_POST['satuan']) && is_array($_POST['satuan']) ? $_POST['satuan'] : array();
  $notes = isset($_POST['keterangan']) && is_array($_POST['keterangan']) ? $_POST['keterangan'] : array();
  $items = array();
  foreach ($names as $index => $name) {
    $name = trim((string) $name);
    $quantity = isset($quantities[$index]) ? str_replace(',', '.', trim((string) $quantities[$index])) : '';
    if ($name === '' && $quantity === '') {
      continue;
    }
    if ($name === '' || !preg_match('/^\d+(?:\.\d{1,3})?$/', $quantity) || (float) $quantity <= 0) {
      surat_jalan_redirect('Setiap barang harus memiliki nama dan jumlah yang valid.');
    }
    $items[] = array('nomor_item' => count($items) + 1, 'nama_barang' => substr($name, 0, 180), 'jumlah' => $quantity,
      'satuan' => substr(trim(isset($units[$index]) ? (string) $units[$index] : ''), 0, 50),
      'keterangan' => substr(trim(isset($notes[$index]) ? (string) $notes[$index] : ''), 0, 255));
  }
  if (count($items) < 1 || count($items) > 100) {
    surat_jalan_redirect('Surat Jalan harus memiliki 1 sampai 100 barang.');
  }
  $templateAbsolute = dirname(__DIR__, 2) . '/uploads/surat-jalan/' . basename($settings['template_path']);
  if (!is_file($templateAbsolute)) {
    surat_jalan_redirect('File template Word tidak ditemukan.');
  }
  $pdfFields = $cleanFields;
  $pdfFields['nomor_surat'] = $document['nomor_surat'];
  $pdfFields['tanggal'] = $tanggalObject->format('d-m-Y');
  $pdfFields['pemohon'] = $pemohon;
  $pdfContent = surat_jalan_docx_html($templateAbsolute, $pdfFields, $items, $tableConfig);
  $pdfFile = 'surat-jalan-' . preg_replace('/[^A-Za-z0-9_-]/', '-', $document['nomor_surat']) . '-' . bin2hex(random_bytes(5)) . '.pdf';
  $pdfAbsolute = dirname(__DIR__, 2) . '/uploads/surat-jalan/' . $pdfFile;
  if (file_put_contents($pdfAbsolute, surat_jalan_pdf_output($pdfContent), LOCK_EX) === false) {
    surat_jalan_redirect('PDF Surat Jalan gagal dibuat.');
  }
  mysqli_begin_transaction($mysqli);
  try {
    $dataFields = json_encode($cleanFields, JSON_UNESCAPED_UNICODE);
    $update = mysqli_prepare($mysqli, 'UPDATE tbl_surat_jalan SET tanggal = ?, pemohon = ?, data_fields = ?, pdf_path = ? WHERE id = ?');
    mysqli_stmt_bind_param($update, 'ssssi', $tanggal, $pemohon, $dataFields, $pdfFile, $documentId);
    if (!mysqli_stmt_execute($update)) {
      throw new RuntimeException('Surat Jalan gagal diperbarui.');
    }
    mysqli_stmt_close($update);
    $deleteItems = mysqli_prepare($mysqli, 'DELETE FROM tbl_surat_jalan_detail WHERE surat_jalan_id = ?');
    mysqli_stmt_bind_param($deleteItems, 'i', $documentId);
    mysqli_stmt_execute($deleteItems);
    mysqli_stmt_close($deleteItems);
    $detail = mysqli_prepare($mysqli, 'INSERT INTO tbl_surat_jalan_detail (surat_jalan_id, nomor_item, nama_barang, jumlah, satuan, keterangan) VALUES (?, ?, ?, ?, ?, ?)');
    foreach ($items as $item) {
      $itemNumber = $item['nomor_item'];
      $name = $item['nama_barang'];
      $quantity = (float) $item['jumlah'];
      $unit = $item['satuan'];
      $note = $item['keterangan'];
      mysqli_stmt_bind_param($detail, 'iisdss', $documentId, $itemNumber, $name, $quantity, $unit, $note);
      if (!mysqli_stmt_execute($detail)) {
        throw new RuntimeException('Rincian barang gagal diperbarui.');
      }
    }
    mysqli_stmt_close($detail);
    mysqli_commit($mysqli);
  } catch (Throwable $exception) {
    mysqli_rollback($mysqli);
    if (is_file($pdfAbsolute)) {
      unlink($pdfAbsolute);
    }
    surat_jalan_redirect($exception->getMessage());
  }
  $oldPdf = dirname(__DIR__, 2) . '/uploads/surat-jalan/' . basename((string) $document['pdf_path']);
  if (!empty($document['pdf_path']) && is_file($oldPdf)) {
    unlink($oldPdf);
  }
  surat_jalan_redirect('Surat Jalan berhasil diperbarui.', true);
}
if ($action === 'template') {
  if (!isset($_FILES['template']) || $_FILES['template']['error'] !== UPLOAD_ERR_OK ||
      $_FILES['template']['size'] > 10485760 || !is_uploaded_file($_FILES['template']['tmp_name'])) {
    surat_jalan_redirect('Template Word wajib diunggah dan maksimal berukuran 10 MB.');
  }
  $original = basename($_FILES['template']['name']);
  if (strtolower(pathinfo($original, PATHINFO_EXTENSION)) !== 'docx') {
    surat_jalan_redirect('Format template harus .docx.');
  }
  $uploadDir = dirname(__DIR__, 2) . '/uploads/surat-jalan';
  if (!is_dir($uploadDir) && !mkdir($uploadDir, 0750, true)) {
    surat_jalan_redirect('Folder template tidak dapat dibuat.');
  }
  $filename = 'template-' . bin2hex(random_bytes(12)) . '.docx';
  $absolute = $uploadDir . DIRECTORY_SEPARATOR . $filename;
  if (!move_uploaded_file($_FILES['template']['tmp_name'], $absolute)) {
    surat_jalan_redirect('Template Word gagal disimpan.');
  }
  try {
    $template = surat_jalan_read_template($absolute);
  } catch (RuntimeException $exception) {
    unlink($absolute);
    surat_jalan_redirect($exception->getMessage());
  }
  $old = mysqli_query($mysqli, 'SELECT template_path FROM tbl_surat_jalan_settings WHERE id = 1');
  $oldRow = mysqli_fetch_assoc($old);
  $statement = mysqli_prepare($mysqli, 'UPDATE tbl_surat_jalan_settings SET template_path = ?, template_name = ?, template_fields = ?, table_config = ? WHERE id = 1');
  $fields = json_encode($template['fields'], JSON_UNESCAPED_UNICODE);
  $tableConfig = json_encode($template['table_config'], JSON_UNESCAPED_UNICODE);
  mysqli_stmt_bind_param($statement, 'ssss', $filename, $original, $fields, $tableConfig);
  if (!mysqli_stmt_execute($statement)) {
    mysqli_stmt_close($statement);
    unlink($absolute);
    surat_jalan_redirect('Template Word tidak dapat disimpan ke database.');
  }
  mysqli_stmt_close($statement);
  if ($oldRow && !empty($oldRow['template_path'])) {
    $oldPath = $uploadDir . DIRECTORY_SEPARATOR . basename($oldRow['template_path']);
    if (is_file($oldPath)) {
      unlink($oldPath);
    }
  }
  surat_jalan_redirect('Template berhasil diunggah dan kolom otomatis berhasil dibaca.', true);
}
if ($action === 'settings') {
  $prefix = trim(isset($_POST['nomor_prefix']) ? $_POST['nomor_prefix'] : '');
  $next = filter_var(isset($_POST['next_number']) ? $_POST['next_number'] : null, FILTER_VALIDATE_INT);
  $digits = filter_var(isset($_POST['number_digits']) ? $_POST['number_digits'] : null, FILTER_VALIDATE_INT);
  if (!preg_match('/^[A-Za-z0-9][A-Za-z0-9_-]{0,39}$/', $prefix) || $next === false || $next < 1 ||
      $next > 999999999 || $digits === false || $digits < 1 || $digits > 9) {
    surat_jalan_redirect('Nomor awal, prefix, atau jumlah digit tidak valid.');
  }
  $statement = mysqli_prepare($mysqli, 'UPDATE tbl_surat_jalan_settings SET nomor_prefix = ?, next_number = ?, number_digits = ? WHERE id = 1');
  mysqli_stmt_bind_param($statement, 'sii', $prefix, $next, $digits);
  if (!mysqli_stmt_execute($statement)) {
    mysqli_stmt_close($statement);
    surat_jalan_redirect('Pengaturan nomor surat gagal disimpan.');
  }
  mysqli_stmt_close($statement);
  surat_jalan_redirect('Pengaturan nomor surat berhasil disimpan.', true);
}
if ($action !== 'generate') {
  surat_jalan_redirect('Aksi Surat Jalan tidak dikenali.');
}
$settingsResult = mysqli_query($mysqli, 'SELECT * FROM tbl_surat_jalan_settings WHERE id = 1');
$settings = mysqli_fetch_assoc($settingsResult);
if (!$settings || empty($settings['template_path'])) {
  surat_jalan_redirect('Unggah template Word terlebih dahulu.');
}
$tableConfig = surat_jalan_parse_json($settings['table_config'], array('table_index' => -1, 'columns' => array()));
if ((int) $tableConfig['table_index'] < 0) {
  surat_jalan_redirect('Tabel barang belum terdeteksi pada template. Tambahkan header Barang/Item dan Jumlah/Qty lalu unggah ulang.');
}
$tanggal = isset($_POST['tanggal']) ? $_POST['tanggal'] : '';
$tanggalObject = DateTime::createFromFormat('!Y-m-d', $tanggal);
$pemohon = trim(isset($_POST['pemohon']) ? $_POST['pemohon'] : '');
if (!$tanggalObject || $tanggalObject->format('Y-m-d') !== $tanggal || $pemohon === '' || strlen($pemohon) > 160) {
  surat_jalan_redirect('Tanggal dan nama Pemohon wajib diisi dengan benar.');
}
$fieldValues = isset($_POST['field']) && is_array($_POST['field']) ? $_POST['field'] : array();
$fields = surat_jalan_parse_json($settings['template_fields'], array());
$cleanFields = array();
foreach ($fields as $key => $label) {
  $value = isset($fieldValues[$key]) && is_string($fieldValues[$key]) ? trim($fieldValues[$key]) : '';
  if (strlen($value) > 500) {
    surat_jalan_redirect('Isi kolom ' . $label . ' terlalu panjang.');
  }
  $cleanFields[$key] = $value;
}
$names = isset($_POST['nama_barang']) && is_array($_POST['nama_barang']) ? $_POST['nama_barang'] : array();
$quantities = isset($_POST['jumlah']) && is_array($_POST['jumlah']) ? $_POST['jumlah'] : array();
$units = isset($_POST['satuan']) && is_array($_POST['satuan']) ? $_POST['satuan'] : array();
$notes = isset($_POST['keterangan']) && is_array($_POST['keterangan']) ? $_POST['keterangan'] : array();
$items = array();
foreach ($names as $index => $name) {
  $name = trim((string) $name);
  $quantity = isset($quantities[$index]) ? str_replace(',', '.', trim((string) $quantities[$index])) : '';
  if ($name === '' && $quantity === '') {
    continue;
  }
  if ($name === '' || !preg_match('/^\d+(?:\.\d{1,3})?$/', $quantity) || (float) $quantity <= 0) {
    surat_jalan_redirect('Setiap barang harus memiliki nama dan jumlah yang valid.');
  }
  $items[] = array(
    'nomor_item' => count($items) + 1,
    'nama_barang' => substr($name, 0, 180),
    'jumlah' => $quantity,
    'satuan' => substr(trim(isset($units[$index]) ? (string) $units[$index] : ''), 0, 50),
    'keterangan' => substr(trim(isset($notes[$index]) ? (string) $notes[$index] : ''), 0, 255)
  );
}
if (count($items) < 1 || count($items) > 100) {
  surat_jalan_redirect('Surat Jalan harus memiliki 1 sampai 100 barang.');
}
$templateAbsolute = dirname(__DIR__, 2) . '/uploads/surat-jalan/' . basename($settings['template_path']);
if (!is_file($templateAbsolute)) {
  surat_jalan_redirect('File template Word tidak ditemukan. Unggah ulang template.');
}
mysqli_begin_transaction($mysqli);
try {
  $number = (int) $settings['next_number'];
  $numberText = surat_jalan_number($settings['nomor_prefix'], $number, $settings['number_digits']);
  $newNext = $number + 1;
  $update = mysqli_prepare($mysqli, 'UPDATE tbl_surat_jalan_settings SET next_number = ? WHERE id = 1 AND next_number = ?');
  mysqli_stmt_bind_param($update, 'ii', $newNext, $number);
  if (!mysqli_stmt_execute($update) || mysqli_stmt_affected_rows($update) !== 1) {
    throw new RuntimeException('Nomor surat sedang dipakai pengguna lain. Silakan coba lagi.');
  }
  mysqli_stmt_close($update);
  $dataFields = json_encode($cleanFields, JSON_UNESCAPED_UNICODE);
  $user = (string) $_SESSION['username'];
  $insert = mysqli_prepare($mysqli, 'INSERT INTO tbl_surat_jalan (nomor_surat, tanggal, pemohon, data_fields, template_name, dibuat_oleh) VALUES (?, ?, ?, ?, ?, ?)');
  mysqli_stmt_bind_param($insert, 'ssssss', $numberText, $tanggal, $pemohon, $dataFields, $settings['template_name'], $user);
  if (!mysqli_stmt_execute($insert)) {
    throw new RuntimeException(mysqli_stmt_errno($insert) === 1062 ? 'Nomor surat sudah ada. Silakan coba lagi.' : 'Surat Jalan gagal disimpan.');
  }
  $documentId = mysqli_insert_id($mysqli);
  mysqli_stmt_close($insert);
  $detail = mysqli_prepare($mysqli, 'INSERT INTO tbl_surat_jalan_detail (surat_jalan_id, nomor_item, nama_barang, jumlah, satuan, keterangan) VALUES (?, ?, ?, ?, ?, ?)');
  foreach ($items as $item) {
    $itemNumber = $item['nomor_item'];
    $name = $item['nama_barang'];
    $quantity = (float) $item['jumlah'];
    $unit = $item['satuan'];
    $note = $item['keterangan'];
    mysqli_stmt_bind_param($detail, 'iisdss', $documentId, $itemNumber, $name, $quantity, $unit, $note);
    if (!mysqli_stmt_execute($detail)) {
      throw new RuntimeException('Rincian barang gagal disimpan.');
    }
  }
  mysqli_stmt_close($detail);
  $pdfFields = $cleanFields;
  $pdfFields['nomor_surat'] = $numberText;
  $pdfFields['tanggal'] = $tanggalObject->format('d-m-Y');
  $pdfFields['pemohon'] = $pemohon;
  $pdfContent = surat_jalan_docx_html($templateAbsolute, $pdfFields, $items, $tableConfig);
  $pdfFile = 'surat-jalan-' . preg_replace('/[^A-Za-z0-9_-]/', '-', $numberText) . '-' . bin2hex(random_bytes(5)) . '.pdf';
  $pdfAbsolute = dirname(__DIR__, 2) . '/uploads/surat-jalan/' . $pdfFile;
  if (file_put_contents($pdfAbsolute, surat_jalan_pdf_output($pdfContent), LOCK_EX) === false) {
    throw new RuntimeException('PDF Surat Jalan gagal disimpan.');
  }
  $pdfUpdate = mysqli_prepare($mysqli, 'UPDATE tbl_surat_jalan SET pdf_path = ? WHERE id = ?');
  mysqli_stmt_bind_param($pdfUpdate, 'si', $pdfFile, $documentId);
  if (!mysqli_stmt_execute($pdfUpdate)) {
    mysqli_stmt_close($pdfUpdate);
    throw new RuntimeException('Lokasi PDF Surat Jalan gagal disimpan.');
  }
  mysqli_stmt_close($pdfUpdate);
  mysqli_commit($mysqli);
} catch (Throwable $exception) {
  mysqli_rollback($mysqli);
  if (isset($pdfAbsolute) && is_file($pdfAbsolute)) {
    unlink($pdfAbsolute);
  }
  surat_jalan_redirect($exception->getMessage());
}
header('location: ../../main.php?module=surat_jalan&pesan=' . urlencode('Surat Jalan ' . $numberText . ' berhasil dibuat.') . '&document_id=' . (int) $documentId);
exit;
