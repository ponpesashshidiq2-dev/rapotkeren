<?php
// Skema database + seed (idempoten)

$db->exec("CREATE TABLE IF NOT EXISTS users (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    username TEXT UNIQUE NOT NULL,
    password TEXT NOT NULL,
    role TEXT DEFAULT 'guru',
    status TEXT DEFAULT 'active',
    nama_lengkap TEXT DEFAULT '',
    kuota_anak INTEGER DEFAULT 0
)");

// Migrasi: tambah kolom role/status/nama/kuota bila belum ada
try {
    $db->exec("ALTER TABLE users ADD COLUMN role TEXT DEFAULT 'guru'");
} catch (Exception $e) {}
try {
    $db->exec("ALTER TABLE users ADD COLUMN status TEXT DEFAULT 'active'");
} catch (Exception $e) {}
try {
    $db->exec("ALTER TABLE users ADD COLUMN nama_lengkap TEXT DEFAULT ''");
} catch (Exception $e) {}
try {
    $db->exec("ALTER TABLE users ADD COLUMN kuota_anak INTEGER DEFAULT 0");
} catch (Exception $e) {}

// Username 'admin' minimal jadi admin (jangan turunkan super_admin)
$db->exec("UPDATE users SET role = 'admin' WHERE username = 'admin' AND role = 'guru'");
// Pastikan selalu ada minimal satu admin (admin atau super_admin)
$adaAdmin = (int)$db->query("SELECT COUNT(*) c FROM users WHERE role IN ('admin','super_admin')")->fetch()['c'];
if ($adaAdmin === 0) {
    $db->exec("UPDATE users SET role = 'admin' WHERE id = (SELECT MIN(id) FROM users)");
}
// Pastikan selalu ada minimal satu super_admin (promosikan admin tertua bila belum ada)
$adaSuper = (int)$db->query("SELECT COUNT(*) c FROM users WHERE role = 'super_admin'")->fetch()['c'];
if ($adaSuper === 0) {
    $db->exec("UPDATE users SET role = 'super_admin'
               WHERE id = (SELECT MIN(id) FROM users WHERE role = 'admin')");
    $adaSuper = (int)$db->query("SELECT COUNT(*) c FROM users WHERE role = 'super_admin'")->fetch()['c'];
    if ($adaSuper === 0) {
        $db->exec("UPDATE users SET role = 'super_admin' WHERE id = (SELECT MIN(id) FROM users)");
    }
}

// ---- Profil multi-user: satu baris profil per user (user_id UNIQUE) ----
// Skema baru untuk install segar (tanpa CHECK id=1, dengan relasi user_id)
$db->exec("CREATE TABLE IF NOT EXISTS profil (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NOT NULL DEFAULT 0,
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
    footer TEXT DEFAULT 'SAHABAT GURU',
    nip_kepala TEXT DEFAULT '',
    nip_guru TEXT DEFAULT '',
    kota TEXT DEFAULT '',
    tanggal_raport TEXT DEFAULT '',
    refleksi_q1 TEXT DEFAULT '',
    refleksi_q2 TEXT DEFAULT '',
    refleksi_q3 TEXT DEFAULT '',
    nss TEXT DEFAULT '',
    hide_aik INTEGER NOT NULL DEFAULT 0,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
)");

// Migrasi kolom tambahan pada tabel lama (sebelum rebuild multi-user)
try {
    $db->exec("ALTER TABLE profil ADD COLUMN background_rapot TEXT DEFAULT ''");
} catch (Exception $e) {}
try {
    $db->exec("ALTER TABLE profil ADD COLUMN nip_kepala TEXT DEFAULT ''");
    $db->exec("ALTER TABLE profil ADD COLUMN nip_guru TEXT DEFAULT ''");
} catch (Exception $e) {}
try {
    $db->exec("ALTER TABLE profil ADD COLUMN nss TEXT DEFAULT ''");
} catch (Exception $e) {}
try {
    $db->exec("ALTER TABLE profil ADD COLUMN hide_aik INTEGER NOT NULL DEFAULT 0");
} catch (Exception $e) {}
try {
    $db->exec("ALTER TABLE profil ADD COLUMN kota TEXT DEFAULT ''");
    $db->exec("ALTER TABLE profil ADD COLUMN tanggal_raport TEXT DEFAULT ''");
} catch (Exception $e) {}
try {
    $db->exec("ALTER TABLE profil ADD COLUMN refleksi_q1 TEXT DEFAULT ''");
    $db->exec("ALTER TABLE profil ADD COLUMN refleksi_q2 TEXT DEFAULT ''");
    $db->exec("ALTER TABLE profil ADD COLUMN refleksi_q3 TEXT DEFAULT ''");
} catch (Exception $e) {}

// Rebuild tabel profil lama (CHECK id=1 → multi-user dengan user_id)
$profilHasUserId = false;
foreach ($db->query('PRAGMA table_info(profil)') as $c) {
    if ($c['name'] === 'user_id') { $profilHasUserId = true; break; }
}
if (!$profilHasUserId) {
    $oldCols = [];
    foreach ($db->query('PRAGMA table_info(profil)') as $c) {
        if ($c['name'] !== 'id') $oldCols[] = $c['name'];
    }
    // FK dimatikan sementara: baris lama masih user_id=0 (belum ada di users)
    $db->exec('PRAGMA foreign_keys = OFF');
    $db->exec('BEGIN');
    $db->exec('CREATE TABLE profil_multi (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER NOT NULL DEFAULT 0' .
        ($oldCols ? ', ' . implode(', ', array_map(fn($c) => '"' . $c . '" TEXT DEFAULT \'\'', $oldCols)) : '') .
        ', FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    )');
    if ($oldCols) {
        $colList = implode(', ', array_map(fn($c) => '"' . $c . '"', $oldCols));
        $db->exec("INSERT INTO profil_multi (user_id, $colList) SELECT 0, $colList FROM profil");
    }
    $db->exec('DROP TABLE profil');
    $db->exec('ALTER TABLE profil_multi RENAME TO profil');
    $db->exec('COMMIT');
    $db->exec('PRAGMA foreign_keys = ON');
}
// Unik parsial: satu profil per user (baris legacy user_id=0 diizinkan sementara)
$db->exec('CREATE UNIQUE INDEX IF NOT EXISTS idx_profil_user ON profil(user_id) WHERE user_id > 0');

// Baris profil lama (user_id=0) dialihkan ke admin pertama yang belum punya profil
foreach ($db->query('SELECT id FROM profil WHERE user_id = 0 OR user_id IS NULL')->fetchAll() as $orphan) {
    $st = $db->query("SELECT MIN(id) AS mid FROM users u
                      WHERE NOT EXISTS (SELECT 1 FROM profil p WHERE p.user_id = u.id)");
    $target = $st->fetch();
    if ($target && $target['mid'] !== null) {
        $db->prepare('UPDATE profil SET user_id = ? WHERE id = ?')
           ->execute([(int)$target['mid'], (int)$orphan['id']]);
    }
}
// Buat profil kosong untuk setiap user yang belum punya
$db->exec("INSERT INTO profil (user_id)
           SELECT u.id FROM users u
           WHERE NOT EXISTS (SELECT 1 FROM profil p WHERE p.user_id = u.id)");

// Daftar pertanyaan refleksi global (bisa tambah/hapus)
$db->exec("CREATE TABLE IF NOT EXISTS refleksi_pertanyaan (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    urutan INTEGER DEFAULT 0,
    teks TEXT NOT NULL
)");
$cntQ = (int)$db->query('SELECT COUNT(*) c FROM refleksi_pertanyaan')->fetch()['c'];
if ($cntQ === 0) {
    // Migrasi dari kolom profil lama / anak lama / default
    $old = $db->query("SELECT refleksi_q1 q1, refleksi_q2 q2, refleksi_q3 q3 FROM profil
                       WHERE refleksi_q1 != '' OR refleksi_q2 != '' OR refleksi_q3 != ''
                       ORDER BY user_id LIMIT 1")->fetch();
    $defs = [
        trim($old['q1'] ?? '') ?: 'Perubahan positif apa yang sudah ada pada Ananda selama semester ini?',
        trim($old['q2'] ?? '') ?: 'Pendampingan/dukungan apa yang sudah Bapak/Ibu lakukan kepada Ananda selama semester ini?',
        trim($old['q3'] ?? '') ?: 'Potensi apa yang ingin Bapak/Ibu maksimalkan dari Ananda?',
    ];
    $insQ = $db->prepare('INSERT INTO refleksi_pertanyaan (urutan, teks) VALUES (?, ?)');
    foreach ($defs as $i => $t) $insQ->execute([$i + 1, $t]);
}

// Rename sub CP: Akhlak dan sikap Islami → Al-Qur'an dan Ibadah
try {
    $db->exec("UPDATE sub_cp SET nama = 'Al-Qur''an dan Ibadah' WHERE nama = 'Akhlak dan sikap Islami'");
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
    skor INTEGER DEFAULT 0,
    UNIQUE(anak_id, capaian_id),
    FOREIGN KEY (anak_id) REFERENCES anak(id) ON DELETE CASCADE,
    FOREIGN KEY (capaian_id) REFERENCES capaian(id) ON DELETE CASCADE
)");

// Migrasi: tambah kolom skor (1-4) bila belum ada
$hasSkor = false;
foreach ($db->query('PRAGMA table_info(penilaian)') as $c) {
    if ($c['name'] === 'skor') { $hasSkor = true; break; }
}
if (!$hasSkor) {
    $db->exec('ALTER TABLE penilaian ADD COLUMN skor INTEGER DEFAULT 0');
    // Baris lama (centang lama) dianggap "berkembang sesuai harapan" (3)
    $db->exec('UPDATE penilaian SET skor = 3 WHERE skor = 0 OR skor IS NULL');
}

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

// Refleksi orang tua + pertumbuhan & kehadiran anak (per anak)
$db->exec("CREATE TABLE IF NOT EXISTS refleksi (
    anak_id INTEGER PRIMARY KEY,
    q1 TEXT DEFAULT '',
    q2 TEXT DEFAULT '',
    q3 TEXT DEFAULT '',
    berat_badan TEXT DEFAULT '',
    tinggi_badan TEXT DEFAULT '',
    lingkar_kepala TEXT DEFAULT '',
    lingkar_lengan TEXT DEFAULT '',
    sakit INTEGER DEFAULT 0,
    izin INTEGER DEFAULT 0,
    tanpa_keterangan INTEGER DEFAULT 0,
    FOREIGN KEY (anak_id) REFERENCES anak(id) ON DELETE CASCADE
)");

// ---- Migrasi: gabung CP Kemuhammadiyah + AIK lama → 1 CP AIK di urutan pertama ----
$aikNama = "AL ISLAM, KE'AISYIYAHAN DAN KEMUHAMMADIYAHAN (AIK)";
$adaAik = $db->prepare('SELECT id FROM cp WHERE nama = ?');
$adaAik->execute([$aikNama]);
if (!$adaAik->fetch()) {
    $kemu = $db->query("SELECT id FROM cp WHERE nama LIKE 'Kemuhammadiyah%'")->fetch();
    $aikLama = $db->query("SELECT id FROM cp WHERE nama LIKE '%(AIK)%'")->fetch();
    if ($kemu && $aikLama) {
        $db->beginTransaction();
        // Pakai id Kemuhammadiyah sebagai induk baru
        $db->prepare('UPDATE cp SET nama = ?, urutan = 0 WHERE id = ?')->execute([$aikNama, $kemu['id']]);
        $newId = (int)$kemu['id'];
        $oldAikId = (int)$aikLama['id'];

        // Pindahkan sub_cp dari AIK lama
        $db->prepare('UPDATE sub_cp SET cp_id = ? WHERE cp_id = ?')->execute([$newId, $oldAikId]);

        // Pindahkan foto (hindari bentrok unique anak+cp+urutan)
        $fotos = $db->prepare('SELECT * FROM foto WHERE cp_id = ? ORDER BY id');
        $fotos->execute([$oldAikId]);
        foreach ($fotos->fetchAll() as $f) {
            $u = (int)$f['urutan'];
            while (true) {
                $chk = $db->prepare('SELECT 1 FROM foto WHERE anak_id=? AND cp_id=? AND urutan=?');
                $chk->execute([$f['anak_id'], $newId, $u]);
                if (!$chk->fetch()) break;
                $u++;
            }
            $db->prepare('UPDATE foto SET cp_id = ?, urutan = ? WHERE id = ?')->execute([$newId, $u, $f['id']]);
        }

        $db->prepare('DELETE FROM cp WHERE id = ?')->execute([$oldAikId]);

        // Susun sub: AL Islam (2 pertama) lalu Kemuhammadiyahan (2 berikutnya)
        $order = [
            'Al-Qur\'an dan Ibadah' => 1,
            'Keimanan dan akhlak' => 2,
            'Pengenalan nilai KeMuhammadiyahan' => 3,
            'Keterampilan hidup dasar' => 4,
        ];
        $updSub = $db->prepare('UPDATE sub_cp SET urutan = ? WHERE cp_id = ? AND nama = ?');
        foreach ($order as $namaSub => $u) $updSub->execute([$u, $newId, $namaSub]);

        // Normalisasi urutan CP lain (AIK = 0 sudah paling atas)
        $i = 1;
        $others = $db->query('SELECT id FROM cp WHERE id != ' . $newId . ' ORDER BY urutan, id')->fetchAll();
        $updCp = $db->prepare('UPDATE cp SET urutan = ? WHERE id = ?');
        foreach ($others as $o) $updCp->execute([$i++, $o['id']]);

        $db->commit();
    }
}

// ---- Seed ----
$cnt = (int)$db->query('SELECT COUNT(*) c FROM users')->fetch()['c'];
if ($cnt === 0) {
    $db->prepare('INSERT INTO users (username, password, role) VALUES (?, ?, ?)')
       ->execute(['admin', password_hash('admin123', PASSWORD_DEFAULT), 'super_admin']);
}

// Seed profil contoh untuk super_admin bila belum ada profil sama sekali
$cnt = (int)$db->query('SELECT COUNT(*) c FROM profil')->fetch()['c'];
if ($cnt === 0) {
    $adminId = (int)$db->query("SELECT MIN(id) AS mid FROM users WHERE role IN ('admin','super_admin')")->fetch()['mid'];
    if ($adminId > 0) {
        $db->prepare("INSERT INTO profil (user_id, nama_sekolah, jenjang, status, footer, tahun_ajaran, semester)
                      VALUES (?, 'TK ISLAM ASSHIDIQ', 'TK', 'Swasta', 'SAHABAT GURU', '2025/2026', 'Ganjil')")
           ->execute([$adminId]);
    }
}
// Pastikan setiap user punya baris profil (termasuk user yang baru dibuat setelah seed)
$db->exec("INSERT INTO profil (user_id)
           SELECT u.id FROM users u
           WHERE NOT EXISTS (SELECT 1 FROM profil p WHERE p.user_id = u.id)");

$cnt = (int)$db->query('SELECT COUNT(*) c FROM cp')->fetch()['c'];
if ($cnt === 0) {
    $cps = [
        ['AL ISLAM, KE\'AISYIYAHAN DAN KEMUHAMMADIYAHAN (AIK)', [
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
        ]],
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

// ---- Migrasi multi-user: kepemilikan anak, sharing & riwayat revisi ----
try {
    $db->exec('ALTER TABLE anak ADD COLUMN user_id INTEGER DEFAULT 0');
} catch (Exception $e) {}
// Data lama dimiliki oleh user pertama (admin)
$db->exec('UPDATE anak SET user_id = (SELECT MIN(id) FROM users)
           WHERE user_id = 0 OR user_id IS NULL');

// Berbagi data anak antar user (editor = boleh ubah, viewer = hanya lihat)
$db->exec("CREATE TABLE IF NOT EXISTS share (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    anak_id INTEGER NOT NULL,
    user_id INTEGER NOT NULL,
    role TEXT NOT NULL DEFAULT 'editor',
    created_by INTEGER DEFAULT 0,
    created_at TEXT DEFAULT '',
    UNIQUE(anak_id, user_id),
    FOREIGN KEY (anak_id) REFERENCES anak(id) ON DELETE CASCADE
)");

// Riwayat perubahan data (siapa, kapan, apa yang diubah)
$db->exec("CREATE TABLE IF NOT EXISTS revisi (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    anak_id INTEGER DEFAULT 0,
    tabel TEXT NOT NULL,
    row_key TEXT DEFAULT '',
    user_id INTEGER DEFAULT 0,
    aksi TEXT NOT NULL,
    ringkasan TEXT DEFAULT '',
    created_at TEXT DEFAULT ''
)");
$db->exec('CREATE INDEX IF NOT EXISTS idx_revisi_anak ON revisi(anak_id)');
