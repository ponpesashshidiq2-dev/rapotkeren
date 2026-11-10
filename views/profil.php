<?php
require_once __DIR__ . '/layout.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fields = ['npsn','nama_sekolah','status','jenjang','alamat','rt_rw','desa','kecamatan',
               'kabupaten','provinsi','kode_pos','telp','email','kepala_sekolah',
               'guru_kelompok','tahun_ajaran','semester','footer'];
    $logo = getProfil($db)['logo'] ?? '';
    if (!empty($_FILES['logo']['name'])) {
        $new = uploadFile('logo', 'logo');
        if ($new) {
            deleteUpload($logo);
            $logo = $new;
        }
    }
    $sql = 'UPDATE profil SET logo = ?';
    $params = [$logo];
    foreach ($fields as $f) {
        $sql .= ", $f = ?";
        $params[] = trim($_POST[$f] ?? '');
    }
    $sql .= ' WHERE id = 1';
    $db->prepare($sql)->execute($params);
    flash('Profil lembaga disimpan.');
    redirect('index.php?route=profil');
}

$profil = getProfil($db);
require_once __DIR__ . '/layout.php';
page_header('Profil Lembaga', 'profil');
?>
<form method="post" enctype="multipart/form-data" class="panel form-grid">
    <h2>Data Satuan Pendidikan</h2>

    <div class="logo-uploader">
        <?php if (!empty($profil['logo'])): ?>
            <img src="<?= e($profil['logo']) ?>" alt="Logo" class="logo-preview">
        <?php else: ?>
            <div class="logo-preview logo-empty">Logo</div>
        <?php endif; ?>
        <div>
            <label>Logo Sekolah (untuk cover cetak)</label>
            <input type="file" name="logo" accept="image/*">
            <p class="muted">Format: JPG/PNG. Tampil di halaman cover.</p>
        </div>
    </div>

    <div class="cols">
        <div><label>NPSN</label><input name="npsn" value="<?= e($profil['npsn']) ?>"></div>
        <div><label>Nama Sekolah / Satuan PAUD</label><input name="nama_sekolah" value="<?= e($profil['nama_sekolah']) ?>"></div>
        <div><label>Status Sekolah</label><input name="status" value="<?= e($profil['status']) ?>"></div>
        <div><label>Jenjang / Bentuk Satuan</label><input name="jenjang" value="<?= e($profil['jenjang']) ?>"></div>
        <div><label>Alamat Jalan</label><input name="alamat" value="<?= e($profil['alamat']) ?>"></div>
        <div><label>RT / RW</label><input name="rt_rw" value="<?= e($profil['rt_rw']) ?>"></div>
        <div><label>Desa / Kelurahan</label><input name="desa" value="<?= e($profil['desa']) ?>"></div>
        <div><label>Kecamatan</label><input name="kecamatan" value="<?= e($profil['kecamatan']) ?>"></div>
        <div><label>Kabupaten / Kota</label><input name="kabupaten" value="<?= e($profil['kabupaten']) ?>"></div>
        <div><label>Provinsi</label><input name="provinsi" value="<?= e($profil['provinsi']) ?>"></div>
        <div><label>Kode Pos</label><input name="kode_pos" value="<?= e($profil['kode_pos']) ?>"></div>
        <div><label>Nomor Telepon</label><input name="telp" value="<?= e($profil['telp']) ?>"></div>
        <div><label>Email Sekolah</label><input name="email" value="<?= e($profil['email']) ?>"></div>
        <div><label>Nama Kepala Sekolah</label><input name="kepala_sekolah" value="<?= e($profil['kepala_sekolah']) ?>"></div>
        <div><label>Nama Guru Kelompok</label><input name="guru_kelompok" value="<?= e($profil['guru_kelompok']) ?>"></div>
        <div><label>Tahun Ajaran</label><input name="tahun_ajaran" value="<?= e($profil['tahun_ajaran']) ?>" placeholder="2025/2026"></div>
        <div><label>Semester</label>
            <select name="semester">
                <?php foreach (['Ganjil','Genap'] as $s): ?>
                <option <?= $profil['semester'] === $s ? 'selected' : '' ?>><?= $s ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div><label>Teks Footer Cetak</label><input name="footer" value="<?= e($profil['footer']) ?>"></div>
    </div>

    <button class="btn btn-primary">Simpan Profil</button>
</form>
<?php page_footer(); ?>
