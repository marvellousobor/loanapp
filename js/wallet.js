/**
 * js/wallet.js
 * Handles Paystack inline payment popup for admin wallet top-up.
 * Reads config from hidden inputs in admin_wallet.php — no inline JS needed.
 */

/**
 * Set amount input from quick-select buttons
 */
function setAmount(val) {
    document.getElementById('amount').value = val;

    // Highlight the active quick-amount button
    document.querySelectorAll('.quick-amounts button').forEach(function (btn) {
        btn.classList.toggle('active', parseInt(btn.dataset.value) === val);
    });
}

/**
 * Open Paystack inline popup and handle result
 */
function payWithPaystack() {
    var amountInput = document.getElementById('amount');
    var amount      = parseFloat(amountInput.value);
    var adminEmail  = document.getElementById('admin-email').value;
    var publicKey   = document.getElementById('paystack-public-key').value;

    // Validate
    if (!amount || amount < 100) {
        showToast('Minimum top-up amount is ₦100', 'error');
        amountInput.focus();
        return;
    }

    // Unique reference per attempt
    var ref = 'wallet_' + Date.now() + '_' + Math.floor(Math.random() * 99999);

    var handler = PaystackPop.setup({
        key:      publicKey,
        email:    adminEmail,
        amount:   Math.round(amount * 100), // Naira → kobo
        currency: 'NGN',
        ref:      ref,
        label:    'Admin Wallet Top-Up',
        metadata: {
            custom_fields: [{
                display_name:  'Payment Type',
                variable_name: 'payment_type',
                value:         'Admin Wallet Top-Up'
            }]
        },

        onClose: function () {
            showToast('Payment window closed. No charge was made.', 'info');
        },

        callback: function (response) {
            showToast('Payment successful! Verifying and crediting wallet…', 'success');
            // Send reference to backend to verify with Paystack API and credit wallet
            window.location.href = '/loanapp/php/wallet/verify_payment.php?reference=' + response.reference;
        }
    });

    handler.openIframe();
}

// ── Toast notification helper ─────────────────────────────────────────────────
function showToast(message, type) {
    type = type || 'info';

    var themes = {
        success: { bg: 'rgba(34,197,94,.15)',  border: 'rgba(34,197,94,.35)',  color: '#4ade80', icon: '✅' },
        error:   { bg: 'rgba(239,68,68,.15)',   border: 'rgba(239,68,68,.35)',  color: '#f87171', icon: '❌' },
        info:    { bg: 'rgba(99,102,241,.15)',  border: 'rgba(99,102,241,.35)', color: '#818cf8', icon: 'ℹ️'  }
    };
    var t = themes[type] || themes.info;

    // Remove any existing toast
    var existing = document.querySelector('.wallet-toast');
    if (existing) existing.remove();

    var toast = document.createElement('div');
    toast.className = 'wallet-toast';
    toast.innerHTML = '<span>' + t.icon + '</span> ' + message;
    toast.style.cssText = [
        'position:fixed',
        'bottom:1.5rem',
        'right:1.5rem',
        'z-index:9999',
        'background:' + t.bg,
        'border:1px solid ' + t.border,
        'color:' + t.color,
        'padding:.85rem 1.2rem',
        'border-radius:10px',
        'font-size:.9rem',
        'display:flex',
        'align-items:center',
        'gap:.5rem',
        'box-shadow:0 8px 24px rgba(0,0,0,.3)',
        'animation:walletToastIn .25s ease'
    ].join(';');

    // Inject keyframe once
    if (!document.getElementById('wallet-toast-style')) {
        var style = document.createElement('style');
        style.id  = 'wallet-toast-style';
        style.textContent = '@keyframes walletToastIn{from{opacity:0;transform:translateY(12px)}to{opacity:1;transform:translateY(0)}}';
        document.head.appendChild(style);
    }

    document.body.appendChild(toast);
    setTimeout(function () { if (toast.parentNode) toast.remove(); }, 4000);
}
