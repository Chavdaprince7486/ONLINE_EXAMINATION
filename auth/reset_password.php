<?php

declare(strict_types=1);

require_once '../config/session.php';


/*
|--------------------------------------------------------------------------
| RESET AUTHORIZATION
|--------------------------------------------------------------------------
*/

if (
    empty(
        $_SESSION['forgot_password']
    )
    ||
    empty(
        $_SESSION['password_reset_verified']
    )
    ||
    empty(
        $_SESSION['forgot_password']['verified']
    )
) {

    header(
        'Location: forgot_password.php'
    );

    exit;
}


/*
|--------------------------------------------------------------------------
| RESET ERROR
|--------------------------------------------------------------------------
*/

$error =
    (string) (
        $_SESSION[
            'reset_password_error'
        ]
        ?? ''
    );


unset(
    $_SESSION[
        'reset_password_error'
    ]
);


/*
|--------------------------------------------------------------------------
| ACCOUNT EMAIL
|--------------------------------------------------------------------------
*/

$email =
    htmlspecialchars(
        (string) (
            $_SESSION[
                'forgot_password'
            ]['email']
            ?? ''
        ),
        ENT_QUOTES |
        ENT_SUBSTITUTE,
        'UTF-8'
    );


/*
|--------------------------------------------------------------------------
| BASIC SESSION INTEGRITY
|--------------------------------------------------------------------------
*/

if (
    $email === ''
) {

    unset(
        $_SESSION[
            'forgot_password'
        ],
        $_SESSION[
            'password_reset_verified'
        ]
    );

    $_SESSION[
        'forgot_error'
    ] =
        'Your password reset session is invalid. Please request a new OTP.';

    header(
        'Location: forgot_password.php'
    );

    exit;
}

?>

<!DOCTYPE html>

<html lang="en">

<head>

<meta charset="UTF-8">


<meta
    name="viewport"
    content="width=device-width, initial-scale=1.0"
>


<meta
    name="color-scheme"
    content="light"
>


<title>
    Reset Password | ExamSphere
</title>


<link
    href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/css/bootstrap.min.css"
    rel="stylesheet"
>


<link
    rel="stylesheet"
    href="../assets/css/login.css"
>


<style>

/*
|--------------------------------------------------------------------------
| PASSWORD RULE
|--------------------------------------------------------------------------
*/

.password-rule {

    margin-top:
        10px;

    color:
        #777777;

    font-size:
        13px;

    line-height:
        1.6;

}


/*
|--------------------------------------------------------------------------
| PASSWORD RULE LIST
|--------------------------------------------------------------------------
*/

.password-rules {

    margin:
        12px 0 0;

    padding:
        0;

    list-style:
        none;

    font-size:
        13px;

    line-height:
        1.7;

}


.password-rules li {

    color:
        #777777;

    transition:
        color .2s ease;

}


.password-rules li::before {

    content:
        "○";

    display:
        inline-block;

    width:
        20px;

    color:
        #999999;

}


.password-rules li.valid {

    color:
        #2E7D32;

}


.password-rules li.valid::before {

    content:
        "✓";

    color:
        #2E7D32;

}


/*
|--------------------------------------------------------------------------
| PASSWORD INPUT WRAPPER
|--------------------------------------------------------------------------
*/

.password-input-box {

    position:
        relative;

}


.password-input-box input {

    padding-right:
        50px;

}


/*
|--------------------------------------------------------------------------
| PASSWORD VISIBILITY BUTTON
|--------------------------------------------------------------------------
*/

.password-toggle {

    position:
        absolute;

    top:
        50%;

    right:
        12px;

    width:
        36px;

    height:
        36px;

    padding:
        0;

    display:
        flex;

    align-items:
        center;

    justify-content:
        center;

    transform:
        translateY(-50%);

    border:
        0;

    background:
        transparent;

    color:
        #777777;

    cursor:
        pointer;

}


.password-toggle:hover {

    color:
        #5D4037;

}


/*
|--------------------------------------------------------------------------
| MATCH MESSAGE
|--------------------------------------------------------------------------
*/

.password-match {

    min-height:
        20px;

    margin-top:
        7px;

    font-size:
        13px;

}


.password-match.success {

    color:
        #2E7D32;

}


.password-match.error {

    color:
        #B3261E;

}


/*
|--------------------------------------------------------------------------
| SUBMIT BUTTON
|--------------------------------------------------------------------------
*/

.login-btn:disabled {

    opacity:
        .55;

    cursor:
        not-allowed;

}


</style>

</head>


<body>


<div class="login-wrapper">


<div class="login-card">


    <!-- ==========================================================
         LOGO
    =========================================================== -->

    <div class="logo-area">


        <img
            src="../assets/images/exam_logo.png"
            alt="ExamSphere"
        >


        <h2>
            ExamSphere
        </h2>


        <p>
            Create New Password
        </p>


    </div>


    <!-- ==========================================================
         PAGE TITLE
    =========================================================== -->

    <div class="login-title">


        <h1>
            Reset Password
        </h1>


        <p>

            Account:
            <br>


            <strong>
                <?= $email ?>
            </strong>

        </p>


    </div>


    <!-- ==========================================================
         ERROR
    =========================================================== -->

    <?php if (
        $error !== ''
    ): ?>


        <div
            class="error-box"
            role="alert"
        >

            <?= htmlspecialchars(
                $error,
                ENT_QUOTES |
                ENT_SUBSTITUTE,
                'UTF-8'
            ) ?>


        </div>


    <?php endif; ?>


    <!-- ==========================================================
         FORM
    =========================================================== -->

    <form
        action="reset_password_process.php"
        method="POST"
        autocomplete="off"
        id="resetPasswordForm"
        novalidate
    >


        <?= csrf_field() ?>


        <!-- ======================================================
             NEW PASSWORD
        ======================================================= -->

        <div class="form-group">


            <label
                for="password"
            >

                New Password

            </label>


            <div
                class="input-box password-input-box"
            >


                <input
                    id="password"
                    type="password"
                    name="password"
                    minlength="8"
                    maxlength="72"
                    autocomplete="new-password"
                    placeholder="Enter New Password"
                    required
                    aria-describedby="passwordRules"
                >


                <button
                    type="button"
                    class="password-toggle"
                    id="togglePassword"
                    aria-label="Show password"
                >

                    <span
                        id="passwordToggleIcon"
                    >
                        👁
                    </span>

                </button>


            </div>


            <ul
                class="password-rules"
                id="passwordRules"
            >

                <li
                    id="ruleLength"
                >
                    At least 8 characters
                </li>


                <li
                    id="ruleLetter"
                >
                    At least one letter
                </li>


                <li
                    id="ruleNumber"
                >
                    At least one number
                </li>

            </ul>


        </div>


        <!-- ======================================================
             CONFIRM PASSWORD
        ======================================================= -->

        <div class="form-group">


            <label
                for="confirm_password"
            >

                Confirm Password

            </label>


            <div
                class="input-box password-input-box"
            >


                <input
                    id="confirm_password"
                    type="password"
                    name="confirm_password"
                    minlength="8"
                    maxlength="72"
                    autocomplete="new-password"
                    placeholder="Confirm New Password"
                    required
                    aria-describedby="passwordMatch"
                >


                <button
                    type="button"
                    class="password-toggle"
                    id="toggleConfirmPassword"
                    aria-label="Show confirm password"
                >

                    <span
                        id="confirmPasswordToggleIcon"
                    >
                        👁
                    </span>

                </button>


            </div>


            <div
                id="passwordMatch"
                class="password-match"
                aria-live="polite"
            ></div>


        </div>


        <!-- ======================================================
             SUBMIT
        ======================================================= -->

        <button
            class="login-btn"
            id="updatePasswordButton"
            type="submit"
            disabled
        >

            Update Password

        </button>


    </form>


</div>


</div>


<script>

(function () {

    'use strict';


    const form =
        document.getElementById(
            'resetPasswordForm'
        );


    const password =
        document.getElementById(
            'password'
        );


    const confirmPassword =
        document.getElementById(
            'confirm_password'
        );


    const button =
        document.getElementById(
            'updatePasswordButton'
        );


    const matchMessage =
        document.getElementById(
            'passwordMatch'
        );


    const ruleLength =
        document.getElementById(
            'ruleLength'
        );


    const ruleLetter =
        document.getElementById(
            'ruleLetter'
        );


    const ruleNumber =
        document.getElementById(
            'ruleNumber'
        );


    const togglePassword =
        document.getElementById(
            'togglePassword'
        );


    const toggleConfirmPassword =
        document.getElementById(
            'toggleConfirmPassword'
        );


    const passwordToggleIcon =
        document.getElementById(
            'passwordToggleIcon'
        );


    const confirmPasswordToggleIcon =
        document.getElementById(
            'confirmPasswordToggleIcon'
        );


    let submitted =
        false;


    /*
    |--------------------------------------------------------------------------
    | VALIDATE PASSWORD
    |--------------------------------------------------------------------------
    */

    function validatePassword(
        value
    ) {

        const lengthValid =
            value.length >= 8
            &&
            value.length <= 72;


        const letterValid =
            /[A-Za-z]/.test(
                value
            );


        const numberValid =
            /[0-9]/.test(
                value
            );


        return {

            lengthValid:
                lengthValid,

            letterValid:
                letterValid,

            numberValid:
                numberValid,

            valid:
                lengthValid
                &&
                letterValid
                &&
                numberValid

        };

    }


    /*
    |--------------------------------------------------------------------------
    | UPDATE RULE UI
    |--------------------------------------------------------------------------
    */

    function updateRules(
        state
    ) {

        if (
            ruleLength
        ) {

            ruleLength.classList.toggle(
                'valid',
                state.lengthValid
            );

        }


        if (
            ruleLetter
        ) {

            ruleLetter.classList.toggle(
                'valid',
                state.letterValid
            );

        }


        if (
            ruleNumber
        ) {

            ruleNumber.classList.toggle(
                'valid',
                state.numberValid
            );

        }

    }


    /*
    |--------------------------------------------------------------------------
    | PASSWORD MATCH
    |--------------------------------------------------------------------------
    */

    function updateMatch(
        passwordValue,
        confirmValue
    ) {

        if (
            !matchMessage
        ) {

            return false;

        }


        matchMessage.className =
            'password-match';


        matchMessage.textContent =
            '';


        if (
            confirmValue === ''
        ) {

            return false;

        }


        if (
            passwordValue ===
            confirmValue
        ) {

            matchMessage.classList.add(
                'success'
            );


            matchMessage.textContent =
                'Passwords match.';


            return true;

        }


        matchMessage.classList.add(
            'error'
        );


        matchMessage.textContent =
            'Passwords do not match.';


        return false;

    }


    /*
    |--------------------------------------------------------------------------
    | FORM STATE
    |--------------------------------------------------------------------------
    */

    function updateFormState() {

        const passwordValue =
            password
                ? password.value
                : '';


        const confirmValue =
            confirmPassword
                ? confirmPassword.value
                : '';


        const state =
            validatePassword(
                passwordValue
            );


        updateRules(
            state
        );


        const passwordsMatch =
            updateMatch(
                passwordValue,
                confirmValue
            );


        const ready =
            state.valid
            &&
            passwordsMatch
            &&
            confirmValue.length >= 8
            &&
            !submitted;


        if (
            button
        ) {

            button.disabled =
                !ready;

        }


        return {

            state:
                state,

            passwordsMatch:
                passwordsMatch,

            ready:
                ready

        };

    }


    /*
    |--------------------------------------------------------------------------
    | TOGGLE PASSWORD
    |--------------------------------------------------------------------------
    */

    function setupToggle(
        toggleButton,
        input,
        icon,
        label
    ) {

        if (
            !toggleButton
            ||
            !input
        ) {

            return;

        }


        toggleButton.addEventListener(
            'click',
            function () {

                const visible =
                    input.type ===
                    'text';


                input.type =
                    visible
                        ? 'password'
                        : 'text';


                if (
                    icon
                ) {

                    icon.textContent =
                        visible
                            ? '👁'
                            : '🙈';

                }


                toggleButton.setAttribute(
                    'aria-label',
                    visible
                        ? 'Show ' + label
                        : 'Hide ' + label
                );


                input.focus();

            }
        );

    }


    setupToggle(
        togglePassword,
        password,
        passwordToggleIcon,
        'password'
    );


    setupToggle(
        toggleConfirmPassword,
        confirmPassword,
        confirmPasswordToggleIcon,
        'confirm password'
    );


    /*
    |--------------------------------------------------------------------------
    | INPUT EVENTS
    |--------------------------------------------------------------------------
    */

    if (
        password
    ) {

        password.addEventListener(
            'input',
            updateFormState
        );

    }


    if (
        confirmPassword
    ) {

        confirmPassword.addEventListener(
            'input',
            updateFormState
        );

    }


    /*
    |--------------------------------------------------------------------------
    | SUBMIT
    |--------------------------------------------------------------------------
    */

    if (
        form
    ) {

        form.addEventListener(
            'submit',
            function (
                event
            ) {

                if (
                    submitted
                ) {

                    event.preventDefault();

                    return;

                }


                const result =
                    updateFormState();


                if (
                    !result.ready
                ) {

                    event.preventDefault();


                    if (
                        password &&
                        !result.state.valid
                    ) {

                        password.focus();

                    } else if (
                        confirmPassword
                    ) {

                        confirmPassword.focus();

                    }


                    return;

                }


                submitted =
                    true;


                if (
                    password
                ) {

                    password.disabled =
                        true;

                }


                if (
                    confirmPassword
                ) {

                    confirmPassword.disabled =
                        true;

                }


                if (
                    button
                ) {

                    button.disabled =
                        true;


                    button.textContent =
                        'Updating...';

                }

            }
        );

    }


    /*
    |--------------------------------------------------------------------------
    | INITIAL STATE
    |--------------------------------------------------------------------------
    */

    updateFormState();

})();

</script>


</body>

</html>