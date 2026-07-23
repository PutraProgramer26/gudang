<?php
session_start();      // mengaktifkan session

// pengecekan session login user 
// jika user belum login
if (empty($_SESSION['username']) && empty($_SESSION['password'])) {
  // alihkan ke halaman login dan tampilkan pesan peringatan login
  header('location: ../../login.php?pesan=2');
}
// jika user sudah login, maka jalankan perintah untuk insert
else {
  // panggil file "database.php" untuk koneksi ke database
  require_once "../../config/database.php";

  mysqli_query($mysqli, "CREATE TABLE IF NOT EXISTS tbl_penyesuaian_stok (
    id_penyesuaian varchar(10) NOT NULL,
    tanggal date NOT NULL,
    barang varchar(5) NOT NULL,
    stok_awal int(11) NOT NULL,
    stok_baru int(11) NOT NULL,
    selisih int(11) NOT NULL,
    keterangan varchar(255) DEFAULT NULL,
    PRIMARY KEY (id_penyesuaian),
    KEY barang (barang)
  ) ENGINE=InnoDB DEFAULT CHARSET=latin1;") or die('Ada kesalahan saat membuat tabel tbl_penyesuaian_stok : ' . mysqli_error($mysqli));

  // mengecek data hasil submit dari form
  if (isset($_POST['simpan'])) {
    // ambil data hasil submit dari form
    $id_penyesuaian = mysqli_real_escape_string($mysqli, $_POST['id_penyesuaian']);
    $tanggal        = mysqli_real_escape_string($mysqli, trim($_POST['tanggal']));
    $barang         = mysqli_real_escape_string($mysqli, $_POST['barang']);
    $stok_baru      = mysqli_real_escape_string($mysqli, $_POST['stok_baru']);
    $keterangan     = mysqli_real_escape_string($mysqli, trim($_POST['keterangan']));

    // ubah format tanggal menjadi Tahun-Bulan-Hari (Y-m-d) sebelum disimpan ke database
    $tanggal_penyesuaian = date('Y-m-d', strtotime($tanggal));

    // ambil stok awal dari database untuk mencegah manipulasi nilai stok yang dikirim dari client
    $query = mysqli_query($mysqli, "SELECT stok FROM tbl_barang WHERE id_barang='$barang'")
                      or die('Ada kesalahan pada query tampil data : ' . mysqli_error($mysqli));
    $data  = mysqli_fetch_assoc($query);
    $stok_awal = $data['stok'];
    $selisih = $stok_baru - $stok_awal;

    mysqli_begin_transaction($mysqli);

    $insert = mysqli_query($mysqli, "INSERT INTO tbl_penyesuaian_stok(id_penyesuaian, tanggal, barang, stok_awal, stok_baru, selisih, keterangan)
                                     VALUES('$id_penyesuaian', '$tanggal_penyesuaian', '$barang', '$stok_awal', '$stok_baru', '$selisih', '$keterangan')")
                                     or die('Ada kesalahan pada query insert : ' . mysqli_error($mysqli));

    $update = mysqli_query($mysqli, "UPDATE tbl_barang SET stok='$stok_baru' WHERE id_barang='$barang'")
                                     or die('Ada kesalahan pada query update : ' . mysqli_error($mysqli));

    if ($insert && $update) {
      mysqli_commit($mysqli);
      header('location: ../../main.php?module=penyesuaian_stok&pesan=1');
    }
    else {
      mysqli_rollback($mysqli);
      header('location: ../../main.php?module=penyesuaian_stok&pesan=0');
    }
  }
}
