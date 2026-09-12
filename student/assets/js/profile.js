document.addEventListener('DOMContentLoaded', () => {

    const profileForm =
        document.getElementById(
            'profileForm'
        );

    const profilePhoto =
        document.getElementById(
            'profilePhoto'
        );

    const profilePreview =
        document.getElementById(
            'profilePreview'
        );

    const editButton =
        document.querySelector(
            '.edit-profile-btn'
        );

    const changePasswordButton =
        document.querySelector(
            '.change-password-btn'
        );

    const csrfToken =
        profileForm
            ?.querySelector(
                'input[name="csrf_token"]'
            )
            ?.value
        ||
        document
            .querySelector(
                'meta[name="csrf-token"]'
            )
            ?.content
        ||
        '';

    const showDialog = (data) => {

        if (
            typeof Swal ===
            'undefined'
        ) {
            return;
        }

        Swal.fire({

            icon:
                data.status ||
                'info',

            title:
                data.title ||
                'ExamSphere',

            text:
                data.message ||
                '',

            confirmButtonColor:
                '#556B2F'

        });

    };

    const toast = (
        icon,
        title
    ) => {

        if (
            typeof Swal ===
            'undefined'
        ) {
            return;
        }

        Swal.fire({

            toast:true,

            position:'top-end',

            icon,

            title,

            showConfirmButton:false,

            timer:2200,

            timerProgressBar:true

        });

    };

    editButton?.addEventListener(
        'click',
        () => {

            const field =
                document.getElementById(
                    'profile_full_name'
                );

            field?.focus();

            field?.scrollIntoView({

                behavior:'smooth',

                block:'center'

            });

        }
    );

    const validateImage =
        (file) => {

            if (!file) {
                return false;
            }

            const allowedTypes = [
                'image/jpeg',
                'image/png'
            ];

            if (
                !allowedTypes.includes(
                    file.type
                )
            ) {

                showDialog({

                    status:'error',

                    title:'Invalid Image',

                    message:
                        'Please choose a JPG, JPEG or PNG image.'

                });

                return false;
            }

            if (
                file.size >
                2 * 1024 * 1024
            ) {

                showDialog({

                    status:'warning',

                    title:'Image Too Large',

                    message:
                        'Maximum image size is 2 MB.'

                });

                return false;
            }

            return true;

        };

    profilePhoto?.addEventListener(
        'change',
        () => {

            const file =
                profilePhoto.files?.[0];

            if (
                !validateImage(file)
            ) {

                profilePhoto.value =
                    '';

                return;

            }

            const reader =
                new FileReader();

            reader.onload =
                (event) => {

                    if (
                        profilePreview &&
                        typeof event.target?.result ===
                        'string'
                    ) {

                        profilePreview.src =
                            event.target.result;

                    }

                };

            reader.readAsDataURL(file);

            const uploadData =
                new FormData();

            uploadData.append(
                'profile_photo',
                file
            );

            uploadData.append(
                'csrf_token',
                csrfToken
            );

            fetch(
                'ajax/upload_profile_photo.php',
                {

                    method:'POST',

                    body:uploadData,

                    credentials:
                        'same-origin',

                    headers:{
                        Accept:
                            'application/json'
                    }

                }
            )

                .then(
                    async response => {

                        const data =
                            await response.json();

                        if (
                            !response.ok
                        ) {

                            throw new Error(
                                data.message ||
                                'Photo upload failed.'
                            );

                        }

                        return data;

                    }
                )

                .then(
                    data => {

                        showDialog(data);

                        if (
                            data.status ===
                            'success' &&
                            data.photo &&
                            profilePreview
                        ) {

                            profilePreview.src =
                                `${data.photo}?t=${Date.now()}`;

                        }

                    }
                )

                .catch(
                    error => {

                        showDialog({

                            status:'error',

                            title:'Upload Failed',

                            message:
                                error.message ||
                                'Unable to upload image.'

                        });

                    }
                );

        }
    );

    profileForm?.addEventListener(
        'submit',
        event => {

            event.preventDefault();

            const mobile =
                profileForm.querySelector(
                    'input[name="mobile"]'
                );

            if (mobile) {

                mobile.value =
                    mobile.value.replace(
                        /\D/g,
                        ''
                    );

            }

            if (
                !profileForm.checkValidity()
            ) {

                profileForm.reportValidity();

                return;

            }

            const button =
                profileForm.querySelector(
                    '.primary-btn[type="submit"]'
                );

            const original =
                button?.innerHTML ||
                '';

            if (button) {

                button.disabled =
                    true;

                button.innerHTML =
                    '<i class="fa-solid fa-spinner fa-spin"></i><span>Saving...</span>';

            }

            fetch(
                'ajax/update_profile.php',
                {

                    method:'POST',

                    body:
                        new FormData(
                            profileForm
                        ),

                    credentials:
                        'same-origin',

                    headers:{
                        Accept:
                            'application/json'
                    }

                }
            )

                .then(
                    async response => {

                        const data =
                            await response.json();

                        if (
                            !response.ok
                        ) {

                            throw new Error(
                                data.message ||
                                'Profile request failed.'
                            );

                        }

                        return data;

                    }
                )

                .then(
                    data => {

                        showDialog(data);

                        if (
                            data.status ===
                            'success'
                        ) {

                            const displayName =
                                document.querySelector(
                                    '.identity-copy h1'
                                );

                            if (
                                displayName
                            ) {

                                displayName.textContent =
                                    profileForm.querySelector(
                                        '[name="full_name"]'
                                    )?.value ||
                                    displayName.textContent;

                            }

                        }

                    }
                )

                .catch(
                    error => {

                        showDialog({

                            status:'error',

                            title:'Update Failed',

                            message:
                                error.message ||
                                'Unable to save your profile.'

                        });

                    }
                )

                .finally(
                    () => {

                        if (button) {

                            button.disabled =
                                false;

                            button.innerHTML =
                                original;

                        }

                    }
                );

        }
    );

    changePasswordButton?.addEventListener(
        'click',
        async () => {

            if (
                typeof Swal ===
                'undefined'
            ) {
                return;
            }

            const result =
                await Swal.fire({

                    title:
                        'Change Password',

                    html:`

                        <input
                            id="profileCurrentPassword"
                            class="swal2-input"
                            type="password"
                            autocomplete="current-password"
                            placeholder="Current password"
                        >

                        <input
                            id="profileNewPassword"
                            class="swal2-input"
                            type="password"
                            autocomplete="new-password"
                            placeholder="New password"
                        >

                        <input
                            id="profileConfirmPassword"
                            class="swal2-input"
                            type="password"
                            autocomplete="new-password"
                            placeholder="Confirm new password"
                        >

                    `,

                    showCancelButton:true,

                    confirmButtonText:
                        'Update Password',

                    confirmButtonColor:
                        '#556B2F',

                    preConfirm:() => {

                        const current =
                            document
                                .getElementById(
                                    'profileCurrentPassword'
                                )
                                ?.value ||
                            '';

                        const next =
                            document
                                .getElementById(
                                    'profileNewPassword'
                                )
                                ?.value ||
                            '';

                        const confirm =
                            document
                                .getElementById(
                                    'profileConfirmPassword'
                                )
                                ?.value ||
                            '';

                        if (
                            !current ||
                            !next ||
                            !confirm
                        ) {

                            Swal.showValidationMessage(
                                'All password fields are required.'
                            );

                            return false;

                        }

                        if (
                            next.length < 8 ||
                            next.length > 72 ||
                            !/[A-Za-z]/.test(
                                next
                            ) ||
                            !/[0-9]/.test(
                                next
                            )
                        ) {

                            Swal.showValidationMessage(
                                'Use 8–72 characters with at least one letter and one number.'
                            );

                            return false;

                        }

                        if (
                            next !==
                            confirm
                        ) {

                            Swal.showValidationMessage(
                                'Passwords do not match.'
                            );

                            return false;

                        }

                        return {

                            current_password:
                                current,

                            new_password:
                                next,

                            confirm_password:
                                confirm

                        };

                    }

                });

            if (
                !result.isConfirmed
            ) {
                return;
            }

            fetch(
                'ajax/change_password.php',
                {

                    method:'POST',

                    credentials:
                        'same-origin',

                    headers:{
                        'Content-Type':
                            'application/json',

                        Accept:
                            'application/json'
                    },

                    body:
                        JSON.stringify({

                            ...result.value,

                            csrf_token:
                                csrfToken

                        })

                }
            )

                .then(
                    async response => {

                        const data =
                            await response.json();

                        if (
                            !response.ok
                        ) {

                            throw new Error(
                                data.message ||
                                'Password request failed.'
                            );

                        }

                        return data;

                    }
                )

                .then(
                    showDialog
                )

                .catch(
                    error => {

                        showDialog({

                            status:'error',

                            title:'Password Update Failed',

                            message:
                                error.message ||
                                'Unable to update password.'

                        });

                    }
                );

        }
    );

    document
        .querySelectorAll(
            '.shortcut-grid a'
        )
        .forEach(
            link => {

                link.addEventListener(
                    'click',
                    () => {

                        toast(
                            'info',
                            'Opening student portal...'
                        );

                    }
                );

            }
        );

});