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

  if (isset($_POST['simpan'])) {
    $id_penyesuaian = mysqli_real_escape_string($mysqli, $_POST['id_penyesuaian']);
    $tanggal        = mysqli_real_escape_string($mysqli, trim($_POST['tanggal']));
    $stok_baru      = mysqli_real_escape_string($mysqli, $_POST['stok_baru']);
    $keterangan     = mysqli_real_escape_string($mysqli, trim($_POST['keterangan']));
    $tanggal_penyesuaian = date('Y-m-d', strtotime($tanggal));

    $query = mysqli_query($mysqli, "SELECT a.barang, a.stok_awal, a.stok_baru, b.stok FROM tbl_penyesuaian_stok a INNER JOIN tbl_barang b ON a.barang=b.id_barang WHERE a.id_penyesuaian='$id_penyesuaian'")
                      or die('Ada kesalahan pada query tampil data : ' . mysqli_error($mysqli));
    $data = mysqli_fetch_assoc($query);

    $barang = $data['barang'];
    $stok_lama = $data['stok_baru'];
    $stok_awal = $data['stok_awal'];
    $stok_barang = $data['stok'];

    mysqli_begin_transaction($mysqli);

    $update_penyesuaian = mysqli_query($mysqli, "UPDATE tbl_penyesuaian_stok SET tanggal='$tanggal_penyesuaian', stok_baru='$stok_baru', selisih='$stok_baru' - '$stok_awal', keterangan='$keterangan' WHERE id_penyesuaian='$id_penyesuaian'")
                            or die('Ada kesalahan pada query update : ' . mysqli_error($mysqli));

    $update_barang = mysqli_query($mysqli, "UPDATE tbl_barang SET stok='$stok_baru' WHERE id_barang='$barang'")
                            or die('Ada kesalahan pada query update barang : ' . mysqli_error($mysqli));

    if ($update_penyesuaian && $update_barang) {
      mysqli_commit($mysqli);
      header('location: ../../main.php?module=penyesuaian_stok&pesan=2');
    }
    else {
      mysqli_rollback($mysqli);
      header('location: ../../main.php?module=penyesuaian_stok&pesan=0');
    }
  }
}
