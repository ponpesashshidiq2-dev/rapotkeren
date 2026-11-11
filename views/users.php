<?php
require_once __DIR__ . '/layout.php';

// Sudah difilter requireRole di index.php (super_admin|admin)
$isSuper = isSuperAdmin($db);

// ---- Proses CRUD pengguna ----
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $act = $_POST['act'] ?? '';
    $me = currentUserId($db);

    /** Ambil target user + batasi scope peran (admin hanya boleh kelola guru). */
    $loadTarget = function (int $id) use ($db, $isSuper) {
        if ($id <= 0) return null;
        $st = $db->prepare('SELECT * FROM users WHERE id = ?');
        $st->execute([$id]);
        $tu = $st->fetch();
        if (!$tu) return null;
        // Admin biasa tidak boleh menyentuh admin/super_admin lain
        if (!$isSuper && $tu['role'] !== 'guru') return 'forbidden';
        return $tu;
    };

    if ($act === 'tambah') {
        $username = trim($_POST['username'] ?? '');
        $password = (string)($_POST['password'] ?? '');
        $nama = trim($_POST['nama_lengkap'] ?? '');
        $kuota = max(0, (int)($_POST['kuota_anak'] ?? 0));
        // Super Admin boleh pilih semua peran; admin hanya membuat guru
        $role = (string)($_POST['role'] ?? 'guru');
        $allowed = $isSuper ? ['super_admin', 'admin', 'guru'] : ['guru'];
        if (!in_array($role, $allowed, true)) $role = 'guru';

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
                $db->prepare('INSERT INTO users (username, password, role, nama_lengkap, kuota_anak, status)
                              VALUES (?, ?, ?, ?, ?, ?)')
                   ->execute([$username, password_hash($password, PASSWORD_DEFAULT), $role, $nama, $kuota, 'active']);
                $newId = (int)$db->lastInsertId();
                // Buat baris profil terisolasi untuk user baru
                ensureProfil($db, $newId);
                flash("User $username ditambahkan sebagai " . roleLabel($role) . ".");
            }
        }
    } elseif ($act === 'reset') {
        $id = (int)($_POST['id'] ?? 0);
        $password = (string)($_POST['password'] ?? '');
        $tu = $loadTarget($id);
        if ($tu === 'forbidden') {
            flash('Anda hanya boleh mengelola akun berperan Guru.', 'error');
        } elseif (!$tu) {
            flash('User tidak ditemukan.', 'error');
        } elseif ($id === $me) {
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
        $role = (string)($_POST['role'] ?? 'guru');
        if (!$isSuper) {
            flash('Hanya Super Admin yang boleh mengubah peran user.', 'error');
        } elseif (!in_array($role, ['super_admin', 'admin', 'guru'], true)) {
            flash('Peran tidak valid.', 'error');
        } else {
            $tu = $loadTarget($id);
            if (!$tu || $tu === 'forbidden') {
                flash('User tidak ditemukan atau di luar kewenangan Anda.', 'error');
            } elseif ($id === $me) {
                flash('Tidak bisa mengubah peran diri sendiri.', 'error');
            } else {
                // Jaga minimal satu super_admin
                if ($tu['role'] === 'super_admin' && $role !== 'super_admin') {
                    $supers = (int)$db->query("SELECT COUNT(*) c FROM users WHERE role='super_admin'")->fetch()['c'];
                    if ($supers <= 1) {
                        flash('Harus ada minimal satu Super Admin.', 'error');
                        redirect('index.php?route=users');
                    }
                }
                // Jaga minimal satu admin (admin atau super_admin)
                if (in_array($tu['role'], ['admin', 'super_admin'], true) && $role === 'guru') {
                    $admins = (int)$db->query("SELECT COUNT(*) c FROM users WHERE role IN ('admin','super_admin')")->fetch()['c'];
                    if ($admins <= 1) {
                        flash('Harus ada minimal satu Admin/Super Admin.', 'error');
                        redirect('index.php?route=users');
                    }
                }
                $db->prepare('UPDATE users SET role = ? WHERE id = ?')->execute([$role, $id]);
                flash('Peran user diperbarui menjadi ' . roleLabel($role) . '.');
            }
        }
    } elseif ($act === 'ubah_status') {
        $id = (int)($_POST['id'] ?? 0);
        $status = (($_POST['status'] ?? '') === 'suspended') ? 'suspended' : 'active';
        $tu = $loadTarget($id);
        if ($tu === 'forbidden') {
            flash('Anda hanya boleh mengelola akun berperan Guru.', 'error');
        } elseif (!$tu) {
            flash('User tidak ditemukan.', 'error');
        } elseif ($id === $me) {
            flash('Tidak bisa menonaktifkan akun sendiri.', 'error');
        } else {
            // Jaga minimal satu admin aktif
            if ($status === 'suspended' && in_array($tu['role'], ['admin', 'super_admin'], true)) {
                $admins = (int)$db->query("SELECT COUNT(*) c FROM users
                                           WHERE role IN ('admin','super_admin') AND status = 'active'")->fetch()['c'];
                if ($admins <= 1) {
                    flash('Harus ada minimal satu Admin/Super Admin aktif.', 'error');
                    redirect('index.php?route=users');
                }
            }
            $db->prepare('UPDATE users SET status = ? WHERE id = ?')->execute([$status, $id]);
            flash($status === 'active' ? 'Akun diaktifkan kembali.' : 'Akun dinonaktifkan.');
        }
    } elseif ($act === 'ubah_kuota') {
        $id = (int)($_POST['id'] ?? 0);
        $kuota = max(0, (int)($_POST['kuota_anak'] ?? 0));
        $tu = $loadTarget($id);
        if ($tu === 'forbidden') {
            flash('Anda hanya boleh mengelola akun berperan Guru.', 'error');
        } elseif (!$tu) {
            flash('User tidak ditemukan.', 'error');
        } elseif (!$isSuper && $id === $me) {
            flash('Kuota akun sendiri hanya bisa diubah Super Admin.', 'error');
        } else {
            $db->prepare('UPDATE users SET kuota_anak = ? WHERE id = ?')->execute([$kuota, $id]);
            flash('Kuota data anak diperbarui' . ($kuota > 0 ? " ($kuota anak)." : ' (tanpa batas).'));
        }
    } elseif ($act === 'hapus') {
        $id = (int)($_POST['id'] ?? 0);
        $tu = $loadTarget($id);
        if ($tu === 'forbidden') {
            flash('Anda hanya boleh mengelola akun berperan Guru.', 'error');
        } elseif (!$tu) {
            flash('User tidak ditemukan.', 'error');
        } elseif ($id === $me) {
            flash('Tidak bisa menghapus akun sendiri.', 'error');
        } else {
            if ($tu['role'] === 'super_admin') {
                $supers = (int)$db->query("SELECT COUNT(*) c FROM users WHERE role='super_admin'")->fetch()['c'];
                if ($supers <= 1) {
                    flash('Harus ada minimal satu Super Admin.', 'error');
                    redirect('index.php?route=users');
                }
            }
            if (in_array($tu['role'], ['admin', 'super_admin'], true)) {
                $admins = (int)$db->query("SELECT COUNT(*) c FROM users WHERE role IN ('admin','super_admin')")->fetch()['c'];
                if ($admins <= 1) {
                    flash('Harus ada minimal satu Admin/Super Admin.', 'error');
                    redirect('index.php?route=users');
                }
            }
            $db->prepare('DELETE FROM users WHERE id = ?')->execute([$id]);
            $db->prepare('DELETE FROM share WHERE user_id = ?')->execute([$id]);
            // Profil ikut terhapus (FK ON DELETE CASCADE); bila FK off, hapus eksplisit
            $db->prepare('DELETE FROM profil WHERE user_id = ?')->execute([$id]);
            flash('User dihapus (akses berbaginya ikut dicabut).');
        }
    }
    redirect('index.php?route=users');
}

$me = currentUserId($db);
// Super Admin melihat semua user; admin biasa hanya guru
$userWhere = $isSuper ? '1=1' : "u.role = 'guru'";
$users = $db->query("SELECT u.*,
    (SELECT COUNT(*) FROM anak a WHERE a.user_id = u.id) AS jumlah_anak,
    (SELECT COUNT(*) FROM share s WHERE s.user_id = u.id) AS jumlah_dibagikan
    FROM users u WHERE $userWhere
    ORDER BY CASE u.role WHEN 'super_admin' THEN 0 WHEN 'admin' THEN 1 ELSE 2 END, u.username")->fetchAll();

page_header('Kelola Pengguna', 'users');
?>
<div class="panel">
    <h2>Tambah User Baru</h2>
    <p class="muted">
        <?php if ($isSuper): ?>
            <strong>Super Admin</strong> dapat membuat Super Admin, Admin, dan Guru — kelola semua akun, peran, status, dan kuota.
        <?php else: ?>
            Sebagai <strong>Admin</strong> Anda hanya dapat membuat &amp; mengelola akun <strong>Guru</strong>.
            Guru hanya melihat anak miliknya + yang dibagikan kepadanya.
        <?php endif; ?>
    </p>
    <form method="post" class="toolbar-form" style="margin-top:12px;">
        <input type="hidden" name="act" value="tambah">
        <label>Username:</label>
        <input type="text" name="username" required placeholder="mis. guru_bunga"
               pattern="[a-zA-Z0-9_.]{3,32}" title="3–32 karakter: huruf, angka, _ atau .">
        <label>Nama Lengkap:</label>
        <input type="text" name="nama_lengkap" placeholder="opsional">
        <label>Password:</label>
        <input type="password" name="password" required minlength="6" placeholder="min. 6 karakter">
        <label>Kuota Anak (0 = tanpa batas):</label>
        <input type="number" name="kuota_anak" min="0" value="0" style="width:110px;">
        <label>Peran:</label>
        <select name="role">
            <option value="guru">Guru</option>
            <?php if ($isSuper): ?>
                <option value="admin">Admin</option>
                <option value="super_admin">Super Admin</option>
            <?php endif; ?>
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
                <th>Status</th>
                <th>Kuota</th>
                <th>Anak</th>
                <th>Dibagikan</th>
                <th style="text-align:right;">Aksi</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($users as $u): ?>
            <?php
                $uid = (int)$u['id'];
                $isMe = $uid === $me;
                $role = (string)$u['role'];
                $status = (string)($u['status'] ?? 'active');
                $kuota = (int)($u['kuota_anak'] ?? 0);
                // Aksi hanya bila bukan diri sendiri dan (super, atau target guru)
                $canAct = !$isMe && ($isSuper || $role === 'guru');
            ?>
            <tr>
                <td>
                    <strong><?= e($u['username']) ?></strong>
                    <?php if (!empty($u['nama_lengkap'])): ?>
                        <div class="muted" style="font-size:12px;"><?= e($u['nama_lengkap']) ?></div>
                    <?php endif; ?>
                    <?php if ($isMe): ?><span class="badge badge-green">Anda</span><?php endif; ?>
                </td>
                <td>
                    <?php if ($role === 'super_admin'): ?>
                        <span class="badge" style="background:#ede9fe; color:#5b21b6;">Super Admin</span>
                    <?php elseif ($role === 'admin'): ?>
                        <span class="badge" style="background:#fef3c7; color:#92400e;">Admin</span>
                    <?php else: ?>
                        <span class="badge">Guru</span>
                    <?php endif; ?>
                </td>
                <td>
                    <?php if ($status === 'active'): ?>
                        <span class="badge badge-green">Aktif</span>
                    <?php else: ?>
                        <span class="badge" style="background:#fee2e2; color:#991b1b;">Nonaktif</span>
                    <?php endif; ?>
                </td>
                <td><?= $kuota > 0 ? (int)$kuota : '∞' ?></td>
                <td><?= (int)$u['jumlah_anak'] ?></td>
                <td><?= (int)$u['jumlah_dibagikan'] ?></td>
                <td style="text-align:right;">
                    <div class="row-actions" style="justify-content:flex-end; flex-wrap:wrap; gap:6px;">
                        <?php if ($canAct): ?>
                            <?php if ($isSuper): ?>
                            <form method="post" class="inline">
                                <input type="hidden" name="act" value="ubah_role">
                                <input type="hidden" name="id" value="<?= $uid ?>">
                                <select name="role" style="width:auto; padding:5px 8px; font-size:12.5px;">
                                    <option value="guru" <?= $role === 'guru' ? 'selected' : '' ?>>Guru</option>
                                    <option value="admin" <?= $role === 'admin' ? 'selected' : '' ?>>Admin</option>
                                    <option value="super_admin" <?= $role === 'super_admin' ? 'selected' : '' ?>>Super Admin</option>
                                </select>
                                <button class="btn btn-sm btn-outline">Simpan Peran</button>
                            </form>
                            <?php endif; ?>
                            <form method="post" class="inline">
                                <input type="hidden" name="act" value="ubah_status">
                                <input type="hidden" name="id" value="<?= $uid ?>">
                                <input type="hidden" name="status" value="<?= $status === 'active' ? 'suspended' : 'active' ?>">
                                <button class="btn btn-sm btn-outline"
                                    onclick="return confirm('<?= $status === 'active' ? 'Nonaktifkan' : 'Aktifkan' ?> akun <?= e($u['username']) ?>?')">
                                    <?= $status === 'active' ? 'Nonaktifkan' : 'Aktifkan' ?>
                                </button>
                            </form>
                            <form method="post" class="inline">
                                <input type="hidden" name="act" value="ubah_kuota">
                                <input type="hidden" name="id" value="<?= $uid ?>">
                                <input type="number" name="kuota_anak" min="0" value="<?= $kuota ?>"
                                       title="0 = tanpa batas" style="width:70px; padding:6px 8px; font-size:13px;">
                                <button class="btn btn-sm btn-outline">Kuota</button>
                            </form>
                            <form method="post" class="inline"
                                  onsubmit="return confirm('Reset password <?= e($u['username']) ?>?')">
                                <input type="hidden" name="act" value="reset">
                                <input type="hidden" name="id" value="<?= $uid ?>">
                                <input type="password" name="password" required minlength="6" placeholder="password baru"
                                       style="min-width:140px; width:auto; padding:6px 10px; font-size:13px;">
                                <button class="btn btn-sm btn-outline">Reset Password</button>
                            </form>
                            <form method="post" class="inline"
                                  onsubmit="return confirm('Hapus user <?= e($u['username']) ?>? Anak miliknya tidak ikut terhapus.')">
                                <input type="hidden" name="act" value="hapus">
                                <input type="hidden" name="id" value="<?= $uid ?>">
                                <button class="btn btn-sm btn-danger">Hapus</button>
                            </form>
                        <?php else: ?>
                            <span class="muted" style="font-size:12px;">—</span>
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
