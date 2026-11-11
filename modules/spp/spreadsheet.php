<?php
function spp_xlsx_xml_escape($value)
{
  return htmlspecialchars((string) $value, ENT_QUOTES | ENT_XML1, 'UTF-8');
}

function spp_create_template_xlsx()
{
  if (!class_exists('ZipArchive')) {
    throw new RuntimeException('Ekstensi ZIP PHP diperlukan untuk membuat template Excel.');
  }

  $path = tempnam(sys_get_temp_dir(), 'spp-template-');
  if ($path === false) {
    throw new RuntimeException('File template Excel tidak dapat dibuat.');
  }

  $zip = new ZipArchive();
  if ($zip->open($path, ZipArchive::OVERWRITE) !== true) {
    unlink($path);
    throw new RuntimeException('File template Excel tidak dapat disiapkan.');
  }

  $headers = array('Nomor SPP', 'Tanggal', 'Nama Item', 'Ukuran & Spesifikasi', 'Jumlah Diminta', 'Satuan', 'Untuk Pekerjaan', 'Keterangan');
  $cells = array();
  foreach ($headers as $index => $header) {
    $column = '';
    $number = $index + 1;
    while ($number > 0) {
      $remainder = ($number - 1) % 26;
      $column = chr(65 + $remainder) . $column;
      $number = (int) (($number - $remainder - 1) / 26);
    }
    $cells[] = '<c r="' . $column . '1" t="inlineStr"><is><t>' . spp_xlsx_xml_escape($header) . '</t></is></c>';
  }

  $files = array(
    '[Content_Types].xml' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' .
      '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">' .
      '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>' .
      '<Default Extension="xml" ContentType="application/xml"/>' .
      '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>' .
      '<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>' .
      '</Types>',
    '_rels/.rels' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' .
      '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">' .
      '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>' .
      '</Relationships>',
    'xl/workbook.xml' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' .
      '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">' .
      '<sheets><sheet name="Data SPP" sheetId="1" r:id="rId1"/></sheets></workbook>',
    'xl/_rels/workbook.xml.rels' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' .
      '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">' .
      '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>' .
      '</Relationships>',
    'xl/worksheets/sheet1.xml' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' .
      '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData><row r="1">' .
      implode('', $cells) . '</row></sheetData></worksheet>'
  );

  foreach ($files as $name => $contents) {
    if (!$zip->addFromString($name, $contents)) {
      $zip->close();
      unlink($path);
      throw new RuntimeException('Isi template Excel tidak dapat dibuat.');
    }
  }
  if (!$zip->close()) {
    unlink($path);
    throw new RuntimeException('File template Excel tidak dapat diselesaikan.');
  }

  return $path;
}

function spp_xlsx_cell_column($reference)
{
  if (!preg_match('/^([A-Z]+)/i', $reference, $matches)) {
    return false;
  }
  $column = 0;
  foreach (str_split(strtoupper($matches[1])) as $letter) {
    $column = ($column * 26) + ord($letter) - 64;
  }
  return $column - 1;
}

function spp_xlsx_first_sheet_path($zip, $workbookXml)
{
  $workbook = @simplexml_load_string($workbookXml, 'SimpleXMLElement', LIBXML_NONET);
  if (!$workbook) {
    throw new RuntimeException('Struktur workbook Excel tidak valid.');
  }
  $relationshipNamespace = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships';
  $sheets = $workbook->xpath('//*[local-name()="sheet"]');
  if (!$sheets || !isset($sheets[0])) {
    throw new RuntimeException('Workbook Excel tidak memiliki sheet data.');
  }
  $sheetAttributes = $sheets[0]->attributes($relationshipNamespace);
  $relationshipId = isset($sheetAttributes['id']) ? (string) $sheetAttributes['id'] : '';
  $relationshipsXml = $zip->getFromName('xl/_rels/workbook.xml.rels');
  if ($relationshipId === '' || $relationshipsXml === false) {
    throw new RuntimeException('Relasi sheet Excel tidak valid.');
  }
  $relationships = @simplexml_load_string($relationshipsXml, 'SimpleXMLElement', LIBXML_NONET);
  if (!$relationships) {
    throw new RuntimeException('Relasi sheet Excel tidak valid.');
  }
  foreach ($relationships->children('http://schemas.openxmlformats.org/package/2006/relationships') as $relationship) {
    $relationshipAttributes = $relationship->attributes();
    if (!isset($relationshipAttributes['Id']) || (string) $relationshipAttributes['Id'] !== $relationshipId) {
      continue;
    }
    $target = isset($relationshipAttributes['Target']) ? (string) $relationshipAttributes['Target'] : '';
    if ($target === '' || (isset($relationshipAttributes['TargetMode']) && (string) $relationshipAttributes['TargetMode'] === 'External')) {
      break;
    }
    if (substr($target, 0, 1) === '/') {
      $path = ltrim($target, '/');
    } else {
      $path = 'xl/' . $target;
    }
    $segments = array();
    foreach (explode('/', $path) as $segment) {
      if ($segment === '' || $segment === '.') {
        continue;
      }
      if ($segment === '..') {
        if (count($segments) === 0) {
          break;
        }
        array_pop($segments);
      } else {
        $segments[] = $segment;
      }
    }
    $path = implode('/', $segments);
    if (strpos($path, 'xl/worksheets/') === 0) {
      return $path;
    }
    break;
  }
  throw new RuntimeException('Sheet data pertama tidak dapat dibaca.');
}

function spp_xlsx_read_table($path)
{
  if (!class_exists('ZipArchive') || !function_exists('simplexml_load_string')) {
    throw new RuntimeException('Ekstensi ZIP dan SimpleXML PHP diperlukan untuk membaca Excel.');
  }
  $zip = new ZipArchive();
  if ($zip->open($path) !== true) {
    throw new RuntimeException('File bukan workbook Excel .xlsx yang valid.');
  }
  if ($zip->numFiles > 50) {
    $zip->close();
    throw new RuntimeException('Struktur file Excel tidak valid.');
  }
  $uncompressedSize = 0;
  for ($index = 0; $index < $zip->numFiles; $index++) {
    $stat = $zip->statIndex($index);
    if ($stat && isset($stat['size'])) {
      $uncompressedSize += (int) $stat['size'];
      if ($uncompressedSize > 15728640) {
        $zip->close();
        throw new RuntimeException('Ukuran isi workbook Excel terlalu besar.');
      }
    }
  }

  $workbookXml = $zip->getFromName('xl/workbook.xml');
  $sharedStringsXml = $zip->getFromName('xl/sharedStrings.xml');
  if ($workbookXml === false) {
    $zip->close();
    throw new RuntimeException('Workbook Excel tidak valid. Gunakan file .xlsx.');
  }
  $sheetPath = spp_xlsx_first_sheet_path($zip, $workbookXml);
  $sheetXml = $zip->getFromName($sheetPath);
  if ($sheetXml === false) {
    $zip->close();
    throw new RuntimeException('Sheet data Excel tidak dapat dibaca.');
  }

  $sharedStrings = array();
  if ($sharedStringsXml !== false) {
    $sharedDocument = @simplexml_load_string($sharedStringsXml, 'SimpleXMLElement', LIBXML_NONET);
    if (!$sharedDocument) {
      $zip->close();
      throw new RuntimeException('Daftar teks Excel tidak valid.');
    }
    foreach ($sharedDocument->children('http://schemas.openxmlformats.org/spreadsheetml/2006/main') as $sharedString) {
      $text = '';
      foreach ($sharedString->xpath('.//*[local-name()="t"]') as $textNode) {
        $text .= (string) $textNode;
      }
      $sharedStrings[] = $text;
    }
  }

  $sheet = @simplexml_load_string($sheetXml, 'SimpleXMLElement', LIBXML_NONET);
  if (!$sheet) {
    $zip->close();
    throw new RuntimeException('Sheet data Excel tidak valid.');
  }
  $rows = $sheet->xpath('//*[local-name()="sheetData"]/*[local-name()="row"]');
  if (!$rows) {
    $zip->close();
    throw new RuntimeException('Sheet Excel belum memiliki baris data.');
  }
  $table = array();
  foreach ($rows as $row) {
    $rowAttributes = $row->attributes();
    $rowNumber = isset($rowAttributes['r']) ? (int) $rowAttributes['r'] : count($table) + 1;
    if ($rowNumber > 102) {
      $zip->close();
      throw new RuntimeException('File Excel maksimal berisi 100 baris item.');
    }
    $values = array();
    foreach ($row->children('http://schemas.openxmlformats.org/spreadsheetml/2006/main') as $cell) {
      if ($cell->getName() !== 'c') {
        continue;
      }
      $attributes = $cell->attributes();
      $column = spp_xlsx_cell_column(isset($attributes['r']) ? (string) $attributes['r'] : '');
      if ($column === false || $column > 50) {
        continue;
      }
      $type = isset($attributes['t']) ? (string) $attributes['t'] : '';
      $value = '';
      if ($type === 'inlineStr') {
        foreach ($cell->xpath('.//*[local-name()="t"]') as $textNode) {
          $value .= (string) $textNode;
        }
      } elseif ($type === 's') {
        $valueNode = $cell->xpath('./*[local-name()="v"]');
        $stringIndex = $valueNode && isset($valueNode[0]) ? (int) $valueNode[0] : -1;
        $value = isset($sharedStrings[$stringIndex]) ? $sharedStrings[$stringIndex] : '';
      } else {
        $valueNode = $cell->xpath('./*[local-name()="v"]');
        $value = $valueNode && isset($valueNode[0]) ? (string) $valueNode[0] : '';
      }
      $values[$column] = $value;
    }
    $table[$rowNumber] = array('values' => $values, 'row' => $rowNumber);
  }
  $zip->close();

  return $table;
}

function spp_xlsx_date($value)
{
  $value = trim((string) $value);
  if ($value === '') {
    return false;
  }
  if (is_numeric($value)) {
    $serial = (float) $value;
    if ($serial < 1 || $serial > 2958465) {
      return false;
    }
    $timestamp = (int) floor(($serial - 25569) * 86400);
    return gmdate('Y-m-d', $timestamp);
  }
  foreach (array('!Y-m-d', '!d/m/Y', '!d-m-Y', '!Y/m/d') as $format) {
    $date = DateTime::createFromFormat($format, $value);
    if ($date && $date->format(substr($format, 1)) === $value) {
      return $date->format('Y-m-d');
    }
  }
  return false;
}

function spp_xlsx_parse_items($path)
{
  $table = spp_xlsx_read_table($path);
  $headerRow = isset($table[1]) ? $table[1]['values'] : array();
  $requiredHeaders = array(
    'nomorspp' => 'nomor_spp',
    'tanggal' => 'tanggal',
    'namaitem' => 'nama_item',
    'ukuranspesifikasi' => 'spesifikasi',
    'jumlahdiminta' => 'jumlah_diminta',
    'satuan' => 'satuan',
    'untukpekerjaan' => 'untuk_pekerjaan',
    'keterangan' => 'keterangan'
  );
  $columns = array();
  foreach ($headerRow as $index => $value) {
    $key = strtolower(preg_replace('/[^a-z0-9]/i', '', trim((string) $value)));
    if (isset($requiredHeaders[$key])) {
      $columns[$requiredHeaders[$key]] = $index;
    }
  }
  if (count($columns) !== count($requiredHeaders)) {
    throw new RuntimeException('Kolom template tidak sesuai. Unduh dan gunakan template Excel SPP.');
  }

  $items = array();
  $number = '';
  $date = '';
  foreach ($table as $rowIndex => $rowData) {
    if ($rowIndex === 1) {
      continue;
    }
    $row = $rowData['values'];
    $allEmpty = true;
    foreach ($columns as $column) {
      if (isset($row[$column]) && trim((string) $row[$column]) !== '') {
        $allEmpty = false;
        break;
      }
    }
    if ($allEmpty) {
      continue;
    }
    $currentNumber = isset($row[$columns['nomor_spp']]) ? trim((string) $row[$columns['nomor_spp']]) : '';
    if ($currentNumber !== '') {
      if ($number !== '' && $number !== $currentNumber) {
        throw new RuntimeException('Satu file hanya dapat memuat satu nomor SPP.');
      }
      $number = $currentNumber;
    }
    $currentDateValue = isset($row[$columns['tanggal']]) ? $row[$columns['tanggal']] : '';
    $currentDate = $currentDateValue === '' ? '' : spp_xlsx_date($currentDateValue);
    if ($currentDateValue !== '' && $currentDate === false) {
      throw new RuntimeException('Tanggal pada baris ' . (int) $rowData['row'] . ' tidak valid. Gunakan format YYYY-MM-DD atau DD/MM/YYYY.');
    }
    if ($currentDate !== '') {
      if ($date !== '' && $date !== $currentDate) {
        throw new RuntimeException('Tanggal SPP harus sama pada semua baris.');
      }
      $date = $currentDate;
    }
    $getValue = function ($columnName) use ($row, $columns) {
      return isset($row[$columns[$columnName]]) ? trim((string) $row[$columns[$columnName]]) : '';
    };
    $name = $getValue('nama_item');
    $specification = $getValue('spesifikasi');
    $quantity = $getValue('jumlah_diminta');
    $unit = $getValue('satuan');
    $work = $getValue('untuk_pekerjaan');
    $note = $getValue('keterangan');
    if ($name === '' || $quantity === '' || $unit === '') {
      throw new RuntimeException('Nama item, jumlah diminta, dan satuan wajib diisi pada baris ' . (int) $rowData['row'] . '.');
    }
    $items[] = array(
      'nama' => $name,
      'spesifikasi' => $specification,
      'jumlah' => $quantity,
      'satuan' => $unit,
      'pekerjaan' => $work,
      'keterangan' => $note,
      'row' => (int) $rowData['row']
    );
    if (count($items) > 100) {
      throw new RuntimeException('File Excel maksimal berisi 100 baris item.');
    }
  }

  if (count($items) < 1) {
    throw new RuntimeException('File Excel belum berisi item SPP.');
  }
  if ($number === '' || $date === '') {
    throw new RuntimeException('Nomor SPP dan tanggal wajib diisi pada baris pertama data.');
  }
  return array('nomor_spp' => $number, 'tanggal' => $date, 'items' => $items);
}
