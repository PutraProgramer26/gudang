<?php
// mencegah direct access file PHP agar file PHP tidak bisa diakses secara langsung dari browser dan hanya dapat dijalankan ketika di include oleh file lain
// jika file diakses secara langsung
if (basename($_SERVER['PHP_SELF']) === basename(__FILE__)) {
  // alihkan ke halaman error 404
  header('location: 404.html');
}
// jika file di include oleh file lain, tampilkan isi file
else { ?>
  <?php
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
  ?>

  <div id="pesan"></div>

  <div class="panel-header bg-secondary-gradient">
    <div class="page-inner py-4">
      <div class="page-header text-white">
        <h4 class="page-title text-white"><i class="fas fa-adjust mr-2"></i> Penyesuaian Stok</h4>
        <ul class="breadcrumbs">
          <li class="nav-home"><a href="?module=dashboard"><i class="flaticon-home text-white"></i></a></li>
          <li class="separator"><i class="flaticon-right-arrow"></i></li>
          <li class="nav-item"><a href="?module=penyesuaian_stok" class="text-white">Penyesuaian Stok</a></li>
          <li class="separator"><i class="flaticon-right-arrow"></i></li>
          <li class="nav-item"><a>Entri</a></li>
        </ul>
      </div>
    </div>
  </div>

  <div class="page-inner mt--5">
    <div class="card">
      <div class="card-header">
        <div class="card-title">Entri Penyesuaian Stok</div>
      </div>
      <form action="modules/penyesuaian-stok/proses_entri.php" method="post" class="needs-validation" novalidate>
        <div class="card-body">
          <div class="row">
            <div class="col-md-7">
              <div class="form-group">
                <?php
                $query = mysqli_query($mysqli, "SELECT RIGHT(id_penyesuaian,7) as nomor FROM tbl_penyesuaian_stok ORDER BY id_penyesuaian DESC LIMIT 1")
                                                or die('Ada kesalahan pada query tampil data : ' . mysqli_error($mysqli));
                $rows = mysqli_num_rows($query);
                if ($rows <> 0) {
                  $data = mysqli_fetch_assoc($query);
                  $nomor_urut = $data['nomor'] + 1;
                }
                else {
                  $nomor_urut = 1;
                }
                $id_penyesuaian = "PA-" . str_pad($nomor_urut, 7, "0", STR_PAD_LEFT);
                ?>
                <label>ID Penyesuaian <span class="text-danger">*</span></label>
                <input type="text" name="id_penyesuaian" class="form-control" value="<?php echo $id_penyesuaian; ?>" readonly>
              </div>
            </div>

            <div class="col-md-5 ml-auto">
              <div class="form-group">
                <label>Tanggal <span class="text-danger">*</span></label>
                <input type="text" name="tanggal" class="form-control date-picker" autocomplete="off" value="<?php echo date("d-m-Y"); ?>" required>
                <div class="invalid-feedback">Tanggal tidak boleh kosong.</div>
              </div>
            </div>
          </div>

          <hr class="mt-3 mb-4">

          <div class="row">
            <div class="col-md-7">
              <div class="form-group">
                <label>Barang <span class="text-danger">*</span></label>
                <select id="data_barang" name="barang" class="form-control chosen-select" autocomplete="off" required>
                  <option selected disabled value="">-- Pilih --</option>
                  <?php
                  $query_barang = mysqli_query($mysqli, "SELECT id_barang, nama_barang FROM tbl_barang ORDER BY id_barang ASC")
                                                         or die('Ada kesalahan pada query tampil data : ' . mysqli_error($mysqli));
                  while ($data_barang = mysqli_fetch_assoc($query_barang)) {
                    echo "<option value='$data_barang[id_barang]'>$data_barang[id_barang] - $data_barang[nama_barang]</option>";
                  }
                  ?>
                </select>
                <div class="invalid-feedback">Barang tidak boleh kosong.</div>
              </div>

              <div class="form-group">
                <label>Stok Saat Ini <span class="text-danger">*</span></label>
                <input type="text" id="data_stok" name="stok_awal" class="form-control" readonly>
              </div>

              <div class="form-group">
                <label>Stok Baru <span class="text-danger">*</span></label>
                <input type="text" id="stok_baru" name="stok_baru" class="form-control" autocomplete="off" onKeyPress="return goodchars(event,'0123456789',this)" required>
                <div class="invalid-feedback">Stok baru tidak boleh kosong.</div>
              </div>

              <div class="form-group">
                <label>Selisih</label>
                <input type="text" id="selisih" name="selisih" class="form-control" readonly>
              </div>

              <div class="form-group">
                <label>Keterangan</label>
                <textarea name="keterangan" class="form-control" rows="3" placeholder="Catatan penyesuaian stok..."></textarea>
              </div>
            </div>
          </div>
        </div>
        <div class="card-action">
          <input type="submit" name="simpan" value="Simpan" class="btn btn-secondary btn-round pl-4 pr-4 mr-2">
          <a href="?module=penyesuaian_stok" class="btn btn-default btn-round pl-4 pr-4">Batal</a>
        </div>
      </form>
    </div>
  </div>

  <script type="text/javascript">
    $(document).ready(function() {
      $('#data_barang').chosen({
        width: '100%',
        no_results_text: 'Tidak ditemukan',
        search_contains: true,
        allow_single_deselect: true
      });

      $('#data_barang').change(function() {
        var id_barang = $(this).val();

        $.ajax({
          type: 'GET',
          url: 'modules/penyesuaian-stok/get_barang.php',
          data: {id_barang: id_barang},
          dataType: 'JSON',
          success: function(result) {
            $('#data_stok').val(result.stok);
            $('#selisih').val('');
            $('#stok_baru').val('');
            $('#stok_baru').focus();
          }
        });
      });

      $('#stok_baru').keyup(function() {
        var stok_awal = $('#data_stok').val();
        var stok_baru = $(this).val();
        var $message = $('#pesan');

        if (stok_awal == '') {
          $message.html('<div class="alert alert-notify alert-info alert-dismissible fade show" role="alert"><span data-notify="icon" class="fas fa-info"></span><span data-notify="title" class="text-info">Info!</span> <span data-notify="message">Silahkan pilih barang terlebih dahulu.</span><button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button></div>');
          $(this).val('');
          $('#selisih').val('');
          return;
        }

        if (stok_baru == '') {
          $('#selisih').val('');
          return;
        }

        if (stok_baru < 0) {
          $message.html('<div class="alert alert-notify alert-warning alert-dismissible fade show" role="alert"><span data-notify="icon" class="fas fa-exclamation"></span><span data-notify="title" class="text-warning">Peringatan!</span> <span data-notify="message">Stok baru tidak boleh negatif.</span><button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button></div>');
          $(this).val('');
          $('#selisih').val('');
          return;
        }

        var selisih = eval(stok_baru) - eval(stok_awal);
        $('#selisih').val(selisih);
      });
    });
  </script>
<?php } ?>