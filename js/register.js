document.addEventListener('DOMContentLoaded', function () {

    const params = new URLSearchParams(window.location.search);
    const msgBox = document.getElementById('msg');
    if (msgBox) {
        if (params.get('error'))   msgBox.innerHTML = '<div class="alert alert-error">'   + params.get('error') + '</div>';
        if (params.get('success')) msgBox.innerHTML = '<div class="alert alert-success">Account created! <a href="login.html">Sign in now</a>.</div>';
    }

    const nameInput = document.getElementById('name');
    const nameError = document.getElementById('name-error');
    if (nameInput) {
        nameInput.addEventListener('input', function () {
            const val = this.value.trim();
            if (val && !/^[a-zA-Z\s]+$/.test(val)) {
                nameError.style.display = 'block'; this.classList.add('invalid'); this.classList.remove('valid');
            } else if (val) {
                nameError.style.display = 'none'; this.classList.remove('invalid'); this.classList.add('valid');
            }
        });
    }

    const emailInput = document.getElementById('email');
    const emailError = document.getElementById('email-error');
    const allowedDomains = ['gmail.com','yahoo.com','outlook.com','hotmail.com','icloud.com','live.com'];
    if (emailInput) {
        emailInput.addEventListener('blur', function () {
            const val = this.value.trim();
            const domain = val.split('@')[1]?.toLowerCase();
            if (!val) return;
            if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(val) || !allowedDomains.includes(domain)) {
                emailError.style.display = 'block'; this.classList.add('invalid'); this.classList.remove('valid');
            } else {
                emailError.style.display = 'none'; this.classList.remove('invalid'); this.classList.add('valid');
            }
        });
    }

    const passInput     = document.getElementById('password');
    const strengthFill  = document.getElementById('strength-fill');
    const strengthLabel = document.getElementById('strength-label');

    function checkStrength(pass) {
        let score = 0;
        if (pass.length >= 8)   score++;
        if (/[A-Z]/.test(pass)) score++;
        if (/[a-z]/.test(pass)) score++;
        if (/[0-9]/.test(pass)) score++;
        if (/[\W_]/.test(pass)) score++;
        return score;
    }

    if (passInput) {
        passInput.addEventListener('input', function () {
            const score  = checkStrength(this.value);
            const colors = ['','#f85149','#e3b341','#e3b341','#3fb950','#3fb950'];
            const labels = ['','Very Weak','Weak','Fair','Strong','Very Strong'];
            if (strengthFill)  { strengthFill.style.width = (score/5*100) + '%'; strengthFill.style.background = colors[score]; }
            if (strengthLabel) { strengthLabel.textContent = score > 0 ? labels[score] : ''; }
        });
    }

    const pass2Input = document.getElementById('password2');
    const pass2Error = document.getElementById('pass2-error');
    if (pass2Input) {
        pass2Input.addEventListener('input', function () {
            if (passInput && this.value && this.value !== passInput.value) {
                pass2Error.style.display = 'block'; this.classList.add('invalid'); this.classList.remove('valid');
            } else if (this.value) {
                pass2Error.style.display = 'none'; this.classList.remove('invalid'); this.classList.add('valid');
            }
        });
    }

    const form = document.getElementById('register-form');
    if (form) {
        form.addEventListener('submit', function (e) {
            const name   = document.getElementById('name').value.trim();
            const email  = document.getElementById('email').value.trim();
            const pass   = document.getElementById('password').value;
            const pass2  = document.getElementById('password2').value;
            const income = parseFloat(document.getElementById('monthly_income').value);
            const domain = email.split('@')[1]?.toLowerCase();

            if (!/^[a-zA-Z\s]+$/.test(name)) {
                e.preventDefault(); msgBox.innerHTML = '<div class="alert alert-error">Name must contain letters only.</div>'; return;
            }
            if (!allowedDomains.includes(domain)) {
                e.preventDefault(); msgBox.innerHTML = '<div class="alert alert-error">Use a valid email provider (e.g. @gmail.com).</div>'; return;
            }
            if (checkStrength(pass) < 4) {
                e.preventDefault(); msgBox.innerHTML = '<div class="alert alert-error">Password is too weak. Use uppercase, lowercase, numbers and symbols.</div>'; return;
            }
            if (pass !== pass2) {
                e.preventDefault(); msgBox.innerHTML = '<div class="alert alert-error">Passwords do not match.</div>'; return;
            }
            if (!income || income <= 0) {
                e.preventDefault(); msgBox.innerHTML = '<div class="alert alert-error">Enter your monthly income.</div>'; return;
            }
        });
    }
});
