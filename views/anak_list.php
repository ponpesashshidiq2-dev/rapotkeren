<?php
require_once __DIR__ . '/layout.php';
page_header('Data Anak', 'anak');
$rows = $db->query('SELECT * FROM anak ORDER BY nama_lengkap')->fetchAll();
?>
<div class="toolbar">
    <a class="btn btn-primary" href="index.php?route=anak_form">+ Tambah Anak</a>
    <a class="btn" href="index.php?route=anak_import">⬆ Import Excel</a>
    <a class="btn" href="index.php?route=anak_import&template=1">⬇ Template</a>
</div>

<?php if (!$rows): ?>
    <div class="panel muted">Belum ada data anak. Klik "+ Tambah Anak" untuk memulai.</div>
<?php else: ?>
<div class="panel table-wrap">
<table class="table">
    <thead>
        <tr>
            <th>Foto</th><th>Nama Lengkap</th><th>Panggilan</th><th>NISN/NIS</th>
            <th>JK</th><th>Kelompok</th><th>Tempat, Tgl Lahir</th><th></th>
        </tr>
    </thead>
    <tbody>
    <?php foreach ($rows as $r): ?>
        <tr>
            <td>
                <?php if ($r['foto']): ?>
                    <img src="<?= e($r['foto']) ?>" class="thumb" alt="">
                <?php else: ?>
                    <div class="thumb thumb-empty">3x4</div>
                <?php endif; ?>
            </td>
            <td><?= e($r['nama_lengkap']) ?></td>
            <td><?= e($r['nama_panggilan']) ?></td>
            <td><?= e($r['nisn']) ?></td>
            <td><?= e($r['jenis_kelamin']) ?></td>
            <td><?= e($r['kelompok']) ?></td>
            <td><?= e($r['tempat_lahir'] . ', ' . $r['tanggal_lahir']) ?></td>
            <td class="row-actions">
                <a class="btn btn-sm" href="index.php?route=anak_form&id=<?= (int)$r['id'] ?>">Ubah</a>
                <form method="post" action="index.php?route=anak_delete" class="inline"
                      onsubmit="return confirm('Hapus data <?= e($r['nama_lengkap']) ?>?')">
                    <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                    <button class="btn btn-sm btn-danger">Hapus</button>
                </form>
            </td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
</div>
<?php endif; ?>
<?php page_footer(); ?>
