<?php
require 'php/config/db.php';
requireAdmin();

$success = $_GET['success'] ?? '';
$error   = $_GET['error']   ?? '';

$users  = $pdo->query("SELECT u.*, k.status AS kyc_status FROM users u LEFT JOIN kyc k ON u.id = k.user_id ORDER BY u.created_at DESC")->fetchAll(PDO::FETCH_ASSOC);
$config = $pdo->query("SELECT * FROM loan_config LIMIT 1")->fetch(PDO::FETCH_ASSOC);

$total_users  = count($users);
$total_admins = array_sum(array_column($users, 'is_admin'));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin - LoanApp</title>
    <link rel="stylesheet" href="/loanapp/css/style.css">
</head>
<body>

    <nav>
        <a class="nav-brand" href="/loanapp/dashboard.php">⬡ LoanApp</a>
        <div class="nav-links">
            <a href="/loanapp/dashboard.php">Dashboard</a>
            <a href="/loanapp/loans.php">Loans</a>
            <a href="/loanapp/html/apply.html">Apply</a>
            <a href="/loanapp/kyc.php">KYC</a>
            <a href="/loanapp/admin.php" class="active">Admin</a>
            <a href="/loanapp/php/auth/logout.php" class="btn btn-ghost btn-sm">Logout</a>
        </div>
    </nav>

    <div class="container">
        <h1 class="page-title">Admin Panel</h1>

        <?php if ($success): ?><div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div><?php endif; ?>
        <?php if ($error):   ?><div class="alert alert-error"><?php   echo htmlspecialchars($error);   ?></div><?php endif; ?>

        <div class="config-box">
            <h2>⚙ Loan Configuration</h2>
            <form action="/loanapp/php/admin/update_user.php" method="POST">
                <input type="hidden" name="action" value="config">
                <div class="config-grid">
                    <div class="form-group">
                        <label>Max Borrowable (% of monthly income)</label>
                        <input type="number" name="max_loan_percent" value="<?php echo $config['max_loan_percent']; ?>" min="1" max="100" step="1" required>
                        <div class="field-hint">e.g. 50 = user can borrow up to 50% of monthly income.</div>
                    </div>
                    <div class="form-group">
                        <label>Min Loan Amount (₦)</label>
                        <input type="number" name="min_loan_amount" value="<?php echo $config['min_loan_amount']; ?>" min="1" required>
                    </div>
                    <div class="form-group">
                        <label>Max Loan Amount (₦)</label>
                        <input type="number" name="max_loan_amount" value="<?php echo $config['max_loan_amount']; ?>" min="1" required>
                    </div>
                </div>
                <button type="submit" class="btn btn-blue">Save Configuration</button>
            </form>
        </div>

        <div class="stats">
            <div class="stat-card"><div class="stat-label">Total Users</div><div class="stat-value"><?php echo $total_users; ?></div></div>
            <div class="stat-card"><div class="stat-label">Admins</div><div class="stat-value orange"><?php echo $total_admins; ?></div></div>
            <div class="stat-card"><div class="stat-label">Regular Users</div><div class="stat-value"><?php echo $total_users - $total_admins; ?></div></div>
        </div>

        <div class="table-wrap">
            <table>
                <thead>
                    <tr><th>#</th><th>Name</th><th>Email</th><th>KYC</th><th>Role</th><th>Wallet (₦)</th><th>Joined</th><th>Action</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $u): ?>
                    <tr>
                        <td class="mono" style="color:var(--muted);"><?php echo $u['id']; ?></td>
                        <td>
                            <?php echo htmlspecialchars($u['name']); ?>
                            <?php if ($u['id'] == $_SESSION['user_id']): ?><span style="font-size:.72rem;color:var(--muted);"> (you)</span><?php endif; ?>
                        </td>
                        <td style="color:var(--muted);font-size:.84rem;"><?php echo htmlspecialchars($u['email']); ?></td>
                        <td><?php echo $u['kyc_status']==='verified' ? '<span class="badge badge-verified">Verified</span>' : '<span class="badge badge-pending">Pending</span>'; ?></td>
                        <td><span class="badge <?php echo $u['is_admin']?'badge-admin':'badge-user'; ?>"><?php echo $u['is_admin']?'Admin':'User'; ?></span></td>
                        <td class="amount"><?php echo number_format($u['wallet_balance'], 2); ?></td>
                        <td style="color:var(--muted);font-size:.82rem;"><?php echo date('M d, Y', strtotime($u['created_at'])); ?></td>
                        <td>
                            <?php if ($u['id'] == $_SESSION['user_id']): ?>
                                <span style="color:var(--muted);font-size:.8rem;">— you</span>
                            <?php else: ?>
                            <form action="/loanapp/php/admin/update_user.php" method="POST">
                                <input type="hidden" name="action" value="role">
                                <input type="hidden" name="user_id" value="<?php echo $u['id']; ?>">
                                <?php if ($u['is_admin']): ?>
                                    <input type="hidden" name="make_admin" value="0">
                                    <button type="submit" class="btn btn-danger btn-sm">Remove Admin</button>
                                <?php else: ?>
                                    <input type="hidden" name="make_admin" value="1">
                                    <button type="submit" class="btn btn-success btn-sm">Make Admin</button>
                                <?php endif; ?>
                            </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>
