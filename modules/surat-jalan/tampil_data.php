<?php
if (basename($_SERVER['PHP_SELF']) === basename(__FILE__)) {
  header('location: ../../404.html');
  exit;
}

require_once __DIR__ . '/schema.php';
surat_jalan_ensure_schema($mysqli);

if (empty($_SESSION['csrf_surat_jalan'])) {
  $_SESSION['csrf_surat_jalan'] = bin2hex(random_bytes(32));
}

function surat_jalan_h($value)
{
  return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

$settings_result = mysqli_query($mysqli, 'SELECT * FROM tbl_surat_jalan_settings WHERE id = 1');
$settings = mysqli_fetch_assoc($settings_result);
$canManageDocuments = isset($_SESSION['hak_akses']) && in_array($_SESSION['hak_akses'], array('Administrator', 'Admin Gudang'), true);
$barang_result = mysqli_query($mysqli, 'SELECT id_barang, nama_barang FROM tbl_barang ORDER BY nama_barang ASC');
$barang_options = array();
while ($barang = mysqli_fetch_assoc($barang_result)) {
  $barang_options[] = $barang;
}
$surat_result = mysqli_query($mysqli, 'SELECT id, nomor_surat, tanggal, pemohon, tujuan FROM tbl_surat_jalan ORDER BY id DESC LIMIT 100');
$current_module = isset($_GET['module']) ? $_GET['module'] : 'surat_jalan';
$editDocument = null;
$editItems = array();
if (isset($_GET['edit'])) {
  if (!$canManageDocuments) {
    header('location: main.php?module=surat_jalan&error=' . urlencode('Anda tidak memiliki izin mengubah surat jalan.'));
    exit;
  }
  $editId = filter_var($_GET['edit'], FILTER_VALIDATE_INT);
  if (!$editId || $editId < 1) {
    header('location: main.php?module=surat_jalan&error=' . urlencode('Surat jalan tidak valid.'));
    exit;
  }
  $editStatement = mysqli_prepare($mysqli, 'SELECT * FROM tbl_surat_jalan WHERE id = ? LIMIT 1');
  mysqli_stmt_bind_param($editStatement, 'i', $editId);
  mysqli_stmt_execute($editStatement);
  $editResult = mysqli_stmt_get_result($editStatement);
  $editDocument = mysqli_fetch_assoc($editResult);
  mysqli_stmt_close($editStatement);
  if (!$editDocument) {
    header('location: main.php?module=surat_jalan&error=' . urlencode('Surat jalan tidak ditemukan.'));
    exit;
  }
  $itemsStatement = mysqli_prepare($mysqli, 'SELECT nama_barang, jumlah, keterangan FROM tbl_surat_jalan_detail WHERE surat_jalan_id = ? ORDER BY id ASC');
  mysqli_stmt_bind_param($itemsStatement, 'i', $editId);
  mysqli_stmt_execute($itemsStatement);
  $itemsResult = mysqli_stmt_get_result($itemsStatement);
  while ($editItem = mysqli_fetch_assoc($itemsResult)) {
    $editItems[] = $editItem;
  }
  mysqli_stmt_close($itemsStatement);
}
$alert_message = isset($_GET['pesan']) && $_GET['pesan'] === '1' ? 'Pengaturan surat jalan berhasil disimpan.' : '';
if (isset($_GET['pesan']) && $_GET['pesan'] === '2') {
  $alert_message = 'Surat jalan berhasil dibuat dan nomor otomatis sudah diterbitkan.';
}
if (isset($_GET['pesan']) && $_GET['pesan'] === '3') {
  $alert_message = 'Perubahan surat jalan berhasil disimpan.';
}
if (isset($_GET['pesan']) && $_GET['pesan'] === '4') {
  $alert_message = 'Surat jalan berhasil dihapus.';
}
?>
<style>
  .sj-page { --sj-ink:#18323a; --sj-green:#147d72; --sj-line:#dce7e5; --sj-muted:#6d7d80; color:var(--sj-ink); }
  .sj-hero { background:linear-gradient(110deg,#e7f3ef 0%,#f7f6ee 62%,#f4e8da 100%); border-radius:8px; padding:25px 28px; display:flex; justify-content:space-between; align-items:center; gap:20px; }
  .sj-hero h2 { font-size:24px; font-weight:800; margin:0 0 5px; color:#18323a; }
  .sj-hero p { margin:0; color:#52666a; }
  .sj-hero-mark { font-size:36px; color:#147d72; opacity:.85; }
  .sj-tabs { display:flex; gap:8px; border-bottom:1px solid var(--sj-line); margin:22px 0 18px; }
  .sj-tabs a { padding:10px 14px; color:#627579; text-decoration:none; border-bottom:3px solid transparent; font-weight:700; }
  .sj-tabs a.active { color:var(--sj-green); border-color:var(--sj-green); }
  .sj-panel { background:#fff; border:1px solid var(--sj-line); border-radius:7px; box-shadow:0 5px 18px rgba(24,50,58,.045); margin-bottom:18px; }
  .sj-panel-head { padding:18px 20px; border-bottom:1px solid #edf1ef; display:flex; justify-content:space-between; align-items:center; gap:12px; }
  .sj-panel-head h3 { font-size:16px; font-weight:800; margin:0; }
  .sj-panel-body { padding:20px; }
  .sj-page label { font-weight:700; color:#40575b; }
  .sj-page .form-control { border-color:#d9e3e1; }
  .sj-page .form-control:focus { border-color:#48a99a; box-shadow:0 0 0 3px rgba(20,125,114,.11); }
  .sj-table th { background:#f3f7f6; color:#53676a; text-transform:uppercase; font-size:11px; letter-spacing:.4px; border-top:0; }
  .sj-table td { vertical-align:middle; }
  .sj-badge { color:#147d72; background:#e7f4f0; border-radius:20px; padding:5px 10px; font-weight:700; font-size:11px; }
  .sj-logo-preview { max-width:150px; max-height:70px; object-fit:contain; border:1px solid var(--sj-line); padding:7px; border-radius:5px; }
  @media (max-width:767px) { .sj-hero { align-items:flex-start; padding:20px; } .sj-hero-mark { display:none; } .sj-panel-body { padding:15px; } .sj-panel-head { padding:15px; } }
</style>

<div class="page-inner sj-page">
  <div class="sj-hero">
    <div>
      <span class="sj-badge">DOKUMEN PENGIRIMAN</span>
      <h2 class="mt-2">Surat Jalan</h2>
      <p>Buat, atur identitas dokumen, dan cetak surat jalan siap kirim.</p>
    </div>
    <i class="fas fa-file-signature sj-hero-mark" aria-hidden="true"></i>
  </div>

  <?php if ($alert_message !== '') { ?>
    <div class="alert alert-success mt-3" role="status"><i class="fas fa-check-circle mr-2"></i><?php echo surat_jalan_h($alert_message); ?></div>
  <?php } ?>
  <?php if (isset($_GET['error'])) { ?>
    <div class="alert alert-danger mt-3" role="alert"><?php echo surat_jalan_h($_GET['error']); ?></div>
  <?php } ?>

  <nav class="sj-tabs" aria-label="Navigasi surat jalan">
    <a class="<?php echo $current_module !== 'surat_jalan_settings' ? 'active' : ''; ?>" href="?module=surat_jalan"><i class="fas fa-list mr-2"></i>Daftar & Buat</a>
    <a class="<?php echo $current_module === 'surat_jalan_settings' ? 'active' : ''; ?>" href="?module=surat_jalan_settings"><i class="fas fa-sliders-h mr-2"></i>Pengaturan</a>
  </nav>

  <?php if ($current_module === 'surat_jalan_settings') { ?>
    <section class="sj-panel">
      <div class="sj-panel-head"><h3>Identitas & Penomoran</h3><span class="sj-badge">Nomor berikutnya: <?php echo surat_jalan_h($settings['nomor_prefix'] . '/' . date('Y') . '/' . str_pad($settings['next_number'], (int) $settings['number_digits'], '0', STR_PAD_LEFT)); ?></span></div>
      <div class="sj-panel-body">
        <form action="modules/surat-jalan/proses.php" method="post" enctype="multipart/form-data">
          <input type="hidden" name="csrf_token" value="<?php echo surat_jalan_h($_SESSION['csrf_surat_jalan']); ?>">
          <input type="hidden" name="aksi" value="pengaturan">
          <div class="form-row">
            <div class="form-group col-md-6"><label for="header_title">Tulisan utama header</label><input id="header_title" class="form-control" name="header_title" maxlength="160" value="<?php echo surat_jalan_h($settings['header_title']); ?>" required></div>
            <div class="form-group col-md-6"><label for="header_subtitle">Tulisan tambahan header</label><textarea id="header_subtitle" class="form-control" name="header_subtitle" rows="2" maxlength="255" placeholder="Alamat, telepon, atau keterangan perusahaan"><?php echo surat_jalan_h($settings['header_subtitle']); ?></textarea></div>
          </div>
          <div class="form-row">
            <div class="form-group col-md-4"><label for="nomor_prefix">Prefix nomor surat</label><input id="nomor_prefix" class="form-control" name="nomor_prefix" maxlength="30" value="<?php echo surat_jalan_h($settings['nomor_prefix']); ?>" required><small class="form-text text-muted">Contoh hasil: SJ/2026/00001</small></div>
            <div class="form-group col-md-4"><label for="next_number">Nomor urut berikutnya</label><input id="next_number" class="form-control" name="next_number" type="number" min="1" max="999999999" value="<?php echo (int) $settings['next_number']; ?>" required><small class="form-text text-muted">Nilai ini bertambah otomatis setiap surat dibuat.</small></div>
            <div class="form-group col-md-4"><label for="number_digits">Panjang digit urut</label><input id="number_digits" class="form-control" name="number_digits" type="number" min="3" max="9" value="<?php echo (int) $settings['number_digits']; ?>" required></div>
          </div>
          <div class="form-group">
            <label for="logo">Logo surat jalan (PNG/JPG, maks. 2 MB)</label>
            <input id="logo" class="form-control-file" type="file" name="logo" accept="image/png,image/jpeg">
            <?php if (!extension_loaded('gd')) { ?><small class="form-text text-warning">Ekstensi PHP GD belum aktif. PDF tetap dapat dibuat, tetapi logo PNG tidak tercetak. Aktifkan GD di php.ini XAMPP dan restart Apache, atau gunakan logo JPG.</small><?php } ?>
            <?php if (!empty($settings['logo_path']) && is_file(dirname(__DIR__, 2) . '/' . $settings['logo_path'])) { ?>
              <div class="mt-3"><img class="sj-logo-preview" src="<?php echo surat_jalan_h($settings['logo_path']); ?>" alt="Logo aktif"></div>
            <?php } ?>
          </div>
          <button type="submit" class="btn btn-success"><i class="fas fa-save mr-2"></i>Simpan Pengaturan</button>
        </form>
      </div>
    </section>
  <?php } else { ?>
    <section class="sj-panel">
      <div class="sj-panel-head"><h3><?php echo $editDocument ? 'Ubah Surat Jalan' : 'Informasi Pengiriman'; ?></h3><span class="text-muted small"><?php echo $editDocument ? 'Nomor surat ' . surat_jalan_h($editDocument['nomor_surat']) . ' tidak berubah' : 'Nomor surat diterbitkan saat disimpan'; ?></span></div>
      <div class="sj-panel-body">
        <form action="modules/surat-jalan/proses.php" method="post" id="form-surat-jalan">
          <input type="hidden" name="csrf_token" value="<?php echo surat_jalan_h($_SESSION['csrf_surat_jalan']); ?>">
          <input type="hidden" name="aksi" value="<?php echo $editDocument ? 'ubah' : 'buat'; ?>">
          <?php if ($editDocument) { ?><input type="hidden" name="document_id" value="<?php echo (int) $editDocument['id']; ?>"><?php } ?>
          <div class="form-row">
            <div class="form-group col-md-4"><label for="tanggal">Tanggal</label><input id="tanggal" class="form-control" type="date" name="tanggal" value="<?php echo surat_jalan_h($editDocument ? $editDocument['tanggal'] : date('Y-m-d')); ?>" required></div>
            <div class="form-group col-md-4"><label for="pemohon">Yang mengajukan</label><input id="pemohon" class="form-control" name="pemohon" maxlength="120" value="<?php echo surat_jalan_h($editDocument ? $editDocument['pemohon'] : ($_SESSION['nama_user'] ?? $_SESSION['username'])); ?>" required></div>
            <div class="form-group col-md-4"><label for="driver">Nama driver</label><input id="driver" class="form-control" name="driver" maxlength="120" value="<?php echo surat_jalan_h($editDocument ? $editDocument['driver'] : ''); ?>" required></div>
          </div>
          <div class="form-row">
            <div class="form-group col-md-6"><label for="manager">Mengetahui (Manager)</label><input id="manager" class="form-control" name="manager" maxlength="120" value="<?php echo surat_jalan_h($editDocument ? $editDocument['manager'] : ''); ?>" required></div>
            <div class="form-group col-md-6"><label for="tujuan">Tujuan / alamat pengiriman</label><input id="tujuan" class="form-control" name="tujuan" maxlength="255" placeholder="Opsional" value="<?php echo surat_jalan_h($editDocument ? $editDocument['tujuan'] : ''); ?>"></div>
          </div>
          <div class="d-flex align-items-center justify-content-between flex-wrap mb-2">
            <label class="mb-2">Daftar barang</label>
            <button id="tambah-barang" type="button" class="btn btn-outline-success btn-sm mb-2"><i class="fas fa-plus mr-1"></i>Tambah baris</button>
          </div>
          <div class="table-responsive">
            <table class="table table-bordered sj-table" id="tabel-barang">
              <thead><tr><th style="width:54px">No</th><th>Nama Barang</th><th style="width:150px">Jumlah Barang</th><th>Keterangan</th><th style="width:60px">Aksi</th></tr></thead>
              <tbody>
                <?php if ($editDocument && count($editItems) > 0) { foreach ($editItems as $editItem) { ?>
                <tr>
                  <td class="text-center nomor-baris"><?php echo count($editItems); ?></td>
                  <td><select class="form-control" name="barang[]" required><option value="">Pilih barang</option><?php foreach ($barang_options as $barang) { ?><option value="<?php echo surat_jalan_h($barang['id_barang']); ?>" <?php echo $barang['nama_barang'] === $editItem['nama_barang'] ? 'selected' : ''; ?>><?php echo surat_jalan_h($barang['nama_barang']); ?></option><?php } ?><?php if (!in_array($editItem['nama_barang'], array_column($barang_options, 'nama_barang'), true)) { ?><option value="" selected><?php echo surat_jalan_h($editItem['nama_barang']); ?> (barang sudah tidak tersedia)</option><?php } ?></select></td>
                  <td><input class="form-control" type="number" name="jumlah[]" min="0.01" step="0.01" value="<?php echo surat_jalan_h($editItem['jumlah']); ?>" required></td>
                  <td><input class="form-control" name="keterangan[]" maxlength="255" value="<?php echo surat_jalan_h($editItem['keterangan']); ?>" placeholder="Catatan barang"></td>
                  <td class="text-center"><button class="btn btn-light text-danger hapus-baris" type="button" aria-label="Hapus baris" title="Hapus baris"><i class="fas fa-trash"></i></button></td>
                </tr>
                <?php } } else { ?>
                <tr>
                  <td class="text-center nomor-baris">1</td>
                  <td><select class="form-control" name="barang[]" required><option value="">Pilih barang</option><?php foreach ($barang_options as $barang) { ?><option value="<?php echo surat_jalan_h($barang['id_barang']); ?>"><?php echo surat_jalan_h($barang['nama_barang']); ?></option><?php } ?></select></td>
                  <td><input class="form-control" type="number" name="jumlah[]" min="0.01" step="0.01" placeholder="0" required></td>
                  <td><input class="form-control" name="keterangan[]" maxlength="255" placeholder="Catatan barang"></td>
                  <td class="text-center"><button class="btn btn-light text-danger hapus-baris" type="button" aria-label="Hapus baris" title="Hapus baris"><i class="fas fa-trash"></i></button></td>
                </tr>
                <?php } ?>
              </tbody>
            </table>
          </div>
          <?php if (count($barang_options) === 0) { ?><div class="alert alert-warning">Belum ada data barang. Tambahkan barang pada menu Barang terlebih dahulu.</div><?php } ?>
          <button type="submit" class="btn btn-success" <?php echo count($barang_options) === 0 ? 'disabled' : ''; ?>><i class="fas <?php echo $editDocument ? 'fa-save' : 'fa-file-alt'; ?> mr-2"></i><?php echo $editDocument ? 'Simpan Perubahan' : 'Buat Surat Jalan'; ?></button>
          <?php if ($editDocument) { ?><a href="?module=surat_jalan" class="btn btn-light ml-2">Batal</a><?php } ?>
        </form>
      </div>
    </section>

    <section class="sj-panel">
      <div class="sj-panel-head"><h3>Riwayat Surat Jalan</h3><span class="text-muted small">100 dokumen terbaru</span></div>
      <div class="table-responsive">
        <table class="table table-hover sj-table mb-0">
          <thead><tr><th>Nomor</th><th>Tanggal</th><th>Pemohon</th><th>Tujuan</th><th class="text-right">Dokumen</th></tr></thead>
          <tbody>
            <?php if (mysqli_num_rows($surat_result) === 0) { ?><tr><td colspan="5" class="text-center text-muted py-4">Belum ada surat jalan.</td></tr><?php } ?>
            <?php while ($surat = mysqli_fetch_assoc($surat_result)) { ?>
              <tr><td><strong><?php echo surat_jalan_h($surat['nomor_surat']); ?></strong></td><td><?php echo date('d-m-Y', strtotime($surat['tanggal'])); ?></td><td><?php echo surat_jalan_h($surat['pemohon']); ?></td><td><?php echo surat_jalan_h($surat['tujuan'] ?: '-'); ?></td><td class="text-right text-nowrap"><?php if ($canManageDocuments) { ?><a class="btn btn-sm btn-outline-primary" href="?module=surat_jalan&amp;edit=<?php echo (int) $surat['id']; ?>" title="Ubah surat jalan"><i class="fas fa-edit"></i></a> <form class="d-inline" action="modules/surat-jalan/proses.php" method="post" onsubmit="return confirm('Hapus surat jalan <?php echo surat_jalan_h($surat['nomor_surat']); ?> beserta semua rincian barangnya?');"><input type="hidden" name="csrf_token" value="<?php echo surat_jalan_h($_SESSION['csrf_surat_jalan']); ?>"><input type="hidden" name="aksi" value="hapus"><input type="hidden" name="document_id" value="<?php echo (int) $surat['id']; ?>"><button type="submit" class="btn btn-sm btn-outline-danger" title="Hapus surat jalan"><i class="fas fa-trash"></i></button></form> <?php } ?><a class="btn btn-sm btn-outline-success" href="modules/surat-jalan/cetak.php?id=<?php echo (int) $surat['id']; ?>" target="_blank" rel="noopener"><i class="fas fa-print mr-1"></i>Cetak / PDF</a></td></tr>
            <?php } ?>
          </tbody>
        </table>
      </div>
    </section>
  <?php } ?>
</div>

<script>
  (function ($) {
    var $body = $('#tabel-barang tbody');
    function refreshRows() {
      $body.find('tr').each(function (index) {
        $(this).find('.nomor-baris').text(index + 1);
        $(this).find('.hapus-baris').prop('disabled', $body.find('tr').length === 1);
      });
    }
    $('#tambah-barang').on('click', function () {
      var $row = $body.find('tr:first').clone();
      $row.find('select').val('');
      $row.find('input').val('');
      $body.append($row);
      refreshRows();
    });
    $body.on('click', '.hapus-baris', function () {
      if ($body.find('tr').length > 1) {
        $(this).closest('tr').remove();
        refreshRows();
      }
    });
    refreshRows();
  })(jQuery);
</script>