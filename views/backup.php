<?php
require_once __DIR__ . '/layout.php';
require_once BASE_DIR . '/src/backup.php';

// ---- Endpoint download (export) ----
if (($_GET['action'] ?? '') === 'export') {
    $withFiles = ($_GET['files'] ?? '') === '1';
    if (($_GET['format'] ?? 'json') === 'sql') {
        backup_stream_sql($db, $_SESSION['user']);
    }
    backup_stream_json($db, $withFiles, $_SESSION['user']);
}

// ---- Proses import (AJAX / fallback POST) ----
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) || (($_POST['format'] ?? '') === 'json');
    $mode = (($_POST['mode'] ?? '') === 'merge') ? 'merge' : 'replace';
    $resp = null;

    $file = $_FILES['file'] ?? null;
    if (!$file || $file['error'] !== UPLOAD_ERR_OK) {
        $code = $file['error'] ?? '-';
        $resp = ['ok' => false, 'msg' => "Upload file gagal (kode $code). Perbesar limit upload bila file foto besar."];
    } else {
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $raw = file_get_contents($file['tmp_name']);

        if ($ext === 'json') {
            $parsed = backup_parse_json($raw === false ? '' : $raw);
        } elseif ($ext === 'sql') {
            $parsed = backup_parse_sql($raw === false ? '' : $raw);
        } else {
            $resp = ['ok' => false, 'msg' => 'Format file tidak didukung. Gunakan file backup .json atau .sql.'];
        }

        if ($resp === null) {
            if (empty($parsed['tables']) && empty($parsed['files'])) {
                $resp = ['ok' => false, 'msg' => 'File tidak valid.', 'warnings' => $parsed['errors']];
            } else {
                $warnings = $parsed['errors'];
                try {
                    $counts     = backup_apply($db, $parsed['tables'], $mode, $warnings);
                    $fileCount  = backup_restore_files($parsed['files'] ?? []);
                    $total      = array_sum($counts);
                    $msg = "Restore selesai: $total baris data dipulihkan";
                    if ($fileCount) $msg .= ", $fileCount file foto/logo";
                    $msg .= ' (' . ($mode === 'merge' ? 'gabung' : 'timpa') . ').';
                    $resp = ['ok' => true, 'msg' => $msg, 'counts' => $counts,
                             'files' => $fileCount, 'warnings' => array_values(array_unique($warnings))];
                } catch (Throwable $ex) {
                    $resp = ['ok' => false, 'msg' => 'Restore dibatalkan, data tidak berubah. ' . $ex->getMessage()];
                }
            }
        }
    }

    if ($isAjax) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($resp, JSON_UNESCAPED_UNICODE);
        exit;
    }
    flash($resp['msg'], $resp['ok'] ? 'success' : 'error');
    redirect('index.php?route=backup');
}

$pageTitle = 'Backup & Restore Data';
page_header($pageTitle, 'backup');
?>
<div class="panel">
    <h2>Download Data Lokal (Export)</h2>
    <p class="muted">Unduh seluruh data aplikasi — profil sekolah, data anak, capaian, penilaian, foto kegiatan,
        refleksi &amp; pertumbuhan — ke komputer Anda. Akun login (<code>users</code>) tidak disertakan.</p>
    <form method="get" action="index.php" class="toolbar-form" style="margin-top:12px">
        <input type="hidden" name="route" value="backup">
        <input type="hidden" name="action" value="export">
        <label style="display:inline-flex;align-items:center;gap:6px">
            <input type="checkbox" name="files" value="1" checked style="min-width:0;width:auto">
            Sertakan file foto/logo (base64)
        </label>
        <button class="btn btn-primary" name="format" value="json">Download JSON</button>
        <button class="btn" name="format" value="sql">Download SQL</button>
    </form>
    <p class="muted" style="margin-top:10px">
        <strong>JSON</strong> = lengkap (data + file). <strong>SQL</strong> = dump database murni (tanpa file).
        Simpan file backup di tempat aman.
    </p>
</div>

<div class="panel">
    <h2>Unggah / Muat Data (Import)</h2>
    <p class="muted">Pilih file backup <strong>.json</strong> atau <strong>.sql</strong> hasil unduhan sebelumnya.
        Data akan divalidasi lalu dipulihkan ke database — halaman tidak perlu dimuat ulang.</p>

    <form id="form-restore" method="post" action="index.php?route=backup" enctype="multipart/form-data"
          class="toolbar-form" style="margin-top:12px">
        <input type="hidden" name="format" value="json">
        <input type="file" name="file" accept=".json,.sql" required>
        <select name="mode" title="Mode pemulihan">
            <option value="replace">Timpa — ganti data dengan isi file</option>
            <option value="merge">Gabung — perbarui / insert per baris</option>
        </select>
        <button type="submit" class="btn btn-primary" id="btn-restore">Unggah / Muat Data</button>
    </form>

    <div id="restore-status" style="margin-top:14px"></div>

    <p class="muted" style="margin-top:12px">
        <strong>Timpa</strong>: data lama pada tabel yang ada di file diganti total oleh isi file (disarankan untuk
        pemulihan penuh). <strong>Gabung</strong>: baris disisipkan/diperbarui tanpa menghapus data lain.
    </p>
</div>

<script>
(function () {
    var form = document.getElementById('form-restore');
    var status = document.getElementById('restore-status');
    var btn = document.getElementById('btn-restore');

    function esc(s) {
        return String(s).replace(/[&<>"']/g, function (c) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
        });
    }

    form.addEventListener('submit', function (e) {
        e.preventDefault();

        var fd = new FormData(form);
        if (!fd.get('file')) {
            status.innerHTML = '<div class="alert alert-error">Pilih file backup terlebih dahulu.</div>';
            return;
        }

        btn.disabled = true;
        var label = btn.textContent;
        btn.textContent = 'Memproses…';
        status.innerHTML = '<div class="alert alert-success">Mengunggah &amp; memulihkan data, mohon tunggu…</div>';

        fetch(form.action, {
            method: 'POST',
            body: fd,
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(function (r) { return r.json(); })
        .then(function (d) {
            var html = '<div class="alert ' + (d.ok ? 'alert-success' : 'alert-error') + '">' + esc(d.msg || '') + '</div>';

            if (d.ok && d.counts) {
                var items = Object.keys(d.counts).map(function (t) {
                    return '<li>' + esc(t) + ': ' + d.counts[t] + ' baris</li>';
                }).join('');
                html += '<div class="muted"><strong>Rincian:</strong><ul style="margin:6px 0 0 18px">' + items + '</ul></div>';
                html += '<p class="muted">Selesai. <a href="index.php?route=dashboard">Muat ulang halaman</a> untuk melihat data terbaru.</p>';
            }

            if (d.warnings && d.warnings.length) {
                var warns = d.warnings.slice(0, 30).map(function (w) { return '<li>' + esc(w) + '</li>'; }).join('');
                html += '<div class="alert alert-error" style="display:block"><strong>' + d.warnings.length +
                        ' peringatan:</strong><ul style="margin:6px 0 0 18px">' + warns + '</ul></div>';
            }

            status.innerHTML = html;
        })
        .catch(function () {
            status.innerHTML = '<div class="alert alert-error">Gagal menghubungi server. Periksa koneksi lalu coba lagi.</div>';
        })
        .then(function () {
            btn.disabled = false;
            btn.textContent = label;
        });
    });
})();
</script>
<?php page_footer(); ?>
