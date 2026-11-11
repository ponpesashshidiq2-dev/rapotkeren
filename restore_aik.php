<?php
// Pulihkan CP AIK yang tidak sengaja terhapus lewat menu Data Capaian.
// Pakai: buka https://domain-anda/restore_aik.php (login dulu), klik tombol pulihkan,
// lalu HAPUS file ini dari hosting setelah selesai.
require_once __DIR__ . '/config.php';

/** Data bawaan CP AIK — sama dengan seed di schema.php. */
function aik_seed_data(): array {
    return [
        'Al-Qur\'an dan Ibadah' => [
            'bersikap jujur dan amanah',
            'saling tolong menolong',
            'peduli terhadap sesama',
            'menjaga kebersihan lingkungan',
            'berperilaku santun',
        ],
        'Keimanan dan akhlak' => [
            'mengenal ciptaan Allah',
            'bersyukur atas nikmat',
            'menunjukkan sikap saling menghormati',
            'menjaga tutur kata',
        ],
        'Pengenalan nilai KeMuhammadiyahan' => [
            'mengenal lambang Muhammadiyah',
            'menyanyi lagu Kemuhammadiyahan',
            'mengenal tokoh Muhammadiyah',
            'mengetahui semangat amal usaha Muhammadiyah',
        ],
        'Keterampilan hidup dasar' => [
            'mengikuti kegiatan sehari-hari',
            'menyiapkan alat belajar sendiri',
            'menyimpan barang dengan rapi',
            'mengikuti aturan kegiatan',
        ],
    ];
}

/**
 * Kembalikan CP AIK (beserta sub-bidang & capaian detail yang hilang).
 * Idempoten: baris yang sudah ada tidak disentuh.
 * @return string[] daftar aksi
 */
function aik_restore(PDO $db): array {
    $log = [];
    $seed = aik_seed_data();

    // Cari CP AIK (nama bisa saja berbeda versi lama)
    $st = $db->prepare("SELECT id FROM cp WHERE nama LIKE '%AL ISLAM%' OR nama LIKE '%KEMUHAMMADIYAHAN%' ORDER BY id LIMIT 1");
    $st->execute();
    $cp = $st->fetch();

    if (!$cp) {
        // Taruh paling depan: urutan = urutan terkecil - 1 (bisa negatif, SQLite tetap valid)
        $minUrut = (int)$db->query('SELECT COALESCE(MIN(urutan), 1) u FROM cp')->fetch()['u'];
        $db->prepare('INSERT INTO cp (urutan, nama) VALUES (?, ?)')
           ->execute([$minUrut - 1, "AL ISLAM, KE'AISYIYAHAN DAN KEMUHAMMADIYAHAN (AIK)"]);
        $cpId = (int)$db->lastInsertId();
        $log[] = "CP AIK dibuat baru (id $cpId, urutan " . ($minUrut - 1) . ").";
    } else {
        $cpId = (int)$cp['id'];
        $log[] = "CP AIK sudah ada (id $cpId) — sub-bidang/capaian yang hilang akan dilengkapi.";
    }

    $insSub = $db->prepare('INSERT INTO sub_cp (cp_id, urutan, nama) VALUES (?, ?, ?)');
    $insCap = $db->prepare('INSERT INTO capaian (sub_cp_id, urutan, teks) VALUES (?, ?, ?)');
    $findSub = $db->prepare('SELECT id FROM sub_cp WHERE cp_id = ? AND nama = ?');
    $findCap = $db->prepare('SELECT 1 FROM capaian WHERE sub_cp_id = ? AND teks = ?');

    $urutSub = 0;
    foreach ($seed as $subNama => $caps) {
        $urutSub++;
        $findSub->execute([$cpId, $subNama]);
        $sub = $findSub->fetch();
        if (!$sub) {
            $insSub->execute([$cpId, $urutSub, $subNama]);
            $subId = (int)$db->lastInsertId();
            $log[] = "Sub-bidang \"$subNama\" ditambahkan.";
        } else {
            $subId = (int)$sub['id'];
        }

        $urutCap = (int)$db->query('SELECT COALESCE(MAX(urutan), 0) u FROM capaian WHERE sub_cp_id = ' . $subId)->fetch()['u'];
        foreach ($caps as $teks) {
            $findCap->execute([$subId, $teks]);
            if (!$findCap->fetch()) {
                $urutCap++;
                $insCap->execute([$subId, $urutCap, $teks]);
                $log[] = "Capaian \"$teks\" ditambahkan.";
            }
        }
    }

    if (!$log) $log[] = 'Tidak ada yang perlu dipulihkan — CP AIK sudah lengkap.';
    return $log;
}

// ---- Mode uji via CLI: php -c php.ini restore_aik.php ----
if (PHP_SAPI === 'cli') {
    $db->beginTransaction();
    try {
        foreach (aik_restore($db) as $l) echo '-', ' ', $l, PHP_EOL;
        $db->commit();
    } catch (Throwable $ex) {
        if ($db->inTransaction()) $db->rollBack();
        echo 'GAGAL: ', $ex->getMessage(), PHP_EOL;
        exit(1);
    }
    exit(0);
}

// ---- Mode browser ----
requireLogin();

$msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $db->beginTransaction();
        $log = aik_restore($db);
        $db->commit();
        $msg = '<div class="alert alert-success"><strong>Selesai.</strong><ul style="margin:6px 0 0 18px">' .
               implode('', array_map(fn($l) => '<li>' . e($l) . '</li>', $log)) .
               '</ul></div>';
    } catch (Throwable $ex) {
        if ($db->inTransaction()) $db->rollBack();
        $msg = '<div class="alert alert-error">Gagal: ' . e($ex->getMessage()) . '</div>';
    }
}

$st = $db->query("SELECT id, urutan, nama FROM cp ORDER BY urutan, id");
$cps = $st->fetchAll();
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Pulihkan CP AIK</title>
<link rel="stylesheet" href="assets/app.css">
</head>
<body style="font-family:'Segoe UI',Arial,sans-serif; background:#eef1f4; margin:0; padding:24px;">
<div style="max-width:760px; margin:0 auto; background:#fff; border:1px solid #d8e0e5; border-radius:12px; padding:24px 28px;">
    <h1 style="font-size:20px; color:#0b3821; margin:0 0 6px;">Pulihkan CP AIK</h1>
    <p style="color:#556; font-size:14px; margin:0 0 16px;">
        Tool darurat: mengembalikan CP <strong>AL ISLAM, KE'AISYIYAHAN DAN KEMUHAMMADIYAHAN (AIK)</strong>
        beserta 4 sub-bidang dan 17 capaian detail yang terhapus dari menu Data Capaian.
        Aman dijalankan berulang (idempoten) — baris yang sudah ada tidak diubah.
    </p>

    <?= $msg ?>

    <h2 style="font-size:15px; color:#0b3821; margin:18px 0 8px;">Status CP saat ini</h2>
    <table style="width:100%; border-collapse:collapse; font-size:13.5px;">
        <tr style="background:#0b3821; color:#fff;">
            <th style="text-align:left; padding:7px 10px; width:40px;">ID</th>
            <th style="text-align:left; padding:7px 10px; width:50px;">Urut</th>
            <th style="text-align:left; padding:7px 10px;">Nama</th>
        </tr>
        <?php foreach ($cps as $i => $cp): ?>
        <tr style="background:<?= $i % 2 ? '#f6faf8' : '#fff' ?>;">
            <td style="padding:6px 10px; border-bottom:1px solid #e3e9ec;"><?= (int)$cp['id'] ?></td>
            <td style="padding:6px 10px; border-bottom:1px solid #e3e9ec;"><?= (int)$cp['urutan'] ?></td>
            <td style="padding:6px 10px; border-bottom:1px solid #e3e9ec; <?= stripos($cp['nama'], 'AL ISLAM') !== false ? 'font-weight:700; color:#187a46;' : '' ?>">
                <?= e($cp['nama']) ?>
                <?php if (stripos($cp['nama'], 'AL ISLAM') !== false): ?><span style="color:#187a46;">✓ ada</span><?php endif; ?>
            </td>
        </tr>
        <?php endforeach; ?>
    </table>

    <form method="post" style="margin-top:20px;">
        <button type="submit" style="background:linear-gradient(135deg,#187a46,#0b3821); color:#fff; border:0; border-radius:8px; padding:11px 22px; font-size:14.5px; font-weight:700; cursor:pointer;">
            Kembalikan CP AIK
        </button>
        <a href="index.php?route=capaian" style="margin-left:12px; font-size:13.5px; color:#187a46; font-weight:600; text-decoration:none;">← Kembali ke Data Capaian</a>
    </form>

    <p style="margin-top:18px; font-size:12.5px; color:#8a949b;">
        Catatan: skor penilaian &amp; foto kegiatan lama CP AIK ikut terhapus permanen saat CP dihapus
        (tidak bisa dikembalikan tanpa file backup) — silakan isi ulang lewat menu Penilaian bila perlu.
        <strong>Hapus file restore_aik.php dari hosting setelah selesai.</strong>
    </p>
</div>
</body>
</html>
