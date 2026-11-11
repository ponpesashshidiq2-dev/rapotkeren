<?php
require_once __DIR__ . '/layout.php';

if (!isAdmin($db)) {
    flash('Halaman Pengguna hanya untuk admin.', 'error');
    redirect('index.php?route=dashboard');
}

// ---- Proses CRUD pengguna ----
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $act = $_POST['act'] ?? '';
    $me = currentUserId($db);

    if ($act === 'tambah') {
        $username = trim($_POST['username'] ?? '');
        $password = (string)($_POST['password'] ?? '');
        $role = (($_POST['role'] ?? '') === 'admin') ? 'admin' : 'guru';
        if ($username === '' || !preg_match('/^[a-zA-Z0-9_.]{3,32}$/', $username)) {
            flash('Username 3–32 karakter (huruf/angka/_. ) dan wajib diisi.', 'error');
        } elseif (strlen($password) < 6) {
            flash('Password minimal 6 karakter.', 'error');
        } else {
            $st = $db->prepare('SELECT id FROM users WHERE username = ?');
            $st->execute([$username]);
            if ($st->fetch()) {
                flash('Username sudah dipakai.', 'error');
            } else {
                $db->prepare('INSERT INTO users (username, password, role) VALUES (?, ?, ?)')
                   ->execute([$username, password_hash($password, PASSWORD_DEFAULT), $role]);
                flash("User $username ditambahkan sebagai $role.");
            }
        }
    } elseif ($act === 'reset') {
        $id = (int)($_POST['id'] ?? 0);
        $password = (string)($_POST['password'] ?? '');
        if ($id === $me) {
            flash('Tidak bisa reset password sendiri di sini.', 'error');
        } elseif (strlen($password) < 6) {
            flash('Password minimal 6 karakter.', 'error');
        } else {
            $db->prepare('UPDATE users SET password = ? WHERE id = ?')
               ->execute([password_hash($password, PASSWORD_DEFAULT), $id]);
            flash('Password user diperbarui.');
        }
    } elseif ($act === 'ubah_role') {
        $id = (int)($_POST['id'] ?? 0);
        $role = (($_POST['role'] ?? '') === 'admin') ? 'admin' : 'guru';
        if ($id === $me) {
            flash('Tidak bisa mengubah peran diri sendiri.', 'error');
        } else {
            $admins = (int)$db->query("SELECT COUNT(*) c FROM users WHERE role='admin'")->fetch()['c'];
            $st = $db->prepare('SELECT role FROM users WHERE id = ?');
            $st->execute([$id]);
            $tu = $st->fetch();
            if ($tu && $tu['role'] === 'admin' && $role !== 'admin' && $admins <= 1) {
                flash('Harus ada minimal satu admin.', 'error');
            } else {
                $db->prepare('UPDATE users SET role = ? WHERE id = ?')->execute([$role, $id]);
                flash('Peran user diperbarui.');
            }
        }
    } elseif ($act === 'hapus') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id === $me) {
            flash('Tidak bisa menghapus akun sendiri.', 'error');
        } else {
            $st = $db->prepare('SELECT role FROM users WHERE id = ?');
            $st->execute([$id]);
            $tu = $st->fetch();
            $admins = (int)$db->query("SELECT COUNT(*) c FROM users WHERE role='admin'")->fetch()['c'];
            if ($tu && $tu['role'] === 'admin' && $admins <= 1) {
                flash('Harus ada minimal satu admin.', 'error');
            } elseif ($tu) {
                $db->prepare('DELETE FROM users WHERE id = ?')->execute([$id]);
                $db->prepare('DELETE FROM share WHERE user_id = ?')->execute([$id]);
                flash('User dihapus (akses berbaginya ikut dicabut).');
            }
        }
    }
    redirect('index.php?route=users');
}

$me = currentUserId($db);
$users = $db->query('SELECT u.*,
    (SELECT COUNT(*) FROM anak a WHERE a.user_id = u.id) AS jumlah_anak,
    (SELECT COUNT(*) FROM share s WHERE s.user_id = u.id) AS jumlah_dibagikan
    FROM users u ORDER BY CASE u.role WHEN \'admin\' THEN 0 ELSE 1 END, u.username')->fetchAll();

page_header('Kelola Pengguna', 'users');
?>
<div class="panel">
    <h2>Tambah User Baru</h2>
    <p class="muted">User <strong>guru</strong> hanya melihat anak miliknya + yang dibagikan kepadanya.
        User <strong>admin</strong> melihat &amp; mengelola semua data.</p>
    <form method="post" class="toolbar-form" style="margin-top:12px;">
        <input type="hidden" name="act" value="tambah">
        <label>Username:</label>
        <input type="text" name="username" required placeholder="mis. guru_bunga"
               pattern="[a-zA-Z0-9_.]{3,32}" title="3–32 karakter: huruf, angka, _ atau .">
        <label>Password:</label>
        <input type="password" name="password" required minlength="6" placeholder="min. 6 karakter">
        <label>Peran:</label>
        <select name="role">
            <option value="guru">Guru</option>
            <option value="admin">Admin</option>
        </select>
        <button class="btn btn-primary">+ Tambah User</button>
    </form>
</div>

<div class="panel">
    <h2>Daftar User (<?= count($users) ?>)</h2>
    <div class="table-wrap">
    <table class="table">
        <thead>
            <tr>
                <th>Username</th>
                <th>Peran</th>
                <th>Anak dimiliki</th>
                <th>Dibagikan kepadanya</th>
                <th style="text-align:right;">Aksi</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($users as $u): ?>
            <tr>
                <td>
                    <strong><?= e($u['username']) ?></strong>
                    <?php if ((int)$u['id'] === $me): ?><span class="badge badge-green">Anda</span><?php endif; ?>
                </td>
                <td>
                    <?php if ($u['role'] === 'admin'): ?>
                        <span class="badge" style="background:#fef3c7; color:#92400e;">Admin</span>
                    <?php else: ?>
                        <span class="badge">Guru</span>
                    <?php endif; ?>
                </td>
                <td><?= (int)$u['jumlah_anak'] ?></td>
                <td><?= (int)$u['jumlah_dibagikan'] ?></td>
                <td style="text-align:right;">
                    <div class="row-actions" style="justify-content:flex-end; flex-wrap:wrap; gap:6px;">
                        <?php if ((int)$u['id'] !== $me): ?>
                        <form method="post" class="inline">
                            <input type="hidden" name="act" value="ubah_role">
                            <input type="hidden" name="id" value="<?= (int)$u['id'] ?>">
                            <input type="hidden" name="role" value="<?= $u['role'] === 'admin' ? 'guru' : 'admin' ?>">
                            <button class="btn btn-sm btn-outline"
                                onclick="return confirm('Ubah peran <?= e($u['username']) ?> menjadi <?= $u['role'] === 'admin' ? 'guru' : 'admin' ?>?')">
                                Jadikan <?= $u['role'] === 'admin' ? 'Guru' : 'Admin' ?>
                            </button>
                        </form>
                        <form method="post" class="inline"
                              onsubmit="return confirm('Reset password <?= e($u['username']) ?>?')">
                            <input type="hidden" name="act" value="reset">
                            <input type="hidden" name="id" value="<?= (int)$u['id'] ?>">
                            <input type="password" name="password" required minlength="6" placeholder="password baru"
                                   style="min-width:140px; width:auto; padding:6px 10px; font-size:13px;">
                            <button class="btn btn-sm btn-outline">Reset Password</button>
                        </form>
                        <form method="post" class="inline"
                              onsubmit="return confirm('Hapus user <?= e($u['username']) ?>? Anak miliknya tidak ikut terhapus.')">
                            <input type="hidden" name="act" value="hapus">
                            <input type="hidden" name="id" value="<?= (int)$u['id'] ?>">
                            <button class="btn btn-sm btn-danger">Hapus</button>
                        </form>
                        <?php endif; ?>
                    </div>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>
</div>
<?php page_footer(); ?>
