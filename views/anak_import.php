<?php
require_once __DIR__ . '/layout.php';
require_once BASE_DIR . '/src/xlsx.php';

// ---- Download template CSV ----
if (($_GET['template'] ?? '') === '1') {
    $fields = [
        'nama_lengkap','nama_panggilan','nisn','tempat_lahir','tanggal_lahir','jenis_kelamin',
        'agama','anak_ke','jumlah_saudara','kelompok','nama_ayah','pekerjaan_ayah',
        'nama_ibu','pekerjaan_ibu','nama_wali','alamat_jalan','alamat_rt_rw','alamat_desa',
        'alamat_kecamatan','alamat_kabupaten','alamat_provinsi',
    ];
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="template_import_anak.csv"');
    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF"); // BOM agar Excel membaca UTF-8
    fputcsv($out, $fields, ';');
    // contoh satu baris
    fputcsv($out, [
        'Bima Arya','Bima','1234567890','Yogyakarta','2021-03-15','Laki-laki',
        'Islam','1','2','Bunga','Ahmad','Wiraswasta','Siti','Ibu Rumah Tangga','',
        'Jl. Merdeka 1','01/02','Mulyorejo','Gondokusuman','Yogyakarta','DI Yogyakarta',
    ], ';');
    fclose($out);
    exit;
}

$pageTitle = 'Import Data Anak';
page_header($pageTitle, 'anak');

// ---- Proses import ----
$result = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_FILES['file']['name'])) {
    $file = $_FILES['file'];
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $result = ['ok' => 0, 'errors' => ['Upload gagal (kode ' . $file['error'] . ').']];
    } else {
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $rows = null;

        if ($ext === 'xlsx') {
            $rows = xlsx_read_rows($file['tmp_name']);
            if ($rows === null) {
                $result = ['ok' => 0, 'errors' => ['Gagal membaca file XLSX. Pastikan file Excel (.xlsx) tidak rusak, atau gunakan template CSV.']];
            }
        } elseif ($ext === 'csv' || $ext === 'txt') {
            $content = file_get_contents($file['tmp_name']);
            $content = preg_replace('/^\xEF\xBB\xBF/', '', $content); // buang BOM
            $content = str_replace("\r\n", "\n", $content);
            $lines = array_filter(explode("\n", $content), fn($l) => trim($l) !== '');
            $rows = [];
            foreach ($lines as $line) {
                $rows[] = str_getcsv($line, ';');
            }
            if (count($rows) > 1) {
                // coba koma bila hasil pemisahan ; hanya 1 kolom
                $check = str_getcsv($lines[0], ',');
                if (count($check) > count($rows[0])) {
                    $rows = [];
                    foreach ($lines as $line) $rows[] = str_getcsv($line, ',');
                }
            }
        } else {
            $result = ['ok' => 0, 'errors' => ['Format file tidak didukung. Gunakan .xlsx atau .csv.']];
        }

        if ($rows !== null && $result === null) {
            $fields = [
                'nama_lengkap','nama_panggilan','nisn','tempat_lahir','tanggal_lahir','jenis_kelamin',
                'agama','anak_ke','jumlah_saudara','kelompok','nama_ayah','pekerjaan_ayah',
                'nama_ibu','pekerjaan_ibu','nama_wali','alamat_jalan','alamat_rt_rw','alamat_desa',
                'alamat_kecamatan','alamat_kabupaten','alamat_provinsi',
            ];
            // alias header → field
            $alias = [];
            foreach ($fields as $f) {
                $alias[str_replace(['_', ' '], '', strtolower($f))] = $f;
            }
            $labels = [
                'namalengkap'=>'nama_lengkap','namapanggilan'=>'nama_panggilan','nisn/nis'=>'nisn',
                'tempattanggallahir'=>'tanggal_lahir','tempatlahir'=>'tempat_lahir',
                'jeniskelamin'=>'jenis_kelamin','anakke'=>'anak_ke','jumlahsaudara'=>'jumlah_saudara',
                'kelompok/rombel'=>'kelompok','namaayah'=>'nama_ayah','pekerjaanayah'=>'pekerjaan_ayah',
                'namaibu'=>'nama_ibu','pekerjaanibu'=>'pekerjaan_ibu','namawali'=>'nama_wali',
                'jalandusun'=>'alamat_jalan','rtrw'=>'alamat_rt_rw','desakelurahan'=>'alamat_desa',
                'kabupatenkota'=>'alamat_kabupaten',
            ];
            foreach ($labels as $k => $v) $alias[$k] = $v;

            $norm = fn($s) => str_replace(['_', ' ', '/', '(', ')', '-'], '', strtolower(trim($s)));

            // Baris 1 = header bila cocok dengan minimal 2 field
            $map = null;
            $start = 0;
            if (isset($rows[0])) {
                $m = [];
                foreach ($rows[0] as $i => $h) {
                    $key = $norm($h);
                    if (isset($alias[$key])) $m[$i] = $alias[$key];
                }
                if (count($m) >= 2) { $map = $m; $start = 1; }
            }
            if ($map === null) {
                // tanpa header → urutan kolom sesuai template
                $map = [];
                foreach ($fields as $i => $f) $map[$i] = $f;
            }

            $ok = 0;
            $errors = [];
            $ins = $db->prepare(
                'INSERT INTO anak (user_id, nama_lengkap, nama_panggilan, nisn, tempat_lahir, tanggal_lahir,
                   jenis_kelamin, agama, anak_ke, jumlah_saudara, kelompok,
                   nama_ayah, pekerjaan_ayah, nama_ibu, pekerjaan_ibu, nama_wali,
                   alamat_jalan, alamat_rt_rw, alamat_desa, alamat_kecamatan, alamat_kabupaten, alamat_provinsi)
                 VALUES (' . implode(',', array_fill(0, 22, '?')) . ')'
            );

            foreach (array_slice($rows, $start) as $idx => $line) {
                $data = [];
                foreach ($map as $col => $field) {
                    $val = trim((string)($line[$col] ?? ''));
                    if ($field === 'tanggal_lahir') $val = xlsx_serial_to_date($val);
                    if ($field === 'jenis_kelamin' && $val !== '') {
                        $lv = strtolower($val);
                        if (in_array($lv, ['l', 'laki-laki', 'lk', 'male'], true)) $val = 'Laki-laki';
                        elseif (in_array($lv, ['p', 'perempuan', 'pr', 'female'], true)) $val = 'Perempuan';
                    }
                    $data[$field] = $val;
                }
                if (($data['nama_lengkap'] ?? '') === '') {
                    $errors[] = 'Baris ' . ($idx + $start + 1) . ': nama lengkap kosong — dilewati.';
                    continue;
                }
                $params = array_merge([currentUserId($db)], array_map(fn($f) => $data[$f] ?? '', $fields));
                $ins->execute($params);
                $ok++;
            }

            $result = ['ok' => $ok, 'errors' => $errors];
            if ($ok > 0) {
                logRevisi($db, 0, 'anak', '', 'tambah', "Import $ok data anak dari file " . basename($file['name']));
                flash("$ok data anak berhasil diimport.");
            }
        }
    }
}
?>
<div class="panel">
    <h2>Import Data Anak dari Excel</h2>
    <ol class="steps">
        <li><a href="index.php?route=anak_import&template=1">Download template CSV</a> (bisa dibuka Excel/Google Sheets, lalu diisi).</li>
        <li>Isi data — <strong>kolom wajib: nama_lengkap</strong>. Kolom lain opsional.</li>
        <li>Simpan sebagai <strong>.xlsx</strong> atau <strong>.csv</strong> (pemisah ; atau ,), lalu unggah di bawah.</li>
    </ol>

    <form method="post" enctype="multipart/form-data" class="toolbar-form" style="margin-top:14px">
        <label>Pilih file:</label>
        <input type="file" name="file" accept=".xlsx,.csv,.txt" required>
        <button class="btn btn-primary">Import</button>
        <a class="btn" href="index.php?route=anak">Kembali</a>
    </form>
    <p class="muted">Format .xlsx (Excel 2007+) dan .csv didukung. File .xls lama — simpan ulang sebagai .xlsx.</p>
</div>

<?php if ($result !== null): ?>
<div class="panel">
    <h2>Hasil Import</h2>
    <div class="alert alert-success">Berhasil: <?= (int)$result['ok'] ?> data anak.</div>
    <?php if ($result['errors']): ?>
        <div class="alert alert-error">
            <strong><?= count($result['errors']) ?> baris bermasalah:</strong>
            <ul style="margin:6px 0 0 18px">
                <?php foreach (array_slice($result['errors'], 0, 30) as $err): ?>
                    <li><?= e($err) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>
    <a class="btn btn-primary" href="index.php?route=anak">Lihat Data Anak</a>
</div>
<?php endif; ?>
<?php page_footer(); ?>
