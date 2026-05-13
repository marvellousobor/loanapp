<?php
require '../config/db.php';
requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('/html/apply.html');
}

$amount  = floatval($_POST['amount'] ?? 0);
$purpose = trim($_POST['purpose'] ?? '');

if ($amount <= 0) {
    redirect('/html/apply.html', 'error', 'Enter a valid loan amount.');
}
if (!$purpose) {
    redirect('/html/apply.html', 'error', 'Loan purpose is required.');
}

$stmt = $pdo->prepare("SELECT monthly_income FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

$config = $pdo->query("SELECT * FROM loan_config LIMIT 1")->fetch(PDO::FETCH_ASSOC);
$max_borrowable = ($user['monthly_income'] * $config['max_loan_percent']) / 100;

if ($amount < $config['min_loan_amount']) {
    redirect('/html/apply.html', 'error', 'Minimum loan amount is ₦' . number_format($config['min_loan_amount'], 2));
}
if ($amount > $config['max_loan_amount']) {
    redirect('/html/apply.html', 'error', 'Maximum loan amount is ₦' . number_format($config['max_loan_amount'], 2));
}
if ($amount > $max_borrowable) {
    redirect('/html/apply.html', 'error', 'Based on your income, you can only borrow up to ₦' . number_format($max_borrowable, 2));
}

// ── Wallet balance check — user cannot borrow more than what admin has ─────
require_once __DIR__ . '/../wallet/wallet_helpers.php';
$wallet_bal = getWalletBalance($pdo);

if ($wallet_bal <= 0) {
    redirect('/html/apply.html', 'error', 'Loans are currently unavailable. Please check back later.');
}
if ($amount > $wallet_bal) {
    redirect('/html/apply.html', 'error', 
        'The requested amount exceeds available funds. Maximum you can borrow right now is ₦' 
        . number_format($wallet_bal, 2) . '.');
}
// ─────────────────────────────────────────────────────────────────────────────

$stmt = $pdo->prepare("SELECT status FROM kyc WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$kyc = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$kyc || $kyc['status'] !== 'verified') {
    redirect('/html/apply.html', 'error', 'You must complete KYC verification before applying.');
}

$stmt = $pdo->prepare("INSERT INTO loans (user_id, amount, purpose) VALUES (?, ?, ?)");
$stmt->execute([$_SESSION['user_id'], $amount, $purpose]);

redirect('/html/apply.html', 'success', '1');
?>