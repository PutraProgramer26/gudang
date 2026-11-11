<?php
session_start();
if (empty($_SESSION['username'])) {
  header('location: ../../login.php?pesan=2');
  exit;
}
if (!isset($_SESSION['hak_akses']) || !in_array($_SESSION['hak_akses'], array('Administrator', 'Admin Gudang'), true)) {
  http_response_code(403);
  exit('Role Anda tidak memiliki izin untuk mengunduh template SPP.');
}

require_once __DIR__ . '/spreadsheet.php';
try {
  $templatePath = spp_create_template_xlsx();
} catch (RuntimeException $exception) {
  http_response_code(500);
  exit($exception->getMessage());
}

header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="template-import-spp.xlsx"');
header('Content-Length: ' . filesize($templatePath));
header('Cache-Control: no-store, no-cache, must-revalidate');
readfile($templatePath);
unlink($templatePath);
exit;
