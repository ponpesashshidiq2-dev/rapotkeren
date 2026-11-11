<?php
require_once __DIR__ . '/layout.php';

$me = currentUserId($db);
$scope = anakScope($db);

// Filter anak
$filterAnak = (int)($_GET['anak_id'] ?? 0);
$st = $db->prepare('SELECT a.id, a.nama_lengkap FROM anak a WHERE ' . $scope['where'] . ' ORDER BY a.nama_lengkap');
$st->execute($scope['params']);
$anakList = $st->fetchAll();

if ($filterAnak && anakAccess($db, $filterAnak) === null) $filterAnak = 0;

$where = '1=1';
$params = [];
if (!$scope['all']) {
    $where .= ' AND (r.anak_id IN (SELECT id FROM anak a WHERE ' . $scope['where'] .
              ') OR (r.anak_id = 0 AND r.user_id = ?))';
    $params = array_merge($params, $scope['params'], [$me]);
}
if ($filterAnak) {
    $where .= ' AND r.anak_id = ?';
    $params[] = $filterAnak;
}

$sql = 'SELECT r.*, u.username, a.nama_lengkap
        FROM revisi r
        LEFT JOIN users u ON u.id = r.user_id
        LEFT JOIN anak a ON a.id = r.anak_id
        WHERE ' . $where . '
        ORDER BY r.id DESC
        LIMIT 300';
$st = $db->prepare($sql);
$st->execute($params);
$rows = $st->fetchAll();

$aksiLabel = [
    'tambah'  => ['badge-green', 'Tambah'],
    'ubah'    => ['', 'Ubah'],
    'hapus'   => ['btn-danger-ish', 'Hapus'],
    'bagikan' => ['share', 'Bagikan'],
    'cabut'   => ['cabut', 'Cabut akses'],
];

page_header('Riwayat Perubahan', 'revisi');
?>
<div class="toolbar" style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:12px; margin-bottom:20px;">
    <form method="get" class="toolbar-form" style="margin:0;">
        <input type="hidden" name="route" value="revisi">
        <label>Anak:</label>
        <select name="anak_id" onchange="this.form.submit()">
            <option value="0">— semua anak —</option>
            <?php foreach ($anakList as $a): ?>
            <option value="<?= (int)$a['id'] ?>" <?= $filterAnak === (int)$a['id'] ? 'selected' : '' ?>>
                <?= e($a['nama_lengkap']) ?>
            </option>
            <?php endforeach; ?>
        </select>
        <noscript><button class="btn btn-sm">Tampilkan</button></noscript>
    </form>
    <div class="muted" style="font-size:13px; font-weight:600;">Menampilkan <?= count($rows) ?> catatan terakhir</div>
</div>

<?php if (!$rows): ?>
    <div class="panel muted">Belum ada riwayat perubahan. Setiap simpanan penilaian, foto, data anak, dan sharing akan tercatat di sini.</div>
<?php else: ?>
<div class="table-wrap">
<table class="table">
    <thead>
        <tr>
            <th style="width:150px;">Waktu</th>
            <th>Pengguna</th>
            <th>Aksi</th>
            <th>Anak</th>
            <th>Detail Perubahan</th>
        </tr>
    </thead>
    <tbody>
    <?php foreach ($rows as $r): ?>
        <tr>
            <td class="muted" style="font-size:12.5px; white-space:nowrap;"><?= e($r['created_at']) ?></td>
            <td><strong><?= e($r['username'] ?? '(user dihapus)') ?></strong></td>
            <td>
                <?php
                    [$cls, $label] = $aksiLabel[$r['aksi']] ?? ['', $r['aksi']];
                    $style = $r['aksi'] === 'hapus' ? 'background:#fef2f2; color:#991b1b;'
                           : ($r['aksi'] === 'bagikan' ? 'background:#e0f2fe; color:#0369a1;'
                           : ($r['aksi'] === 'cabut' ? 'background:#f1f5f9; color:#475569;' : ''));
                ?>
                <span class="badge" style="<?= $style ?>"><?= e($label) ?></span>
                <span class="muted" style="font-size:11.5px;"><?= e($r['tabel']) ?></span>
            </td>
            <td>
                <?php if ($r['nama_lengkap']): ?>
                    <a href="index.php?route=revisi&anak_id=<?= (int)$r['anak_id'] ?>"><?= e($r['nama_lengkap']) ?></a>
                <?php else: ?>
                    <span class="muted">—</span>
                <?php endif; ?>
            </td>
            <td style="font-size:13px;"><?= e($r['ringkasan'] ?: '-') ?></td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
</div>
<p class="muted">Maksimal 300 catatan terakhir ditampilkan.</p>
<?php endif; ?>
<?php page_footer(); ?>
