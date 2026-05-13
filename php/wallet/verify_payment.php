<?php
/**
 * php/wallet/verify_payment.php
 * Paystack redirects here after payment.
 * Verifies the transaction and credits the wallet on success.
 */
require '../../php/config/db.php';
requireAdmin();

require_once __DIR__ . '/wallet_helpers.php';

define('PAYSTACK_SECRET_KEY', 'sk_test_2bae7c07ad96a414b6fb8d7ef03830c8cba771bf'); // ← same key as initiate_payment.php

$reference = isset($_GET['reference']) ? trim($_GET['reference']) : '';

if (empty($reference)) {
    $_SESSION['wallet_error'] = 'Invalid payment reference.';
    header('Location: /loanapp/admin_wallet.php');
    exit;
}

// ── Verify with Paystack ──────────────────────────────────────────────────────
$ch = curl_init("https://api.paystack.co/transaction/verify/{$reference}");
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER     => [
        'Authorization: Bearer ' . PAYSTACK_SECRET_KEY,
    ],
]);

$response  = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

$result = json_decode($response, true);

if ($http_code !== 200 || !$result['status']) {
    // Mark as failed in DB
    $pdo->prepare(
        "UPDATE wallet_transactions SET status = 'failed' WHERE reference = ? AND status = 'pending'"
    )->execute([$reference]);

    $_SESSION['wallet_error'] = 'Payment verification failed. Contact support if money was deducted.';
    header('Location: /loanapp/admin_wallet.php');
    exit;
}

$tx_data = $result['data'];

if ($tx_data['status'] !== 'success') {
    $pdo->prepare(
        "UPDATE wallet_transactions SET status = 'failed' WHERE reference = ? AND status = 'pending'"
    )->execute([$reference]);

    $_SESSION['wallet_error'] = 'Payment was not successful (' . $tx_data['status'] . '). No funds were added.';
    header('Location: /loanapp/admin_wallet.php');
    exit;
}

// Amount Paystack confirms (in kobo) → convert to Naira
$amount_naira = $tx_data['amount'] / 100;

// Credit wallet (helper prevents double-credit via reference check)
$credited = creditWallet($pdo, $amount_naira, $reference);

if ($credited) {
    $_SESSION['wallet_success'] = '₦' . number_format($amount_naira, 2) . ' has been added to the global wallet.';
} else {
    $_SESSION['wallet_error'] = 'This payment reference has already been processed or an error occurred.';
}

header('Location: /loanapp/admin_wallet.php');
exit;
