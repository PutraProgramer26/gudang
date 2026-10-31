<?php
session_start();
if (empty($_SESSION['username'])) {
  header('location: ../../login.php?pesan=2');
  exit;
}
require_once '../../assets/js/plugin/dompdf/autoload.inc.php';
require_once '../../config/database.php';
require_once __DIR__ . '/schema.php';
inventory_ensure_schema($mysqli);
use Dompdf\Dompdf;

function inventory_pdf_e($value)
{
  return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

$warehouseId = isset($_GET['gudang_id']) ? filter_var($_GET['gudang_id'], FILTER_VALIDATE_INT) : false;
if (!$warehouseId || $warehouseId < 1) {
  http_response_code(400);
  exit('Gudang tidak valid.');
}
$warehouseStatement = mysqli_prepare($mysqli, 'SELECT * FROM tbl_gudang WHERE id_gudang = ? LIMIT 1');
mysqli_stmt_bind_param($warehouseStatement, 'i', $warehouseId);
mysqli_stmt_execute($warehouseStatement);
$warehouse = mysqli_fetch_assoc(mysqli_stmt_get_result($warehouseStatement));
mysqli_stmt_close($warehouseStatement);
if (!$warehouse) {
  http_response_code(404);
  exit('Gudang tidak ditemukan.');
}
$itemStatement = mysqli_prepare($mysqli, 'SELECT * FROM tbl_inventory WHERE gudang_id = ? ORDER BY kategori, nama_item');
mysqli_stmt_bind_param($itemStatement, 'i', $warehouseId);
mysqli_stmt_execute($itemStatement);
$itemResult = mysqli_stmt_get_result($itemStatement);
$rows = '';
$number = 1;
while ($item = mysqli_fetch_assoc($itemResult)) {
  $rows .= '<tr><td class="center">' . $number++ . '</td><td>' . inventory_pdf_e($item['nama_item']) . '</td><td>' . inventory_pdf_e($item['kategori']) . '</td><td class="right">' . number_format((float) $item['qty'], 2, ',', '.') . '</td><td>' . inventory_pdf_e($item['satuan']) . '</td><td class="center">' . inventory_pdf_e($item['kondisi']) . '</td><td class="center">' . ($item['tanggal_pengambilan'] ? date('d-m-Y', strtotime($item['tanggal_pengambilan'])) : '-') . '</td><td>' . inventory_pdf_e($item['keterangan'] ?: '-') . '</td></tr>';
}
mysqli_stmt_close($itemStatement);
$html = '<!doctype html><html lang="id"><head><meta charset="utf-8"><style>@page{margin:12mm}body{font-family:DejaVu Sans,sans-serif;color:#24323a;font-size:8pt}h1{color:#173f5f;margin:0 0 4px;font-size:18pt}h2{text-align:center;margin:20px 0 5px;font-size:14pt}.meta{margin:0 0 18px;color:#52616b}.items{width:100%;border-collapse:collapse}.items th,.items td{border:1px solid #9aa8ad;padding:6px;vertical-align:top}.items th{background:#eaf3f7;color:#173f5f}.center{text-align:center}.right{text-align:right}.footer{margin-top:22px;text-align:right;color:#68757b}</style></head><body><h1>DATA INVENTORY GUDANG</h1><div class="meta"><strong>' . inventory_pdf_e($warehouse['kode_gudang'] . ' · ' . $warehouse['nama_gudang']) . '</strong><br>Penanggung Jawab: ' . inventory_pdf_e($warehouse['penanggung_jawab']) . '<br>Dicetak: ' . date('d-m-Y H:i') . '</div><h2>REKAP INVENTORY</h2><table class="items"><thead><tr><th>No</th><th>Nama Item</th><th>Kategori</th><th>QTY</th><th>Satuan</th><th>Kondisi</th><th>Tgl Pengambilan</th><th>Keterangan</th></tr></thead><tbody>' . ($rows ?: '<tr><td colspan="8" class="center">Belum ada data inventory.</td></tr>') . '</tbody></table><div class="footer">Dokumen rekap inventory</div></body></html>';
$dompdf = new Dompdf();
$dompdf->loadHtml($html, 'UTF-8');
$dompdf->setPaper('A4', 'landscape');
$dompdf->render();
$dompdf->stream('Rekap-Inventory-' . preg_replace('/[^A-Za-z0-9_-]/', '-', $warehouse['kode_gudang']) . '.pdf', array('Attachment' => false));
exit;
