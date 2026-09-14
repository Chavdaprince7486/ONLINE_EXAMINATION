(() => {

    'use strict';


    const csrfToken =
        window.EXAMSPHERE_CSRF_TOKEN
        ||
        document
            .querySelector(
                'meta[name="csrf-token"]'
            )
            ?.getAttribute(
                'content'
            )
        ||
        '';


    async function requestJson(
        url,
        options = {}
    ){

        const response =
            await fetch(
                url,
                {

                    ...options,

                    credentials:
                        'same-origin',

                    headers:{
                        Accept:
                            'application/json',

                        ...(options.headers || {})
                    }

                }
            );


        const data =
            await response
                .json()
                .catch(
                    () => ({})
                );


        if (
            !response.ok ||
            data.status !== 'success'
        ){

            const error =
                new Error(
                    data.message ||
                    'Request failed.'
                );


            error.response =
                data;


            throw error;

        }


        return data;

    }


    async function notify(
        icon,
        title,
        text
    ){

        if (
            window.Swal
        ){

            return Swal.fire({

                icon,
                title,
                text,

                confirmButtonColor:
                    '#556B2F',

                background:
                    '#FFFEFA',

                color:
                    '#3E2723'

            });

        }


        window.alert(
            `${title}: ${text}`
        );

    }


    /*
    |--------------------------------------------------------------------------
    | PROFILE SAVE
    |--------------------------------------------------------------------------
    */

    const profileForm =
        document.getElementById(
            'profileForm'
        );


    if (
        profileForm
    ){

        profileForm.addEventListener(
            'submit',
            async event => {

                event.preventDefault();


                const button =
                    profileForm.querySelector(
                        'button[type="submit"]'
                    );


                if (
                    !button
                ){

                    return;

                }


                const old =
                    button.innerHTML;


                button.disabled =
                    true;


                button.innerHTML =
                    '<i class="fa-solid fa-spinner fa-spin"></i> Saving...';


                const formData =
                    new FormData(
                        profileForm
                    );


                if (
                    !formData.get(
                        'csrf_token'
                    )
                ){

                    formData.append(
                        'csrf_token',
                        csrfToken
                    );

                }


                try{

                    const data =
                        await requestJson(
                            'ajax/update_profile.php',
                            {

                                method:
                                    'POST',

                                body:
                                    formData

                            }
                        );


                    await notify(
                        'success',
                        data.title ||
                            'Profile Updated',
                        data.message ||
                            'Your profile information has been updated.'
                    );


                    window.location.reload();


                }catch(
                    error
                ){

                    await notify(
                        'error',
                        error.response?.title ||
                            'Update Failed',
                        error.message ||
                            'Unable to update your profile.'
                    );

                }finally{

                    button.disabled =
                        false;


                    button.innerHTML =
                        old;

                }

            }
        );

    }


    /*
    |--------------------------------------------------------------------------
    | PHOTO UPLOAD
    |--------------------------------------------------------------------------
    */

    const photoInput =
        document.getElementById(
            'profilePhoto'
        );


    const photoPreview =
        document.getElementById(
            'profilePreview'
        );


    if (
        photoInput
    ){

        photoInput.addEventListener(
            'change',
            async () => {

                const file =
                    photoInput.files?.[0];


                if (
                    !file
                ){

                    return;

                }


                const allowedTypes = [

                    'image/jpeg',

                    'image/png',

                    'image/webp'

                ];


                if (
                    !allowedTypes.includes(
                        file.type
                    )
                ){

                    await notify(
                        'error',
                        'Invalid Image',
                        'Only JPG, PNG and WebP images are allowed.'
                    );


                    photoInput.value =
                        '';

                    return;

                }


                if (
                    file.size >
                    2 * 1024 * 1024
                ){

                    await notify(
                        'error',
                        'Image Too Large',
                        'Maximum profile photo size is 2 MB.'
                    );


                    photoInput.value =
                        '';

                    return;

                }


                /*
                |--------------------------------------------------------------------------
                | SHOW LOCAL PREVIEW
                |--------------------------------------------------------------------------
                */

                if (
                    photoPreview
                ){

                    const reader =
                        new FileReader();


                    reader.onload =
                        event => {

                            photoPreview.src =
                                String(
                                    event.target?.result
                                    ||
                                    ''
                                );

                        };


                    reader.readAsDataURL(
                        file
                    );

                }


                const formData =
                    new FormData();


                formData.append(
                    'profile_photo',
                    file
                );


                formData.append(
                    'csrf_token',
                    csrfToken
                );


                photoInput.disabled =
                    true;


                try{

                    const data =
                        await requestJson(
                            'ajax/upload_profile_photo.php',
                            {

                                method:
                                    'POST',

                                body:
                                    formData

                            }
                        );


                    if (
                        photoPreview &&
                        data.photo
                    ){

                        photoPreview.src =
                            `${data.photo}?t=${Date.now()}`;

                    }


                    await notify(
                        'success',
                        data.title ||
                            'Photo Updated',
                        data.message ||
                            'Your profile photo has been updated successfully.'
                    );


                }catch(
                    error
                ){

                    await notify(
                        'error',
                        error.response?.title ||
                            'Upload Failed',
                        error.message ||
                            'Unable to update your profile photo.'
                    );

                }finally{

                    photoInput.disabled =
                        false;

                    photoInput.value =
                        '';

                }

            }
        );

    }


    /*
    |--------------------------------------------------------------------------
    | PASSWORD
    |--------------------------------------------------------------------------
    */

    const modal =
        document.getElementById(
            'profilePasswordModal'
        );


    const passwordForm =
        document.getElementById(
            'passwordForm'
        );


    const closeButton =
        document.getElementById(
            'passwordModalClose'
        );


    const cancelButton =
        document.getElementById(
            'passwordCancel'
        );


    const currentPassword =
        document.getElementById(
            'currentPassword'
        );


    const newPassword =
        document.getElementById(
            'newPassword'
        );


    const confirmPassword =
        document.getElementById(
            'confirmPassword'
        );


    const submitButton =
        document.getElementById(
            'passwordSubmit'
        );


    const strengthBox =
        document.getElementById(
            'passwordStrength'
        );


    const strengthText =
        document.getElementById(
            'passwordStrengthText'
        );


    const strengthFill =
        document.getElementById(
            'passwordStrengthFill'
        );


    function openPasswordModal(){

        if (
            !modal
        ){

            return;

        }


        modal.classList.add(
            'is-visible'
        );


        modal.setAttribute(
            'aria-hidden',
            'false'
        );


        document.body.style.overflow =
            'hidden';


        window.setTimeout(
            () => {

                currentPassword?.focus();

            },
            100
        );

    }


    function resetPasswordForm(){

        passwordForm?.reset();


        if (
            strengthBox
        ){

            strengthBox.style.display =
                'none';

        }


        if (
            strengthText
        ){

            strengthText.textContent =
                '—';

        }


        if (
            strengthFill
        ){

            strengthFill.style.width =
                '0%';

        }

    }


    function closePasswordModal(){

        if (
            !modal
        ){

            return;

        }


        modal.classList.remove(
            'is-visible'
        );


        modal.setAttribute(
            'aria-hidden',
            'true'
        );


        document.body.style.overflow =
            '';


        resetPasswordForm();

    }


    closeButton?.addEventListener(
        'click',
        closePasswordModal
    );


    cancelButton?.addEventListener(
        'click',
        closePasswordModal
    );


    modal?.addEventListener(
        'click',
        event => {

            if (
                event.target ===
                modal
            ){

                closePasswordModal();

            }

        }
    );


    document.addEventListener(
        'keydown',
        event => {

            if (
                event.key ===
                'Escape'
            ){

                if (
                    modal?.classList.contains(
                        'is-visible'
                    )
                ){

                    closePasswordModal();

                }

            }

        }
    );


    /*
    |--------------------------------------------------------------------------
    | PASSWORD EYE
    |--------------------------------------------------------------------------
    */

    document
        .querySelectorAll(
            '[data-password-target]'
        )
        .forEach(
            button => {

                button.addEventListener(
                    'click',
                    () => {

                        const id =
                            button.getAttribute(
                                'data-password-target'
                            );


                        const input =
                            document.getElementById(
                                id
                            );


                        const icon =
                            button.querySelector(
                                'i'
                            );


                        if (
                            !input ||
                            !icon
                        ){

                            return;

                        }


                        if (
                            input.type ===
                            'password'
                        ){

                            input.type =
                                'text';

                            icon.className =
                                'fa-solid fa-eye-slash';

                        }else{

                            input.type =
                                'password';

                            icon.className =
                                'fa-solid fa-eye';

                        }

                    }
                );

            }
        );


    /*
    |--------------------------------------------------------------------------
    | STRENGTH
    |--------------------------------------------------------------------------
    */

    function passwordStrength(
        value
    ){

        let score =
            0;


        if (
            value.length >= 8
        ){

            score++;

        }


        if (
            value.length >= 12
        ){

            score++;

        }


        if (
            /[a-z]/.test(value)
        ){

            score++;

        }


        if (
            /[A-Z]/.test(value)
        ){

            score++;

        }


        if (
            /[0-9]/.test(value)
        ){

            score++;

        }


        if (
            /[^A-Za-z0-9]/.test(value)
        ){

            score++;

        }


        if (
            score <= 2
        ){

            return {

                label:'Weak',

                width:30,

                color:'#A64D45'

            };

        }


        if (
            score <= 4
        ){

            return {

                label:'Good',

                width:65,

                color:'#A47B29'

            };

        }


        return {

            label:'Strong',

            width:100,

            color:'#556B2F'

        };

    }


    newPassword?.addEventListener(
        'input',
        () => {

            const value =
                newPassword.value;


            if (
                value === ''
            ){

                if (
                    strengthBox
                ){

                    strengthBox.style.display =
                        'none';

                }

                return;

            }


            const result =
                passwordStrength(
                    value
                );


            if (
                strengthBox
            ){

                strengthBox.style.display =
                    'block';

            }


            if (
                strengthText
            ){

                strengthText.textContent =
                    result.label;

                strengthText.style.color =
                    result.color;

            }


            if (
                strengthFill
            ){

                strengthFill.style.width =
                    `${result.width}%`;

                strengthFill.style.background =
                    result.color;

            }

        }
    );


    /*
    |--------------------------------------------------------------------------
    | PASSWORD SUBMIT
    |--------------------------------------------------------------------------
    */

    passwordForm?.addEventListener(
        'submit',
        async event => {

            event.preventDefault();


            const current =
                currentPassword?.value
                || '';


            const next =
                newPassword?.value
                || '';


            const confirm =
                confirmPassword?.value
                || '';


            if (
                current === ''
            ){

                await notify(
                    'warning',
                    'Current Password Required',
                    'Please enter your current password.'
                );

                currentPassword?.focus();

                return;

            }


            if (
                next.length < 8 ||
                next.length > 72
            ){

                await notify(
                    'warning',
                    'Invalid Password',
                    'New password must be between 8 and 72 characters.'
                );

                newPassword?.focus();

                return;

            }


            if (
                !/[A-Za-z]/.test(next) ||
                !/[0-9]/.test(next)
            ){

                await notify(
                    'warning',
                    'Stronger Password Required',
                    'Use at least one letter and one number.'
                );

                newPassword?.focus();

                return;

            }


            if (
                next !== confirm
            ){

                await notify(
                    'warning',
                    'Password Mismatch',
                    'New password and confirm password do not match.'
                );

                confirmPassword?.focus();

                return;

            }


            if (
                current === next
            ){

                await notify(
                    'warning',
                    'Same Password',
                    'New password must be different from your current password.'
                );

                newPassword?.focus();

                return;

            }


            const old =
                submitButton?.innerHTML
                ||
                'Update Password';


            if (
                submitButton
            ){

                submitButton.disabled =
                    true;

                submitButton.innerHTML =
                    '<i class="fa-solid fa-spinner fa-spin"></i> Updating...';

            }


            try{

                const data =
                    await requestJson(
                        'ajax/change_password.php',
                        {

                            method:
                                'POST',

                            headers:{
                                'Content-Type':
                                    'application/json'
                            },

                            body:
                                JSON.stringify({

                                    current_password:
                                        current,

                                    new_password:
                                        next,

                                    confirm_password:
                                        confirm,

                                    csrf_token:
                                        csrfToken

                                })

                        }
                    );


                closePasswordModal();


                await notify(
                    'success',
                    data.title ||
                        'Password Updated',
                    data.message ||
                        'Your password has been changed successfully.'
                );


            }catch(
                error
            ){

                await notify(
                    'error',
                    error.response?.title ||
                        'Password Update Failed',
                    error.message ||
                        'Unable to update your password.'
                );

            }finally{

                if (
                    submitButton
                ){

                    submitButton.disabled =
                        false;

                    submitButton.innerHTML =
                        old;

                }

            }

        }
    );


    /*
    |--------------------------------------------------------------------------
    | NAVBAR CHANGE PASSWORD
    |--------------------------------------------------------------------------
    */

    const params =
        new URLSearchParams(
            window.location.search
        );


    if (
        params.get(
            'change_password'
        ) === '1'
    ){

        window.setTimeout(
            openPasswordModal,
            150
        );


        window.history.replaceState(
            {},
            document.title,
            window.location.pathname
        );

    }


})();