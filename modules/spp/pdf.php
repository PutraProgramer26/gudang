<?php
require_once dirname(__DIR__, 2) . '/assets/js/plugin/dompdf/autoload.inc.php';
require_once dirname(__DIR__, 2) . '/config/database.php';
require_once __DIR__ . '/schema.php';
spp_ensure_schema($mysqli);

use Dompdf\Dompdf;

function spp_pdf_escape($value)
{
  return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

$token = isset($_GET['token']) ? trim($_GET['token']) : '';
if (!preg_match('/^[a-f0-9]{64}$/', $token)) {
  http_response_code(404);
  exit('Dokumen SPP tidak ditemukan.');
}

$headerStatement = mysqli_prepare($mysqli, 'SELECT s.id, s.nomor_spp, s.tanggal, p.nama_project FROM tbl_spp s INNER JOIN tbl_spp_project p ON p.id = s.project_id WHERE s.share_token = ? LIMIT 1');
if (!$headerStatement) {
  http_response_code(500);
  exit('Dokumen SPP sementara tidak dapat ditampilkan.');
}
mysqli_stmt_bind_param($headerStatement, 's', $token);
mysqli_stmt_execute($headerStatement);
$headerResult = mysqli_stmt_get_result($headerStatement);
$header = mysqli_fetch_assoc($headerResult);
mysqli_stmt_close($headerStatement);
if (!$header) {
  http_response_code(404);
  exit('Dokumen SPP tidak ditemukan.');
}

$itemStatement = mysqli_prepare($mysqli, 'SELECT nomor_item, nama_item, spesifikasi, jumlah_diminta, jumlah_diterima, satuan, untuk_pekerjaan, keterangan FROM tbl_spp_item WHERE spp_id = ? ORDER BY nomor_item ASC');
mysqli_stmt_bind_param($itemStatement, 'i', $header['id']);
mysqli_stmt_execute($itemStatement);
$itemResult = mysqli_stmt_get_result($itemStatement);
$items = array();
$requestedTotal = 0.0;
$receivedTotal = 0.0;
while ($item = mysqli_fetch_assoc($itemResult)) {
  $items[] = $item;
  $requestedTotal += (float) $item['jumlah_diminta'];
  $receivedTotal += (float) $item['jumlah_diterima'];
}
mysqli_stmt_close($itemStatement);
$overallPercent = $requestedTotal > 0 ? min(100, round(($receivedTotal / $requestedTotal) * 100, 1)) : 0;

$rows = '';
foreach ($items as $item) {
  $percent = (float) $item['jumlah_diminta'] > 0 ? min(100, round(((float) $item['jumlah_diterima'] / (float) $item['jumlah_diminta']) * 100, 1)) : 0;
  $rows .= '<tr><td class="center">' . (int) $item['nomor_item'] . '</td>' .
    '<td>' . spp_pdf_escape($item['nama_item']) . '</td>' .
    '<td>' . (spp_pdf_escape($item['spesifikasi']) ?: '-') . '</td>' .
    '<td class="center">' . number_format((float) $item['jumlah_diminta'], 3, ',', '.') . '</td>' .
    '<td class="center">' . spp_pdf_escape($item['satuan']) . '</td>' .
    '<td>' . (spp_pdf_escape($item['untuk_pekerjaan']) ?: '-') . '</td>' .
    '<td>' . (spp_pdf_escape($item['keterangan']) ?: '-') . '</td>' .
    '<td class="center">' . number_format((float) $item['jumlah_diterima'], 3, ',', '.') . '</td>' .
    '<td class="center">' . number_format($percent, 1, ',', '.') . '%</td></tr>';
}

$html = '<!doctype html><html lang="id"><head><meta charset="utf-8"><style>
  @page { margin: 13mm 10mm; }
  body { font-family: DejaVu Sans, sans-serif; color:#26383b; font-size:8pt; }
  h1 { margin:0; text-align:center; font-size:17pt; color:#176e63; }
  .subtitle { text-align:center; color:#607173; margin:5px 0 17px; font-size:9pt; }
  .rule { border-bottom:2px solid #176e63; margin-bottom:15px; }
  .meta { width:100%; margin-bottom:15px; border-collapse:collapse; }
  .meta td { padding:4px 3px; }
  .meta .label { color:#627275; width:105px; }
  .progress { margin:0 0 12px; padding:9px 11px; background:#edf5f1; border:1px solid #d8e9e0; }
  table.items { width:100%; border-collapse:collapse; }
  .items th,.items td { border:1px solid #879695; padding:6px 5px; vertical-align:top; }
  .items th { background:#e9f1ed; font-size:7pt; }
  .center { text-align:center; }
  .footer { margin-top:14px; color:#647275; font-size:7pt; }
</style></head><body>
  <h1>SURAT PERMINTAAN PEMBELIAN</h1>
  <div class="subtitle">Dokumen kebutuhan material project</div><div class="rule"></div>
  <table class="meta"><tr><td class="label">Project</td><td>: <strong>' . spp_pdf_escape($header['nama_project']) . '</strong></td><td class="label">Nomor SPP</td><td>: <strong>' . spp_pdf_escape($header['nomor_spp']) . '</strong></td></tr><tr><td class="label">Tanggal</td><td>: ' . date('d-m-Y', strtotime($header['tanggal'])) . '</td><td class="label">Status penerimaan</td><td>: ' . number_format($overallPercent, 1, ',', '.') . '%</td></tr></table>
  <div class="progress">Jumlah permintaan awal: <strong>' . number_format($requestedTotal, 3, ',', '.') . '</strong> &nbsp; | &nbsp; Sudah diterima di site: <strong>' . number_format($receivedTotal, 3, ',', '.') . '</strong> &nbsp; | &nbsp; Progres: <strong>' . number_format($overallPercent, 1, ',', '.') . '%</strong></div>
  <table class="items"><thead><tr><th style="width:4%">No</th><th style="width:17%">Nama Item</th><th style="width:16%">Ukuran & Spesifikasi</th><th style="width:8%">Jumlah</th><th style="width:7%">Satuan</th><th style="width:15%">Untuk Pekerjaan</th><th style="width:14%">Keterangan</th><th style="width:9%">Diterima</th><th style="width:10%">Progres</th></tr></thead><tbody>' . $rows . '</tbody></table>
  <div class="footer">Dokumen ini dibagikan melalui tautan khusus. Data penerimaan mencerminkan pembaruan terakhir.</div>
</body></html>';

$dompdf = new Dompdf();
$dompdf->loadHtml($html, 'UTF-8');
$dompdf->setPaper('A4', 'landscape');
$dompdf->render();
$safeNumber = preg_replace('/[^A-Za-z0-9_-]/', '-', $header['nomor_spp']);
$dompdf->stream('SPP-' . $safeNumber . '.pdf', array('Attachment' => false));
exit;