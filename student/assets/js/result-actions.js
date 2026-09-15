(function () {
    'use strict';

    document.addEventListener('DOMContentLoaded', function () {

        /* =========================================================
           BUTTON REFERENCES
        ========================================================= */

        const downloadButton =
            document.getElementById('downloadResultPdf');

        const emailButton =
            document.getElementById('emailResult');

        const printButton =
            document.getElementById('printResult');


        /* =========================================================
           BUTTON BUSY STATE
        ========================================================= */

        function setBusy(button, busyText) {

            if (!button) {
                return;
            }

            if (!button.dataset.originalHtml) {
                button.dataset.originalHtml =
                    button.innerHTML;
            }

            button.disabled = true;

            button.setAttribute(
                'aria-busy',
                'true'
            );

            button.innerHTML = busyText;
        }


        /* =========================================================
           CLEAR BUSY STATE
        ========================================================= */

        function clearBusy(button) {

            if (!button) {
                return;
            }

            button.disabled = false;

            button.removeAttribute(
                'aria-busy'
            );

            if (button.dataset.originalHtml) {

                button.innerHTML =
                    button.dataset.originalHtml;
            }
        }


        /* =========================================================
           TOAST MESSAGE
        ========================================================= */

        function notify(message, type) {

            let toast =
                document.getElementById(
                    'resultActionToast'
                );

            if (!toast) {

                toast =
                    document.createElement(
                        'div'
                    );

                toast.id =
                    'resultActionToast';

                toast.setAttribute(
                    'role',
                    'status'
                );

                toast.style.cssText = [
                    'position:fixed',
                    'left:50%',
                    'bottom:28px',
                    'transform:translateX(-50%)',
                    'z-index:99999',
                    'padding:12px 18px',
                    'border-radius:12px',
                    'box-shadow:0 14px 35px rgba(62,39,35,.20)',
                    'font:600 13px Poppins,Arial,sans-serif',
                    'max-width:calc(100vw - 32px)',
                    'text-align:center'
                ].join(';');

                document.body.appendChild(
                    toast
                );
            }


            toast.textContent =
                message;


            if (type === 'error') {

                toast.style.color =
                    '#8A2F2F';

                toast.style.background =
                    '#FBECE8';

                toast.style.border =
                    '1px solid #EDC8C1';

            } else {

                toast.style.color =
                    '#3E5E24';

                toast.style.background =
                    '#EDF4E4';

                toast.style.border =
                    '1px solid #D8E5C8';
            }


            toast.style.opacity =
                '1';


            window.clearTimeout(
                toast._timer
            );


            toast._timer =
                window.setTimeout(
                    function () {

                        toast.style.opacity =
                            '0';

                    },
                    3500
                );
        }


        /* =========================================================
           DOWNLOAD RESULT PDF
        ========================================================= */

        if (downloadButton) {

            downloadButton.addEventListener(
                'click',
                function (event) {

                    event.preventDefault();

                    const href =
                        downloadButton.getAttribute(
                            'href'
                        );


                    if (!href) {

                        notify(
                            'Result PDF link is unavailable.',
                            'error'
                        );

                        return;
                    }


                    setBusy(
                        downloadButton,
                        '<i class="fa-solid fa-spinner fa-spin"></i>&nbsp; Preparing PDF...'
                    );


                    /*
                     * Open PHP PDF endpoint.
                     *
                     * Browser will handle the actual download.
                     */
                    window.location.assign(
                        href
                    );


                    /*
                     * Restore button after a small delay.
                     */
                    window.setTimeout(
                        function () {

                            clearBusy(
                                downloadButton
                            );

                        },
                        2500
                    );
                }
            );
        }


        /* =========================================================
           EMAIL RESULT
        ========================================================= */

        if (emailButton) {

            emailButton.addEventListener(
                'click',
                async function () {

                    if (
                        emailButton.disabled
                    ) {
                        return;
                    }


                    const attemptId =
                        String(
                            emailButton.dataset.attemptId || ''
                        ).trim();


                    const csrfToken =
                        String(
                            emailButton.dataset.csrfToken || ''
                        ).trim();


                    /*
                     * Check Attempt ID
                     */
                    if (!attemptId) {

                        notify(
                            'Result attempt ID is missing.',
                            'error'
                        );

                        return;
                    }


                    /*
                     * Check CSRF token
                     */
                    if (!csrfToken) {

                        notify(
                            'Security token is missing. Please refresh the result page.',
                            'error'
                        );

                        return;
                    }


                    setBusy(
                        emailButton,
                        '<i class="fa-solid fa-spinner fa-spin"></i>&nbsp; Sending...'
                    );


                    try {

                        /*
                         * Create POST body
                         */
                        const body =
                            new URLSearchParams();


                        body.set(
                            'attempt_id',
                            attemptId
                        );


                        body.set(
                            'csrf_token',
                            csrfToken
                        );


                        /*
                         * Send request to PHP endpoint
                         */
                        const response =
                            await fetch(
                                'ajax/send_result_email.php',
                                {
                                    method: 'POST',

                                    credentials:
                                        'same-origin',

                                    headers: {
                                        'Content-Type':
                                            'application/x-www-form-urlencoded; charset=UTF-8',

                                        'Accept':
                                            'application/json',

                                        'X-Requested-With':
                                            'XMLHttpRequest'
                                    },

                                    body:
                                        body.toString()
                                }
                            );


                        /*
                         * Parse JSON
                         */
                        let data = null;


                        try {

                            data =
                                await response.json();

                        } catch (
                            jsonError
                        ) {

                            throw new Error(
                                'The email service returned an invalid response.'
                            );
                        }


                        /*
                         * Check server response
                         */
                        if (
                            !response.ok ||
                            !data ||
                            data.status !== true
                        ) {

                            throw new Error(
                                data &&
                                data.message
                                    ? data.message
                                    : 'Unable to email the result.'
                            );
                        }


                        /*
                         * Success message
                         */
                        notify(
                            data.message ||
                            'Result emailed successfully.',
                            'success'
                        );


                    } catch (
                        error
                    ) {

                        console.error(
                            'ExamSphere result email failed:',
                            error
                        );


                        notify(
                            error.message ||
                            'Unable to email the result.',
                            'error'
                        );


                    } finally {

                        /*
                         * Restore button
                         */
                        clearBusy(
                            emailButton
                        );
                    }
                }
            );
        }


        /* =========================================================
           PRINT RESULT
        ========================================================= */

        if (printButton) {

            printButton.addEventListener(
                'click',
                function () {

                    window.print();

                }
            );
        }

    });

})();