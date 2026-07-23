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

  // mengecek data hasil submit dari form
  if (isset($_POST['simpan'])) {
    // ambil data hasil submit dari form
    $id_transaksi  = mysqli_real_escape_string($mysqli, trim($_POST['id_transaksi']));
    $tanggal       = mysqli_real_escape_string($mysqli, trim($_POST['tanggal']));
    $keterangan    = mysqli_real_escape_string($mysqli, trim($_POST['keterangan']));
    $barang_list   = $_POST['barang'];
    $jumlah_list   = $_POST['jumlah'];

    $prefix = 'TK-';
    $nomor_urut = 1;

    if (preg_match('/^' . preg_quote($prefix, '/') . '(\d{7})$/', $id_transaksi, $matches)) {
      $nomor_urut = (int) $matches[1];
    }

    $selected_id = '';
    $max_attempts = 50;

    for ($attempt = 0; $attempt < $max_attempts; $attempt++) {
      $candidate_id = $prefix . str_pad($nomor_urut, 7, '0', STR_PAD_LEFT);
      $check_query = mysqli_query($mysqli, "SELECT id_transaksi FROM tbl_barang_keluar WHERE id_transaksi = '" . mysqli_real_escape_string($mysqli, $candidate_id) . "' LIMIT 1")
                      or die('Ada kesalahan pada query cek transaksi : ' . mysqli_error($mysqli));

      if (mysqli_num_rows($check_query) == 0) {
        $selected_id = $candidate_id;
        break;
      }

      $nomor_urut++;
    }

    if ($selected_id !== '') {
      $id_transaksi = $selected_id;
    } else {
      $id_transaksi = $prefix . str_pad($nomor_urut, 7, '0', STR_PAD_LEFT);
    }

    // ubah format tanggal menjadi Tahun-Bulan-Hari (Y-m-d) sebelum disimpan ke database
    $tanggal_keluar = date('Y-m-d', strtotime($tanggal));

    // insert setiap baris item
    $insert_success = true;
    if (!empty($barang_list) && is_array($barang_list)) {
      foreach ($barang_list as $index => $barang) {
        $barang = mysqli_real_escape_string($mysqli, $barang);
        $jumlah = mysqli_real_escape_string($mysqli, $jumlah_list[$index]);

        if ($barang == '' || $jumlah == '' || !is_numeric($jumlah) || $jumlah <= 0) {
          continue;
        }

        $insert = mysqli_query($mysqli, "INSERT INTO tbl_barang_keluar(id_transaksi, tanggal, barang, jumlah, keterangan) 
                                         VALUES('$id_transaksi', '$tanggal_keluar', '$barang', '$jumlah', '$keterangan')")
                                         or die('Ada kesalahan pada query insert : ' . mysqli_error($mysqli));
        if (!$insert) {
          $insert_success = false;
          break;
        }
      }
    }

    if ($insert_success) {
      // alihkan ke halaman barang keluar dan tampilkan pesan berhasil simpan data
      header('location: ../../main.php?module=barang_keluar&pesan=1');
    }
  }
}
