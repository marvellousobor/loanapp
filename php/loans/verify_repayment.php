<?php
require '../../php/config/db.php';
requireLogin();

define('PAYSTACK_SECRET_KEY', 'sk_test_2bae7c07ad96a414b6fb8d7ef03830c8cba771bf'); // ← your secret key

$reference = trim($_GET['reference'] ?? '');
$loan_id   = (int)($_GET['loan_id'] ?? 0);
$uid       = $_SESSION['user_id'];

if (!$reference || !$loan_id) {
    $_SESSION['savings_error'] = 'Invalid request.';
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

// Prevent double processing
$exists = $pdo->prepare("SELECT id FROM savings_transactions WHERE reference = ? AND status = 'success'");
$exists->execute([$reference]);
if ($exists->fetch()) {
    $_SESSION['savings_error'] = 'Already processed.';
    header('Location: /loanapp/dashboard.php'); exit;
}

// Fetch loan
$stmt = $pdo->prepare("SELECT * FROM loans WHERE id = ? AND user_id = ? AND status = 'approved'");
$stmt->execute([$loan_id, $uid]);
$loan = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$loan) {
    $_SESSION['savings_error'] = 'Loan not found or already repaid.';
    header('Location: /loanapp/dashboard.php'); exit;
}

$amount_naira = $result['data']['amount'] / 100;

// Mark loan repaid + log transaction
$pdo->beginTransaction();
$pdo->prepare("UPDATE loans SET status = 'repaid' WHERE id = ?")->execute([$loan_id]);
$pdo->prepare("INSERT INTO savings_transactions (user_id, type, amount, description, reference, status) VALUES (?, 'repayment', ?, ?, ?, 'success')")->execute([$uid, $amount_naira, 'Loan #' . $loan_id . ' repayment via Paystack', $reference]);
$pdo->commit();

$_SESSION['savings_success'] = 'Loan #' . $loan_id . ' repaid successfully via Paystack.';
header('Location: /loanapp/dashboard.php');