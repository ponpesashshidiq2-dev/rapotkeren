<?php
require_once __DIR__ . '/layout.php';
page_header('Cetak Raport', 'cetak');

$anakList = $db->query('SELECT id, nama_lengkap, kelompok FROM anak ORDER BY nama_lengkap')->fetchAll();
$profil = getProfil($db);
?>
<div class="panel form-card">
    <div class="card-header">
        <div class="card-header-icon">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="8"/></svg>
        </div>
        <div>
            <h3>Cetak Dokumen Raport Anak Didik</h3>
            <p class="muted">Satu proses cetak otomatis menyusun seluruh lembar raport format standar A4</p>
        </div>
    </div>

    <div style="background:#f7faf8; border:1px solid var(--border-light); border-radius:12px; padding:18px 22px; margin-bottom:24px;">
        <div style="font-weight:700; color:var(--hijau-botol); font-size:14px; margin-bottom:6px;">Susunan Lembar Cetak Lengkap:</div>
        <ol class="steps" style="margin-top:4px;">
            <li><strong>Sampul / Cover</strong> — Memuat logo dan identitas resmi satuan pendidikan.</li>
            <li><strong>Data Peserta Didik</strong> — Data pribadi, orang tua, alamat, dan pasfoto 3x4.</li>
            <li><strong>5 Halaman Capaian Pembelajaran (CP)</strong> — Deskripsi naratif perkembangan anak + dokumentasi 2 foto kegiatan tiap CP.</li>
            <li><strong>Lembar Pengesahan</strong> — Tanda tangan orang tua/wali, guru kelompok, dan kepala sekolah berserta stempel/titimangsa.</li>
        </ol>
    </div>

    <?php if (empty($profil['nama_sekolah'])): ?>
        <div class="alert alert-error">Profil lembaga belum lengkap. Silakan lengkapi terlebih dahulu di menu <a href="index.php?route=profil" style="font-weight:700; text-decoration:underline;">Profil Lembaga</a>.</div>
    <?php endif; ?>

    <?php if (!$anakList): ?>
        <div class="alert alert-error">Belum ada data anak didik. Silakan tambahkan di menu <a href="index.php?route=anak" style="font-weight:700; text-decoration:underline;">Data Anak</a>.</div>
    <?php else: ?>
    <form method="get" class="toolbar-form" style="background:#fff; border:1.5px solid var(--border); border-radius:12px; padding:18px 20px;">
        <input type="hidden" name="route" value="cetak_print">
        <label style="font-weight:700; color:var(--hijau-botol);">Pilih Peserta Didik:</label>
        <select name="anak_id" required style="min-width:280px;">
            <option value="">— Pilih nama anak —</option>
            <?php foreach ($anakList as $a): ?>
            <option value="<?= (int)$a['id'] ?>"><?= e($a['nama_lengkap']) ?><?= $a['kelompok'] ? ' (' . e($a['kelompok']) . ')' : '' ?></option>
            <?php endforeach; ?>
        </select>
        <button class="btn btn-primary btn-lg">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="8"/></svg>
            Buka Halaman Cetak Raport
        </button>
    </form>
    <?php endif; ?>
</div>
<?php page_footer(); ?>
