<?php
require 'php/config/db.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST');
header('Access-Control-Allow-Headers: Content-Type');

function response($data, $status = 200) {
    http_response_code($status);
    echo json_encode(['status' => $status, 'data' => $data], JSON_PRETTY_PRINT);
    exit;
}

function error($message, $status = 400) {
    http_response_code($status);
    echo json_encode(['status' => $status, 'error' => $message], JSON_PRETTY_PRINT);
    exit;
}

function requireAdmin() {
    if (!isset($_SESSION['user_id']) || $_SESSION['is_admin'] != 1) {
        error('Admin access only. Please log in as admin.', 403);
    }
}

function requireLogin() {
    if (!isset($_SESSION['user_id'])) {
        error('You must be logged in to access this.', 401);
    }
}

$method   = $_SERVER['REQUEST_METHOD'];
$endpoint = $_GET['endpoint'] ?? '';
$body     = json_decode(file_get_contents('php://input'), true) ?? [];

// ── GET: all users (admin only) ──
if ($endpoint === 'users' && $method === 'GET') {
    requireAdmin();

    $users = $pdo->query(
        "SELECT u.id, u.name, u.email, u.is_admin, u.wallet_balance,
                u.monthly_income, k.status AS kyc_status, u.created_at
         FROM users u
         LEFT JOIN kyc k ON u.id = k.user_id
         ORDER BY u.created_at DESC"
    )->fetchAll(PDO::FETCH_ASSOC);

    response($users);
}

// ── GET: single user (admin only) ──
if ($endpoint === 'user' && $method === 'GET') {
    requireAdmin();

    $id = intval($_GET['id'] ?? 0);
    if (!$id) error('User ID is required.');

    $stmt = $pdo->prepare(
        "SELECT u.id, u.name, u.email, u.is_admin, u.wallet_balance,
                u.monthly_income, k.status AS kyc_status, u.created_at
         FROM users u
         LEFT JOIN kyc k ON u.id = k.user_id
         WHERE u.id = ?"
    );
    $stmt->execute([$id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) error('User not found.', 404);
    response($user);
}

// ── GET: all loans ──
// Admin sees all, logged in user sees only their own
if ($endpoint === 'loans' && $method === 'GET') {
    requireLogin();

    if ($_SESSION['is_admin'] == 1) {
        $loans = $pdo->query(
            "SELECT l.*, u.name AS user_name, u.email AS user_email
             FROM loans l
             JOIN users u ON l.user_id = u.id
             ORDER BY l.created_at DESC"
        )->fetchAll(PDO::FETCH_ASSOC);
    } else {
        $stmt = $pdo->prepare(
            "SELECT l.*, u.name AS user_name, u.email AS user_email
             FROM loans l
             JOIN users u ON l.user_id = u.id
             WHERE l.user_id = ?
             ORDER BY l.created_at DESC"
        );
        $stmt->execute([$_SESSION['user_id']]);
        $loans = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    response($loans);
}

// ── GET: single loan ──
if ($endpoint === 'loan' && $method === 'GET') {
    requireLogin();

    $id = intval($_GET['id'] ?? 0);
    if (!$id) error('Loan ID is required.');

    $stmt = $pdo->prepare(
        "SELECT l.*, u.name AS user_name, u.email AS user_email
         FROM loans l
         JOIN users u ON l.user_id = u.id
         WHERE l.id = ?"
    );
    $stmt->execute([$id]);
    $loan = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$loan) error('Loan not found.', 404);

    // User can only see their own loan
    if ($_SESSION['is_admin'] != 1 && $loan['user_id'] != $_SESSION['user_id']) {
        error('Access denied.', 403);
    }

    response($loan);
}

// ── GET: wallet ──
// Admin sees any user's wallet, user sees only their own
if ($endpoint === 'wallet' && $method === 'GET') {
    requireLogin();

    $user_id = intval($_GET['user_id'] ?? 0);
    if (!$user_id) error('user_id is required.');

    // User can only see their own wallet
    if ($_SESSION['is_admin'] != 1 && $user_id != $_SESSION['user_id']) {
        error('You can only view your own wallet.', 403);
    }

    $stmt = $pdo->prepare("SELECT wallet_balance FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$user) error('User not found.', 404);

    $stmt = $pdo->prepare(
        "SELECT * FROM wallet_transactions WHERE user_id = ? ORDER BY created_at DESC"
    );
    $stmt->execute([$user_id]);
    $transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);

    response([
        'wallet_balance' => $user['wallet_balance'],
        'transactions'   => $transactions
    ]);
}

// ── GET: config (public) ──
if ($endpoint === 'config' && $method === 'GET') {
    $config = $pdo->query("SELECT * FROM loan_config LIMIT 1")->fetch(PDO::FETCH_ASSOC);
    response($config);
}

// ─────────────────────────────────────────────
// ALL POST ENDPOINTS — ADMIN ONLY BELOW HERE
// ─────────────────────────────────────────────

// ── POST: create user (admin only) ──
if ($endpoint === 'create_user' && $method === 'POST') {
    requireAdmin();

    $name           = trim($body['name'] ?? '');
    $email          = trim($body['email'] ?? '');
    $password       = $body['password'] ?? '';
    $monthly_income = floatval($body['monthly_income'] ?? 0);
    $is_admin       = intval($body['is_admin'] ?? 0);

    if (!$name)     error('Name is required.');
    if (!$email)    error('Email is required.');
    if (!$password) error('Password is required.');
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) error('Invalid email format.');
    if (strlen($password) < 8) error('Password must be at least 8 characters.');

    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->execute([$email]);
    if ($stmt->fetch()) error('Email already registered.');

    $hashed = password_hash($password, PASSWORD_DEFAULT);
    $stmt   = $pdo->prepare(
        "INSERT INTO users (name, email, password, monthly_income, is_admin) VALUES (?, ?, ?, ?, ?)"
    );
    $stmt->execute([$name, $email, $hashed, $monthly_income, $is_admin]);

    response(['message' => 'User created successfully.', 'user_id' => $pdo->lastInsertId()], 201);
}

// ── POST: apply for loan (admin only) ──
if ($endpoint === 'apply' && $method === 'POST') {
    requireAdmin();

    $user_id = intval($body['user_id'] ?? 0);
    $amount  = floatval($body['amount'] ?? 0);
    $purpose = trim($body['purpose'] ?? '');

    if (!$user_id) error('user_id is required.');
    if (!$amount)  error('Amount is required.');
    if (!$purpose) error('Purpose is required.');

    $stmt = $pdo->prepare("SELECT status FROM kyc WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $kyc = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$kyc || $kyc['status'] !== 'verified') {
        error('User has not completed KYC verification.');
    }

    $stmt = $pdo->prepare("SELECT monthly_income FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user   = $stmt->fetch(PDO::FETCH_ASSOC);
    $config = $pdo->query("SELECT * FROM loan_config LIMIT 1")->fetch(PDO::FETCH_ASSOC);
    $max    = ($user['monthly_income'] * $config['max_loan_percent']) / 100;

    if ($amount < $config['min_loan_amount']) {
        error('Amount below minimum of ₦' . number_format($config['min_loan_amount'], 2));
    }
    if ($amount > $max) {
        error('Amount exceeds user limit of ₦' . number_format($max, 2));
    }

    $stmt = $pdo->prepare("INSERT INTO loans (user_id, amount, purpose) VALUES (?, ?, ?)");
    $stmt->execute([$user_id, $amount, $purpose]);

    response(['message' => 'Loan application submitted.', 'loan_id' => $pdo->lastInsertId()], 201);
}

// ── POST: update loan status (admin only) ──
if ($endpoint === 'update_loan' && $method === 'POST') {
    requireAdmin();

    $loan_id = intval($body['loan_id'] ?? 0);
    $status  = $body['status'] ?? '';

    if (!$loan_id) error('loan_id is required.');
    if (!in_array($status, ['pending', 'approved', 'rejected'])) {
        error('Status must be: pending, approved or rejected.');
    }

    $stmt = $pdo->prepare("SELECT * FROM loans WHERE id = ?");
    $stmt->execute([$loan_id]);
    $loan = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$loan) error('Loan not found.', 404);

    $old = $loan['status'];
    $pdo->prepare("UPDATE loans SET status = ? WHERE id = ?")->execute([$status, $loan_id]);

    if ($status === 'approved' && $old !== 'approved') {
        $pdo->prepare("UPDATE users SET wallet_balance = wallet_balance + ? WHERE id = ?")
            ->execute([$loan['amount'], $loan['user_id']]);
        $pdo->prepare(
            "INSERT INTO wallet_transactions (user_id, loan_id, amount, type, description)
             VALUES (?, ?, ?, 'credit', ?)"
        )->execute([$loan['user_id'], $loan_id, $loan['amount'], 'Loan #' . $loan_id . ' approved']);
    }

    if ($old === 'approved' && $status !== 'approved') {
        $pdo->prepare("UPDATE users SET wallet_balance = wallet_balance - ? WHERE id = ?")
            ->execute([$loan['amount'], $loan['user_id']]);
        $pdo->prepare(
            "INSERT INTO wallet_transactions (user_id, loan_id, amount, type, description)
             VALUES (?, ?, ?, 'debit', ?)"
        )->execute([$loan['user_id'], $loan_id, $loan['amount'], 'Loan #' . $loan_id . ' reversed']);
    }

    response(['message' => 'Loan updated to ' . $status]);
}

// ── POST: update loan config (admin only) ──
if ($endpoint === 'update_config' && $method === 'POST') {
    requireAdmin();

    $percent = floatval($body['max_loan_percent'] ?? 0);
    $min     = floatval($body['min_loan_amount'] ?? 0);
    $max     = floatval($body['max_loan_amount'] ?? 0);

    if ($percent <= 0 || $percent > 100) error('Percent must be between 1 and 100.');
    if ($min <= 0 || $max <= 0 || $min >= $max) error('Invalid min/max amounts.');

    $pdo->prepare(
        "UPDATE loan_config SET max_loan_percent=?, min_loan_amount=?, max_loan_amount=? WHERE id=1"
    )->execute([$percent, $min, $max]);

    response(['message' => 'Loan config updated.']);
}

// No route matched
error('Unknown endpoint: ' . $endpoint, 404);
?>