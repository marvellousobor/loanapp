<?php
require '../config/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('/html/register.html');
}

$name           = trim($_POST['name'] ?? '');
$email          = trim($_POST['email'] ?? '');
$pass           = $_POST['password'] ?? '';
$pass2          = $_POST['password2'] ?? '';
$monthly_income = floatval($_POST['monthly_income'] ?? 0);

if (!$name) {
    redirect('/html/register.html', 'error', 'Full name is required.');
}
if (!preg_match('/^[a-zA-Z\s]+$/', $name)) {
    redirect('/html/register.html', 'error', 'Name must contain letters only, no numbers.');
}
if (!$email) {
    redirect('/html/register.html', 'error', 'Email is required.');
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    redirect('/html/register.html', 'error', 'Enter a valid email address.');
}
$allowed_domains = ['gmail.com','yahoo.com','outlook.com','hotmail.com','icloud.com','live.com'];
$email_domain = strtolower(substr(strrchr($email, '@'), 1));
if (!in_array($email_domain, $allowed_domains)) {
    redirect('/html/register.html', 'error', 'Use a valid email provider (e.g. @gmail.com, @yahoo.com).');
}
if (strlen($pass) < 8) {
    redirect('/html/register.html', 'error', 'Password must be at least 8 characters.');
}
if (!preg_match('/[A-Z]/', $pass)) {
    redirect('/html/register.html', 'error', 'Password must contain at least one uppercase letter.');
}
if (!preg_match('/[a-z]/', $pass)) {
    redirect('/html/register.html', 'error', 'Password must contain at least one lowercase letter.');
}
if (!preg_match('/[0-9]/', $pass)) {
    redirect('/html/register.html', 'error', 'Password must contain at least one number.');
}
if (!preg_match('/[\W_]/', $pass)) {
    redirect('/html/register.html', 'error', 'Password must contain at least one special character (e.g. @, #, !).');
}
if ($pass !== $pass2) {
    redirect('/html/register.html', 'error', 'Passwords do not match.');
}
if ($monthly_income <= 0) {
    redirect('/html/register.html', 'error', 'Enter your monthly income.');
}

$stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
$stmt->execute([$email]);
if ($stmt->fetch()) {
    redirect('/html/register.html', 'error', 'This email is already registered.');
}

$hashed = password_hash($pass, PASSWORD_DEFAULT);
$stmt   = $pdo->prepare("INSERT INTO users (name, email, password, monthly_income) VALUES (?, ?, ?, ?)");
$stmt->execute([$name, $email, $hashed, $monthly_income]);

redirect('/html/register.html', 'success', '1');
?>
