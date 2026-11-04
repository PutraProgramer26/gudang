<?php
session_start();
if (empty($_SESSION['username'])) {
  header('location: ../../login.php?pesan=2');
  exit;
}
require_once dirname(__DIR__, 2) . '/config/database.php';
require_once __DIR__ . '/schema.php';
require_once __DIR__ . '/template.php';
surat_jalan_ensure_schema($mysqli);
$documentId = filter_var(isset($_GET['id']) ? $_GET['id'] : null, FILTER_VALIDATE_INT);
if (!$documentId || $documentId < 1) {
  http_response_code(400);
  exit('Surat Jalan tidak valid.');
}
$statement = mysqli_prepare($mysqli, 'SELECT * FROM tbl_surat_jalan WHERE id = ? LIMIT 1');
mysqli_stmt_bind_param($statement, 'i', $documentId);
mysqli_stmt_execute($statement);
$document = mysqli_fetch_assoc(mysqli_stmt_get_result($statement));
mysqli_stmt_close($statement);
if (!$document) {
  http_response_code(404);
  exit('Surat Jalan tidak ditemukan.');
}
$storedPdf = dirname(__DIR__, 2) . '/uploads/surat-jalan/' . basename((string) $document['pdf_path']);
if (!empty($document['pdf_path']) && is_file($storedPdf)) {
  header('Content-Type: application/pdf');
  $disposition = isset($_GET['view']) && $_GET['view'] === '1' ? 'inline' : 'attachment';
  header('Content-Disposition: ' . $disposition . '; filename="surat-jalan-' . preg_replace('/[^A-Za-z0-9_-]/', '-', $document['nomor_surat']) . '.pdf"');
  readfile($storedPdf);
  exit;
}
$detailStatement = mysqli_prepare($mysqli, 'SELECT nomor_item, nama_barang, jumlah, satuan, keterangan FROM tbl_surat_jalan_detail WHERE surat_jalan_id = ? ORDER BY nomor_item ASC');
mysqli_stmt_bind_param($detailStatement, 'i', $documentId);
mysqli_stmt_execute($detailStatement);
$items = array();
$detailResult = mysqli_stmt_get_result($detailStatement);
while ($row = mysqli_fetch_assoc($detailResult)) {
  $items[] = $row;
}
mysqli_stmt_close($detailStatement);
$settings = mysqli_fetch_assoc(mysqli_query($mysqli, 'SELECT * FROM tbl_surat_jalan_settings WHERE id = 1'));
$templateAbsolute = dirname(__DIR__, 2) . '/uploads/surat-jalan/' . basename($settings['template_path']);
$fields = surat_jalan_parse_json($document['data_fields'], array());
$fields['nomor_surat'] = $document['nomor_surat'];
$fields['tanggal'] = date('d-m-Y', strtotime($document['tanggal']));
$fields['pemohon'] = $document['pemohon'];
try {
  $content = surat_jalan_docx_html($templateAbsolute, $fields, $items, surat_jalan_parse_json($settings['table_config'], array('table_index' => -1, 'columns' => array())));
} catch (RuntimeException $exception) {
  http_response_code(500);
  exit($exception->getMessage());
}
$html = '<!doctype html><html><head><meta charset="utf-8"><style>
@page{margin:18mm 16mm}body{font-family:DejaVu Sans,Arial,sans-serif;font-size:11px;color:#222;line-height:1.35}p{margin:0 0 7px;min-height:12px}.template-header{margin:0 0 12px;padding-bottom:4px}.template-header p{margin:0;min-height:0}.template-image{display:inline-block;max-width:100%;max-height:42mm;vertical-align:middle}.template-table{width:100%;border-collapse:collapse;margin:8px 0 12px}.template-table td,.template-table th{border:1px solid #444;padding:5px;vertical-align:top}.template-table th{font-weight:bold;background:#f2f2f2}.items-table th{text-align:center}.items-table td{height:20px}
</style></head><body>' . $content . '</body></html>';
$dompdf = new Dompdf\Dompdf();
$dompdf->loadHtml($html, 'UTF-8');
$dompdf->setPaper('A4', 'portrait');
$dompdf->render();
$dompdf->stream('surat-jalan-' . preg_replace('/[^A-Za-z0-9_-]/', '-', $document['nomor_surat']) . '.pdf', array('Attachment' => true));
