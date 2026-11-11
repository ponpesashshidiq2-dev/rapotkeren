<?php
/**
 * ============================================================
 *  MODUL KOMPRESI GAMBAR OTOMATIS (GD Library)
 * ============================================================
 *  Fungsi:
 *   1. Validasi ketat file upload (ekstensi + MIME nyata + batas ukuran)
 *   2. Auto-resize dimensi maksimal (default sisi terpanjang 1200 px)
 *   3. Kompresi kualitas bertingkat (mulai 82 → turun s/d 70) agar
 *      hasil tajam namun ukuran file masuk target 100–150 KB
 *   4. Menjaga transparansi PNG/WebP/GIF (kanal alpha tidak rusak),
 *      output utama WebP (mendukung alpha + paling hemat ukuran)
 *   5. Fallback aman: tanpa WebP → PNG (alpha) / JPEG (tanpa alpha)
 *
 *  Dipanggil otomatis oleh uploadFile() di config.php untuk semua
 *  upload gambar aplikasi: foto kegiatan, pasfoto, logo, background.
 * ============================================================
 */

// ---------- Konfigurasi (boleh dioverride dengan define() sebelum require) ----------
if (!defined('IMG_MAX_DIM'))       define('IMG_MAX_DIM', 1200);         // sisi terpanjang maksimum hasil resize (px)
if (!defined('IMG_QUALITY'))       define('IMG_QUALITY', 82);           // kualitas awal encoding (rentang disarankan 80–85)
if (!defined('IMG_MIN_QUALITY'))   define('IMG_MIN_QUALITY', 70);       // batas bawah kualitas agar tidak pecah
if (!defined('IMG_TARGET_MAX_KB')) define('IMG_TARGET_MAX_KB', 150);    // batas atas ukuran file hasil kompres (KB)
if (!defined('IMG_MAX_UPLOAD'))    define('IMG_MAX_UPLOAD', 5 * 1024 * 1024); // batas ukuran file upload mentah (5 MB)
if (!defined('IMG_SHRINK_STEP'))   define('IMG_SHRINK_STEP', 0.9);      // faktor penyusutan dimensi tiap iterasi (10% per langkah)
if (!defined('IMG_MIN_DIM'))       define('IMG_MIN_DIM', 640);          // batas bawah sisi terpanjang saat menyusut
if (!defined('IMG_MAX_PIXELS'))    define('IMG_MAX_PIXELS', 12000);     // toleransi dimensi mentah anti "decompression bomb"

// ---------- Daftar format yang diizinkan ----------
// Ekstensi yang diterima dari nama file yang diunggah
define('IMG_ALLOWED_EXT', ['jpg', 'jpeg', 'png', 'webp', 'gif']);
// MIME nyata (dibaca dari isi file, bukan tebakan ekstensi) yang diterima
define('IMG_ALLOWED_MIME', ['image/jpeg', 'image/png', 'image/webp', 'image/gif']);

/**
 * Validasi file gambar mentah sebelum diproses.
 * Menolak: ekstensi tak dikenal, file bukan gambar sungguhan,
 *          melebihi batas ukuran, dan dimensi mencurigakan.
 *
 * @param string $tmpPath   path file sementara hasil upload ($_FILES[...]['tmp_name'])
 * @param string $origName  nama file asli dari pengguna (untuk cek ekstensi)
 * @param int    $maxBytes  batas ukuran maksimum dalam byte
 * @return array|null       ['mime','width','height','bytes'] jika valid, null jika ditolak
 */
function imgValidate(string $tmpPath, string $origName, int $maxBytes = IMG_MAX_UPLOAD): ?array
{
    // 1) Tolak jika file sementara tidak ada atau gagal diunggah
    if ($tmpPath === '' || !is_file($tmpPath)) {
        return null;
    }

    // 2) Ambil ekstensi dari nama file asli lalu seragamkan huruf besar/kecil
    $ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION));

    // 3) Tolak ekstensi di luar daftar putih (mencegah .php/.phtml/.svg disisipkan)
    if (!in_array($ext, IMG_ALLOWED_EXT, true)) {
        return null;
    }

    // 4) Baca ukuran file mentah (byte) dan pastikan tidak melebihi batas
    $size = filesize($tmpPath);
    if ($size === false || $size <= 0 || $size > $maxBytes) {
        return null;
    }

    // 5) Baca CIRI NYATA file (MIME + dimensi) dari isi binernya,
    //    sehingga file sampah bernama .jpg tetap tertolak
    $info = @getimagesize($tmpPath);
    if ($info === false) {
        return null;
    }

    // 6) Tolak jika MIME hasil pembacaan bukan gambar yang diizinkan
    if (!in_array($info['mime'], IMG_ALLOWED_MIME, true)) {
        return null;
    }

    // 7) Tolak dimensi nol / negatif / terlalu besar (anti decompression bomb)
    if ($info[0] < 1 || $info[1] < 1 || $info[0] > IMG_MAX_PIXELS || $info[1] > IMG_MAX_PIXELS) {
        return null;
    }

    // 8) Semua lolos — kembalikan metadata hasil validasi
    return [
        'mime'   => $info['mime'],         // MIME nyata, mis. 'image/png'
        'width'  => (int)$info[0],         // lebar asli (px)
        'height' => (int)$info[1],         // tinggi asli (px)
        'bytes'  => (int)$size,            // ukuran file mentah (byte)
        'ext'    => $ext,                  // ekstensi asli dari nama file
    ];
}

/**
 * Deteksi apakah gambar PNG punya kanal alpha (transparan)
 * dengan membaca byte color-type pada header IHDR (tanpa memindai piksel).
 * Color type 4 = grayscale+alpha, 6 = RGBA → keduanya transparan.
 */
function imgPngHasAlpha(string $path): bool
{
    // Buka file secara binary
    $fh = @fopen($path, 'rb');
    if (!$fh) {
        return false; // gagal dibuka → anggap tidak transparan (aman)
    }
    // Posisi byte ke-25 = field Color Type pada chunk IHDR PNG
    // (8 signature + 4 panjang + 4 'IHDR' + 4 lebar + 4 tinggi + 1 bit-depth)
    fseek($fh, 25);
    $colorType = ord(fread($fh, 1)); // baca 1 byte color type
    fclose($fh);                     // tutup file
    return $colorType === 4 || $colorType === 6; // 4/6 = memuat alpha
}

/**
 * Deteksi alpha untuk GIF (warna transparan tunggal dari palet).
 * $im harus gambar hasil imagecreatefromgif().
 */
function imgGifHasAlpha($im): bool
{
    // imagecolortransparent mengembalikan indeks warna transparan, atau -1 bila tidak ada
    return imagecolortransparent($im) >= 0;
}

/**
 * Deteksi alpha untuk WebP dengan menyampel 4 sudut gambar.
 * (WebP tidak punya header alpha yang mudah dibaca tanpa decode penuh.)
 */
function imgWebpHasAlpha($im): bool
{
    // Ukuran gambar
    $w = imagesx($im);
    $h = imagesy($im);
    // Titik sampel: 4 sudut (dibatasi agar tidak keluar bidang)
    $points = [[0, 0], [$w - 1, 0], [0, $h - 1], [$w - 1, $h - 1]];
    foreach ($points as [$x, $y]) {
        $rgba = imagecolorat($im, $x, $y);          // ambil nilai warna piksel
        $alpha = ($rgba >> 24) & 0x7F;              // 7 bit alpha (0 = opaque, 127 = transparan)
        if ($alpha > 0) {
            return true;                            // ada sudut transparan → anggap punya alpha
        }
    }
    return false; // semua sudut opaque
}

/**
 * Muat gambar dari file ke resource GD berdasarkan MIME nyatanya.
 * Mengembalikan resource gambar, atau null jika format tak didukung/gagal decode.
 */
function imgLoad(string $path, string $mime)
{
    switch ($mime) {
        case 'image/jpeg':
            return @imagecreatefromjpeg($path); // decode JPEG
        case 'image/png':
            return @imagecreatefrompng($path);  // decode PNG (alpha ikut termuat)
        case 'image/webp':
            return @imagecreatefromwebp($path); // decode WebP (alpha ikut termuat)
        case 'image/gif':
            return @imagecreatefromgif($path);  // decode GIF (bingkai pertama)
    }
    return null; // MIME di luar daftar putih
}

/**
 * Buat kanvas baru; jika $hasAlpha, siapkan kanal alpha agar transparansi
 * tidak berubah jadi hitam/putih saat disalin.
 */
function imgCreateCanvas(int $w, int $h, bool $hasAlpha)
{
    $canvas = imagecreatetruecolor($w, $h);         // kanvas RGB 24-bit
    if ($hasAlpha) {
        imagealphablending($canvas, false);         // matikan blending agar alpha disalin apa adanya
        imagesavealpha($canvas, true);              // aktifkan penyimpanan alpha pada kanvas
        $transparent = imagecolorallocatealpha($canvas, 0, 0, 0, 127); // warna transparan penuh
        imagefilledrectangle($canvas, 0, 0, $w, $h, $transparent);     // isi seluruh kanvas dgn transparan
    }
    return $canvas;
}

/**
 * Resize gambar agar sisi terpanjangnya tidak melebihi $maxDim px.
 * Rasio aspek dijaga; TIDAK pernah memperbesar (upscale) gambar kecil.
 * Gambar sumber dihancurkan (imagedestroy) untuk membebaskan memori.
 *
 * @return resource kanvas hasil resize (atau resource sumber jika tidak perlu resize)
 */
function imgResize($src, int $maxDim, bool $hasAlpha)
{
    $w = imagesx($src); // lebar sumber
    $h = imagesy($src); // tinggi sumber
    $longest = max($w, $h); // sisi terpanjang sumber

    // Jika sudah dalam batas → kembalikan apa adanya (tanpa upscale)
    if ($longest <= $maxDim) {
        return $src;
    }

    // Hitung skala penyusutan agar proporsional
    $scale = $maxDim / $longest;
    $nw = max(1, (int)round($w * $scale)); // lebar baru (minimal 1 px)
    $nh = max(1, (int)round($h * $scale)); // tinggi baru (minimal 1 px)

    // Buat kanvas tujuan (dengan perlakuan alpha yang sama seperti sumber)
    $dst = imgCreateCanvas($nw, $nh, $hasAlpha);

    // Salin + resample (interpolasi bicubik-ish GD) → hasil halus, tidak pecah
    imagecopyresampled($dst, $src, 0, 0, 0, 0, $nw, $nh, $w, $h);

    imagedestroy($src); // bebaskan memori sumber
    return $dst;        // kembalikan kanvas hasil resize
}

/**
 * Flatten alpha ke latar putih — hanya dipakai bila output DIPAKSA JPEG
 * (mis. sistem lama yang tidak menerima WebP/PNG transparan).
 * Alpha 100% → putih murni; semi-transparan → campuran putih.
 */
function imgFlattenToWhite($im): void
{
    $w = imagesx($im); // lebar gambar
    $h = imagesy($im); // tinggi gambar

    $flat = imagecreatetruecolor($w, $h);           // kanvas opak baru
    $white = imagecolorallocate($flat, 255, 255, 255); // warna putih
    imagefill($flat, 0, 0, $white);                 // seluruh kanvas = putih

    imagealphablending($flat, true);                // aktifkan blending (gambar menimpa putih dgn alpha)
    imagecopy($flat, $im, 0, 0, 0, 0, $w, $h);      // salin gambar ber-alpha di atas putih

    imagealphablending($im, true);                  // ubah kanvas asli jadi mode opak
    imagecopy($im, $flat, 0, 0, 0, 0, $w, $h);      // timpa isi asli dgn versi putih
    imagesavealpha($im, false);                     // matikan kanal alpha (sudah putih semua)
    imagedestroy($flat);                            // bebaskan kanvas sementara
}

/**
 * Encode resource GD ke string biner sesuai format + kualitas.
 *  - WebP/JPEG : parameter $q = kualitas 0–100 (82 = tajam & hemat)
 *  - PNG       : parameter ke-3 = level kompresi zlib 0–9 (9 = paling kecil)
 */
function imgEncode($im, string $format, int $q): string
{
    ob_start(); // mulai penampung output ke memori (bukan langsung ke layar)
    if ($format === 'webp') {
        imagewebp($im, null, $q);   // encode WebP dengan kualitas $q
    } elseif ($format === 'jpeg') {
        imagejpeg($im, null, $q);   // encode JPEG dengan kualitas $q
    } else {
        imagepng($im, null, 9);     // encode PNG level 9 (kualitas PNG lossless)
    }
    return (string)ob_get_clean();  // ambil hasil biner dari penampung
}

/**
 * Pilih format output terbaik:
 *  - Ada alpha  → WebP (jaga transparansi + paling kecil); fallback PNG
 *  - Tanpa alpha → WebP (paling kecil); fallback JPEG
 *  - $forceFormat = 'jpeg' → JPEG (alpha di-flatten ke putih dulu)
 */
function imgChooseFormat(bool $hasAlpha, string $forceFormat = ''): string
{
    // Jika dipaksa JPEG, taati (alpha akan di-flatten terpisah)
    if ($forceFormat === 'jpeg') {
        return 'jpeg';
    }
    // WebP didukung GD? → pakai WebP untuk semua kasis
    if (function_exists('imagewebp')) {
        return 'webp';
    }
    // Fallback tanpa WebP: gambar transparan → PNG (menjaga alpha),
    // gambar opak → JPEG (jauh lebih kecil dari PNG)
    return $hasAlpha ? 'png' : 'jpeg';
}

/**
 * ============================================================
 *  FUNGSI UTAMA: Validasi + Resize + Kompresi upload → file final
 * ============================================================
 *
 * @param string $tmpPath  file sementara hasil upload
 * @param string $origName nama file asli pengguna (untuk validasi ekstensi)
 * @param string $destDir  folder tujuan (sudah dipastikan ada oleh pemanggil)
 * @param array  $opts     opsi tambahan:
 *                         - max_dim   : override batas sisi terpanjang (px)
 *                         - quality   : override kualitas awal (0–100)
 *                         - max_bytes : override batas ukuran hasil (byte)
 *                         - format    : paksa 'jpeg' (alpha → putih) / 'png' / 'webp'
 *                         - max_upload: override batas ukuran file mentah (byte)
 * @return array|null      ['file','bytes','orig_bytes','width','height','quality','format']
 *                         atau null jika file ditolak/gagal diproses
 */
function imgCompress(string $tmpPath, string $origName, string $destDir, array $opts = []): ?array
{
    // ---------- 1. Validasi ketat file mentah ----------
    $maxUpload = (int)($opts['max_upload'] ?? IMG_MAX_UPLOAD);       // batas upload mentah
    $valid = imgValidate($tmpPath, $origName, $maxUpload);           // validasi ekstensi+MIME+ukuran
    if ($valid === null) {
        return null; // file sampah / bukan gambar / terlalu besar → tolak
    }

    // ---------- 2. Muat gambar ke resource GD ----------
    $src = imgLoad($tmpPath, $valid['mime']); // decode sesuai MIME nyata
    if ($src === null) {
        return null; // format tak didukung atau file korup → tolak
    }

    // ---------- 3. Deteksi transparansi (alpha) ----------
    if ($valid['mime'] === 'image/png') {
        $hasAlpha = imgPngHasAlpha($tmpPath);        // PNG: baca header IHDR (akurat & cepat)
    } elseif ($valid['mime'] === 'image/gif') {
        $hasAlpha = imgGifHasAlpha($src);            // GIF: cek warna transparan palet
    } elseif ($valid['mime'] === 'image/webp') {
        $hasAlpha = imgWebpHasAlpha($src);           // WebP: sampel 4 sudut
    } else {
        $hasAlpha = false;                           // JPEG tidak punya alpha
    }

    // ---------- 4. Resize otomatis agar maksimum sisi terpanjang ----------
    $maxDim = (int)($opts['max_dim'] ?? IMG_MAX_DIM); // batas dimensi (default 1200 px)
    $src = imgResize($src, $maxDim, $hasAlpha);       // resize proporsional tanpa upscale

    // ---------- 5. Tentukan format output ----------
    $forceFormat = (string)($opts['format'] ?? '');   // opsi paksa format (default: bebas)
    $format = imgChooseFormat($hasAlpha, $forceFormat); // pilih WebP/PNG/JPEG terbaik

    // Jika dipaksa JPEG tapi sumber transparan → flatten ke latar putih dulu
    if ($format === 'jpeg' && $hasAlpha) {
        imgFlattenToWhite($src);
        $hasAlpha = false; // setelah flatten tidak ada alpha lagi
    }

    // ---------- 6. Kompresi kualitas bertingkat menuju target ≤ 150 KB ----------
    $maxBytes = (int)($opts['max_bytes'] ?? IMG_TARGET_MAX_KB * 1024); // batas ukuran hasil (byte)
    $baseQ = (int)($opts['quality'] ?? IMG_QUALITY);                   // kualitas awal (82)

    // Urutan kualitas dicoba dari yang tertinggi: 82 → 78 → 75 → 72 → 70
    $qualitySteps = array_values(array_unique([$baseQ, 78, 75, 72, IMG_MIN_QUALITY]));

    $best = '';          // biner hasil encoding terbaik (paling kecil yang lolos batas)
    $bestQ = $baseQ;     // kualitas yang menghasilkan $best
    $curW = imagesx($src); // lebar gambar saat ini
    $curH = imagesy($src); // tinggi gambar saat ini

    // Lingkaran luar: turunkan kualitas dulu, baru turunkan dimensi bila masih > 150 KB
    while (true) {
        foreach ($qualitySteps as $q) {            // coba tiap tingkat kualitas
            $bin = imgEncode($src, $format, $q);   // encode ke memori dengan kualitas $q
            if ($best === '' || strlen($bin) < strlen($best)) {
                $best = $bin;                      // simpan hasil terkecil sejauh ini
                $bestQ = $q;
            }
            if (strlen($bin) <= $maxBytes) {       // sudah ≤ target 150 KB?
                break 2;                           // ya → selesai, pakai hasil ini
            }
        }

        // Semua kualitas masih > 150 KB → coba susutkan dimensi 10% per langkah
        $longest = max($curW, $curH);
        if ($longest <= IMG_MIN_DIM) {
            break; // sudah batas bawah (640 px) → terima hasil terkecil yang ada
        }

        // Hitung dimensi baru (faktor 0.9), jangan sampai di bawah IMG_MIN_DIM
        $scale = max(IMG_MIN_DIM / $longest, IMG_SHRINK_STEP);
        $nw = max(IMG_MIN_DIM, (int)round($curW * $scale));
        $nh = max(IMG_MIN_DIM, (int)round($curH * $scale));

        // Buat kanvas baru berukuran lebih kecil lalu resample isi $src ke dalamnya
        $small = imgCreateCanvas($nw, $nh, $hasAlpha);
        imagecopyresampled($small, $src, 0, 0, 0, 0, $nw, $nh, $curW, $curH);
        imagedestroy($src);   // bebaskan kanvas lama
        $src = $small;        // lanjutkan dengan kanvas menyusut
        $curW = $nw;          // perbarui lebar aktif
        $curH = $nh;          // perbarui tinggi aktif

        // Kualitas untuk iterasi penyusutan: mulai dari yang sedang (75)
        $qualitySteps = array_values(array_unique([75, 72, IMG_MIN_QUALITY]));
    }

    // Jika hasil encoding kosong (kasus mustahil), gagal aman
    if ($best === '') {
        imagedestroy($src);
        return null;
    }

    // ---------- 7. Tentukan nama file final (acak, ekstensi = format hasil) ----------
    $extOut = $format === 'jpeg' ? 'jpg' : $format;                 // jpeg → .jpg, webp → .webp, png → .png
    $fileName = bin2hex(random_bytes(16)) . '.' . $extOut;          // nama acak 32 hex (anti tebakan/brute-force)
    $destPath = rtrim($destDir, '/\\') . DIRECTORY_SEPARATOR . $fileName; // path tujuan lengkap

    // ---------- 8. Tulis hasil kompresi ke disk ----------
    if (file_put_contents($destPath, $best) === false) {
        imagedestroy($src);
        return null; // gagal menulis (izin folder dsb.) → tolak
    }

    // ---------- 9. Bebaskan memori & kembalikan metadata ----------
    imagedestroy($src);

    return [
        'file'       => $fileName,                     // nama file final di folder tujuan
        'path'       => $destPath,                     // path lengkap final
        'bytes'      => strlen($best),                 // ukuran file hasil kompres (byte)
        'orig_bytes' => $valid['bytes'],               // ukuran file mentah sebelum kompres (byte)
        'width'      => $curW,                         // lebar final (px)
        'height'     => $curH,                         // tinggi final (px)
        'quality'    => $bestQ,                        // kualitas encoding yang terpakai
        'format'     => $format,                       // format output: webp/png/jpeg
    ];
}
