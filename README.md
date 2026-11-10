# Raport TK — Laporan Perkembangan Anak Didik

Aplikasi cetak raport TK/PAUD berbasis web dengan **PHP 8 + SQLite** (tanpa framework).

## Fitur

- **Login sederhana** — default `admin` / `admin123`
- **Profil Lembaga** — identitas satuan pendidikan + **upload logo** (tampil di cover cetak)
- **Data Anak** — data peserta didik, orang tua/wali, alamat, foto 3x4
- **Data Capaian** — 5 Capaian Pembelajaran (CP):
  1. Nilai Agama dan Budi Pekerti
  2. Jati Diri
  3. Dasar-dasar Literasi, Matematika, Sains, Teknologi, Rekayasa, dan Seni
  4. Kemuhammadiyah
  5. AIK (Alam dan Keterampilan Hidup)

  Tiap CP punya **sub-bidang** (mis. Emosional, Hubungan Sosial, Kesehatan & Keselamatan Diri) dan **capaian detail** (mis. berbagi, menunggu giliran) — semuanya **bisa ditambah/hapus** dari menu.
- **Penilaian Anak** — centang capaian detail per sub-bidang → **deskripsi otomatis diawali "Ananda + nama anak"** (bisa diedit manual), **upload 2 foto kegiatan + keterangan** per CP.
- **Cetak (satu menu)** — satu proses cetak menghasilkan dokumen lengkap:
  1. Cover — Data Satuan Pendidikan (dengan logo)
  2. Data Peserta Didik
  3. 5 halaman CP (deskripsi + dokumentasi foto)
  4. Lembar Pengesahan (tanda tangan dari Profil Lembaga)

## Cara Menjalankan

```bash
php -S localhost:8000
```

Buka `http://localhost:8000`, login dengan `admin` / `admin123`.

> **Catatan:** aplikasi membutuhkan ekstensi **pdo_sqlite** (biasanya aktif secara default).
> Bila belum aktif, salin/aktifkan `php.ini` yang disertakan di repositori ini.

## Struktur

```
├── index.php        # front controller + auth
├── config.php       # PDO SQLite + helper
├── schema.php       # buat tabel + seed (idempoten)
├── php.ini          # konfigurasi ekstensi SQLite
├── views/           # login, dashboard, profil, anak, capaian, penilaian, cetak
├── assets/          # app.css (UI), print.css (A4)
├── uploads/         # logo, foto 3x4, foto kegiatan
└── data/            # raport.sqlite (dibuat otomatis, tidak di-commit)
```

## Data Awal

| Item | Nilai |
|---|---|
| Username | `admin` |
| Password | `admin123` |

Ganti password dengan mengubah hash di tabel `users` (mis. via `php -r "echo password_hash('rahasia', PASSWORD_DEFAULT);"`)
