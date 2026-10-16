<?php

function xlsx_col_index(string $letters): int
{
    $n = 0;
    $length = strlen($letters);

    for ($i = 0; $i < $length; $i++) {
        $n = $n * 26 + (ord($letters[$i]) - 64);
    }

    return $n - 1;
}

function xlsx_load_xml(string $xml): SimpleXMLElement
{
    $xml = preg_replace('/\sxmlns(?::[A-Za-z0-9]+)?="[^"]*"/', '', $xml) ?? $xml;
    $xml = preg_replace('/\s[A-Za-z0-9]+:[A-Za-z0-9]+="[^"]*"/', '', $xml) ?? $xml;
    libxml_use_internal_errors(true);
    $element = simplexml_load_string($xml, 'SimpleXMLElement', LIBXML_NONET | LIBXML_NOCDATA | LIBXML_NOERROR);
    libxml_clear_errors();

    if ($element === false) {
        throw new RuntimeException('The Excel file could not be read.');
    }

    return $element;
}

function xlsx_plain_text(SimpleXMLElement $node): string
{
    $text = '';

    foreach ($node->xpath('.//t') ?: [] as $part) {
        $text .= (string) $part;
    }

    return $text;
}

function xlsx_open_package(string $path): array
{
    if (class_exists('ZipArchive')) {
        $zip = new ZipArchive();

        if ($zip->open($path) !== true) {
            throw new RuntimeException('That file is not a readable .xlsx spreadsheet.');
        }

        return ['kind' => 'zip', 'zip' => $zip, 'copy' => ''];
    }

    if (! class_exists('PharData')) {
        throw new RuntimeException('PHP zip support is off. Enable the zip extension in php.ini and restart Apache.');
    }

    $copy = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'casxlsx_' . bin2hex(random_bytes(6)) . '.zip';

    if (! copy($path, $copy)) {
        throw new RuntimeException('The Excel file could not be opened.');
    }

    try {
        $phar = new PharData($copy, 0, null, Phar::ZIP);
    } catch (Throwable $e) {
        @unlink($copy);
        throw new RuntimeException('That file is not a readable .xlsx spreadsheet.');
    }

    return ['kind' => 'phar', 'phar' => $phar, 'copy' => $copy];
}

function xlsx_package_entry(array $package, string $name): string|false
{
    if ($package['kind'] === 'zip') {
        $data = $package['zip']->getFromName($name);

        return $data === false ? false : (string) $data;
    }

    try {
        if (! isset($package['phar'][$name])) {
            return false;
        }

        return (string) $package['phar'][$name]->getContent();
    } catch (Throwable $e) {
        return false;
    }
}

function xlsx_close_package(array $package): void
{
    if ($package['kind'] === 'zip') {
        $package['zip']->close();
    }

    if (($package['copy'] ?? '') !== '') {
        @unlink($package['copy']);
    }
}

function xlsx_shared_strings(array $package): array
{
    $xml = xlsx_package_entry($package, 'xl/sharedStrings.xml');

    if ($xml === false) {
        return [];
    }

    $document = xlsx_load_xml($xml);
    $strings = [];

    foreach ($document->si as $item) {
        $strings[] = xlsx_plain_text($item);
    }

    return $strings;
}

function xlsx_first_sheet_path(array $package): string
{
    if (xlsx_package_entry($package, 'xl/worksheets/sheet1.xml') !== false) {
        return 'xl/worksheets/sheet1.xml';
    }

    throw new RuntimeException('The Excel file has no worksheets.');
}

function xlsx_cell_text(SimpleXMLElement $cell, array $shared): string
{
    $type = (string) $cell['t'];
    $raw = isset($cell->v) ? trim((string) $cell->v) : '';

    if ($type === 's') {
        return (string) ($shared[(int) $raw] ?? '');
    }

    if ($type === 'inlineStr') {
        return xlsx_plain_text($cell);
    }

    if ($type === 'b') {
        return ($raw === '1') ? 'TRUE' : 'FALSE';
    }

    return $raw;
}

function xlsx_read_rows(string $path): array
{
    $package = xlsx_open_package($path);
    $shared = xlsx_shared_strings($package);
    $sheetPath = xlsx_first_sheet_path($package);
    $sheetXml = xlsx_package_entry($package, $sheetPath);
    xlsx_close_package($package);

    if ($sheetXml === false) {
        throw new RuntimeException('The Excel worksheet could not be opened.');
    }

    $sheet = xlsx_load_xml($sheetXml);
    $grid = [];

    if (! isset($sheet->sheetData->row)) {
        throw new RuntimeException('The spreadsheet is empty.');
    }

    foreach ($sheet->sheetData->row as $row) {
        $rowIndex = ((int) $row['r']) - 1;
        $values = [];

        foreach ($row->c as $cell) {
            $ref = (string) $cell['r'];

            if (! preg_match('/^([A-Z]+)/', $ref, $match)) {
                continue;
            }

            $values[xlsx_col_index($match[1])] = xlsx_cell_text($cell, $shared);
        }

        if ($values === []) {
            continue;
        }

        ksort($values);
        $grid[$rowIndex] = $values;
    }

    if ($grid === []) {
        throw new RuntimeException('The spreadsheet is empty.');
    }

    ksort($grid);
    $headerRow = array_shift($grid);
    $headers = [];

    foreach ($headerRow as $index => $label) {
        $label = (string) $label;
        $label = preg_replace('/^\xEF\xBB\xBF/', '', $label) ?? $label;
        $headers[$index] = strtolower(preg_replace('/[^a-z0-9]+/i', '', $label) ?? '');
    }

    $rows = [];

    foreach ($grid as $cells) {
        $row = [];
        $empty = true;

        foreach ($headers as $index => $key) {
            if ($key === '') {
                continue;
            }

            $value = isset($cells[$index]) ? trim((string) $cells[$index]) : '';
            $row[$key] = $value;

            if ($value !== '') {
                $empty = false;
            }
        }

        if (! $empty) {
            $rows[] = $row;
        }
    }

    return $rows;
}

function xlsx_col_letter(int $index): string
{
    $letter = '';
    $n = $index + 1;

    while ($n > 0) {
        $n--;
        $letter = chr(65 + ($n % 26)) . $letter;
        $n = intdiv($n, 26);
    }

    return $letter;
}

function xlsx_xml_text(string $value): string
{
    return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
}

function xlsx_write_sheet(string $path, array $headers, array $rows): void
{
    $sheet = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>';

    $all = array_merge([$headers], $rows);
    $rowNum = 1;

    foreach ($all as $row) {
        $sheet .= '<row r="' . $rowNum . '">';
        $col = 0;

        foreach ($row as $value) {
            $ref = xlsx_col_letter($col) . $rowNum;
            $sheet .= '<c r="' . $ref . '" t="inlineStr"><is><t>' . xlsx_xml_text((string) $value) . '</t></is></c>';
            $col++;
        }

        $sheet .= '</row>';
        $rowNum++;
    }

    $sheet .= '</sheetData></worksheet>';

    $contentTypes = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
        . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
        . '<Default Extension="xml" ContentType="application/xml"/>'
        . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
        . '<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
        . '</Types>';

    $rels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
        . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
        . '</Relationships>';

    $workbook = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
        . '<sheets><sheet name="Fleet" sheetId="1" r:id="rId1"/></sheets></workbook>';

    $workbookRels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
        . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
        . '</Relationships>';

    if (class_exists('ZipArchive')) {
        $zip = new ZipArchive();
        if ($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Could not create the Excel file.');
        }
        $zip->addFromString('[Content_Types].xml', $contentTypes);
        $zip->addFromString('_rels/.rels', $rels);
        $zip->addFromString('xl/workbook.xml', $workbook);
        $zip->addFromString('xl/_rels/workbook.xml.rels', $workbookRels);
        $zip->addFromString('xl/worksheets/sheet1.xml', $sheet);
        $zip->close();

        return;
    }

    throw new RuntimeException('Excel .xlsx export needs the PHP zip extension.');
}
