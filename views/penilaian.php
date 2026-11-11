<?php
require_once __DIR__ . '/layout.php';
require_once BASE_DIR . '/src/deskripsi.php';

$anakId = (int)($_GET['anak_id'] ?? ($_POST['anak_id'] ?? 0));
$scope = anakScope($db);
$st = $db->prepare('SELECT id, nama_lengkap, nama_panggilan, kelompok FROM anak a
                    WHERE ' . $scope['where'] . ' ORDER BY nama_lengkap');
$st->execute($scope['params']);
$anakList = $st->fetchAll();

// ---- Simpan skor 1-4 ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $anakId) {
    $act = $_POST['act'] ?? 'simpan';

    if ($act === 'simpan') {
        if (!canEditAnak($db, $anakId)) {
            flash('Anda tidak punya akses mengubah penilaian anak ini.', 'error');
            redirect('index.php?route=penilaian&anak_id=' . $anakId);
        }

        $skor = $_POST['skor'] ?? []; // capaian_id => 1..4
        $dbs = $_POST['deskripsi'] ?? [];

        // Ambil data lama untuk riwayat
        $oldSkor = [];
        $stOld = $db->prepare('SELECT capaian_id, skor FROM penilaian WHERE anak_id = ?');
        $stOld->execute([$anakId]);
        foreach ($stOld->fetchAll() as $r) $oldSkor[(int)$r['capaian_id']] = (int)$r['skor'];
        $oldDesc = [];
        $stOld = $db->prepare('SELECT sub_cp_id, teks FROM deskripsi WHERE anak_id = ?');
        $stOld->execute([$anakId]);
        foreach ($stOld->fetchAll() as $r) $oldDesc[(int)$r['sub_cp_id']] = $r['teks'];

        $db->beginTransaction();
        $db->prepare('DELETE FROM penilaian WHERE anak_id = ?')->execute([$anakId]);
        $ins = $db->prepare('INSERT OR REPLACE INTO penilaian (anak_id, capaian_id, skor) VALUES (?, ?, ?)');
        foreach ($skor as $cid => $s) {
            $s = (int)$s;
            if ($s >= 1 && $s <= 4) $ins->execute([$anakId, (int)$cid, $s]);
        }

        // Deskripsi manual (override) per sub_cp
        $db->prepare('DELETE FROM deskripsi WHERE anak_id = ?')->execute([$anakId]);
        $insD = $db->prepare('INSERT INTO deskripsi (anak_id, sub_cp_id, teks) VALUES (?, ?, ?)');
        foreach ($dbs as $subId => $teks) {
            $teks = trim($teks);
            if ($teks !== '') $insD->execute([$anakId, (int)$subId, $teks]);
        }
        $db->commit();

        // Ringkasan revisi (maks beberapa baris)
        $teksCap = [];
        $stT = $db->query('SELECT id, teks FROM capaian');
        foreach ($stT->fetchAll() as $c) $teksCap[(int)$c['id']] = $c['teks'];
        $parts = [];
        foreach ($skor as $cid => $s) {
            $s = (int)$s;
            if ($s < 1 || $s > 4) continue;
            $old = $oldSkor[(int)$cid] ?? 0;
            if ($old !== $s) {
                $parts[] = authz_trunc($teksCap[(int)$cid] ?? ('capaian #' . $cid), 35) . ': ' .
                           ($old ?: '—') . ' → ' . $s;
            }
        }
        $newDesc = [];
        foreach ($dbs as $subId => $teks) if (trim($teks) !== '') $newDesc[(int)$subId] = trim($teks);
        $nDesc = 0;
        foreach ($newDesc as $sid => $t) if (($oldDesc[$sid] ?? '') !== $t) $nDesc++;
        foreach ($oldDesc as $sid => $t) if (!isset($newDesc[$sid]) && $t !== '') $nDesc++;
        $ringkasan = [];
        if ($parts) $ringkasan[] = 'skor: ' . implode(', ', array_slice($parts, 0, 6)) .
                                   (count($parts) > 6 ? ' +' . (count($parts) - 6) . ' lagi' : '');
        if ($nDesc) $ringkasan[] = "$nDesc deskripsi diubah";
        logRevisi($db, $anakId, 'penilaian', (string)$anakId, 'ubah', implode('; ', $ringkasan));

        flash('Penilaian disimpan. Deskripsi diperbarui otomatis.');
        redirect('index.php?route=penilaian&anak_id=' . $anakId);
    }
}

// Validasi akses SEBELUM output HTML (agar redirect berhasil)
$anak = null;
$canEdit = false;
$panggilan = '';
if ($anakId && $anakList) {
    $st = $db->prepare('SELECT * FROM anak WHERE id = ?');
    $st->execute([$anakId]);
    $anak = $st->fetch();
    if (!$anak) {
        flash('Anak tidak ditemukan.', 'error');
        redirect('index.php?route=penilaian');
    }
    $access = anakAccess($db, $anakId);
    if (!$access) {
        flash('Anda tidak punya akses ke data anak ini.', 'error');
        redirect('index.php?route=penilaian');
    }
    $canEdit = ($access === 'owner' || $access === 'editor');
    $panggilan = $anak['nama_panggilan'] ?: $anak['nama_lengkap'];
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
    <span class="muted">Semua Capaian Pembelajaran ditampilkan dalam satu halaman.</span>
</form>

<?php if (!$anakList): ?>
    <div class="panel muted">Belum ada data anak. Tambahkan lewat menu <a href="index.php?route=anak">Data Anak</a>.</div>
<?php elseif ($anakId): ?>
<?php
$skors = [];
$st = $db->prepare('SELECT capaian_id, skor FROM penilaian WHERE anak_id = ?');
$st->execute([$anakId]);
foreach ($st->fetchAll() as $r) $skors[(int)$r['capaian_id']] = (int)$r['skor'];

$manual = [];
$st = $db->prepare('SELECT sub_cp_id, teks FROM deskripsi WHERE anak_id = ?');
$st->execute([$anakId]);
foreach ($st->fetchAll() as $r) $manual[(int)$r['sub_cp_id']] = $r['teks'];

$cps = $db->query('SELECT * FROM cp ORDER BY urutan, id')->fetchAll();
$splitIds = [];
foreach ($cps as $c) {
    $n = strtolower($c['nama']);
    $splitIds[(int)$c['id']] = (strpos($n, 'jati diri') !== false) || (strpos($n, 'literasi') !== false);
}
?>
<div class="panel">
    <div class="penilaian-head">
        <div>
            <strong><?= e($anak['nama_lengkap']) ?></strong><br>
            <span class="muted">Kelompok: <?= e($anak['kelompok'] ?: '-') ?></span>
            <?php if (!$canEdit): ?>
                <span class="badge" style="background:#f1f5f9; color:#475569; margin-left:6px;">Lihat saja</span>
            <?php endif; ?>
        </div>
        <a class="btn btn-sm" href="index.php?route=foto&anak_id=<?= $anakId ?>">📷 Upload Foto Kegiatan</a>
    </div>

    <?php if (!$canEdit): ?>
        <div class="alert alert-error">Anda hanya punya akses <strong>lihat saja</strong> untuk anak ini — data tidak bisa diubah.</div>
    <?php endif; ?>

    <div class="skor-legend">
        <span><b>1</b> Belum berkembang dalam</span>
        <span><b>2</b> Mulai berkembang dalam</span>
        <span><b>3</b> Berkembang sesuai harapan dalam</span>
        <span><b>4</b> Berkembang sangat baik dalam</span>
    </div>

    <form method="post">
        <input type="hidden" name="anak_id" value="<?= $anakId ?>">
        <input type="hidden" name="act" value="simpan">

        <?php foreach ($cps as $cp): ?>
        <div class="cp-section">
            <div class="cp-section-head">
                <h3><?= e($cp['nama']) ?></h3>
                <?php if ($splitIds[(int)$cp['id']]): ?>
                    <span class="badge badge-green">Dicetak 2 Lembar A4</span>
                <?php else: ?>
                    <span class="badge">Dicetak 1 Lembar A4</span>
                <?php endif; ?>
            </div>

            <?php
            $subs = $db->prepare('SELECT * FROM sub_cp WHERE cp_id = ? ORDER BY urutan, id');
            $subs->execute([$cp['id']]);
            $subs = $subs->fetchAll();
            ?>
            <?php foreach ($subs as $sub): ?>
            <div class="sub-block">
                <div class="sub-head"><strong><?= e($sub['nama']) ?></strong></div>
                <?php
                $caps = $db->prepare('SELECT * FROM capaian WHERE sub_cp_id = ? ORDER BY urutan, id');
                $caps->execute([$sub['id']]);
                $caps = $caps->fetchAll();
                ?>
                <?php if ($caps): ?>
                <div class="skor-list">
                    <?php foreach ($caps as $c): $cid = (int)$c['id']; $cur = $skors[$cid] ?? 0; ?>
                    <div class="skor-row">
                        <span class="skor-teks"><?= e($c['teks']) ?></span>
                        <span class="skor-opts" role="radiogroup" aria-label="<?= e($c['teks']) ?>">
                            <?php for ($v = 1; $v <= 4; $v++): ?>
                            <label class="skor-opt skor-<?= $v ?> <?= $cur === $v ? 'on' : '' ?>">
                                <input type="radio" name="skor[<?= $cid ?>]" value="<?= $v ?>" <?= $cur === $v ? 'checked' : '' ?> <?= $canEdit ? '' : 'disabled' ?>>
                                <?= $v ?>
                            </label>
                            <?php endfor; ?>
                        </span>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php else: ?>
                <p class="muted">Belum ada capaian detail — tambahkan di menu Data Capaian.</p>
                <?php endif; ?>

                <?php $auto = capaianSkorText((int)$sub['id'], $skors, $db); ?>
                <div class="deskripsi-box">
                    <div class="deskripsi-head">
                        <label>Deskripsi (otomatis dari skor — bisa diedit manual):</label>
                        <span class="muted auto-hint">Saran: <em><?= $auto ? e('Ananda ' . $panggilan . ' ' . $auto . '.') : '(belum ada skor)' ?></em>
                            <?php if ($canEdit): ?><button type="button" class="btn-link" onclick="isiOtomatis(this)">pakai</button><?php endif; ?>
                        </span>
                    </div>
                    <textarea name="deskripsi[<?= (int)$sub['id'] ?>]" rows="2" <?= $canEdit ? '' : 'disabled' ?>
                        placeholder="Otomatis terisi dari skor 1-4..."><?= e($manual[(int)$sub['id']] ?? '') ?></textarea>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endforeach; ?>

        <div class="form-actions">
            <?php if ($canEdit): ?>
            <button class="btn btn-primary">Simpan Semua Penilaian</button>
            <?php endif; ?>
            <a class="btn" href="index.php?route=foto&anak_id=<?= $anakId ?>">Lanjut: Upload Foto →</a>
        </div>
    </form>
</div>

<!-- Preview deskripsi semua CP -->
<div class="panel">
    <h2>Preview Deskripsi untuk Cetak</h2>
    <?php foreach ($cps as $cp): ?>
        <?php
        $subs = $db->prepare('SELECT * FROM sub_cp WHERE cp_id = ? ORDER BY urutan, id');
        $subs->execute([$cp['id']]);
        $subs = $subs->fetchAll();
        ?>
        <div style="margin-bottom:14px;">
            <strong style="color:var(--hijau-botol);"><?= e($cp['nama']) ?></strong>
            <?php foreach ($subs as $sub): ?>
                <?php
                $auto = capaianSkorText((int)$sub['id'], $skors, $db);
                $text = trim($manual[(int)$sub['id']] ?? '');
                if ($text === '' && $auto !== '') $text = 'Ananda ' . $panggilan . ' ' . $auto . '.';
                ?>
                <div class="preview-item">
                    <strong><?= e($sub['nama']) ?></strong>
                    <p><?= $text ? e($text) : '<span class="muted">— belum ada skor —</span>' ?></p>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endforeach; ?>
</div>

<script>
function isiOtomatis(btn) {
    var box = btn.closest('.deskripsi-box');
    var saran = box.querySelector('.auto-hint em').textContent;
    if (saran && saran.indexOf('(') !== 0) box.querySelector('textarea').value = saran;
}
document.addEventListener('change', function(e) {
    if (e.target.matches('.skor-opt input')) {
        e.target.closest('.skor-opts').querySelectorAll('.skor-opt').forEach(function(l) { l.classList.remove('on'); });
        e.target.closest('.skor-opt').classList.add('on');
    }
});
</script>
<?php else: ?>
    <div class="panel muted">Pilih anak terlebih dahulu.</div>
<?php endif; ?>
<?php page_footer(); ?>
