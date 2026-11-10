<?php
require_once __DIR__ . '/layout.php';

$id = (int)($_GET['id'] ?? 0);
$row = [];
if ($id) {
    $st = $db->prepare('SELECT * FROM anak WHERE id = ?');
    $st->execute([$id]);
    $row = $st->fetch() ?: [];
    if (!$row) redirect('index.php?route=anak');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
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
        flash('Data anak diperbarui.');
    } else {
        $sql = 'INSERT INTO anak (foto, ' . implode(', ', $fields) . ') VALUES (?, ' . implode(', ', array_fill(0, count($fields), '?')) . ')';
        $params = array_merge([$foto], array_map(fn($f) => $data[$f], $fields));
        $db->prepare($sql)->execute($params);
        flash('Data anak ditambahkan.');
    }
    redirect('index.php?route=anak');
}

$pageTitle = $id ? 'Ubah Data Anak' : 'Tambah Data Anak';
page_header($pageTitle, 'anak');

function field($label, $name, $value, $type = 'text', $extra = '') {
    echo '<div><label>' . e($label) . '</label><input type="' . $type . '" name="' . e($name) . '" value="' . e($value) . '" ' . $extra . '></div>';
}
?>
<form method="post" enctype="multipart/form-data" class="panel form-grid">
    <h2>Data Anak</h2>
    <div class="cols">
        <?php
        field('Nama lengkap', 'nama_lengkap', $row['nama_lengkap'] ?? '', 'text', 'required');
        field('Nama panggilan', 'nama_panggilan', $row['nama_panggilan'] ?? '');
        field('NISN / NIS', 'nisn', $row['nisn'] ?? '');
        field('Tempat lahir', 'tempat_lahir', $row['tempat_lahir'] ?? '');
        field('Tanggal lahir', 'tanggal_lahir', $row['tanggal_lahir'] ?? '', 'date');
        ?>
        <div><label>Jenis kelamin</label>
            <select name="jenis_kelamin">
                <?php foreach (['Laki-laki','Perempuan'] as $g): ?>
                <option <?= ($row['jenis_kelamin'] ?? '') === $g ? 'selected' : '' ?>><?= $g ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div><label>Agama</label>
            <select name="agama">
                <?php foreach (['Islam','Kristen','Katolik','Hindu','Buddha','Konghucu'] as $a): ?>
                <option <?= ($row['agama'] ?? '') === $a ? 'selected' : '' ?>><?= $a ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <?php
        field('Anak ke-', 'anak_ke', $row['anak_ke'] ?? '');
        field('Jumlah saudara', 'jumlah_saudara', $row['jumlah_saudara'] ?? '');
        field('Kelompok / rombel', 'kelompok', $row['kelompok'] ?? '');
        ?>
    </div>

    <h2>Data Orang Tua / Wali</h2>
    <div class="cols">
        <?php
        field('Nama ayah', 'nama_ayah', $row['nama_ayah'] ?? '');
        field('Pekerjaan ayah', 'pekerjaan_ayah', $row['pekerjaan_ayah'] ?? '');
        field('Nama ibu', 'nama_ibu', $row['nama_ibu'] ?? '');
        field('Pekerjaan ibu', 'pekerjaan_ibu', $row['pekerjaan_ibu'] ?? '');
        field('Nama wali (jika ada)', 'nama_wali', $row['nama_wali'] ?? '');
        ?>
    </div>

    <h2>Alamat Tempat Tinggal</h2>
    <div class="cols">
        <?php
        field('Jalan / Dusun', 'alamat_jalan', $row['alamat_jalan'] ?? '');
        field('RT / RW', 'alamat_rt_rw', $row['alamat_rt_rw'] ?? '');
        field('Desa / Kelurahan', 'alamat_desa', $row['alamat_desa'] ?? '');
        field('Kecamatan', 'alamat_kecamatan', $row['alamat_kecamatan'] ?? '');
        field('Kabupaten / Kota', 'alamat_kabupaten', $row['alamat_kabupaten'] ?? '');
        field('Provinsi', 'alamat_provinsi', $row['alamat_provinsi'] ?? '');
        ?>
    </div>

    <div class="logo-uploader">
        <?php if (!empty($row['foto'])): ?>
            <img src="<?= e($row['foto']) ?>" class="logo-preview" alt="Foto 3x4">
        <?php else: ?>
            <div class="logo-preview logo-empty">3x4</div>
        <?php endif; ?>
        <div>
            <label>Foto 3x4</label>
            <input type="file" name="foto" accept="image/*">
        </div>
    </div>

    <div class="form-actions">
        <button class="btn btn-primary">Simpan</button>
        <a class="btn" href="index.php?route=anak">Batal</a>
    </div>
</form>
<?php page_footer(); ?>
