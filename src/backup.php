<?php
// Helper backup & restore data berbasis file lokal (JSON / SQL)

function backup_tables(): array {
    // Urutan aman terhadap foreign key (induk → anak)
    return ['cp', 'sub_cp', 'capaian', 'profil', 'refleksi_pertanyaan',
            'anak', 'share', 'penilaian', 'deskripsi', 'foto', 'refleksi', 'revisi'];
}

function backup_table_columns(PDO $db, string $table): array {
    $st = $db->query('PRAGMA table_info("' . $table . '")');
    $cols = [];
    foreach ($st->fetchAll() as $r) $cols[] = $r['name'];
    return $cols;
}

// ---------------------------------------------------------------------------
// EXPORT
// ---------------------------------------------------------------------------

function backup_export_rows(PDO $db): array {
    $data = [];
    foreach (backup_tables() as $t) {
        $data[$t] = $db->query('SELECT * FROM "' . $t . '"')->fetchAll();
    }
    return $data;
}

function backup_export_files(): array {
    $files = [];
    $base = realpath(UPLOAD_DIR);
    if ($base === false || !is_dir($base)) return $files;
    $it = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($base, FilesystemIterator::SKIP_DOTS)
    );
    foreach ($it as $f) {
        if (!$f->isFile()) continue;
        $abs = $f->getPathname();
        $rel = 'uploads/' . str_replace('\\', '/', substr($abs, strlen($base) + 1));
        $files[$rel] = base64_encode(file_get_contents($abs));
    }
    ksort($files);
    return $files;
}

function backup_build_json(PDO $db, bool $withFiles, string $user): string {
    $payload = [
        'app'        => 'raport-tk',
        'type'       => 'backup',
        'version'    => 1,
        'exported_at'=> date('c'),
        'user'       => $user,
        'tables'     => backup_export_rows($db),
    ];
    if ($withFiles) $payload['files'] = backup_export_files();

    $json = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($json === false) {
        throw new RuntimeException('Gagal encoding JSON: ' . json_last_error_msg());
    }
    return $json;
}

function backup_stream_json(PDO $db, bool $withFiles, string $user): void {
    try {
        $json = backup_build_json($db, $withFiles, $user);
    } catch (Throwable $ex) {
        header('HTTP/1.1 500 Internal Server Error');
        echo 'Gagal membuat file backup: ' . e($ex->getMessage());
        exit;
    }
    $name = 'backup-raport-' . date('Ymd-His') . '.json';
    header('Content-Type: application/json; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $name . '"');
    header('Content-Length: ' . strlen($json));
    header('X-Content-Type-Options: nosniff');
    echo $json;
    exit;
}

function backup_sql_quote(string $s): string {
    return "'" . str_replace("'", "''", $s) . "'";
}

function backup_build_sql(PDO $db, string $user): string {
    $out = '';
    $out .= "-- Backup data Raport TK\n";
    $out .= "-- Diexport: " . date('Y-m-d H:i:s') . "\n";
    $out .= "-- Oleh: " . str_replace(['<?', '?>'], '', $user) . "\n\n";
    $out .= "PRAGMA foreign_keys=OFF;\n";
    $out .= "BEGIN TRANSACTION;\n";

    foreach (backup_tables() as $t) {
        $schema = $db->prepare("SELECT sql FROM sqlite_master WHERE type='table' AND name=?");
        $schema->execute([$t]);
        $row = $schema->fetch();
        if ($row && $row['sql']) $out .= $row['sql'] . ";\n";

        $cols = backup_table_columns($db, $t);
        if (!$cols) continue;
        $colList = '"' . implode('","', $cols) . '"';
        $out .= "-- data tabel $t\n";
        foreach ($db->query('SELECT * FROM "' . $t . '"') as $r) {
            $vals = [];
            foreach ($cols as $c) {
                $v = $r[$c] ?? null;
                if ($v === null)          $vals[] = 'NULL';
                elseif (is_int($v))       $vals[] = (string)$v;
                elseif (is_float($v))     $vals[] = (string)$v;
                elseif (is_bool($v))      $vals[] = $v ? '1' : '0';
                else                      $vals[] = backup_sql_quote((string)$v);
            }
            $out .= 'INSERT INTO "' . $t . '" (' . $colList . ') VALUES (' . implode(',', $vals) . ");\n";
        }
        $out .= "\n";
    }

    $out .= "COMMIT;\n";
    $out .= "PRAGMA foreign_keys=ON;\n";
    return $out;
}

function backup_stream_sql(PDO $db, string $user): void {
    try {
        $sql = backup_build_sql($db, $user);
    } catch (Throwable $ex) {
        header('HTTP/1.1 500 Internal Server Error');
        echo 'Gagal membuat file backup: ' . e($ex->getMessage());
        exit;
    }
    $name = 'backup-raport-' . date('Ymd-His') . '.sql';
    header('Content-Type: application/sql; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $name . '"');
    header('X-Content-Type-Options: nosniff');
    echo $sql;
    exit;
}

// ---------------------------------------------------------------------------
// PARSE / VALIDASI FILE
// ---------------------------------------------------------------------------

function backup_parse_json(string $raw): array {
    $data = json_decode($raw, true);
    if (!is_array($data)) {
        return ['tables' => [], 'files' => [], 'errors' => ['File JSON tidak valid atau rusak.']];
    }
    if (($data['app'] ?? '') !== 'raport-tk' || ($data['type'] ?? '') !== 'backup') {
        return ['tables' => [], 'files' => [],
                'errors' => ['File bukan backup Raport TK (field app/type tidak cocok).']];
    }

    $errors = [];
    $known  = backup_tables();
    $tables = [];
    foreach (($data['tables'] ?? []) as $t => $rows) {
        $t = strtolower((string)$t);
        if (!in_array($t, $known, true)) { $errors[] = "Tabel '$t' tidak dikenal — dilewati."; continue; }
        if (!is_array($rows))           { $errors[] = "Isi tabel '$t' bukan daftar baris — dilewati."; continue; }
        $tables[$t] = $rows;
    }

    $files = [];
    foreach (($data['files'] ?? []) as $rel => $b64) {
        $rel = str_replace('\\', '/', (string)$rel);
        if (strpos($rel, 'uploads/') !== 0 || strpos($rel, '..') !== false || !is_string($b64)) {
            $errors[] = "Lokasi file '$rel' tidak valid — dilewati.";
            continue;
        }
        $files[$rel] = $b64;
    }

    if (empty($tables) && empty($files)) $errors[] = 'Tidak ada data yang bisa dipulihkan dari file ini.';
    return ['tables' => $tables, 'files' => $files, 'errors' => $errors];
}

function backup_split_sql_statements(string $sql): array {
    $stmts = []; $cur = ''; $inStr = false; $n = strlen($sql);
    for ($i = 0; $i < $n; $i++) {
        $ch = $sql[$i];
        if ($inStr) {
            $cur .= $ch;
            if ($ch === "'") {
                if (($sql[$i + 1] ?? '') === "'") { $cur .= "'"; $i++; }
                else $inStr = false;
            }
            continue;
        }
        if ($ch === "'")                    { $inStr = true; $cur .= $ch; continue; }
        if ($ch === '-' && ($sql[$i + 1] ?? '') === '-') {
            while ($i < $n && $sql[$i] !== "\n") $i++;
            continue;
        }
        if ($ch === ';') {
            if (trim($cur) !== '') $stmts[] = trim($cur);
            $cur = '';
            continue;
        }
        $cur .= $ch;
    }
    if (trim($cur) !== '') $stmts[] = trim($cur);
    return $stmts;
}

function backup_split_values(string $s): array {
    $vals = []; $cur = ''; $inStr = false; $n = strlen($s);
    for ($i = 0; $i < $n; $i++) {
        $ch = $s[$i];
        if ($inStr) {
            $cur .= $ch;
            if ($ch === "'") {
                if (($s[$i + 1] ?? '') === "'") { $cur .= "'"; $i++; }
                else $inStr = false;
            }
            continue;
        }
        if ($ch === "'")   { $inStr = true; $cur .= $ch; continue; }
        if ($ch === ',')   { $vals[] = trim($cur); $cur = ''; continue; }
        $cur .= $ch;
    }
    if (trim($cur) !== '') $vals[] = trim($cur);

    return array_map(function ($v) {
        if (strcasecmp($v, 'NULL') === 0) return null;
        if (strlen($v) >= 2 && $v[0] === "'" && substr($v, -1) === "'") {
            return str_replace("''", "'", substr($v, 1, -1));
        }
        if (preg_match('/^-?\d+$/', $v))  return (int)$v;
        if (preg_match('/^-?\d+\.\d+$/', $v)) return (float)$v;
        return $v;
    }, $vals);
}

function backup_parse_sql(string $sql): array {
    $errors = []; $tables = []; $known = backup_tables();

    foreach (backup_split_sql_statements($sql) as $stmt) {
        if (preg_match('/^INSERT\s+INTO\s+"?([A-Za-z_][A-Za-z0-9_]*)"?\s+\(([^)]*)\)\s+VALUES\s*\((.*)\)$/is',
                       $stmt, $m)) {
            $t = strtolower($m[1]);
            if (!in_array($t, $known, true)) { $errors[] = "Tabel '$t' tidak dikenal — dilewati."; continue; }
            $cols = array_map(fn($c) => trim($c, " \t\n\r\0\x0B\"'"), explode(',', $m[2]));
            $vals = backup_split_values($m[3]);
            if (count($cols) !== count($vals)) {
                $errors[] = "Jumlah kolom tidak sama dengan nilai pada tabel $t — baris dilewati.";
                continue;
            }
            $tables[$t][] = array_combine($cols, $vals);
            continue;
        }
        if (preg_match('/^(CREATE|SELECT|BEGIN|COMMIT|END|PRAGMA|DROP|ALTER)\b/i', $stmt)) continue;
        if (preg_match('/^INSERT\b/i', $stmt)) $errors[] = 'Statement INSERT tidak dikenali — dilewati.';
    }

    if (empty($tables)) $errors[] = 'Tidak ada data INSERT yang bisa dipulihkan dari file SQL ini.';
    return ['tables' => $tables, 'files' => [], 'errors' => $errors];
}

// ---------------------------------------------------------------------------
// APPLY / RESTORE
// ---------------------------------------------------------------------------

function backup_apply(PDO $db, array $tables, string $mode, array &$warnings): array {
    $known   = backup_tables();
    $mode    = ($mode === 'merge') ? 'merge' : 'replace';
    $counts  = [];
    $hasData = array_intersect_key($tables, array_flip($known));

    $db->exec('PRAGMA foreign_keys = OFF');
    try {
        $db->beginTransaction();

        if ($mode === 'replace') {
            foreach (array_reverse($known) as $t) {
                if (isset($hasData[$t])) $db->exec('DELETE FROM "' . $t . '"');
            }
            foreach (array_diff($known, array_keys($hasData)) as $t) {
                $warnings[] = "Tabel '$t' tidak ada di file — data lama dipertahankan.";
            }
        }

        foreach ($hasData as $t => $rows) {
            $cols    = backup_table_columns($db, $t);
            $colFlip = array_flip($cols);
            $verb    = ($mode === 'merge') ? 'INSERT OR REPLACE INTO' : 'INSERT INTO';
            $n = 0;

            foreach ($rows as $row) {
                if (!is_array($row)) { $warnings[] = "$t: baris tidak valid — dilewati."; continue; }

                $extra = array_diff(array_keys($row), $cols);
                if ($extra) $warnings[] = "$t: kolom tidak dikenal dibuang (" . implode(', ', $extra) . ").";

                $useCols = array_values(array_intersect($cols, array_keys($row)));
                if (!$useCols) { $warnings[] = "$t: baris tanpa kolom cocok — dilewati."; continue; }

                $placeholders = implode(',', array_fill(0, count($useCols), '?'));
                $sql = $verb . ' "' . $t . '" ("' . implode('","', $useCols) . '") VALUES (' . $placeholders . ')';
                $st = $db->prepare($sql);
                $st->execute(array_map(fn($c) => $row[$c], $useCols));
                $n++;
            }
            $counts[$t] = $n;
        }

        $db->commit();
    } catch (Throwable $ex) {
        if ($db->inTransaction()) $db->rollBack();
        $db->exec('PRAGMA foreign_keys = ON');
        throw $ex;
    }
    $db->exec('PRAGMA foreign_keys = ON');
    return $counts;
}

function backup_restore_files(array $files): int {
    $n = 0;
    $base = realpath(BASE_DIR);
    foreach ($files as $rel => $b64) {
        $rel = str_replace('\\', '/', $rel);
        if (strpos($rel, 'uploads/') !== 0 || strpos($rel, '..') !== false) continue;
        $bin = base64_decode($b64, true);
        if ($bin === false) continue;
        $dest = BASE_DIR . '/' . $rel;
        $dir = dirname($dest);
        if (!is_dir($dir)) @mkdir($dir, 0777, true);
        $realDir = realpath($dir);
        if ($base !== false && ($realDir === false || strpos($realDir . DIRECTORY_SEPARATOR, $base . DIRECTORY_SEPARATOR) !== 0)) continue;
        if (@file_put_contents($dest, $bin) !== false) $n++;
    }
    return $n;
}
