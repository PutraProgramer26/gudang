<?php
function surat_jalan_h($value)
{
  return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function surat_jalan_xml_text($xml)
{
  $xml = preg_replace('/<w:tab[^>]*\/>/i', ' ', $xml);
  $xml = preg_replace('/<w:br[^>]*\/>/i', "\n", $xml);
  return html_entity_decode(strip_tags($xml), ENT_QUOTES, 'UTF-8');
}

function surat_jalan_template_fields($xml)
{
  $text = surat_jalan_xml_text($xml);
  $fields = array();
  if (preg_match_all('/\{\{\s*([a-zA-Z][a-zA-Z0-9 _-]{0,60})\s*\}\}/', $text, $matches)) {
    foreach ($matches[1] as $field) {
      $key = strtolower(trim(preg_replace('/[^a-zA-Z0-9]+/', '_', $field), '_'));
      if ($key !== '' && !isset($fields[$key])) {
        $fields[$key] = trim($field);
      }
    }
  }
  if (preg_match_all('/([A-Za-z][A-Za-z0-9 _-]{1,60})\s*[:：]\s*[._…]{3,}/u', $text, $matches)) {
    foreach ($matches[1] as $field) {
      $key = strtolower(trim(preg_replace('/[^a-zA-Z0-9]+/', '_', $field), '_'));
      if ($key !== '' && !isset($fields[$key])) {
        $fields[$key] = trim($field);
      }
    }
  }
  return $fields;
}

function surat_jalan_table_config($xml)
{
  $tables = array();
  if (!preg_match_all('/<w:tbl\b.*?<\/w:tbl>/is', $xml, $matches)) {
    return array('table_index' => -1, 'columns' => array());
  }
  foreach ($matches[0] as $index => $tableXml) {
    preg_match_all('/<w:tr\b.*?<\/w:tr>/is', $tableXml, $rows);
    $header = isset($rows[0][0]) ? strtolower(surat_jalan_xml_text($rows[0][0])) : '';
    $isItemTable = preg_match('/barang|item|material|nama|qty|jumlah|satuan|keterangan/', $header);
    if ($isItemTable) {
      $cells = array();
      preg_match_all('/<w:tc\b.*?<\/w:tc>/is', $rows[0][0], $cellMatches);
      foreach ($cellMatches[0] as $cell) {
        $cells[] = strtolower(trim(surat_jalan_xml_text($cell)));
      }
      $columns = array();
      foreach ($cells as $position => $cell) {
        if (preg_match('/no|nomor|urut/', $cell)) {
          $columns[$position] = 'nomor_item';
        } elseif (preg_match('/barang|item|material|nama/', $cell)) {
          $columns[$position] = 'nama_barang';
        } elseif (preg_match('/qty|jumlah|kuantitas/', $cell)) {
          $columns[$position] = 'jumlah';
        } elseif (preg_match('/satuan|unit/', $cell)) {
          $columns[$position] = 'satuan';
        } elseif (preg_match('/keterangan|catatan|note/', $cell)) {
          $columns[$position] = 'keterangan';
        } else {
          $columns[$position] = 'keterangan';
        }
      }
      return array('table_index' => $index, 'columns' => $columns);
    }
  }
  return array('table_index' => -1, 'columns' => array());
}

function surat_jalan_read_template($absolutePath)
{
  if (!class_exists('ZipArchive')) {
    throw new RuntimeException('PHP ZipArchive wajib diaktifkan untuk membaca template Word.');
  }
  $zip = new ZipArchive();
  if ($zip->open($absolutePath) !== true) {
    throw new RuntimeException('Template Word tidak dapat dibaca.');
  }
  $xml = $zip->getFromName('word/document.xml');
  $zip->close();
  if (!$xml) {
    throw new RuntimeException('Template Word tidak memiliki document.xml yang valid.');
  }
  return array(
    'fields' => surat_jalan_template_fields($xml),
    'table_config' => surat_jalan_table_config($xml)
  );
}

function surat_jalan_parse_json($value, $fallback)
{
  $decoded = json_decode((string) $value, true);
  return is_array($decoded) ? $decoded : $fallback;
}

function surat_jalan_number($prefix, $number, $digits)
{
  return $prefix . '-' . str_pad((string) $number, (int) $digits, '0', STR_PAD_LEFT);
}

function surat_jalan_replace_text($text, $fields)
{
  $text = preg_replace_callback('/\{\{\s*([a-zA-Z][a-zA-Z0-9 _-]{0,60})\s*\}\}/', function ($match) use ($fields) {
    $key = strtolower(trim(preg_replace('/[^a-zA-Z0-9]+/', '_', $match[1]), '_'));
    return isset($fields[$key]) ? (string) $fields[$key] : '';
  }, $text);
  return preg_replace_callback('/([A-Za-z][A-Za-z0-9 _-]{1,60})\s*([:：])\s*[._…]{3,}/u', function ($match) use ($fields) {
    $key = strtolower(trim(preg_replace('/[^a-zA-Z0-9]+/', '_', $match[1]), '_'));
    return $match[1] . $match[2] . ' ' . (isset($fields[$key]) ? (string) $fields[$key] : '');
  }, $text);
}

function surat_jalan_docx_part_path($basePath, $target)
{
  $path = dirname($basePath) . '/' . str_replace('\\', '/', $target);
  $segments = array();
  foreach (explode('/', $path) as $segment) {
    if ($segment === '..') {
      array_pop($segments);
    } elseif ($segment !== '.' && $segment !== '') {
      $segments[] = $segment;
    }
  }
  return implode('/', $segments);
}

function surat_jalan_docx_relationships($zip, $partPath)
{
  $relationshipPath = dirname($partPath) . '/_rels/' . basename($partPath) . '.rels';
  $relationships = array();
  $xml = $zip->getFromName($relationshipPath);
  if (!$xml || !preg_match_all('/<Relationship\b([^>]*)\/?>/i', $xml, $matches)) {
    return $relationships;
  }
  foreach ($matches[1] as $attributes) {
    preg_match('/\bId="([^"]+)"/i', $attributes, $idMatch);
    preg_match('/\bTarget="([^"]+)"/i', $attributes, $targetMatch);
    if (isset($idMatch[1], $targetMatch[1])) {
      $resolved = surat_jalan_docx_part_path($partPath, $targetMatch[1]);
      if ($zip->getFromName($resolved) === false) {
        $relativeTarget = preg_replace('/^(?:\.\.\/)+/', '', str_replace('\\', '/', $targetMatch[1]));
        $fallback = dirname($partPath) . '/' . $relativeTarget;
        if ($zip->getFromName($fallback) !== false) {
          $resolved = $fallback;
        }
      }
      $relationships[$idMatch[1]] = $resolved;
    }
  }
  return $relationships;
}

function surat_jalan_docx_images($zip, $xml, $partPath)
{
  $relationships = surat_jalan_docx_relationships($zip, $partPath);
  $images = array();
  if (!preg_match_all('/(?:r:embed|r:id)="([^"]+)"/i', $xml, $matches)) {
    return $images;
  }
  foreach (array_unique($matches[1]) as $relationshipId) {
    if (!isset($relationships[$relationshipId])) {
      continue;
    }
    $image = $zip->getFromName($relationships[$relationshipId]);
    if ($image === false) {
      continue;
    }
    $extension = strtolower(pathinfo($relationships[$relationshipId], PATHINFO_EXTENSION));
    $mimeTypes = array('jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'gif' => 'image/gif', 'bmp' => 'image/bmp');
    if (isset($mimeTypes[$extension])) {
      $images[$relationshipId] = 'data:' . $mimeTypes[$extension] . ';base64,' . base64_encode($image);
    }
  }
  return $images;
}

function surat_jalan_docx_render_fragment($xml, $fields, $images)
{
  $xml = preg_replace_callback('/<w:drawing\b.*?<\/w:drawing>|<w:pict\b.*?<\/w:pict>/is', function ($match) use ($images) {
    if (preg_match('/(?:r:embed|r:id)="([^"]+)"/i', $match[0], $embed)) {
      if (isset($images[$embed[1]])) {
        return '<img class="template-image" src="' . $images[$embed[1]] . '" alt="">';
      }
    }
    return '';
  }, $xml);
  $html = '';
  preg_match_all('/<w:tbl\b.*?<\/w:tbl>|<w:p\b.*?<\/w:p>/is', $xml, $blocks);
  foreach ($blocks[0] as $block) {
    if (preg_match('/^<w:tbl\b/is', $block)) {
      $html .= surat_jalan_render_docx_table($block, $fields, $images);
      continue;
    }
    $text = surat_jalan_xml_text($block);
    $text = surat_jalan_replace_text($text, $fields);
    $text = nl2br(surat_jalan_h($text));
    preg_match_all('/<img class="template-image" src="([^"]+)" alt="">/', $block, $imageTags);
    foreach ($imageTags[1] as $image) {
      $text .= '<img class="template-image" src="' . $image . '" alt="">';
    }
    if (trim(strip_tags($text)) !== '' || count($imageTags[1]) > 0) {
      $html .= '<p>' . $text . '</p>';
    }
  }
  return $html;
}

function surat_jalan_docx_html($absolutePath, $fields, $items, $tableConfig)
{
  $zip = new ZipArchive();
  if ($zip->open($absolutePath) !== true) {
    throw new RuntimeException('Template Word tidak dapat dibuka untuk PDF.');
  }
  $xml = $zip->getFromName('word/document.xml');
  if (!$xml) {
    $zip->close();
    throw new RuntimeException('Template Word tidak memiliki document.xml yang valid.');
  }
  $headerHtml = '';
  $headerParts = array();
  for ($headerIndex = 1; $headerIndex <= 9; $headerIndex++) {
    $headerPath = 'word/header' . $headerIndex . '.xml';
    $headerXml = $zip->getFromName($headerPath);
    if ($headerXml) {
      $headerParts[$headerPath] = $headerXml;
    }
  }
  foreach ($headerParts as $headerPath => $headerXml) {
    $headerHtml .= '<div class="template-header">' . surat_jalan_docx_render_fragment(
      $headerXml,
      $fields,
      surat_jalan_docx_images($zip, $headerXml, $headerPath)
    ) . '</div>';
  }
  $body = preg_replace('/^.*?<w:body\b[^>]*>/is', '', $xml);
  $body = preg_replace('/<\/w:body>.*$/is', '', $body);
  $parts = preg_split('/(<w:tbl\b.*?<\/w:tbl>)/is', $body, -1, PREG_SPLIT_DELIM_CAPTURE);
  $html = $headerHtml;
  $tableIndex = 0;
  foreach ($parts as $part) {
    if (preg_match('/^<w:tbl\b/is', $part)) {
      if ((int) $tableConfig['table_index'] === $tableIndex) {
        $html .= surat_jalan_render_items_table($items, $tableConfig['columns']);
      } else {
        $html .= surat_jalan_render_docx_table($part, $fields);
      }
      $tableIndex++;
      continue;
    }
    $html .= surat_jalan_docx_render_fragment($part, $fields, surat_jalan_docx_images($zip, $part, 'word/document.xml'));
  }
  $zip->close();
  return $html;
}

function surat_jalan_render_docx_table($xml, $fields, $images = array())
{
  $html = '<table class="template-table">';
  preg_match_all('/<w:tr\b.*?<\/w:tr>/is', $xml, $rows);
  foreach ($rows[0] as $row) {
    $html .= '<tr>';
    preg_match_all('/<w:tc\b.*?<\/w:tc>/is', $row, $cells);
    foreach ($cells[0] as $cell) {
      $text = surat_jalan_xml_text($cell);
      $text = surat_jalan_replace_text($text, $fields);
      $cellHtml = nl2br(surat_jalan_h($text));
      preg_match_all('/<img class="template-image" src="([^"]+)" alt="">/', $cell, $imageTags);
      foreach ($imageTags[1] as $image) {
        $cellHtml .= '<img class="template-image" src="' . $image . '" alt="">';
      }
      $html .= '<td>' . $cellHtml . '</td>';
    }
    $html .= '</tr>';
  }
  return $html . '</table>';
}

function surat_jalan_render_items_table($items, $columns)
{
  $html = '<table class="template-table items-table"><thead><tr>';
  foreach ($columns as $column) {
    $html .= '<th>' . surat_jalan_h(ucwords(str_replace('_', ' ', $column))) . '</th>';
  }
  $html .= '</tr></thead><tbody>';
  foreach ($items as $item) {
    $html .= '<tr>';
    foreach ($columns as $column) {
      $value = isset($item[$column]) ? $item[$column] : '';
      $html .= '<td>' . surat_jalan_h($value) . '</td>';
    }
    $html .= '</tr>';
  }
  return $html . '</tbody></table>';
}

function surat_jalan_pdf_output($content)
{
  require_once dirname(__DIR__, 2) . '/assets/js/plugin/dompdf/autoload.inc.php';
  $html = '<!doctype html><html><head><meta charset="utf-8"><style>
@page{margin:18mm 16mm}body{font-family:DejaVu Sans,Arial,sans-serif;font-size:11px;color:#222;line-height:1.35}p{margin:0 0 7px;min-height:12px}.template-header{margin:0 0 12px;padding-bottom:4px}.template-header p{margin:0;min-height:0}.template-image{display:inline-block;max-width:100%;max-height:42mm;vertical-align:middle}.template-table{width:100%;border-collapse:collapse;margin:8px 0 12px}.template-table td,.template-table th{border:1px solid #444;padding:5px;vertical-align:top}.template-table th{font-weight:bold;background:#f2f2f2}.items-table th{text-align:center}.items-table td{height:20px}
</style></head><body>' . $content . '</body></html>';
  $dompdf = new Dompdf\Dompdf();
  $dompdf->loadHtml($html, 'UTF-8');
  $dompdf->setPaper('A4', 'portrait');
  $dompdf->render();
  return $dompdf->output();
}
