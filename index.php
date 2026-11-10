<?php
require_once __DIR__ . '/config.php';

$route = $_GET['route'] ?? 'dashboard';
$method = $_SERVER['REQUEST_METHOD'];

// Routes publik (tanpa login)
if ($route === 'login') {
    $error = '';
    if ($method === 'POST') {
        $u = trim($_POST['username'] ?? '');
        $p = $_POST['password'] ?? '';
        $st = $db->prepare('SELECT * FROM users WHERE username = ?');
        $st->execute([$u]);
        $user = $st->fetch();
        if ($user && password_verify($p, $user['password'])) {
            $_SESSION['user'] = $user['username'];
            redirect('index.php');
        }
        $error = 'Username atau password salah.';
    }
    require __DIR__ . '/views/login.php';
    exit;
}

if ($route === 'logout') {
    session_destroy();
    redirect('index.php?route=login');
}

requireLogin();

// Routes terproteksi
switch ($route) {
    case 'dashboard':
        require __DIR__ . '/views/dashboard.php';
        break;

    case 'profil':
        require __DIR__ . '/views/profil.php';
        break;

    case 'anak':
        require __DIR__ . '/views/anak_list.php';
        break;

    case 'anak_form':
        require __DIR__ . '/views/anak_form.php';
        break;

    case 'anak_import':
        require __DIR__ . '/views/anak_import.php';
        break;

    case 'anak_delete':
        if ($method === 'POST') {
            $id = (int)($_POST['id'] ?? 0);
            $st = $db->prepare('SELECT foto FROM anak WHERE id = ?');
            $st->execute([$id]);
            $row = $st->fetch();
            if ($row) {
                deleteUpload($row['foto']);
                $db->prepare('DELETE FROM anak WHERE id = ?')->execute([$id]);
                flash('Data anak dihapus.');
            }
        }
        redirect('index.php?route=anak');
        break;

    case 'capaian':
        require __DIR__ . '/views/capaian.php';
        break;

    case 'penilaian':
        require __DIR__ . '/views/penilaian.php';
        break;

    case 'foto':
        require __DIR__ . '/views/foto.php';
        break;

    case 'cetak':
        require __DIR__ . '/views/cetak.php';
        break;

    case 'cetak_print':
        require __DIR__ . '/views/cetak_print.php';
        break;

    default:
        require __DIR__ . '/views/dashboard.php';
}
