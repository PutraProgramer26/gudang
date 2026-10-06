<?php
if (basename($_SERVER['PHP_SELF']) === basename(__FILE__)) {
  header('location: ../../404.html');
  exit;
}

require_once __DIR__ . '/schema.php';
spp_ensure_schema($mysqli);

if (empty($_SESSION['csrf_spp'])) {
  $_SESSION['csrf_spp'] = bin2hex(random_bytes(32));
}

function spp_h($value)
{
  return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

$canManageSpp = isset($_SESSION['hak_akses']) && in_array($_SESSION['hak_akses'], array('Administrator', 'Admin Gudang'), true);
$projectResult = mysqli_query($mysqli, 'SELECT id, nama_project FROM tbl_spp_project ORDER BY nama_project ASC');
$projects = array();
while ($projectRow = mysqli_fetch_assoc($projectResult)) {
  $projects[] = $projectRow;
}
$projectId = isset($_GET['project_id']) ? filter_var($_GET['project_id'], FILTER_VALIDATE_INT) : false;
$selectedProject = null;
if ($projectId && $projectId > 0) {
  $projectStatement = mysqli_prepare($mysqli, 'SELECT id, nama_project FROM tbl_spp_project WHERE id = ?');
  mysqli_stmt_bind_param($projectStatement, 'i', $projectId);
  mysqli_stmt_execute($projectStatement);
  $projectData = mysqli_stmt_get_result($projectStatement);
  $selectedProject = mysqli_fetch_assoc($projectData);
  mysqli_stmt_close($projectStatement);
  if (!$selectedProject) {
    $projectId = false;
  }
}

$selectedSpp = null;
$sppItems = array();
if ($selectedProject && isset($_GET['spp_id'])) {
  $sppId = filter_var($_GET['spp_id'], FILTER_VALIDATE_INT);
  if ($sppId && $sppId > 0) {
    $sppStatement = mysqli_prepare($mysqli, 'SELECT id, project_id, nomor_spp, tanggal, share_token FROM tbl_spp WHERE id = ? AND project_id = ? LIMIT 1');
    mysqli_stmt_bind_param($sppStatement, 'ii', $sppId, $projectId);
    mysqli_stmt_execute($sppStatement);
    $sppResult = mysqli_stmt_get_result($sppStatement);
    $selectedSpp = mysqli_fetch_assoc($sppResult);
    mysqli_stmt_close($sppStatement);
    if ($selectedSpp) {
      $itemStatement = mysqli_prepare($mysqli, 'SELECT id, nomor_item, nama_item, spesifikasi, jumlah_diminta, jumlah_diterima, satuan, untuk_pekerjaan, keterangan FROM tbl_spp_item WHERE spp_id = ? ORDER BY nomor_item ASC');
      mysqli_stmt_bind_param($itemStatement, 'i', $sppId);
      mysqli_stmt_execute($itemStatement);
      $itemResult = mysqli_stmt_get_result($itemStatement);
      while ($itemRow = mysqli_fetch_assoc($itemResult)) {
        $sppItems[] = $itemRow;
      }
      mysqli_stmt_close($itemStatement);
    }
  }
}

$alertText = '';
if (isset($_GET['pesan'])) {
  $messages = array('1' => 'Project berhasil ditambahkan.', '2' => 'SPP berhasil dibuat.', '3' => 'Penerimaan barang berhasil diperbarui.');
  $alertText = isset($messages[$_GET['pesan']]) ? $messages[$_GET['pesan']] : '';
}
$errorText = isset($_GET['error']) ? $_GET['error'] : '';
$baseUrl = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https://' : 'http://') . (isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : 'localhost');
$appPath = dirname(isset($_SERVER['SCRIPT_NAME']) ? $_SERVER['SCRIPT_NAME'] : '/gudang/main.php');
$appPath = rtrim($appPath, '/');
if ($appPath === '') {
  $appPath = '/';
}
?>
<style>
  .spp-page { --spp-ink:#20343a; --spp-green:#087f72; --spp-line:#dce6e3; color:var(--spp-ink); }
  .spp-hero { background:linear-gradient(112deg,#e2f1eb 0%,#f5f4e9 56%,#f2e4d4 100%); padding:25px 28px; border-radius:8px; display:flex; align-items:center; justify-content:space-between; gap:20px; }
  .spp-hero h2 { color:#20343a; font-size:25px; font-weight:800; margin:8px 0 4px; }
  .spp-hero p { color:#607173; margin:0; }
  .spp-mark { color:#087f72; font-size:38px; }
  .spp-badge { display:inline-block; background:#e4f3ed; color:#087f72; padding:5px 10px; border-radius:16px; font-size:11px; font-weight:800; }
  .spp-panel { background:white; border:1px solid var(--spp-line); border-radius:7px; box-shadow:0 5px 18px rgba(32,52,58,.045); margin-top:18px; }
  .spp-panel-head { padding:17px 20px; border-bottom:1px solid #edf1ef; display:flex; justify-content:space-between; align-items:center; gap:12px; }
  .spp-panel-head h3 { font-size:16px; font-weight:800; margin:0; }
  .spp-panel-body { padding:20px; }
  .spp-page label { font-weight:700; color:#40565a; }
  .spp-page .form-control { border-color:#d8e2df; }
  .spp-page .form-control:focus { border-color:#45a596; box-shadow:0 0 0 3px rgba(8,127,114,.1); }
  .spp-table th { background:#f2f6f4; border-top:0; color:#5b6c6e; font-size:10px; text-transform:uppercase; letter-spacing:.3px; }
  .spp-table td { vertical-align:middle; }
  .spp-progress { height:8px; min-width:90px; background:#e8efed; border-radius:8px; overflow:hidden; }
  .spp-progress span { display:block; height:100%; background:#168b75; }
  .spp-number { color:#087f72; font-weight:800; white-space:nowrap; }
  .spp-share-box { display:flex; gap:8px; }
  .spp-share-box input { min-width:0; }
  .spp-empty { padding:38px 20px; text-align:center; color:#718083; }
  @media(max-width:767px) { .spp-hero { padding:20px; align-items:flex-start; } .spp-mark { display:none; } .spp-panel-body { padding:14px; } .spp-panel-head { padding:14px; } }
</style>

<div class="page-inner spp-page">
  <header class="spp-hero">
    <div><span class="spp-badge">PROJECT PROCUREMENT</span><h2>Surat Permintaan Pembelian</h2><p>Kelola kebutuhan material dan pantau penerimaan di site project.</p></div>
    <i class="fas fa-clipboard-list spp-mark" aria-hidden="true"></i>
  </header>

  <?php if ($alertText !== '') { ?><div class="alert alert-success mt-3"><i class="fas fa-check-circle mr-2"></i><?php echo spp_h($alertText); ?></div><?php } ?>
  <?php if ($errorText !== '') { ?><div class="alert alert-danger mt-3"><?php echo spp_h($errorText); ?></div><?php } ?>

  <section class="spp-panel">
    <div class="spp-panel-head"><h3>Project</h3><span class="text-muted small"><?php echo count($projects); ?> project terdaftar</span></div>
    <div class="spp-panel-body">
      <div class="row align-items-end">
        <div class="col-lg-6">
          <form method="get" action="main.php">
            <input type="hidden" name="module" value="spp">
            <div class="form-group mb-lg-0"><label for="project_id">Pilih project untuk melihat atau membuat SPP</label><select class="form-control" id="project_id" name="project_id" required><option value="">Pilih project</option><?php foreach ($projects as $projectRow) { ?><option value="<?php echo (int) $projectRow['id']; ?>" <?php echo $selectedProject && (int) $selectedProject['id'] === (int) $projectRow['id'] ? 'selected' : ''; ?>><?php echo spp_h($projectRow['nama_project']); ?></option><?php } ?></select></div>
            <button type="submit" class="btn btn-outline-success"><i class="fas fa-folder-open mr-1"></i>Buka Project</button>
          </form>
        </div>
        <div class="col-lg-6 mt-3 mt-lg-0">
          <?php if ($canManageSpp) { ?>
          <form class="form-row align-items-end" method="post" action="modules/spp/proses.php">
            <input type="hidden" name="csrf_token" value="<?php echo spp_h($_SESSION['csrf_spp']); ?>"><input type="hidden" name="aksi" value="buat_project">
            <div class="form-group col-sm-8 mb-2"><label for="nama_project">Tambah nama project</label><input id="nama_project" class="form-control" name="nama_project" maxlength="160" placeholder="Contoh: Pembangunan Gedung A" required></div>
            <div class="form-group col-sm-4 mb-2"><button type="submit" class="btn btn-success btn-block"><i class="fas fa-plus mr-1"></i>Tambah</button></div>
          </form>
          <?php } ?>
        </div>
      </div>
    </div>
  </section>

  <?php if (!$selectedProject) { ?>
    <section class="spp-panel spp-empty"><i class="fas fa-hand-point-up fa-2x mb-3"></i><div>Pilih project terlebih dahulu untuk membuat dan melihat SPP.</div></section>
  <?php } else { ?>
    <?php if ($canManageSpp) { ?>
    <section class="spp-panel">
      <div class="spp-panel-head"><h3><?php echo spp_h($selectedProject['nama_project']); ?></h3><span class="spp-badge">PROJECT AKTIF</span></div>
      <div class="spp-panel-body">
        <h4 class="mb-3" style="font-size:15px;font-weight:800">Buat SPP Baru</h4>
        <form action="modules/spp/proses.php" method="post" id="form-spp">
          <input type="hidden" name="csrf_token" value="<?php echo spp_h($_SESSION['csrf_spp']); ?>"><input type="hidden" name="aksi" value="buat_spp"><input type="hidden" name="project_id" value="<?php echo (int) $selectedProject['id']; ?>">
          <div class="form-row">
            <div class="form-group col-md-6"><label for="nomor_spp">Nomor SPP (diisi manual)</label><input id="nomor_spp" class="form-control" name="nomor_spp" maxlength="80" placeholder="001/JP/SPP/2026" pattern="[A-Za-z0-9-]+(/[A-Za-z0-9-]+){2,4}" title="Contoh format: 001/JP/SPP/2026" required><small class="form-text text-muted">Gunakan format seperti 001/JP/SPP/2026. Nomor harus unik.</small></div>
            <div class="form-group col-md-3"><label for="tanggal_spp">Tanggal</label><input id="tanggal_spp" class="form-control" type="date" name="tanggal" value="<?php echo date('Y-m-d'); ?>" required></div>
          </div>
          <div class="table-responsive">
            <table class="table table-bordered spp-table" id="tabel-spp">
              <thead><tr><th style="width:55px">No</th><th style="min-width:180px">Nama Item</th><th style="min-width:180px">Ukuran & Spesifikasi</th><th style="width:120px">Jumlah</th><th style="width:110px">Satuan</th><th style="min-width:160px">Untuk Pekerjaan</th><th style="min-width:160px">Keterangan</th><th style="width:55px">Aksi</th></tr></thead>
              <tbody><tr>
                <td class="text-center nomor-item">1</td>
                <td><input class="form-control" name="nama_item[]" maxlength="160" required></td>
                <td><input class="form-control" name="spesifikasi[]" maxlength="255" placeholder="Ukuran / spesifikasi"></td>
                <td><input class="form-control" type="number" name="jumlah_diminta[]" min="0.001" step="0.001" required></td>
                <td><input class="form-control" name="satuan[]" maxlength="40" placeholder="pcs, kg, m" required></td>
                <td><input class="form-control" name="untuk_pekerjaan[]" maxlength="160"></td>
                <td><input class="form-control" name="keterangan[]" maxlength="255"></td>
                <td class="text-center"><button type="button" class="btn btn-light text-danger hapus-item" title="Hapus item" aria-label="Hapus item"><i class="fas fa-trash"></i></button></td>
              </tr></tbody>
            </table>
          </div>
          <div class="d-flex flex-wrap justify-content-between align-items-center"><button type="button" id="tambah-item" class="btn btn-outline-success mb-2"><i class="fas fa-plus mr-1"></i>Tambah item</button><button type="submit" class="btn btn-success mb-2"><i class="fas fa-save mr-1"></i>Simpan SPP</button></div>
        </form>
      </div>
    </section>
    <?php } ?>

    <?php if ($selectedSpp) { ?>
      <?php
      $requestedTotal = 0.0;
      $receivedTotal = 0.0;
      foreach ($sppItems as $itemRow) {
        $requestedTotal += (float) $itemRow['jumlah_diminta'];
        $receivedTotal += (float) $itemRow['jumlah_diterima'];
      }
      $receivedPercent = $requestedTotal > 0 ? min(100, round(($receivedTotal / $requestedTotal) * 100, 1)) : 0;
      $shareUrl = $baseUrl . ($appPath === '/' ? '' : $appPath) . '/modules/spp/pdf.php?token=' . rawurlencode($selectedSpp['share_token']);
      ?>
      <section class="spp-panel">
        <div class="spp-panel-head"><div><h3>Detail <?php echo spp_h($selectedSpp['nomor_spp']); ?></h3><span class="text-muted small">Tanggal <?php echo date('d-m-Y', strtotime($selectedSpp['tanggal'])); ?> · <?php echo spp_h($selectedProject['nama_project']); ?></span></div><a class="btn btn-outline-success btn-sm" target="_blank" rel="noopener" href="<?php echo spp_h($shareUrl); ?>"><i class="fas fa-file-pdf mr-1"></i>Lihat PDF</a></div>
        <div class="spp-panel-body">
          <label for="tautan-spp">Link PDF online</label>
          <div class="spp-share-box mb-4"><input id="tautan-spp" class="form-control" readonly value="<?php echo spp_h($shareUrl); ?>"><button type="button" class="btn btn-outline-secondary" id="salin-link" title="Salin tautan" aria-label="Salin tautan"><i class="fas fa-copy"></i></button></div>
          <div class="d-flex align-items-center justify-content-between flex-wrap mb-3"><div><strong>Penerimaan di site project</strong><div class="small text-muted">Total diterima <?php echo number_format($receivedTotal, 3, ',', '.'); ?> dari <?php echo number_format($requestedTotal, 3, ',', '.'); ?> unit jumlah</div></div><strong class="text-success" style="font-size:22px"><?php echo number_format($receivedPercent, 1, ',', '.'); ?>%</strong></div>
          <div class="spp-progress mb-4" aria-label="Progres penerimaan"><span style="width:<?php echo $receivedPercent; ?>%"></span></div>
          <?php if ($canManageSpp) { ?><form action="modules/spp/proses.php" method="post"><?php } ?>
            <?php if ($canManageSpp) { ?>
            <input type="hidden" name="csrf_token" value="<?php echo spp_h($_SESSION['csrf_spp']); ?>"><input type="hidden" name="aksi" value="update_penerimaan"><input type="hidden" name="spp_id" value="<?php echo (int) $selectedSpp['id']; ?>"><input type="hidden" name="project_id" value="<?php echo (int) $selectedProject['id']; ?>">
            <?php } ?>
            <div class="table-responsive"><table class="table table-bordered spp-table"><thead><tr><th>No</th><th>Nama Item</th><th>Ukuran & Spesifikasi</th><th>Diminta</th><th>Diterima di Site</th><th>Satuan</th><th>Untuk Pekerjaan</th><th>Keterangan</th><th>Progres</th></tr></thead><tbody>
            <?php foreach ($sppItems as $itemRow) { $itemPercent = (float) $itemRow['jumlah_diminta'] > 0 ? min(100, round(((float) $itemRow['jumlah_diterima'] / (float) $itemRow['jumlah_diminta']) * 100, 1)) : 0; ?>
              <tr><td><?php echo (int) $itemRow['nomor_item']; ?><?php if ($canManageSpp) { ?><input type="hidden" name="item_id[]" value="<?php echo (int) $itemRow['id']; ?>"><?php } ?></td><td><?php echo spp_h($itemRow['nama_item']); ?></td><td><?php echo spp_h($itemRow['spesifikasi'] ?: '-'); ?></td><td><?php echo number_format((float) $itemRow['jumlah_diminta'], 3, ',', '.'); ?></td><td><?php if ($canManageSpp) { ?><input class="form-control" type="number" name="jumlah_diterima[]" min="0" max="<?php echo spp_h($itemRow['jumlah_diminta']); ?>" step="0.001" value="<?php echo spp_h($itemRow['jumlah_diterima']); ?>" required><?php } else { echo number_format((float) $itemRow['jumlah_diterima'], 3, ',', '.'); } ?></td><td><?php echo spp_h($itemRow['satuan']); ?></td><td><?php echo spp_h($itemRow['untuk_pekerjaan'] ?: '-'); ?></td><td><?php echo spp_h($itemRow['keterangan'] ?: '-'); ?></td><td><?php echo number_format($itemPercent, 1, ',', '.'); ?>%</td></tr>
            <?php } ?>
            </tbody></table></div>
            <?php if ($canManageSpp) { ?><button type="submit" class="btn btn-success"><i class="fas fa-truck-loading mr-1"></i>Simpan Update Penerimaan</button></form><?php } ?>
        </div>
      </section>
      <script>
        (function () {
          var copyButton = document.getElementById('salin-link');
          var linkField = document.getElementById('tautan-spp');
          if (copyButton && linkField) {
            copyButton.addEventListener('click', function () {
              linkField.select();
              if (navigator.clipboard && window.isSecureContext) {
                navigator.clipboard.writeText(linkField.value).then(function () { copyButton.innerHTML = '<i class="fas fa-check"></i>'; });
              } else {
                document.execCommand('copy');
                copyButton.innerHTML = '<i class="fas fa-check"></i>';
              }
            });
          }
        })();
      </script>
    <?php } ?>

    <?php
    $listStatement = mysqli_prepare($mysqli, 'SELECT s.id, s.nomor_spp, s.tanggal, s.share_token, COUNT(i.id) AS jumlah_item, COALESCE(SUM(i.jumlah_diminta), 0) AS total_diminta, COALESCE(SUM(i.jumlah_diterima), 0) AS total_diterima FROM tbl_spp s LEFT JOIN tbl_spp_item i ON i.spp_id = s.id WHERE s.project_id = ? GROUP BY s.id ORDER BY s.id DESC');
    mysqli_stmt_bind_param($listStatement, 'i', $projectId);
    mysqli_stmt_execute($listStatement);
    $listResult = mysqli_stmt_get_result($listStatement);
    ?>
    <section class="spp-panel">
      <div class="spp-panel-head"><h3>SPP Project</h3><span class="text-muted small">Dokumen pada project terpilih</span></div>
      <div class="table-responsive"><table class="table table-hover spp-table mb-0"><thead><tr><th>Nomor SPP</th><th>Tanggal</th><th>Item</th><th style="min-width:155px">Penerimaan</th><th class="text-right">Aksi</th></tr></thead><tbody>
        <?php if (mysqli_num_rows($listResult) === 0) { ?><tr><td colspan="5" class="text-center text-muted py-4">Belum ada SPP untuk project ini.</td></tr><?php } ?>
        <?php while ($listRow = mysqli_fetch_assoc($listResult)) { $percent = (float) $listRow['total_diminta'] > 0 ? min(100, round(((float) $listRow['total_diterima'] / (float) $listRow['total_diminta']) * 100, 1)) : 0; ?>
          <tr><td><span class="spp-number"><?php echo spp_h($listRow['nomor_spp']); ?></span></td><td><?php echo date('d-m-Y', strtotime($listRow['tanggal'])); ?></td><td><?php echo (int) $listRow['jumlah_item']; ?> item</td><td><div class="d-flex align-items-center" style="gap:8px"><div class="spp-progress flex-grow-1"><span style="width:<?php echo $percent; ?>%"></span></div><span class="small font-weight-bold"><?php echo number_format($percent, 1, ',', '.'); ?>%</span></div></td><td class="text-right text-nowrap"><a class="btn btn-sm btn-outline-primary" href="?module=spp&amp;project_id=<?php echo (int) $projectId; ?>&amp;spp_id=<?php echo (int) $listRow['id']; ?>" title="Detail dan update penerimaan"><i class="fas fa-box-open"></i></a> <a class="btn btn-sm btn-outline-success" href="modules/spp/pdf.php?token=<?php echo rawurlencode($listRow['share_token']); ?>" target="_blank" rel="noopener" title="Lihat PDF"><i class="fas fa-file-pdf"></i></a></td></tr>
        <?php } ?>
      </tbody></table></div>
    </section>
    <?php mysqli_stmt_close($listStatement); ?>
  <?php } ?>
</div>

<script>
  (function ($) {
    var $body = $('#tabel-spp tbody');
    function renumberItems() {
      $body.find('tr').each(function (index) { $(this).find('.nomor-item').text(index + 1); });
      $body.find('.hapus-item').prop('disabled', $body.find('tr').length === 1);
    }
    $('#tambah-item').on('click', function () {
      var $row = $body.find('tr:first').clone();
      $row.find('input').val('');
      $body.append($row);
      renumberItems();
    });
    $body.on('click', '.hapus-item', function () {
      if ($body.find('tr').length > 1) { $(this).closest('tr').remove(); renumberItems(); }
    });
    renumberItems();
  })(jQuery);
</script>