<?php
require_once __DIR__ . '/layout.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fields = ['npsn','nama_sekolah','status','jenjang','alamat','rt_rw','desa','kecamatan',
               'kabupaten','provinsi','kode_pos','telp','email','kepala_sekolah',
               'guru_kelompok','tahun_ajaran','semester','footer','nip_kepala','nip_guru',
               'kota','tanggal_raport'];
    $curProfil = getProfil($db);
    $logo = $curProfil['logo'] ?? '';
    $backgroundRapot = $curProfil['background_rapot'] ?? '';

    // Hapus logo jika diminta
    if (!empty($_POST['hapus_logo'])) {
        deleteUpload($logo);
        $logo = '';
    } elseif (!empty($_FILES['logo']['name'])) {
        $new = uploadFile('logo', 'logo');
        if ($new) {
            deleteUpload($logo);
            $logo = $new;
        }
    }

    // Hapus background rapot jika diminta
    if (!empty($_POST['hapus_background_rapot'])) {
        deleteUpload($backgroundRapot);
        $backgroundRapot = '';
    } elseif (!empty($_FILES['background_rapot']['name'])) {
        $newBg = uploadFile('background_rapot', 'background');
        if ($newBg) {
            deleteUpload($backgroundRapot);
            $backgroundRapot = $newBg;
        }
    }

    $sql = 'UPDATE profil SET logo = ?, background_rapot = ?';
    $params = [$logo, $backgroundRapot];
    foreach ($fields as $f) {
        $sql .= ", $f = ?";
        $params[] = trim($_POST[$f] ?? '');
    }
    $sql .= ' WHERE id = 1';
    $db->prepare($sql)->execute($params);
    flash('Profil lembaga dan konfigurasi rapot berhasil diperbarui.');
    redirect('index.php?route=profil');
}

$profil = getProfil($db);
page_header('Profil Lembaga', 'profil');
?>

<div class="page-intro">
    <p class="muted">Kelola identitas resmi satuan pendidikan PAUD/TK, logo resmi cover cetak, background/bingkai rapot, alamat sekolah, dan personalia penanggung jawab tanda tangan.</p>
</div>

<form method="post" enctype="multipart/form-data" class="form-sections">
    <!-- Section 1: Logo & Identitas Lembaga -->
    <div class="panel form-card">
        <div class="card-header">
            <div class="card-header-icon">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 21h18"/><path d="M5 21V7l7-4 7 4v14"/><path d="M9 21v-4h6v4"/></svg>
            </div>
            <div>
                <h3>Identitas Satuan Pendidikan & Logo</h3>
                <p class="muted">Data pokok lembaga dan logo resmi yang dicetak pada halaman cover raport dan header aplikasi</p>
            </div>
        </div>

        <div class="logo-upload-card">
            <div class="logo-preview-box">
                <?php if (!empty($profil['logo']) && file_exists(BASE_DIR . '/' . $profil['logo'])): ?>
                    <img src="<?= e($profil['logo']) ?>" alt="Logo Sekolah" id="logoPreviewImg">
                <?php else: ?>
                    <div class="logo-empty-state" id="logoPreviewEmpty">
                        <svg width="34" height="34" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><rect width="18" height="18" x="3" y="3" rx="2"/><circle cx="9" cy="9" r="2"/><path d="m21 15-3.086-3.086a2 2 0 0 0-2.828 0L6 21"/></svg>
                        <span>Belum Ada Logo</span>
                    </div>
                <?php endif; ?>
            </div>
            <div class="logo-upload-info">
                <div class="upload-title">Logo Resmi Sekolah</div>
                <p class="upload-desc">Format berkas: JPG, PNG, atau WebP (disarankan latar transparan). Tampil di cover raport dan header aplikasi.</p>
                <div class="upload-btn-wrap">
                    <label class="btn btn-outline file-btn" for="logoInput">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" x1="12" y1="3" y2="15"/></svg>
                        Pilih Berkas Logo
                    </label>
                    <input type="file" id="logoInput" name="logo" accept="image/*" class="sr-only" onchange="previewLogo(this)">
                    <span class="file-name-hint" id="logoFileName">Tidak ada berkas baru dipilih</span>

                    <?php if (!empty($profil['logo'])): ?>
                    <label style="display:inline-flex; align-items:center; gap:6px; margin-left:10px; cursor:pointer; font-size:12.5px; color:#ef4444; font-weight:600;">
                        <input type="checkbox" name="hapus_logo" value="1" style="width:auto; height:auto; accent-color:#ef4444;"> Hapus Logo
                    </label>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="form-grid-2">
            <div class="form-group">
                <label for="npsn">NPSN (Nomor Pokok Sekolah Nasional)</label>
                <input type="text" id="npsn" name="npsn" value="<?= e($profil['npsn']) ?>" placeholder="Misal: 20108899">
            </div>
            <div class="form-group">
                <label for="nama_sekolah">Nama Sekolah / Satuan PAUD</label>
                <input type="text" id="nama_sekolah" name="nama_sekolah" value="<?= e($profil['nama_sekolah']) ?>" placeholder="Misal: TK ISLAM ASSHIDIQ" required>
            </div>
            <div class="form-group">
                <label for="status">Status Sekolah</label>
                <input type="text" id="status" name="status" value="<?= e($profil['status']) ?>" placeholder="Swasta / Negeri">
            </div>
            <div class="form-group">
                <label for="jenjang">Jenjang / Bentuk Satuan</label>
                <input type="text" id="jenjang" name="jenjang" value="<?= e($profil['jenjang']) ?>" placeholder="TK / KB / TPA / SPS">
            </div>
        </div>
    </div>

    <!-- Section 2: Background / Bingkai Rapot A4 -->
    <div class="panel form-card">
        <div class="card-header">
            <div class="card-header-icon">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="18" height="18" x="3" y="3" rx="2"/><circle cx="9" cy="9" r="2"/><path d="m21 15-3.086-3.086a2 2 0 0 0-2.828 0L6 21"/></svg>
            </div>
            <div>
                <h3>Background / Bingkai Rapot (A4)</h3>
                <p class="muted">Unggah gambar latar belakang atau template bingkai resmi cetak raport (ukuran kertas A4)</p>
            </div>
        </div>

        <div class="logo-upload-card">
            <div class="logo-preview-box" style="width: 85px; height: 120px; border-radius: 8px;">
                <?php if (!empty($profil['background_rapot']) && file_exists(BASE_DIR . '/' . $profil['background_rapot'])): ?>
                    <img src="<?= e($profil['background_rapot']) ?>" alt="Background Rapot" id="bgPreviewImg" style="object-fit: cover; width: 100%; height: 100%;">
                <?php else: ?>
                    <div class="logo-empty-state" id="bgPreviewEmpty">
                        <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><rect x="3" y="3" width="18" height="18" rx="2"/><line x1="3" y1="9" x2="21" y2="9"/><line x1="9" y1="21" x2="9" y2="9"/></svg>
                        <span>Bingkai Bawaan</span>
                    </div>
                <?php endif; ?>
            </div>
            <div class="logo-upload-info">
                <div class="upload-title">Gambar Background / Template Bingkai Rapot</div>
                <p class="upload-desc">
                    Unggah gambar bingkai/kop resmi sekolah dalam format JPG atau PNG (orientasi Portrait A4, rasio 1:1.414).<br>
                    Jika diunggah, gambar ini otomatis menjadi latar belakang di seluruh lembar cetak rapot (Cover, Biodata, Halaman CP, dan Pengesahan).
                </p>
                <div class="upload-btn-wrap">
                    <label class="btn btn-outline file-btn" for="bgInput">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" x1="12" y1="3" y2="15"/></svg>
                        Pilih Gambar Background
                    </label>
                    <input type="file" id="bgInput" name="background_rapot" accept="image/*" class="sr-only" onchange="previewBg(this)">
                    <span class="file-name-hint" id="bgFileName">Tidak ada berkas baru dipilih</span>

                    <?php if (!empty($profil['background_rapot'])): ?>
                    <label style="display:inline-flex; align-items:center; gap:6px; margin-left:10px; cursor:pointer; font-size:12.5px; color:#ef4444; font-weight:600;">
                        <input type="checkbox" name="hapus_background_rapot" value="1" style="width:auto; height:auto; accent-color:#ef4444;"> Hapus Background (Gunakan Bawaan)
                    </label>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Section 3: Alamat & Kontak -->
    <div class="panel form-card">
        <div class="card-header">
            <div class="card-header-icon">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 10c0 6-8 12-8 12s-8-6-8-12a8 8 0 0 1 16 0Z"/><circle cx="12" cy="10" r="3"/></svg>
            </div>
            <div>
                <h3>Alamat & Kontak Sekolah</h3>
                <p class="muted">Detail lokasi dan kontak resmi untuk identitas satuan pendidikan</p>
            </div>
        </div>

        <div class="form-grid-3">
            <div class="form-group col-span-2">
                <label for="alamat">Alamat Jalan</label>
                <input type="text" id="alamat" name="alamat" value="<?= e($profil['alamat']) ?>" placeholder="Nama jalan, gedung, nomor">
            </div>
            <div class="form-group">
                <label for="rt_rw">RT / RW</label>
                <input type="text" id="rt_rw" name="rt_rw" value="<?= e($profil['rt_rw']) ?>" placeholder="01 / 02">
            </div>
            <div class="form-group">
                <label for="desa">Desa / Kelurahan</label>
                <input type="text" id="desa" name="desa" value="<?= e($profil['desa']) ?>" placeholder="Kelurahan / Desa">
            </div>
            <div class="form-group">
                <label for="kecamatan">Kecamatan</label>
                <input type="text" id="kecamatan" name="kecamatan" value="<?= e($profil['kecamatan']) ?>" placeholder="Kecamatan">
            </div>
            <div class="form-group">
                <label for="kabupaten">Kabupaten / Kota</label>
                <input type="text" id="kabupaten" name="kabupaten" value="<?= e($profil['kabupaten']) ?>" placeholder="Kabupaten / Kota">
            </div>
            <div class="form-group">
                <label for="provinsi">Provinsi</label>
                <input type="text" id="provinsi" name="provinsi" value="<?= e($profil['provinsi']) ?>" placeholder="Provinsi">
            </div>
            <div class="form-group">
                <label for="kode_pos">Kode Pos</label>
                <input type="text" id="kode_pos" name="kode_pos" value="<?= e($profil['kode_pos']) ?>" placeholder="5 Digit Kode Pos">
            </div>
            <div class="form-group">
                <label for="telp">Nomor Telepon / WhatsApp</label>
                <input type="text" id="telp" name="telp" value="<?= e($profil['telp']) ?>" placeholder="08xxxxxxxxxx">
            </div>
            <div class="form-group col-span-3">
                <label for="email">Email Sekolah</label>
                <input type="email" id="email" name="email" value="<?= e($profil['email']) ?>" placeholder="sekolah@domain.sch.id">
            </div>
        </div>
    </div>

    <!-- Section 4: Periode & Personalia Tanda Tangan -->
    <div class="panel form-card">
        <div class="card-header">
            <div class="card-header-icon">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
            </div>
            <div>
                <h3>Periode Ajaran & Personalia Penandatangan</h3>
                <p class="muted">Nama yang diinput di sini otomatis diambil dan dicantumkan pada kolom tanda tangan lembar pengesahan dan data anak</p>
            </div>
        </div>

        <div class="form-grid-3">
            <div class="form-group">
                <label for="tahun_ajaran">Tahun Ajaran</label>
                <input type="text" id="tahun_ajaran" name="tahun_ajaran" value="<?= e($profil['tahun_ajaran']) ?>" placeholder="2025/2026">
            </div>
            <div class="form-group">
                <label for="semester">Semester</label>
                <div class="select-wrapper">
                    <select id="semester" name="semester" class="form-select">
                        <?php foreach (['Ganjil','Genap'] as $s): ?>
                        <option <?= $profil['semester'] === $s ? 'selected' : '' ?> value="<?= $s ?>"><?= $s ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="form-group">
                <label for="footer">Teks Footer Cetak</label>
                <input type="text" id="footer" name="footer" value="<?= e($profil['footer']) ?>" placeholder="Misal: SAHABAT GURU">
            </div>
            <div class="form-group">
                <label for="kota">Kota (untuk tanggal cetak raport)</label>
                <input type="text" id="kota" name="kota" value="<?= e($profil['kota'] ?? '') ?>" placeholder="mis. Ngawan">
            </div>
            <div class="form-group">
                <label for="tanggal_raport">Tanggal Raport</label>
                <input type="date" id="tanggal_raport" name="tanggal_raport" value="<?= e($profil['tanggal_raport'] ?? '') ?>">
            </div>
            <div class="form-group col-span-2">
                <label for="kepala_sekolah">Nama Kepala Sekolah (Dicantumkan di Tanda Tangan)</label>
                <input type="text" id="kepala_sekolah" name="kepala_sekolah" value="<?= e($profil['kepala_sekolah']) ?>" placeholder="Nama lengkap beserta gelar (mis. Dra. Hj. Siti Aminah, M.Pd)">
            </div>
            <div class="form-group">
                <label for="nip_kepala">NIP / NBM Kepala Sekolah</label>
                <input type="text" id="nip_kepala" name="nip_kepala" value="<?= e($profil['nip_kepala'] ?? '') ?>" placeholder="mis. 19700101 199403 1 001">
            </div>
            <div class="form-group">
                <label for="guru_kelompok">Nama Guru Kelompok (Dicantumkan di Tanda Tangan)</label>
                <input type="text" id="guru_kelompok" name="guru_kelompok" value="<?= e($profil['guru_kelompok']) ?>" placeholder="Nama lengkap guru kelas / wali kelompok">
            </div>
            <div class="form-group">
                <label for="nip_guru">NIP / NBM Guru Kelompok</label>
                <input type="text" id="nip_guru" name="nip_guru" value="<?= e($profil['nip_guru'] ?? '') ?>" placeholder="mis. 1332715">
            </div>
        </div>
    </div>

    <!-- Action Bar -->
    <div class="form-action-bar">
        <div class="bar-notice">
            <span class="notice-badge">INFO</span>
            <span>Perubahan data profil, tanda tangan, dan background langsung diterapkan pada seluruh dokumen cetak raport.</span>
        </div>
        <button type="submit" class="btn btn-primary btn-lg">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></svg>
            Simpan Pengaturan Profil & Rapot
        </button>
    </div>
</form>

<script>
function previewLogo(input) {
    if (input.files && input.files[0]) {
        const file = input.files[0];
        document.getElementById('logoFileName').textContent = file.name;
        const reader = new FileReader();
        reader.onload = function(e) {
            const previewBox = document.querySelectorAll('.logo-preview-box')[0];
            previewBox.innerHTML = '<img src="' + e.target.result + '" alt="Preview Logo" id="logoPreviewImg">';
        };
        reader.readAsDataURL(file);
    }
}

function previewBg(input) {
    if (input.files && input.files[0]) {
        const file = input.files[0];
        document.getElementById('bgFileName').textContent = file.name;
        const reader = new FileReader();
        reader.onload = function(e) {
            const previewBox = document.querySelectorAll('.logo-preview-box')[1];
            previewBox.innerHTML = '<img src="' + e.target.result + '" alt="Preview Background" id="bgPreviewImg" style="object-fit:cover; width:100%; height:100%;">';
        };
        reader.readAsDataURL(file);
    }
}
</script>

<?php page_footer(); ?>
