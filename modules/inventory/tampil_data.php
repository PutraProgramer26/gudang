<?php
if (basename($_SERVER['PHP_SELF']) === basename(__FILE__)) {
  header('location: ../../404.html');
  exit;
}

require_once __DIR__ . '/schema.php';
inventory_ensure_schema($mysqli);

function inventory_e($value)
{
  return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

$selectedWarehouse = isset($_GET['gudang_id']) ? filter_var($_GET['gudang_id'], FILTER_VALIDATE_INT) : false;
$selectedWarehouse = $selectedWarehouse && $selectedWarehouse > 0 ? $selectedWarehouse : 0;
$selectedCondition = isset($_GET['kondisi']) && in_array($_GET['kondisi'], array('Baik', 'Rusak'), true) ? $_GET['kondisi'] : '';
$selectedCategory = isset($_GET['kategori']) && in_array($_GET['kategori'], array('APD/Safety', 'Tools', 'Consumable', 'Elektronik'), true) ? $_GET['kategori'] : '';
$editId = isset($_GET['edit']) ? filter_var($_GET['edit'], FILTER_VALIDATE_INT) : false;
$editItem = null;

$warehouseResult = mysqli_query($mysqli, 'SELECT id_gudang, kode_gudang, nama_gudang, penanggung_jawab FROM tbl_gudang ORDER BY nama_gudang ASC');
$warehouses = array();
while ($warehouse = mysqli_fetch_assoc($warehouseResult)) {
  $warehouses[] = $warehouse;
}
if ($selectedWarehouse === 0 && count($warehouses) > 0) {
  $selectedWarehouse = (int) $warehouses[0]['id_gudang'];
}

if ($editId) {
  $editStatement = mysqli_prepare($mysqli, 'SELECT * FROM tbl_inventory WHERE id_inventory = ? LIMIT 1');
  mysqli_stmt_bind_param($editStatement, 'i', $editId);
  mysqli_stmt_execute($editStatement);
  $editResult = mysqli_stmt_get_result($editStatement);
  $editItem = mysqli_fetch_assoc($editResult);
  mysqli_stmt_close($editStatement);
}

$where = 'WHERE i.gudang_id = ' . (int) $selectedWarehouse;
if ($selectedCondition !== '') {
  $where .= " AND i.kondisi = '" . mysqli_real_escape_string($mysqli, $selectedCondition) . "'";
}
if ($selectedCategory !== '') {
  $where .= " AND i.kategori = '" . mysqli_real_escape_string($mysqli, $selectedCategory) . "'";
}
$itemsResult = mysqli_query($mysqli, "SELECT i.*, g.nama_gudang FROM tbl_inventory i INNER JOIN tbl_gudang g ON g.id_gudang = i.gudang_id $where ORDER BY i.kategori ASC, i.nama_item ASC");
$items = array();
$totalQty = 0;
$goodCount = 0;
$damagedCount = 0;
while ($item = mysqli_fetch_assoc($itemsResult)) {
  $items[] = $item;
  $totalQty += (float) $item['qty'];
  if ($item['kondisi'] === 'Baik') {
    $goodCount++;
  } else {
    $damagedCount++;
  }
}

$currentWarehouse = null;
foreach ($warehouses as $warehouse) {
  if ((int) $warehouse['id_gudang'] === $selectedWarehouse) {
    $currentWarehouse = $warehouse;
    break;
  }
}
$isEdit = is_array($editItem);
$formWarehouse = $isEdit ? (int) $editItem['gudang_id'] : $selectedWarehouse;
?>
<style>
  .inventory-hero { background: linear-gradient(135deg, #173f5f 0%, #20639b 55%, #3caea3 100%); border-radius: 0 0 22px 22px; }
  .inventory-card { border: 0; border-radius: 16px; box-shadow: 0 8px 26px rgba(28, 54, 80, .08); }
  .inventory-stat { border-radius: 14px; padding: 18px; background: #fff; box-shadow: 0 5px 18px rgba(28, 54, 80, .07); }
  .inventory-stat .icon { width: 42px; height: 42px; border-radius: 12px; display: inline-flex; align-items: center; justify-content: center; background: #eaf3ff; color: #20639b; }
  .inventory-table thead th { background: #f3f7fa; border: 0; color: #52616b; font-size: 11px; text-transform: uppercase; letter-spacing: .04em; }
  .inventory-table td { vertical-align: middle; }
  .condition-badge { border-radius: 20px; padding: 5px 10px; font-size: 11px; font-weight: 700; }
  .condition-good { color: #18794e; background: #e6f7ee; }
  .condition-bad { color: #b42318; background: #ffebe8; }
  .category-badge { color: #20639b; background: #eaf3ff; border-radius: 8px; padding: 4px 8px; font-size: 11px; }
  @media (max-width: 767px) { .inventory-hero .page-inner { padding: 28px 20px !important; } .inventory-table { min-width: 900px; } }
</style>

<?php if (isset($_GET['pesan'])): ?>
  <div class="alert alert-<?php echo $_GET['pesan'] === 'error' ? 'danger' : 'success'; ?> alert-dismissible fade show">
    <?php echo $_GET['pesan'] === 'error' ? inventory_e(isset($_GET['detail']) ? $_GET['detail'] : 'Operasi tidak dapat diproses.') : 'Data Inventory Gudang berhasil diperbarui.'; ?>
    <button type="button" class="close" data-dismiss="alert">&times;</button>
  </div>
<?php endif; ?>

<div class="inventory-hero">
  <div class="page-inner py-45">
    <div class="d-flex align-items-center justify-content-between flex-wrap">
      <div class="page-header text-white mb-2">
        <h4 class="page-title text-white mb-1"><i class="fas fa-warehouse mr-2"></i>Data Inventory Gudang</h4>
        <p class="mb-0 opacity-8">Kelola stok lintas gudang dengan rekap yang rapi dan siap cetak.</p>
      </div>
      <a href="modules/inventory/cetak.php?gudang_id=<?php echo (int) $selectedWarehouse; ?>" target="_blank" class="btn btn-light btn-round mb-2"><i class="fas fa-file-pdf text-danger mr-2"></i>Rekap PDF</a>
    </div>
  </div>
</div>

<div class="page-inner mt--5">
  <div class="row">
    <div class="col-sm-6 col-xl-3"><div class="inventory-stat mb-3"><span class="icon mr-2"><i class="fas fa-boxes"></i></span><strong><?php echo count($items); ?></strong><div class="text-muted small mt-2">Jenis item tercatat</div></div></div>
    <div class="col-sm-6 col-xl-3"><div class="inventory-stat mb-3"><span class="icon mr-2" style="background:#e8f8f2;color:#178255"><i class="fas fa-cubes"></i></span><strong><?php echo number_format($totalQty, 2, ',', '.'); ?></strong><div class="text-muted small mt-2">Total QTY</div></div></div>
    <div class="col-sm-6 col-xl-3"><div class="inventory-stat mb-3"><span class="icon mr-2" style="background:#fff5da;color:#a86f00"><i class="fas fa-check-circle"></i></span><strong><?php echo $goodCount; ?></strong><div class="text-muted small mt-2">Kondisi baik</div></div></div>
    <div class="col-sm-6 col-xl-3"><div class="inventory-stat mb-3"><span class="icon mr-2" style="background:#ffebe8;color:#b42318"><i class="fas fa-exclamation-triangle"></i></span><strong><?php echo $damagedCount; ?></strong><div class="text-muted small mt-2">Perlu perhatian</div></div></div>
  </div>

  <div class="card inventory-card mb-4">
    <div class="card-body">
      <form method="get" class="row align-items-end">
        <input type="hidden" name="module" value="inventory">
        <div class="form-group col-md-5 mb-2"><label>Gudang aktif</label><select name="gudang_id" class="form-control" onchange="this.form.submit()"><?php foreach ($warehouses as $warehouse): ?><option value="<?php echo (int) $warehouse['id_gudang']; ?>" <?php echo $selectedWarehouse === (int) $warehouse['id_gudang'] ? 'selected' : ''; ?>><?php echo inventory_e($warehouse['kode_gudang'] . ' · ' . $warehouse['nama_gudang']); ?></option><?php endforeach; ?></select></div>
        <div class="form-group col-md-3 mb-2"><label>Kategori item</label><select name="kategori" class="form-control"><option value="">Semua kategori</option><?php foreach (array('APD/Safety', 'Tools', 'Consumable', 'Elektronik') as $category): ?><option <?php echo $selectedCategory === $category ? 'selected' : ''; ?>><?php echo inventory_e($category); ?></option><?php endforeach; ?></select></div>
        <div class="form-group col-md-2 mb-2"><label>Kondisi</label><select name="kondisi" class="form-control"><option value="">Semua</option><option <?php echo $selectedCondition === 'Baik' ? 'selected' : ''; ?>>Baik</option><option <?php echo $selectedCondition === 'Rusak' ? 'selected' : ''; ?>>Rusak</option></select></div>
        <div class="form-group col-md-2 mb-2"><button class="btn btn-primary btn-block"><i class="fas fa-filter mr-1"></i>Filter</button></div>
      </form>
      <div class="d-flex flex-wrap justify-content-between align-items-center border-top pt-3 mt-2">
        <div><strong><?php echo $currentWarehouse ? inventory_e($currentWarehouse['nama_gudang']) : 'Gudang'; ?></strong><span class="text-muted ml-2">PJ: <?php echo $currentWarehouse ? inventory_e($currentWarehouse['penanggung_jawab']) : '-'; ?></span></div>
        <button class="btn btn-outline-primary btn-sm mt-2 mt-md-0" data-toggle="modal" data-target="#warehouseModal"><i class="fas fa-cog mr-1"></i>Kelola gudang</button>
      </div>
    </div>
  </div>

  <div class="card inventory-card">
    <div class="card-header d-flex justify-content-between align-items-center flex-wrap">
      <div><h4 class="card-title mb-1"><?php echo $isEdit ? 'Ubah item inventory' : 'Tambah item inventory'; ?></h4><small class="text-muted">Kategori: APD/Safety, Tools, Consumable, atau Elektronik</small></div>
      <?php if ($isEdit): ?><a href="?module=inventory&gudang_id=<?php echo $selectedWarehouse; ?>" class="btn btn-light btn-sm">Batal ubah</a><?php endif; ?>
    </div>
    <div class="card-body">
      <form method="post" action="modules/inventory/proses.php" class="row">
        <input type="hidden" name="aksi" value="<?php echo $isEdit ? 'ubah_item' : 'tambah_item'; ?>"><input type="hidden" name="id_inventory" value="<?php echo $isEdit ? (int) $editItem['id_inventory'] : 0; ?>">
        <div class="form-group col-md-4"><label>Gudang <span class="text-danger">*</span></label><select name="gudang_id" class="form-control" required><?php foreach ($warehouses as $warehouse): ?><option value="<?php echo (int) $warehouse['id_gudang']; ?>" <?php echo $formWarehouse === (int) $warehouse['id_gudang'] ? 'selected' : ''; ?>><?php echo inventory_e($warehouse['nama_gudang']); ?></option><?php endforeach; ?></select></div>
        <div class="form-group col-md-4"><label>Nama item <span class="text-danger">*</span></label><input name="nama_item" class="form-control" required maxlength="150" value="<?php echo $isEdit ? inventory_e($editItem['nama_item']) : ''; ?>"></div>
        <div class="form-group col-md-2"><label>QTY <span class="text-danger">*</span></label><input type="number" name="qty" class="form-control" min="0" step="0.01" required value="<?php echo $isEdit ? inventory_e($editItem['qty']) : '0'; ?>"></div>
        <div class="form-group col-md-2"><label>Satuan <span class="text-danger">*</span></label><input name="satuan" class="form-control" required maxlength="40" placeholder="pcs, box..." value="<?php echo $isEdit ? inventory_e($editItem['satuan']) : ''; ?>"></div>
        <div class="form-group col-md-3"><label>Kategori <span class="text-danger">*</span></label><select name="kategori" class="form-control" required><?php foreach (array('APD/Safety', 'Tools', 'Consumable', 'Elektronik') as $category): ?><option <?php echo $isEdit && $editItem['kategori'] === $category ? 'selected' : ''; ?>><?php echo inventory_e($category); ?></option><?php endforeach; ?></select></div>
        <div class="form-group col-md-3"><label>Kondisi <span class="text-danger">*</span></label><select name="kondisi" class="form-control" required><option <?php echo !$isEdit || $editItem['kondisi'] === 'Baik' ? 'selected' : ''; ?>>Baik</option><option <?php echo $isEdit && $editItem['kondisi'] === 'Rusak' ? 'selected' : ''; ?>>Rusak</option></select></div>
        <div class="form-group col-md-3"><label>Tanggal pengambilan</label><input type="date" name="tanggal_pengambilan" class="form-control" value="<?php echo $isEdit ? inventory_e($editItem['tanggal_pengambilan']) : ''; ?>"></div>
        <div class="form-group col-md-3"><label>Keterangan</label><input name="keterangan" class="form-control" maxlength="500" value="<?php echo $isEdit ? inventory_e($editItem['keterangan']) : ''; ?>"></div>
        <div class="col-12 text-right"><button class="btn btn-primary btn-round px-4"><i class="fas fa-save mr-1"></i><?php echo $isEdit ? 'Simpan perubahan' : 'Simpan item'; ?></button></div>
      </form>
    </div>
  </div>

  <div class="card inventory-card mt-4">
    <div class="card-header"><h4 class="card-title mb-0">Daftar inventory · <?php echo $currentWarehouse ? inventory_e($currentWarehouse['nama_gudang']) : '-'; ?></h4></div>
    <div class="card-body"><div class="table-responsive"><table class="table inventory-table table-hover"><thead><tr><th>No</th><th>Nama item</th><th>Kategori</th><th class="text-right">QTY</th><th>Satuan</th><th>Kondisi</th><th>Tanggal pengambilan</th><th>Keterangan</th><th>Aksi</th></tr></thead><tbody>
      <?php if (count($items) === 0): ?><tr><td colspan="9" class="text-center text-muted py-5"><i class="fas fa-box-open fa-2x mb-2 d-block"></i>Belum ada item pada gudang ini.</td></tr><?php endif; ?>
      <?php foreach ($items as $index => $item): ?><tr><td><?php echo $index + 1; ?></td><td><strong><?php echo inventory_e($item['nama_item']); ?></strong></td><td><span class="category-badge"><?php echo inventory_e($item['kategori']); ?></span></td><td class="text-right"><?php echo number_format((float) $item['qty'], 2, ',', '.'); ?></td><td><?php echo inventory_e($item['satuan']); ?></td><td><span class="condition-badge <?php echo $item['kondisi'] === 'Baik' ? 'condition-good' : 'condition-bad'; ?>"><?php echo inventory_e($item['kondisi']); ?></span></td><td><?php echo $item['tanggal_pengambilan'] ? date('d/m/Y', strtotime($item['tanggal_pengambilan'])) : '-'; ?></td><td><?php echo inventory_e($item['keterangan'] ?: '-'); ?></td><td class="text-nowrap"><a class="btn btn-icon btn-round btn-secondary btn-sm" href="?module=inventory&gudang_id=<?php echo $selectedWarehouse; ?>&edit=<?php echo (int) $item['id_inventory']; ?>"><i class="fas fa-pencil-alt"></i></a> <a class="btn btn-icon btn-round btn-danger btn-sm" href="modules/inventory/proses.php?aksi=hapus_item&id=<?php echo (int) $item['id_inventory']; ?>&gudang_id=<?php echo $selectedWarehouse; ?>" onclick="return confirm('Hapus item ini?')"><i class="fas fa-trash"></i></a></td></tr><?php endforeach; ?>
    </tbody></table></div></div>
  </div>
</div>

<div class="modal fade" id="warehouseModal" tabindex="-1"><div class="modal-dialog"><div class="modal-content"><div class="modal-header"><h5 class="modal-title">Gudang & penanggung jawab</h5><button class="close" data-dismiss="modal">&times;</button></div><div class="modal-body">
  <form method="post" action="modules/inventory/proses.php"><input type="hidden" name="aksi" value="tambah_gudang"><div class="form-group"><label>Kode gudang</label><input name="kode_gudang" class="form-control" required maxlength="20" placeholder="G-004"></div><div class="form-group"><label>Nama gudang</label><input name="nama_gudang" class="form-control" required maxlength="100"></div><div class="form-group"><label>Penanggung jawab</label><input name="penanggung_jawab" class="form-control" required maxlength="100"></div><button class="btn btn-primary btn-block">Tambah gudang</button></form>
  <hr><h6>Gudang terdaftar</h6><?php foreach ($warehouses as $warehouse): ?><div class="d-flex justify-content-between align-items-center border-bottom py-2"><span><strong><?php echo inventory_e($warehouse['nama_gudang']); ?></strong><small class="d-block text-muted"><?php echo inventory_e($warehouse['kode_gudang'] . ' · PJ: ' . $warehouse['penanggung_jawab']); ?></small></span><?php if (count($warehouses) > 1): ?><a href="modules/inventory/proses.php?aksi=hapus_gudang&id=<?php echo (int) $warehouse['id_gudang']; ?>" class="text-danger" onclick="return confirm('Hapus gudang dan seluruh inventory di dalamnya?')"><i class="fas fa-trash"></i></a><?php endif; ?></div><?php endforeach; ?>
</div></div></div></div>
