// assets/js/admin.js
document.addEventListener('DOMContentLoaded', function () {
    const hamburger = document.querySelector('.admin-hamburger');
    const sidebar = document.querySelector('.admin-sidebar');
    const overlay = document.createElement('div');

    overlay.className = 'sidebar-overlay';
    document.body.appendChild(overlay);

    if (hamburger) {
        hamburger.addEventListener('click', function () {
            sidebar.classList.toggle('active');
            overlay.classList.toggle('active');
        });
    }

    overlay.addEventListener('click', function () {
        sidebar.classList.remove('active');
        overlay.classList.remove('active');
    });
});
