<?php
function page_header($title, $active = '') {
    $nav = [
        'dashboard' => ['Dashboard', 'index.php?route=dashboard'],
        'anak'      => ['Data Anak', 'index.php?route=anak'],
        'capaian'   => ['Data Capaian', 'index.php?route=capaian'],
        'penilaian' => ['Penilaian', 'index.php?route=penilaian'],
        'cetak'     => ['Cetak', 'index.php?route=cetak'],
        'profil'    => ['Profil', 'index.php?route=profil'],
    ];
    $flash = flash();
    ?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($title) ?> - Raport TK</title>
<link rel="stylesheet" href="assets/app.css">
</head>
<body>
<div class="app">
    <aside class="sidebar">
        <div class="brand">✿ Raport TK</div>
        <nav>
        <?php foreach ($nav as $key => [$label, $url]): ?>
            <a href="<?= e($url) ?>" class="<?= $active === $key ? 'active' : '' ?>"><?= e($label) ?></a>
        <?php endforeach; ?>
        </nav>
        <a class="logout" href="index.php?route=logout">Keluar (<?= e($_SESSION['user']) ?>)</a>
    </aside>
    <main class="content">
        <h1 class="page-title"><?= e($title) ?></h1>
        <?php if ($flash): ?>
            <div class="alert alert-<?= e($flash['type']) ?>"><?= e($flash['msg']) ?></div>
        <?php endif; ?>
<?php }

function page_footer() {
    ?>
    </main>
</div>
</body>
</html>
    <?php
}
