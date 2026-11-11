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

// Panggil sekali setelah requireLogin(): pastikan akun di sesi masih ada
function authz_guard(PDO $db): void {
    if (!isLoggedIn()) return;
    if (!authz_user($db)) {
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

function isAdmin(PDO $db): bool {
    $u = authz_user($db);
    return $u && (($u['role'] ?? '') === 'admin');
}

// 'owner' | 'editor' | 'viewer' | null (tidak ada akses)
function anakAccess(PDO $db, int $anakId): ?string {
    if ($anakId <= 0) return null;
    $u = authz_user($db);
    if (!$u) return null;
    if (($u['role'] ?? '') === 'admin') return 'owner';

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

// Filter kueri daftar anak: admin = semua, guru = milik sendiri + yang dibagikan
// Kembalikan ['all'=>bool, 'where'=>string (pakai alias a), 'params'=>array]
function anakScope(PDO $db): array {
    $u = authz_user($db);
    if (!$u || ($u['role'] ?? '') === 'admin') {
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
