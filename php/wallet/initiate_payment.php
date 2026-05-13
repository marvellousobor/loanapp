<?php
/**
 * php/wallet/initiate_payment.php
 * Initiates a Paystack payment to top up the admin global wallet.
 * Called via POST from admin_wallet.php
 */
require '../../php/config/db.php';
requireAdmin();

// ── Config ────────────────────────────────────────────────────────────────────
define('PAYSTACK_SECRET_KEY', 'sk_test_2bae7c07ad96a414b6fb8d7ef03830c8cba771bf'); // ← replace
define('PAYSTACK_CALLBACK_URL', 'https://yourdomain.com/loanapp/php/wallet/verify_payment.php'); // ← replace

// ── Validate input ────────────────────────────────────────────────────────────
$amount_naira = isset($_POST['amount']) ? (float)$_POST['amount'] : 0;

if ($amount_naira < 100) {
    $_SESSION['wallet_error'] = 'Minimum top-up amount is ₦100.';
    header('Location: /loanapp/admin_wallet.php');
    exit;
}

// Paystack expects amount in kobo (1 Naira = 100 kobo)
$amount_kobo = (int)($amount_naira * 100);

// Get admin email from session / DB
$admin = $pdo->prepare("SELECT email FROM users WHERE id = ? AND is_admin = 1");
$admin->execute([$_SESSION['user_id']]);
$admin_row = $admin->fetch(PDO::FETCH_ASSOC);

if (!$admin_row) {
    $_SESSION['wallet_error'] = 'Admin account not found.';
    header('Location: /loanapp/admin_wallet.php');
    exit;
}

// ── Initiate Paystack transaction ─────────────────────────────────────────────
$payload = json_encode([
    'email'        => $admin_row['email'],
    'amount'       => $amount_kobo,
    'callback_url' => PAYSTACK_CALLBACK_URL,
    'metadata'     => [
        'custom_fields' => [[
            'display_name' => 'Top-up Type',
            'variable_name' => 'top_up_type',
            'value'         => 'Admin Wallet',
        ]],
    ],
]);

$ch = curl_init('https://api.paystack.co/transaction/initialize');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => $payload,
    CURLOPT_HTTPHEADER     => [
        'Authorization: Bearer ' . PAYSTACK_SECRET_KEY,
        'Content-Type: application/json',
    ],
]);

$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

$result = json_decode($response, true);

if ($http_code !== 200 || !$result['status']) {
    $msg = $result['message'] ?? 'Unable to connect to Paystack. Try again.';
    $_SESSION['wallet_error'] = $msg;
    header('Location: /loanapp/admin_wallet.php');
    exit;
}

// Log a pending transaction so we have a record before the user pays
$ref = $result['data']['reference'];
$pdo->prepare(
    "INSERT INTO wallet_transactions (type, amount, description, reference, status)
     VALUES ('credit', ?, 'Admin top-up via Paystack (pending)', ?, 'pending')"
)->execute([$amount_naira, $ref]);

// Redirect to Paystack hosted checkout
header('Location: ' . $result['data']['authorization_url']);
exit;
