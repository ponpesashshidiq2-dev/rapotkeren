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
            <p class="muted">Pilih peserta didik dan bagian raport yang ingin dicetak (format A4 standar)</p>
        </div>
    </div>

    <div style="background:#f7faf8; border:1px solid var(--border-light); border-radius:12px; padding:18px 22px; margin-bottom:24px;">
        <div style="font-weight:700; color:var(--hijau-botol); font-size:14px; margin-bottom:8px;">Ketentuan Format Cetak A4:</div>
        <ul style="margin:0 0 0 18px; padding:0; line-height:1.8; font-size:13.5px; color:var(--text-secondary);">
            <li><strong>Cover & Biodata</strong>: Terpisah dari isi raport (2 halaman: Sampul Sekolah & Biodata Anak + Pasfoto).</li>
            <li><strong>Isi Raport CP</strong>: 1 CP dicetak 1 lembar A4, <em>kecuali</em> <strong>AIK</strong> (AL ISLAM, KE'AISYIYAHAN DAN KEMUHAMMADIYAHAN — lembar 1: AL Islam & Al-Qur'an, lembar 2: Ke'Aisyiyahan & Kemuhammadiyahan), <strong>Jati Diri</strong> dan <strong>Dasar Literasi, Matematika, Sains, Tekno & Seni</strong> yang dibagi menjadi <strong>2 lembar</strong>.</li>
            <li><strong>Refleksi Orang Tua</strong>: 1 lembar terpisah berisi daftar pertanyaan (bisa ditambah/dihapus) + jeda tulis tangan — kelola lewat menu <a href="index.php?route=refleksi" style="font-weight:600; text-decoration:underline;">Refleksi Orang Tua</a>.</li>
            <li><strong>Deskripsi</strong>: Terisi otomatis dari <strong>skor 1–4</strong> pada menu <a href="index.php?route=penilaian" style="font-weight:600; text-decoration:underline;">Penilaian</a> (frasa predikat muncul setelah nama anak sebelum detail capaian).</li>
            <li><strong>Foto Kegiatan</strong>: 2 foto per lembar CP — diunggah lewat menu <a href="index.php?route=foto" style="font-weight:600; text-decoration:underline;">Foto Kegiatan</a> (CP terbagi 2 lembar = 4 foto).</li>
            <li><strong>Pertumbuhan, Kehadiran &amp; Pengesahan</strong>: 1 lembar memuat tabel pertumbuhan/kehadiran serta tanda tangan (Orang Tua/Wali, Guru Kelas, Kepala Sekolah + NIP dari menu <a href="index.php?route=profil" style="font-weight:600; text-decoration:underline;">Profil</a>).</li>
            <?php if (!empty($profil['background_rapot'])): ?>
            <li><strong>Background Rapot Kustom Aktif</strong>: Menggunakan gambar background/bingkai yang diunggah di menu <a href="index.php?route=profil" style="font-weight:600; text-decoration:underline;">Profil</a>.</li>
            <?php endif; ?>
        </ul>
    </div>

    <?php if (empty($profil['nama_sekolah'])): ?>
        <div class="alert alert-error">Profil lembaga belum lengkap. Silakan lengkapi terlebih dahulu di menu <a href="index.php?route=profil" style="font-weight:700; text-decoration:underline;">Profil Lembaga</a>.</div>
    <?php endif; ?>

    <?php if (!$anakList): ?>
        <div class="alert alert-error">Belum ada data anak didik. Silakan tambahkan di menu <a href="index.php?route=anak" style="font-weight:700; text-decoration:underline;">Data Anak</a>.</div>
    <?php else: ?>
    <form method="get" style="background:#fff; border:1.5px solid var(--border); border-radius:14px; padding:22px 24px;">
        <input type="hidden" name="route" value="cetak_print">
        
        <div class="form-group" style="margin-bottom:20px;">
            <label style="font-size:14px; font-weight:700; color:var(--hijau-botol);">Pilih Peserta Didik:</label>
            <select name="anak_id" id="anakSelect" required style="font-size:14.5px; max-width:480px;">
                <option value="">— Pilih nama anak —</option>
                <?php foreach ($anakList as $a): ?>
                <option value="<?= (int)$a['id'] ?>"><?= e($a['nama_lengkap']) ?><?= $a['kelompok'] ? ' (' . e($a['kelompok']) . ')' : '' ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div style="font-size:13.5px; font-weight:700; color:var(--hijau-botol); margin-bottom:10px;">Pilih Bagian yang Ingin Dicetak:</div>
        
        <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(280px, 1fr)); gap:14px; margin-bottom:22px;">
            <label style="display:flex; align-items:flex-start; gap:12px; padding:14px 16px; border:1.5px solid var(--border); border-radius:10px; cursor:pointer; background:#fbfdfc; transition:all .2s;" class="print-opt">
                <input type="radio" name="bagian" value="isi" checked style="width:18px; height:18px; margin-top:2px; accent-color:var(--hijau-daun);">
                <div>
                    <div style="font-weight:700; color:var(--hijau-botol); font-size:14px;">1. Isi Raport Saja (9 Halaman)</div>
                    <div class="muted" style="font-size:12.5px; margin-top:3px;">5 CP (AIK/Jati Diri/Literasi terbagi = 7 lembar) + Refleksi Orang Tua + Pertumbuhan &amp; Pengesahan.</div>
                </div>
            </label>

            <label style="display:flex; align-items:flex-start; gap:12px; padding:14px 16px; border:1.5px solid var(--border); border-radius:10px; cursor:pointer; background:#fbfdfc; transition:all .2s;" class="print-opt">
                <input type="radio" name="bagian" value="cover_biodata" style="width:18px; height:18px; margin-top:2px; accent-color:var(--hijau-daun);">
                <div>
                    <div style="font-weight:700; color:var(--hijau-botol); font-size:14px;">2. Cover & Biodata Saja (2 Halaman)</div>
                    <div class="muted" style="font-size:12.5px; margin-top:3px;">Data Satuan Pendidikan (logo sekolah) & Data Peserta Didik (biodata lengkap, pasfoto 3x4). Cocok untuk awal tahun ajaran.</div>
                </div>
            </label>

            <label style="display:flex; align-items:flex-start; gap:12px; padding:14px 16px; border:1.5px solid var(--border); border-radius:10px; cursor:pointer; background:#fbfdfc; transition:all .2s;" class="print-opt">
                <input type="radio" name="bagian" value="semua" style="width:18px; height:18px; margin-top:2px; accent-color:var(--hijau-daun);">
                <div>
                    <div style="font-weight:700; color:var(--hijau-botol); font-size:14px;">3. Cetak Lengkap Semua (11 Halaman)</div>
                    <div class="muted" style="font-size:12.5px; margin-top:3px;">Seluruh dokumen: Cover, Biodata, 7 lembar CP, Refleksi Orang Tua, hingga Pertumbuhan &amp; Pengesahan.</div>
                </div>
            </label>
        </div>

        <button type="submit" class="btn btn-primary btn-lg">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="8"/></svg>
            Buka Dokumen Cetak Raport
        </button>
    </form>
    <?php endif; ?>
</div>

<script>
document.querySelectorAll('.print-opt').forEach(function(card) {
    card.addEventListener('click', function() {
        document.querySelectorAll('.print-opt').forEach(function(c) {
            c.style.borderColor = 'var(--border)';
            c.style.background = '#fbfdfc';
        });
        card.style.borderColor = 'var(--hijau-daun)';
        card.style.background = '#f0f9f3';
    });
});
</script>

<?php page_footer(); ?>
