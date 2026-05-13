document.addEventListener('DOMContentLoaded', function () {
    const params = new URLSearchParams(window.location.search);
    const msgBox = document.getElementById('msg');
    if (!msgBox) return;
    if (params.get('error'))   msgBox.innerHTML = '<div class="alert alert-error">'   + params.get('error') + '</div>';
    if (params.get('success')) msgBox.innerHTML = '<div class="alert alert-success">Loan application submitted! <a href="/loanapp/loans.php">View loans</a>.</div>';
});
