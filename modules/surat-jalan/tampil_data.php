<?php
if (basename($_SERVER['PHP_SELF']) === basename(__FILE__)) {
  header('location: ../../404.html');
  exit;
}
require_once __DIR__ . '/schema.php';
require_once __DIR__ . '/template.php';
surat_jalan_ensure_schema($mysqli);
if (empty($_SESSION['csrf_surat_jalan'])) {
  $_SESSION['csrf_surat_jalan'] = bin2hex(random_bytes(32));
}
$settings = mysqli_fetch_assoc(mysqli_query($mysqli, 'SELECT * FROM tbl_surat_jalan_settings WHERE id = 1'));
$fields = surat_jalan_parse_json($settings['template_fields'], array());
$tableConfig = surat_jalan_parse_json($settings['table_config'], array('table_index' => -1, 'columns' => array()));
$documents = array();
$documentResult = mysqli_query($mysqli, 'SELECT id, nomor_surat, tanggal, pemohon, template_name, dibuat_oleh, dibuat_pada FROM tbl_surat_jalan ORDER BY id DESC LIMIT 100');
while ($row = mysqli_fetch_assoc($documentResult)) {
  $documents[] = $row;
}
$barang = array();
$barangResult = mysqli_query($mysqli, 'SELECT a.nama_barang, b.nama_satuan FROM tbl_barang a LEFT JOIN tbl_satuan b ON b.id_satuan = a.satuan ORDER BY a.nama_barang ASC');
while ($row = mysqli_fetch_assoc($barangResult)) {
  $barang[] = $row;
}
$errorText = isset($_GET['error']) ? trim((string) $_GET['error']) : '';
$successText = isset($_GET['pesan']) ? trim((string) $_GET['pesan']) : '';
$documentId = isset($_GET['document_id']) ? (int) $_GET['document_id'] : 0;
?>
<style>
.sj-page{--ink:#20343a;--green:#087f72;--line:#dce6e3;color:var(--ink)}.sj-hero{background:linear-gradient(112deg,#e2f1eb,#f5f4e9 56%,#f2e4d4);padding:26px 28px;border-radius:10px;display:flex;justify-content:space-between;align-items:center}.sj-hero h2{margin:8px 0 4px;font-size:25px;font-weight:800}.sj-hero p{margin:0;color:#607173}.sj-badge{display:inline-block;background:#e4f3ed;color:var(--green);padding:5px 10px;border-radius:16px;font-size:11px;font-weight:800}.sj-mark{font-size:38px;color:var(--green)}.sj-panel{background:#fff;border:1px solid var(--line);border-radius:9px;box-shadow:0 5px 18px rgba(32,52,58,.045);margin-top:18px}.sj-head{padding:17px 20px;border-bottom:1px solid #edf1ef;display:flex;justify-content:space-between;gap:12px;align-items:center}.sj-head h3{margin:0;font-size:16px;font-weight:800}.sj-body{padding:20px}.sj-page label{font-weight:700;color:#40565a}.sj-page .form-control{border-color:#d8e2df}.sj-field{border:1px solid #dce6e3;border-radius:8px;padding:12px;margin-bottom:10px}.sj-table th{background:#f2f6f4;border-top:0;font-size:10px;text-transform:uppercase;letter-spacing:.3px}.sj-table td{vertical-align:middle}.sj-empty{padding:32px;text-align:center;color:#718083}.sj-help{font-size:12px;color:#718083}.sj-chip{display:inline-block;background:#edf6f3;color:#087f72;border-radius:14px;padding:4px 8px;margin:2px;font-size:11px}@media(max-width:767px){.sj-hero{padding:20px;align-items:flex-start}.sj-mark{display:none}.sj-body{padding:14px}}
</style>
<div class="page-inner sj-page">
  <header class="sj-hero"><div><span class="sj-badge">DOCUMENT AUTOMATION</span><h2>Surat Jalan Generator</h2><p>Gunakan template Word Anda untuk membuat surat jalan siap cetak secara konsisten.</p></div><i class="fas fa-file-signature sj-mark" aria-hidden="true"></i></header>
  <?php if ($successText !== '') { ?><div class="alert alert-success mt-3"><i class="fas fa-check-circle mr-2"></i><?php echo surat_jalan_h($successText); ?><?php if ($documentId > 0) { ?> <a class="alert-link" href="modules/surat-jalan/cetak.php?id=<?php echo $documentId; ?>">Download PDF</a><?php } ?></div><?php } ?>
  <?php if ($errorText !== '') { ?><div class="alert alert-danger mt-3"><i class="fas fa-exclamation-circle mr-2"></i><?php echo surat_jalan_h($errorText); ?></div><?php } ?>
  <section class="sj-panel">
    <div class="sj-head"><h3><i class="fas fa-file-word mr-2 text-success"></i>Template Word</h3><span class="sj-help"><?php echo $settings['template_name'] ? 'Template aktif: ' . surat_jalan_h($settings['template_name']) : 'Belum ada template'; ?></span></div>
    <div class="sj-body">
      <form method="post" action="modules/surat-jalan/proses.php" enctype="multipart/form-data">
        <input type="hidden" name="csrf_token" value="<?php echo surat_jalan_h($_SESSION['csrf_surat_jalan']); ?>"><input type="hidden" name="aksi" value="template">
        <div class="form-row align-items-end"><div class="form-group col-md-8"><label for="template">Upload template surat jalan (.docx)</label><input id="template" type="file" name="template" class="form-control-file" accept=".docx" required><small class="sj-help">Gunakan placeholder seperti <b>{{nomor_surat}}</b> atau kolom titik-titik seperti <b>Tujuan: ........</b>.</small></div><div class="form-group col-md-4"><button class="btn btn-success btn-block" type="submit"><i class="fas fa-upload mr-1"></i>Upload &amp; Baca Template</button></div></div>
      </form>
      <?php if (count($fields) > 0) { ?><div class="mt-2"><strong>Kolom terdeteksi:</strong><div><?php foreach ($fields as $field) { ?><span class="sj-chip"><?php echo surat_jalan_h($field); ?></span><?php } ?></div></div><?php } ?>
      <div class="sj-help mt-2"><?php echo (int) $tableConfig['table_index'] >= 0 ? 'Tabel barang terdeteksi otomatis pada tabel Word #' . ((int) $tableConfig['table_index'] + 1) . '.' : 'Tabel barang belum terdeteksi. Pastikan baris header memuat Barang/Item, Jumlah/Qty, Satuan, atau Keterangan.'; ?></div>
    </div>
  </section>
  <section class="sj-panel">
    <div class="sj-head"><h3><i class="fas fa-sliders-h mr-2 text-success"></i>Nomor Surat</h3><span class="sj-help">Nomor berikutnya dikunci oleh transaksi database agar tidak double.</span></div>
    <div class="sj-body"><form method="post" action="modules/surat-jalan/proses.php" class="form-row align-items-end"><input type="hidden" name="csrf_token" value="<?php echo surat_jalan_h($_SESSION['csrf_surat_jalan']); ?>"><input type="hidden" name="aksi" value="settings"><div class="form-group col-md-4"><label for="nomor_prefix">Prefix</label><input id="nomor_prefix" name="nomor_prefix" class="form-control" value="<?php echo surat_jalan_h($settings['nomor_prefix']); ?>" maxlength="40" required></div><div class="form-group col-md-4"><label for="next_number">Nomor awal / berikutnya</label><input id="next_number" name="next_number" type="number" min="1" max="999999999" class="form-control" value="<?php echo (int) $settings['next_number']; ?>" required></div><div class="form-group col-md-2"><label for="number_digits">Digit</label><input id="number_digits" name="number_digits" type="number" min="1" max="9" class="form-control" value="<?php echo (int) $settings['number_digits']; ?>" required></div><div class="form-group col-md-2"><button class="btn btn-outline-success btn-block" type="submit">Simpan</button></div></form></div>
  </section>
  <?php if (!empty($settings['template_path'])) { ?>
  <section class="sj-panel">
    <div class="sj-head"><h3><i class="fas fa-magic mr-2 text-success"></i>Generate Surat Jalan</h3><span class="sj-help">Pemohon dapat diisi custom untuk kolom tanda tangan “Yang Pemohon”.</span></div>
    <div class="sj-body">
      <form method="post" action="modules/surat-jalan/proses.php" id="sj-generator"><input type="hidden" name="csrf_token" value="<?php echo surat_jalan_h($_SESSION['csrf_surat_jalan']); ?>"><input type="hidden" name="aksi" value="generate">
        <div class="form-row"><div class="form-group col-md-4"><label for="tanggal">Tanggal</label><input id="tanggal" name="tanggal" type="date" class="form-control" value="<?php echo date('Y-m-d'); ?>" required></div><div class="form-group col-md-8"><label for="pemohon">Nama Pemohon (custom untuk TTD)</label><input id="pemohon" name="pemohon" class="form-control" maxlength="160" placeholder="Nama lengkap pemohon" required></div></div>
        <?php if (count($fields) > 0) { ?><h5 class="mt-3">Kolom template</h5><div class="row"><?php foreach ($fields as $key => $label) { ?><div class="form-group col-md-6"><label for="field_<?php echo surat_jalan_h($key); ?>"><?php echo surat_jalan_h($label); ?></label><input id="field_<?php echo surat_jalan_h($key); ?>" name="field[<?php echo surat_jalan_h($key); ?>]" class="form-control" maxlength="500" <?php echo in_array($key, array('nomor_surat', 'tanggal', 'pemohon'), true) ? 'readonly' : ''; ?>></div><?php } ?></div><?php } ?>
        <h5 class="mt-3">Barang yang dibawa</h5><div class="table-responsive"><table class="table sj-table" id="sj-items"><thead><tr><th>Barang</th><th width="130">Jumlah</th><th width="120">Satuan</th><th>Keterangan</th><th width="45"></th></tr></thead><tbody><tr class="sj-row"><td><input name="nama_barang[]" list="barang-list" class="form-control" required></td><td><input name="jumlah[]" type="number" min="0.001" step="0.001" class="form-control" required></td><td><input name="satuan[]" class="form-control"></td><td><input name="keterangan[]" class="form-control"></td><td><button type="button" class="btn btn-sm btn-outline-danger sj-remove" title="Hapus"><i class="fas fa-times"></i></button></td></tr></tbody></table></div><datalist id="barang-list"><?php foreach ($barang as $item) { ?><option value="<?php echo surat_jalan_h($item['nama_barang']); ?>"><?php echo surat_jalan_h($item['nama_satuan']); ?></option><?php } ?></datalist><button type="button" class="btn btn-light btn-sm" id="sj-add"><i class="fas fa-plus mr-1"></i>Tambah barang</button><button type="submit" class="btn btn-success float-right"><i class="fas fa-file-pdf mr-1"></i>Generate &amp; Simpan Surat Jalan</button>
      </form>
    </div>
  </section>
  <?php } ?>
  <section class="sj-panel"><div class="sj-head"><h3><i class="fas fa-history mr-2 text-success"></i>Surat Jalan Tersimpan</h3><span class="sj-help">100 dokumen terakhir</span></div><div class="table-responsive"><table class="table sj-table mb-0"><thead><tr><th>Nomor</th><th>Tanggal</th><th>Pemohon</th><th>Template</th><th>Dibuat</th><th>Aksi</th></tr></thead><tbody><?php if (!$documents) { ?><tr><td colspan="6" class="sj-empty">Belum ada Surat Jalan.</td></tr><?php } ?><?php foreach ($documents as $document) { ?><tr><td><strong><?php echo surat_jalan_h($document['nomor_surat']); ?></strong></td><td><?php echo surat_jalan_h(date('d-m-Y', strtotime($document['tanggal']))); ?></td><td><?php echo surat_jalan_h($document['pemohon']); ?></td><td><?php echo surat_jalan_h($document['template_name']); ?></td><td><?php echo surat_jalan_h($document['dibuat_pada']); ?></td><td><a class="btn btn-sm btn-outline-success" href="modules/surat-jalan/cetak.php?id=<?php echo (int) $document['id']; ?>"><i class="fas fa-download mr-1"></i>PDF</a></td></tr><?php } ?></tbody></table></div></section>
</div>
<script>
(function(){var add=document.getElementById('sj-add'), body=document.querySelector('#sj-items tbody');if(!add||!body){return;}add.addEventListener('click',function(){var row=body.querySelector('.sj-row').cloneNode(true);row.querySelectorAll('input').forEach(function(input){input.value='';});body.appendChild(row);});body.addEventListener('click',function(event){var button=event.target.closest('.sj-remove');if(button&&body.querySelectorAll('.sj-row').length>1){button.closest('.sj-row').remove();}});})();
</script>
