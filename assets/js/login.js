document.addEventListener("DOMContentLoaded", function () {

    const password = document.getElementById("password");
    const toggle = document.getElementById("togglePassword");

    if (password && toggle) {

        toggle.addEventListener("click", function () {

            const icon = this.querySelector("i");

            if (password.type === "password") {

                password.type = "text";
                icon.classList.replace("fa-eye", "fa-eye-slash");

            } else {

                password.type = "password";
                icon.classList.replace("fa-eye-slash", "fa-eye");

            }

        });

    }

    const roleButtons = document.querySelectorAll(".role-btn");
    const roleInput = document.getElementById("role");

    roleButtons.forEach(button => {

        button.addEventListener("click", function () {

            roleButtons.forEach(btn => btn.classList.remove("active"));

            this.classList.add("active");

            roleInput.value = this.dataset.role;

        });

    });

});