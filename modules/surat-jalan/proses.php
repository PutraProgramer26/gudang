<?php
session_start();
if (empty($_SESSION['username'])) {
  header('location: ../../login.php?pesan=2');
  exit;
}

require_once '../../config/database.php';
require_once __DIR__ . '/schema.php';
surat_jalan_ensure_schema($mysqli);

function surat_jalan_redirect_error($message)
{
  header('location: ../../main.php?module=surat_jalan&error=' . urlencode($message));
  exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_SESSION['csrf_surat_jalan']) ||
    empty($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_surat_jalan'], $_POST['csrf_token'])) {
  surat_jalan_redirect_error('Permintaan tidak valid. Muat ulang halaman dan coba lagi.');
}

$action = isset($_POST['aksi']) ? $_POST['aksi'] : '';
$canManageDocuments = isset($_SESSION['hak_akses']) && in_array($_SESSION['hak_akses'], array('Administrator', 'Admin Gudang'), true);

if (in_array($action, array('ubah', 'hapus'), true) && !$canManageDocuments) {
  surat_jalan_redirect_error('Anda tidak memiliki izin mengubah atau menghapus surat jalan.');
}

if ($action === 'hapus') {
  $documentId = filter_var(isset($_POST['document_id']) ? $_POST['document_id'] : null, FILTER_VALIDATE_INT);
  if (!$documentId || $documentId < 1 || !mysqli_begin_transaction($mysqli)) {
    surat_jalan_redirect_error('Surat jalan tidak valid atau tidak dapat diproses.');
  }
  $deleteStatement = mysqli_prepare($mysqli, 'DELETE FROM tbl_surat_jalan WHERE id = ?');
  if (!$deleteStatement) {
    mysqli_rollback($mysqli);
    surat_jalan_redirect_error('Surat jalan tidak dapat dihapus.');
  }
  mysqli_stmt_bind_param($deleteStatement, 'i', $documentId);
  $deleted = mysqli_stmt_execute($deleteStatement) && mysqli_stmt_affected_rows($deleteStatement) === 1;
  mysqli_stmt_close($deleteStatement);
  if (!$deleted) {
    mysqli_rollback($mysqli);
    surat_jalan_redirect_error('Surat jalan tidak ditemukan atau sudah dihapus.');
  }
  mysqli_commit($mysqli);
  header('location: ../../main.php?module=surat_jalan&pesan=4');
  exit;
}

if ($action === 'pengaturan') {
  $title = trim(isset($_POST['header_title']) ? $_POST['header_title'] : '');
  $subtitle = trim(isset($_POST['header_subtitle']) ? $_POST['header_subtitle'] : '');
  $prefix = trim(isset($_POST['nomor_prefix']) ? $_POST['nomor_prefix'] : '');
  $nextNumber = filter_var(isset($_POST['next_number']) ? $_POST['next_number'] : null, FILTER_VALIDATE_INT);
  $digits = filter_var(isset($_POST['number_digits']) ? $_POST['number_digits'] : null, FILTER_VALIDATE_INT);

  if ($title === '' || strlen($title) > 160 || strlen($subtitle) > 255 ||
      !preg_match('/^[A-Za-z0-9_-]{1,30}$/', $prefix) || $nextNumber === false ||
      $nextNumber < 1 || $nextNumber > 999999999 || $digits === false || $digits < 3 || $digits > 9) {
    surat_jalan_redirect_error('Periksa kembali tulisan header dan pengaturan nomor surat.');
  }

  $current = mysqli_query($mysqli, 'SELECT logo_path FROM tbl_surat_jalan_settings WHERE id = 1');
  $currentSettings = mysqli_fetch_assoc($current);
  $logoPath = $currentSettings ? $currentSettings['logo_path'] : null;
  $newLogoAbsolute = null;

  if (isset($_FILES['logo']) && $_FILES['logo']['error'] !== UPLOAD_ERR_NO_FILE) {
    if ($_FILES['logo']['error'] !== UPLOAD_ERR_OK || $_FILES['logo']['size'] > 2097152 || !is_uploaded_file($_FILES['logo']['tmp_name'])) {
      surat_jalan_redirect_error('Logo gagal diunggah atau ukurannya melebihi 2 MB.');
    }
    $fileInfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = finfo_file($fileInfo, $_FILES['logo']['tmp_name']);
    finfo_close($fileInfo);
    $extensions = array('image/png' => 'png', 'image/jpeg' => 'jpg');
    if (!isset($extensions[$mimeType]) || getimagesize($_FILES['logo']['tmp_name']) === false) {
      surat_jalan_redirect_error('Format logo harus PNG atau JPG yang valid.');
    }

    $uploadDirectory = dirname(__DIR__, 2) . '/uploads/surat-jalan';
    if (!is_dir($uploadDirectory) && !mkdir($uploadDirectory, 0755, true)) {
      surat_jalan_redirect_error('Folder penyimpanan logo tidak dapat dibuat. Periksa izin folder aplikasi.');
    }
    $filename = 'logo-' . bin2hex(random_bytes(12)) . '.' . $extensions[$mimeType];
    $newLogoAbsolute = $uploadDirectory . '/' . $filename;
    if (!move_uploaded_file($_FILES['logo']['tmp_name'], $newLogoAbsolute)) {
      surat_jalan_redirect_error('Logo tidak dapat disimpan. Periksa izin folder uploads.');
    }
    $logoPath = 'uploads/surat-jalan/' . $filename;
  }

  $statement = mysqli_prepare($mysqli, 'UPDATE tbl_surat_jalan_settings SET header_title = ?, header_subtitle = ?, nomor_prefix = ?, next_number = ?, number_digits = ?, logo_path = ? WHERE id = 1');
  if (!$statement) {
    if ($newLogoAbsolute && is_file($newLogoAbsolute)) {
      unlink($newLogoAbsolute);
    }
    surat_jalan_redirect_error('Pengaturan tidak dapat disimpan ke database.');
  }
  mysqli_stmt_bind_param($statement, 'sssiis', $title, $subtitle, $prefix, $nextNumber, $digits, $logoPath);
  $saved = mysqli_stmt_execute($statement);
  mysqli_stmt_close($statement);
  if (!$saved) {
    if ($newLogoAbsolute && is_file($newLogoAbsolute)) {
      unlink($newLogoAbsolute);
    }
    surat_jalan_redirect_error('Pengaturan tidak dapat disimpan. Silakan coba kembali.');
  }

  if ($newLogoAbsolute && !empty($currentSettings['logo_path'])) {
    $oldLogo = dirname(__DIR__, 2) . '/' . $currentSettings['logo_path'];
    if (is_file($oldLogo)) {
      unlink($oldLogo);
    }
  }
  header('location: ../../main.php?module=surat_jalan_settings&pesan=1');
  exit;
}

if (!in_array($action, array('buat', 'ubah'), true)) {
  surat_jalan_redirect_error('Aksi tidak dikenali.');
}
$documentId = $action === 'ubah' ? filter_var(isset($_POST['document_id']) ? $_POST['document_id'] : null, FILTER_VALIDATE_INT) : null;
if ($action === 'ubah' && (!$documentId || $documentId < 1)) {
  surat_jalan_redirect_error('Surat jalan yang akan diubah tidak valid.');
}

$date = isset($_POST['tanggal']) ? $_POST['tanggal'] : '';
$dateObject = DateTime::createFromFormat('!Y-m-d', $date);
$pemohon = trim(isset($_POST['pemohon']) ? $_POST['pemohon'] : '');
$driver = trim(isset($_POST['driver']) ? $_POST['driver'] : '');
$manager = trim(isset($_POST['manager']) ? $_POST['manager'] : '');
$tujuan = trim(isset($_POST['tujuan']) ? $_POST['tujuan'] : '');
$barangIds = isset($_POST['barang']) && is_array($_POST['barang']) ? $_POST['barang'] : array();
$jumlahList = isset($_POST['jumlah']) && is_array($_POST['jumlah']) ? $_POST['jumlah'] : array();
$keteranganList = isset($_POST['keterangan']) && is_array($_POST['keterangan']) ? $_POST['keterangan'] : array();

if (!$dateObject || $dateObject->format('Y-m-d') !== $date || $pemohon === '' || $driver === '' || $manager === '' ||
    strlen($pemohon) > 120 || strlen($driver) > 120 || strlen($manager) > 120 || strlen($tujuan) > 255 ||
    count($barangIds) < 1 || count($barangIds) > 100 || count($barangIds) !== count($jumlahList)) {
  surat_jalan_redirect_error('Lengkapi tanggal, tiga nama penanda tangan, dan daftar barang dengan benar.');
}

$items = array();
$findItem = mysqli_prepare($mysqli, 'SELECT nama_barang FROM tbl_barang WHERE id_barang = ? LIMIT 1');
if (!$findItem) {
  surat_jalan_redirect_error('Data barang tidak dapat diperiksa.');
}
foreach ($barangIds as $index => $barangId) {
  $barangId = trim($barangId);
  $quantity = filter_var($jumlahList[$index], FILTER_VALIDATE_FLOAT);
  if ($barangId === '' || $quantity === false || $quantity <= 0 || $quantity > 9999999999.99) {
    mysqli_stmt_close($findItem);
    surat_jalan_redirect_error('Nama barang dan jumlah pada setiap baris harus valid.');
  }
  mysqli_stmt_bind_param($findItem, 's', $barangId);
  if (!mysqli_stmt_execute($findItem)) {
    mysqli_stmt_close($findItem);
    surat_jalan_redirect_error('Data barang gagal diperiksa.');
  }
  $itemResult = mysqli_stmt_get_result($findItem);
  $itemData = mysqli_fetch_assoc($itemResult);
  if (!$itemData) {
    mysqli_stmt_close($findItem);
    surat_jalan_redirect_error('Salah satu barang tidak tersedia lagi. Muat ulang halaman.');
  }
  $note = isset($keteranganList[$index]) ? trim($keteranganList[$index]) : '';
  if (strlen($note) > 255) {
    mysqli_stmt_close($findItem);
    surat_jalan_redirect_error('Keterangan barang maksimal 255 karakter.');
  }
  $items[] = array('nama' => $itemData['nama_barang'], 'jumlah' => $quantity, 'keterangan' => $note);
}
mysqli_stmt_close($findItem);

if (!mysqli_begin_transaction($mysqli)) {
  surat_jalan_redirect_error('Transaksi database tidak dapat dimulai.');
}

try {
  if ($action === 'buat') {
    $settingsResult = mysqli_query($mysqli, 'SELECT nomor_prefix, next_number, number_digits FROM tbl_surat_jalan_settings WHERE id = 1 FOR UPDATE');
    $numberSettings = mysqli_fetch_assoc($settingsResult);
    if (!$numberSettings) {
      throw new RuntimeException('Pengaturan nomor surat belum tersedia.');
    }

    $sequence = (int) $numberSettings['next_number'];
    $digits = (int) $numberSettings['number_digits'];
    $prefix = $numberSettings['nomor_prefix'];
    $number = '';
    $numberCheck = mysqli_prepare($mysqli, 'SELECT id FROM tbl_surat_jalan WHERE nomor_surat = ? LIMIT 1');
    if (!$numberCheck) {
      throw new RuntimeException('Nomor surat tidak dapat diperiksa.');
    }
    for ($attempt = 0; $attempt < 1000; $attempt++) {
      $candidate = $prefix . '/' . date('Y', strtotime($date)) . '/' . str_pad((string) $sequence, $digits, '0', STR_PAD_LEFT);
      mysqli_stmt_bind_param($numberCheck, 's', $candidate);
      mysqli_stmt_execute($numberCheck);
      $checkResult = mysqli_stmt_get_result($numberCheck);
      if (mysqli_num_rows($checkResult) === 0) {
        $number = $candidate;
        break;
      }
      $sequence++;
    }
    mysqli_stmt_close($numberCheck);
    if ($number === '') {
      throw new RuntimeException('Nomor surat tidak tersedia. Periksa konfigurasi nomor.');
    }

    $createdBy = $_SESSION['username'];
    $headerStatement = mysqli_prepare($mysqli, 'INSERT INTO tbl_surat_jalan (nomor_surat, tanggal, pemohon, driver, manager, tujuan, dibuat_oleh) VALUES (?, ?, ?, ?, ?, ?, ?)');
    if (!$headerStatement) {
      throw new RuntimeException('Surat jalan tidak dapat disimpan.');
    }
    mysqli_stmt_bind_param($headerStatement, 'sssssss', $number, $date, $pemohon, $driver, $manager, $tujuan, $createdBy);
    if (!mysqli_stmt_execute($headerStatement)) {
      mysqli_stmt_close($headerStatement);
      throw new RuntimeException('Surat jalan tidak dapat disimpan.');
    }
    $documentId = mysqli_insert_id($mysqli);
    mysqli_stmt_close($headerStatement);
  } else {
    $lockStatement = mysqli_prepare($mysqli, 'SELECT id FROM tbl_surat_jalan WHERE id = ? FOR UPDATE');
    if (!$lockStatement) {
      throw new RuntimeException('Surat jalan tidak dapat diperiksa.');
    }
    mysqli_stmt_bind_param($lockStatement, 'i', $documentId);
    mysqli_stmt_execute($lockStatement);
    $lockedDocument = mysqli_stmt_get_result($lockStatement);
    $documentExists = mysqli_fetch_assoc($lockedDocument);
    mysqli_stmt_close($lockStatement);
    if (!$documentExists) {
      throw new RuntimeException('Surat jalan tidak ditemukan atau sudah dihapus.');
    }

    $headerStatement = mysqli_prepare($mysqli, 'UPDATE tbl_surat_jalan SET tanggal = ?, pemohon = ?, driver = ?, manager = ?, tujuan = ? WHERE id = ?');
    if (!$headerStatement) {
      throw new RuntimeException('Surat jalan tidak dapat diperbarui.');
    }
    mysqli_stmt_bind_param($headerStatement, 'sssssi', $date, $pemohon, $driver, $manager, $tujuan, $documentId);
    if (!mysqli_stmt_execute($headerStatement)) {
      mysqli_stmt_close($headerStatement);
      throw new RuntimeException('Surat jalan tidak dapat diperbarui.');
    }
    mysqli_stmt_close($headerStatement);

    $clearDetails = mysqli_prepare($mysqli, 'DELETE FROM tbl_surat_jalan_detail WHERE surat_jalan_id = ?');
    if (!$clearDetails) {
      throw new RuntimeException('Rincian surat jalan tidak dapat diperbarui.');
    }
    mysqli_stmt_bind_param($clearDetails, 'i', $documentId);
    if (!mysqli_stmt_execute($clearDetails)) {
      mysqli_stmt_close($clearDetails);
      throw new RuntimeException('Rincian surat jalan tidak dapat diperbarui.');
    }
    mysqli_stmt_close($clearDetails);
  }

  $detailStatement = mysqli_prepare($mysqli, 'INSERT INTO tbl_surat_jalan_detail (surat_jalan_id, nama_barang, jumlah, keterangan) VALUES (?, ?, ?, ?)');
  if (!$detailStatement) {
    throw new RuntimeException('Rincian barang tidak dapat disimpan.');
  }
  foreach ($items as $item) {
    $itemNote = $item['keterangan'] === '' ? null : $item['keterangan'];
    mysqli_stmt_bind_param($detailStatement, 'isds', $documentId, $item['nama'], $item['jumlah'], $itemNote);
    if (!mysqli_stmt_execute($detailStatement)) {
      mysqli_stmt_close($detailStatement);
      throw new RuntimeException('Rincian barang tidak dapat disimpan.');
    }
  }
  mysqli_stmt_close($detailStatement);

  if ($action === 'buat') {
    $nextNumber = $sequence + 1;
    $counterStatement = mysqli_prepare($mysqli, 'UPDATE tbl_surat_jalan_settings SET next_number = ? WHERE id = 1');
    if (!$counterStatement) {
      throw new RuntimeException('Nomor urut tidak dapat diperbarui.');
    }
    mysqli_stmt_bind_param($counterStatement, 'i', $nextNumber);
    if (!mysqli_stmt_execute($counterStatement)) {
      mysqli_stmt_close($counterStatement);
      throw new RuntimeException('Nomor urut tidak dapat diperbarui.');
    }
    mysqli_stmt_close($counterStatement);
  }
  mysqli_commit($mysqli);
} catch (Exception $exception) {
  mysqli_rollback($mysqli);
  surat_jalan_redirect_error($exception->getMessage());
}

header('location: ../../main.php?module=surat_jalan&pesan=' . ($action === 'ubah' ? '3' : '2'));
exit;