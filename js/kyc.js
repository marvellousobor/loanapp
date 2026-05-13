document.addEventListener('DOMContentLoaded', function () {
    const params = new URLSearchParams(window.location.search);
    const msgBox = document.getElementById('msg');
    if (msgBox) {
        if (params.get('error'))   msgBox.innerHTML = '<div class="alert alert-error">'   + params.get('error') + '</div>';
        if (params.get('success')) msgBox.innerHTML = '<div class="alert alert-success">'  + params.get('success') + '</div>';
    }

    ['nin','bvn'].forEach(function (id) {
        const input = document.getElementById(id);
        const error = document.getElementById(id + '-error');
        if (!input) return;
        input.addEventListener('input', function () {
            this.value = this.value.replace(/\D/g, '').slice(0, 11);
        });
        input.addEventListener('blur', function () {
            if (this.value.length > 0 && this.value.length !== 11) {
                error.style.display = 'block'; this.classList.add('invalid'); this.classList.remove('valid');
            } else if (this.value.length === 11) {
                error.style.display = 'none'; this.classList.remove('invalid'); this.classList.add('valid');
            }
        });
    });
});
