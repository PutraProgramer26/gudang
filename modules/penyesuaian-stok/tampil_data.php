<?php
// mencegah direct access file PHP agar file PHP tidak bisa diakses secara langsung dari browser dan hanya dapat dijalankan ketika di include oleh file lain
// jika file diakses secara langsung
if (basename($_SERVER['PHP_SELF']) === basename(__FILE__)) {
  // alihkan ke halaman error 404
  header('location: 404.html');
}
// jika file di include oleh file lain, tampilkan isi file
else {
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

  // menampilkan pesan sesuai dengan proses yang dijalankan
  if (isset($_GET['pesan'])) {
    if ($_GET['pesan'] == 1) {
      echo '<div class="alert alert-notify alert-success alert-dismissible fade show" role="alert">
              <span data-notify="icon" class="fas fa-check"></span>
              <span data-notify="title" class="text-success">Sukses!</span>
              <span data-notify="message">Penyesuaian stok berhasil disimpan.</span>
              <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                <span aria-hidden="true">&times;</span>
              </button>
            </div>';
    }
    elseif ($_GET['pesan'] == 2) {
      echo '<div class="alert alert-notify alert-success alert-dismissible fade show" role="alert">
              <span data-notify="icon" class="fas fa-check"></span>
              <span data-notify="title" class="text-success">Sukses!</span>
              <span data-notify="message">Penyesuaian stok berhasil diubah.</span>
              <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                <span aria-hidden="true">&times;</span>
              </button>
            </div>';
    }
    elseif ($_GET['pesan'] == 3) {
      echo '<div class="alert alert-notify alert-success alert-dismissible fade show" role="alert">
              <span data-notify="icon" class="fas fa-check"></span>
              <span data-notify="title" class="text-success">Sukses!</span>
              <span data-notify="message">Penyesuaian stok berhasil dihapus.</span>
              <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                <span aria-hidden="true">&times;</span>
              </button>
            </div>';
    }
    elseif ($_GET['pesan'] == 'forbidden') {
      echo '<div class="alert alert-notify alert-warning alert-dismissible fade show" role="alert">
              <span data-notify="icon" class="fas fa-exclamation"></span>
              <span data-notify="title" class="text-warning">Peringatan!</span>
              <span data-notify="message">Hanya Administrator yang dapat mengubah atau menghapus data ini.</span>
              <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                <span aria-hidden="true">&times;</span>
              </button>
            </div>';
    }
  }
?>
  <div class="panel-header bg-secondary-gradient">
    <div class="page-inner py-45">
      <div class="d-flex align-items-left align-items-md-top flex-column flex-md-row">
        <div class="page-header text-white">
          <h4 class="page-title text-white"><i class="fas fa-adjust mr-2"></i> Penyesuaian Stok</h4>
          <ul class="breadcrumbs">
            <li class="nav-home"><a href="?module=dashboard"><i class="flaticon-home text-white"></i></a></li>
            <li class="separator"><i class="flaticon-right-arrow"></i></li>
            <li class="nav-item"><a href="?module=penyesuaian_stok" class="text-white">Penyesuaian Stok</a></li>
            <li class="separator"><i class="flaticon-right-arrow"></i></li>
            <li class="nav-item"><a>Data</a></li>
          </ul>
        </div>
        <div class="ml-md-auto py-2 py-md-0">
          <a href="?module=form_entri_penyesuaian_stok" class="btn btn-secondary btn-round">
            <span class="btn-label"><i class="fa fa-plus mr-2"></i></span> Entri Data
          </a>
        </div>
      </div>
    </div>
  </div>

  <div class="page-inner mt--5">
    <div class="card">
      <div class="card-header">
        <div class="card-title">Riwayat Penyesuaian Stok</div>
      </div>
      <div class="card-body">
        <div class="table-responsive">
          <table id="basic-datatables" class="display table table-bordered table-striped table-hover">
            <thead>
              <tr>
                <th class="text-center">No.</th>
                <th class="text-center">ID Penyesuaian</th>
                <th class="text-center">Tanggal</th>
                <th class="text-center">Barang</th>
                <th class="text-center">Stok Awal</th>
                <th class="text-center">Stok Baru</th>
                <th class="text-center">Selisih</th>
                <th class="text-center">Keterangan</th>
                <?php if (isset($_SESSION['hak_akses']) && $_SESSION['hak_akses'] == 'Administrator') { ?>
                <th class="text-center">Aksi</th>
                <?php } ?>
              </tr>
            </thead>
            <tbody>
              <?php
              $no = 1;
              $query = mysqli_query($mysqli, "SELECT a.id_penyesuaian, a.tanggal, a.barang, a.stok_awal, a.stok_baru, a.selisih, a.keterangan, b.nama_barang, c.nama_satuan
                                              FROM tbl_penyesuaian_stok as a INNER JOIN tbl_barang as b INNER JOIN tbl_satuan as c
                                              ON a.barang=b.id_barang AND b.satuan=c.id_satuan
                                              ORDER BY a.id_penyesuaian DESC")
                                              or die('Ada kesalahan pada query tampil data : ' . mysqli_error($mysqli));
              while ($data = mysqli_fetch_assoc($query)) { ?>
                <tr>
                  <td width="50" class="text-center"><?php echo $no++; ?></td>
                  <td width="110" class="text-center"><?php echo $data['id_penyesuaian']; ?></td>
                  <td width="80" class="text-center"><?php echo date('d-m-Y', strtotime($data['tanggal'])); ?></td>
                  <td width="250"><?php echo $data['barang']; ?> - <?php echo $data['nama_barang']; ?></td>
                  <td width="80" class="text-right"><?php echo number_format($data['stok_awal'], 0, '', '.'); ?></td>
                  <td width="80" class="text-right"><?php echo number_format($data['stok_baru'], 0, '', '.'); ?></td>
                  <td width="80" class="text-right"><?php echo number_format($data['selisih'], 0, '', '.'); ?></td>
                  <td><?php echo $data['keterangan']; ?></td>
                  <?php if (isset($_SESSION['hak_akses']) && $_SESSION['hak_akses'] == 'Administrator') { ?>
                  <td width="90" class="text-center">
                    <a href="modules/penyesuaian-stok/form_ubah.php?id=<?php echo $data['id_penyesuaian']; ?>" class="btn btn-icon btn-round btn-warning btn-sm" title="Ubah"><i class="fas fa-edit"></i></a>
                    <a href="modules/penyesuaian-stok/proses_hapus.php?id=<?php echo $data['id_penyesuaian']; ?>" onclick="return confirm('Anda yakin ingin menghapus data penyesuaian stok <?php echo $data['id_penyesuaian']; ?>?')" class="btn btn-icon btn-round btn-danger btn-sm" title="Hapus"><i class="fas fa-trash"></i></a>
                  </td>
                  <?php } ?>
                </tr>
              <?php } ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
<?php } ?>