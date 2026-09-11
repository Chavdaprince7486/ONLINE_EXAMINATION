document.addEventListener('DOMContentLoaded', function () {
    const sidebar = document.getElementById('sidebar');
    const mainContent = document.querySelector('.main-content');
    const toggle = document.getElementById('toggleSidebar');

    if (sidebar && mainContent && toggle) {
        toggle.addEventListener('click', function () {
            sidebar.classList.toggle('hide');
            mainContent.classList.toggle('expand');
        });
    }

    const darkButton = document.querySelector('.dark-btn');
    if (darkButton) {
        darkButton.addEventListener('click', function () {
            document.body.classList.toggle('admin-dark-mode');
        });
    }
});
