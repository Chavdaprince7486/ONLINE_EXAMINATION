(() => {

    'use strict';


    /*
    |--------------------------------------------------------------------------
    | GLOBAL CSRF
    |--------------------------------------------------------------------------
    */

    const csrf =
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
        document
            .querySelector(
                'input[name="csrf_token"]'
            )
            ?.value
        ||
        '';


    /*
    |--------------------------------------------------------------------------
    | ELEMENTS
    |--------------------------------------------------------------------------
    */

    const profileForm =
        document.getElementById(
            'profileForm'
        );


    const passwordForm =
        document.getElementById(
            'passwordForm'
        );


    const photoInput =
        document.getElementById(
            'profilePhoto'
        );


    const profilePreview =
        document.getElementById(
            'profilePreview'
        );


    /*
    |--------------------------------------------------------------------------
    | SAFE NOTIFICATION
    |--------------------------------------------------------------------------
    */

    const notify = (
        icon,
        title,
        text
    ) => {

        if (
            window.Swal
        ) {

            return Swal.fire({
                icon,
                title,
                text,
                confirmButtonColor:
                    '#556B2F',
                confirmButtonText:
                    'OK'
            });
        }


        window.alert(
            `${title}: ${text}`
        );


        return Promise.resolve();
    };


    /*
    |--------------------------------------------------------------------------
    | JSON REQUEST
    |--------------------------------------------------------------------------
    */

    const requestJson = async (
        url,
        options = {}
    ) => {

        const headers = {
            Accept:
                'application/json',

            ...(options.headers || {})
        };


        const response =
            await fetch(
                url,
                {
                    ...options,

                    credentials:
                        'same-origin',

                    headers
                }
            );


        const raw =
            await response.text();


        let data = {};


        try {

            data =
                raw
                    ? JSON.parse(
                        raw
                    )
                    : {};

        } catch (
            error
        ) {

            throw new Error(
                'The server returned an invalid response.'
            );
        }


        if (
            !response.ok
            ||
            data.status !==
            'success'
        ) {

            throw new Error(
                data.message
                ||
                'Request failed.'
            );
        }


        return data;
    };


    /*
    |--------------------------------------------------------------------------
    | UPDATE ALL STUDENT PHOTO ELEMENTS
    |--------------------------------------------------------------------------
    */

    const updateStudentPhotos = (
        photoUrl
    ) => {

        if (
            !photoUrl
        ) {
            return;
        }


        const finalUrl =
            photoUrl.includes('?')
                ? photoUrl
                : (
                    photoUrl
                    +
                    (
                        photoUrl.includes(
                            '?'
                        )
                            ? '&'
                            : '?'
                    )
                    +
                    't='
                    +
                    Date.now()
                );


        const photoElements =
            document.querySelectorAll(
                '[data-student-photo]'
            );


        photoElements.forEach(
            (
                image
            ) => {

                image.src =
                    finalUrl;

                image.removeAttribute(
                    'srcset'
                );

                image.setAttribute(
                    'data-photo-version',
                    String(
                        Date.now()
                    )
                );
            }
        );


        /*
        | Backward compatibility:
        | profile pages or older navbar markup.
        */

        const legacyNavbarPhoto =
            document.getElementById(
                'studentNavbarPhoto'
            );


        if (
            legacyNavbarPhoto
        ) {

            legacyNavbarPhoto.src =
                finalUrl;

            legacyNavbarPhoto.removeAttribute(
                'srcset'
            );
        }


        if (
            profilePreview
        ) {

            profilePreview.src =
                finalUrl;

            profilePreview.removeAttribute(
                'srcset'
            );
        }
    };


    /*
    |--------------------------------------------------------------------------
    | PROFILE FORM
    |--------------------------------------------------------------------------
    */

    profileForm?.addEventListener(
        'submit',
        async (
            event
        ) => {

            event.preventDefault();


            const button =
                profileForm.querySelector(
                    'button[type="submit"]'
                );


            if (
                !button
            ) {

                return;
            }


            const originalText =
                button.innerHTML;


            button.disabled =
                true;


            button.innerHTML =
                `
                <i class="
                    fa-solid
                    fa-spinner
                    fa-spin
                "></i>
                Saving…
                `;


            const payload =
                new FormData(
                    profileForm
                );


            if (
                !payload.get(
                    'csrf_token'
                )
            ) {

                payload.append(
                    'csrf_token',
                    csrf
                );
            }


            try {

                const data =
                    await requestJson(
                        'ajax/update_profile.php',
                        {
                            method:
                                'POST',

                            body:
                                payload
                        }
                    );


                await notify(
                    'success',
                    data.title
                    ||
                    'Profile Updated',
                    data.message
                    ||
                    'Your profile has been updated.'
                );


                window.location.reload();


            } catch (
                error
            ) {

                await notify(
                    'error',
                    'Update Failed',
                    error.message
                );


            } finally {

                button.disabled =
                    false;

                button.innerHTML =
                    originalText;
            }

        }
    );


    /*
    |--------------------------------------------------------------------------
    | PASSWORD FORM
    |--------------------------------------------------------------------------
    */

    passwordForm?.addEventListener(
        'submit',
        async (
            event
        ) => {

            event.preventDefault();


            const formData =
                new FormData(
                    passwordForm
                );


            const payload = {};


            formData.forEach(
                (
                    value,
                    key
                ) => {

                    payload[key] =
                        String(
                            value
                        );
                }
            );


            payload.csrf_token =
                payload.csrf_token
                ||
                csrf;


            const button =
                passwordForm.querySelector(
                    'button[type="submit"]'
                );


            const originalText =
                button?.innerHTML
                ||
                '';


            if (
                button
            ) {

                button.disabled =
                    true;


                button.innerHTML =
                    `
                    <i class="
                        fa-solid
                        fa-spinner
                        fa-spin
                    "></i>
                    Updating…
                    `;
            }


            try {

                const data =
                    await requestJson(
                        'ajax/change_password.php',
                        {
                            method:
                                'POST',

                            headers: {
                                'Content-Type':
                                    'application/json'
                            },

                            body:
                                JSON.stringify(
                                    payload
                                )
                        }
                    );


                passwordForm.reset();


                await notify(
                    'success',
                    data.title
                    ||
                    'Password Updated',
                    data.message
                    ||
                    'Password updated successfully.'
                );


            } catch (
                error
            ) {

                await notify(
                    'error',
                    'Password Update Failed',
                    error.message
                );


            } finally {

                if (
                    button
                ) {

                    button.disabled =
                        false;

                    button.innerHTML =
                        originalText;
                }
            }

        }
    );


    /*
    |--------------------------------------------------------------------------
    | PHOTO UPLOAD
    |--------------------------------------------------------------------------
    */

    photoInput?.addEventListener(
        'change',
        async () => {

            const file =
                photoInput.files?.[0];


            if (
                !file
            ) {

                return;
            }


            /*
            | Client-side validation
            */

            const allowedTypes = [
                'image/jpeg',
                'image/png',
                'image/webp'
            ];


            if (
                !allowedTypes.includes(
                    file.type
                )
            ) {

                await notify(
                    'error',
                    'Invalid Image',
                    'Please choose a JPG, PNG or WebP image.'
                );


                photoInput.value =
                    '';


                return;
            }


            if (
                file.size >
                2 * 1024 * 1024
            ) {

                await notify(
                    'warning',
                    'Image Too Large',
                    'Maximum image size is 2 MB.'
                );


                photoInput.value =
                    '';


                return;
            }


            /*
            | Instant preview
            */

            if (
                profilePreview
            ) {

                const localUrl =
                    URL.createObjectURL(
                        file
                    );


                profilePreview.src =
                    localUrl;


                profilePreview.onload =
                    () => {

                        URL.revokeObjectURL(
                            localUrl
                        );
                    };
            }


            /*
            | Loading state
            */

            const avatarUpload =
                document.querySelector(
                    '.avatar-upload'
                );


            const oldAvatarHtml =
                avatarUpload?.innerHTML
                ||
                '';


            if (
                avatarUpload
            ) {

                avatarUpload.style.pointerEvents =
                    'none';


                avatarUpload.innerHTML =
                    `
                    <i class="
                        fa-solid
                        fa-spinner
                        fa-spin
                    "></i>
                    `;
            }


            const payload =
                new FormData();


            payload.append(
                'profile_photo',
                file
            );


            payload.append(
                'csrf_token',
                csrf
            );


            try {

                const data =
                    await requestJson(
                        new URL(
                            'ajax/upload_profile_photo.php',
                            window.location.href
                        ).pathname,
                        {
                            method:
                                'POST',

                            body:
                                payload
                        }
                    );


                const photoUrl =
                    data.photo_url
                    ||
                    data.photo
                    ||
                    '';


                if (
                    photoUrl
                ) {

                    updateStudentPhotos(
                        photoUrl
                    );
                }


                await notify(
                    'success',
                    data.title
                    ||
                    'Photo Updated',
                    data.message
                    ||
                    'Your profile photo has been updated successfully.'
                );


            } catch (
                error
            ) {

                /*
                | Restore database/current image.
                */

                window.location.reload();


                await notify(
                    'error',
                    'Upload Failed',
                    error.message
                );


            } finally {

                if (
                    avatarUpload
                ) {

                    avatarUpload.style.pointerEvents =
                        '';

                    avatarUpload.innerHTML =
                        oldAvatarHtml;
                }


                photoInput.value =
                    '';
            }

        }
    );


})();