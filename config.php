<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', '1');

define('BASE_DIR', __DIR__);
define('DB_PATH', BASE_DIR . '/data/raport.sqlite');
define('UPLOAD_DIR', BASE_DIR . '/uploads');

$db = new PDO('sqlite:' . DB_PATH);
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
$db->exec('PRAGMA foreign_keys = ON');

require_once __DIR__ . '/schema.php';
// Modul kompresi gambar otomatis (validasi + resize 1200px + quality 82%)
require_once __DIR__ . '/src/image_compress.php';

function e($s) {
    return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}

function redirect($url) {
    header('Location: ' . $url);
    exit;
}

function isLoggedIn() {
    return !empty($_SESSION['user']);
}

function requireLogin() {
    if (!isLoggedIn()) {
        redirect('index.php?route=login');
    }
}

/**
 * Upload gambar dengan kompresi otomatis.
 * - Validasi ketat: ekstensi whitelist + MIME nyata (bukan tebakan nama file)
 *   + batas ukuran 5 MB agar server aman dari file sampah.
 * - Kompresi: resize maksimum sisi terpanjang (default 1200 px) +
 *   quality 82% (rentang 80–85) agar hasil tetap tajam, target ≤ 150 KB.
 * - Transparansi PNG/WebP/GIF dijaga (output WebP ber-alpha).
 *
 * @param string $field    nama field di $_FILES
 * @param string $subdir   subfolder di uploads/
 * @param array  $allowedExt daftar ekstensi yang diizinkan
 * @param array  $opts     opsi kompresi (max_dim, quality, max_bytes, format) → diteruskan ke imgCompress()
 * @return string|null     path relatif 'uploads/...' atau null jika gagal/ditolak
 */
function uploadFile($field, $subdir, $allowedExt = ['jpg', 'jpeg', 'png', 'webp', 'gif'], array $opts = []) {
    // 1) Tolak bila tidak ada file atau kode error upload PHP (kecil, dsb.)
    if (empty($_FILES[$field]['name']) || $_FILES[$field]['error'] !== UPLOAD_ERR_OK) {
        return null;
    }
    $orig = $_FILES[$field]['name'];
    $ext = strtolower(pathinfo($orig, PATHINFO_EXTENSION));
    // 2) Tolak ekstensi di luar daftar putih (sebelum proses apa pun)
    if (!in_array($ext, $allowedExt, true)) {
        return null;
    }
    // 3) Siapkan folder tujuan
    $dir = UPLOAD_DIR . '/' . $subdir;
    if (!is_dir($dir)) {
        mkdir($dir, 0777, true);
    }
    // 4) Validasi + resize + kompresi (menulis file final sendiri, ekstensi bisa berubah .webp)
    $res = imgCompress($_FILES[$field]['tmp_name'], $orig, $dir, $opts);
    if ($res === null) {
        return null; // file ditolak (bukan gambar valid / terlalu besar / gagal proses)
    }
    // 5) Kembalikan path relatif yang sama seperti format lama aplikasi
    return 'uploads/' . $subdir . '/' . $res['file'];
}

function deleteUpload($relPath) {
    if (!$relPath) return;
    $p = BASE_DIR . '/' . $relPath;
    if (strpos($p, UPLOAD_DIR) === 0 && is_file($p)) {
        @unlink($p);
    }
}

/**
 * Ambil profil milik user tertentu (default: user di sesi login).
 * - Anti-IDOR: selalu query by user_id dari sesi/bukan dari request.
 * - Lazy create: bila user belum punya baris profil, buat default dulu.
 * - Halaman login (belum ada sesi): kembalikan profil pertama untuk branding, atau [].
 */
function getProfil(PDO $db, ?int $userId = null): array {
    static $cache = [];
    if ($userId === null) {
        $userId = 0;
        if (!empty($_SESSION['user'])) {
            $st = $db->prepare('SELECT id FROM users WHERE username = ?');
            $st->execute([$_SESSION['user']]);
            $row = $st->fetch();
            if ($row) $userId = (int)$row['id'];
        }
    }

    if ($userId > 0) {
        if (isset($cache[$userId])) return $cache[$userId];
        $st = $db->prepare('SELECT * FROM profil WHERE user_id = ?');
        $st->execute([$userId]);
        $row = $st->fetch();
        if (!$row) {
            // Lazy create baris profil kosong untuk user ini
            try {
                $db->prepare('INSERT INTO profil (user_id) VALUES (?)')->execute([$userId]);
                $st->execute([$userId]);
                $row = $st->fetch();
            } catch (Exception $e) {
                $row = null;
            }
        }
        return $cache[$userId] = $row ?: [];
    }

    // Belum login (halaman login): branding dari profil pertama bila ada
    if (array_key_exists('__public', $cache)) return $cache['__public'];
    $row = $db->query('SELECT * FROM profil WHERE user_id > 0 ORDER BY user_id LIMIT 1')->fetch();
    return $cache['__public'] = $row ?: [];
}

/** Pastikan user punya baris profil; kembalikan id profilnya. */
function ensureProfil(PDO $db, int $userId): int {
    $st = $db->prepare('SELECT id FROM profil WHERE user_id = ?');
    $st->execute([$userId]);
    $row = $st->fetch();
    if ($row) return (int)$row['id'];
    $db->prepare('INSERT INTO profil (user_id) VALUES (?)')->execute([$userId]);
    return (int)$db->lastInsertId();
}

function flash($msg = null, $type = 'success') {
    if ($msg !== null) {
        $_SESSION['flash'] = ['msg' => $msg, 'type' => $type];
        return null;
    }
    if (!empty($_SESSION['flash'])) {
        $f = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $f;
    }
    return null;
}
