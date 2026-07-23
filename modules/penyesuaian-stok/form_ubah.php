<?php
session_start();

if (empty($_SESSION['username']) && empty($_SESSION['password'])) {
  header('location: ../../login.php?pesan=2');
}
else {
  require_once "../../config/database.php";

  if (!isset($_SESSION['hak_akses']) || $_SESSION['hak_akses'] != 'Administrator') {
    header('location: ../../main.php?module=penyesuaian_stok&pesan=forbidden');
    exit;
  }

  if (isset($_GET['id'])) {
    $id_penyesuaian = mysqli_real_escape_string($mysqli, $_GET['id']);
    $query = mysqli_query($mysqli, "SELECT a.id_penyesuaian, a.tanggal, a.barang, a.stok_awal, a.stok_baru, a.selisih, a.keterangan, b.nama_barang
                                    FROM tbl_penyesuaian_stok as a INNER JOIN tbl_barang as b ON a.barang=b.id_barang
                                    WHERE a.id_penyesuaian='$id_penyesuaian'")
                                    or die('Ada kesalahan pada query tampil data : ' . mysqli_error($mysqli));
    $data = mysqli_fetch_assoc($query);
  }
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Ubah Penyesuaian Stok</title>
  <link rel="stylesheet" href="../../assets/css/bootstrap.min.css">
  <link rel="stylesheet" href="../../assets/css/atlantis.min.css">
</head>
<body>
  <div class="container py-5">
    <div class="card">
      <div class="card-header">
        <div class="card-title">Ubah Penyesuaian Stok</div>
      </div>
      <form action="proses_ubah.php" method="post" class="needs-validation" novalidate>
        <div class="card-body">
          <div class="form-group">
            <label>ID Penyesuaian</label>
            <input type="text" name="id_penyesuaian" class="form-control" value="<?php echo $data['id_penyesuaian']; ?>" readonly>
          </div>
          <div class="form-group">
            <label>Tanggal</label>
            <input type="text" name="tanggal" class="form-control" value="<?php echo date('d-m-Y', strtotime($data['tanggal'])); ?>" required>
          </div>
          <div class="form-group">
            <label>Barang</label>
            <input type="text" class="form-control" value="<?php echo $data['barang'] . ' - ' . $data['nama_barang']; ?>" readonly>
          </div>
          <div class="form-group">
            <label>Stok Awal</label>
            <input type="text" class="form-control" value="<?php echo $data['stok_awal']; ?>" readonly>
          </div>
          <div class="form-group">
            <label>Stok Baru</label>
            <input type="text" name="stok_baru" class="form-control" value="<?php echo $data['stok_baru']; ?>" required>
          </div>
          <div class="form-group">
            <label>Keterangan</label>
            <textarea name="keterangan" class="form-control" rows="3"><?php echo $data['keterangan']; ?></textarea>
          </div>
        </div>
        <div class="card-action">
          <input type="submit" name="simpan" value="Simpan" class="btn btn-secondary btn-round pl-4 pr-4 mr-2">
          <a href="../../main.php?module=penyesuaian_stok" class="btn btn-default btn-round pl-4 pr-4">Batal</a>
        </div>
      </form>
    </div>
  </div>
</body>
</html>
<?php } ?>