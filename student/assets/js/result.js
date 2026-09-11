'use strict';

document.addEventListener(
    'DOMContentLoaded',
    function () {

        /*
        |--------------------------------------------------------------------------
        | ELEMENTS
        |--------------------------------------------------------------------------
        */

        const emailButton =
            document.getElementById(
                'emailResult'
            );


        const printButton =
            document.querySelector(
                '.btn-print'
            );


        /*
        |--------------------------------------------------------------------------
        | EMAIL RESULT
        |--------------------------------------------------------------------------
        */

        if (
            emailButton
        ) {

            emailButton.addEventListener(
                'click',
                async function () {

                    if (
                        emailButton.dataset.sending === '1'
                    ) {
                        return;
                    }


                    const attemptId =
                        Number(
                            emailButton.dataset.attemptId || 0
                        );


                    const csrfToken =
                        String(
                            emailButton.dataset.csrfToken || ''
                        ).trim();


                    /*
                    |--------------------------------------------------------------------------
                    | VALIDATION
                    |--------------------------------------------------------------------------
                    */

                    if (
                        !Number.isInteger(
                            attemptId
                        ) ||
                        attemptId <= 0
                    ) {

                        showResultMessage(
                            'Unable to identify this result.',
                            'error'
                        );

                        return;
                    }


                    if (
                        csrfToken === ''
                    ) {

                        showResultMessage(
                            'Security token is missing. Please refresh the page.',
                            'error'
                        );

                        return;
                    }


                    /*
                    |--------------------------------------------------------------------------
                    | SAVE ORIGINAL STATE
                    |--------------------------------------------------------------------------
                    */

                    const originalHTML =
                        emailButton.innerHTML;


                    const originalWidth =
                        emailButton.offsetWidth;


                    emailButton.dataset.sending =
                        '1';


                    emailButton.disabled =
                        true;


                    if (
                        originalWidth > 0
                    ) {

                        emailButton.style.minWidth =
                            `${originalWidth}px`;
                    }


                    emailButton.innerHTML = `

                        <i
                            class="
                                fa-solid
                                fa-spinner
                                fa-spin
                            "
                        ></i>

                        Sending...

                    `;


                    /*
                    |--------------------------------------------------------------------------
                    | REQUEST
                    |--------------------------------------------------------------------------
                    */

                    try {

                        const body =
                            new URLSearchParams();


                        body.append(
                            'attempt_id',
                            String(attemptId)
                        );


                        body.append(
                            'csrf_token',
                            csrfToken
                        );


                        const controller =
                            new AbortController();


                        const timeoutId =
                            window.setTimeout(
                                function () {

                                    controller.abort();

                                },
                                30000
                            );


                        let response;


                        try {

                            response =
                                await fetch(
                                    'ajax/send_result_email.php',
                                    {
                                        method:
                                            'POST',

                                        headers:
                                            {
                                                'Content-Type':
                                                    'application/x-www-form-urlencoded; charset=UTF-8',

                                                'X-Requested-With':
                                                    'XMLHttpRequest',

                                                'Accept':
                                                    'application/json'
                                            },

                                        credentials:
                                            'same-origin',

                                        cache:
                                            'no-store',

                                        signal:
                                            controller.signal,

                                        body:
                                            body.toString()
                                    }
                                );

                        } finally {

                            window.clearTimeout(
                                timeoutId
                            );
                        }


                        /*
                        |--------------------------------------------------------------------------
                        | CONTENT TYPE
                        |--------------------------------------------------------------------------
                        */

                        const contentType =
                            response.headers.get(
                                'content-type'
                            ) || '';


                        let result =
                            null;


                        if (
                            contentType
                                .toLowerCase()
                                .includes(
                                    'application/json'
                                )
                        ) {

                            try {

                                result =
                                    await response.json();

                            } catch (
                                parseError
                            ) {

                                throw new Error(
                                    'The server returned an invalid response.'
                                );
                            }

                        } else {

                            /*
                            |--------------------------------------------------------------------------
                            | Do not expose raw PHP/HTML errors.
                            |--------------------------------------------------------------------------
                            */

                            throw new Error(
                                response.ok
                                    ? 'The server returned an unexpected response.'
                                    : 'Unable to send the result email right now.'
                            );
                        }


                        /*
                        |--------------------------------------------------------------------------
                        | SERVER ERROR
                        |--------------------------------------------------------------------------
                        */

                        if (
                            !response.ok ||
                            !result ||
                            result.status !== true
                        ) {

                            throw new Error(
                                result &&
                                typeof result.message === 'string' &&
                                result.message.trim() !== ''

                                    ? result.message

                                    : 'Unable to send the result email.'
                            );
                        }


                        /*
                        |--------------------------------------------------------------------------
                        | SUCCESS
                        |--------------------------------------------------------------------------
                        */

                        emailButton.innerHTML = `

                            <i
                                class="
                                    fa-solid
                                    fa-circle-check
                                "
                            ></i>

                            Email sent

                        `;


                        showResultMessage(
                            result.message ||
                            'Your result PDF was sent successfully.',
                            'success'
                        );


                        /*
                        |--------------------------------------------------------------------------
                        | RESTORE AFTER DELAY
                        |--------------------------------------------------------------------------
                        */

                        window.setTimeout(
                            function () {

                                emailButton.disabled =
                                    false;


                                emailButton.dataset.sending =
                                    '0';


                                emailButton.innerHTML =
                                    originalHTML;


                                emailButton.style.minWidth =
                                    '';

                            },
                            3500
                        );


                    } catch (
                        error
                    ) {

                        /*
                        |--------------------------------------------------------------------------
                        | ERROR
                        |--------------------------------------------------------------------------
                        */

                        emailButton.disabled =
                            false;


                        emailButton.dataset.sending =
                            '0';


                        emailButton.innerHTML =
                            originalHTML;


                        emailButton.style.minWidth =
                            '';


                        if (
                            error &&
                            error.name === 'AbortError'
                        ) {

                            showResultMessage(
                                'The email request timed out. Please try again.',
                                'error'
                            );

                        } else {

                            showResultMessage(
                                error &&
                                typeof error.message === 'string'
                                    ? error.message
                                    : 'Unable to send the result email.',
                                'error'
                            );
                        }

                    }

                }
            );
        }


        /*
        |--------------------------------------------------------------------------
        | PRINT
        |--------------------------------------------------------------------------
        */

        if (
            printButton
        ) {

            printButton.addEventListener(
                'click',
                function () {

                    window.print();

                }
            );
        }


        /*
        |--------------------------------------------------------------------------
        | RESULT TOAST
        |--------------------------------------------------------------------------
        */

        function showResultMessage(
            message,
            type
        ) {

            let toast =
                document.getElementById(
                    'resultPageToast'
                );


            /*
            |--------------------------------------------------------------------------
            | Create toast once
            |--------------------------------------------------------------------------
            */

            if (
                !toast
            ) {

                toast =
                    document.createElement(
                        'div'
                    );


                toast.id =
                    'resultPageToast';


                toast.setAttribute(
                    'role',
                    'status'
                );


                toast.setAttribute(
                    'aria-live',
                    'polite'
                );


                toast.style.position =
                    'fixed';


                toast.style.left =
                    '50%';


                toast.style.bottom =
                    '24px';


                toast.style.zIndex =
                    '99999';


                toast.style.maxWidth =
                    'calc(100% - 30px)';


                toast.style.padding =
                    '13px 18px';


                toast.style.borderRadius =
                    '14px';


                toast.style.fontFamily =
                    'Poppins, Arial, sans-serif';


                toast.style.fontSize =
                    '12px';


                toast.style.fontWeight =
                    '600';


                toast.style.lineHeight =
                    '1.5';


                toast.style.boxShadow =
                    '0 18px 45px rgba(0,0,0,.18)';


                toast.style.transform =
                    'translate(-50%, 20px)';


                toast.style.opacity =
                    '0';


                toast.style.transition =
                    'opacity .25s ease, transform .25s ease';


                toast.style.pointerEvents =
                    'none';


                document.body.appendChild(
                    toast
                );
            }


            /*
            |--------------------------------------------------------------------------
            | Message
            |--------------------------------------------------------------------------
            */

            toast.textContent =
                String(
                    message || ''
                );


            /*
            |--------------------------------------------------------------------------
            | Type
            |--------------------------------------------------------------------------
            */

            if (
                type === 'success'
            ) {

                toast.style.background =
                    '#465925';

                toast.style.color =
                    '#FFFFFF';

            } else {

                toast.style.background =
                    '#5D4037';

                toast.style.color =
                    '#FFFFFF';
            }


            /*
            |--------------------------------------------------------------------------
            | Show
            |--------------------------------------------------------------------------
            */

            window.clearTimeout(
                toast._hideTimer
            );


            requestAnimationFrame(
                function () {

                    toast.style.opacity =
                        '1';

                    toast.style.transform =
                        'translate(-50%, 0)';

                }
            );


            /*
            |--------------------------------------------------------------------------
            | Hide
            |--------------------------------------------------------------------------
            */

            toast._hideTimer =
                window.setTimeout(
                    function () {

                        toast.style.opacity =
                            '0';

                        toast.style.transform =
                            'translate(-50%, 20px)';

                    },
                    4000
                );
        }


        /*
        |--------------------------------------------------------------------------
        | DOWNLOAD FEEDBACK
        |--------------------------------------------------------------------------
        |
        | The PDF download is a normal browser link, so we do not
        | intercept it. This preserves browser download behavior.
        |--------------------------------------------------------------------------
        */

        const downloadLinks =
            document.querySelectorAll(
                'a[href*="download_result_pdf.php"]'
            );


        downloadLinks.forEach(
            function (link) {

                link.addEventListener(
                    'click',
                    function () {

                        const originalHTML =
                            link.innerHTML;


                        link.dataset.downloading =
                            '1';


                        link.style.pointerEvents =
                            'none';


                        link.innerHTML = `

                            <i
                                class="
                                    fa-solid
                                    fa-spinner
                                    fa-spin
                                "
                            ></i>

                            Preparing...

                        `;


                        window.setTimeout(
                            function () {

                                link.dataset.downloading =
                                    '0';


                                link.style.pointerEvents =
                                    '';


                                link.innerHTML =
                                    originalHTML;

                            },
                            2500
                        );
                    }
                );

            }
        );


        /*
        |--------------------------------------------------------------------------
        | PRINT KEYBOARD SHORTCUT
        |--------------------------------------------------------------------------
        */

        document.addEventListener(
            'keydown',
            function (event) {

                if (
                    (
                        event.ctrlKey ||
                        event.metaKey
                    ) &&
                    event.key.toLowerCase() === 'p'
                ) {

                    /*
                     * Let the browser's normal print shortcut work.
                     * We do not prevent the default behavior.
                     */

                    return;
                }

            }
        );

    }
);