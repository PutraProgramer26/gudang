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

    $query = mysqli_query($mysqli, "SELECT barang, stok_baru FROM tbl_penyesuaian_stok WHERE id_penyesuaian='$id_penyesuaian'")
                      or die('Ada kesalahan pada query tampil data : ' . mysqli_error($mysqli));
    $data = mysqli_fetch_assoc($query);

    mysqli_begin_transaction($mysqli);

    $delete = mysqli_query($mysqli, "DELETE FROM tbl_penyesuaian_stok WHERE id_penyesuaian='$id_penyesuaian'")
                                  or die('Ada kesalahan pada query delete : ' . mysqli_error($mysqli));

    if ($delete) {
      mysqli_commit($mysqli);
      header('location: ../../main.php?module=penyesuaian_stok&pesan=3');
    }
    else {
      mysqli_rollback($mysqli);
      header('location: ../../main.php?module=penyesuaian_stok&pesan=0');
    }
  }
}
