<?php
// Halaman cetak raport (A4)
$anakId = (int)($_GET['anak_id'] ?? 0);
$bagian = $_GET['bagian'] ?? 'semua'; // 'semua', 'isi', 'cover_biodata'
if (!in_array($bagian, ['semua', 'isi', 'cover_biodata'], true)) {
    $bagian = 'semua';
}

$st = $db->prepare('SELECT * FROM anak WHERE id = ?');
$st->execute([$anakId]);
$anak = $st->fetch();
if (!$anak) redirect('index.php?route=cetak');

$profil = getProfil($db);
$cps = $db->query('SELECT * FROM cp ORDER BY urutan, id')->fetchAll();

// skor capaian (1-4)
$skors = [];
$st = $db->prepare('SELECT capaian_id, skor FROM penilaian WHERE anak_id = ?');
$st->execute([$anakId]);
foreach ($st->fetchAll() as $r) $skors[(int)$r['capaian_id']] = (int)$r['skor'];

// manual deskripsi override
$manual = [];
$st = $db->prepare('SELECT sub_cp_id, teks FROM deskripsi WHERE anak_id = ?');
$st->execute([$anakId]);
foreach ($st->fetchAll() as $r) $manual[(int)$r['sub_cp_id']] = $r['teks'];

$panggilan = $anak['nama_panggilan'] ?: $anak['nama_lengkap'];

if (!function_exists('usia')) {
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
}

require_once BASE_DIR . '/src/deskripsi.php';

if (!function_exists('isSplitCp')) {
    function isSplitCp($namaCp) {
        $n = strtolower($namaCp);
        return (strpos($n, 'jati diri') !== false) || (strpos($n, 'literasi') !== false);
    }
}

// Hitung jumlah halaman
$totalIsiPages = 1; // lembar pengesahan
foreach ($cps as $cp) {
    $totalIsiPages += isSplitCp($cp['nama']) ? 2 : 1;
}
$totalAllPages = $totalIsiPages + 2; // + cover & biodata
$currentPageCount = ($bagian === 'cover_biodata') ? 2 : (($bagian === 'isi') ? $totalIsiPages : $totalAllPages);
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<title>Raport - <?= e($anak['nama_lengkap']) ?><?= $bagian !== 'semua' ? ' (' . ($bagian === 'isi' ? 'Isi Raport' : 'Cover & Biodata') . ')' : '' ?></title>
<link rel="stylesheet" href="assets/print.css">
<?php if (!empty($profil['background_rapot']) && file_exists(BASE_DIR . '/' . $profil['background_rapot'])): ?>
<style>
.page {
    background-image: url('<?= e($profil['background_rapot']) ?>') !important;
    background-size: 100% 100% !important;
    background-position: center !important;
    background-repeat: no-repeat !important;
}
.page-inner {
    border: none !important;
    background: transparent !important;
    box-shadow: none !important;
}
.page-inner::before,
.page-inner::after {
    display: none !important;
}
</style>
<?php endif; ?>
</head>
<body>

<div class="print-toolbar no-print">
    <button onclick="window.print()">🖨 Cetak Halaman Ini</button>
    <a href="index.php?route=cetak_print&anak_id=<?= $anakId ?>&bagian=isi" class="btn-tab <?= $bagian === 'isi' ? 'active' : '' ?>">Isi Raport (<?= $totalIsiPages ?> Hal)</a>
    <a href="index.php?route=cetak_print&anak_id=<?= $anakId ?>&bagian=cover_biodata" class="btn-tab <?= $bagian === 'cover_biodata' ? 'active' : '' ?>">Cover & Biodata (2 Hal)</a>
    <a href="index.php?route=cetak_print&anak_id=<?= $anakId ?>&bagian=semua" class="btn-tab <?= $bagian === 'semua' ? 'active' : '' ?>">Cetak Semua (<?= $totalAllPages ?> Hal)</a>
    <a href="index.php?route=cetak" class="back-link">&larr; Kembali</a>
    <span class="info-tag"><?= e($anak['nama_lengkap']) ?> &bull; <strong><?= $currentPageCount ?> Halaman Siap Cetak</strong></span>
</div>

<?php if ($bagian === 'semua' || $bagian === 'cover_biodata'): ?>
<!-- ============ 1. COVER / IDENTITAS SATUAN PENDIDIKAN ============ -->
<section class="page">
    <div class="page-inner">
        <div class="head-bar">✿ &nbsp; LAPORAN PERKEMBANGAN ANAK DIDIK &nbsp; ✿</div>

        <h1 class="cover-title">✿ DATA SATUAN PENDIDIKAN ✿</h1>
        <h2 class="cover-sub">IDENTITAS SEKOLAH</h2>

        <div class="logo-wrap">
            <?php 
            $coverLogo = !empty($profil['logo']) && file_exists(BASE_DIR . '/' . $profil['logo']) 
                ? $profil['logo'] 
                : (file_exists(BASE_DIR . '/assets/logo.png') ? 'assets/logo.png' : '');
            ?>
            <?php if ($coverLogo): ?>
                <img src="<?= e($coverLogo) ?>" alt="Logo Sekolah">
            <?php else: ?>
                <div class="logo-placeholder">LOGO</div>
            <?php endif; ?>
        </div>

        <table class="identitas">
            <?php
            $rows = [
                'Nama Satuan PAUD' => $profil['nama_sekolah'] ?: '-',
                'NPSN' => $profil['npsn'] ?: '-',
                'Alamat' => $profil['alamat'] ?: '-',
                'RT / RW' => $profil['rt_rw'] ?: '-',
                'Desa / Kelurahan' => $profil['desa'] ?: '-',
                'Kecamatan' => $profil['kecamatan'] ?: '-',
                'Kabupaten / Kota' => $profil['kabupaten'] ?: '-',
                'Provinsi' => $profil['provinsi'] ?: '-',
                'Kode Pos' => $profil['kode_pos'] ?: '-',
                'Telepon / WhatsApp' => $profil['telp'] ?: '-',
                'Email' => $profil['email'] ?: '-',
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
        <div class="head-bar">✿ &nbsp; LAPORAN PERKEMBANGAN ANAK DIDIK &nbsp; ✿</div>

        <h1 class="cover-title">✿ DATA PESERTA DIDIK ✿</h1>
        <h2 class="cover-sub">KETERANGAN DIRI ANAK</h2>

        <div class="band band-pink">DATA ANAK</div>
        <table class="identitas">
            <?php
            $rows = [
                'Nama lengkap' => $anak['nama_lengkap'],
                'Nama panggilan' => $anak['nama_panggilan'] ?: '-',
                'NISN / NIS' => $anak['nisn'] ?: '-',
                'Tempat, tanggal lahir' => trim($anak['tempat_lahir'] . ', ' . $anak['tanggal_lahir'], ', ') ?: '-',
                'Jenis kelamin' => $anak['jenis_kelamin'] ?: '-',
                'Agama' => $anak['agama'] ?: '-',
                'Anak ke-' => $anak['anak_ke'] ?: '-',
                'Jumlah saudara' => $anak['jumlah_saudara'] ?: '-',
                'Kelompok / rombel' => $anak['kelompok'] ?: '-',
            ];
            foreach ($rows as $label => $val): ?>
            <tr><td class="lbl"><?= e($label) ?></td><td class="colon">:</td><td><?= e($val) ?></td></tr>
            <?php endforeach; ?>
        </table>

        <div class="band band-pink">DATA ORANG TUA / WALI</div>
        <table class="identitas">
            <?php
            $rows = [
                'Nama ayah' => $anak['nama_ayah'] ?: '-',
                'Pekerjaan ayah' => $anak['pekerjaan_ayah'] ?: '-',
                'Nama ibu' => $anak['nama_ibu'] ?: '-',
                'Pekerjaan ibu' => $anak['pekerjaan_ibu'] ?: '-',
                'Nama wali (jika ada)' => $anak['nama_wali'] ?: '-',
            ];
            foreach ($rows as $label => $val): ?>
            <tr><td class="lbl"><?= e($label) ?></td><td class="colon">:</td><td><?= e($val) ?></td></tr>
            <?php endforeach; ?>
        </table>

        <div class="band band-yellow">ALAMAT TEMPAT TINGGAL</div>
        <table class="identitas">
            <?php
            $rows = [
                'Jalan / Dusun' => $anak['alamat_jalan'] ?: '-',
                'RT / RW' => $anak['alamat_rt_rw'] ?: '-',
                'Desa / Kelurahan' => $anak['alamat_desa'] ?: '-',
                'Kecamatan' => $anak['alamat_kecamatan'] ?: '-',
                'Kabupaten / Kota' => $anak['alamat_kabupaten'] ?: '-',
                'Provinsi' => $anak['alamat_provinsi'] ?: '-',
            ];
            foreach ($rows as $label => $val): ?>
            <tr><td class="lbl"><?= e($label) ?></td><td class="colon">:</td><td><?= e($val) ?></td></tr>
            <?php endforeach; ?>
        </table>

        <div class="sign-row">
            <div class="photo-34">
                <?php if (!empty($anak['foto']) && file_exists(BASE_DIR . '/' . $anak['foto'])): ?>
                    <img src="<?= e($anak['foto']) ?>" alt="Foto 3x4">
                <?php else: ?>
                    <span>3x4</span>
                <?php endif; ?>
            </div>
            <div class="sign-right">
                <div class="sign-date"><?= e($profil['kabupaten'] ?: ($profil['desa'] ?: '..........................')) ?>, ..................................</div>
                <div class="sign-label">Kepala Satuan PAUD / TK</div>
                <div class="sign-space"></div>
                <div class="sign-name"><strong>( <?= e($profil['kepala_sekolah'] ?: '................................................') ?> )</strong></div>
            </div>
        </div>

        <div class="pemisah">- - - - - - Pemisah - - - - - -</div>
        <div class="footer"><?= e($profil['footer'] ?? 'SAHABAT GURU') ?></div>
    </div>
</section>
<?php endif; ?>

<?php if ($bagian === 'semua' || $bagian === 'isi'): ?>
<!-- ============ HALAMAN CAPAIAN PEMBELAJARAN (CP) ============ -->
<?php foreach ($cps as $cp): ?>
    <?php
    $subs = $db->prepare('SELECT * FROM sub_cp WHERE cp_id = ? ORDER BY urutan, id');
    $subs->execute([$cp['id']]);
    $subs = $subs->fetchAll();

    // Foto dokumentasi (urutan 1 dan 2)
    $fotos = [];
    $st = $db->prepare('SELECT * FROM foto WHERE anak_id = ? AND cp_id = ? ORDER BY urutan');
    $st->execute([$anakId, $cp['id']]);
    foreach ($st->fetchAll() as $r) $fotos[(int)$r['urutan']] = $r;

    $split = isSplitCp($cp['nama']);
    // Jika split: 2 halaman, masing-masing tetap 2 foto (lembar 1: foto 1&2, lembar 2: foto 3&4)
    $pages = $split ? [
        ['title_suffix' => ' (BAGIAN 1)', 'subs' => array_slice($subs, 0, 3), 'foto_start' => 1],
        ['title_suffix' => ' (BAGIAN 2)', 'subs' => array_slice($subs, 3),    'foto_start' => 3],
    ] : [
        ['title_suffix' => '', 'subs' => $subs, 'foto_start' => 1],
    ];
    ?>

    <?php foreach ($pages as $pInfo): ?>
    <section class="page">
        <div class="page-inner">
            <div class="head-bar">✿ &nbsp; LAPORAN PERKEMBANGAN ANAK DIDIK &nbsp; ✿</div>

            <table class="meta">
                <tr>
                    <td class="m-lbl">Nama Anak</td><td class="m-col">:</td><td class="m-val"><?= e($anak['nama_lengkap']) ?></td>
                    <td class="m-lbl">Kelompok</td><td class="m-col">:</td><td class="m-val"><?= e($anak['kelompok'] ?: '-') ?></td>
                </tr>
                <tr>
                    <td class="m-lbl">Usia</td><td class="m-col">:</td><td class="m-val"><?= e(usia($anak['tanggal_lahir'])) ?></td>
                    <td class="m-lbl">Tahun Ajaran</td><td class="m-col">:</td><td class="m-val"><?= e($profil['tahun_ajaran'] ?? '-') ?> (<?= e($profil['semester'] ?? '-') ?>)</td>
                </tr>
            </table>

            <h1 class="cp-title">✿ <?= e(strtoupper($cp['nama'])) ?><?= e($pInfo['title_suffix']) ?> ✿</h1>

            <?php
            $color = 0;
            foreach ($pInfo['subs'] as $sub):
                $color++;
                $pink = ($color % 2 === 1);
                $auto = capaianSkorText((int)$sub['id'], $skors, $db);
                $text = trim($manual[(int)$sub['id']] ?? '');
                if ($text === '' && $auto !== '') $text = 'Ananda ' . $panggilan . ' ' . $auto . '.';
            ?>
            <div class="block <?= $pink ? 'block-pink' : 'block-blue' ?>">
                <div class="block-head"><?= e($sub['nama']) ?></div>
                <div class="block-body">
                    <?php if ($text): ?>
                        <?= e($text) ?>
                    <?php else: ?>
                        <span class="muted-print">Belum ada skor capaian untuk sub-bidang ini.</span>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>

            <h3 class="doc-title">✿ DOKUMENTASI KEGIATAN ANAK ✿</h3>
            <!-- Selalu 2 foto per lembar (lembar split lanjutan: foto 3&4) -->
            <?php $fs = $pInfo['foto_start']; ?>
            <div class="foto-row">
                <?php for ($i = $fs; $i < $fs + 2; $i++): $f = $fotos[$i] ?? null; ?>
                <div class="foto-cell">
                    <div class="foto-img">
                        <?php if (!empty($f['file']) && file_exists(BASE_DIR . '/' . $f['file'])): ?>
                            <img src="<?= e($f['file']) ?>" alt="Foto kegiatan <?= $i ?>">
                        <?php else: ?>
                            <span class="muted-print">Foto Kegiatan <?= $i ?></span>
                        <?php endif; ?>
                    </div>
                    <div class="foto-ket"><strong>Keterangan:</strong> <?= e($f['keterangan'] ?? '-') ?></div>
                </div>
                <?php endfor; ?>
            </div>

            <div class="footer"><?= e($profil['footer'] ?? 'SAHABAT GURU') ?></div>
        </div>
    </section>
    <?php endforeach; ?>
<?php endforeach; ?>

<!-- ============ LEMBAR PENGESAHAN ============ -->
<section class="page">
    <div class="page-inner">
        <div class="head-bar">✿ &nbsp; LAPORAN PERKEMBANGAN ANAK DIDIK &nbsp; ✿</div>

        <h1 class="cover-title">✿ LEMBAR PENGESAHAN ✿</h1>
        <h2 class="cover-sub">LAPORAN PERKEMBANGAN ANAK DIDIK</h2>

        <table class="pengesahan-meta">
            <tr>
                <td>Nama Anak: <strong><?= e($anak['nama_lengkap']) ?></strong></td>
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

        <p class="pengesahan-text">
            Tempat, tanggal: <?= e($profil['kabupaten'] ?: ($profil['desa'] ?: '................................................')) ?>, ....................................
        </p>

        <div class="ttd-row">
            <div class="ttd">
                <div class="ttd-role">Orang Tua / Wali</div>
                <div class="ttd-space"></div>
                <div class="ttd-name"><strong>( <?= e($anak['nama_ayah'] ?: ($anak['nama_wali'] ?: ($anak['nama_ibu'] ?: '................................................'))) ?> )</strong></div>
            </div>
            <div class="ttd">
                <div class="ttd-role">Guru Kelompok / Wali Kelas</div>
                <div class="ttd-space"></div>
                <div class="ttd-name"><strong>( <?= e($profil['guru_kelompok'] ?: '................................................') ?> )</strong></div>
            </div>
        </div>

        <div class="ttd-center">
            <div>Mengetahui,</div>
            <div>Kepala Satuan PAUD / TK</div>
            <div class="ttd-space-lg"></div>
            <div class="ttd-name"><strong>( <?= e($profil['kepala_sekolah'] ?: '................................................') ?> )</strong></div>
        </div>

        <p class="catatan">
            <em>Catatan: Foto pada bagian dokumentasi merupakan kegiatan nyata anak selama proses pembelajaran. Guru dapat mengunggah foto melalui menu Foto Kegiatan.</em>
        </p>

        <div class="footer"><?= e($profil['footer'] ?? 'SAHABAT GURU') ?></div>
    </div>
</section>
<?php endif; ?>

</body>
</html>
