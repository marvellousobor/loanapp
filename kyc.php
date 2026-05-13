<?php
require 'php/config/db.php';
requireLogin();

$success = $_GET['success'] ?? '';
$error   = $_GET['error']   ?? '';

$stmt = $pdo->prepare("SELECT * FROM kyc WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$kyc = $stmt->fetch(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>KYC - LoanApp</title>
    <link rel="stylesheet" href="/loanapp/css/style.css">
</head>
<body>

    <nav>
        <a class="nav-brand" href="/loanapp/dashboard.php">⬡ LoanApp</a>
        <div class="nav-links">
            <a href="/loanapp/dashboard.php">Dashboard</a>
            <a href="/loanapp/loans.php">Loans</a>
            <a href="/loanapp/html/apply.php">Apply</a>
            <a href="/loanapp/kyc.php" class="active">KYC</a>
            <?php if (isAdmin()): ?>
            <a href="/loanapp/admin.php">Admin</a>
            <?php endif; ?>
            <a href="/loanapp/php/auth/logout.php" class="btn btn-ghost btn-sm">Logout</a>
        </div>
    </nav>

    <div class="container">
        <h1 class="page-title">KYC Verification</h1>

        <?php if ($success): ?><div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div><?php endif; ?>
        <?php if ($error):   ?><div class="alert alert-error"><?php   echo htmlspecialchars($error);   ?></div><?php endif; ?>

        <?php if ($kyc && $kyc['status'] === 'verified'): ?>
        <div class="alert alert-success">✔ Your KYC is verified. You are eligible to apply for loans.</div>
        <div class="form-box">
            <p style="color:var(--muted);font-size:.88rem;margin-bottom:1rem;">KYC Details on file:</p>
            <table style="width:100%">
                <tr><td style="color:var(--muted);padding:.5rem 0;font-size:.85rem;">NIN</td><td class="mono">***<?php echo substr($kyc['nin'],-4); ?></td></tr>
                <tr><td style="color:var(--muted);padding:.5rem 0;font-size:.85rem;">BVN</td><td class="mono">***<?php echo substr($kyc['bvn'],-4); ?></td></tr>
                <tr><td style="color:var(--muted);padding:.5rem 0;font-size:.85rem;">Status</td><td><span class="badge badge-verified">Verified</span></td></tr>
                <tr><td style="color:var(--muted);padding:.5rem 0;font-size:.85rem;">Submitted</td><td style="font-size:.85rem;"><?php echo date('M d, Y', strtotime($kyc['submitted_at'])); ?></td></tr>
            </table>
        </div>
        <?php else: ?>
        <div class="form-box">
            <p style="color:var(--muted);font-size:.88rem;margin-bottom:1.5rem;">Submit your NIN and BVN to verify your identity. Required before applying for a loan.</p>
            <form action="/loanapp/php/kyc/submit.php" method="POST">
                <div class="form-group">
                    <label for="nin">NIN (National Identification Number)</label>
                    <input type="text" id="nin" name="nin" placeholder="11-digit NIN" maxlength="11" required>
                    <div class="field-error" id="nin-error">NIN must be exactly 11 digits.</div>
                </div>
                <div class="form-group">
                    <label for="bvn">BVN (Bank Verification Number)</label>
                    <input type="text" id="bvn" name="bvn" placeholder="11-digit BVN" maxlength="11" required>
                    <div class="field-error" id="bvn-error">BVN must be exactly 11 digits.</div>
                </div>
                <button type="submit" class="btn btn-primary">Submit & Verify</button>
            </form>
        </div>
        <script src="/loanapp/js/kyc.js"></script>
        <?php endif; ?>
    </div>
</body>
</html>
