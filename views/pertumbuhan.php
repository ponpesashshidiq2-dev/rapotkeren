<?php
require_once __DIR__ . '/layout.php';

$anakId = (int)($_GET['anak_id'] ?? ($_POST['anak_id'] ?? 0));
$scope = anakScope($db);
$st = $db->prepare('SELECT id, nama_lengkap, kelompok FROM anak a WHERE ' . $scope['where'] . ' ORDER BY nama_lengkap');
$st->execute($scope['params']);
$anakList = $st->fetchAll();

// ---- Simpan pertumbuhan & kehadiran per anak ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $anakId && ($_POST['act'] ?? '') === 'simpan') {
    if (!canEditAnak($db, $anakId)) {
        flash('Anda tidak punya akses mengubah data anak ini.', 'error');
        redirect('index.php?route=pertumbuhan&anak_id=' . $anakId);
    }
    $stOld = $db->prepare('SELECT * FROM refleksi WHERE anak_id = ?');
    $stOld->execute([$anakId]);
    $old = $stOld->fetch() ?: [];

    $baru = [
        'berat_badan'       => trim($_POST['berat_badan'] ?? ''),
        'tinggi_badan'      => trim($_POST['tinggi_badan'] ?? ''),
        'lingkar_kepala'    => trim($_POST['lingkar_kepala'] ?? ''),
        'lingkar_lengan'    => trim($_POST['lingkar_lengan'] ?? ''),
        'sakit'             => (int)($_POST['sakit'] ?? 0),
        'izin'              => (int)($_POST['izin'] ?? 0),
        'tanpa_keterangan'  => (int)($_POST['tanpa_keterangan'] ?? 0),
    ];
    $db->prepare('INSERT INTO refleksi (anak_id, berat_badan, tinggi_badan, lingkar_kepala, lingkar_lengan, sakit, izin, tanpa_keterangan)
                  VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                  ON CONFLICT(anak_id) DO UPDATE SET
                    berat_badan=excluded.berat_badan, tinggi_badan=excluded.tinggi_badan,
                    lingkar_kepala=excluded.lingkar_kepala, lingkar_lengan=excluded.lingkar_lengan,
                    sakit=excluded.sakit, izin=excluded.izin, tanpa_keterangan=excluded.tanpa_keterangan')
       ->execute(array_merge([$anakId], array_values($baru)));

    $ringkasan = revisiDiff($old, $baru, array_keys($baru), [
        'berat_badan' => 'Berat', 'tinggi_badan' => 'Tinggi',
        'lingkar_kepala' => 'Lingkar kepala', 'lingkar_lengan' => 'Lingkar lengan',
        'sakit' => 'Sakit', 'izin' => 'Izin', 'tanpa_keterangan' => 'Tanpa keterangan',
    ]);
    logRevisi($db, $anakId, 'refleksi', (string)$anakId, 'ubah', $ringkasan);

    flash('Pertumbuhan & kehadiran disimpan.');
    redirect('index.php?route=pertumbuhan&anak_id=' . $anakId);
}

// Validasi akses SEBELUM output HTML (agar redirect berhasil)
$canEdit = false;
if ($anakId && $anakList) {
    $access = anakAccess($db, $anakId);
    if (!$access) {
        flash('Anda tidak punya akses ke data anak ini.', 'error');
        redirect('index.php?route=pertumbuhan');
    }
    $canEdit = ($access === 'owner' || $access === 'editor');
}

page_header('Pertumbuhan & Kehadiran', 'pertumbuhan');
?>
<form method="get" class="panel toolbar-form">
    <input type="hidden" name="route" value="pertumbuhan">
    <label>Anak:</label>
    <select name="anak_id" onchange="this.form.submit()" required>
        <option value="">— pilih anak —</option>
        <?php foreach ($anakList as $a): ?>
        <option value="<?= (int)$a['id'] ?>" <?= $anakId === (int)$a['id'] ? 'selected' : '' ?>>
            <?= e($a['nama_lengkap']) ?><?= $a['kelompok'] ? ' (' . e($a['kelompok']) . ')' : '' ?>
        </option>
        <?php endforeach; ?>
    </select>
    <span class="muted">Data pertumbuhan &amp; rekap kehadiran per anak (terpisah dari Refleksi Orang Tua).</span>
</form>

<?php if ($anakId && $anakList): ?>
<?php
$st = $db->prepare('SELECT * FROM refleksi WHERE anak_id = ?');
$st->execute([$anakId]);
$r = $st->fetch() ?: [];
?>
<?php if (!$canEdit): ?>
<div class="alert alert-error">Anda hanya punya akses <strong>lihat saja</strong> untuk anak ini.</div>
<?php endif; ?>
<form method="post">
    <input type="hidden" name="anak_id" value="<?= $anakId ?>">
    <input type="hidden" name="act" value="simpan">

    <div class="panel">
        <div class="cp-section-head">
            <h3>Pertumbuhan Anak</h3>
            <span class="badge badge-green">Dicetak di lembar Pertumbuhan &amp; Kehadiran</span>
        </div>
        <div class="form-grid-2" style="padding:16px 18px;">
            <div class="form-group"><label>Berat Badan</label>
                <input type="text" name="berat_badan" value="<?= e($r['berat_badan'] ?? '') ?>" placeholder="mis. 17.35 Kg" <?= $canEdit ? '' : 'disabled' ?>></div>
            <div class="form-group"><label>Tinggi Badan</label>
                <input type="text" name="tinggi_badan" value="<?= e($r['tinggi_badan'] ?? '') ?>" placeholder="mis. 110 cm" <?= $canEdit ? '' : 'disabled' ?>></div>
            <div class="form-group"><label>Lingkar Kepala</label>
                <input type="text" name="lingkar_kepala" value="<?= e($r['lingkar_kepala'] ?? '') ?>" placeholder="mis. 52 cm" <?= $canEdit ? '' : 'disabled' ?>></div>
            <div class="form-group"><label>Lingkar Lengan</label>
                <input type="text" name="lingkar_lengan" value="<?= e($r['lingkar_lengan'] ?? '') ?>" placeholder="mis. 18 cm" <?= $canEdit ? '' : 'disabled' ?>></div>
        </div>
    </div>

    <div class="panel">
        <div class="cp-section-head">
            <h3>Kehadiran Anak</h3>
            <span class="badge badge-green">Rekap semester (hari)</span>
        </div>
        <div class="form-grid-3" style="padding:16px 18px;">
            <div class="form-group"><label>Sakit</label>
                <input type="number" min="0" name="sakit" value="<?= (int)($r['sakit'] ?? 0) ?>" <?= $canEdit ? '' : 'disabled' ?>></div>
            <div class="form-group"><label>Izin</label>
                <input type="number" min="0" name="izin" value="<?= (int)($r['izin'] ?? 0) ?>" <?= $canEdit ? '' : 'disabled' ?>></div>
            <div class="form-group"><label>Tanpa Keterangan</label>
                <input type="number" min="0" name="tanpa_keterangan" value="<?= (int)($r['tanpa_keterangan'] ?? 0) ?>" <?= $canEdit ? '' : 'disabled' ?>></div>
        </div>
    </div>

    <div class="form-actions" style="margin-bottom:24px;">
        <?php if ($canEdit): ?>
        <button class="btn btn-primary">Simpan Pertumbuhan &amp; Kehadiran</button>
        <?php endif; ?>
        <a class="btn" href="index.php?route=refleksi">← Refleksi Orang Tua</a>
        <a class="btn" href="index.php?route=cetak">Lanjut: Cetak →</a>
    </div>
</form>
<?php else: ?>
    <div class="panel muted">Pilih anak terlebih dahulu.</div>
<?php endif; ?>
<?php page_footer(); ?>
