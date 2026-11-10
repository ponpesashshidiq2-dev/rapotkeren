<?php
require_once __DIR__ . '/layout.php';

$anakId = (int)($_GET['anak_id'] ?? 0);
$cpId = (int)($_GET['cp_id'] ?? 0);

$anakList = $db->query('SELECT id, nama_lengkap, nama_panggilan, kelompok FROM anak ORDER BY nama_lengkap')->fetchAll();
$cpList = $db->query('SELECT id, nama FROM cp ORDER BY urutan, id')->fetchAll();

if (!$cpId && $cpList) $cpId = (int)$cpList[0]['id'];

// ---- Simpan ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $anakId) {
    $act = $_POST['act'] ?? 'simpan';

    if ($act === 'simpan') {
        // 1. Sinkronkan centang capaian
        $checked = array_map('intval', $_POST['capaian'] ?? []);
        $db->beginTransaction();
        $db->prepare('DELETE FROM penilaian WHERE anak_id = ?')->execute([$anakId]);
        $ins = $db->prepare('INSERT OR IGNORE INTO penilaian (anak_id, capaian_id) VALUES (?, ?)');
        foreach ($checked as $cid) {
            if ($cid > 0) $ins->execute([$anakId, $cid]);
        }

        // 2. Deskripsi manual (override) per sub_cp
        $dbs = $_POST['deskripsi'] ?? [];
        $del = $db->prepare('DELETE FROM deskripsi WHERE anak_id = ?');
        $del->execute([$anakId]);
        $insD = $db->prepare('INSERT INTO deskripsi (anak_id, sub_cp_id, teks) VALUES (?, ?, ?)');
        foreach ($dbs as $subId => $teks) {
            $teks = trim($teks);
            if ($teks !== '') {
                $insD->execute([$anakId, (int)$subId, $teks]);
            }
        }

        // 3. Foto kegiatan (2 per CP)
        $cpF = (int)($_POST['cp_id_foto'] ?? 0) ?: $cpId;
        $urutanMap = ['foto1' => 1, 'foto2' => 2];
        foreach ($urutanMap as $field => $urut) {
            if (!empty($_FILES[$field]['name'])) {
                $new = uploadFile($field, 'kegiatan');
                if ($new) {
                    $st = $db->prepare('SELECT file FROM foto WHERE anak_id=? AND cp_id=? AND urutan=?');
                    $st->execute([$anakId, $cpF, $urut]);
                    $old = $st->fetch();
                    if ($old) deleteUpload($old['file']);
                    $db->prepare('INSERT INTO foto (anak_id, cp_id, urutan, file) VALUES (?,?,?,?)
                                  ON CONFLICT(anak_id, cp_id, urutan) DO UPDATE SET file=excluded.file')
                       ->execute([$anakId, $cpF, $urut, $new]);
                }
            }
        }
        // Keterangan foto
        foreach ($urutanMap as $field => $urut) {
            $ket = trim($_POST[$field . '_ket'] ?? '');
            if ($ket !== '') {
                $db->prepare('UPDATE foto SET keterangan = ? WHERE anak_id=? AND cp_id=? AND urutan=?')
                   ->execute([$ket, $anakId, $cpF, $urut]);
            }
        }
        $db->commit();
        flash('Penilaian disimpan. Deskripsi diperbarui otomatis.');
        redirect('index.php?route=penilaian&anak_id=' . $anakId . '&cp_id=' . $cpF);
    }

    if ($act === 'del_foto') {
        $urut = (int)$_POST['urut'];
        $st = $db->prepare('SELECT file FROM foto WHERE anak_id=? AND cp_id=? AND urutan=?');
        $st->execute([$anakId, (int)$_POST['cp_id_foto'], $urut]);
        if ($r = $st->fetch()) deleteUpload($r['file']);
        $db->prepare('DELETE FROM foto WHERE anak_id=? AND cp_id=? AND urutan=?')
           ->execute([$anakId, (int)$_POST['cp_id_foto'], $urut]);
        flash('Foto dihapus.');
        redirect('index.php?route=penilaian&anak_id=' . $anakId . '&cp_id=' . (int)$_POST['cp_id_foto']);
    }
}

page_header('Penilaian Anak', 'penilaian');
?>
<form method="get" class="panel toolbar-form">
    <input type="hidden" name="route" value="penilaian">
    <label>Anak:</label>
    <select name="anak_id" onchange="this.form.submit()" required>
        <option value="">— pilih anak —</option>
        <?php foreach ($anakList as $a): ?>
        <option value="<?= (int)$a['id'] ?>" <?= $anakId === (int)$a['id'] ? 'selected' : '' ?>>
            <?= e($a['nama_lengkap']) ?><?= $a['kelompok'] ? ' (' . e($a['kelompok']) . ')' : '' ?>
        </option>
        <?php endforeach; ?>
    </select>
    <label>CP:</label>
    <select name="cp_id" onchange="this.form.submit()" <?= $anakId ? '' : 'disabled' ?>>
        <?php foreach ($cpList as $c): ?>
        <option value="<?= (int)$c['id'] ?>" <?= $cpId === (int)$c['id'] ? 'selected' : '' ?>>
            <?= e($c['nama']) ?>
        </option>
        <?php endforeach; ?>
    </select>
</form>

<?php if (!$anakList): ?>
    <div class="panel muted">Belum ada data anak. Tambahkan lewat menu <a href="index.php?route=anak">Data Anak</a>.</div>
<?php elseif ($anakId && $cpId): ?>
<?php
$st = $db->prepare('SELECT * FROM anak WHERE id = ?');
$st->execute([$anakId]);
$anak = $st->fetch();
$st = $db->prepare('SELECT * FROM cp WHERE id = ?');
$st->execute([$cpId]);
$cp = $st->fetch();

// Sub-bidang + capaian + status centang
$subs = $db->prepare('SELECT * FROM sub_cp WHERE cp_id = ? ORDER BY urutan, id');
$subs->execute([$cpId]);
$subs = $subs->fetchAll();

$checked = [];
$st = $db->prepare('SELECT capaian_id FROM penilaian WHERE anak_id = ?');
$st->execute([$anakId]);
foreach ($st->fetchAll() as $r) $checked[(int)$r['capaian_id']] = true;

$manual = [];
$st = $db->prepare('SELECT sub_cp_id, teks FROM deskripsi WHERE anak_id = ?');
$st->execute([$anakId]);
foreach ($st->fetchAll() as $r) $manual[(int)$r['sub_cp_id']] = $r['teks'];

$fotos = [];
$st = $db->prepare('SELECT * FROM foto WHERE anak_id = ? AND cp_id = ?');
$st->execute([$anakId, $cpId]);
foreach ($st->fetchAll() as $r) $fotos[(int)$r['urutan']] = $r;

// Bangun deskripsi otomatis dari capaian tercentang
function autoDeskripsi($subId, $checked, $db) {
    $st = $db->prepare('SELECT id, teks FROM capaian WHERE sub_cp_id = ? ORDER BY urutan, id');
    $st->execute([$subId]);
    $picked = [];
    foreach ($st->fetchAll() as $r) {
        if (!empty($checked[(int)$r['id']])) $picked[] = $r['teks'];
    }
    if (!$picked) return '';
    $n = count($picked);
    if ($n === 1) return $picked[0];
    $last = array_pop($picked);
    return implode(', ', $picked) . ', dan ' . $last;
}

$panggilan = $anak['nama_panggilan'] ?: $anak['nama_lengkap'];
?>
<div class="panel">
    <div class="penilaian-head">
        <div>
            <strong><?= e($anak['nama_lengkap']) ?></strong><br>
            <span class="muted">Kelompok: <?= e($anak['kelompok'] ?: '-') ?> · Usia: <?= e($anak['tanggal_lahir'] ?: '-') ?></span>
        </div>
        <div class="muted">
            CP: <strong><?= e($cp['nama']) ?></strong>
            <?php $isSplit = (strpos(strtolower($cp['nama']), 'jati diri') !== false || strpos(strtolower($cp['nama']), 'literasi') !== false); ?>
            <?php if ($isSplit): ?>
                <span style="display:inline-block; margin-left:8px; padding:3px 9px; border-radius:6px; background:#dcfce7; color:#15803d; font-size:12px; font-weight:700;">Dicetak 2 Lembar A4</span>
            <?php else: ?>
                <span style="display:inline-block; margin-left:8px; padding:3px 9px; border-radius:6px; background:var(--hijau-soft); color:var(--hijau-botol); font-size:12px; font-weight:600;">Dicetak 1 Lembar A4</span>
            <?php endif; ?>
        </div>
    </div>

    <form method="post" enctype="multipart/form-data">
        <input type="hidden" name="act" value="simpan">
        <input type="hidden" name="cp_id_foto" value="<?= (int)$cpId ?>">

        <?php foreach ($subs as $sub): ?>
        <div class="sub-block">
            <div class="sub-head"><strong><?= e($sub['nama']) ?></strong></div>
            <?php
            $caps = $db->prepare('SELECT * FROM capaian WHERE sub_cp_id = ? ORDER BY urutan, id');
            $caps->execute([$sub['id']]);
            $caps = $caps->fetchAll();
            ?>
            <div class="check-grid">
                <?php foreach ($caps as $c): ?>
                <label class="check-item">
                    <input type="checkbox" name="capaian[]" value="<?= (int)$c['id'] ?>"
                        <?= !empty($checked[(int)$c['id']]) ? 'checked' : '' ?>>
                    <?= e($c['teks']) ?>
                </label>
                <?php endforeach; ?>
                <?php if (!$caps): ?><span class="muted">Belum ada capaian detail — tambahkan di menu Data Capaian.</span><?php endif; ?>
            </div>

            <?php $auto = autoDeskripsi((int)$sub['id'], $checked, $db); ?>
            <div class="deskripsi-box">
                <label>Deskripsi (otomatis dari centangan — bisa diedit):</label>
                <textarea name="deskripsi[<?= (int)$sub['id'] ?>]" rows="3"
                    placeholder="Otomatis terisi saat centang capaian..."><?= e($manual[(int)$sub['id']] ?? '') ?></textarea>
                <p class="muted auto-hint">Saran otomatis: <em><?= $auto ? e('Ananda ' . $panggilan . ' ' . $auto) : '(belum ada yang dicentang)' ?></em>
                    <button type="button" class="btn-link" onclick="isiOtomatis(this)">pakai</button>
                </p>
                <script>
                function isiOtomatis(btn) {
                    var box = btn.closest('.deskripsi-box');
                    var saran = box.querySelector('.auto-hint em').textContent;
                    if (saran.indexOf('(') !== 0) box.querySelector('textarea').value = saran;
                }
                </script>
            </div>
        </div>
        <?php endforeach; ?>

        <!-- Foto kegiatan -->
        <div class="sub-block">
            <div class="sub-head">
                <strong>Dokumentasi Kegiatan Anak (maks. 2 foto)</strong>
                <?php if ($isSplit): ?>
                    <span class="muted" style="font-size:12px; font-weight:normal;">Foto 1 dicetak di Lembar 1 &bull; Foto 2 dicetak di Lembar 2</span>
                <?php else: ?>
                    <span class="muted" style="font-size:12px; font-weight:normal;">Foto 1 & 2 dicetak berdampingan di 1 lembar CP</span>
                <?php endif; ?>
            </div>
            <div class="foto-grid">
                <?php for ($i = 1; $i <= 2; $i++): $f = $fotos[$i] ?? null; ?>
                <div class="foto-slot">
                    <?php if (!empty($f['file'])): ?>
                        <img src="<?= e($f['file']) ?>" alt="Foto <?= $i ?>">
                        <button type="button" class="btn-link" onclick="if(confirm('Hapus foto ini?')) submitDel(<?= $i ?>)">hapus foto</button>
                    <?php else: ?>
                        <div class="foto-empty">Foto <?= $i ?></div>
                    <?php endif; ?>
                    <input type="file" name="foto<?= $i ?>" accept="image/*">
                    <label>Keterangan foto <?= $i ?></label>
                    <input type="text" name="foto<?= $i ?>_ket" value="<?= e($f['keterangan'] ?? '') ?>"
                           placeholder="mis. sedang bermain balok">
                </div>
                <?php endfor; ?>
            </div>
        </div>

        <div class="form-actions">
            <button class="btn btn-primary">Simpan Penilaian</button>
        </div>
    </form>

    <form method="post" id="delFotoForm" style="display:none">
        <input type="hidden" name="act" value="del_foto">
        <input type="hidden" name="cp_id_foto" value="<?= (int)$cpId ?>">
        <input type="hidden" name="urut" id="delFotoUrut">
    </form>
    <script>
    function submitDel(u) {
        document.getElementById('delFotoUrut').value = u;
        document.getElementById('delFotoForm').submit();
    }
    </script>
</div>

<!-- Preview deskripsi gabungan -->
<div class="panel">
    <h2>Preview Deskripsi untuk Cetak — CP <?= e($cp['nama']) ?></h2>
    <?php foreach ($subs as $sub): ?>
        <?php
        $auto = autoDeskripsi((int)$sub['id'], $checked, $db);
        $text = trim($manual[(int)$sub['id']] ?? '');
        if ($text === '' && $auto !== '') $text = 'Ananda ' . $panggilan . ' ' . $auto . '.';
        ?>
        <div class="preview-item">
            <strong><?= e($sub['nama']) ?></strong>
            <p><?= $text ? e($text) : '<span class="muted">— belum ada capaian dicentang —</span>' ?></p>
        </div>
    <?php endforeach; ?>
</div>
<?php else: ?>
    <div class="panel muted">Pilih anak terlebih dahulu.</div>
<?php endif; ?>
<?php page_footer(); ?>
