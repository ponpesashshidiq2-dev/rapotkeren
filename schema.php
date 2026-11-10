<?php
// Skema database + seed (idempoten)

$db->exec("CREATE TABLE IF NOT EXISTS users (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    username TEXT UNIQUE NOT NULL,
    password TEXT NOT NULL
)");

$db->exec("CREATE TABLE IF NOT EXISTS profil (
    id INTEGER PRIMARY KEY CHECK (id = 1),
    logo TEXT DEFAULT '',
    background_rapot TEXT DEFAULT '',
    npsn TEXT DEFAULT '',
    nama_sekolah TEXT DEFAULT '',
    status TEXT DEFAULT '',
    jenjang TEXT DEFAULT '',
    alamat TEXT DEFAULT '',
    rt_rw TEXT DEFAULT '',
    desa TEXT DEFAULT '',
    kecamatan TEXT DEFAULT '',
    kabupaten TEXT DEFAULT '',
    provinsi TEXT DEFAULT '',
    kode_pos TEXT DEFAULT '',
    telp TEXT DEFAULT '',
    email TEXT DEFAULT '',
    kepala_sekolah TEXT DEFAULT '',
    guru_kelompok TEXT DEFAULT '',
    tahun_ajaran TEXT DEFAULT '',
    semester TEXT DEFAULT '',
    footer TEXT DEFAULT 'SAHABAT GURU'
)");

try {
    $db->exec("ALTER TABLE profil ADD COLUMN background_rapot TEXT DEFAULT ''");
} catch (Exception $e) {}

$db->exec("CREATE TABLE IF NOT EXISTS anak (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    nama_lengkap TEXT NOT NULL,
    nama_panggilan TEXT DEFAULT '',
    nisn TEXT DEFAULT '',
    tempat_lahir TEXT DEFAULT '',
    tanggal_lahir TEXT DEFAULT '',
    jenis_kelamin TEXT DEFAULT '',
    agama TEXT DEFAULT '',
    anak_ke TEXT DEFAULT '',
    jumlah_saudara TEXT DEFAULT '',
    kelompok TEXT DEFAULT '',
    nama_ayah TEXT DEFAULT '',
    pekerjaan_ayah TEXT DEFAULT '',
    nama_ibu TEXT DEFAULT '',
    pekerjaan_ibu TEXT DEFAULT '',
    nama_wali TEXT DEFAULT '',
    alamat_jalan TEXT DEFAULT '',
    alamat_rt_rw TEXT DEFAULT '',
    alamat_desa TEXT DEFAULT '',
    alamat_kecamatan TEXT DEFAULT '',
    alamat_kabupaten TEXT DEFAULT '',
    alamat_provinsi TEXT DEFAULT '',
    foto TEXT DEFAULT ''
)");

$db->exec("CREATE TABLE IF NOT EXISTS cp (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    urutan INTEGER DEFAULT 0,
    nama TEXT NOT NULL
)");

$db->exec("CREATE TABLE IF NOT EXISTS sub_cp (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    cp_id INTEGER NOT NULL,
    urutan INTEGER DEFAULT 0,
    nama TEXT NOT NULL,
    FOREIGN KEY (cp_id) REFERENCES cp(id) ON DELETE CASCADE
)");

$db->exec("CREATE TABLE IF NOT EXISTS capaian (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    sub_cp_id INTEGER NOT NULL,
    urutan INTEGER DEFAULT 0,
    teks TEXT NOT NULL,
    FOREIGN KEY (sub_cp_id) REFERENCES sub_cp(id) ON DELETE CASCADE
)");

$db->exec("CREATE TABLE IF NOT EXISTS penilaian (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    anak_id INTEGER NOT NULL,
    capaian_id INTEGER NOT NULL,
    UNIQUE(anak_id, capaian_id),
    FOREIGN KEY (anak_id) REFERENCES anak(id) ON DELETE CASCADE,
    FOREIGN KEY (capaian_id) REFERENCES capaian(id) ON DELETE CASCADE
)");

$db->exec("CREATE TABLE IF NOT EXISTS deskripsi (
    anak_id INTEGER NOT NULL,
    sub_cp_id INTEGER NOT NULL,
    teks TEXT DEFAULT '',
    PRIMARY KEY (anak_id, sub_cp_id),
    FOREIGN KEY (anak_id) REFERENCES anak(id) ON DELETE CASCADE,
    FOREIGN KEY (sub_cp_id) REFERENCES sub_cp(id) ON DELETE CASCADE
)");

$db->exec("CREATE TABLE IF NOT EXISTS foto (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    anak_id INTEGER NOT NULL,
    cp_id INTEGER NOT NULL,
    urutan INTEGER NOT NULL,
    file TEXT DEFAULT '',
    keterangan TEXT DEFAULT '',
    UNIQUE(anak_id, cp_id, urutan),
    FOREIGN KEY (anak_id) REFERENCES anak(id) ON DELETE CASCADE,
    FOREIGN KEY (cp_id) REFERENCES cp(id) ON DELETE CASCADE
)");

// ---- Seed ----
$cnt = (int)$db->query('SELECT COUNT(*) c FROM users')->fetch()['c'];
if ($cnt === 0) {
    $db->prepare('INSERT INTO users (username, password) VALUES (?, ?)')
       ->execute(['admin', password_hash('admin123', PASSWORD_DEFAULT)]);
}

$cnt = (int)$db->query('SELECT COUNT(*) c FROM profil')->fetch()['c'];
if ($cnt === 0) {
    $db->exec("INSERT INTO profil (id, nama_sekolah, jenjang, status, footer, tahun_ajaran, semester)
               VALUES (1, 'TK ISLAM ASSHIDIQ', 'TK', 'Swasta', 'SAHABAT GURU', '2025/2026', 'Ganjil')");
}

$cnt = (int)$db->query('SELECT COUNT(*) c FROM cp')->fetch()['c'];
if ($cnt === 0) {
    $cps = [
        ['Nilai Agama dan Budi Pekerti', [
            'Pembiasaan ibadah dan doa' => [
                'berdoa sebelum dan sesudah kegiatan',
                'mengikuti ibadah sesuai keyakinan',
                'mengucapkan syukur',
                'menghafal doa harian',
                'menunjukkan sikap tertib saat beribadah',
            ],
            'Perilaku baik dan kepedulian' => [
                'berkata jujur',
                'menggunakan kata-kata santun',
                'mengucapkan terima kasih dan maaf',
                'menghargai guru dan teman',
                'berbagi alat bermain',
                'membantu teman',
                'menjaga barang bersama',
            ],
        ]],
        ['Jati Diri', [
            'Identitas diri dan percaya diri' => [
                'menyebutkan nama',
                'mengenali ciri diri',
                'menyampaikan pilihan',
                'menunjukkan rasa percaya diri',
                'berani mencoba pengalaman baru',
                'menyampaikan pendapat',
            ],
            'Kemandirian dan tanggung jawab' => [
                'menyimpan tas dan sepatu',
                'mencuci tangan',
                'menggunakan perlengkapan pribadi',
                'makan sendiri',
                'merapikan alat bermain',
                'merapikan tempat duduk',
            ],
            'Emosional' => [
                'berbagi',
                'menunggu giliran',
                'mengendalikan amarah',
                'sabar menunggu',
                'menunjukkan emosi dengan tepat',
                'memaafkan teman',
            ],
            'Hubungan sosial' => [
                'bermain bersama teman',
                'bekerja sama dalam kelompok',
                'menyapa guru dan teman',
                'mengajak teman bermain',
                'mendengarkan teman berbicara',
                'menyelesaikan perbedaan dengan damai',
            ],
            'Kesehatan dan keselamatan diri' => [
                'mencuci tangan sebelum makan',
                'menggosok gigi',
                'menyeka tangan setelah toilet',
                'menyeberang dengan hati-hati',
                'menjaga jarak saat berlari',
                'memberi tahu guru jika merasa tidak enak badan',
            ],
        ]],
        ['Dasar-dasar Literasi, Matematika, Sains, Teknologi, Rekayasa, dan Seni', [
            'Literasi dan komunikasi' => [
                'menyimak cerita',
                'melihat buku bergambar',
                'bercakap-cakap',
                'menyampaikan pengalaman',
                'menjawab pertanyaan sederhana',
                'menyebutkan tokoh atau benda yang dilihat',
                'mengungkapkan gagasan melalui gambar dan coretan',
            ],
            'Matematika awal' => [
                'menghitung benda',
                'membandingkan banyak-sedikit',
                'membandingkan besar-kecil',
                'mengenali bentuk dan warna',
                'mengelompokkan benda',
                'melanjutkan pola sederhana',
            ],
            'Sains dan penjelajahan' => [
                'mengamati benda di sekitar',
                'mencoba percobaan sederhana',
                'mengelompokkan benda berdasarkan ciri',
                'menyampaikan hasil pengamatan',
                'bertanya tentang fenomena alam',
            ],
            'Teknologi dan rekayasa' => [
                'mengenal alat sederhana',
                'membangun konstruksi sederhana',
                'memecahkan masalah dengan alat',
                'bekerja sama dalam proyek kecil',
            ],
            'Seni dan kreativitas' => [
                'menyanyi dengan tepat',
                'bergerak mengikuti irama',
                'mewarnai dan menggambar',
                'bermain peran',
                'membuat karya dari bahan sederhana',
            ],
        ]],
        ['Kemuhammadiyah', [
            'Pengenalan nilai KeMuhammadiyahan' => [
                'mengenal lambang Muhammadiyah',
                'menyanyi lagu Kemuhammadiyahan',
                'mengenal tokoh Muhammadiyah',
                'mengetahui semangat amal usaha Muhammadiyah',
            ],
            'Akhlak dan sikap Islami' => [
                'bersikap jujur dan amanah',
                'saling tolong menolong',
                'peduli terhadap sesama',
                'menjaga kebersihan lingkungan',
                'berperilaku santun',
            ],
        ]],
        ['Alam dan Keterampilan Hidup (AIK)', [
            'Keterampilan hidup dasar' => [
                'mengikuti kegiatan sehari-hari',
                'menyiapkan alat belajar sendiri',
                'menyimpan barang dengan rapi',
                'mengikuti aturan kegiatan',
            ],
            'Keimanan dan akhlak' => [
                'mengenal ciptaan Allah',
                'bersyukur atas nikmat',
                'menunjukkan sikap saling menghormati',
                'menjaga tutur kata',
            ],
        ]],
    ];
    $insCp = $db->prepare('INSERT INTO cp (urutan, nama) VALUES (?, ?)');
    $insSub = $db->prepare('INSERT INTO sub_cp (cp_id, urutan, nama) VALUES (?, ?, ?)');
    $insCap = $db->prepare('INSERT INTO capaian (sub_cp_id, urutan, teks) VALUES (?, ?, ?)');
    $u = 0;
    foreach ($cps as [$cpNama, $subs]) {
        $u++;
        $insCp->execute([$u, $cpNama]);
        $cpId = (int)$db->lastInsertId();
        $su = 0;
        foreach ($subs as $subNama => $caps) {
            $su++;
            $insSub->execute([$cpId, $su, $subNama]);
            $subId = (int)$db->lastInsertId();
            $cu = 0;
            foreach ($caps as $cap) {
                $cu++;
                $insCap->execute([$subId, $cu, $cap]);
            }
        }
    }
}
