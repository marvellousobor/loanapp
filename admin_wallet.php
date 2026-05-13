<?php
/**
 * admin_wallet.php
 * Admin global wallet — view balance, top up via Paystack JS, see transaction history.
 */
require 'php/config/db.php';
requireAdmin();

require_once __DIR__ . '/php/wallet/wallet_helpers.php';

// ── Config — replace with your actual Paystack public key ─────────────────────
define('PAYSTACK_PUBLIC_KEY', 'pk_test_e23b8e129e8aa76869bda4c574c6259230c64066'); // ← replace

// ── Admin email (passed to wallet.js via hidden input) ────────────────────────
$admin_stmt = $pdo->prepare("SELECT email FROM users WHERE id = ? AND is_admin = 1");
$admin_stmt->execute([$_SESSION['user_id']]);
$admin_row = $admin_stmt->fetch(PDO::FETCH_ASSOC);

if (!$admin_row) {
    die('Admin account not found.');
}

// ── Data ──────────────────────────────────────────────────────────────────────
$balance = getWalletBalance($pdo);

// Flash messages
$success_msg = $_SESSION['wallet_success'] ?? null; unset($_SESSION['wallet_success']);
$error_msg   = $_SESSION['wallet_error']   ?? null; unset($_SESSION['wallet_error']);

// Pagination
$per_page    = 15;
$page        = max(1, (int)($_GET['page'] ?? 1));
$offset      = ($page - 1) * $per_page;
$total_tx    = (int)$pdo->query("SELECT COUNT(*) FROM wallet_transactions")->fetchColumn();
$total_pages = (int)ceil($total_tx / $per_page);

$tx_stmt = $pdo->prepare(
    "SELECT * FROM wallet_transactions ORDER BY created_at DESC LIMIT :limit OFFSET :offset"
);
$tx_stmt->bindValue(':limit',  $per_page, PDO::PARAM_INT);
$tx_stmt->bindValue(':offset', $offset,   PDO::PARAM_INT);
$tx_stmt->execute();
$transactions = $tx_stmt->fetchAll(PDO::FETCH_ASSOC);

// Summary stats
$total_credited = (float)$pdo->query(
    "SELECT COALESCE(SUM(amount),0) FROM wallet_transactions WHERE type='credit' AND status='success'"
)->fetchColumn();

$total_disbursed = (float)$pdo->query(
    "SELECT COALESCE(SUM(amount),0) FROM wallet_transactions WHERE type='debit' AND status='success'"
)->fetchColumn();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Global Wallet - LoanApp Admin</title>
    <link rel="stylesheet" href="/loanapp/css/style.css">
    <script src="https://js.paystack.co/v1/inline.js"></script>
    <style>
        .wallet-hero {
            background: linear-gradient(135deg, var(--accent, #6c63ff) 0%, #4f46e5 100%);
            border-radius: 16px;
            padding: 2rem 2.4rem;
            color: #fff;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1.5rem;
            flex-wrap: wrap;
            margin-bottom: 1.5rem;
            box-shadow: 0 8px 32px rgba(99,102,241,.25);
        }
        .wallet-hero .bal-label {
            font-size: .85rem;
            opacity: .8;
            letter-spacing: .06em;
            text-transform: uppercase;
            margin-bottom: .4rem;
        }
        .wallet-hero .bal-amount {
            font-size: 2.6rem;
            font-weight: 700;
            letter-spacing: -.5px;
            line-height: 1;
        }
        .wallet-hero .bal-sub {
            margin-top: .5rem;
            font-size: .82rem;
            opacity: .75;
        }
        .topup-card {
            background: var(--card, #1e1e2e);
            border: 1px solid var(--border, #2d2d3d);
            border-radius: 12px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
        }
        .topup-card h3 { margin: 0 0 1rem; font-size: 1rem; color: var(--fg, #e4e4ef); }
        .topup-row {
            display: flex;
            gap: .75rem;
            flex-wrap: wrap;
            align-items: flex-end;
        }
        .topup-row .field {
            display: flex;
            flex-direction: column;
            gap: .35rem;
            flex: 1;
            min-width: 200px;
        }
        .topup-row label { font-size: .8rem; color: var(--muted, #888); }
        .topup-row input[type="number"] {
            padding: .65rem 1rem;
            border-radius: 8px;
            border: 1px solid var(--border, #2d2d3d);
            background: var(--bg, #13131e);
            color: var(--fg, #e4e4ef);
            font-size: 1rem;
            outline: none;
            transition: border-color .2s;
        }
        .topup-row input[type="number"]:focus { border-color: var(--accent, #6c63ff); }
        .quick-amounts {
            display: flex;
            gap: .5rem;
            flex-wrap: wrap;
            margin-top: .5rem;
        }
        .quick-amounts button {
            padding: .3rem .75rem;
            border-radius: 20px;
            border: 1px solid var(--border, #2d2d3d);
            background: transparent;
            color: var(--muted, #888);
            cursor: pointer;
            font-size: .82rem;
            transition: all .18s;
        }
        .quick-amounts button:hover,
        .quick-amounts button.active {
            border-color: var(--accent, #6c63ff);
            color: var(--accent, #6c63ff);
            background: rgba(99,102,241,.08);
        }
        .alert {
            padding: .85rem 1.1rem;
            border-radius: 10px;
            margin-bottom: 1.2rem;
            font-size: .9rem;
            display: flex;
            align-items: center;
            gap: .6rem;
        }
        .alert-success { background: rgba(34,197,94,.12);  border: 1px solid rgba(34,197,94,.3);  color: #4ade80; }
        .alert-error   { background: rgba(239,68,68,.12);  border: 1px solid rgba(239,68,68,.3);  color: #f87171; }
        .tx-credit { color: #4ade80; }
        .tx-debit  { color: #f87171; }
        .mini-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: .85rem;
            margin-bottom: 1.5rem;
        }
        .mini-stat {
            background: var(--card, #1e1e2e);
            border: 1px solid var(--border, #2d2d3d);
            border-radius: 10px;
            padding: 1rem 1.2rem;
        }
        .mini-stat .ms-label { font-size: .78rem; color: var(--muted, #888); margin-bottom: .3rem; }
        .mini-stat .ms-val   { font-size: 1.15rem; font-weight: 600; }
        .pagination {
            display: flex;
            gap: .4rem;
            justify-content: center;
            margin-top: 1.2rem;
            flex-wrap: wrap;
        }
        .pagination a, .pagination span {
            padding: .35rem .75rem;
            border-radius: 6px;
            border: 1px solid var(--border, #2d2d3d);
            font-size: .82rem;
            text-decoration: none;
            color: var(--muted, #888);
            transition: all .18s;
        }
        .pagination a:hover  { border-color: var(--accent, #6c63ff); color: var(--accent, #6c63ff); }
        .pagination span.cur { background: var(--accent, #6c63ff); border-color: var(--accent, #6c63ff); color: #fff; }
    </style>
</head>
<body>

    <!--
        Hidden inputs: pass PHP values to wallet.js without inline JS.
        wallet.js reads these with document.getElementById(...)
    -->
    <input type="hidden" id="admin-email"         value="<?php echo htmlspecialchars($admin_row['email']); ?>">
    <input type="hidden" id="paystack-public-key" value="<?php echo htmlspecialchars(PAYSTACK_PUBLIC_KEY); ?>">

    <nav>
        <a class="nav-brand" href="/loanapp/admin_dashboard.php">⬡ LoanApp Admin</a>
        <div class="nav-links">
            <a href="/loanapp/admin_dashboard.php">Dashboard</a>
            <a href="/loanapp/admin_wallet.php" class="active">Wallet</a>
            <a href="/loanapp/loans.php">All Loans</a>
            <a href="/loanapp/admin.php">Manage Users</a>
            <a href="/loanapp/php/auth/logout.php" class="btn btn-ghost btn-sm">Logout</a>
        </div>
    </nav>

    <div class="container">

        <?php if ($success_msg): ?>
        <div class="alert alert-success">✅ <?php echo htmlspecialchars($success_msg); ?></div>
        <?php endif; ?>
        <?php if ($error_msg): ?>
        <div class="alert alert-error">❌ <?php echo htmlspecialchars($error_msg); ?></div>
        <?php endif; ?>

        <!-- Wallet Hero -->
        <div class="wallet-hero">
            <div>
                <div class="bal-label">Global Loan Wallet</div>
                <div class="bal-amount">₦<?php echo number_format($balance, 2); ?></div>
                <div class="bal-sub">Available to disburse to approved borrowers</div>
            </div>
            <div style="font-size:2.5rem;">🏦</div>
        </div>

        <!-- Mini Stats -->
        <div class="mini-stats">
            <div class="mini-stat">
                <div class="ms-label">Total Funded (All Time)</div>
                <div class="ms-val tx-credit">₦<?php echo number_format($total_credited, 2); ?></div>
            </div>
            <div class="mini-stat">
                <div class="ms-label">Total Disbursed (All Time)</div>
                <div class="ms-val tx-debit">₦<?php echo number_format($total_disbursed, 2); ?></div>
            </div>
            <div class="mini-stat">
                <div class="ms-label">Total Transactions</div>
                <div class="ms-val"><?php echo number_format($total_tx); ?></div>
            </div>
        </div>

        <!-- Top-up Card -->
        <div class="topup-card">
            <h3>💳 Fund Wallet via Paystack</h3>
            <div class="topup-row">
                <div class="field">
                    <label for="amount">Amount (₦)</label>
                    <input type="number" id="amount" min="100" step="100" placeholder="e.g. 50000">
                    <div class="quick-amounts">
                        <button type="button" data-value="10000"   onclick="setAmount(10000)">₦10,000</button>
                        <button type="button" data-value="50000"   onclick="setAmount(50000)">₦50,000</button>
                        <button type="button" data-value="100000"  onclick="setAmount(100000)">₦100,000</button>
                        <button type="button" data-value="500000"  onclick="setAmount(500000)">₦500,000</button>
                        <button type="button" data-value="1000000" onclick="setAmount(1000000)">₦1,000,000</button>
                    </div>
                </div>
                <div>
                    <button type="button" class="btn btn-primary" onclick="payWithPaystack()">
                        Pay with Paystack →
                    </button>
                </div>
            </div>
        </div>

        <!-- Transaction History -->
        <div class="action-row">
            <strong>Transaction History</strong>
            <span style="font-size:.82rem;color:var(--muted);"><?php echo $total_tx; ?> total</span>
        </div>

        <div class="table-wrap">
            <?php if (empty($transactions)): ?>
            <div class="empty">
                <div class="empty-icon">📭</div>
                <p>No wallet transactions yet.</p>
            </div>
            <?php else: ?>
            <table>
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Type</th>
                        <th>Amount (₦)</th>
                        <th>Description</th>
                        <th>Reference</th>
                        <th>Status</th>
                        <th>Date</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($transactions as $tx): ?>
                    <tr>
                        <td class="mono" style="color:var(--muted);"><?php echo $tx['id']; ?></td>
                        <td>
                            <?php if ($tx['type'] === 'credit'): ?>
                                <span class="tx-credit">▲ Credit</span>
                            <?php else: ?>
                                <span class="tx-debit">▼ Debit</span>
                            <?php endif; ?>
                        </td>
                        <td class="amount <?php echo $tx['type'] === 'credit' ? 'tx-credit' : 'tx-debit'; ?>">
                            <?php echo $tx['type'] === 'debit' ? '−' : '+'; ?>
                            ₦<?php echo number_format($tx['amount'], 2); ?>
                        </td>
                        <td style="color:var(--muted);max-width:260px;">
                            <?php echo htmlspecialchars($tx['description']); ?>
                            <?php if (!empty($tx['loan_id'])): ?>
                                <a href="/loanapp/loans.php?id=<?php echo $tx['loan_id']; ?>"
                                   style="color:var(--accent);font-size:.78rem;margin-left:.3rem;">
                                   #<?php echo $tx['loan_id']; ?>
                                </a>
                            <?php endif; ?>
                        </td>
                        <td class="mono" style="color:var(--muted);font-size:.78rem;">
                            <?php echo !empty($tx['reference'])
                                ? htmlspecialchars(substr($tx['reference'], 0, 20)) . '…'
                                : '—';
                            ?>
                        </td>
                        <td>
                            <?php
                            echo match($tx['status']) {
                                'success' => '<span class="badge badge-active">Success</span>',
                                'pending' => '<span class="badge" style="background:rgba(251,191,36,.15);color:#fbbf24;">Pending</span>',
                                'failed'  => '<span class="badge badge-rejected">Failed</span>',
                                default   => '<span class="badge">' . htmlspecialchars($tx['status']) . '</span>',
                            };
                            ?>
                        </td>
                        <td style="color:var(--muted);font-size:.82rem;">
                            <?php echo date('M d, Y H:i', strtotime($tx['created_at'])); ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <?php if ($total_pages > 1): ?>
            <div class="pagination">
                <?php if ($page > 1): ?>
                    <a href="?page=<?php echo $page - 1; ?>">← Prev</a>
                <?php endif; ?>
                <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                    <?php if ($i === $page): ?>
                        <span class="cur"><?php echo $i; ?></span>
                    <?php else: ?>
                        <a href="?page=<?php echo $i; ?>"><?php echo $i; ?></a>
                    <?php endif; ?>
                <?php endfor; ?>
                <?php if ($page < $total_pages): ?>
                    <a href="?page=<?php echo $page + 1; ?>">Next →</a>
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <?php endif; ?>
        </div>

    </div>

    <!-- Wallet JS — loaded last so DOM is ready -->
    <script src="/loanapp/js/wallet.js"></script>
</body>
</html>
