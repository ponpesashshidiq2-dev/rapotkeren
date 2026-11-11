<?php
require_once __DIR__ . '/layout.php';
page_header('Data Anak', 'anak');

$scope = anakScope($db);
$st = $db->prepare('SELECT a.*, u.username AS pemilik,
                    (SELECT GROUP_CONCAT(u2.username || \': \' ||
                        CASE s.role WHEN \'viewer\' THEN \'lihat saja\' ELSE \'editor\' END, \', \')
                     FROM share s JOIN users u2 ON u2.id = s.user_id
                     WHERE s.anak_id = a.id) AS dibagikan
                    FROM anak a
                    LEFT JOIN users u ON u.id = a.user_id
                    WHERE ' . $scope['where'] . '
                    ORDER BY a.nama_lengkap');
$st->execute($scope['params']);
$rows = $st->fetchAll();
?>
<div class="toolbar" style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:12px; margin-bottom:20px;">
    <div style="display:flex; gap:10px; flex-wrap:wrap;">
        <a class="btn btn-primary" href="index.php?route=anak_form">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
            Tambah Peserta Didik
        </a>
        <a class="btn btn-outline" href="index.php?route=anak_import">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
            Import Excel / CSV
        </a>
        <a class="btn btn-outline" href="index.php?route=anak_import&template=1">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
            Download Template
        </a>
    </div>
    <div class="muted" style="font-size:13px; font-weight:600;">
        Total: <?= count($rows) ?> Anak Didik
    </div>
</div>

<?php if (!$rows): ?>
    <div class="panel form-card" style="text-align:center; padding:48px 24px;">
        <div style="width:56px; height:56px; border-radius:16px; background:var(--hijau-soft); color:var(--hijau-daun); display:inline-flex; align-items:center; justify-content:center; margin-bottom:14px;">
            <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="12" cy="7" r="4"/><path d="M5 21v-2a7 7 0 0 1 14 0v2"/></svg>
        </div>
        <h3 style="margin:0 0 6px; font-size:17px; color:var(--hijau-botol);">Belum Ada Data Anak</h3>
        <p class="muted" style="max-width:400px; margin:0 auto 18px; font-size:13.5px;">Tambahkan peserta didik secara manual atau import sekaligus menggunakan berkas template Excel/CSV.</p>
        <a class="btn btn-primary" href="index.php?route=anak_form">+ Tambah Peserta Didik</a>
    </div>
<?php else: ?>
<div class="table-wrap">
<table class="table">
    <thead>
        <tr>
            <th style="width:70px;">Pasfoto</th>
            <th>Nama Lengkap</th>
            <th>Panggilan</th>
            <th>NISN / NIS</th>
            <th>JK</th>
            <th>Kelompok</th>
            <th>Tempat & Tgl Lahir</th>
            <th>Akses</th>
            <th style="text-align:right;">Aksi</th>
        </tr>
    </thead>
    <tbody>
    <?php foreach ($rows as $r):
        $isOwnerUser = ((int)$r['user_id'] === currentUserId($db));
        $access = anakAccess($db, (int)$r['id']);
    ?>
        <tr>
            <td>
                <?php if ($r['foto']): ?>
                    <img src="<?= e($r['foto']) ?>" class="thumb" alt="<?= e($r['nama_lengkap']) ?>">
                <?php else: ?>
                    <div class="thumb thumb-empty">3x4</div>
                <?php endif; ?>
            </td>
            <td><strong><?= e($r['nama_lengkap']) ?></strong></td>
            <td><?= e($r['nama_panggilan']) ?></td>
            <td><code style="background:var(--hijau-soft); color:var(--hijau-botol); padding:2px 6px; border-radius:4px; font-size:12px; font-family:inherit;"><?= e($r['nisn'] ?: '-') ?></code></td>
            <td>
                <?php if ($r['jenis_kelamin'] === 'Laki-laki'): ?>
                    <span style="display:inline-block; padding:2px 8px; border-radius:6px; background:#e0f2fe; color:#0369a1; font-size:12px; font-weight:600;">L</span>
                <?php else: ?>
                    <span style="display:inline-block; padding:2px 8px; border-radius:6px; background:#fce7f3; color:#be185d; font-size:12px; font-weight:600;">P</span>
                <?php endif; ?>
            </td>
            <td><span style="display:inline-block; padding:2px 8px; border-radius:6px; background:var(--hijau-soft); color:var(--hijau-botol); font-size:12.5px; font-weight:600;"><?= e($r['kelompok'] ?: '-') ?></span></td>
            <td style="color:var(--text-secondary);"><?= e($r['tempat_lahir'] ? ($r['tempat_lahir'] . ', ' . $r['tanggal_lahir']) : '-') ?></td>
            <td style="font-size:12.5px;">
                <?php if ($isOwnerUser): ?>
                    <span class="badge badge-green">Milik saya</span>
                <?php elseif ($access === 'owner'): ?>
                    <span class="badge badge-green">Admin</span>
                    <div class="muted" style="margin-top:3px;">Pemilik: <?= e($r['pemilik'] ?: '-') ?></div>
                <?php elseif ($access === 'editor'): ?>
                    <span class="badge" style="background:#e0f2fe; color:#0369a1;">Editor</span>
                    <div class="muted" style="margin-top:3px;">dari <?= e($r['pemilik'] ?: '-') ?></div>
                <?php elseif ($access === 'viewer'): ?>
                    <span class="badge" style="background:#f1f5f9; color:#475569;">Lihat saja</span>
                    <div class="muted" style="margin-top:3px;">dari <?= e($r['pemilik'] ?: '-') ?></div>
                <?php else: ?>
                    <span class="badge" style="background:#f1f5f9; color:#475569;">—</span>
                <?php endif; ?>
                <?php if ($r['dibagikan']): ?>
                    <div class="muted" style="margin-top:3px; font-size:11.5px;">Dibagikan: <?= e($r['dibagikan']) ?></div>
                <?php endif; ?>
            </td>
            <td>
                <div class="row-actions" style="justify-content:flex-end; flex-wrap:wrap;">
                    <?php if ($access === 'owner'): ?>
                        <a class="btn btn-sm btn-outline" href="index.php?route=anak_share&anak_id=<?= (int)$r['id'] ?>">Bagikan</a>
                    <?php endif; ?>
                    <?php if ($access === 'owner' || $access === 'editor'): ?>
                        <a class="btn btn-sm btn-outline" href="index.php?route=anak_form&id=<?= (int)$r['id'] ?>">Ubah</a>
                    <?php endif; ?>
                    <a class="btn btn-sm" href="index.php?route=revisi&anak_id=<?= (int)$r['id'] ?>">Riwayat</a>
                    <?php if ($access === 'owner'): ?>
                    <form method="post" action="index.php?route=anak_delete" class="inline"
                          onsubmit="return confirm('Hapus data <?= e($r['nama_lengkap']) ?>?')">
                        <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                        <button class="btn btn-sm btn-danger">Hapus</button>
                    </form>
                    <?php endif; ?>
                    <?php if (!$access): ?>
                        <span class="muted" style="font-size:12px;">—</span>
                    <?php endif; ?>
                </div>
            </td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
</div>
<?php endif; ?>
<?php page_footer(); ?>
