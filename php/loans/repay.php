<?php
require '../../php/config/db.php';
requireLogin();

$loan_id = (int)($_POST['loan_id'] ?? 0);
$uid     = $_SESSION['user_id'];

if (!$loan_id) {
    $_SESSION['savings_error'] = 'Invalid loan.';
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

// Fetch savings balance
$stmt = $pdo->prepare("SELECT savings_balance FROM users WHERE id = ?");
$stmt->execute([$uid]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if ($user['savings_balance'] < $loan['amount']) {
    $_SESSION['savings_error'] = 'Insufficient savings balance.';
    header('Location: /loanapp/dashboard.php'); exit;
}

// Deduct savings + mark loan repaid
$pdo->beginTransaction();
$pdo->prepare("UPDATE users SET savings_balance = savings_balance - ? WHERE id = ?")->execute([$loan['amount'], $uid]);
$pdo->prepare("UPDATE loans SET status = 'repaid' WHERE id = ?")->execute([$loan_id]);
$pdo->prepare("INSERT INTO savings_transactions (user_id, type, amount, description, status) VALUES (?, 'repayment', ?, ?, 'success')")->execute([$uid, $loan['amount'], 'Loan #' . $loan_id . ' repayment from savings']);
$pdo->commit();

$_SESSION['savings_success'] = 'Loan #' . $loan_id . ' repaid successfully from your savings.';
header('Location: /loanapp/dashboard.php');