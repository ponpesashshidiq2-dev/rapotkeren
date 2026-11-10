<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', '1');

define('BASE_DIR', __DIR__);
define('DB_PATH', BASE_DIR . '/data/raport.sqlite');
define('UPLOAD_DIR', BASE_DIR . '/uploads');

$db = new PDO('sqlite:' . DB_PATH);
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
$db->exec('PRAGMA foreign_keys = ON');

require_once __DIR__ . '/schema.php';

function e($s) {
    return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}

function redirect($url) {
    header('Location: ' . $url);
    exit;
}

function isLoggedIn() {
    return !empty($_SESSION['user']);
}

function requireLogin() {
    if (!isLoggedIn()) {
        redirect('index.php?route=login');
    }
}

function uploadFile($field, $subdir, $allowedExt = ['jpg', 'jpeg', 'png', 'webp', 'gif']) {
    if (empty($_FILES[$field]['name']) || $_FILES[$field]['error'] !== UPLOAD_ERR_OK) {
        return null;
    }
    $orig = $_FILES[$field]['name'];
    $ext = strtolower(pathinfo($orig, PATHINFO_EXTENSION));
    if (!in_array($ext, $allowedExt, true)) {
        return null;
    }
    $name = uniqid('img_', true) . '.' . $ext;
    $dir = UPLOAD_DIR . '/' . $subdir;
    if (!is_dir($dir)) {
        mkdir($dir, 0777, true);
    }
    $dest = $dir . '/' . $name;
    if (move_uploaded_file($_FILES[$field]['tmp_name'], $dest)) {
        return 'uploads/' . $subdir . '/' . $name;
    }
    return null;
}

function deleteUpload($relPath) {
    if (!$relPath) return;
    $p = BASE_DIR . '/' . $relPath;
    if (strpos($p, UPLOAD_DIR) === 0 && is_file($p)) {
        @unlink($p);
    }
}

function getProfil($db) {
    $row = $db->query('SELECT * FROM profil LIMIT 1')->fetch();
    return $row ?: [];
}

function flash($msg = null, $type = 'success') {
    if ($msg !== null) {
        $_SESSION['flash'] = ['msg' => $msg, 'type' => $type];
        return null;
    }
    if (!empty($_SESSION['flash'])) {
        $f = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $f;
    }
    return null;
}
