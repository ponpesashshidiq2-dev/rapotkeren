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
    <div class="card">
        <div class="card-num"><?= $jumlahAnak ?></div>
        <div class="card-label">Data Peserta Didik</div>
    </div>
    <div class="card">
        <div class="card-num"><?= $jumlahCp ?></div>
        <div class="card-label">Capaian Pembelajaran (CP)</div>
    </div>
    <div class="card">
        <div class="card-num"><?= $jumlahCapaian ?></div>
        <div class="card-label">Total Indikator Capaian</div>
    </div>
    <div class="card">
        <div class="card-num"><?= $tercentang ?></div>
        <div class="card-label">Capaian Dinilai (Skor 1–4)</div>
    </div>
    <div class="card">
        <div class="card-num"><?= $foto ?></div>
        <div class="card-label">Dokumentasi Foto</div>
    </div>
</div>

<div class="panel form-card">
    <div class="card-header">
        <div class="card-header-icon">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 21h18"/><path d="M5 21V7l7-4 7 4v14"/><path d="M9 21v-4h6v4"/></svg>
        </div>
        <div>
            <h3>Profil Lembaga Aktif</h3>
            <p class="muted">Satuan pendidikan yang saat ini aktif digunakan untuk pencetakan raport</p>
        </div>
    </div>
    <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:12px;">
        <div>
            <div style="font-size:17px; font-weight:700; color:var(--hijau-botol); margin-bottom:4px;">
                <?= e($profil['nama_sekolah'] ?: 'Belum diisi') ?>
            </div>
            <p class="muted" style="margin:0; font-size:13.5px;">
                Jenjang: <strong><?= e($profil['jenjang'] ?: '-') ?></strong> &nbsp;•&nbsp; 
                Tahun Ajaran: <strong><?= e($profil['tahun_ajaran'] ?: '-') ?></strong> (Semester <?= e($profil['semester'] ?: '-') ?>) &nbsp;•&nbsp; 
                Kepala Sekolah: <strong><?= e($profil['kepala_sekolah'] ?: '-') ?></strong>
            </p>
        </div>
        <a href="index.php?route=profil" class="btn btn-outline btn-sm">Edit Profil & Logo &rsaquo;</a>
    </div>
</div>

<div class="panel form-card">
    <div class="card-header">
        <div class="card-header-icon">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
        </div>
        <div>
            <h3>Alur Kerja Penilaian Raport</h3>
            <p class="muted">Panduan langkah-langkah pengisian hingga pencetakan raport anak</p>
        </div>
    </div>
    <ol class="steps">
        <li>Lengkapi <strong><a href="index.php?route=profil">Profil Lembaga</a></strong> — atur nama satuan PAUD/TK, alamat, serta unggah logo untuk cover.</li>
        <li>Kelola daftar anak pada menu <strong><a href="index.php?route=anak">Data Anak</a></strong> (bisa input manual atau import file Excel/CSV).</li>
        <li>Tinjau dan sesuaikan <strong><a href="index.php?route=capaian">Data Capaian</a></strong> — per sub-bidang & capaian indikator (bisa ditambah/diubah).</li>
        <li>Buka menu <strong><a href="index.php?route=penilaian">Penilaian</a></strong> — beri <strong>skor 1–4</strong> untuk tiap capaian (deskripsi otomatis terisi dengan frasa predikat & bisa diedit manual).</li>
        <li>Buka menu <strong><a href="index.php?route=foto">Foto Kegiatan</a></strong> — unggah 2 foto per lembar CP (Jati Diri & Literasi terbagi 2 lembar = 4 foto).</li>
        <li>Buka <strong><a href="index.php?route=cetak">Cetak Raport</a></strong> — menghasilkan dokumen cetak A4 lengkap (Cover, Identitas Anak, 7 Lembar CP + Foto, Lembar Pengesahan).</li>
    </ol>
</div>
<?php page_footer(); ?>
