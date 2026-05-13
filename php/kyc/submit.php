<?php
require '../config/db.php';
requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('/kyc.php');
}

$nin = trim($_POST['nin'] ?? '');
$bvn = trim($_POST['bvn'] ?? '');

if (!$nin || !$bvn) {
    redirect('/kyc.php', 'error', 'Both NIN and BVN are required.');
}
if (!preg_match('/^\d{11}$/', $nin)) {
    redirect('/kyc.php', 'error', 'NIN must be exactly 11 digits.');
}
if (!preg_match('/^\d{11}$/', $bvn)) {
    redirect('/kyc.php', 'error', 'BVN must be exactly 11 digits.');
}

$stmt = $pdo->prepare("SELECT id FROM kyc WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
if ($stmt->fetch()) {
    redirect('/kyc.php', 'error', 'You have already submitted your KYC.');
}

$stmt = $pdo->prepare("INSERT INTO kyc (user_id, nin, bvn, status) VALUES (?, ?, ?, 'verified')");
$stmt->execute([$_SESSION['user_id'], $nin, $bvn]);

redirect('/kyc.php', 'success', 'KYC verified successfully.');
?>
