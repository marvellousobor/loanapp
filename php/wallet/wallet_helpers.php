<?php
/**
 * Wallet helpers — include after db.php is already required
 */

/**
 * Returns the current global wallet balance.
 */
function getWalletBalance(PDO $pdo): float {
    $bal = $pdo->query("SELECT balance FROM wallet WHERE id = 1")->fetchColumn();
    return $bal !== false ? (float)$bal : 0.00;
}

/**
 * Credits the wallet and logs the transaction.
 * Call this only after Paystack has confirmed a successful payment.
 *
 * @param PDO    $pdo
 * @param float  $amount     Amount in Naira (NOT kobo)
 * @param string $reference  Paystack reference
 * @param string $desc       Human-readable description
 * @return bool
 */
function creditWallet(PDO $pdo, float $amount, string $reference, string $desc = 'Admin top-up via Paystack'): bool {
    try {
        $pdo->beginTransaction();

        // Prevent double-crediting the same reference
        $exists = $pdo->prepare("SELECT id FROM wallet_transactions WHERE reference = ? AND status = 'success'");
        $exists->execute([$reference]);
        if ($exists->fetch()) {
            $pdo->rollBack();
            return false; // already processed
        }

        // Update wallet balance
        $pdo->prepare("UPDATE wallet SET balance = balance + ? WHERE id = 1")->execute([$amount]);

        // Log the transaction
        $pdo->prepare(
            "INSERT INTO wallet_transactions (type, amount, description, reference, status)
             VALUES ('credit', ?, ?, ?, 'success')"
        )->execute([$amount, $desc, $reference]);

        $pdo->commit();
        return true;
    } catch (Exception $e) {
        $pdo->rollBack();
        error_log('creditWallet error: ' . $e->getMessage());
        return false;
    }
}

/**
 * Debits the wallet when a loan is approved.
 *
 * @param PDO    $pdo
 * @param float  $amount
 * @param int    $loan_id
 * @param string $borrower_name
 * @return bool  false if insufficient funds
 */
function debitWallet(PDO $pdo, float $amount, int $loan_id, string $borrower_name = ''): bool {
    try {
        $pdo->beginTransaction();

        $balance = getWalletBalance($pdo);
        if ($balance < $amount) {
            $pdo->rollBack();
            return false; // insufficient funds
        }

        $pdo->prepare("UPDATE wallet SET balance = balance - ? WHERE id = 1")->execute([$amount]);

        $desc = $borrower_name
            ? "Loan disbursed to {$borrower_name} (Loan #{$loan_id})"
            : "Loan disbursed (Loan #{$loan_id})";

        $pdo->prepare(
            "INSERT INTO wallet_transactions (type, amount, description, loan_id, status)
             VALUES ('debit', ?, ?, ?, 'success')"
        )->execute([$amount, $desc, $loan_id]);

        $pdo->commit();
        return true;
    } catch (Exception $e) {
        $pdo->rollBack();
        error_log('debitWallet error: ' . $e->getMessage());
        return false;
    }
}
