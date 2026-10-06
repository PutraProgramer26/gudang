<?php
session_start();
if (empty($_SESSION['username'])) {
  header('location: ../../login.php?pesan=2');
  exit;
}

require_once '../../assets/js/plugin/dompdf/autoload.inc.php';
require_once '../../config/database.php';
require_once __DIR__ . '/schema.php';
surat_jalan_ensure_schema($mysqli);

use Dompdf\Dompdf;

function surat_jalan_pdf_escape($value)
{
  return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

$documentId = isset($_GET['id']) ? filter_var($_GET['id'], FILTER_VALIDATE_INT) : false;
if (!$documentId || $documentId < 1) {
  http_response_code(400);
  exit('Nomor dokumen tidak valid.');
}

$documentStatement = mysqli_prepare($mysqli, 'SELECT * FROM tbl_surat_jalan WHERE id = ? LIMIT 1');
mysqli_stmt_bind_param($documentStatement, 'i', $documentId);
mysqli_stmt_execute($documentStatement);
$documentResult = mysqli_stmt_get_result($documentStatement);
$document = mysqli_fetch_assoc($documentResult);
mysqli_stmt_close($documentStatement);
if (!$document) {
  http_response_code(404);
  exit('Surat jalan tidak ditemukan.');
}

$detailStatement = mysqli_prepare($mysqli, 'SELECT nama_barang, jumlah, keterangan FROM tbl_surat_jalan_detail WHERE surat_jalan_id = ? ORDER BY id ASC');
mysqli_stmt_bind_param($detailStatement, 'i', $documentId);
mysqli_stmt_execute($detailStatement);
$detailResult = mysqli_stmt_get_result($detailStatement);
$items = array();
while ($item = mysqli_fetch_assoc($detailResult)) {
  $items[] = $item;
}
mysqli_stmt_close($detailStatement);

$settingsResult = mysqli_query($mysqli, 'SELECT header_title, header_subtitle, logo_path FROM tbl_surat_jalan_settings WHERE id = 1');
$settings = mysqli_fetch_assoc($settingsResult);
$logoData = '';
if (!empty($settings['logo_path'])) {
  $projectRoot = realpath(dirname(__DIR__, 2));
  $logoAbsolutePath = realpath($projectRoot . DIRECTORY_SEPARATOR . $settings['logo_path']);
  if ($logoAbsolutePath && strpos($logoAbsolutePath, $projectRoot . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'surat-jalan' . DIRECTORY_SEPARATOR) === 0 && is_file($logoAbsolutePath)) {
    $imageInfo = getimagesize($logoAbsolutePath);
    $canRenderImage = $imageInfo && ($imageInfo['mime'] === 'image/jpeg' || ($imageInfo['mime'] === 'image/png' && extension_loaded('gd')));
    if ($canRenderImage) {
      $logoData = 'data:' . $imageInfo['mime'] . ';base64,' . base64_encode(file_get_contents($logoAbsolutePath));
    }
  }
}

$rows = '';
foreach ($items as $index => $item) {
  $rows .= '<tr><td class="center">' . ($index + 1) . '</td><td>' . surat_jalan_pdf_escape($item['nama_barang']) . '</td><td class="center">' . number_format((float) $item['jumlah'], 2, ',', '.') . '</td><td>' . (!empty($item['keterangan']) ? nl2br(surat_jalan_pdf_escape($item['keterangan'])) : '&nbsp;') . '</td></tr>';
}

$subtitle = nl2br(surat_jalan_pdf_escape($settings['header_subtitle']));
$logoMarkup = $logoData !== '' ? '<img class="logo" src="' . $logoData . '" alt="Logo">' : '';
$html = '<!doctype html><html lang="id"><head><meta charset="utf-8"><style>
  @page { margin: 13mm 14mm 14mm; }
  * { box-sizing: border-box; }
  body { font-family: DejaVu Sans, sans-serif; color: #202a2d; font-size: 10pt; }
  .head { width: 100%; border-bottom: 2px solid #183b3a; padding-bottom: 11px; margin-bottom: 18px; }
  .head td { vertical-align: middle; }
  .logo-box { width: 95px; }
  .logo { max-width: 82px; max-height: 68px; }
  .identity { text-align: center; }
  .identity h1 { margin: 0 0 5px; font-size: 18pt; color: #183b3a; }
  .identity p { margin: 0; font-size: 9pt; line-height: 1.45; white-space: pre-line; }
  .document-title { text-align: center; margin: 0 0 4px; font-size: 15pt; letter-spacing: 1px; }
  .document-number { text-align: center; margin: 0 0 20px; font-size: 10pt; }
  .meta { width: 100%; margin-bottom: 16px; border-collapse: collapse; }
  .meta td { padding: 4px 2px; vertical-align: top; }
  .meta .label { width: 115px; color: #536064; }
  .items { width: 100%; border-collapse: collapse; }
  .items th, .items td { border: 1px solid #718084; padding: 8px 7px; vertical-align: top; }
  .items th { background: #edf3f1; font-size: 9pt; }
  .center { text-align: center; }
  .signatures { width: 100%; margin-top: 34px; border-collapse: collapse; page-break-inside: avoid; }
  .signatures td { width: 33.33%; text-align: center; vertical-align: top; padding: 0 5px; }
  .sign-title { min-height: 35px; }
  .sign-space { height: 78px; }
  .sign-name { border-top: 1px solid #526064; display: inline-block; min-width: 120px; padding-top: 5px; }
  .destination { margin-top: 20px; color: #536064; }
</style></head><body>
  <table class="head"><tr><td class="logo-box">' . $logoMarkup . '</td><td class="identity"><h1>' . surat_jalan_pdf_escape($settings['header_title']) . '</h1><p>' . $subtitle . '</p></td><td class="logo-box"></td></tr></table>
  <h2 class="document-title">SURAT JALAN</h2>
  <p class="document-number">Nomor: <strong>' . surat_jalan_pdf_escape($document['nomor_surat']) . '</strong></p>
  <table class="meta">
    <tr><td class="label">Tanggal</td><td>: ' . date('d-m-Y', strtotime($document['tanggal'])) . '</td></tr>
    <tr><td class="label">Yang mengajukan</td><td>: ' . surat_jalan_pdf_escape($document['pemohon']) . '</td></tr>
    <tr><td class="label">Nama driver</td><td>: ' . surat_jalan_pdf_escape($document['driver']) . '</td></tr>
    <tr><td class="label">Tujuan</td><td>: ' . surat_jalan_pdf_escape($document['tujuan'] ?: '-') . '</td></tr>
  </table>
  <table class="items"><thead><tr><th style="width:8%">No</th><th style="width:42%">Nama Barang</th><th style="width:18%">Jumlah Barang</th><th style="width:32%">Keterangan</th></tr></thead><tbody>' . $rows . '</tbody></table>
  <p class="destination">Barang tersebut telah diperiksa dan diserahkan untuk dikirim ke tujuan.</p>
  <table class="signatures"><tr>
    <td><div class="sign-title">Yang mengajukan</div><div class="sign-space"></div><div class="sign-name">' . surat_jalan_pdf_escape($document['pemohon']) . '</div></td>
    <td><div class="sign-title">Driver</div><div class="sign-space"></div><div class="sign-name">' . surat_jalan_pdf_escape($document['driver']) . '</div></td>
    <td><div class="sign-title">Mengetahui, Manager</div><div class="sign-space"></div><div class="sign-name">' . surat_jalan_pdf_escape($document['manager']) . '</div></td>
  </tr></table>
</body></html>';

$dompdf = new Dompdf();
$dompdf->loadHtml($html, 'UTF-8');
$dompdf->setPaper('A4', 'portrait');
$dompdf->render();
$dompdf->stream('Surat-Jalan-' . preg_replace('/[^A-Za-z0-9_-]/', '-', $document['nomor_surat']) . '.pdf', array('Attachment' => false));
exit;