<?php
$title = 'Login';
$profil = getProfil($db);
$namaSekolah = !empty($profil['nama_sekolah']) ? $profil['nama_sekolah'] : '';
// Logo elegan untuk halaman login (identitas produk); logo sekolah tetap tampil sebagai lambang kecil bila ada
$logoElegant = 'assets/logo-elegant.svg';
$logoSekolah = !empty($profil['logo']) && file_exists(BASE_DIR . '/' . $profil['logo'])
    ? $profil['logo']
    : (file_exists(BASE_DIR . '/assets/logo.png') ? 'assets/logo.png' : '');
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Login — Laporan Perkembangan Anak Didik</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,500;9..144,600;9..144,700&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="assets/app.css">
</head>
<body class="login-page">
<div class="login-box login-box-elegant">
    <div class="login-brand">
        <div class="login-logo login-logo-elegant">
            <img src="<?= e($logoElegant) ?>" alt="Logo Laporan Perkembangan">
        </div>
        <?php if ($logoSekolah): ?>
            <img class="login-school-seal" src="<?= e($logoSekolah) ?>" alt="<?= e($namaSekolah) ?>">
        <?php endif; ?>
    </div>

    <p class="login-eyebrow">Pendidikan Anak Usia Dini</p>
    <h1>Laporan Perkembangan<br><em>Anak Didik</em></h1>
    <?php if ($error): ?>
        <div class="alert alert-error"><?= e($error) ?></div>
    <?php endif; ?>
    <form method="post" action="index.php?route=login">
        <label>Username</label>
        <input type="text" name="username" required autofocus>
        <label>Password</label>
        <input type="password" name="password" required>
        <button type="submit" class="btn btn-primary btn-block">Masuk</button>
    </form>
    <p class="login-footnote">Hak akses terbatas · Data anak terlindungi</p>
</div>
</body>
</html>
