document.addEventListener('DOMContentLoaded', function () {

    document.querySelectorAll('.toggle-password').forEach(function (toggle) {

        toggle.addEventListener('click', function () {

            const targetId = toggle.getAttribute('data-target');
            const input = document.getElementById(targetId);
            const icon = toggle.querySelector('i');

            if (!input) return;

            if (input.type === 'password') {
                input.type = 'text';
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            } else {
                input.type = 'password';
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            }

        });

    });

    const form = document.getElementById('registerForm');
    const password = document.getElementById('password');
    const confirmPassword = document.getElementById('confirm_password');

    if (form && password && confirmPassword) {

        function checkMatch() {
            if (confirmPassword.value && password.value !== confirmPassword.value) {
                confirmPassword.setCustomValidity('Passwords do not match');
            } else {
                confirmPassword.setCustomValidity('');
            }
        }

        password.addEventListener('input', checkMatch);
        confirmPassword.addEventListener('input', checkMatch);

        form.addEventListener('submit', function (e) {
            checkMatch();
            if (!confirmPassword.checkValidity()) {
                e.preventDefault();
                confirmPassword.reportValidity();
            }
        });

    }

});