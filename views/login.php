<?php $title = 'Login'; ?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Login - Raport TK</title>
<link rel="stylesheet" href="assets/app.css">
</head>
<body class="login-page">
<div class="login-box">
    <div class="login-logo">✿</div>
    <h1>Raport TK</h1>
    <p class="muted">Laporan Perkembangan Anak Didik</p>
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
