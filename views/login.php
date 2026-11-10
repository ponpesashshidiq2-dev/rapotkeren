<?php
$title = 'Login';
$profil = getProfil($db);
$logoSrc = !empty($profil['logo']) && file_exists(BASE_DIR . '/' . $profil['logo'])
    ? $profil['logo']
    : (file_exists(BASE_DIR . '/assets/logo.png') ? 'assets/logo.png' : '');
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Login - Raport TK</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="assets/app.css">
</head>
<body class="login-page">
<div class="login-box">
    <?php if ($logoSrc): ?>
        <div class="login-logo login-logo-img">
            <img src="<?= e($logoSrc) ?>" alt="Logo Sekolah">
        </div>
    <?php else: ?>
        <div class="login-logo">✿</div>
    <?php endif; ?>
    <h1>Raport TK</h1>
    <p class="muted"><?= !empty($profil['nama_sekolah']) ? e($profil['nama_sekolah']) : 'Laporan Perkembangan Anak Didik' ?></p>
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
</div>
</body>
</html>
