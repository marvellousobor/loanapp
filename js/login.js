document.addEventListener('DOMContentLoaded', function () {
    const params = new URLSearchParams(window.location.search);
    const msgBox = document.getElementById('msg');
    if (params.get('error') && msgBox) {
        msgBox.innerHTML = '<div class="alert alert-error">' + params.get('error') + '</div>';
    }
});
