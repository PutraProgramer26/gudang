<?php
if (basename($_SERVER['PHP_SELF']) === basename(__FILE__)) {
  header('location: ../../404.html');
  exit;
}

require_once __DIR__ . '/schema.php';
jadwal_apd_ensure_schema($mysqli);

if (empty($_SESSION['csrf_jadwal_apd'])) {
  $_SESSION['csrf_jadwal_apd'] = bin2hex(random_bytes(32));
}

function jadwal_apd_h($value)
{
  return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function jadwal_apd_tanggal_berikutnya($tanggal)
{
  $tanggalDasar = DateTimeImmutable::createFromFormat('!Y-m-d', $tanggal);
  $hari = (int) $tanggalDasar->format('j');
  $bulanTujuan = $tanggalDasar->modify('first day of +6 months');
  $hariTerakhir = (int) $bulanTujuan->format('t');
  return $bulanTujuan->setDate(
    (int) $bulanTujuan->format('Y'),
    (int) $bulanTujuan->format('n'),
    min($hari, $hariTerakhir)
  );
}

function jadwal_apd_status_tanggal($tanggalBerikutnya, $hariIni)
{
  $selisih = (int) $hariIni->diff($tanggalBerikutnya)->format('%r%a');
  if ($selisih < 0) {
    return array('label' => 'Terlambat ' . abs($selisih) . ' hari', 'class' => 'apd-overdue', 'days' => $selisih);
  }
  if ($selisih === 0) {
    return array('label' => 'Jatuh tempo hari ini', 'class' => 'apd-due', 'days' => 0);
  }
  return array('label' => $selisih . ' hari lagi', 'class' => $selisih <= 30 ? 'apd-soon' : 'apd-safe', 'days' => $selisih);
}

$hariIni = new DateTimeImmutable('today');
$resultJadwal = mysqli_query(
  $mysqli,
  'SELECT p.nama_karyawan, p.uid_karyawan, p.site, i.nama_apd,
          MAX(p.tanggal_pengambilan) AS pengambilan_terakhir,
          (
            SELECT item_terakhir.ukuran
            FROM tbl_jadwal_apd pengambilan_terakhir
            INNER JOIN tbl_jadwal_apd_item item_terakhir
              ON item_terakhir.pengambilan_id = pengambilan_terakhir.id
            WHERE pengambilan_terakhir.nama_karyawan = p.nama_karyawan
              AND pengambilan_terakhir.site = p.site
              AND pengambilan_terakhir.uid_karyawan <=> p.uid_karyawan
              AND item_terakhir.nama_apd = i.nama_apd
            ORDER BY pengambilan_terakhir.tanggal_pengambilan DESC, pengambilan_terakhir.id DESC
            LIMIT 1
          ) AS ukuran
   FROM tbl_jadwal_apd p
   INNER JOIN tbl_jadwal_apd_item i ON i.pengambilan_id = p.id
   GROUP BY p.nama_karyawan, p.uid_karyawan, p.site, i.nama_apd
   ORDER BY p.nama_karyawan ASC, p.uid_karyawan ASC, p.site ASC, i.nama_apd ASC'
);
if (!$resultJadwal) {
  throw new RuntimeException('Gagal memuat jadwal APD: ' . mysqli_error($mysqli));
}

$jadwal = array();
$jadwalKaryawan = array();
$jadwalApdAktif = array();
$jumlahTerlambat = 0;
$jumlahSegera = 0;
while ($row = mysqli_fetch_assoc($resultJadwal)) {
  $row['tanggal_berikutnya'] = jadwal_apd_tanggal_berikutnya($row['pengambilan_terakhir']);
  $row['status'] = jadwal_apd_status_tanggal($row['tanggal_berikutnya'], $hariIni);
  if ($row['status']['days'] < 0) {
    $jumlahTerlambat++;
  } elseif ($row['status']['days'] <= 30) {
    $jumlahSegera++;
  }
  $key = strtolower((string) $row['uid_karyawan']) . "\0" . strtolower($row['nama_karyawan']) . "\0" . strtolower($row['site']) . "\0" . strtolower($row['nama_apd']);
  $jadwalApdAktif[$key] = $row;
  $jadwal[] = $row;

  $employeeKey = strtolower((string) $row['uid_karyawan']) . "\0" . strtolower($row['nama_karyawan']) . "\0" . strtolower($row['site']);
  if (!isset($jadwalKaryawan[$employeeKey])) {
    $jadwalKaryawan[$employeeKey] = array(
      'nama_karyawan' => $row['nama_karyawan'],
      'uid_karyawan' => $row['uid_karyawan'],
      'site' => $row['site'],
      'status' => $row['status'],
      'items' => array()
    );
  } elseif ($row['status']['days'] < $jadwalKaryawan[$employeeKey]['status']['days']) {
    $jadwalKaryawan[$employeeKey]['status'] = $row['status'];
  }
  $jadwalKaryawan[$employeeKey]['items'][] = $row;
}

$siteResult = mysqli_query($mysqli, 'SELECT DISTINCT site FROM tbl_jadwal_apd ORDER BY site ASC');
if (!$siteResult) {
  throw new RuntimeException('Gagal memuat daftar site APD: ' . mysqli_error($mysqli));
}
$sites = array();
while ($siteRow = mysqli_fetch_assoc($siteResult)) {
  $sites[] = $siteRow['site'];
}

$jadwalNamaFilter = isset($_GET['jadwal_nama']) && is_string($_GET['jadwal_nama']) ? trim($_GET['jadwal_nama']) : '';
$jadwalSiteFilter = isset($_GET['jadwal_site']) && is_string($_GET['jadwal_site']) ? trim($_GET['jadwal_site']) : '';
$jadwalKaryawanTampil = array();
foreach ($jadwalKaryawan as $employeeKey => $employeeSchedule) {
  if ($jadwalNamaFilter !== '' && stripos($employeeSchedule['nama_karyawan'], $jadwalNamaFilter) === false) {
    continue;
  }
  if ($jadwalSiteFilter !== '' && strcasecmp($employeeSchedule['site'], $jadwalSiteFilter) !== 0) {
    continue;
  }
  $employeeSchedule['detail_id'] = 'apd-detail-' . count($jadwalKaryawanTampil);
  $jadwalKaryawanTampil[] = $employeeSchedule;
}

$employeeResult = mysqli_query($mysqli, 'SELECT DISTINCT nama_karyawan FROM tbl_jadwal_apd ORDER BY nama_karyawan ASC');
if (!$employeeResult) {
  throw new RuntimeException('Gagal memuat daftar karyawan APD: ' . mysqli_error($mysqli));
}
$employees = array();
while ($employeeRow = mysqli_fetch_assoc($employeeResult)) {
  $employees[] = $employeeRow['nama_karyawan'];
}

$isApdAdministrator = isset($_SESSION['hak_akses']) && $_SESSION['hak_akses'] === 'Administrator';
$canRecordApd = isset($_SESSION['hak_akses']) && in_array($_SESSION['hak_akses'], array('Administrator', 'Admin Gudang'), true);
$editRecord = null;
$editItems = array();
$editId = $isApdAdministrator && isset($_GET['edit_id']) ? filter_var($_GET['edit_id'], FILTER_VALIDATE_INT) : false;
if ($editId && $editId > 0) {
  $editStatement = mysqli_prepare($mysqli, 'SELECT id, nama_karyawan, uid_karyawan, site, tanggal_pengambilan FROM tbl_jadwal_apd WHERE id = ? LIMIT 1');
  if (!$editStatement) {
    throw new RuntimeException('Gagal menyiapkan data edit pengambilan APD: ' . mysqli_error($mysqli));
  }
  mysqli_stmt_bind_param($editStatement, 'i', $editId);
  if (!mysqli_stmt_execute($editStatement)) {
    throw new RuntimeException('Gagal memuat data edit pengambilan APD: ' . mysqli_stmt_error($editStatement));
  }
  $editResult = mysqli_stmt_get_result($editStatement);
  $editRecord = mysqli_fetch_assoc($editResult);
  mysqli_stmt_close($editStatement);

  if ($editRecord) {
    $editItemsStatement = mysqli_prepare(
      $mysqli,
      'SELECT i.nama_apd, i.ukuran
       FROM tbl_jadwal_apd p
       INNER JOIN tbl_jadwal_apd_item i ON i.pengambilan_id = p.id
       WHERE p.nama_karyawan = ? AND p.uid_karyawan <=> ? AND p.site = ? AND p.tanggal_pengambilan = ?
       ORDER BY p.id DESC, i.id DESC'
    );
    if (!$editItemsStatement) {
      throw new RuntimeException('Gagal menyiapkan item edit pengambilan APD: ' . mysqli_error($mysqli));
    }
    mysqli_stmt_bind_param($editItemsStatement, 'ssss', $editRecord['nama_karyawan'], $editRecord['uid_karyawan'], $editRecord['site'], $editRecord['tanggal_pengambilan']);
    if (!mysqli_stmt_execute($editItemsStatement)) {
      throw new RuntimeException('Gagal memuat item edit pengambilan APD: ' . mysqli_stmt_error($editItemsStatement));
    }
    $editItemsResult = mysqli_stmt_get_result($editItemsStatement);
    while ($editItem = mysqli_fetch_assoc($editItemsResult)) {
      if (!isset($editItems[$editItem['nama_apd']])) {
        $editItems[$editItem['nama_apd']] = $editItem['ukuran'];
      }
    }
    mysqli_stmt_close($editItemsStatement);
  }
}

$siteFilter = isset($_GET['site']) && is_string($_GET['site']) ? trim($_GET['site']) : '';
$namaFilter = isset($_GET['nama_karyawan']) && is_string($_GET['nama_karyawan']) ? trim($_GET['nama_karyawan']) : '';
$historySql = 'SELECT p.id, p.nama_karyawan, p.uid_karyawan, p.site, p.tanggal_pengambilan,
                      i.nama_apd, i.ukuran
               FROM tbl_jadwal_apd p
               INNER JOIN tbl_jadwal_apd_item i ON i.pengambilan_id = p.id';
$conditions = array();
if ($siteFilter !== '') {
  $conditions[] = 'p.site = ?';
}
if ($namaFilter !== '') {
  $conditions[] = 'p.nama_karyawan LIKE ?';
}
if (count($conditions) > 0) {
  $historySql .= ' WHERE ' . implode(' AND ', $conditions);
}
$historySql .= ' ORDER BY p.tanggal_pengambilan DESC, p.nama_karyawan ASC, p.site ASC, i.nama_apd ASC, p.id DESC';
$historyStatement = mysqli_prepare($mysqli, $historySql);
if (!$historyStatement) {
  throw new RuntimeException('Gagal menyiapkan riwayat APD: ' . mysqli_error($mysqli));
}
if ($siteFilter !== '' && $namaFilter !== '') {
  $namaPattern = '%' . $namaFilter . '%';
  mysqli_stmt_bind_param($historyStatement, 'ss', $siteFilter, $namaPattern);
} elseif ($siteFilter !== '') {
  mysqli_stmt_bind_param($historyStatement, 's', $siteFilter);
} elseif ($namaFilter !== '') {
  $namaPattern = '%' . $namaFilter . '%';
  mysqli_stmt_bind_param($historyStatement, 's', $namaPattern);
}
if (!mysqli_stmt_execute($historyStatement)) {
  throw new RuntimeException('Gagal memuat riwayat APD: ' . mysqli_stmt_error($historyStatement));
}
$historyResult = mysqli_stmt_get_result($historyStatement);
$riwayat = array();
while ($historyRow = mysqli_fetch_assoc($historyResult)) {
  $historyKey = strtolower((string) $historyRow['uid_karyawan']) . "\0" .
    strtolower($historyRow['nama_karyawan']) . "\0" .
    strtolower($historyRow['site']) . "\0" . $historyRow['tanggal_pengambilan'];
  if (!isset($riwayat[$historyKey])) {
    $riwayat[$historyKey] = array(
      'id' => (int) $historyRow['id'],
      'nama_karyawan' => $historyRow['nama_karyawan'],
      'uid_karyawan' => $historyRow['uid_karyawan'],
      'site' => $historyRow['site'],
      'tanggal_pengambilan' => $historyRow['tanggal_pengambilan'],
      'jadwal_apd_item' => array()
    );
  } elseif ((int) $historyRow['id'] < $riwayat[$historyKey]['id']) {
    $riwayat[$historyKey]['id'] = (int) $historyRow['id'];
  }

  $itemKey = strtolower($historyRow['nama_apd']);
  if (!isset($riwayat[$historyKey]['jadwal_apd_item'][$itemKey])) {
    $activeKey = strtolower((string) $historyRow['uid_karyawan']) . "\0" .
      strtolower($historyRow['nama_karyawan']) . "\0" .
      strtolower($historyRow['site']) . "\0" . $itemKey;
    if (isset($jadwalApdAktif[$activeKey])) {
      $itemData = $jadwalApdAktif[$activeKey];
    } else {
      $itemData = array('nama_apd' => $historyRow['nama_apd'], 'ukuran' => array());
    }
    $itemData['ukuran'] = array();
    $riwayat[$historyKey]['jadwal_apd_item'][$itemKey] = $itemData;
  }
  if ($historyRow['ukuran'] !== null && $historyRow['ukuran'] !== '' &&
      !in_array($historyRow['ukuran'], $riwayat[$historyKey]['jadwal_apd_item'][$itemKey]['ukuran'], true)) {
    $riwayat[$historyKey]['jadwal_apd_item'][$itemKey]['ukuran'][] = $historyRow['ukuran'];
  }
}
$riwayat = array_values($riwayat);
mysqli_stmt_close($historyStatement);

$pesanSukses = isset($_GET['pesan']) && $_GET['pesan'] === '1';
$pesanDiperbarui = isset($_GET['pesan']) && $_GET['pesan'] === '2';
$pesanDihapus = isset($_GET['pesan']) && $_GET['pesan'] === '3';
$pesanError = isset($_GET['error']) ? trim($_GET['error']) : '';
?>
<style>
  .apd-page { --apd-ink:#20343a; --apd-green:#087f72; --apd-line:#dce6e3; color:var(--apd-ink); }
  .apd-hero { background:linear-gradient(112deg,#e2f1eb 0%,#f5f4e9 56%,#f2e4d4 100%); padding:25px 28px; border-radius:10px; display:flex; align-items:center; justify-content:space-between; gap:20px; }
  .apd-hero h2 { color:#20343a; font-size:25px; font-weight:800; margin:8px 0 4px; }
  .apd-hero p { color:#607173; margin:0; }
  .apd-mark { color:#087f72; font-size:38px; }
  .apd-badge { display:inline-block; background:#e4f3ed; color:#087f72; padding:5px 10px; border-radius:16px; font-size:11px; font-weight:800; }
  .apd-panel { background:#fff; border:1px solid var(--apd-line); border-radius:9px; box-shadow:0 5px 18px rgba(32,52,58,.045); margin-top:18px; }
  .apd-panel-head { padding:17px 20px; border-bottom:1px solid #edf1ef; display:flex; justify-content:space-between; align-items:center; gap:12px; }
  .apd-panel-head h3 { font-size:16px; font-weight:800; margin:0; }
  .apd-panel-body { padding:20px; }
  .apd-page label { font-weight:700; color:#40565a; }
  .apd-page .form-control { border-color:#d8e2df; }
  .apd-page .form-control:focus { border-color:#45a596; box-shadow:0 0 0 3px rgba(8,127,114,.1); }
  .apd-table th { background:#f2f6f4; border-top:0; color:#5b6c6e; font-size:10px; text-transform:uppercase; letter-spacing:.3px; white-space:nowrap; }
  .apd-table td { vertical-align:middle; }
  .apd-status { display:inline-block; padding:5px 9px; border-radius:20px; font-size:12px; font-weight:700; white-space:nowrap; }
  .apd-safe { color:#087f72; background:#e4f3ed; }
  .apd-soon { color:#93620a; background:#fff4d8; }
  .apd-due, .apd-overdue { color:#a83434; background:#fde8e6; }
  .apd-stat { height:100%; padding:16px 18px; border:1px solid var(--apd-line); border-radius:9px; background:#fff; box-shadow:0 4px 14px rgba(32,52,58,.035); }
  .apd-stat span { display:block; color:#6c7d7e; font-size:12px; font-weight:700; }
  .apd-stat strong { display:block; margin-top:4px; color:#20343a; font-size:24px; }
  .apd-apd-option { display:flex; align-items:center; gap:9px; padding:10px 12px; border:1px solid #dce6e3; border-radius:8px; cursor:pointer; }
  .apd-size-field { margin-top:8px; }
  .apd-size-field[hidden] { display:none; }
  .apd-size-label { font-size:12px; margin-bottom:4px; }
  .apd-size-value { display:inline-block; padding:2px 7px; color:#466260; background:#e9f1ef; border-radius:12px; font-size:11px; font-weight:700; }
  .apd-item-status { display:flex; align-items:center; flex-wrap:wrap; gap:6px; padding:7px 9px; border-left:3px solid #45a596; border-radius:4px; background:#f6faf8; }
  .apd-employee-mode { display:flex; align-items:center; gap:8px; margin-bottom:12px; }
  .apd-employee-mode label { display:flex; align-items:center; gap:7px; padding:9px 12px; border:1px solid #dce6e3; border-radius:7px; cursor:pointer; margin:0; }
  .apd-employee-mode input { accent-color:#087f72; }
  .apd-apd-option input { accent-color:#087f72; }
  .apd-empty { padding:34px 16px; text-align:center; color:#718083; }
  @media(max-width:767px) { .apd-hero { padding:20px; align-items:flex-start; } .apd-mark { display:none; } .apd-panel-body { padding:14px; } .apd-panel-head { padding:14px; } }
</style>

<div class="page-inner apd-page">
  <header class="apd-hero">
    <div><span class="apd-badge">KESELAMATAN KERJA</span><h2>Jadwal Pengambilan APD</h2><p>Catat pengambilan dan pantau siklus APD setiap enam bulan per karyawan dan site.</p></div>
    <i class="fas fa-hard-hat apd-mark" aria-hidden="true"></i>
  </header>

  <?php if ($pesanSukses) { ?><div class="alert alert-success mt-3"><i class="fas fa-check-circle mr-2"></i>Pengambilan APD berhasil dicatat. Jadwal berikutnya dihitung enam bulan dari tanggal pengambilan ini.</div><?php } ?>
  <?php if ($pesanDiperbarui) { ?><div class="alert alert-success mt-3"><i class="fas fa-check-circle mr-2"></i>Riwayat pengambilan APD berhasil diperbarui.</div><?php } ?>
  <?php if ($pesanDihapus) { ?><div class="alert alert-success mt-3"><i class="fas fa-check-circle mr-2"></i>Riwayat pengambilan APD berhasil dihapus.</div><?php } ?>
  <?php if ($pesanError !== '') { ?><div class="alert alert-danger mt-3"><i class="fas fa-exclamation-circle mr-2"></i><?php echo jadwal_apd_h($pesanError); ?></div><?php } ?>

  <?php if ($editId && !$editRecord) { ?>
    <div class="alert alert-warning mt-3">Catatan pengambilan yang akan diubah tidak ditemukan.</div>
  <?php } ?>

  <?php if ($editRecord) { ?>
    <section class="apd-panel">
      <div class="apd-panel-head">
        <h3><i class="fas fa-edit mr-2 text-success"></i>Edit Riwayat Pengambilan</h3>
        <a class="btn btn-light btn-sm" href="main.php?module=jadwal_apd">Batal</a>
      </div>
      <div class="apd-panel-body">
        <form action="modules/jadwal-apd/proses.php" method="post" class="apd-edit-form">
          <input type="hidden" name="csrf_token" value="<?php echo jadwal_apd_h($_SESSION['csrf_jadwal_apd']); ?>">
          <input type="hidden" name="aksi" value="ubah">
          <input type="hidden" name="pengambilan_id" value="<?php echo (int) $editRecord['id']; ?>">
          <div class="row">
            <div class="form-group col-md-4">
              <label for="edit_nama_karyawan">Nama karyawan <span class="text-danger">*</span></label>
              <input id="edit_nama_karyawan" name="nama_karyawan" class="form-control" maxlength="150" value="<?php echo jadwal_apd_h($editRecord['nama_karyawan']); ?>" required>
            </div>
            <div class="form-group col-md-4">
              <label for="edit_uid_karyawan">UID karyawan <span class="text-danger">*</span></label>
              <input id="edit_uid_karyawan" name="uid_karyawan" class="form-control" maxlength="50" value="<?php echo jadwal_apd_h($editRecord['uid_karyawan']); ?>" required>
            </div>
            <div class="form-group col-md-4">
              <label for="edit_site">Site <span class="text-danger">*</span></label>
              <input id="edit_site" name="site" class="form-control" maxlength="120" value="<?php echo jadwal_apd_h($editRecord['site']); ?>" required>
            </div>
            <div class="form-group col-md-4">
              <label for="edit_tanggal_pengambilan">Tanggal pengambilan <span class="text-danger">*</span></label>
              <input id="edit_tanggal_pengambilan" type="date" name="tanggal_pengambilan" class="form-control" value="<?php echo jadwal_apd_h($editRecord['tanggal_pengambilan']); ?>" required>
            </div>
          </div>
          <div class="form-group">
            <label class="d-block mb-2">APD yang diambil <span class="text-danger">*</span></label>
            <div class="row">
              <?php foreach (array('Vest', 'Helm', 'Sepatu', 'Kacamata') as $apd) {
                $apdKey = strtolower($apd);
                $needsSize = in_array($apd, array('Vest', 'Sepatu'), true);
                $checkboxId = 'edit_apd_' . $apdKey;
                $sizeId = 'edit_ukuran_' . $apdKey;
                $isSelected = array_key_exists($apd, $editItems);
              ?>
                <div class="col-6 col-md-3 mb-2">
                  <label class="apd-apd-option mb-0" for="<?php echo jadwal_apd_h($checkboxId); ?>">
                    <input id="<?php echo jadwal_apd_h($checkboxId); ?>" type="checkbox" name="apd[]" value="<?php echo jadwal_apd_h($apd); ?>" <?php echo $isSelected ? 'checked' : ''; ?> <?php echo $needsSize ? 'data-size-target="' . jadwal_apd_h($sizeId) . '"' : ''; ?>>
                    <span><?php echo jadwal_apd_h($apd); ?></span>
                  </label>
                  <?php if ($needsSize) { ?>
                    <div class="apd-size-field" id="field_<?php echo jadwal_apd_h($sizeId); ?>" <?php echo $isSelected ? '' : 'hidden'; ?>>
                      <label class="apd-size-label" for="<?php echo jadwal_apd_h($sizeId); ?>">Ukuran <?php echo jadwal_apd_h($apd); ?> <span class="text-danger">*</span></label>
                      <input id="<?php echo jadwal_apd_h($sizeId); ?>" type="text" name="ukuran_apd[<?php echo jadwal_apd_h($apdKey); ?>]" class="form-control form-control-sm" maxlength="30" value="<?php echo jadwal_apd_h(isset($editItems[$apd]) ? $editItems[$apd] : ''); ?>" placeholder="<?php echo $apd === 'Sepatu' ? 'Contoh: 40' : 'Contoh: M'; ?>" <?php echo $isSelected ? 'required' : 'disabled'; ?>>
                    </div>
                  <?php } ?>
                </div>
              <?php } ?>
            </div>
            <small class="form-text text-muted">Ukuran Vest dan Sepatu wajib diisi jika item tersebut dipilih. Catatan lain pada tanggal, karyawan, dan site yang sama akan digabung ke riwayat ini.</small>
          </div>
          <button type="submit" class="btn btn-success"><i class="fas fa-save mr-1"></i>Simpan Perubahan</button>
        </form>
      </div>
    </section>
  <?php } ?>

  <div class="row mt-3">
    <div class="col-sm-4 mb-3"><div class="apd-stat"><span>Karyawan terdaftar</span><strong><?php echo count($employees); ?></strong></div></div>
    <div class="col-sm-4 mb-3"><div class="apd-stat"><span>Item APD jatuh tempo dalam 30 hari</span><strong><?php echo $jumlahSegera; ?></strong></div></div>
    <div class="col-sm-4 mb-3"><div class="apd-stat"><span>Item APD terlambat</span><strong><?php echo $jumlahTerlambat; ?></strong></div></div>
  </div>

  <?php if ($canRecordApd) { ?>
  <section class="apd-panel">
    <div class="apd-panel-head"><h3><i class="fas fa-plus-circle mr-2 text-success"></i>Catat Pengambilan</h3><span class="text-muted small">Periode baru dimulai dari tanggal pengambilan terakhir</span></div>
    <div class="apd-panel-body">
      <form action="modules/jadwal-apd/proses.php" method="post">
        <input type="hidden" name="csrf_token" value="<?php echo jadwal_apd_h($_SESSION['csrf_jadwal_apd']); ?>">
        <div class="row">
          <div class="col-lg-4 col-md-6">
            <div class="form-group">
              <label>Nama karyawan <span class="text-danger">*</span></label>
              <div class="apd-employee-mode" role="radiogroup" aria-label="Pilih cara memasukkan nama karyawan">
                <label><input type="radio" name="mode_karyawan" value="terdaftar" <?php echo count($employees) > 0 ? 'checked' : ''; ?>> Pilih terdaftar</label>
                <label><input type="radio" name="mode_karyawan" value="baru" <?php echo count($employees) === 0 ? 'checked' : ''; ?>> Tambah baru</label>
              </div>
              <select id="nama_karyawan_terdaftar" name="nama_karyawan" class="form-control" required>
                <option value="">Pilih nama karyawan</option>
                <?php foreach ($employees as $employee) { ?>
                  <option value="<?php echo jadwal_apd_h($employee); ?>"><?php echo jadwal_apd_h($employee); ?></option>
                <?php } ?>
              </select>
              <input id="nama_karyawan_baru" type="text" name="nama_karyawan" class="form-control" maxlength="150" autocomplete="off" placeholder="Masukkan nama karyawan baru" disabled>
              <?php if (count($employees) === 0) { ?><small class="form-text text-muted">Belum ada karyawan terdaftar. Pilih “Tambah baru” untuk memulai.</small><?php } ?>
            </div>
          </div>
          <div class="col-lg-4 col-md-6">
            <div class="form-group"><label for="uid_karyawan">UID karyawan <span class="text-danger">*</span></label><input id="uid_karyawan" name="uid_karyawan" class="form-control" maxlength="50" autocomplete="off" required placeholder="Masukkan UID karyawan"></div>
          </div>
          <div class="col-lg-4 col-md-6">
            <div class="form-group"><label for="site">Site <span class="text-danger">*</span></label><input id="site" name="site" class="form-control" list="apd-daftar-site" maxlength="120" autocomplete="off" required placeholder="Masukkan site kerja"><datalist id="apd-daftar-site"><?php foreach ($sites as $site) { ?><option value="<?php echo jadwal_apd_h($site); ?>"><?php } ?></datalist></div>
          </div>
          <script>
            (function() {
              var modeOptions = document.querySelectorAll('input[name="mode_karyawan"]');
              var registeredName = document.getElementById('nama_karyawan_terdaftar');
              var newName = document.getElementById('nama_karyawan_baru');

              function updateEmployeeName() {
                var isNewEmployee = document.querySelector('input[name="mode_karyawan"]:checked').value === 'baru';
                registeredName.disabled = isNewEmployee;
                registeredName.required = !isNewEmployee;
                newName.disabled = !isNewEmployee;
                newName.required = isNewEmployee;
              }

              modeOptions.forEach(function(option) {
                option.addEventListener('change', updateEmployeeName);
              });
              registeredName.addEventListener('change', updateEmployeeName);
              newName.addEventListener('input', updateEmployeeName);
              registeredName.form.addEventListener('submit', updateEmployeeName);
              updateEmployeeName();
            })();
          </script>
          <div class="col-lg-4 col-md-6">
            <div class="form-group"><label for="tanggal_pengambilan">Tanggal pengambilan <span class="text-danger">*</span></label><input id="tanggal_pengambilan" type="date" name="tanggal_pengambilan" class="form-control" value="<?php echo date('Y-m-d'); ?>" required></div>
          </div>
        </div>
        <div class="form-group mb-3">
          <label class="d-block mb-2">APD yang diambil <span class="text-danger">*</span></label>
          <div class="row">
            <?php foreach (array('Vest', 'Helm', 'Sepatu', 'Kacamata') as $apd) {
              $apdKey = strtolower($apd);
              $needsSize = in_array($apd, array('Vest', 'Sepatu'), true);
            ?>
              <div class="col-6 col-md-3 mb-2">
                <label class="apd-apd-option mb-0" for="apd_<?php echo jadwal_apd_h($apdKey); ?>">
                  <input id="apd_<?php echo jadwal_apd_h($apdKey); ?>" type="checkbox" name="apd[]" value="<?php echo jadwal_apd_h($apd); ?>" <?php echo $needsSize ? 'data-size-target="ukuran_' . jadwal_apd_h($apdKey) . '"' : ''; ?>>
                  <span><?php echo jadwal_apd_h($apd); ?></span>
                </label>
                <?php if ($needsSize) { ?>
                  <div class="apd-size-field" id="field_ukuran_<?php echo jadwal_apd_h($apdKey); ?>" hidden>
                    <label class="apd-size-label" for="ukuran_<?php echo jadwal_apd_h($apdKey); ?>">Ukuran <?php echo jadwal_apd_h($apd); ?> <span class="text-danger">*</span></label>
                    <input id="ukuran_<?php echo jadwal_apd_h($apdKey); ?>" type="text" name="ukuran_apd[<?php echo jadwal_apd_h($apdKey); ?>]" class="form-control form-control-sm" maxlength="30" placeholder="<?php echo $apd === 'Sepatu' ? 'Contoh: 40' : 'Contoh: M'; ?>" disabled>
                  </div>
                <?php } ?>
              </div>
            <?php } ?>
          </div>
          <small class="form-text text-muted">Pilih satu atau beberapa item. Ukuran wajib diisi jika memilih Vest atau Sepatu. Setiap pencatatan pengambilan memulai ulang siklus enam bulan.</small>
        </div>
        <button type="submit" class="btn btn-success"><i class="fas fa-save mr-1"></i>Simpan Pengambilan</button>
      </form>
    </div>
  </section>
  <script>
    (function() {
      var sizeOptions = document.querySelectorAll('.apd-page input[type="checkbox"][data-size-target]');
      sizeOptions.forEach(function(option) {
        var sizeInput = document.getElementById(option.getAttribute('data-size-target'));
        var sizeField = document.getElementById('field_' + option.getAttribute('data-size-target'));
        function updateSizeField() {
          sizeField.hidden = !option.checked;
          sizeInput.disabled = !option.checked;
          sizeInput.required = option.checked;
          if (!option.checked) {
            sizeInput.value = '';
          }
        }
        option.addEventListener('change', updateSizeField);
        updateSizeField();
      });
    })();
  </script>
  <?php } ?>

  <section class="apd-panel">
    <div class="apd-panel-head"><h3><i class="fas fa-calendar-check mr-2 text-success"></i>Jadwal Aktif</h3><span class="text-muted small">Pilih detail untuk melihat jadwal setiap item APD karyawan</span></div>
    <div class="apd-panel-body pb-0">
      <form method="get" action="main.php" class="row align-items-end mb-3">
        <input type="hidden" name="module" value="jadwal_apd">
        <div class="col-md-5">
          <div class="form-group">
            <label for="jadwal_filter_nama">Nama karyawan</label>
            <input id="jadwal_filter_nama" type="search" name="jadwal_nama" class="form-control" value="<?php echo jadwal_apd_h($jadwalNamaFilter); ?>" placeholder="Cari nama karyawan">
          </div>
        </div>
        <div class="col-md-4">
          <div class="form-group">
            <label for="jadwal_filter_site">Site</label>
            <select id="jadwal_filter_site" name="jadwal_site" class="form-control">
              <option value="">Semua site</option>
              <?php foreach ($sites as $site) { ?>
                <option value="<?php echo jadwal_apd_h($site); ?>" <?php echo $jadwalSiteFilter === $site ? 'selected' : ''; ?>><?php echo jadwal_apd_h($site); ?></option>
              <?php } ?>
            </select>
          </div>
        </div>
        <div class="col-md-3 mb-3 mb-md-0">
          <button type="submit" class="btn btn-outline-success mr-1"><i class="fas fa-search mr-1"></i>Cari</button>
          <a class="btn btn-light" href="main.php?module=jadwal_apd">Reset</a>
        </div>
      </form>
    </div>
    <div class="table-responsive">
      <table class="table table-hover mb-0 apd-table">
        <thead><tr><th>Nama karyawan</th><th>Sisa waktu terdekat</th><th>Aksi</th></tr></thead>
        <tbody>
          <?php if (count($jadwalKaryawanTampil) === 0) { ?><tr><td colspan="3" class="apd-empty"><?php echo count($jadwalKaryawan) === 0 ? 'Belum ada data pengambilan APD.' : 'Tidak ada jadwal yang cocok dengan pencarian.'; ?></td></tr><?php } ?>
          <?php foreach ($jadwalKaryawanTampil as $row) { ?>
            <tr>
              <td><span class="font-weight-bold"><?php echo jadwal_apd_h($row['nama_karyawan']); ?></span><small class="d-block text-muted">UID: <?php echo jadwal_apd_h($row['uid_karyawan'] ?: '-'); ?> · <?php echo jadwal_apd_h($row['site']); ?></small></td>
              <td><span class="apd-status <?php echo jadwal_apd_h($row['status']['class']); ?>"><?php echo jadwal_apd_h($row['status']['label']); ?></span></td>
              <td><button class="btn btn-sm btn-outline-success" type="button" data-toggle="collapse" data-target="#<?php echo jadwal_apd_h($row['detail_id']); ?>" aria-expanded="false" aria-controls="<?php echo jadwal_apd_h($row['detail_id']); ?>"><i class="fas fa-eye mr-1"></i>Lihat detail</button></td>
            </tr>
            <tr class="bg-light">
              <td colspan="3" class="p-0 border-0">
                <div class="collapse" id="<?php echo jadwal_apd_h($row['detail_id']); ?>">
                  <div class="p-3">
                    <div class="font-weight-bold mb-2"><?php echo jadwal_apd_h($row['nama_karyawan']); ?> · UID: <?php echo jadwal_apd_h($row['uid_karyawan'] ?: '-'); ?> · <?php echo jadwal_apd_h($row['site']); ?> — jadwal APD</div>
                    <div class="table-responsive">
                      <table class="table table-sm table-bordered bg-white mb-0">
                        <thead><tr><th>Item APD</th><th>Ukuran</th><th>Pengambilan terakhir</th><th>Tanggal berikutnya</th><th>Sisa waktu</th></tr></thead>
                        <tbody>
                          <?php foreach ($row['items'] as $item) { ?>
                            <tr>
                              <td><?php echo jadwal_apd_h($item['nama_apd']); ?></td>
                              <td><?php echo $item['ukuran'] !== null && $item['ukuran'] !== '' ? jadwal_apd_h($item['ukuran']) : '-'; ?></td>
                              <td><?php echo jadwal_apd_h(date('d-m-Y', strtotime($item['pengambilan_terakhir']))); ?></td>
                              <td><?php echo jadwal_apd_h($item['tanggal_berikutnya']->format('d-m-Y')); ?></td>
                              <td><span class="apd-status <?php echo jadwal_apd_h($item['status']['class']); ?>"><?php echo jadwal_apd_h($item['status']['label']); ?></span></td>
                            </tr>
                          <?php } ?>
                        </tbody>
                      </table>
                    </div>
                  </div>
                </div>
              </td>
            </tr>
          <?php } ?>
        </tbody>
      </table>
    </div>
  </section>

  <section class="apd-panel">
    <div class="apd-panel-head"><h3><i class="fas fa-history mr-2 text-success"></i>Riwayat Pengambilan</h3><span class="text-muted small"><?php echo count($riwayat); ?> catatan ditemukan</span></div>
    <div class="apd-panel-body">
      <form method="get" action="main.php" class="row align-items-end mb-3">
        <input type="hidden" name="module" value="jadwal_apd">
        <div class="col-md-4"><div class="form-group"><label for="filter_site">Site kerja</label><select id="filter_site" name="site" class="form-control"><option value="">Semua site</option><?php foreach ($sites as $site) { ?><option value="<?php echo jadwal_apd_h($site); ?>" <?php echo $siteFilter === $site ? 'selected' : ''; ?>><?php echo jadwal_apd_h($site); ?></option><?php } ?></select></div></div>
        <div class="col-md-5"><div class="form-group"><label for="filter_nama">Nama karyawan</label><input id="filter_nama" name="nama_karyawan" class="form-control" value="<?php echo jadwal_apd_h($namaFilter); ?>" placeholder="Cari nama karyawan"></div></div>
        <div class="col-md-3 mb-3 mb-md-0"><button class="btn btn-outline-success mr-1" type="submit"><i class="fas fa-search mr-1"></i>Cari</button><a class="btn btn-light" href="main.php?module=jadwal_apd">Reset</a></div>
      </form>
      <div class="table-responsive">
        <table class="table table-hover mb-0 apd-table">
          <thead><tr><th>Tanggal</th><th>UID Karyawan</th><th>Karyawan</th><th>Site</th><th>APD &amp; jadwal aktif per item</th><?php if ($isApdAdministrator) { ?><th>Aksi</th><?php } ?></tr></thead>
          <tbody>
            <?php if (count($riwayat) === 0) { ?><tr><td colspan="<?php echo $isApdAdministrator ? '6' : '5'; ?>" class="apd-empty">Tidak ada riwayat yang sesuai dengan pencarian.</td></tr><?php } ?>
            <?php foreach ($riwayat as $row) { ?>
              <tr>
                <td><?php echo jadwal_apd_h(date('d-m-Y', strtotime($row['tanggal_pengambilan']))); ?></td>
                <td><?php echo jadwal_apd_h($row['uid_karyawan'] ?: '-'); ?></td>
                <td class="font-weight-bold"><?php echo jadwal_apd_h($row['nama_karyawan']); ?></td>
                <td><?php echo jadwal_apd_h($row['site']); ?></td>
                <td>
                  <?php foreach ($row['jadwal_apd_item'] as $jadwalItem) { ?>
                    <div class="apd-item-status mb-2">
                      <strong><?php echo jadwal_apd_h($jadwalItem['nama_apd']); ?></strong>
                      <?php if (count($jadwalItem['ukuran']) > 0) { ?>
                        <span class="apd-size-value">Ukuran: <?php echo jadwal_apd_h(implode(', ', $jadwalItem['ukuran'])); ?></span>
                      <?php } ?>
                      <span class="text-muted small">Tanggal berikutnya: <?php echo jadwal_apd_h($jadwalItem['tanggal_berikutnya']->format('d-m-Y')); ?></span>
                      <span class="apd-status <?php echo jadwal_apd_h($jadwalItem['status']['class']); ?>">Sisa: <?php echo jadwal_apd_h($jadwalItem['status']['label']); ?></span>
                    </div>
                  <?php } ?>
                  <small class="text-muted">Sisa hari dan jadwal selalu mengikuti pengambilan terbaru untuk setiap item karyawan di site ini.</small>
                </td>
                <?php if ($isApdAdministrator) { ?>
                  <td class="text-nowrap">
                    <a class="btn btn-sm btn-outline-primary" href="main.php?module=jadwal_apd&amp;edit_id=<?php echo (int) $row['id']; ?>" title="Edit riwayat"><i class="fas fa-edit"></i></a>
                    <form action="modules/jadwal-apd/proses.php" method="post" class="d-inline" onsubmit="return confirm('Hapus seluruh riwayat pengambilan APD pada tanggal, karyawan, dan site ini?');">
                      <input type="hidden" name="csrf_token" value="<?php echo jadwal_apd_h($_SESSION['csrf_jadwal_apd']); ?>">
                      <input type="hidden" name="aksi" value="hapus">
                      <input type="hidden" name="pengambilan_id" value="<?php echo (int) $row['id']; ?>">
                      <button type="submit" class="btn btn-sm btn-outline-danger" title="Hapus riwayat"><i class="fas fa-trash"></i></button>
                    </form>
                  </td>
                <?php } ?>
              </tr>
            <?php } ?>
          </tbody>
        </table>
      </div>
    </div>
  </section>
</div>
