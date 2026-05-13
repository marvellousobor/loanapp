<?php
require 'php/config/db.php';
requireAdmin();

require_once __DIR__ . '/php/wallet/wallet_helpers.php';

// Stats
$total_users  = $pdo->query("SELECT COUNT(*) FROM users WHERE is_admin = 0")->fetchColumn();
$total_loans  = $pdo->query("SELECT COUNT(*) FROM loans")->fetchColumn();
$pending      = $pdo->query("SELECT COUNT(*) FROM loans WHERE status = 'pending'")->fetchColumn();
$approved     = $pdo->query("SELECT COUNT(*) FROM loans WHERE status = 'approved'")->fetchColumn();
$rejected     = $pdo->query("SELECT COUNT(*) FROM loans WHERE status = 'rejected'")->fetchColumn();
$total_amount = $pdo->query("SELECT COALESCE(SUM(amount),0) FROM loans WHERE status = 'approved'")->fetchColumn();
$wallet_bal   = getWalletBalance($pdo);

// Flash messages
$success_msg = $_SESSION['success'] ?? null; unset($_SESSION['success']);
$error_msg   = $_SESSION['error']   ?? null; unset($_SESSION['error']);

// Recent pending loans
$recent_pending = $pdo->query(
    "SELECT l.*, u.name AS user_name, u.email AS user_email
     FROM loans l JOIN users u ON l.user_id = u.id
     WHERE l.status = 'pending'
     ORDER BY l.created_at DESC LIMIT 5"
)->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - LoanApp</title>
    <link rel="stylesheet" href="/loanapp/css/style.css">
    <style>
        .alert { padding: .85rem 1.1rem; border-radius: 10px; margin-bottom: 1.2rem; font-size: .9rem; }
        .alert-success { background: rgba(34,197,94,.12); border: 1px solid rgba(34,197,94,.3); color: #4ade80; }
        .alert-error   { background: rgba(239,68,68,.12);  border: 1px solid rgba(239,68,68,.3);  color: #f87171; }

        .wallet-stat {
            background: linear-gradient(135deg, var(--accent, #6c63ff) 0%, #4f46e5 100%);
            color: #fff;
            border-radius: 12px;
        }
        .wallet-stat .stat-label { opacity: .8; }
        .wallet-stat .stat-value { color: #fff !important; }
        .wallet-stat .wallet-link {
            display: block;
            margin-top: .4rem;
            font-size: .75rem;
            opacity: .75;
            color: #fff;
            text-decoration: none;
        }
        .wallet-stat .wallet-link:hover { opacity: 1; }
    </style>
</head>
<body>

    <nav>
        <a class="nav-brand" href="/loanapp/admin_dashboard.php">⬡ LoanApp Admin</a>
        <div class="nav-links">
            <a href="/loanapp/admin_dashboard.php" class="active">Dashboard</a>
            <a href="/loanapp/admin_wallet.php">Wallet</a>
            <a href="/loanapp/loans.php">All Loans</a>
            <a href="/loanapp/admin.php">Manage Users</a>
            <a href="/loanapp/html/api_explorer.html">API</a>
            <a href="/loanapp/php/auth/logout.php" class="btn btn-ghost btn-sm">Logout</a>
        </div>
    </nav>

    <div class="container">

        <!-- Flash messages -->
        <?php if ($success_msg): ?>
        <div class="alert alert-success">✅ <?php echo htmlspecialchars($success_msg); ?></div>
        <?php endif; ?>
        <?php if ($error_msg): ?>
        <div class="alert alert-error">❌ <?php echo htmlspecialchars($error_msg); ?>
            <?php if (str_contains($error_msg, 'wallet')): ?>
                <a href="/loanapp/admin_wallet.php" style="color:inherit;margin-left:.5rem;text-decoration:underline;">Fund wallet →</a>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <div class="action-row">
            <div>
                <h1 class="page-title" style="margin-bottom:.3rem;">
                    Admin Dashboard 👋
                </h1>
                <span class="badge badge-admin">Admin</span>
            </div>
        </div>

        <!-- Stats -->
        <div class="stats">
            <!-- Wallet balance — highlighted card -->
            <div class="stat-card wallet-stat">
                <div class="stat-label">🏦 Wallet Balance</div>
                <div class="stat-value">₦<?php echo number_format($wallet_bal, 2); ?></div>
                <a href="/loanapp/admin_wallet.php" class="wallet-link">Fund wallet →</a>
            </div>
            <div class="stat-card">
                <div class="stat-label">Total Users</div>
                <div class="stat-value"><?php echo $total_users; ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Total Loans</div>
                <div class="stat-value"><?php echo $total_loans; ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Pending</div>
                <div class="stat-value orange"><?php echo $pending; ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Approved</div>
                <div class="stat-value green"><?php echo $approved; ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Rejected</div>
                <div class="stat-value red"><?php echo $rejected; ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Total Disbursed (₦)</div>
                <div class="stat-value green" style="font-size:1.1rem;"><?php echo number_format($total_amount, 2); ?></div>
            </div>
        </div>

        <!-- Pending Loan Requests -->
        <div class="action-row">
            <strong>Pending Loan Requests</strong>
            <a href="/loanapp/loans.php" style="color:var(--accent);text-decoration:none;font-size:.85rem;">View all loans →</a>
        </div>

        <div class="table-wrap">
            <?php if (empty($recent_pending)): ?>
            <div class="empty">
                <div class="empty-icon">✅</div>
                <p>No pending loan requests.</p>
            </div>
            <?php else: ?>
            <table>
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Applicant</th>
                        <th>Amount (₦)</th>
                        <th>Purpose</th>
                        <th>Date</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recent_pending as $loan): ?>
                    <?php $insufficient = $wallet_bal < (float)$loan['amount']; ?>
                    <tr <?php echo $insufficient ? 'style="opacity:.65;"' : ''; ?>>
                        <td class="mono" style="color:var(--muted);"><?php echo $loan['id']; ?></td>
                        <td>
                            <div><?php echo htmlspecialchars($loan['user_name']); ?></div>
                            <div style="font-size:.78rem;color:var(--muted);"><?php echo htmlspecialchars($loan['user_email']); ?></div>
                        </td>
                        <td class="amount"><?php echo number_format($loan['amount'], 2); ?></td>
                        <td style="color:var(--muted);"><?php echo htmlspecialchars($loan['purpose']); ?></td>
                        <td style="color:var(--muted);font-size:.82rem;"><?php echo date('M d, Y', strtotime($loan['created_at'])); ?></td>
                        <td>
                            <?php if ($insufficient): ?>
                                <span style="font-size:.78rem;color:#f87171;">Insufficient wallet funds</span>
                            <?php else: ?>
                            <div style="display:flex;gap:.4rem;">
                                <form action="/loanapp/php/loans/update_status.php" method="POST">
                                    <input type="hidden" name="loan_id" value="<?php echo $loan['id']; ?>">
                                    <input type="hidden" name="status" value="approved">
                                    <button type="submit" class="btn btn-success btn-sm">Approve</button>
                                </form>
                                <form action="/loanapp/php/loans/update_status.php" method="POST">
                                    <input type="hidden" name="loan_id" value="<?php echo $loan['id']; ?>">
                                    <input type="hidden" name="status" value="rejected">
                                    <button type="submit" class="btn btn-danger btn-sm">Reject</button>
                                </form>
                            </div>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>

    </div>
</body>
</html>
