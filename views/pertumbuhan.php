<?php
require_once __DIR__ . '/layout.php';

$anakId = (int)($_GET['anak_id'] ?? ($_POST['anak_id'] ?? 0));
$anakList = $db->query('SELECT id, nama_lengkap, kelompok FROM anak ORDER BY nama_lengkap')->fetchAll();

// ---- Simpan pertumbuhan & kehadiran per anak ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $anakId && ($_POST['act'] ?? '') === 'simpan') {
    $db->prepare('INSERT INTO refleksi (anak_id, berat_badan, tinggi_badan, lingkar_kepala, lingkar_lengan, sakit, izin, tanpa_keterangan)
                  VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                  ON CONFLICT(anak_id) DO UPDATE SET
                    berat_badan=excluded.berat_badan, tinggi_badan=excluded.tinggi_badan,
                    lingkar_kepala=excluded.lingkar_kepala, lingkar_lengan=excluded.lingkar_lengan,
                    sakit=excluded.sakit, izin=excluded.izin, tanpa_keterangan=excluded.tanpa_keterangan')
       ->execute([
           $anakId,
           trim($_POST['berat_badan'] ?? ''),
           trim($_POST['tinggi_badan'] ?? ''),
           trim($_POST['lingkar_kepala'] ?? ''),
           trim($_POST['lingkar_lengan'] ?? ''),
           (int)($_POST['sakit'] ?? 0),
           (int)($_POST['izin'] ?? 0),
           (int)($_POST['tanpa_keterangan'] ?? 0),
       ]);
    flash('Pertumbuhan & kehadiran disimpan.');
    redirect('index.php?route=pertumbuhan&anak_id=' . $anakId);
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

<?php if ($anakId): ?>
<?php
$st = $db->prepare('SELECT * FROM refleksi WHERE anak_id = ?');
$st->execute([$anakId]);
$r = $st->fetch() ?: [];
?>
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
                <input type="text" name="berat_badan" value="<?= e($r['berat_badan'] ?? '') ?>" placeholder="mis. 17.35 Kg"></div>
            <div class="form-group"><label>Tinggi Badan</label>
                <input type="text" name="tinggi_badan" value="<?= e($r['tinggi_badan'] ?? '') ?>" placeholder="mis. 110 cm"></div>
            <div class="form-group"><label>Lingkar Kepala</label>
                <input type="text" name="lingkar_kepala" value="<?= e($r['lingkar_kepala'] ?? '') ?>" placeholder="mis. 52 cm"></div>
            <div class="form-group"><label>Lingkar Lengan</label>
                <input type="text" name="lingkar_lengan" value="<?= e($r['lingkar_lengan'] ?? '') ?>" placeholder="mis. 18 cm"></div>
        </div>
    </div>

    <div class="panel">
        <div class="cp-section-head">
            <h3>Kehadiran Anak</h3>
            <span class="badge badge-green">Rekap semester (hari)</span>
        </div>
        <div class="form-grid-3" style="padding:16px 18px;">
            <div class="form-group"><label>Sakit</label>
                <input type="number" min="0" name="sakit" value="<?= (int)($r['sakit'] ?? 0) ?>"></div>
            <div class="form-group"><label>Izin</label>
                <input type="number" min="0" name="izin" value="<?= (int)($r['izin'] ?? 0) ?>"></div>
            <div class="form-group"><label>Tanpa Keterangan</label>
                <input type="number" min="0" name="tanpa_keterangan" value="<?= (int)($r['tanpa_keterangan'] ?? 0) ?>"></div>
        </div>
    </div>

    <div class="form-actions" style="margin-bottom:24px;">
        <button class="btn btn-primary">Simpan Pertumbuhan &amp; Kehadiran</button>
        <a class="btn" href="index.php?route=refleksi">← Refleksi Orang Tua</a>
        <a class="btn" href="index.php?route=cetak">Lanjut: Cetak →</a>
    </div>
</form>
<?php else: ?>
    <div class="panel muted">Pilih anak terlebih dahulu.</div>
<?php endif; ?>
<?php page_footer(); ?>
