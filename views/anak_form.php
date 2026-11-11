<?php
require_once __DIR__ . '/layout.php';

$id = (int)($_GET['id'] ?? 0);
$row = [];
if ($id) {
    $st = $db->prepare('SELECT * FROM anak WHERE id = ?');
    $st->execute([$id]);
    $row = $st->fetch() ?: [];
    if (!$row) redirect('index.php?route=anak');
    $access = anakAccess($db, $id);
    if (!$access) {
        flash('Anda tidak punya akses ke data anak ini.', 'error');
        redirect('index.php?route=anak');
    }
    if ($access === 'viewer') {
        flash('Data anak ini hanya bisa dilihat (bukan editor).', 'error');
        redirect('index.php?route=anak');
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($id && !canEditAnak($db, $id)) {
        flash('Anda tidak punya akses mengubah data anak ini.', 'error');
        redirect('index.php?route=anak');
    }
    $fields = ['nama_lengkap','nama_panggilan','nisn','tempat_lahir','tanggal_lahir','jenis_kelamin',
               'agama','anak_ke','jumlah_saudara','kelompok','nama_ayah','pekerjaan_ayah',
               'nama_ibu','pekerjaan_ibu','nama_wali','alamat_jalan','alamat_rt_rw','alamat_desa',
               'alamat_kecamatan','alamat_kabupaten','alamat_provinsi'];
    $foto = $row['foto'] ?? '';
    if (!empty($_FILES['foto']['name'])) {
        $new = uploadFile('foto', 'pasfoto');
        if ($new) {
            deleteUpload($foto);
            $foto = $new;
        }
    }
    $data = ['foto' => $foto];
    foreach ($fields as $f) $data[$f] = trim($_POST[$f] ?? '');

    if ($id) {
        $sql = 'UPDATE anak SET foto=?, ' . implode(', ', array_map(fn($f) => "$f=?", $fields)) . ' WHERE id=?';
        $params = array_merge([$foto], array_map(fn($f) => $data[$f], $fields), [$id]);
        $db->prepare($sql)->execute($params);
        $labels = ['nama_lengkap' => 'nama', 'tanggal_lahir' => 'tgl lahir', 'jenis_kelamin' => 'JK',
                   'nama_ayah' => 'ayah', 'nama_ibu' => 'ibu', 'kelompok' => 'kelompok'];
        $ringkasan = revisiDiff($row, $data, array_merge(['foto'], $fields), $labels);
        if ($row['foto'] !== $foto) {
            $ringkasan = ($ringkasan ? $ringkasan . '; ' : '') . 'pasfoto diganti';
        }
        logRevisi($db, $id, 'anak', (string)$id, 'ubah', $ringkasan ?: 'Data anak disimpan tanpa perubahan');
        flash('Data anak diperbarui.');
    } else {
        // Kuota resource per user (0 = tanpa batas) — tolak bila sudah penuh
        $sisa = sisaKuotaAnak($db);
        if ($sisa !== null && $sisa <= 0) {
            flash('Kuota data anak Anda sudah penuh. Hubungi admin untuk menambah kuota.', 'error');
            redirect('index.php?route=anak');
        }
        $sql = 'INSERT INTO anak (foto, user_id, ' . implode(', ', $fields) . ')
                VALUES (?, ?, ' . implode(', ', array_fill(0, count($fields), '?')) . ')';
        $params = array_merge([$foto, currentUserId($db)], array_map(fn($f) => $data[$f], $fields));
        $db->prepare($sql)->execute($params);
        $newId = (int)$db->lastInsertId();
        logRevisi($db, $newId, 'anak', (string)$newId, 'tambah',
            'Anak "' . ($data['nama_lengkap'] ?: '-') . '" ditambahkan');
        flash('Data anak ditambahkan.');
    }
    redirect('index.php?route=anak');
}

$pageTitle = $id ? 'Ubah Data Anak' : 'Tambah Data Anak';
page_header($pageTitle, 'anak');

function field($label, $name, $value, $type = 'text', $extra = '') {
    echo '<div class="form-group"><label>' . e($label) . '</label><input type="' . $type . '" name="' . e($name) . '" value="' . e($value) . '" ' . $extra . '></div>';
}
?>
<form method="post" enctype="multipart/form-data" class="form-sections">
    <!-- Section 1: Data Diri Anak -->
    <div class="panel form-card">
        <div class="card-header">
            <div class="card-header-icon">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="9" cy="7" r="4"/><path d="M2 21v-2a4 4 0 0 1 4-4h6a4 4 0 0 1 4 4v2"/><circle cx="19" cy="8" r="3"/><path d="M17 21v-1.5a3.5 3.5 0 0 1 3.5-3.5H22"/></svg>
            </div>
            <div>
                <h3>Identitas Peserta Didik</h3>
                <p class="muted">Informasi pribadi anak didik dan pasfoto 3x4 untuk raport</p>
            </div>
        </div>

        <div class="logo-upload-card" style="margin-bottom: 20px;">
            <div class="logo-preview-box" style="width: 80px; height: 104px; border-radius: 8px;">
                <?php if (!empty($row['foto'])): ?>
                    <img src="<?= e($row['foto']) ?>" alt="Pasfoto" id="anakFotoPreview" style="object-fit: cover;">
                <?php else: ?>
                    <div class="logo-empty-state" id="anakFotoEmpty">
                        <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><circle cx="12" cy="8" r="4"/><path d="M4 20c0-4 4-6 8-6s8 2 8 6"/></svg>
                        <span>Pasfoto 3x4</span>
                    </div>
                <?php endif; ?>
            </div>
            <div class="logo-upload-info">
                <div class="upload-title">Pasfoto Anak (3x4)</div>
                <p class="upload-desc">Format JPG atau PNG. Tampil pada lembar data peserta didik di raport cetak.</p>
                <div class="upload-btn-wrap">
                    <label class="btn btn-outline file-btn" for="fotoInput">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" x1="12" y1="3" y2="15"/></svg>
                        Pilih Pasfoto
                    </label>
                    <input type="file" id="fotoInput" name="foto" accept="image/*" class="sr-only" onchange="previewAnakFoto(this)">
                    <span class="file-name-hint" id="fotoFileName">Tidak ada berkas dipilih</span>
                </div>
            </div>
        </div>

        <div class="form-grid-3">
            <div class="form-group col-span-2">
                <label>Nama Lengkap</label>
                <input type="text" name="nama_lengkap" value="<?= e($row['nama_lengkap'] ?? '') ?>" required placeholder="Nama lengkap sesuai akta">
            </div>
            <div class="form-group">
                <label>Nama Panggilan</label>
                <input type="text" name="nama_panggilan" value="<?= e($row['nama_panggilan'] ?? '') ?>" placeholder="Panggilan akrab">
            </div>
            <div class="form-group">
                <label>NISN / NIS</label>
                <input type="text" name="nisn" value="<?= e($row['nisn'] ?? '') ?>" placeholder="Nomor Induk Siswa">
            </div>
            <div class="form-group">
                <label>Tempat Lahir</label>
                <input type="text" name="tempat_lahir" value="<?= e($row['tempat_lahir'] ?? '') ?>" placeholder="Kota / Kabupaten Lahir">
            </div>
            <div class="form-group">
                <label>Tanggal Lahir</label>
                <input type="date" name="tanggal_lahir" value="<?= e($row['tanggal_lahir'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label>Jenis Kelamin</label>
                <select name="jenis_kelamin" class="form-select">
                    <?php foreach (['Laki-laki','Perempuan'] as $g): ?>
                    <option <?= ($row['jenis_kelamin'] ?? '') === $g ? 'selected' : '' ?> value="<?= $g ?>"><?= $g ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>Agama</label>
                <select name="agama" class="form-select">
                    <?php foreach (['Islam','Kristen','Katolik','Hindu','Buddha','Konghucu'] as $a): ?>
                    <option <?= ($row['agama'] ?? '') === $a ? 'selected' : '' ?> value="<?= $a ?>"><?= $a ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>Kelompok / Rombel</label>
                <input type="text" name="kelompok" value="<?= e($row['kelompok'] ?? '') ?>" placeholder="Misal: Kelompok A1">
            </div>
            <div class="form-group">
                <label>Anak ke-</label>
                <input type="number" name="anak_ke" value="<?= e($row['anak_ke'] ?? '') ?>" placeholder="1">
            </div>
            <div class="form-group">
                <label>Jumlah Saudara</label>
                <input type="number" name="jumlah_saudara" value="<?= e($row['jumlah_saudara'] ?? '') ?>" placeholder="0">
            </div>
        </div>
    </div>

    <!-- Section 2: Data Orang Tua / Wali -->
    <div class="panel form-card">
        <div class="card-header">
            <div class="card-header-icon">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
            </div>
            <div>
                <h3>Data Orang Tua & Wali</h3>
                <p class="muted">Identitas ayah, ibu, atau wali peserta didik</p>
            </div>
        </div>

        <div class="form-grid-2">
            <?php
            field('Nama Ayah', 'nama_ayah', $row['nama_ayah'] ?? '');
            field('Pekerjaan Ayah', 'pekerjaan_ayah', $row['pekerjaan_ayah'] ?? '');
            field('Nama Ibu', 'nama_ibu', $row['nama_ibu'] ?? '');
            field('Pekerjaan Ibu', 'pekerjaan_ibu', $row['pekerjaan_ibu'] ?? '');
            ?>
            <div class="form-group col-span-2">
                <label>Nama Wali (jika diasuh wali)</label>
                <input type="text" name="nama_wali" value="<?= e($row['nama_wali'] ?? '') ?>" placeholder="Kosongkan jika diasuh orang tua">
            </div>
        </div>
    </div>

    <!-- Section 3: Alamat Tempat Tinggal -->
    <div class="panel form-card">
        <div class="card-header">
            <div class="card-header-icon">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 10c0 6-8 12-8 12s-8-6-8-12a8 8 0 0 1 16 0Z"/><circle cx="12" cy="10" r="3"/></svg>
            </div>
            <div>
                <h3>Alamat Tempat Tinggal</h3>
                <p class="muted">Domisili tempat tinggal peserta didik saat ini</p>
            </div>
        </div>

        <div class="form-grid-3">
            <div class="form-group col-span-2">
                <label>Jalan / Dusun</label>
                <input type="text" name="alamat_jalan" value="<?= e($row['alamat_jalan'] ?? '') ?>" placeholder="Nama jalan, RT/RW atau dusun">
            </div>
            <div class="form-group">
                <label>RT / RW</label>
                <input type="text" name="alamat_rt_rw" value="<?= e($row['alamat_rt_rw'] ?? '') ?>" placeholder="01 / 02">
            </div>
            <div class="form-group">
                <label>Desa / Kelurahan</label>
                <input type="text" name="alamat_desa" value="<?= e($row['alamat_desa'] ?? '') ?>" placeholder="Desa / Kelurahan">
            </div>
            <div class="form-group">
                <label>Kecamatan</label>
                <input type="text" name="alamat_kecamatan" value="<?= e($row['alamat_kecamatan'] ?? '') ?>" placeholder="Kecamatan">
            </div>
            <div class="form-group">
                <label>Kabupaten / Kota</label>
                <input type="text" name="alamat_kabupaten" value="<?= e($row['alamat_kabupaten'] ?? '') ?>" placeholder="Kabupaten / Kota">
            </div>
            <div class="form-group col-span-3">
                <label>Provinsi</label>
                <input type="text" name="alamat_provinsi" value="<?= e($row['alamat_provinsi'] ?? '') ?>" placeholder="Provinsi">
            </div>
        </div>
    </div>

    <!-- Action Bar -->
    <div class="form-action-bar">
        <div class="bar-notice">
            <span class="notice-badge">INFO</span>
            <span>Data peserta didik akan dicetak pada lembar identitas anak di buku raport.</span>
        </div>
        <div style="display:flex; gap:10px;">
            <a class="btn btn-outline btn-lg" href="index.php?route=anak">Batal</a>
            <button type="submit" class="btn btn-primary btn-lg">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 11-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></svg>
                Simpan Data Anak
            </button>
        </div>
    </div>
</form>

<script>
function previewAnakFoto(input) {
    if (input.files && input.files[0]) {
        const file = input.files[0];
        document.getElementById('fotoFileName').textContent = file.name;
        const reader = new FileReader();
        reader.onload = function(e) {
            const previewBox = document.querySelector('.logo-preview-box');
            previewBox.innerHTML = '<img src="' + e.target.result + '" alt="Pasfoto" id="anakFotoPreview" style="object-fit: cover; width: 100%; height: 100%;">';
        };
        reader.readAsDataURL(file);
    }
}
</script>
<?php page_footer(); ?>
