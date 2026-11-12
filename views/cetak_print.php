<?php
// Halaman cetak raport (A4)
require_once BASE_DIR . '/src/authz.php';
$anakId = (int)($_GET['anak_id'] ?? 0);
$bagian = $_GET['bagian'] ?? 'semua'; // 'semua', 'isi', 'cover_biodata'
if (!in_array($bagian, ['semua', 'isi', 'cover_biodata'], true)) {
    $bagian = 'semua';
}

$st = $db->prepare('SELECT * FROM anak WHERE id = ?');
$st->execute([$anakId]);
$anak = $st->fetch();
if (!$anak) redirect('index.php?route=cetak');
if (anakAccess($db, $anakId) === null) {
    flash('Anda tidak punya akses mencetak raport anak ini.', 'error');
    redirect('index.php?route=cetak');
}

$profil = getProfil($db);
$cps = $db->query('SELECT * FROM cp ORDER BY urutan, id')->fetchAll();
// Opsi profil: sembunyikan CP AIK dari cetak (juga berlaku di menu Penilaian)
$hideAik = !empty($profil['hide_aik']);
if ($hideAik) {
    $cps = array_values(array_filter($cps, function ($cp) {
        $n = strtolower($cp['nama']);
        return !(strpos($n, 'al islam') !== false || strpos($n, 'kemuhammadiyahan') !== false);
    }));
}

// Refleksi global (pertanyaan sama untuk semua anak) + pertumbuhan/kehadiran per anak
$refleksi = [];
$st = $db->prepare('SELECT * FROM refleksi WHERE anak_id = ?');
$st->execute([$anakId]);
if ($r = $st->fetch()) $refleksi = $r;
// Pertanyaan refleksi global (bisa banyak, diurutkan)
$qs = $db->query('SELECT teks FROM refleksi_pertanyaan ORDER BY urutan, id')->fetchAll(PDO::FETCH_COLUMN);
if (!$qs) {
    $qs = [
        'Perubahan positif apa yang sudah ada pada Ananda selama semester ini?',
        'Pendampingan/dukungan apa yang sudah Bapak/Ibu lakukan kepada Ananda selama semester ini?',
        'Potensi apa yang ingin Bapak/Ibu maksimalkan dari Ananda?',
    ];
}

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

// Tanggal Indonesia: "Ngawan, 26 Juni 2026"
function tanggalIndo($tgl = null) {
    $bulan = [1=>'Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'];
    $d = $tgl ? new DateTime($tgl) : new DateTime();
    return $d->format('j') . ' ' . $bulan[(int)$d->format('n')] . ' ' . $d->format('Y');
}

// Split 2 lembar saat cetak: hanya Jati Diri & Literasi (AIK cukup 1 lembar)
if (!function_exists('isSplitCp')) {
    function isSplitCp($namaCp) {
        $n = strtolower($namaCp);
        return (strpos($n, 'jati diri') !== false) || (strpos($n, 'literasi') !== false);
    }
}

// Hitung jumlah halaman
$totalIsiPages = 2; // refleksi + lembar pertumbuhan/pengesahan
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
/* Background hanya untuk halaman isi — cover & biodata tetap bingkai bawaan */
.page:not(.page-cover-official):not(.page-biodata) {
    background-image: url('<?= e($profil['background_rapot']) ?>') !important;
    background-size: 100% 100% !important;
    background-position: center !important;
    background-repeat: no-repeat !important;
}
.page:not(.page-cover-official):not(.page-biodata) .page-inner {
    border: none !important;
    background: transparent !important;
    box-shadow: none !important;
}
.page:not(.page-cover-official):not(.page-biodata) .page-inner::before,
.page:not(.page-cover-official):not(.page-biodata) .page-inner::after {
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
<!-- ============ 1. COVER (gaya resmi: 1 logo + identitas + nama anak) ============ -->
<section class="page page-cover-official">
    <div class="page-inner">
        <div class="logo-wrap logo-wrap-official">
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

        <div class="official-kicker">LAPORAN</div>
        <div class="official-title">PERKEMBANGAN ANAK DIDIK</div>
	<div class="official-title">TAMAN KANAK KANAK 'AISYIYAH BUSTANUL ATHFAL</div>
        <div class="official-school">
           
        <table class="identitas identitas-official">
            <?php
            $rows = [
                'Nama TK' => $profil['nama_sekolah'] ?: '-',
                'NSS' => $profil['nss'] ?? '',
                'NPSN' => $profil['npsn'] ?: '-',
                'Alamat' => trim(($profil['alamat'] ?? '') . (($profil['rt_rw'] ?? '') !== '' ? ', RT/RW ' . $profil['rt_rw'] : ''), ', ') ?: '-',
                'Desa/Kelurahan' => $profil['desa'] ?: '-',
                'Kecamatan' => $profil['kecamatan'] ?: '-',
                'Kabupaten' => $profil['kabupaten'] ?: '-',
                'Provinsi' => $profil['provinsi'] ?: '-',
            ];
            foreach ($rows as $label => $val): ?>
            <tr><td class="lbl"><?= e($label) ?></td><td class="colon">:</td><td><?= e($val !== '' ? $val : '-') ?></td></tr>
            <?php endforeach; ?>
        </table>

        <div class="official-child-label">Nama Anak Didik :</div>
        <div class="official-child-name"><?= e($anak['nama_lengkap']) ?></div>
	<br>
	<br>
	<br>
 	<div class="official-child-note">PIMPINAN PUSAT 'AISYIYAH </div>
	<div class="official-child-note">MAJELIS PENDIDIKAN ANAK USIA DINI, DASAR DAN MENENGAH </div>

        <?php if (!empty($profil['cover_note'])): ?>
        <div class="official-child-note"><?= e($profil['cover_note']) ?></div>
        <?php endif; ?>
    </div>
</section>

<!-- ============ 2. DATA PESERTA DIDIK (1 halaman A4) ============ -->
<section class="page page-biodata">
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
                'Kelompok / rombel' => $anak['kelompok'] ?: '-',
                'Tanggal masuk' => !empty($profil['tanggal_masuk']) ? tanggalIndo($profil['tanggal_masuk']) : '-',
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
                <div class="sign-date"><?= e($profil['kota'] ?: $profil['kabupaten'] ?: ($profil['desa'] ?: '..........................')) ?>, <?= !empty($profil['tanggal_masuk']) ? tanggalIndo($profil['tanggal_masuk']) : '....................................' ?></div>
                <div class="sign-label">
    Kepala <?= e($profil['nama_sekolah']) ?>
</div>
                <div class="sign-space"></div>
                <div class="sign-name"><strong>( <?= e($profil['kepala_sekolah'] ?: '................................................') ?> )</strong></div>
            </div>
        </div>

        <div class="pemisah">- - - - - - Pemisah - - - - - -</div>
    </div>
</section>
<?php endif; ?>

<?php if ($bagian === 'semua' || $bagian === 'isi'): ?>
<!-- ============ HALAMAN CAPAIAN PEMBELAJARAN (CP) ============ -->
<?php $isiCounter = 0; ?>
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
    if ($split) {
        // Jati Diri / Literasi: 2 lembar — L1: 3 sub-CP tanpa foto; L2: sisa sub-CP + foto 1&2
        $pages = [
            ['title_suffix' => ' (BAGIAN 1)', 'subs' => array_slice($subs, 0, 3), 'show_foto' => false],
            ['title_suffix' => ' (BAGIAN 2)', 'subs' => array_slice($subs, 3),    'show_foto' => true, 'foto_start' => 1],
        ];
    } else {
        // Semua CP lain (termasuk AIK): 1 lembar — semua sub-CP + foto 1&2
        $pages = [
            ['title_suffix' => '', 'subs' => $subs, 'show_foto' => true, 'foto_start' => 1],
        ];
    }
    ?>

    <?php foreach ($pages as $pInfo): $isiCounter++; $isFirstIsi = ($isiCounter === 1); ?>
    <section class="page">
        <div class="page-inner">
            <?php if ($isFirstIsi): ?>
            <!-- Format header + meta hanya di halaman pertama isi raport -->
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
            <?php endif; ?>

            <h1 class="cp-title">✿ <?= e(strtoupper($cp['nama'])) ?><?= e($pInfo['title_suffix'] ?? '') ?> ✿</h1>

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

            <?php if (!empty($pInfo['show_foto'])): ?>
            <h3 class="doc-title">✿ DOKUMENTASI KEGIATAN ANAK ✿</h3>
            <?php $fs = $pInfo['foto_start'] ?? 1; ?>
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
                </div>
                <?php endfor; ?>
            </div>
            <?php endif; ?>
        </div>
    </section>
    <?php endforeach; ?>
<?php endforeach; ?>

<!-- ============ LEMBAR REFLEKSI ORANG TUA (terpisah) ============ -->
<section class="page">
    <div class="page-inner">
        <div class="meta-simple">
            Nama Anak: <strong><?= e($anak['nama_lengkap']) ?></strong>
            &nbsp;&nbsp;|&nbsp;&nbsp; Kelompok: <?= e($anak['kelompok'] ?: '-') ?>
            &nbsp;&nbsp;|&nbsp;&nbsp; Tahun Ajaran: <?= e($profil['tahun_ajaran'] ?? '-') ?> (<?= e($profil['semester'] ?? '-') ?>)
        </div>

        <div class="refleksi-title">REFLEKSI ORANG TUA</div>
        <table class="refleksi-tbl">
            <thead>
                <tr>
                    <th style="width:8mm;">No</th>
                    <th>Pertanyaan &amp; Jawaban Orang Tua / Wali</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($qs as $i => $q): ?>
                <tr>
                    <td class="refleksi-no"><?= $i + 1 ?>.</td>
                    <td>
                        <div class="refleksi-qtext"><?= e($q) ?></div>
                        <div class="refleksi-lines">
                            <span></span><span></span><span></span>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>

<!-- ============ LEMBAR PERTUMBUHAN, KEHADIRAN & PENGESAHAN ============ -->
<section class="page page-pengesahan">
    <div class="page-inner">
        <div class="meta-simple">
            Nama Anak: <strong><?= e($anak['nama_lengkap']) ?></strong>
            &nbsp;&nbsp;|&nbsp;&nbsp; Kelompok: <?= e($anak['kelompok'] ?: '-') ?>
            &nbsp;&nbsp;|&nbsp;&nbsp; Tahun Ajaran: <?= e($profil['tahun_ajaran'] ?? '-') ?> (<?= e($profil['semester'] ?? '-') ?>)
        </div>

        <div class="growth-title">PERTUMBUHAN DAN KEHADIRAN ANAK</div>

        <table class="growth-tbl growth-single">
            <thead>
                <tr>
                    <th colspan="2">Pertumbuhan</th>
                    <th colspan="2">Kehadiran Anak</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>Berat Badan</td>
                    <td><?= e($refleksi['berat_badan'] ?? '') !== '' ? e($refleksi['berat_badan']) : '................' ?></td>
                    <td>Sakit</td>
                    <td><?= isset($refleksi['sakit']) ? (int)$refleksi['sakit'] : 0 ?></td>
                </tr>
                <tr>
                    <td>Tinggi Badan</td>
                    <td><?= e($refleksi['tinggi_badan'] ?? '') !== '' ? e($refleksi['tinggi_badan']) : '................' ?></td>
                    <td>Izin</td>
                    <td><?= isset($refleksi['izin']) ? (int)$refleksi['izin'] : 0 ?></td>
                </tr>
                <tr>
                    <td>Lingkar Kepala</td>
                    <td><?= e($refleksi['lingkar_kepala'] ?? '') !== '' ? e($refleksi['lingkar_kepala']) : '................' ?></td>
                    <td>Tanpa Keterangan</td>
                    <td><?= isset($refleksi['tanpa_keterangan']) ? (int)$refleksi['tanpa_keterangan'] : 0 ?></td>
                </tr>
                <tr>
                    <td>Lingkar Lengan</td>
                    <td><?= e($refleksi['lingkar_lengan'] ?? '') !== '' ? e($refleksi['lingkar_lengan']) : '................' ?></td>
                    <td></td>
                    <td></td>
                </tr>
            </tbody>
        </table>

        <div class="ttd-date-row">
            <?= e($profil['kota'] ?: $profil['kabupaten'] ?: $profil['desa'] ?: '..........................') ?>, <?= !empty($profil['tanggal_raport']) ? tanggalIndo($profil['tanggal_raport']) : '....................................' ?>
        </div>

        <div class="ttd-block">
            <div class="ttd-col">
                <div class="ttd-role">Orang Tua/Wali</div>
                <div class="ttd-space"></div>
                <div class="ttd-line-dotted">(<?= str_repeat('.', 28) ?>)</div>
            </div>
            <div class="ttd-col ttd-col-right">
                <div class="ttd-role">Guru Kelas</div>
                <div class="ttd-space"></div>
                <div class="ttd-name-print"><strong><?= e($profil['guru_kelompok'] ?: '') ?></strong></div>
                <div class="ttd-nip">NBM. <?= e($profil['nip_guru'] ?: '........................') ?></div>
            </div>
        </div>

        <div class="ttd-center">
            <div>Mengetahui</div>
            <div>Kepala <?= e($profil['nama_sekolah'] ?: 'Satuan PAUD / TK') ?></div>
            <div class="ttd-space-lg"></div>
            <div class="ttd-name-print"><strong><?= e($profil['kepala_sekolah'] ?: '') ?></strong></div>
            <div class="ttd-nip">NIP/NBM. <?= e($profil['nip_kepala'] ?: '........................') ?></div>
        </div>
    </div>
</section>
<?php endif; ?>

</body>
<script>
// Auto-scale: setiap lembar dipaksa pas 1 halaman A4.
// Jika konten melebihi tinggi halaman, isi di-scale otomatis (transform) agar tidak meluber ke halaman berikutnya.
(function () {
    var MM = 96 / 25.4;                 // px per mm (CSS)
    var TARGET = 277 * MM - 2;          // tinggi maks page-inner (297mm - padding 10mm x2), sisa 2px jaga2

    function fitPages() {
        var pages = document.querySelectorAll('.page');
        for (var i = 0; i < pages.length; i++) {
            var page = pages[i];
            var inner = page.querySelector('.page-inner');
            if (!inner) continue;

            // Reset dulu agar pengukuran selalu tinggi asli (transform tidak memengaruhi offsetHeight)
            inner.style.transform = '';
            inner.style.width = '';
            page.style.height = '';

            var h = inner.offsetHeight;
            if (h > TARGET) {
                var scale = TARGET / h;
                inner.style.transformOrigin = 'top left';
                inner.style.transform = 'scale(' + scale + ')';
                inner.style.width = (100 / scale) + '%'; // kompensasi lebar agar pas setelah di-scale
                page.style.height = '297mm';             // kunci tinggi halaman = 1 A4
                page.style.overflow = 'hidden';
            }
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', fitPages);
    } else {
        fitPages();
    }
    window.addEventListener('load', fitPages);        // setelah gambar/font selesai dimuat
    window.addEventListener('beforeprint', fitPages); // jaring pengaman sebelum cetak
    if (document.fonts && document.fonts.ready) {
        document.fonts.ready.then(fitPages);
    }
})();
</script>
</html>
