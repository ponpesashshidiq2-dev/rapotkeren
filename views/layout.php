<?php
function nav_icon($key) {
    $icons = [
        'dashboard' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/></svg>',
        'anak'      => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="9" cy="7" r="4"/><path d="M2 21v-2a4 4 0 0 1 4-4h6a4 4 0 0 1 4 4v2"/><circle cx="19" cy="8" r="3"/><path d="M17 21v-1.5a3.5 3.5 0 0 1 3.5-3.5H22"/></svg>',
        'capaian'   => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 5H7a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2h-2"/><rect x="9" y="3" width="6" height="4" rx="1"/><path d="m9 14 2 2 4-4"/></svg>',
        'penilaian' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20h9"/><path d="M16.5 3.5a2.12 2.12 0 0 1 3 3L7 19l-4 1 1-4Z"/></svg>',
        'cetak'     => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="8"/></svg>',
        'profil'    => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 21h18"/><path d="M5 21V7l7-4 7 4v14"/><path d="M9 21v-4h6v4"/><path d="M9 10h.01M15 10h.01M9 13h.01M15 13h.01"/></svg>',
        'logout'    => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>',
    ];
    return $icons[$key] ?? '';
}

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
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="assets/app.css">
</head>
<body>
<div class="app">
    <aside class="sidebar">
        <div class="brand">
            <div class="brand-badge">✿</div>
            <div class="brand-info">
                <span class="brand-title">Raport TK</span>
                <span class="brand-tag">Sistem Penilaian PAUD</span>
            </div>
        </div>
        <nav>
        <?php foreach ($nav as $key => [$label, $url]): ?>
            <a href="<?= e($url) ?>" class="<?= $active === $key ? 'active' : '' ?>">
                <span class="nav-icon"><?= nav_icon($key) ?></span>
                <span class="nav-label"><?= e($label) ?></span>
            </a>
        <?php endforeach; ?>
        </nav>
        <div class="sidebar-footer">
            <a class="logout" href="index.php?route=logout">
                <span class="nav-icon"><?= nav_icon('logout') ?></span>
                <span>Keluar (<?= e($_SESSION['user']) ?>)</span>
            </a>
        </div>
    </aside>
    <main class="content">
        <div class="content-header">
            <div>
                <div class="breadcrumbs">Aplikasi Raport &rsaquo; <span><?= e($title) ?></span></div>
                <h1 class="page-title"><?= e($title) ?></h1>
            </div>
        </div>
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
