<?php
require '../../php/config/db.php';
requireLogin();

define('PAYSTACK_SECRET_KEY', 'sk_test_2bae7c07ad96a414b6fb8d7ef03830c8cba771bf'); // ← your secret key

$reference = trim($_GET['reference'] ?? '');
if (!$reference) {
    $_SESSION['savings_error'] = 'Invalid reference.';
    header('Location: /loanapp/dashboard.php'); exit;
}

// Verify with Paystack
$ch = curl_init("https://api.paystack.co/transaction/verify/{$reference}");
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER     => ['Authorization: Bearer ' . PAYSTACK_SECRET_KEY],
]);
$result    = json_decode(curl_exec($ch), true);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($http_code !== 200 || !$result['status'] || $result['data']['status'] !== 'success') {
    $_SESSION['savings_error'] = 'Payment verification failed.';
    header('Location: /loanapp/dashboard.php'); exit;
}

$amount_naira = $result['data']['amount'] / 100;
$uid          = $_SESSION['user_id'];

// Prevent double credit
$exists = $pdo->prepare("SELECT id FROM savings_transactions WHERE reference = ? AND status = 'success'");
$exists->execute([$reference]);
if ($exists->fetch()) {
    $_SESSION['savings_error'] = 'This payment has already been processed.';
    header('Location: /loanapp/dashboard.php'); exit;
}

// Credit savings
$pdo->beginTransaction();
$pdo->prepare("UPDATE users SET savings_balance = savings_balance + ? WHERE id = ?")->execute([$amount_naira, $uid]);
$pdo->prepare("INSERT INTO savings_transactions (user_id, type, amount, description, reference, status) VALUES (?, 'deposit', ?, 'Savings deposit via Paystack', ?, 'success')")->execute([$uid, $amount_naira, $reference]);
$pdo->commit();

$_SESSION['savings_success'] = '₦' . number_format($amount_naira, 2) . ' added to your savings wallet.';
header('Location: /loanapp/dashboard.php');