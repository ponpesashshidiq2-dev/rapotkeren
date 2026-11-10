<?php
require_once __DIR__ . '/layout.php';
page_header('Cetak Raport', 'cetak');

$anakList = $db->query('SELECT id, nama_lengkap, kelompok FROM anak ORDER BY nama_lengkap')->fetchAll();
$profil = getProfil($db);
?>
<div class="panel">
    <h2>Cetak Laporan Perkembangan Anak Didik</h2>
    <p class="muted">Satu proses cetak menghasilkan dokumen lengkap:</p>
    <ol class="steps">
        <li>Cover — Data Satuan Pendidikan (logo dari Profil)</li>
        <li>Data Peserta Didik</li>
        <li>5 halaman Capaian Pembelajaran (deskripsi + 2 foto kegiatan tiap CP)</li>
        <li>Lembar Pengesahan (tanda tangan dari Profil Lembaga)</li>
    </ol>

    <?php if (empty($profil['nama_sekolah'])): ?>
        <div class="alert alert-error">Profil lembaga belum lengkap. Lengkapi dulu di <a href="index.php?route=profil">Profil</a>.</div>
    <?php endif; ?>

    <?php if (!$anakList): ?>
        <div class="alert alert-error">Belum ada data anak. Tambahkan di <a href="index.php?route=anak">Data Anak</a>.</div>
    <?php else: ?>
    <form method="get" class="toolbar-form">
        <input type="hidden" name="route" value="cetak_print">
        <label>Pilih Anak:</label>
        <select name="anak_id" required>
            <option value="">— pilih anak —</option>
            <?php foreach ($anakList as $a): ?>
            <option value="<?= (int)$a['id'] ?>"><?= e($a['nama_lengkap']) ?><?= $a['kelompok'] ? ' (' . e($a['kelompok']) . ')' : '' ?></option>
            <?php endforeach; ?>
        </select>
        <button class="btn btn-primary">Buka Halaman Cetak</button>
    </form>
    <?php endif; ?>
</div>
<?php page_footer(); ?>
