<?php
require_once __DIR__ . '/layout.php';

$anakId = (int)($_GET['anak_id'] ?? ($_POST['anak_id'] ?? 0));
$anakList = $db->query('SELECT id, nama_lengkap, kelompok FROM anak ORDER BY nama_lengkap')->fetchAll();
$profil = getProfil($db);

$DEFAULT_Q = [
    1 => 'Perubahan positif apa yang sudah ada pada Ananda selama semester ini?',
    2 => 'Pendampingan/dukungan apa yang sudah Bapak/Ibu lakukan kepada Ananda selama semester ini?',
    3 => 'Potensi apa yang ingin Bapak/Ibu maksimalkan dari Ananda?',
];

// ---- Simpan pertanyaan global (sama untuk semua anak) ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['act'] ?? '') === 'simpan_pertanyaan') {
    $db->prepare('UPDATE profil SET refleksi_q1 = ?, refleksi_q2 = ?, refleksi_q3 = ? WHERE id = 1')
       ->execute([
           trim($_POST['q1'] ?? ''),
           trim($_POST['q2'] ?? ''),
           trim($_POST['q3'] ?? ''),
       ]);
    flash('Pertanyaan refleksi disimpan (berlaku untuk semua anak).');
    redirect('index.php?route=refleksi' . ($anakId ? '&anak_id=' . $anakId : ''));
}

// ---- Simpan pertumbuhan & kehadiran per anak ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $anakId && ($_POST['act'] ?? '') === 'simpan_anak') {
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
    redirect('index.php?route=refleksi&anak_id=' . $anakId);
}

page_header('Refleksi Orang Tua', 'refleksi');
?>
<div class="panel">
    <div class="cp-section-head">
        <h3>Pertanyaan Refleksi Orang Tua</h3>
        <span class="badge">Global — sama untuk semua anak, diisi sekali</span>
    </div>
    <form method="post" style="padding:16px 18px; display:flex; flex-direction:column; gap:14px;">
        <input type="hidden" name="act" value="simpan_pertanyaan">
        <input type="hidden" name="anak_id" value="<?= $anakId ?>">
        <?php for ($i = 1; $i <= 3; $i++): ?>
        <div class="form-group" style="margin:0;">
            <label>Pertanyaan <?= $i ?></label>
            <input type="text" name="q<?= $i ?>"
                   value="<?= e($profil['refleksi_q' . $i] ?? '') ?>"
                   placeholder="<?= e($DEFAULT_Q[$i]) ?>">
        </div>
        <?php endfor; ?>
        <div class="muted" style="font-size:12.5px;">Kosongkan untuk memakai pertanyaan bawaan. Di cetak, setiap pertanyaan diberi jeda garis untuk ditulis orang tua.</div>
        <div><button class="btn btn-primary">Simpan Pertanyaan (untuk Semua Anak)</button></div>
    </form>
</div>

<form method="get" class="panel toolbar-form">
    <input type="hidden" name="route" value="refleksi">
    <label>Anak:</label>
    <select name="anak_id" onchange="this.form.submit()" required>
        <option value="">— pilih anak —</option>
        <?php foreach ($anakList as $a): ?>
        <option value="<?= (int)$a['id'] ?>" <?= $anakId === (int)$a['id'] ? 'selected' : '' ?>>
            <?= e($a['nama_lengkap']) ?><?= $a['kelompok'] ? ' (' . e($a['kelompok']) . ')' : '' ?>
        </option>
        <?php endforeach; ?>
    </select>
    <span class="muted">Data pertumbuhan &amp; rekap kehadiran per anak.</span>
</form>

<?php if ($anakId): ?>
<?php
$st = $db->prepare('SELECT * FROM refleksi WHERE anak_id = ?');
$st->execute([$anakId]);
$r = $st->fetch() ?: [];
?>
<form method="post">
    <input type="hidden" name="anak_id" value="<?= $anakId ?>">
    <input type="hidden" name="act" value="simpan_anak">

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
        <a class="btn" href="index.php?route=cetak">Lanjut: Cetak →</a>
    </div>
</form>
<?php else: ?>
    <div class="panel muted">Pilih anak untuk mengisi pertumbuhan &amp; kehadiran.</div>
<?php endif; ?>
<?php page_footer(); ?>
