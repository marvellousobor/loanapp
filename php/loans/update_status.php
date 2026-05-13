<?php
require '../config/db.php';
requireAdmin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('/loans.php');
}

$loan_id = intval($_POST['loan_id'] ?? 0);
$status  = $_POST['status'] ?? '';

if (!$loan_id || !in_array($status, ['pending', 'approved', 'rejected'])) {
    redirect('/loans.php', 'error', 'Invalid request.');
}

$stmt = $pdo->prepare("SELECT * FROM loans WHERE id = ?");
$stmt->execute([$loan_id]);
$loan = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$loan) {
    redirect('/loans.php', 'error', 'Loan not found.');
}

$old_status = $loan['status'];

$stmt = $pdo->prepare("UPDATE loans SET status = ? WHERE id = ?");
$stmt->execute([$status, $loan_id]);

if ($status === 'approved' && $old_status !== 'approved') {

// ── NEW: Check & debit the global admin wallet ──────────────
    require_once __DIR__ . '/../wallet/wallet_helpers.php';

    $wallet_bal = getWalletBalance($pdo);
    if ($wallet_bal < (float)$loan['amount']) {
        // Undo the status update we already did
        $pdo->prepare("UPDATE loans SET status = ? WHERE id = ?")->execute([$old_status, $loan_id]);
        redirect('/loans.php', 'error',
            'Insufficient wallet balance (₦' . number_format($wallet_bal, 2) . '). Fund the wallet first.');
    }

    debitWallet($pdo, (float)$loan['amount'], $loan_id);

    $stmt = $pdo->prepare("UPDATE users SET wallet_balance = wallet_balance + ? WHERE id = ?");
    $stmt->execute([$loan['amount'], $loan['user_id']]);

    $stmt = $pdo->prepare("INSERT INTO wallet_transactions (user_id, loan_id, amount, type, description) VALUES (?, ?, ?, 'credit', ?)");
    $stmt->execute([$loan['user_id'], $loan_id, $loan['amount'], 'Loan #' . $loan_id . ' approved and credited']);
}

if ($old_status === 'approved' && $status !== 'approved') {
    $stmt = $pdo->prepare("UPDATE users SET wallet_balance = wallet_balance - ? WHERE id = ?");
    $stmt->execute([$loan['amount'], $loan['user_id']]);

    $stmt = $pdo->prepare("INSERT INTO wallet_transactions (user_id, loan_id, amount, type, description) VALUES (?, ?, ?, 'debit', ?)");
    $stmt->execute([$loan['user_id'], $loan_id, $loan['amount'], 'Loan #' . $loan_id . ' approval reversed']);
}

redirect('/loans.php', 'success', 'Loan status updated.');
?>
