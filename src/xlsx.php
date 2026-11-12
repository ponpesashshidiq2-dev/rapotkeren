<?php
// Parser sederhana XLSX (zip + XML) tanpa library ekstensi tambahan.
// Mengembalikan array baris, tiap baris array string per kolom (0-indexed).

function xlsx_strip_ns(string $xml): string {
    // Buang deklarasi namespace agar SimpleXML xpath tanpa prefix
    $xml = preg_replace('/xmlns(:\w+)?="[^"]*"/', '', $xml);
    return $xml;
}

function xlsx_read_rows(string $file): ?array {
    if (!class_exists('ZipArchive')) return null;
    $zip = new ZipArchive();
    if ($zip->open($file) !== true) return null;

    // Shared strings
    $shared = [];
    $xml = $zip->getFromName('xl/sharedStrings.xml');
    if ($xml !== false) {
        $sx = @simplexml_load_string(xlsx_strip_ns($xml));
        if ($sx !== false) {
            foreach ($sx->si as $si) {
                $txt = '';
                foreach ($si->t as $t) $txt .= (string)$t;
                if ($txt === '' && isset($si->r)) {
                    foreach ($si->r as $r) {
                        if (isset($r->t)) $txt .= (string)$r->t;
                    }
                }
                $shared[] = $txt;
            }
        }
    }

    // Sheet pertama
    $sheetXml = $zip->getFromName('xl/worksheets/sheet1.xml');
    if ($sheetXml === false) {
        for ($i = 1; $i < 20; $i++) {
            $sheetXml = $zip->getFromName("xl/worksheets/sheet{$i}.xml");
            if ($sheetXml !== false) break;
        }
    }
    $zip->close();
    if ($sheetXml === false) return null;

    $sx = @simplexml_load_string(xlsx_strip_ns($sheetXml));
    if ($sx === false) return null;

    $rows = [];
    foreach ($sx->sheetData->row as $row) {
        $cells = [];
        foreach ($row->c as $c) {
            $ref = (string)$c['r'];           // mis. B3
            $colIdx = 0;
            if (preg_match('/^([A-Z]+)/', $ref, $m)) {
                foreach (str_split($m[1]) as $ch) {
                    $colIdx = $colIdx * 26 + (ord($ch) - 64);
                }
                $colIdx--;                   // 0-indexed
            } else {
                $colIdx = count($cells);
            }
            $type = (string)$c['t'];
            $val = '';
            if ($type === 'inlineStr') {
                foreach ($c->is->t as $t) $val .= (string)$t;
            } else {
                $v = (string)$c->v;
                if ($type === 's') {
                    $val = $shared[(int)$v] ?? '';
                } else {
                    $val = $v;
                }
            }
            $cells[$colIdx] = $val;
        }
        if ($cells) {
            $max = max(array_keys($cells));
            $line = [];
            for ($i = 0; $i <= $max; $i++) $line[$i] = $cells[$i] ?? '';
            $rows[] = $line;
        } else {
            $rows[] = [];
        }
    }
    return $rows;
}

// Ubah serial tanggal Excel (mis. 44271) menjadi Y-m-d
function xlsx_serial_to_date($val): string {
    if (!is_numeric($val)) return trim((string)$val);
    $n = (float)$val;
    if ($n < 20000 || $n > 60000) return trim((string)$val);
    $ts = (int)round(($n - 25569) * 86400); // 25569 = hari 1970-01-01 sejak 1899-12-30
    return gmdate('Y-m-d', $ts);
}

/**
 * Tulis array baris (array of array string) menjadi file XLSX valid.
 * @param array $rows  [[col1, col2, ...], ...]
 * @param array $colWidths  lebar kolom opsional (dalam character width)
 * @return string|null  isi file XLSX (binary), null bila ZipArchive tidak ada
 */
function xlsx_write_rows(array $rows, array $colWidths = []): ?string {
    if (!class_exists('ZipArchive')) return null;

    $colName = function (int $i): string {
        $s = '';
        $i++;
        while ($i > 0) {
            $m = ($i - 1) % 26;
            $s = chr(65 + $m) . $s;
            $i = intdiv($i - 1, 26);
        }
        return $s;
    };

    $xmlEnc = fn(string $s): string => htmlspecialchars($s, ENT_XML1 | ENT_QUOTES, 'UTF-8');

    // sheet XML
    $sheet = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . "\n";
    $sheet .= '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">';
    if ($colWidths) {
        $sheet .= '<cols>';
        foreach ($colWidths as $i => $w) {
            $sheet .= '<col min="' . ($i + 1) . '" max="' . ($i + 1) . '" width="' . (float)$w . '" customWidth="1"/>';
        }
        $sheet .= '</cols>';
    }
    $sheet .= '<sheetData>';
    foreach ($rows as $r => $row) {
        $rn = $r + 1;
        $sheet .= '<row r="' . $rn . '">';
        foreach ($row as $c => $val) {
            $ref = $colName($c) . $rn;
            // tanggal Y-m-d → serial Excel agar diformat tanggal oleh Excel
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)$val)) {
                $ts = strtotime((string)$val . ' UTC');
                $serial = (int)($ts / 86400) + 25569;
                $sheet .= '<c r="' . $ref . '"><v>' . $serial . '</v></c>';
            } elseif (is_numeric($val) && $val !== '' && !preg_match('/^0\d/', (string)$val)) {
                $sheet .= '<c r="' . $ref . '"><v>' . $val . '</v></c>';
            } else {
                $sheet .= '<c r="' . $ref . '" t="inlineStr"><is><t xml:space="preserve">' . $xmlEnc((string)$val) . '</t></is></c>';
            }
        }
        $sheet .= '</row>';
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
        . '<sheets><sheet name="Template" sheetId="1" r:id="rId1"/></sheets>'
        . '</workbook>';

    $wbRels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
        . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
        . '</Relationships>';

    $tmp = tempnam(sys_get_temp_dir(), 'xlsx');
    $zip = new ZipArchive();
    if ($zip->open($tmp, ZipArchive::OVERWRITE) !== true) {
        @unlink($tmp);
        return null;
    }
    $zip->addFromString('[Content_Types].xml', $contentTypes);
    $zip->addFromString('_rels/.rels', $rels);
    $zip->addFromString('xl/workbook.xml', $workbook);
    $zip->addFromString('xl/_rels/workbook.xml.rels', $wbRels);
    $zip->addFromString('xl/worksheets/sheet1.xml', $sheet);
    $zip->close();
    $data = file_get_contents($tmp);
    @unlink($tmp);
    return $data ?: null;
}
