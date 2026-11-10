<?php
require_once __DIR__ . '/layout.php';
page_header('Dashboard', 'dashboard');

$jumlahAnak = (int)$db->query('SELECT COUNT(*) c FROM anak')->fetch()['c'];
$jumlahCp = (int)$db->query('SELECT COUNT(*) c FROM cp')->fetch()['c'];
$jumlahCapaian = (int)$db->query('SELECT COUNT(*) c FROM capaian')->fetch()['c'];
$tercentang = (int)$db->query('SELECT COUNT(*) c FROM penilaian')->fetch()['c'];
$foto = (int)$db->query('SELECT COUNT(*) c FROM foto WHERE file != ""')->fetch()['c'];
$profil = getProfil($db);
?>
<div class="cards">
    <div class="card"><div class="card-num"><?= $jumlahAnak ?></div><div class="card-label">Data Anak</div></div>
    <div class="card"><div class="card-num"><?= $jumlahCp ?></div><div class="card-label">Capaian Pembelajaran</div></div>
    <div class="card"><div class="card-num"><?= $jumlahCapaian ?></div><div class="card-label">Capaian Detail</div></div>
    <div class="card"><div class="card-num"><?= $tercentang ?></div><div class="card-label">Capaian Dicentang</div></div>
    <div class="card"><div class="card-num"><?= $foto ?></div><div class="card-label">Foto Kegiatan</div></div>
</div>

<div class="panel">
    <h2>Langkah Kerja</h2>
    <ol class="steps">
        <li>Isi <a href="index.php?route=profil">Profil Lembaga</a> — identitas sekolah & logo untuk cover.</li>
        <li>Masukkan data lewat menu <a href="index.php?route=anak">Data Anak</a>.</li>
        <li>Sesuaikan <a href="index.php?route=capaian">Data Capaian</a> — sub-bidang & capaian detail per CP (bisa ditambah).</li>
        <li>Isi <a href="index.php?route=penilaian">Penilaian</a> — centang capaian (deskripsi otomatis "Ananda + nama") & upload 2 foto kegiatan per CP.</li>
        <li><a href="index.php?route=cetak">Cetak</a> — satu menu: cover, data anak, 5 halaman CP, lembar pengesahan.</li>
    </ol>
</div>

<div class="panel">
    <h2>Profil Lembaga</h2>
    <p><strong><?= e($profil['nama_sekolah'] ?? '-') ?></strong> — <?= e($profil['jenjang'] ?? '-') ?> · Tahun Ajaran <?= e($profil['tahun_ajaran'] ?? '-') ?> (<?= e($profil['semester'] ?? '-') ?>)</p>
    <p class="muted">Kepala Sekolah: <?= e($profil['kepala_sekolah'] ?: '-') ?> · Guru Kelompok: <?= e($profil['guru_kelompok'] ?: '-') ?></p>
</div>
<?php page_footer(); ?>
