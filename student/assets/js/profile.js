document.addEventListener('DOMContentLoaded', function () {
    const profileForm = document.getElementById('profileForm');
    const profilePhoto = document.getElementById('profilePhoto');
    const profilePreview = document.getElementById('profilePreview');
    const csrfToken = profileForm?.querySelector('input[name="csrf_token"]')?.value
        || document.querySelector('meta[name="csrf-token"]')?.getAttribute('content')
        || '';

    const notify = (icon, title, text, options = {}) => {
        if (typeof Swal === 'undefined') {
            window.alert(`${title}\n${text}`);
            return;
        }

        Swal.fire({
            icon,
            title,
            text,
            confirmButtonColor: '#556B2F',
            ...options
        });
    };

    const scrollToPanel = (id) => {
        const panel = document.getElementById(id);
        if (!panel) return;

        window.scrollTo({
            top: Math.max(0, panel.getBoundingClientRect().top + window.scrollY - 110),
            behavior: 'smooth'
        });
    };

    const activateTab = (id) => {
        const panel = document.getElementById(id);
        if (!panel) return;

        document.querySelectorAll('.tab-btn[data-tab]').forEach((button) => {
            button.classList.toggle('active', button.dataset.tab === id);
        });

        document.querySelectorAll('.profile-tab-panel').forEach((section) => {
            section.classList.toggle('active-panel', section.id === id);
        });
    };

    document.querySelectorAll('.tab-btn[data-tab]').forEach((button) => {
        button.addEventListener('click', () => {
            activateTab(button.dataset.tab);
            scrollToPanel(button.dataset.tab);
        });
    });

    document.querySelectorAll('[data-scroll-to]').forEach((button) => {
        button.addEventListener('click', () => {
            const id = button.dataset.scrollTo;
            activateTab(id);
            scrollToPanel(id);

            const nameInput = document.querySelector('input[name="full_name"]');
            if (id === 'personal-panel' && nameInput) {
                window.setTimeout(() => nameInput.focus(), 350);
            }
        });
    });

    document.querySelectorAll('[data-photo-trigger="true"]').forEach((button) => {
        button.addEventListener('click', () => profilePhoto?.click());
    });

    const parseJsonResponse = async (response) => {
        const text = await response.text();
        let data = null;

        try {
            data = JSON.parse(text);
        } catch (error) {
            throw new Error('The server returned an invalid response.');
        }

        if (!response.ok && !data?.message) {
            throw new Error('The request could not be completed.');
        }

        return data;
    };

    if (profileForm) {
        profileForm.addEventListener('submit', async function (event) {
            event.preventDefault();

            const saveButton = document.getElementById('saveProfileButton');
            const originalHtml = saveButton?.innerHTML || '';

            if (saveButton) {
                saveButton.disabled = true;
                saveButton.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Saving...';
            }

            try {
                const response = await fetch('ajax/update_profile.php', {
                    method: 'POST',
                    body: new FormData(profileForm),
                    credentials: 'same-origin',
                    headers: { Accept: 'application/json' }
                });

                const data = await parseJsonResponse(response);

                notify(
                    data.status === 'success' ? 'success' : 'error',
                    data.title || (data.status === 'success' ? 'Profile Updated' : 'Update Failed'),
                    data.message || 'Unable to update your profile.'
                ).then(() => {
                    if (data.status === 'success') {
                        window.location.reload();
                    }
                });
            } catch (error) {
                notify('error', 'Server Error', error.message || 'Unable to save profile.');
            } finally {
                if (saveButton) {
                    saveButton.disabled = false;
                    saveButton.innerHTML = originalHtml;
                }
            }
        });
    }

    if (profilePhoto) {
        profilePhoto.addEventListener('change', async function () {
            const file = this.files?.[0];
            if (!file) return;

            const allowedTypes = ['image/jpeg', 'image/png'];
            const maxSize = 2 * 1024 * 1024;

            if (!allowedTypes.includes(file.type)) {
                notify('error', 'Invalid Image', 'Please select a JPG, JPEG or PNG image.');
                this.value = '';
                return;
            }

            if (file.size > maxSize) {
                notify('warning', 'Image Too Large', 'Maximum profile photo size is 2 MB.');
                this.value = '';
                return;
            }

            if (profilePreview && window.FileReader) {
                const reader = new FileReader();
                reader.onload = (event) => {
                    if (event.target?.result) profilePreview.src = event.target.result;
                };
                reader.readAsDataURL(file);
            }

            const formData = new FormData();
            formData.append('profile_photo', file);
            formData.append('csrf_token', csrfToken);

            try {
                const response = await fetch('ajax/upload_profile_photo.php', {
                    method: 'POST',
                    body: formData,
                    credentials: 'same-origin',
                    headers: { Accept: 'application/json' }
                });

                const data = await parseJsonResponse(response);

                if (data.status === 'success') {
                    if (data.photo && profilePreview) {
                        profilePreview.src = `${data.photo}?t=${Date.now()}`;
                    }

                    notify('success', data.title || 'Uploaded', data.message || 'Profile photo updated.', {
                        timer: 1800,
                        showConfirmButton: false
                    });
                } else {
                    notify('error', data.title || 'Upload Failed', data.message || 'Unable to upload image.');
                }
            } catch (error) {
                notify('error', 'Server Error', error.message || 'Unable to upload image.');
            } finally {
                this.value = '';
            }
        });
    }

    const openPasswordDialog = () => {
        if (typeof Swal === 'undefined') return;

        Swal.fire({
            title: 'Change Password',
            html: `
                <input id="currentPassword" class="swal2-input" type="password" autocomplete="current-password" placeholder="Current Password">
                <input id="newPassword" class="swal2-input" type="password" autocomplete="new-password" placeholder="New Password">
                <input id="confirmPassword" class="swal2-input" type="password" autocomplete="new-password" placeholder="Confirm Password">
            `,
            confirmButtonText: 'Update Password',
            confirmButtonColor: '#556B2F',
            showCancelButton: true,
            cancelButtonText: 'Cancel',
            focusConfirm: false,
            preConfirm: () => {
                const current = document.getElementById('currentPassword')?.value || '';
                const password = document.getElementById('newPassword')?.value || '';
                const confirm = document.getElementById('confirmPassword')?.value || '';

                if (!current || !password || !confirm) {
                    Swal.showValidationMessage('All password fields are required.');
                    return false;
                }

                if (password.length < 8 || password.length > 72) {
                    Swal.showValidationMessage('New password must be between 8 and 72 characters.');
                    return false;
                }

                if (!/[A-Za-z]/.test(password) || !/[0-9]/.test(password)) {
                    Swal.showValidationMessage('New password must contain at least one letter and one number.');
                    return false;
                }

                if (password !== confirm) {
                    Swal.showValidationMessage('New password and confirmation do not match.');
                    return false;
                }

                return {
                    current_password: current,
                    new_password: password,
                    confirm_password: confirm,
                    csrf_token: csrfToken
                };
            }
        }).then(async (result) => {
            if (!result.isConfirmed) return;

            try {
                const response = await fetch('ajax/change_password.php', {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {
                        'Content-Type': 'application/json',
                        Accept: 'application/json'
                    },
                    body: JSON.stringify(result.value)
                });

                const data = await parseJsonResponse(response);

                notify(
                    data.status === 'success' ? 'success' : 'error',
                    data.title || 'Password Update',
                    data.message || 'Password update completed.'
                );
            } catch (error) {
                notify('error', 'Server Error', error.message || 'Unable to update password.');
            }
        });
    };

    document.querySelectorAll('.change-password-trigger').forEach((button) => {
        button.addEventListener('click', openPasswordDialog);
    });
});
