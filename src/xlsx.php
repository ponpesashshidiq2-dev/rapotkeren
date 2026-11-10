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
