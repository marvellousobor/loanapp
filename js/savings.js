/**
 * js/savings.js
 * Handles savings wallet deposit and loan repayment via Paystack
 */

var publicKey = document.getElementById("paystack-public-key").value;
var userEmail = document.getElementById("user-actual-email").value;
var loanAmount =
  parseFloat(document.getElementById("active-loan-amount").value) || 0;
var loanId = document.getElementById("active-loan-id").value;
var savingsBal =
  parseFloat(document.getElementById("savings-balance").value) || 0;

// Deposit Modal
function openDepositModal() {
  document.getElementById("deposit-modal").style.display = "flex";
}
function closeDepositModal() {
  document.getElementById("deposit-modal").style.display = "none";
}
function setDeposit(val) {
  document.getElementById("deposit-amount").value = val;
}

function confirmDeposit() {
  var amount = parseFloat(document.getElementById("deposit-amount").value);
  if (!amount || amount < 100) {
    alert("Minimum deposit is ₦100");
    return;
  }

  var ref = "savings_" + Date.now() + "_" + Math.floor(Math.random() * 99999);

  var handler = PaystackPop.setup({
    key: publicKey,
    email: userEmail,
    amount: Math.round(amount * 100),
    currency: "NGN",
    ref: ref,
    label: "Savings Deposit",
    onClose: function () {
      alert("Payment window closed. No charge was made.");
    },
    callback: function (response) {
      closeDepositModal();
      window.location.href =
        "/loanapp/php/savings/verify_deposit.php?reference=" +
        response.reference;
    },
  });

  handler.openIframe();
}

// ── Repay via Paystack ────────────────────────────────────────────────────────
function repayViaPaystack() {
  if (!loanId || loanAmount <= 0) {
    alert("No active loan found.");
    return;
  }

  var ref = "repay_" + Date.now() + "_" + Math.floor(Math.random() * 99999);

  var handler = PaystackPop.setup({
    key: publicKey,
    email: userEmail,
    amount: Math.round(loanAmount * 100),
    currency: "NGN",
    ref: ref,
    label: "Loan Repayment",
    onClose: function () {
      alert("Payment window closed. No charge was made.");
    },
    callback: function (response) {
      window.location.href =
        "/loanapp/php/loans/verify_repayment.php?reference=" +
        response.reference +
        "&loan_id=" +
        loanId;
    },
  });

  handler.openIframe();
}
