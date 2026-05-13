<?php
require 'php/config/db.php';
requireLogin();

$success = $_GET['success'] ?? '';
$error   = $_GET['error']   ?? '';

if (isAdmin()) {
    $loans = $pdo->query("SELECT l.*, u.name AS user_name, u.email AS user_email FROM loans l JOIN users u ON l.user_id = u.id ORDER BY l.created_at DESC")->fetchAll(PDO::FETCH_ASSOC);
} else {
    $stmt = $pdo->prepare("SELECT l.*, u.name AS user_name, u.email AS user_email FROM loans l JOIN users u ON l.user_id = u.id WHERE l.user_id = ? ORDER BY l.created_at DESC");
    $stmt->execute([$_SESSION['user_id']]);
    $loans = $stmt->fetchAll(PDO::FETCH_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Loans - LoanApp</title>
    <link rel="stylesheet" href="/loanapp/css/style.css">
</head>
<body>

    <nav>
        <a class="nav-brand" href="/loanapp/dashboard.php">⬡ LoanApp</a>
        <div class="nav-links">
            <a href="/loanapp/dashboard.php">Dashboard</a>
            <a href="/loanapp/loans.php" class="active">Loans</a>
            <a href="/loanapp/html/apply.html">Apply</a>
            <a href="/loanapp/kyc.php">KYC</a>
            <?php if (isAdmin()): ?>
            <a href="/loanapp/admin.php">Admin</a>
            <?php endif; ?>
            <a href="/loanapp/php/auth/logout.php" class="btn btn-ghost btn-sm">Logout</a>
        </div>
    </nav>

    <div class="container">
        <div class="action-row">
            <h1 class="page-title" style="margin-bottom:0;"><?php echo isAdmin() ? 'All Loan Applications' : 'My Loan Applications'; ?></h1>
            <a href="/loanapp/html/apply.html" class="btn btn-primary">+ New Application</a>
        </div>

        <?php if ($success): ?><div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div><?php endif; ?>
        <?php if ($error):   ?><div class="alert alert-error"><?php   echo htmlspecialchars($error);   ?></div><?php endif; ?>

        <div class="table-wrap">
            <?php if (empty($loans)): ?>
            <div class="empty"><div class="empty-icon">📋</div><p>No loan applications found.</p><a href="/loanapp/html/apply.html" class="btn btn-primary" style="margin-top:1rem;">Apply Now</a></div>
            <?php else: ?>
            <table>
                <thead>
                    <tr>
                        <th>#</th>
                        <?php if (isAdmin()): ?><th>Applicant</th><?php endif; ?>
                        <th>Amount (₦)</th>
                        <th>Purpose</th>
                        <th>Status</th>
                        <th>Date</th>
                        <?php if (isAdmin()): ?><th>Update</th><?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($loans as $loan): ?>
                    <tr>
                        <td class="mono" style="color:var(--muted);"><?php echo $loan['id']; ?></td>
                        <?php if (isAdmin()): ?>
                        <td>
                            <div><?php echo htmlspecialchars($loan['user_name']); ?></div>
                            <div style="font-size:.78rem;color:var(--muted);"><?php echo htmlspecialchars($loan['user_email']); ?></div>
                        </td>
                        <?php endif; ?>
                        <td class="amount"><?php echo number_format($loan['amount'], 2); ?></td>
                        <td style="color:var(--muted);max-width:200px;"><?php echo htmlspecialchars($loan['purpose']); ?></td>
                        <td><span class="badge badge-<?php echo $loan['status']; ?>"><?php echo ucfirst($loan['status']); ?></span></td>
                        <td style="color:var(--muted);font-size:.82rem;"><?php echo date('M d, Y', strtotime($loan['created_at'])); ?></td>
                        <?php if (isAdmin()): ?>
                        <td>
                            <form action="/loanapp/php/loans/update_status.php" method="POST" style="display:flex;gap:.4rem;">
                                <input type="hidden" name="loan_id" value="<?php echo $loan['id']; ?>">
                                <select name="status">
                                    <option value="pending"  <?php echo $loan['status']==='pending'  ?'selected':''; ?>>Pending</option>
                                    <option value="approved" <?php echo $loan['status']==='approved' ?'selected':''; ?>>Approved</option>
                                    <option value="rejected" <?php echo $loan['status']==='rejected' ?'selected':''; ?>>Rejected</option>
                                </select>
                                <button type="submit" class="btn btn-ghost btn-sm">Save</button>
                            </form>
                        </td>
                        <?php endif; ?>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
