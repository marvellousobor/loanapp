<?php
define('BASE', '/loanapp');

session_start();

$host = 'localhost';
$db   = 'loanapp';
$user = 'root';
$pass = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("DB Error: " . $e->getMessage());
}

function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function isAdmin() {
    return isset($_SESSION['is_admin']) && $_SESSION['is_admin'] == 1;
}

function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: ' . BASE . '/html/login.html');
        exit;
    }
}

function requireAdmin() {
    requireLogin();
    if (!isAdmin()) {
        header('Location: ' . BASE . '/dashboard.php');
        exit;
    }
}

function redirect($path, $param = '', $value = '') {
    if ($param && $value) {
        header('Location: ' . BASE . $path . '?' . $param . '=' . urlencode($value));
    } else {
        header('Location: ' . BASE . $path);
    }
    exit;
}
?>
