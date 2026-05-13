<?php
require '../config/db.php';
requireAdmin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('/admin.php');
}

$action = $_POST['action'] ?? '';

if ($action === 'role') {
    $uid        = intval($_POST['user_id']);
    $make_admin = intval($_POST['make_admin']);

    if ($uid === (int)$_SESSION['user_id']) {
        redirect('/admin.php', 'error', 'You cannot change your own role.');
    }

    $stmt = $pdo->prepare("UPDATE users SET is_admin = ? WHERE id = ?");
    $stmt->execute([$make_admin, $uid]);
    redirect('/admin.php', 'success', 'User role updated.');
}

if ($action === 'config') {
    $percent = floatval($_POST['max_loan_percent']);
    $min     = floatval($_POST['min_loan_amount']);
    $max     = floatval($_POST['max_loan_amount']);

    if ($percent <= 0 || $percent > 100) {
        redirect('/admin.php', 'error', 'Percent must be between 1 and 100.');
    }
    if ($min <= 0 || $max <= 0 || $min >= $max) {
        redirect('/admin.php', 'error', 'Invalid min/max loan amounts.');
    }

    $pdo->prepare("UPDATE loan_config SET max_loan_percent=?, min_loan_amount=?, max_loan_amount=? WHERE id=1")
        ->execute([$percent, $min, $max]);
    redirect('/admin.php', 'success', 'Loan configuration updated.');
}

redirect('/admin.php', 'error', 'Unknown action.');
?>
