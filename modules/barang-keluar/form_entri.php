<?php
// mencegah direct access file PHP agar file PHP tidak bisa diakses secara langsung dari browser dan hanya dapat dijalankan ketika di include oleh file lain
// jika file diakses secara langsung
if (basename($_SERVER['PHP_SELF']) === basename(__FILE__)) {
  // alihkan ke halaman error 404
  header('location: 404.html');
}
// jika file di include oleh file lain, tampilkan isi file
else { ?>
  <!-- menampilkan pesan kesalahan -->
  <div id="pesan"></div>

  <div class="panel-header bg-secondary-gradient">
    <div class="page-inner py-4">
      <div class="page-header text-white">
        <!-- judul halaman -->
        <h4 class="page-title text-white"><i class="fas fa-sign-out-alt mr-2"></i> Barang Keluar</h4>
        <!-- breadcrumbs -->
        <ul class="breadcrumbs">
          <li class="nav-home"><a href="?module=dashboard"><i class="flaticon-home text-white"></i></a></li>
          <li class="separator"><i class="flaticon-right-arrow"></i></li>
          <li class="nav-item"><a href="?module=barang_keluar" class="text-white">Barang Keluar</a></li>
          <li class="separator"><i class="flaticon-right-arrow"></i></li>
          <li class="nav-item"><a>Entri</a></li>
        </ul>
      </div>
    </div>
  </div>

  <div class="page-inner mt--5">
    <div class="card">
      <div class="card-header">
        <!-- judul form -->
        <div class="card-title">Entri Data Barang Keluar</div>
      </div>
      <!-- form entri data -->
      <form action="modules/barang-keluar/proses_entri.php" method="post" class="needs-validation" novalidate>
        <div class="card-body">
          <div class="row">
            <div class="col-md-7">
              <div class="form-group">
                <?php
                // membuat "id_transaksi"
                // sql statement untuk menampilkan 7 digit terakhir dari "id_transaksi" pada tabel "tbl_barang_keluar"
                $query = mysqli_query($mysqli, "SELECT RIGHT(id_transaksi,7) as nomor FROM tbl_barang_keluar ORDER BY id_transaksi DESC LIMIT 1")
                                                or die('Ada kesalahan pada query tampil data : ' . mysqli_error($mysqli));
                // ambil jumlah baris data hasil query
                $rows = mysqli_num_rows($query);

                // cek hasil query
                // jika "id_transaksi" sudah ada
                if ($rows <> 0) {
                  // ambil data hasil query
                  $data = mysqli_fetch_assoc($query);
                  // nomor urut "id_transaksi" yang terakhir + 1 (contoh nomor urut yang terakhir adalah 2, maka 2 + 1 = 3, dst..)
                  $nomor_urut = $data['nomor'] + 1;
                }
                // jika "id_transaksi" belum ada
                else {
                  // nomor urut "id_transaksi" = 1
                  $nomor_urut = 1;
                }

                // menambahkan karakter "TK-" diawal dan karakter "0" disebelah kiri nomor urut
                $prefix = 'TK-';
                $id_transaksi = $prefix . str_pad($nomor_urut, 7, "0", STR_PAD_LEFT);
                $max_attempts = 50;

                for ($attempt = 0; $attempt < $max_attempts; $attempt++) {
                  $check_query = mysqli_query($mysqli, "SELECT id_transaksi FROM tbl_barang_keluar WHERE id_transaksi = '" . mysqli_real_escape_string($mysqli, $id_transaksi) . "' LIMIT 1")
                                  or die('Ada kesalahan pada query cek transaksi : ' . mysqli_error($mysqli));

                  if (mysqli_num_rows($check_query) == 0) {
                    break;
                  }

                  $nomor_urut++;
                  $id_transaksi = $prefix . str_pad($nomor_urut, 7, "0", STR_PAD_LEFT);
                }
                ?>
                <label>ID Transaksi <span class="text-danger">*</span></label>
                <!-- tampilkan "id_transaksi" -->
                <input type="text" name="id_transaksi" class="form-control" value="<?php echo $id_transaksi; ?>" readonly>
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

          <div class="row">
            <div class="col-md-12">
              <div class="form-group">
                <label>Keterangan</label>
                <textarea name="keterangan" class="form-control" rows="3" placeholder="Contoh: Barang dikirim ke bagian produksi"></textarea>
              </div>
            </div>
          </div>

          <hr class="mt-3 mb-4">

          <div class="row">
            <div class="col-md-12">
              <div class="form-group">
                <label>Daftar Barang <span class="text-danger">*</span></label>
                <?php
                // sql statement untuk menampilkan data dari tabel "tbl_barang"
                $query_barang = mysqli_query($mysqli, "SELECT id_barang, nama_barang FROM tbl_barang ORDER BY id_barang ASC")
                                                       or die('Ada kesalahan pada query tampil data : ' . mysqli_error($mysqli));
                $barang_options = "";
                while ($data_barang = mysqli_fetch_assoc($query_barang)) {
                  $barang_options .= "<option value='$data_barang[id_barang]'>$data_barang[id_barang] - $data_barang[nama_barang]</option>";
                }
                ?>
                <div class="table-responsive">
                  <table class="table table-bordered table-striped" id="table-barang-keluar">
                    <thead>
                      <tr>
                        <th>Barang</th>
                        <th>Stok</th>
                        <th>Jumlah Keluar</th>
                        <th>Sisa Stok</th>
                        <th class="text-center">Aksi</th>
                      </tr>
                    </thead>
                    <tbody>
                      <tr class="item-row">
                        <td>
                          <select name="barang[]" class="form-control chosen-select barang-select" autocomplete="off" required>
                            <option selected disabled value="">-- Pilih --</option>
                            <?php echo $barang_options; ?>
                          </select>
                        </td>
                        <td>
                          <div class="input-group">
                            <input type="text" name="stok[]" class="form-control stok-input" readonly>
                            <div class="input-group-append satuan-cell"></div>
                          </div>
                        </td>
                        <td>
                          <input type="text" name="jumlah[]" class="form-control jumlah-input" autocomplete="off" onKeyPress="return goodchars(event,'0123456789',this)" required>
                        </td>
                        <td>
                          <input type="text" name="sisa[]" class="form-control sisa-input" readonly>
                        </td>
                        <td class="text-center">
                          <button type="button" class="btn btn-danger btn-sm remove-row" disabled><i class="fas fa-trash"></i></button>
                        </td>
                      </tr>
                    </tbody>
                  </table>
                </div>
                <button type="button" id="add-row" class="btn btn-secondary btn-sm"><i class="fa fa-plus mr-2"></i> Tambah Barang</button>
              </div>
            </div>
          </div>
        </div>
        <div class="card-action">
          <!-- tombol simpan data -->
          <input type="submit" name="simpan" value="Simpan" class="btn btn-secondary btn-round pl-4 pr-4 mr-2">
          <!-- tombol kembali ke halaman data barang keluar -->
          <a href="?module=barang_keluar" class="btn btn-default btn-round pl-4 pr-4">Batal</a>
        </div>
      </form>
    </div>
  </div>

  <script type="text/javascript">
    $(document).ready(function() {
      $('#table-barang-keluar .chosen-select').chosen({
        width: '100%',
        no_results_text: 'Tidak ditemukan',
        search_contains: true,
        allow_single_deselect: true
      });

      var barangOptions = <?php echo json_encode($barang_options); ?>;
      var rowTemplate = '<tr class="item-row">' +
                        '<td>' +
                          '<select name="barang[]" class="form-control chosen-select barang-select" autocomplete="off" required>' +
                            '<option selected disabled value="">-- Pilih --</option>' +
                            barangOptions +
                          '</select>' +
                        '</td>' +
                        '<td>' +
                          '<div class="input-group">' +
                            '<input type="text" name="stok[]" class="form-control stok-input" readonly>' +
                            '<div class="input-group-append satuan-cell"></div>' +
                          '</div>' +
                        '</td>' +
                        '<td>' +
                          '<input type="text" name="jumlah[]" class="form-control jumlah-input" autocomplete="off" onKeyPress="return goodchars(event,\'0123456789\',this)" required>' +
                        '</td>' +
                        '<td>' +
                          '<input type="text" name="sisa[]" class="form-control sisa-input" readonly>' +
                        '</td>' +
                        '<td class="text-center">' +
                          '<button type="button" class="btn btn-danger btn-sm remove-row"><i class="fas fa-trash"></i></button>' +
                        '</td>' +
                      '</tr>';

      function updateRemoveButtons() {
        var rowCount = $('#table-barang-keluar tbody tr').length;
        $('#table-barang-keluar tbody tr .remove-row').prop('disabled', rowCount === 1);
      }

      $('#table-barang-keluar').on('change', '.barang-select', function() {
        var $row = $(this).closest('tr');
        var id_barang = $(this).val();

        if (!id_barang) {
          $row.find('.stok-input, .sisa-input').val('');
          $row.find('.satuan-cell').html('');
          return;
        }

        $.ajax({
          type: 'GET',
          url: 'modules/barang-keluar/get_barang.php',
          data: {id_barang: id_barang},
          dataType: 'JSON',
          success: function(result) {
            $row.find('.stok-input').val(result.stok);
            $row.find('.satuan-cell').html('<span class="input-group-text">' + result.nama_satuan + '</span>');
            $row.find('.jumlah-input').focus();
          }
        });
      });

      $('#table-barang-keluar').on('keyup', '.jumlah-input', function() {
        var $row = $(this).closest('tr');
        var stok = $row.find('.stok-input').val();
        var jumlah = $(this).val();
        var $message = $('#pesan');

        if (stok == '') {
          $message.html('<div class="alert alert-notify alert-info alert-dismissible fade show" role="alert"><span data-notify="icon" class="fas fa-info"></span><span data-notify="title" class="text-info">Info!</span> <span data-notify="message">Silahkan isi data barang terlebih dahulu.</span><button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button></div>');
          $(this).val('');
          $row.find('.sisa-input').val('');
          return;
        }

        if (jumlah == '') {
          $row.find('.sisa-input').val('');
          return;
        }

        if (jumlah == 0) {
          $message.html('<div class="alert alert-notify alert-warning alert-dismissible fade show" role="alert"><span data-notify="icon" class="fas fa-exclamation"></span><span data-notify="title" class="text-warning">Peringatan!</span> <span data-notify="message">Jumlah keluar tidak boleh 0 (nol).</span><button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button></div>');
          $(this).val('');
          $row.find('.sisa-input').val('');
          return;
        }

        if (eval(jumlah) > eval(stok)) {
          $message.html('<div class="alert alert-notify alert-warning alert-dismissible fade show" role="alert"><span data-notify="icon" class="fas fa-exclamation"></span><span data-notify="title" class="text-warning">Peringatan!</span> <span data-notify="message">Stok tidak memenuhi, kurangi jumlah keluar.</span><button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button></div>');
          $(this).val('');
          $row.find('.sisa-input').val('');
          return;
        }

        var sisa_stok = eval(stok) - eval(jumlah);
        $row.find('.sisa-input').val(sisa_stok);
      });

      $('#add-row').click(function() {
        $('#table-barang-keluar tbody').append(rowTemplate);
        $('#table-barang-keluar tbody tr:last .chosen-select').chosen({
          width: '100%',
          no_results_text: 'Tidak ditemukan',
          search_contains: true,
          allow_single_deselect: true
        });
        updateRemoveButtons();
      });

      $('#table-barang-keluar').on('click', '.remove-row', function() {
        $(this).closest('tr').remove();
        updateRemoveButtons();
      });

      updateRemoveButtons();
    });
  </script>
<?php } ?>