<?php
// Halaman cetak gabungan — tanpa layout app, khusus print
$anakId = (int)($_GET['anak_id'] ?? 0);
$st = $db->prepare('SELECT * FROM anak WHERE id = ?');
$st->execute([$anakId]);
$anak = $st->fetch();
if (!$anak) redirect('index.php?route=cetak');

$profil = getProfil($db);
$cps = $db->query('SELECT * FROM cp ORDER BY urutan, id')->fetchAll();

// centang
$checked = [];
$st = $db->prepare('SELECT capaian_id FROM penilaian WHERE anak_id = ?');
$st->execute([$anakId]);
foreach ($st->fetchAll() as $r) $checked[(int)$r['capaian_id']] = true;

// manual override
$manual = [];
$st = $db->prepare('SELECT sub_cp_id, teks FROM deskripsi WHERE anak_id = ?');
$st->execute([$anakId]);
foreach ($st->fetchAll() as $r) $manual[(int)$r['sub_cp_id']] = $r['teks'];

$panggilan = $anak['nama_panggilan'] ?: $anak['nama_lengkap'];

function autoText($subId, $checked, $db) {
    $st = $db->prepare('SELECT id, teks FROM capaian WHERE sub_cp_id = ? ORDER BY urutan, id');
    $st->execute([$subId]);
    $picked = [];
    foreach ($st->fetchAll() as $r) {
        if (!empty($checked[(int)$r['id']])) $picked[] = $r['teks'];
    }
    if (!$picked) return '';
    $n = count($picked);
    if ($n === 1) return $picked[0];
    $last = array_pop($picked);
    return implode(', ', $picked) . ', dan ' . $last;
}

function usia($tgl) {
    if (!$tgl) return '-';
    try {
        $d = new DateTime($tgl);
        $now = new DateTime();
        $diff = $now->diff($d);
        if ($diff->y > 0) return $diff->y . ' tahun ' . $diff->m . ' bulan';
        return $diff->m . ' bulan';
    } catch (Exception $e) { return '-'; }
}

function listNamaAlamat($profil) {
    $parts = [];
    foreach (['alamat','rt_rw','desa','kecamatan','kabupaten','provinsi'] as $f) {
        if (!empty($profil[$f])) $parts[] = $profil[$f];
    }
    return implode(', ', $parts);
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<title>Raport - <?= e($anak['nama_lengkap']) ?></title>
<link rel="stylesheet" href="assets/print.css">
</head>
<body>

<div class="print-toolbar no-print">
    <button onclick="window.print()">🖨 Cetak Semua</button>
    <a href="index.php?route=cetak">&larr; Kembali</a>
    <span>Raport <?= e($anak['nama_lengkap']) ?> — <?= count($cps) + 3 ?> halaman</span>
</div>

<!-- ============ 1. COVER / IDENTITAS SATUAN PENDIDIKAN ============ -->
<section class="page">
    <div class="page-inner">
        <div class="head-bar">✿ &nbsp; LAPORAN PERKEMANGBANGAN ANAK DIDIK &nbsp; ✿</div>

        <h1 class="cover-title">✿ DATA SATUAN PENDIDIKAN ✿</h1>
        <h2 class="cover-sub">IDENTITAS SEKOLAH</h2>

        <div class="logo-wrap">
            <?php if (!empty($profil['logo'])): ?>
                <img src="<?= e($profil['logo']) ?>" alt="Logo">
            <?php else: ?>
                <div class="logo-placeholder">LOGO</div>
            <?php endif; ?>
        </div>

        <div class="band band-blue">IDENTITAS SATUAN PENDIDIKAN</div>
        <table class="identitas">
            <?php
            $rows = [
                'Nama Sekolah / Satuan PAUD' => $profil['nama_sekolah'] ?? '',
                'NPSN' => $profil['npsn'] ?? '',
                'Status Sekolah' => $profil['status'] ?? '',
                'Jenjang / Bentuk Satuan' => $profil['jenjang'] ?? '',
                'Alamat Jalan' => $profil['alamat'] ?? '',
                'RT / RW' => $profil['rt_rw'] ?? '',
                'Desa / Kelurahan' => $profil['desa'] ?? '',
                'Kecamatan' => $profil['kecamatan'] ?? '',
                'Kabupaten / Kota' => $profil['kabupaten'] ?? '',
                'Provinsi' => $profil['provinsi'] ?? '',
                'Kode Pos' => $profil['kode_pos'] ?? '',
                'Nomor Telepon' => $profil['telp'] ?? '',
                'Email Sekolah' => $profil['email'] ?? '',
                'Nama Kepala Sekolah' => $profil['kepala_sekolah'] ?? '',
                'Nama Guru Kelompok' => $profil['guru_kelompok'] ?? '',
            ];
            foreach ($rows as $label => $val): ?>
            <tr><td class="lbl"><?= e($label) ?></td><td class="colon">:</td><td><?= e($val) ?></td></tr>
            <?php endforeach; ?>
        </table>

        <div class="pemisah">- - - - - - Pemisah - - - - - -</div>
        <div class="footer"><?= e($profil['footer'] ?? 'SAHABAT GURU') ?></div>
    </div>
</section>

<!-- ============ 2. DATA PESERTA DIDIK ============ -->
<section class="page">
    <div class="page-inner">
        <div class="head-bar">✿ &nbsp; LAPORAN PERKEMANGBANGAN ANAK DIDIK &nbsp; ✿</div>

        <h1 class="cover-title">✿ DATA PESERTA DIDIK ✿</h1>
        <h2 class="cover-sub">KETERANGAN DIRI ANAK</h2>

        <div class="band band-pink">DATA ANAK</div>
        <table class="identitas">
            <?php
            $rows = [
                'Nama lengkap' => $anak['nama_lengkap'],
                'Nama panggilan' => $anak['nama_panggilan'],
                'NISN / NIS' => $anak['nisn'],
                'Tempat, tanggal lahir' => trim($anak['tempat_lahir'] . ', ' . $anak['tanggal_lahir'], ', '),
                'Jenis kelamin' => $anak['jenis_kelamin'],
                'Agama' => $anak['agama'],
                'Anak ke-' => $anak['anak_ke'],
                'Jumlah saudara' => $anak['jumlah_saudara'],
                'Kelompok / rombel' => $anak['kelompok'],
            ];
            foreach ($rows as $label => $val): ?>
            <tr><td class="lbl"><?= e($label) ?></td><td class="colon">:</td><td><?= e($val) ?></td></tr>
            <?php endforeach; ?>
        </table>

        <div class="band band-pink">DATA ORANG TUA / WALI</div>
        <table class="identitas">
            <?php
            $rows = [
                'Nama ayah' => $anak['nama_ayah'],
                'Pekerjaan ayah' => $anak['pekerjaan_ayah'],
                'Nama ibu' => $anak['nama_ibu'],
                'Pekerjaan ibu' => $anak['pekerjaan_ibu'],
                'Nama wali (jika ada)' => $anak['nama_wali'],
            ];
            foreach ($rows as $label => $val): ?>
            <tr><td class="lbl"><?= e($label) ?></td><td class="colon">:</td><td><?= e($val) ?></td></tr>
            <?php endforeach; ?>
        </table>

        <div class="band band-yellow">ALAMAT TEMPAT TINGGAL</div>
        <table class="identitas">
            <?php
            $rows = [
                'Jalan / Dusun' => $anak['alamat_jalan'],
                'RT / RW' => $anak['alamat_rt_rw'],
                'Desa / Kelurahan' => $anak['alamat_desa'],
                'Kecamatan' => $anak['alamat_kecamatan'],
                'Kabupaten / Kota' => $anak['alamat_kabupaten'],
                'Provinsi' => $anak['alamat_provinsi'],
            ];
            foreach ($rows as $label => $val): ?>
            <tr><td class="lbl"><?= e($label) ?></td><td class="colon">:</td><td><?= e($val) ?></td></tr>
            <?php endforeach; ?>
        </table>

        <div class="sign-row">
            <div class="photo-34">
                <?php if (!empty($anak['foto'])): ?>
                    <img src="<?= e($anak['foto']) ?>" alt="Foto 3x4">
                <?php else: ?>
                    <span>3x4</span>
                <?php endif; ?>
            </div>
            <div class="sign-right">
                <div class="sign-line">.........................., ..................</div>
                <div class="sign-label">Kepala Satuan PAUD</div>
                <div class="sign-name">(<?= e($profil['kepala_sekolah'] ?: '..........................') ?>)</div>
            </div>
        </div>

        <div class="pemisah">- - - - - - Pemisah - - - - - -</div>
        <div class="footer"><?= e($profil['footer'] ?? 'SAHABAT GURU') ?></div>
    </div>
</section>

<!-- ============ 3..7. HALAMAN CP ============ -->
<?php foreach ($cps as $cp): ?>
<section class="page">
    <div class="page-inner">
        <div class="head-bar">✿ &nbsp; LAPORAN PERKEMANGBANGAN ANAK DIDIK &nbsp; ✿</div>

        <table class="meta">
            <tr>
                <td>Nama Anak : <?= e($anak['nama_lengkap']) ?></td>
                <td>Kelompok : <?= e($anak['kelompok']) ?></td>
            </tr>
            <tr>
                <td>Usia : <?= e(usia($anak['tanggal_lahir'])) ?></td>
                <td>Tahun Ajaran : <?= e($profil['tahun_ajaran'] ?? '-') ?></td>
            </tr>
        </table>

        <h1 class="cp-title">✿ <?= e(strtoupper($cp['nama'])) ?> ✿</h1>

        <?php
        $subs = $db->prepare('SELECT * FROM sub_cp WHERE cp_id = ? ORDER BY urutan, id');
        $subs->execute([$cp['id']]);
        $subs = $subs->fetchAll();
        $color = 0;
        foreach ($subs as $sub):
            $color++;
            $pink = ($color % 2 === 1);
            $auto = autoText((int)$sub['id'], $checked, $db);
            $text = trim($manual[(int)$sub['id']] ?? '');
            if ($text === '' && $auto !== '') $text = 'Ananda ' . $panggilan . ' ' . $auto . '.';
        ?>
        <div class="block <?= $pink ? 'block-pink' : 'block-blue' ?>">
            <div class="block-head"><?= e($sub['nama']) ?></div>
            <div class="block-body">
                <?php if ($text): ?>
                    <?= e($text) ?>
                <?php else: ?>
                    <span class="muted-print">Belum ada capaian yang dicentang untuk sub-bidang ini.</span>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>

        <?php
        $fotos = [];
        $st = $db->prepare('SELECT * FROM foto WHERE anak_id = ? AND cp_id = ? ORDER BY urutan');
        $st->execute([$anakId, $cp['id']]);
        foreach ($st->fetchAll() as $r) $fotos[(int)$r['urutan']] = $r;
        ?>
        <h3 class="doc-title">✿ DOKUMENTASI KEGIATAN ANAK ✿</h3>
        <div class="foto-row">
            <?php for ($i = 1; $i <= 2; $i++): $f = $fotos[$i] ?? null; ?>
            <div class="foto-cell">
                <div class="foto-img">
                    <?php if (!empty($f['file'])): ?>
                        <img src="<?= e($f['file']) ?>" alt="Foto kegiatan">
                    <?php else: ?>
                        <span class="muted-print">foto kegiatan</span>
                    <?php endif; ?>
                </div>
                <div class="foto-ket">Keterangan: <?= e($f['keterangan'] ?? '-') ?></div>
            </div>
            <?php endfor; ?>
        </div>

        <div class="footer"><?= e($profil['footer'] ?? 'SAHABAT GURU') ?></div>
    </div>
</section>
<?php endforeach; ?>

<!-- ============ LEMBAR PENGESAHAN ============ -->
<section class="page">
    <div class="page-inner">
        <div class="head-bar">✿ &nbsp; LAPORAN PERKEMANGBANGAN ANAK DIDIK &nbsp; ✿</div>

        <h1 class="cover-title">✿ LEMBAR PENGESAHAN ✿</h1>
        <h1 class="cover-title">✿ LAPORAN PERKEMANGBANGAN ANAK DIDIK ✿</h1>

        <table class="pengesahan-meta">
            <tr>
                <td>Nama Anak: <?= e($anak['nama_lengkap']) ?></td>
                <td>Kelompok: <?= e($anak['kelompok'] ?: '..............................') ?></td>
            </tr>
            <tr>
                <td>Tahun Ajaran: <?= e($profil['tahun_ajaran'] ?? '..............................') ?></td>
                <td>Semester: <?= e($profil['semester'] ?? '................') ?></td>
            </tr>
        </table>

        <p class="pengesahan-text">
            Laporan perkembangan ini telah dibaca dan diketahui oleh orang tua/wali serta pihak
            satuan pendidikan.
        </p>

        <p class="pengesahan-text">Tempat, tanggal: ............................................................</p>

        <div class="ttd-row">
            <div class="ttd">
                <div class="ttd-role">Orang Tua / Wali</div>
                <div class="ttd-space">(................................................)</div>
            </div>
            <div class="ttd">
                <div class="ttd-role">Guru Kelompok</div>
                <div class="ttd-space">(................................................)</div>
            </div>
        </div>

        <div class="ttd-center">
            <div>Mengetahui,</div>
            <div>Kepala Satuan PAUD / TK</div>
            <div class="ttd-space-lg">(................................................)</div>
            <div class="ttd-name"><?= e($profil['kepala_sekolah'] ?: '..............................') ?></div>
        </div>

        <p class="catatan">
            <em>Catatan: Foto pada bagian dokumentasi merupakan contoh. Guru dapat mengunggah foto
            kegiatan anak melalui menu Penilaian.</em>
        </p>

        <div class="footer"><?= e($profil['footer'] ?? 'SAHABAT GURU') ?></div>
    </div>
</section>

</body>
</html>
