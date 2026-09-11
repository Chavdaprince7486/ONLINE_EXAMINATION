document.addEventListener('DOMContentLoaded', function () {
    var darkButton = document.querySelector('.teacher-dark-button');
    var menuButton = document.querySelector('.teacher-menu-button');
    var sidebar = document.querySelector('.portal-sidebar');

    if (localStorage.getItem('examsphereTeacherTheme') === 'dark') {
        document.body.classList.add('teacher-dark');
    }
    if (darkButton) {
        darkButton.addEventListener('click', function () {
            document.body.classList.toggle('teacher-dark');
            localStorage.setItem('examsphereTeacherTheme', document.body.classList.contains('teacher-dark') ? 'dark' : 'light');
        });
    }
    if (menuButton && sidebar) {
        menuButton.addEventListener('click', function () {
            var main = document.querySelector('.portal-main');
            var collapsed = sidebar.classList.toggle('teacher-sidebar-collapsed');
            if (window.innerWidth > 800 && main) {
                sidebar.style.setProperty('width', collapsed ? '82px' : '280px', 'important');
                main.style.setProperty('width', collapsed ? 'calc(100% - 82px)' : 'calc(100% - 280px)', 'important');
                main.style.setProperty('margin-left', collapsed ? '82px' : '280px', 'important');
                document.querySelector('.teacher-topbar').style.setProperty('left', collapsed ? '82px' : '280px', 'important');
            }
        });
    }
});
