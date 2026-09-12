document.addEventListener('DOMContentLoaded', () => {

    const form = document.getElementById('registerForm');
    const password = document.getElementById('password');
    const confirmPassword = document.getElementById('confirm_password');
    const matchHint = document.getElementById('passwordMatchHint');
    const submitButton = document.getElementById('registerSubmit');

    const dob = document.getElementById('dob');
    const mobile = document.getElementById('mobile');
    const pincode = document.getElementById('pincode');

    const fullName = document.getElementById('full_name');
    const city = document.getElementById('city');
    const state = document.getElementById('state');

    document.querySelectorAll('.toggle-password').forEach((toggle) => {

        toggle.addEventListener('click', () => {

            const input =
                document.getElementById(
                    toggle.dataset.target
                );

            const icon =
                toggle.querySelector('i');

            if (!input || !icon) {
                return;
            }

            const show =
                input.type === 'password';

            input.type =
                show
                    ? 'text'
                    : 'password';

            icon.classList.toggle(
                'fa-eye',
                !show
            );

            icon.classList.toggle(
                'fa-eye-slash',
                show
            );

        });

    });

    const cleanDigits = (input, max) => {

        if (!input) {
            return;
        }

        input.value =
            input.value
                .replace(/\D/g, '')
                .slice(0, max);

    };

    mobile?.addEventListener(
        'input',
        () => cleanDigits(mobile, 15)
    );

    pincode?.addEventListener(
        'input',
        () => cleanDigits(pincode, 10)
    );

    [fullName, city, state].forEach((input) => {

        input?.addEventListener('blur', () => {

            input.value =
                input.value
                    .trim()
                    .replace(/\s+/g, ' ');

        });

    });

    function updatePasswordState() {

        if (!password) {
            return true;
        }

        const value =
            password.value;

        const bar =
            document.querySelector(
                '.strength-bar i'
            );

        const text =
            document.querySelector(
                '.strength-text'
            );

        let score = 0;

        if (value.length >= 8) score++;
        if (/[A-Za-z]/.test(value)) score++;
        if (/[0-9]/.test(value)) score++;
        if (/[^A-Za-z0-9]/.test(value)) score++;

        const widths = [
            '0%',
            '25%',
            '50%',
            '75%',
            '100%'
        ];

        if (bar) {
            bar.style.width =
                widths[score];
        }

        if (text) {

            text.textContent =
                score >= 4
                    ? 'Strong password.'
                    : score === 3
                        ? 'Good password.'
                        : 'Use at least 8 characters with letters and numbers.';

        }

        return (
            score >= 3 &&
            value.length >= 8
        );

    }

    function updateMatchState() {

        if (
            !password ||
            !confirmPassword
        ) {
            return true;
        }

        const valid =
            confirmPassword.value !== '' &&
            password.value ===
            confirmPassword.value;

        confirmPassword.setCustomValidity(
            valid
                ? ''
                : 'Passwords do not match.'
        );

        if (matchHint) {

            matchHint.textContent =
                confirmPassword.value === ''
                    ? 'Passwords must match.'
                    : valid
                        ? 'Passwords match.'
                        : 'Passwords do not match.';

        }

        return valid;

    }

    password?.addEventListener(
        'input',
        () => {
            updatePasswordState();
            updateMatchState();
        }
    );

    confirmPassword?.addEventListener(
        'input',
        updateMatchState
    );

    updatePasswordState();

    if (dob) {

        dob.addEventListener(
            'change',
            () => {

                if (!dob.value) {
                    return;
                }

                const selected =
                    new Date(
                        `${dob.value}T00:00:00`
                    );

                if (
                    Number.isNaN(
                        selected.getTime()
                    )
                ) {
                    return;
                }

                const minimumAgeDate =
                    new Date();

                minimumAgeDate.setFullYear(
                    minimumAgeDate.getFullYear() - 5
                );

                if (
                    selected >
                    minimumAgeDate
                ) {

                    dob.setCustomValidity(
                        'You must be at least 5 years old.'
                    );

                } else {

                    dob.setCustomValidity('');

                }

            }
        );

    }

    form?.addEventListener(
        'submit',
        (event) => {

            if (
                !form.checkValidity() ||
                !updatePasswordState() ||
                !updateMatchState()
            ) {

                event.preventDefault();

                form.reportValidity();

                return;
            }

            if (submitButton) {

                submitButton.disabled =
                    true;

                submitButton.innerHTML =
                    '<i class="fa-solid fa-spinner fa-spin"></i><span>Starting verification...</span>';

            }

        }
    );

});