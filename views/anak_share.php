<?php
require_once __DIR__ . '/layout.php';

$anakId = (int)($_GET['anak_id'] ?? ($_POST['anak_id'] ?? 0));
$anak = null;
if ($anakId) {
    $st = $db->prepare('SELECT * FROM anak WHERE id = ?');
    $st->execute([$anakId]);
    $anak = $st->fetch() ?: null;
}
$access = $anak ? anakAccess($db, $anakId) : null;
if (!$anak || !$access) {
    flash('Data anak tidak ditemukan atau Anda tidak punya akses.', 'error');
    redirect('index.php?route=anak');
}
if ($access !== 'owner') {
    flash('Hanya pemilik data (atau admin) yang bisa membagikan data anak ini.', 'error');
    redirect('index.php?route=anak');
}

// ---- Proses tambah / cabut sharing ----
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $act = $_POST['act'] ?? '';
    if ($act === 'tambah') {
        $userId = (int)($_POST['user_id'] ?? 0);
        $role = (($_POST['role'] ?? '') === 'viewer') ? 'viewer' : 'editor';
        $me = currentUserId($db);
        if ($userId > 0 && $userId !== $me) {
            $st = $db->prepare('SELECT id, username FROM users WHERE id = ?');
            $st->execute([$userId]);
            $tu = $st->fetch();
            if ($tu) {
                $db->prepare('INSERT INTO share (anak_id, user_id, role, created_by, created_at)
                              VALUES (?, ?, ?, ?, ?)
                              ON CONFLICT(anak_id, user_id) DO UPDATE SET role = excluded.role')
                   ->execute([$anakId, $userId, $role, $me, date('Y-m-d H:i:s')]);
                logRevisi($db, $anakId, 'share', (string)$userId, 'bagikan',
                    'Data "' . $anak['nama_lengkap'] . '" dibagikan ke ' . $tu['username'] .
                    ' (' . shareRoleLabel($role) . ')');
                flash('Data dibagikan ke ' . $tu['username'] . ' sebagai ' . shareRoleLabel($role) . '.');
            } else {
                flash('User tidak ditemukan.', 'error');
            }
        } else {
            flash('Pilih user lain (bukan diri sendiri).', 'error');
        }
    } elseif ($act === 'cabut') {
        $userId = (int)($_POST['user_id'] ?? 0);
        $st = $db->prepare('SELECT username FROM users WHERE id = ?');
        $st->execute([$userId]);
        $tu = $st->fetch();
        $db->prepare('DELETE FROM share WHERE anak_id = ? AND user_id = ?')->execute([$anakId, $userId]);
        logRevisi($db, $anakId, 'share', (string)$userId, 'cabut',
            'Akses ' . ($tu['username'] ?? ('user #' . $userId)) . ' dicabut');
        flash('Akses dibatalkan.');
    }
    redirect('index.php?route=anak_share&anak_id=' . $anakId);
}

page_header('Bagikan Data Anak', 'anak');

$stOw = $db->prepare('SELECT username FROM users WHERE id = ?');
$stOw->execute([(int)$anak['user_id']]);
$ownerName = $stOw->fetch()['username'] ?? '-';

// Daftar user yang sudah dibagikan
$st = $db->prepare('SELECT s.*, u.username FROM share s JOIN users u ON u.id = s.user_id
                    WHERE s.anak_id = ? ORDER BY u.username');
$st->execute([$anakId]);
$shares = $st->fetchAll();

// User yang belum punya akses
$st = $db->prepare('SELECT id, username, role FROM users WHERE id != ? AND id NOT IN
                    (SELECT user_id FROM share WHERE anak_id = ?) ORDER BY username');
$st->execute([currentUserId($db), $anakId]);
$available = $st->fetchAll();
?>
<div class="panel">
    <div class="penilaian-head">
        <div>
            <strong><?= e($anak['nama_lengkap']) ?></strong><br>
            <span class="muted">Kelompok: <?= e($anak['kelompok'] ?: '-') ?> • Pemilik: <?= e($ownerName) ?></span>
        </div>
        <a class="btn btn-sm" href="index.php?route=anak">← Kembali ke Daftar Anak</a>
    </div>

    <p class="muted" style="margin-top:14px;">
        Bagikan data anak ini ke user lain agar bisa bekerja bersama:
        <strong>Editor</strong> boleh mengubah penilaian/foto/pertumbuhan,
        <strong>Lihat saja</strong> hanya bisa melihat &amp; mencetak.
    </p>

    <form method="post" class="toolbar-form" style="margin-top:12px;">
        <input type="hidden" name="anak_id" value="<?= $anakId ?>">
        <input type="hidden" name="act" value="tambah">
        <label>User:</label>
        <select name="user_id" required>
            <option value="">— pilih user —</option>
            <?php foreach ($available as $u): ?>
                <option value="<?= (int)$u['id'] ?>"><?= e($u['username']) ?> (<?= e(roleLabel($u['role'])) ?>)</option>
            <?php endforeach; ?>
        </select>
        <label>Peran:</label>
        <select name="role">
            <option value="editor">Editor (boleh ubah)</option>
            <option value="viewer">Lihat saja</option>
        </select>
        <button class="btn btn-primary" <?= $available ? '' : 'disabled' ?>>+ Bagikan</button>
    </form>
    <?php if (!$available): ?>
        <p class="muted" style="margin-top:8px;">Semua user sudah memiliki akses. Tambahkan user baru lewat menu <a href="index.php?route=users">Pengguna</a> (admin).</p>
    <?php endif; ?>
</div>

<div class="panel">
    <h2>User yang Punya Akses (<?= count($shares) ?>)</h2>
    <?php if (!$shares): ?>
        <p class="muted">Belum dibagikan ke siapa pun — hanya Anda (pemilik) yang bisa mengakses.</p>
    <?php else: ?>
    <div class="table-wrap">
    <table class="table">
        <thead><tr><th>Username</th><th>Peran</th><th>Dibagikan pada</th><th style="text-align:right;">Aksi</th></tr></thead>
        <tbody>
        <?php foreach ($shares as $s): ?>
            <tr>
                <td><strong><?= e($s['username']) ?></strong></td>
                <td>
                    <?php if ($s['role'] === 'viewer'): ?>
                        <span class="badge" style="background:#f1f5f9; color:#475569;">Lihat saja</span>
                    <?php else: ?>
                        <span class="badge" style="background:#e0f2fe; color:#0369a1;">Editor</span>
                    <?php endif; ?>
                </td>
                <td class="muted"><?= e($s['created_at'] ?: '-') ?></td>
                <td style="text-align:right;">
                    <form method="post" class="inline" onsubmit="return confirm('Cabut akses <?= e($s['username']) ?>?')">
                        <input type="hidden" name="anak_id" value="<?= $anakId ?>">
                        <input type="hidden" name="act" value="cabut">
                        <input type="hidden" name="user_id" value="<?= (int)$s['user_id'] ?>">
                        <button class="btn btn-sm btn-danger">Cabut Akses</button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>
    <?php endif; ?>
</div>
<?php page_footer(); ?>
