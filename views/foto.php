<?php
require_once __DIR__ . '/layout.php';

$anakId = (int)($_GET['anak_id'] ?? ($_POST['anak_id'] ?? 0));
$anakList = $db->query('SELECT id, nama_lengkap, kelompok FROM anak ORDER BY nama_lengkap')->fetchAll();
$cps = $db->query('SELECT * FROM cp ORDER BY urutan, id')->fetchAll();

function isSplitCpName(string $nama): bool {
    $n = strtolower($nama);
    return (strpos($n, 'jati diri') !== false)
        || (strpos($n, 'literasi') !== false)
        || (strpos($n, 'al islam') !== false)
        || (strpos($n, 'kemuhammadiyahan') !== false);
}

// ---- Simpan / hapus foto ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $anakId) {
    $act = $_POST['act'] ?? 'simpan';

    if ($act === 'simpan') {
        $isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) || (($_POST['format'] ?? '') === 'json');
        foreach ($cps as $cp) {
            $max = isSplitCpName($cp['nama']) ? 4 : 2; // split: 2 foto per lembar × 2 lembar
            for ($urut = 1; $urut <= $max; $urut++) {
                $field = 'foto_' . (int)$cp['id'] . '_' . $urut;
                $ketField = $field . '_ket';

                if (!empty($_FILES[$field]['name']) && $_FILES[$field]['error'] === UPLOAD_ERR_OK) {
                    $new = uploadFile($field, 'kegiatan');
                    if ($new) {
                        $st = $db->prepare('SELECT file FROM foto WHERE anak_id=? AND cp_id=? AND urutan=?');
                        $st->execute([$anakId, (int)$cp['id'], $urut]);
                        $old = $st->fetch();
                        if ($old) deleteUpload($old['file']);
                        $db->prepare('INSERT INTO foto (anak_id, cp_id, urutan, file) VALUES (?,?,?,?)
                                      ON CONFLICT(anak_id, cp_id, urutan) DO UPDATE SET file=excluded.file')
                           ->execute([$anakId, (int)$cp['id'], $urut, $new]);
                    }
                }
                $ket = trim($_POST[$ketField] ?? '');
                if ($ket !== '') {
                    $db->prepare('UPDATE foto SET keterangan = ? WHERE anak_id=? AND cp_id=? AND urutan=?')
                       ->execute([$ket, $anakId, (int)$cp['id'], $urut]);
                }
            }
        }
        if ($isAjax) {
            header('Content-Type: application/json');
            echo json_encode(['ok' => true, 'msg' => 'Foto kegiatan disimpan.']);
            exit;
        }
        flash('Foto kegiatan disimpan.');
        redirect('index.php?route=foto&anak_id=' . $anakId);
    }

    if ($act === 'del_foto') {
        $cpId = (int)$_POST['cp_id'];
        $urut = (int)$_POST['urut'];
        $st = $db->prepare('SELECT file FROM foto WHERE anak_id=? AND cp_id=? AND urutan=?');
        $st->execute([$anakId, $cpId, $urut]);
        if ($r = $st->fetch()) deleteUpload($r['file']);
        $db->prepare('DELETE FROM foto WHERE anak_id=? AND cp_id=? AND urutan=?')
           ->execute([$anakId, $cpId, $urut]);
        flash('Foto dihapus.');
        redirect('index.php?route=foto&anak_id=' . $anakId);
    }
}

page_header('Upload Foto Kegiatan', 'foto');
?>
<form method="get" class="panel toolbar-form">
    <input type="hidden" name="route" value="foto">
    <label>Anak:</label>
    <select name="anak_id" onchange="this.form.submit()" required>
        <option value="">— pilih anak —</option>
        <?php foreach ($anakList as $a): ?>
        <option value="<?= (int)$a['id'] ?>" <?= $anakId === (int)$a['id'] ? 'selected' : '' ?>>
            <?= e($a['nama_lengkap']) ?><?= $a['kelompok'] ? ' (' . e($a['kelompok']) . ')' : '' ?>
        </option>
        <?php endforeach; ?>
    </select>
    <span class="muted">2 foto kegiatan per lembar CP. Jati Diri & Literasi (2 lembar) = 4 foto.</span>
</form>

<?php if (!$anakList): ?>
    <div class="panel muted">Belum ada data anak. Tambahkan lewat menu <a href="index.php?route=anak">Data Anak</a>.</div>
<?php elseif ($anakId): ?>
<?php
$fotos = [];
$st = $db->prepare('SELECT * FROM foto WHERE anak_id = ?');
$st->execute([$anakId]);
foreach ($st->fetchAll() as $r) $fotos[(int)$r['cp_id']][(int)$r['urutan']] = $r;
?>
<form method="post" enctype="multipart/form-data">
    <input type="hidden" name="anak_id" value="<?= $anakId ?>">
    <input type="hidden" name="act" value="simpan">

    <?php foreach ($cps as $cp): ?>
    <?php
        $split = isSplitCpName($cp['nama']);
        $max = $split ? 4 : 2;
        $perSheet = $split
            ? [[1, 'Lembar 1 — Foto 1 & 2'], [3, 'Lembar 2 — Foto 3 & 4']]
            : [[1, 'Foto 1 & 2']];
    ?>
    <div class="panel">
        <div class="cp-section-head">
            <h3><?= e($cp['nama']) ?></h3>
            <?php if ($split): ?>
                <span class="badge badge-green">2 Lembar × 2 Foto = 4 Foto</span>
            <?php else: ?>
                <span class="badge">1 Lembar × 2 Foto</span>
            <?php endif; ?>
        </div>

        <?php foreach ($perSheet as [$start, $label]): ?>
        <div class="sub-block">
            <div class="sub-head"><strong><?= e($label) ?></strong></div>
            <div class="foto-grid">
                <?php for ($i = $start; $i < $start + 2; $i++): $f = $fotos[(int)$cp['id']][$i] ?? null; ?>
                <div class="foto-slot">
                    <?php if (!empty($f['file'])): ?>
                        <img src="<?= e($f['file']) ?>" alt="Foto <?= $i ?>">
                        <button type="button" class="btn-link"
                            onclick="submitDel(<?= (int)$cp['id'] ?>, <?= $i ?>)">hapus foto</button>
                    <?php else: ?>
                        <div class="foto-empty">Foto <?= $i ?></div>
                    <?php endif; ?>
                    <input type="file" name="foto_<?= (int)$cp['id'] ?>_<?= $i ?>" accept="image/*">
                    <label>Keterangan foto <?= $i ?></label>
                    <input type="text" name="foto_<?= (int)$cp['id'] ?>_<?= $i ?>_ket"
                           value="<?= e($f['keterangan'] ?? '') ?>" placeholder="mis. sedang bermain balok">
                </div>
                <?php endfor; ?>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endforeach; ?>

    <div class="form-actions" style="margin-bottom:24px;">
        <button type="button" class="btn btn-primary" id="btnSimpanFoto">Simpan Semua Foto</button>
        <span id="fotoStatus" class="muted" style="font-size:13px; margin-left:8px;"></span>
        <a class="btn" href="index.php?route=penilaian&anak_id=<?= $anakId ?>">← Kembali ke Penilaian</a>
        <a class="btn" href="index.php?route=cetak">Lanjut: Cetak →</a>
    </div>
</form>

<form method="post" id="delFotoForm" style="display:none">
    <input type="hidden" name="anak_id" value="<?= $anakId ?>">
    <input type="hidden" name="act" value="del_foto">
    <input type="hidden" name="cp_id" id="delCpId">
    <input type="hidden" name="urut" id="delUrut">
</form>
<script>
function submitDel(cp, u) {
    if (!confirm('Hapus foto ini?')) return;
    document.getElementById('delCpId').value = cp;
    document.getElementById('delUrut').value = u;
    document.getElementById('delFotoForm').submit();
}

// Simpan langsung selesai — tanpa loading, upload diproses di belakang
document.getElementById('btnSimpanFoto').addEventListener('click', function () {
    var form = this.closest('form');
    var status = document.getElementById('fotoStatus');
    var fd = new FormData(form);
    fd.append('format', 'json');
    status.textContent = 'Tersimpan.';
    status.style.color = '#187a46';
    fetch(form.action || window.location.href, {
        method: 'POST',
        body: fd,
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    }).then(function (r) { return r.json(); }).then(function () {
        // Selesai di belakang — muat ulang ringan agar thumbnail tampil
        window.location.reload();
    }).catch(function () {
        window.location.reload();
    });
});
</script>
<?php else: ?>
    <div class="panel muted">Pilih anak terlebih dahulu.</div>
<?php endif; ?>
<?php page_footer(); ?>
