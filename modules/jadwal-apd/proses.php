<?php
session_start();

if (empty($_SESSION['username'])) {
  header('location: ../../login.php?pesan=2');
  exit;
}

if (!isset($_SESSION['hak_akses']) || !in_array($_SESSION['hak_akses'], array('Administrator', 'Admin Gudang'), true)) {
  header('location: ../../main.php?module=dashboard');
  exit;
}

require_once dirname(__DIR__, 2) . '/config/database.php';
require_once __DIR__ . '/schema.php';
jadwal_apd_ensure_schema($mysqli);

function jadwal_apd_redirect_error($message)
{
  header('location: ../../main.php?module=jadwal_apd&error=' . urlencode($message));
  exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' ||
    empty($_SESSION['csrf_jadwal_apd']) ||
    empty($_POST['csrf_token']) ||
    !hash_equals($_SESSION['csrf_jadwal_apd'], $_POST['csrf_token'])) {
  jadwal_apd_redirect_error('Permintaan tidak valid. Muat ulang halaman dan coba lagi.');
}

$action = isset($_POST['aksi']) && is_string($_POST['aksi']) ? $_POST['aksi'] : 'tambah';
$isAdministrator = $_SESSION['hak_akses'] === 'Administrator';
if (in_array($action, array('ubah', 'hapus'), true) && !$isAdministrator) {
  jadwal_apd_redirect_error('Fitur edit dan hapus riwayat hanya dapat digunakan oleh Administrator.');
}

if ($action === 'hapus') {
  $recordId = filter_var(isset($_POST['pengambilan_id']) ? $_POST['pengambilan_id'] : null, FILTER_VALIDATE_INT);
  if (!$recordId || $recordId < 1) {
    jadwal_apd_redirect_error('Catatan pengambilan tidak valid.');
  }

  $recordStatement = mysqli_prepare($mysqli, 'SELECT nama_karyawan, site, tanggal_pengambilan FROM tbl_jadwal_apd WHERE id = ? LIMIT 1');
  if (!$recordStatement) {
    error_log('Jadwal APD: gagal menyiapkan pencarian catatan hapus: ' . mysqli_error($mysqli));
    jadwal_apd_redirect_error('Catatan pengambilan tidak dapat dihapus. Silakan coba kembali.');
  }
  mysqli_stmt_bind_param($recordStatement, 'i', $recordId);
  if (!mysqli_stmt_execute($recordStatement)) {
    error_log('Jadwal APD: gagal mencari catatan hapus: ' . mysqli_stmt_error($recordStatement));
    mysqli_stmt_close($recordStatement);
    jadwal_apd_redirect_error('Catatan pengambilan tidak dapat dihapus. Silakan coba kembali.');
  }
  $recordResult = mysqli_stmt_get_result($recordStatement);
  $record = mysqli_fetch_assoc($recordResult);
  mysqli_stmt_close($recordStatement);
  if (!$record) {
    jadwal_apd_redirect_error('Catatan pengambilan tidak ditemukan.');
  }

  $recordEmployee = $record['nama_karyawan'];
  $recordSite = $record['site'];
  $recordDate = $record['tanggal_pengambilan'];
  $deleteStatement = mysqli_prepare($mysqli, 'DELETE FROM tbl_jadwal_apd WHERE nama_karyawan = ? AND site = ? AND tanggal_pengambilan = ?');
  if (!$deleteStatement) {
    error_log('Jadwal APD: gagal menyiapkan penghapusan catatan: ' . mysqli_error($mysqli));
    jadwal_apd_redirect_error('Catatan pengambilan tidak dapat dihapus. Silakan coba kembali.');
  }
  mysqli_stmt_bind_param($deleteStatement, 'sss', $recordEmployee, $recordSite, $recordDate);
  $deleted = mysqli_stmt_execute($deleteStatement);
  $deletedRows = mysqli_stmt_affected_rows($deleteStatement);
  if (!$deleted) {
    error_log('Jadwal APD: gagal menghapus catatan: ' . mysqli_stmt_error($deleteStatement));
  }
  mysqli_stmt_close($deleteStatement);
  if (!$deleted || $deletedRows < 1) {
    jadwal_apd_redirect_error('Catatan pengambilan tidak dapat dihapus. Silakan coba kembali.');
  }
  header('location: ../../main.php?module=jadwal_apd&pesan=3');
  exit;
}

if (!in_array($action, array('tambah', 'ubah'), true)) {
  jadwal_apd_redirect_error('Aksi pengambilan APD tidak dikenali.');
}

$recordId = null;
if ($action === 'ubah') {
  $recordId = filter_var(isset($_POST['pengambilan_id']) ? $_POST['pengambilan_id'] : null, FILTER_VALIDATE_INT);
  if (!$recordId || $recordId < 1) {
    jadwal_apd_redirect_error('Catatan pengambilan yang akan diubah tidak valid.');
  }
}

$namaKaryawan = isset($_POST['nama_karyawan']) && is_string($_POST['nama_karyawan']) ? trim($_POST['nama_karyawan']) : '';
$site = isset($_POST['site']) && is_string($_POST['site']) ? trim($_POST['site']) : '';
$tanggal = isset($_POST['tanggal_pengambilan']) && is_string($_POST['tanggal_pengambilan']) ? $_POST['tanggal_pengambilan'] : '';
$tanggalObject = $tanggal !== '' ? DateTime::createFromFormat('!Y-m-d', $tanggal) : false;
$apdDiizinkan = array('Vest', 'Helm', 'Sepatu', 'Kacamata');
$apdMasukan = isset($_POST['apd']) && is_array($_POST['apd']) ? $_POST['apd'] : array();
$ukuranMasukan = isset($_POST['ukuran_apd']) && is_array($_POST['ukuran_apd']) ? $_POST['ukuran_apd'] : array();
$apdDipilih = array();
$ukuranApd = array();
$apdValid = count($apdMasukan) > 0;
foreach ($apdMasukan as $namaApd) {
  if (!is_string($namaApd) || !in_array($namaApd, $apdDiizinkan, true)) {
    $apdValid = false;
    break;
  }
  if (!in_array($namaApd, $apdDipilih, true)) {
    $apdDipilih[] = $namaApd;
  }
}

foreach (array('Vest' => 'vest', 'Sepatu' => 'sepatu') as $namaApd => $fieldUkuran) {
  if (!in_array($namaApd, $apdDipilih, true)) {
    continue;
  }
  $ukuran = isset($ukuranMasukan[$fieldUkuran]) && is_string($ukuranMasukan[$fieldUkuran])
    ? trim($ukuranMasukan[$fieldUkuran])
    : '';
  if ($ukuran === '' || strlen($ukuran) > 30) {
    $apdValid = false;
    break;
  }
  $ukuranApd[$namaApd] = $ukuran;
}

if ($namaKaryawan === '' || strlen($namaKaryawan) > 150 ||
    $site === '' || strlen($site) > 120 ||
    !$tanggalObject || $tanggalObject->format('Y-m-d') !== $tanggal ||
    !$apdValid) {
  jadwal_apd_redirect_error('Lengkapi nama karyawan, site, tanggal, minimal satu jenis APD, serta ukuran Vest/Sepatu yang dipilih (maksimal 30 karakter).');
}

if (!mysqli_begin_transaction($mysqli)) {
  error_log('Jadwal APD: gagal memulai transaksi: ' . mysqli_error($mysqli));
  jadwal_apd_redirect_error('Data pengambilan belum dapat disimpan. Silakan coba kembali.');
}

if ($action === 'ubah') {
  $recordStatement = mysqli_prepare($mysqli, 'SELECT nama_karyawan, site, tanggal_pengambilan FROM tbl_jadwal_apd WHERE id = ? FOR UPDATE');
  if (!$recordStatement) {
    error_log('Jadwal APD: gagal menyiapkan pencarian catatan edit: ' . mysqli_error($mysqli));
    mysqli_rollback($mysqli);
    jadwal_apd_redirect_error('Catatan pengambilan tidak dapat diubah. Silakan coba kembali.');
  }
  mysqli_stmt_bind_param($recordStatement, 'i', $recordId);
  $recordLoaded = mysqli_stmt_execute($recordStatement);
  $recordResult = $recordLoaded ? mysqli_stmt_get_result($recordStatement) : false;
  $record = $recordResult ? mysqli_fetch_assoc($recordResult) : null;
  if (!$recordLoaded) {
    error_log('Jadwal APD: gagal memuat catatan edit: ' . mysqli_stmt_error($recordStatement));
  }
  mysqli_stmt_close($recordStatement);
  if (!$record) {
    mysqli_rollback($mysqli);
    jadwal_apd_redirect_error('Catatan pengambilan tidak ditemukan.');
  }
  $recordEmployee = $record['nama_karyawan'];
  $recordSite = $record['site'];
  $recordDate = $record['tanggal_pengambilan'];

  $updateStatement = mysqli_prepare($mysqli, 'UPDATE tbl_jadwal_apd SET nama_karyawan = ?, site = ?, tanggal_pengambilan = ? WHERE id = ?');
  if (!$updateStatement) {
    error_log('Jadwal APD: gagal menyiapkan perubahan catatan: ' . mysqli_error($mysqli));
    mysqli_rollback($mysqli);
    jadwal_apd_redirect_error('Catatan pengambilan tidak dapat diubah. Silakan coba kembali.');
  }
  mysqli_stmt_bind_param($updateStatement, 'sssi', $namaKaryawan, $site, $tanggal, $recordId);
  $saved = mysqli_stmt_execute($updateStatement);
  if (!$saved) {
    error_log('Jadwal APD: gagal mengubah catatan: ' . mysqli_stmt_error($updateStatement));
  }
  mysqli_stmt_close($updateStatement);
  if (!$saved) {
    mysqli_rollback($mysqli);
    jadwal_apd_redirect_error('Catatan pengambilan tidak dapat diubah. Silakan coba kembali.');
  }

  $mergeStatement = mysqli_prepare($mysqli, 'DELETE FROM tbl_jadwal_apd WHERE nama_karyawan = ? AND site = ? AND tanggal_pengambilan = ? AND id <> ?');
  if (!$mergeStatement) {
    error_log('Jadwal APD: gagal menyiapkan penggabungan catatan pada hari yang sama: ' . mysqli_error($mysqli));
    mysqli_rollback($mysqli);
    jadwal_apd_redirect_error('Catatan pengambilan tidak dapat diubah. Silakan coba kembali.');
  }
  mysqli_stmt_bind_param($mergeStatement, 'sssi', $recordEmployee, $recordSite, $recordDate, $recordId);
  $merged = mysqli_stmt_execute($mergeStatement);
  if (!$merged) {
    error_log('Jadwal APD: gagal menggabungkan catatan pada hari yang sama: ' . mysqli_stmt_error($mergeStatement));
  }
  mysqli_stmt_close($mergeStatement);
  if (!$merged) {
    mysqli_rollback($mysqli);
    jadwal_apd_redirect_error('Catatan pengambilan tidak dapat diubah. Silakan coba kembali.');
  }

  $deleteItems = mysqli_prepare($mysqli, 'DELETE FROM tbl_jadwal_apd_item WHERE pengambilan_id = ?');
  if (!$deleteItems) {
    error_log('Jadwal APD: gagal menyiapkan penggantian item: ' . mysqli_error($mysqli));
    mysqli_rollback($mysqli);
    jadwal_apd_redirect_error('Item APD tidak dapat diperbarui. Silakan coba kembali.');
  }
  mysqli_stmt_bind_param($deleteItems, 'i', $recordId);
  $itemsDeleted = mysqli_stmt_execute($deleteItems);
  if (!$itemsDeleted) {
    error_log('Jadwal APD: gagal menghapus item lama: ' . mysqli_stmt_error($deleteItems));
  }
  mysqli_stmt_close($deleteItems);
  if (!$itemsDeleted) {
    mysqli_rollback($mysqli);
    jadwal_apd_redirect_error('Item APD tidak dapat diperbarui. Silakan coba kembali.');
  }

  $pengambilanId = $recordId;
} else {
  $insertHeader = mysqli_prepare($mysqli, 'INSERT INTO tbl_jadwal_apd (nama_karyawan, site, tanggal_pengambilan, dibuat_oleh) VALUES (?, ?, ?, ?)');
  if (!$insertHeader) {
    error_log('Jadwal APD: gagal menyiapkan penyimpanan pengambilan: ' . mysqli_error($mysqli));
    mysqli_rollback($mysqli);
    jadwal_apd_redirect_error('Data pengambilan belum dapat disimpan. Silakan coba kembali.');
  }
  $dibuatOleh = $_SESSION['username'];
  mysqli_stmt_bind_param($insertHeader, 'ssss', $namaKaryawan, $site, $tanggal, $dibuatOleh);
  $saved = mysqli_stmt_execute($insertHeader);
  $pengambilanId = mysqli_insert_id($mysqli);
  if (!$saved) {
    error_log('Jadwal APD: gagal menyimpan pengambilan: ' . mysqli_stmt_error($insertHeader));
  }
  mysqli_stmt_close($insertHeader);
  if (!$saved) {
    mysqli_rollback($mysqli);
    jadwal_apd_redirect_error('Data pengambilan belum dapat disimpan. Silakan coba kembali.');
  }
}

$insertItem = mysqli_prepare($mysqli, 'INSERT INTO tbl_jadwal_apd_item (pengambilan_id, nama_apd, ukuran) VALUES (?, ?, ?)');
if (!$insertItem) {
  error_log('Jadwal APD: gagal menyiapkan penyimpanan item: ' . mysqli_error($mysqli));
  mysqli_rollback($mysqli);
  jadwal_apd_redirect_error('Item APD belum dapat disimpan. Silakan coba kembali.');
}

mysqli_stmt_bind_param($insertItem, 'iss', $pengambilanId, $namaApd, $ukuran);
$itemsSaved = true;
foreach ($apdDipilih as $namaApd) {
  $ukuran = isset($ukuranApd[$namaApd]) ? $ukuranApd[$namaApd] : null;
  if (!mysqli_stmt_execute($insertItem)) {
    error_log('Jadwal APD: gagal menyimpan item: ' . mysqli_stmt_error($insertItem));
    $itemsSaved = false;
    break;
  }
}
mysqli_stmt_close($insertItem);

if (!$itemsSaved) {
  mysqli_rollback($mysqli);
  jadwal_apd_redirect_error('Data pengambilan belum dapat disimpan secara lengkap. Silakan coba kembali.');
}
if (!mysqli_commit($mysqli)) {
  error_log('Jadwal APD: gagal melakukan commit transaksi: ' . mysqli_error($mysqli));
  mysqli_rollback($mysqli);
  jadwal_apd_redirect_error('Data pengambilan belum dapat disimpan secara lengkap. Silakan coba kembali.');
}

header('location: ../../main.php?module=jadwal_apd&pesan=' . ($action === 'ubah' ? '2' : '1'));
exit;
