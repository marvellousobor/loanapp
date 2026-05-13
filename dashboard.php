<?php
require 'php/config/db.php';
requireLogin();

if (isAdmin()) {
    header('Location: /loanapp/admin_dashboard.php');
    exit;
}

$uid = $_SESSION['user_id'];

$stmt = $pdo->prepare("SELECT name, email, wallet_balance, savings_balance, monthly_income FROM users WHERE id = ?");
$stmt->execute([$uid]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

$stmt = $pdo->prepare("SELECT COUNT(*) as total, SUM(status='pending') as pending, SUM(status='approved') as approved, SUM(status='rejected') as rejected FROM loans WHERE user_id = ?");
$stmt->execute([$uid]);
$stats = $stmt->fetch(PDO::FETCH_ASSOC);

$stmt = $pdo->prepare("SELECT status FROM kyc WHERE user_id = ?");
$stmt->execute([$uid]);
$kyc = $stmt->fetch(PDO::FETCH_ASSOC);

$stmt = $pdo->prepare("SELECT * FROM loans WHERE user_id = ? ORDER BY created_at DESC LIMIT 5");
$stmt->execute([$uid]);
$recent = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Active approved loan for repayment
$stmt = $pdo->prepare("SELECT * FROM loans WHERE user_id = ? AND status = 'approved' ORDER BY created_at DESC LIMIT 1");
$stmt->execute([$uid]);
$active_loan = $stmt->fetch(PDO::FETCH_ASSOC);

$stmt = $pdo->prepare("SELECT * FROM wallet_transactions WHERE user_id = ? ORDER BY created_at DESC LIMIT 5");
$stmt->execute([$uid]);
$transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);

$stmt = $pdo->prepare("SELECT * FROM savings_transactions WHERE user_id = ? ORDER BY created_at DESC LIMIT 5");
$stmt->execute([$uid]);
$savings_txs = $stmt->fetchAll(PDO::FETCH_ASSOC);



// Flash messages
$success_msg = $_SESSION['savings_success'] ?? null; unset($_SESSION['savings_success']);
$error_msg   = $_SESSION['savings_error']   ?? null; unset($_SESSION['savings_error']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - LoanApp</title>
    <link rel="stylesheet" href="/loanapp/css/style.css">
    <script src="https://js.paystack.co/v1/inline.js"></script>
    <style>
        .dual-wallet {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
            margin-bottom: 1.5rem;
        }
        @media(max-width:600px){ .dual-wallet { grid-template-columns: 1fr; } }

        .wallet-box {
            background: var(--card, #1e1e2e);
            border: 1px solid var(--border, #2d2d3d);
            border-radius: 14px;
            padding: 1.4rem 1.6rem;
        }
        .wallet-box.loan-box {
            border-color: rgba(99,102,241,.4);
            background: linear-gradient(135deg, rgba(99,102,241,.12), var(--card, #1e1e2e));
        }
        .wallet-box.savings-box {
            border-color: rgba(34,197,94,.4);
            background: linear-gradient(135deg, rgba(34,197,94,.08), var(--card, #1e1e2e));
        }
        .wb-label {
            font-size: .78rem;
            color: var(--muted, #888);
            text-transform: uppercase;
            letter-spacing: .06em;
            margin-bottom: .5rem;
        }
        .wb-amount {
            font-size: 1.8rem;
            font-weight: 700;
            margin-bottom: .8rem;
        }
        .loan-box  .wb-amount { color: #818cf8; }
        .savings-box .wb-amount { color: #4ade80; }
        .wb-actions { display: flex; gap: .5rem; flex-wrap: wrap; }

        .repay-section {
            background: var(--card, #1e1e2e);
            border: 1px solid rgba(251,191,36,.3);
            border-radius: 12px;
            padding: 1.2rem 1.4rem;
            margin-bottom: 1.5rem;
        }
        .repay-section h3 {
            margin: 0 0 .6rem;
            font-size: .95rem;
            color: #fbbf24;
        }
        .repay-meta {
            font-size: .82rem;
            color: var(--muted, #888);
            margin-bottom: .8rem;
        }
        .repay-actions { display: flex; gap: .6rem; flex-wrap: wrap; }

        .alert {
            padding: .85rem 1.1rem;
            border-radius: 10px;
            margin-bottom: 1.2rem;
            font-size: .9rem;
        }
        .alert-success { background: rgba(34,197,94,.12); border: 1px solid rgba(34,197,94,.3); color: #4ade80; }
        .alert-error   { background: rgba(239,68,68,.12);  border: 1px solid rgba(239,68,68,.3);  color: #f87171; }
    </style>
</head>
<body>

    <!-- Hidden inputs for savings JS -->
    <input type="hidden" id="user-email"          value="<?php echo htmlspecialchars($user['name']); ?>">
<input type="hidden" id="user-actual-email" value="<?php echo htmlspecialchars($user['email']); ?>">    <input type="hidden" id="paystack-public-key" value="pk_test_e23b8e129e8aa76869bda4c574c6259230c64066">
    <input type="hidden" id="active-loan-amount"  value="<?php echo $active_loan ? $active_loan['amount'] : 0; ?>">
    <input type="hidden" id="active-loan-id"      value="<?php echo $active_loan ? $active_loan['id'] : 0; ?>">
    <input type="hidden" id="savings-balance"     value="<?php echo $user['savings_balance']; ?>">

    <nav>
        <a class="nav-brand" href="/loanapp/dashboard.php">⬡ LoanApp</a>
        <div class="nav-links">
            <a href="/loanapp/dashboard.php" class="active">Dashboard</a>
            <a href="/loanapp/loans.php">Loans</a>
            <a href="/loanapp/html/apply.html">Apply</a>
            <a href="/loanapp/kyc.php">KYC</a>
            <a href="/loanapp/html/api_explorer.html">API</a>
            <a href="/loanapp/php/auth/logout.php" class="btn btn-ghost btn-sm">Logout</a>
        </div>
    </nav>

    <div class="container">

        <div class="action-row">
            <div>
                <h1 class="page-title" style="margin-bottom:.3rem;">
                    Hello, <?php echo htmlspecialchars($_SESSION['user_name']); ?> 👋
                </h1>
                <span class="badge badge-user">User</span>
            </div>
            <a href="/loanapp/html/apply.html" class="btn btn-primary">+ Apply for Loan</a>
        </div>

        <?php if ($success_msg): ?>
        <div class="alert alert-success">✅ <?php echo htmlspecialchars($success_msg); ?></div>
        <?php endif; ?>
        <?php if ($error_msg): ?>
        <div class="alert alert-error">❌ <?php echo htmlspecialchars($error_msg); ?></div>
        <?php endif; ?>

        <!-- Dual Wallet Cards -->
        <div class="dual-wallet">
            <!-- Loan Wallet -->
            <div class="wallet-box loan-box">
                <div class="wb-label">🏦 Loan Wallet</div>
                <div class="wb-amount">₦<?php echo number_format($user['wallet_balance'], 2); ?></div>
                <div style="font-size:.78rem;color:var(--muted);">Credited when your loan is approved</div>
            </div>

            <!-- Savings Wallet -->
            <div class="wallet-box savings-box">
                <div class="wb-label">💰 Savings Wallet</div>
                <div class="wb-amount">₦<?php echo number_format($user['savings_balance'], 2); ?></div>
                <div class="wb-actions">
                    <button class="btn btn-sm btn-success" onclick="openDepositModal()">+ Add Money</button>
                </div>
            </div>
        </div>

        <!-- KYC + Income row -->
        <div class="wallet-card" style="margin-bottom:1.5rem;">
            <div class="wallet-item">
                <div class="wallet-label">Monthly Income</div>
                <div class="wallet-amount" style="font-size:1.4rem;color:var(--muted);">
                    ₦<?php echo number_format($user['monthly_income'], 2); ?>
                </div>
                <?php if ($user['monthly_income'] == 0): ?>
                <a href="/loanapp/profile.php" style="font-size:.78rem;color:var(--accent);margin-top:.3rem;display:block;">Set income →</a>
                <?php endif; ?>
            </div>
            <div class="wallet-divider"></div>
            <div class="wallet-item">
                <div class="wallet-label">KYC Status</div>
                <?php if ($kyc && $kyc['status'] === 'verified'): ?>
                <div class="kyc-badge verified">✔ Verified</div>
                <?php else: ?>
                <div class="kyc-badge pending">⚠ Not Verified</div>
                <a href="/loanapp/kyc.php" style="font-size:.78rem;color:var(--accent);margin-top:.3rem;display:block;">Complete KYC →</a>
                <?php endif; ?>
            </div>
        </div>

        <!-- Active Loan Repayment Section -->
        <?php if ($active_loan): ?>
        <div class="repay-section">
            <h3>⚠️ Active Loan — Repayment Due</h3>
            <div class="repay-meta">
                Loan #<?php echo $active_loan['id']; ?> &nbsp;·&nbsp;
                Amount: <strong>₦<?php echo number_format($active_loan['amount'], 2); ?></strong> &nbsp;·&nbsp;
                Applied: <?php echo date('M d, Y', strtotime($active_loan['created_at'])); ?>
            </div>
            <div class="repay-actions">
                <?php if ($user['savings_balance'] >= $active_loan['amount']): ?>
                <form action="/loanapp/php/loans/repay.php" method="POST">
                    <input type="hidden" name="loan_id" value="<?php echo $active_loan['id']; ?>">
                    <button type="submit" class="btn btn-success btn-sm">
                        Repay from Savings (₦<?php echo number_format($active_loan['amount'], 2); ?>)
                    </button>
                </form>
                <?php else: ?>
                <span style="font-size:.82rem;color:#f87171;">
                    Insufficient savings (₦<?php echo number_format($user['savings_balance'], 2); ?>) —
                </span>
                <?php endif; ?>
                <button class="btn btn-primary btn-sm" onclick="repayViaPaystack()">
                    Repay via Paystack
                </button>
            </div>
        </div>
        <?php endif; ?>

        <!-- Stats -->
        <div class="stats">
            <div class="stat-card">
                <div class="stat-label">Total Applications</div>
                <div class="stat-value"><?php echo $stats['total'] ?: 0; ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Pending</div>
                <div class="stat-value orange"><?php echo $stats['pending'] ?: 0; ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Approved</div>
                <div class="stat-value green"><?php echo $stats['approved'] ?: 0; ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Rejected</div>
                <div class="stat-value red"><?php echo $stats['rejected'] ?: 0; ?></div>
            </div>
        </div>

        <!-- Recent Loans -->
        <div class="action-row">
            <strong>Recent Loan Applications</strong>
            <a href="/loanapp/loans.php" style="color:var(--accent);text-decoration:none;font-size:.85rem;">View all →</a>
        </div>
        <div class="table-wrap" style="margin-bottom:2rem;">
            <?php if (empty($recent)): ?>
            <div class="empty">
                <div class="empty-icon">📄</div>
                <p>No applications yet.</p>
                <a href="/loanapp/html/apply.html" class="btn btn-primary" style="margin-top:1rem;">Apply Now</a>
            </div>
            <?php else: ?>
            <table>
                <thead>
                    <tr><th>#</th><th>Purpose</th><th>Amount (₦)</th><th>Status</th><th>Date</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($recent as $loan): ?>
                    <tr>
                        <td class="mono" style="color:var(--muted);"><?php echo $loan['id']; ?></td>
                        <td><?php echo htmlspecialchars($loan['purpose']); ?></td>
                        <td class="amount"><?php echo number_format($loan['amount'], 2); ?></td>
                        <td><span class="badge badge-<?php echo $loan['status']; ?>"><?php echo ucfirst($loan['status']); ?></span></td>
                        <td style="color:var(--muted);font-size:.82rem;"><?php echo date('M d, Y', strtotime($loan['created_at'])); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>

        <!-- Loan Wallet Transactions -->
        <?php if (!empty($transactions)): ?>
        <div class="action-row"><strong>Loan Wallet Transactions</strong></div>
        <div class="table-wrap" style="margin-bottom:2rem;">
            <table>
                <thead>
                    <tr><th>Description</th><th>Amount (₦)</th><th>Type</th><th>Date</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($transactions as $tx): ?>
                    <tr>
                        <td style="color:var(--muted);"><?php echo htmlspecialchars($tx['description']); ?></td>
                        <td class="mono <?php echo $tx['type']==='credit'?'green':'red'; ?>">
                            <?php echo $tx['type']==='credit'?'+':'-'; ?>₦<?php echo number_format($tx['amount'],2); ?>
                        </td>
                        <td><span class="badge <?php echo $tx['type']==='credit'?'badge-approved':'badge-rejected'; ?>"><?php echo ucfirst($tx['type']); ?></span></td>
                        <td style="color:var(--muted);font-size:.82rem;"><?php echo date('M d, Y', strtotime($tx['created_at'])); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>

        <!-- Savings Transactions -->
        <?php if (!empty($savings_txs)): ?>
        <div class="action-row"><strong>Savings Transactions</strong></div>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr><th>Description</th><th>Amount (₦)</th><th>Type</th><th>Status</th><th>Date</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($savings_txs as $tx): ?>
                    <tr>
                        <td style="color:var(--muted);"><?php echo htmlspecialchars($tx['description']); ?></td>
                        <td class="mono <?php echo $tx['type']==='deposit'?'green':'red'; ?>">
                            <?php echo $tx['type']==='deposit'?'+':'-'; ?>₦<?php echo number_format($tx['amount'],2); ?>
                        </td>
                        <td><span class="badge <?php echo $tx['type']==='deposit'?'badge-approved':'badge-rejected'; ?>"><?php echo ucfirst($tx['type']); ?></span></td>
                        <td>
                            <?php
                            echo match($tx['status']) {
                                'success' => '<span class="badge badge-active">Success</span>',
                                'pending' => '<span class="badge" style="background:rgba(251,191,36,.15);color:#fbbf24;">Pending</span>',
                                'failed'  => '<span class="badge badge-rejected">Failed</span>',
                                default   => ''
                            };
                            ?>
                        </td>
                        <td style="color:var(--muted);font-size:.82rem;"><?php echo date('M d, Y', strtotime($tx['created_at'])); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>

    </div>

    <!-- Deposit Modal -->
    <div id="deposit-modal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.6);z-index:1000;align-items:center;justify-content:center;">
        <div style="background:var(--card,#1e1e2e);border:1px solid var(--border,#2d2d3d);border-radius:14px;padding:2rem;width:100%;max-width:400px;margin:1rem;">
            <h3 style="margin:0 0 1rem;">💰 Add to Savings</h3>
            <label style="font-size:.82rem;color:var(--muted);">Amount (₦)</label>
            <input type="number" id="deposit-amount" min="100" step="100"
                   placeholder="e.g. 5000"
                   style="width:100%;padding:.65rem 1rem;border-radius:8px;border:1px solid var(--border,#2d2d3d);background:var(--bg,#13131e);color:var(--fg,#e4e4ef);font-size:1rem;margin:.4rem 0 1rem;box-sizing:border-box;">
            <div style="display:flex;gap:.5rem;flex-wrap:wrap;margin-bottom:1rem;">
                <button onclick="setDeposit(1000)"  class="btn btn-ghost btn-sm">₦1,000</button>
                <button onclick="setDeposit(5000)"  class="btn btn-ghost btn-sm">₦5,000</button>
                <button onclick="setDeposit(10000)" class="btn btn-ghost btn-sm">₦10,000</button>
                <button onclick="setDeposit(50000)" class="btn btn-ghost btn-sm">₦50,000</button>
            </div>
            <div style="display:flex;gap:.5rem;">
                <button class="btn btn-primary" onclick="confirmDeposit()">Pay with Paystack</button>
                <button class="btn btn-ghost"   onclick="closeDepositModal()">Cancel</button>
            </div>
        </div>
    </div>

    <script src="/loanapp/js/savings.js"></script>
</body>
</html>