<?php
// Otorisasi multi-user: kepemilikan data anak, sharing, dan riwayat revisi

function authz_user(PDO $db): ?array {
    static $cache = null;
    static $done = false;
    if ($done) return $cache;
    $done = true;
    if (!empty($_SESSION['user'])) {
        $st = $db->prepare('SELECT * FROM users WHERE username = ?');
        $st->execute([$_SESSION['user']]);
        $cache = $st->fetch() ?: null;
    }
    return $cache;
}

// Panggil sekali setelah requireLogin(): pastikan akun di sesi masih ada & aktif
function authz_guard(PDO $db): void {
    if (!isLoggedIn()) return;
    $u = authz_user($db);
    if (!$u) {
        session_destroy();
        redirect('index.php?route=login');
    }
    // Akun dinonaktifkan oleh admin → paksa keluar
    if (($u['status'] ?? 'active') !== 'active') {
        session_destroy();
        redirect('index.php?route=login');
    }
}

function currentUser(PDO $db): ?array {
    return authz_user($db);
}

function currentUserId(PDO $db): int {
    $u = authz_user($db);
    return $u ? (int)$u['id'] : 0;
}

// Peran: super_admin > admin > guru
function userRole(PDO $db): string {
    $u = authz_user($db);
    return $u ? (string)($u['role'] ?? 'guru') : '';
}

function isSuperAdmin(PDO $db): bool {
    return userRole($db) === 'super_admin';
}

function isAdmin(PDO $db): bool {
    $r = userRole($db);
    return $r === 'admin' || $r === 'super_admin';
}

/**
 * Middleware: tolak akses bila peran user di sesi bukan termasuk $roles.
 * Panggil SETELAH requireLogin() + authz_guard($db). Contoh:
 *   requireRole($db, ['super_admin']);
 *   requireRole($db, ['super_admin', 'admin']);
 */
function requireRole(PDO $db, array $roles): void {
    if (!isLoggedIn()) redirect('index.php?route=login');
    $r = userRole($db);
    if (!in_array($r, $roles, true)) {
        flash('Anda tidak punya hak akses untuk halaman tersebut.', 'error');
        redirect('index.php?route=dashboard');
    }
}

// Cek peran minimum: super_admin > admin > guru
function hasRoleAtLeast(PDO $db, string $minRole): bool {
    $rank = ['guru' => 1, 'admin' => 2, 'super_admin' => 3];
    $r = userRole($db);
    return ($rank[$r] ?? 0) >= ($rank[$minRole] ?? 99);
}

/** Kuota anak per user (0 = tanpa batas). */
function kuotaAnak(PDO $db): int {
    $u = authz_user($db);
    return $u ? max(0, (int)($u['kuota_anak'] ?? 0)) : 0;
}

/** Sisa kuota membuat anak; null = unlimited. */
function sisaKuotaAnak(PDO $db): ?int {
    $q = kuotaAnak($db);
    if ($q <= 0) return null;
    $uid = currentUserId($db);
    $st = $db->prepare('SELECT COUNT(*) c FROM anak WHERE user_id = ?');
    $st->execute([$uid]);
    return max(0, $q - (int)$st->fetch()['c']);
}

// 'owner' | 'editor' | 'viewer' | null (tidak ada akses)
function anakAccess(PDO $db, int $anakId): ?string {
    if ($anakId <= 0) return null;
    $u = authz_user($db);
    if (!$u) return null;
    if (isAdmin($db)) return 'owner';

    $st = $db->prepare('SELECT user_id FROM anak WHERE id = ?');
    $st->execute([$anakId]);
    $a = $st->fetch();
    if (!$a) return null;
    if ((int)$a['user_id'] === (int)$u['id']) return 'owner';

    $st = $db->prepare('SELECT role FROM share WHERE anak_id = ? AND user_id = ?');
    $st->execute([$anakId, (int)$u['id']]);
    $s = $st->fetch();
    if ($s) return ($s['role'] === 'viewer') ? 'viewer' : 'editor';
    return null;
}

function canEditAnak(PDO $db, int $anakId): bool {
    $a = anakAccess($db, $anakId);
    return $a === 'owner' || $a === 'editor';
}

// Filter kueri daftar anak: admin/super_admin = semua, guru = milik sendiri + yang dibagikan
// Kembalikan ['all'=>bool, 'where'=>string (pakai alias a), 'params'=>array]
function anakScope(PDO $db): array {
    $u = authz_user($db);
    if (!$u || isAdmin($db)) {
        return ['all' => true, 'where' => '1=1', 'params' => []];
    }
    $uid = (int)$u['id'];
    return [
        'all'    => false,
        'where'  => '(a.user_id = ? OR a.id IN (SELECT anak_id FROM share WHERE user_id = ?))',
        'params' => [$uid, $uid],
    ];
}

// Potong teks aman untuk ringkasan revisi
function authz_trunc(string $s, int $len = 60): string {
    $s = trim(preg_replace('/\s+/', ' ', $s));
    if ($s === '') return '(kosong)';
    if (function_exists('mb_substr')) return mb_substr($s, 0, $len, 'UTF-8');
    return strlen($s) > $len ? substr($s, 0, $len - 1) . '…' : $s;
}

// Bandingkan field lama vs baru → "Label: lama → baru; ..."
function revisiDiff(array $old, array $new, array $fields, array $labels = []): string {
    $parts = [];
    foreach ($fields as $f) {
        $o = (string)($old[$f] ?? '');
        $n = (string)($new[$f] ?? '');
        if ($o === $n) continue;
        $parts[] = ($labels[$f] ?? $f) . ': ' . authz_trunc($o, 40) . ' → ' . authz_trunc($n, 40);
    }
    return implode('; ', $parts);
}

function logRevisi(PDO $db, int $anakId, string $tabel, string $rowKey, string $aksi, string $ringkasan): void {
    if ($ringkasan === '') return;
    if (function_exists('mb_substr')) $ringkasan = mb_substr($ringkasan, 0, 500, 'UTF-8');
    else $ringkasan = substr($ringkasan, 0, 500);
    $db->prepare('INSERT INTO revisi (anak_id, tabel, row_key, user_id, aksi, ringkasan, created_at)
                  VALUES (?, ?, ?, ?, ?, ?, ?)')
       ->execute([$anakId, $tabel, $rowKey, currentUserId($db), $aksi, $ringkasan, date('Y-m-d H:i:s')]);
}

function shareRoleLabel(string $role): string {
    return $role === 'viewer' ? 'Lihat saja' : 'Editor';
}

function roleLabel(string $role): string {
    return match ($role) {
        'super_admin' => 'Super Admin',
        'admin'       => 'Admin',
        default       => 'Guru',
    };
}
