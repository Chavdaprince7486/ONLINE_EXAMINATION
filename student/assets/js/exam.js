'use strict';

document.addEventListener(
    'DOMContentLoaded',
    function () {

        const config =
            window.EXAMSPHERE_EXAM;


        if (
            !config ||
            !Array.isArray(
                config.questions
            ) ||
            config.questions.length === 0
        ) {

            return;
        }


        const questions =
            config.questions;


        let currentIndex = 0;

        let submitting = false;

        let autoSubmitting = false;

        let transitionLock = false;

        let saveTimeout = null;

        let timerInterval = null;


        const pending =
            new Set();


        const questionContainer =
            document.getElementById(
                'questionContainer'
            );


        const questionPalette =
            document.getElementById(
                'questionPalette'
            );


        const paperPalette =
            document.getElementById(
                'paperPalette'
            );


        const examTimer =
            document.getElementById(
                'examTimer'
            );


        const previousButton =
            document.getElementById(
                'previousButton'
            );


        const nextButton =
            document.getElementById(
                'nextButton'
            );


        const markNextButton =
            document.getElementById(
                'markNextButton'
            );


        const clearButton =
            document.getElementById(
                'clearButton'
            );


        const submitButton =
            document.getElementById(
                'submitButton'
            );


        const confirmSubmit =
            document.getElementById(
                'confirmSubmit'
            );


        const toast =
            document.getElementById(
                'examToast'
            );


        const statusAnswered =
            document.getElementById(
                'statusAnswered'
            );


        const statusNotAnswered =
            document.getElementById(
                'statusNotAnswered'
            );


        const statusNotVisited =
            document.getElementById(
                'statusNotVisited'
            );


        const statusReviewed =
            document.getElementById(
                'statusReviewed'
            );


        const statusAnswerReview =
            document.getElementById(
                'statusAnswerReview'
            );


        const submitMessage =
            document.getElementById(
                'submitMessage'
            );


        const submitAnswered =
            document.getElementById(
                'submitAnswered'
            );


        const submitUnanswered =
            document.getElementById(
                'submitUnanswered'
            );


        const submitReview =
            document.getElementById(
                'submitReview'
            );


        const submitForm =
            document.getElementById(
                'submitForm'
            );


        const autoSubmitInput =
            document.getElementById(
                'autoSubmit'
            );


        let instructionsModal = null;

        let shortcutsModal = null;

        let paperModal = null;

        let submitModal = null;


        if (
            typeof bootstrap !==
            'undefined'
        ) {

            const makeModal =
                function (
                    id
                ) {

                    const element =
                        document.getElementById(
                            id
                        );


                    return element
                        ? bootstrap.Modal
                            .getOrCreateInstance(
                                element
                            )
                        : null;
                };


            instructionsModal =
                makeModal(
                    'instructionsModal'
                );


            shortcutsModal =
                makeModal(
                    'shortcutsModal'
                );


            paperModal =
                makeModal(
                    'paperModal'
                );


            submitModal =
                makeModal(
                    'submitModal'
                );

        }


        /*
        |--------------------------------------------------------------------------
        | NORMALIZE
        |--------------------------------------------------------------------------
        */

        function normalizeAnswer(
            value
        ) {

            const answer =
                String(
                    value || ''
                )
                    .trim()
                    .toUpperCase();


            return [
                'A',
                'B',
                'C',
                'D'
            ].includes(
                answer
            )
                ? answer
                : '';
        }


        function answered(
            question
        ) {

            return !!(
                question &&
                normalizeAnswer(
                    question.answer
                ) !== ''
            );
        }


        function reviewed(
            question
        ) {

            return !!(
                question &&
                (
                    question.status ===
                    'Marked for Review'

                    ||

                    question.status ===
                    'Answered & Marked for Review'
                )
            );
        }


        function statusOf(
            question
        ) {

            if (
                !question
            ) {

                return 'Not Visited';
            }


            const hasAnswer =
                answered(
                    question
                );


            const isReview =
                reviewed(
                    question
                );


            if (
                hasAnswer &&
                isReview
            ) {

                return 'Answered & Marked for Review';
            }


            if (
                hasAnswer
            ) {

                return 'Answered';
            }


            if (
                isReview
            ) {

                return 'Marked for Review';
            }


            if (
                Number(
                    question.visited || 0
                ) === 1
            ) {

                return 'Not Answered';
            }


            return 'Not Visited';
        }


        /*
        |--------------------------------------------------------------------------
        | HTML ESCAPE
        |--------------------------------------------------------------------------
        */

        function escapeHtml(
            value
        ) {

            const div =
                document.createElement(
                    'div'
                );


            div.textContent =
                String(
                    value ?? ''
                );


            return div.innerHTML;
        }


        /*
        |--------------------------------------------------------------------------
        | OPTIONS
        |--------------------------------------------------------------------------
        */

        function normalizeOptions(
            options
        ) {

            const output = [];


            if (
                !Array.isArray(
                    options
                )
            ) {

                return output;
            }


            options.forEach(
                function (
                    option,
                    index
                ) {

                    let label =
                        String.fromCharCode(
                            65 + index
                        );


                    let text =
                        '';


                    if (
                        option &&
                        typeof option ===
                        'object'
                    ) {

                        label =
                            String(
                                option.label ||
                                label
                            )
                                .trim()
                                .toUpperCase();


                        text =
                            String(
                                option.text ||
                                option.content ||
                                option.value ||
                                ''
                            );

                    } else {

                        text =
                            String(
                                option ||
                                ''
                            );

                    }


                    if (
                        [
                            'A',
                            'B',
                            'C',
                            'D'
                        ].includes(
                            label
                        ) &&
                        text.trim() !== ''
                    ) {

                        output.push({

                            label,

                            text

                        });
                    }

                }
            );


            return output;
        }


        /*
        |--------------------------------------------------------------------------
        | TOAST
        |--------------------------------------------------------------------------
        */

        let toastTimeout = null;


        function showToast(
            message,
            type = ''
        ) {

            if (
                !toast
            ) {

                return;
            }


            clearTimeout(
                toastTimeout
            );


            toast.className =
                'exm-toast';


            if (
                type
            ) {

                toast.classList.add(
                    type
                );
            }


            toast.textContent =
                String(
                    message || ''
                );


            requestAnimationFrame(
                function () {

                    toast.classList.add(
                        'show'
                    );

                }
            );


            toastTimeout =
                setTimeout(
                    function () {

                        toast.classList.remove(
                            'show'
                        );

                    },
                    2400
                );

        }


        /*
        |--------------------------------------------------------------------------
        | COUNTS
        |--------------------------------------------------------------------------
        */

        function getCounts() {

            const counts = {

                answered: 0,

                notAnswered: 0,

                notVisited: 0,

                reviewed: 0,

                answerReview: 0

            };


            questions.forEach(
                function (
                    question
                ) {

                    question.status =
                        statusOf(
                            question
                        );


                    if (
                        question.status ===
                        'Answered'
                    ) {

                        counts.answered++;

                    } else if (
                        question.status ===
                        'Not Answered'
                    ) {

                        counts.notAnswered++;

                    } else if (
                        question.status ===
                        'Not Visited'
                    ) {

                        counts.notVisited++;

                    } else if (
                        question.status ===
                        'Marked for Review'
                    ) {

                        counts.reviewed++;

                    } else if (
                        question.status ===
                        'Answered & Marked for Review'
                    ) {

                        counts.answerReview++;

                    }

                }
            );


            return counts;
        }


        function updateCounts() {

            const counts =
                getCounts();


            if (
                statusAnswered
            ) {

                statusAnswered.textContent =
                    String(
                        counts.answered +
                        counts.answerReview
                    );
            }


            if (
                statusNotAnswered
            ) {

                statusNotAnswered.textContent =
                    String(
                        counts.notAnswered
                    );
            }


            if (
                statusNotVisited
            ) {

                statusNotVisited.textContent =
                    String(
                        counts.notVisited
                    );
            }


            if (
                statusReviewed
            ) {

                statusReviewed.textContent =
                    String(
                        counts.reviewed
                    );
            }


            if (
                statusAnswerReview
            ) {

                statusAnswerReview.textContent =
                    String(
                        counts.answerReview
                    );
            }


            if (
                submitAnswered
            ) {

                submitAnswered.textContent =
                    String(
                        counts.answered +
                        counts.answerReview
                    );
            }


            if (
                submitUnanswered
            ) {

                submitUnanswered.textContent =
                    String(
                        counts.notAnswered +
                        counts.notVisited
                    );
            }


            if (
                submitReview
            ) {

                submitReview.textContent =
                    String(
                        counts.reviewed +
                        counts.answerReview
                    );
            }


            updatePalette();
        }


        /*
        |--------------------------------------------------------------------------
        | PALETTE
        |--------------------------------------------------------------------------
        */

        function paletteClass(
            status
        ) {

            switch (
                status
            ) {

                case 'Answered':

                    return 'answered';


                case 'Not Answered':

                    return 'notanswered';


                case 'Marked for Review':

                    return 'review';


                case 'Answered & Marked for Review':

                    return 'answerreview';


                default:

                    return 'notvisited';

            }

        }


        function buildPalette() {

            if (
                !questionPalette
            ) {

                return;
            }


            questionPalette.innerHTML =
                '';


            questions.forEach(
                function (
                    question,
                    index
                ) {

                    const button =
                        document.createElement(
                            'button'
                        );


                    button.type =
                        'button';


                    button.className =
                        'exm-palette-button';


                    button.dataset.index =
                        String(
                            index
                        );


                    button.textContent =
                        String(
                            index + 1
                        );


                    button.addEventListener(
                        'click',
                        function () {

                            navigateTo(
                                index
                            );

                        }
                    );


                    questionPalette.appendChild(
                        button
                    );

                }
            );


            updatePalette();
        }


        function updatePalette() {

            if (
                !questionPalette
            ) {

                return;
            }


            questionPalette
                .querySelectorAll(
                    '.exm-palette-button'
                )
                .forEach(
                    function (
                        button,
                        index
                    ) {

                        const question =
                            questions[
                                index
                            ];


                        if (
                            !question
                        ) {

                            return;
                        }


                        const classes = [

                            'current',

                            'notvisited',

                            'notanswered',

                            'answered',

                            'review',

                            'answerreview'

                        ];


                        button.classList.remove(
                            ...classes
                        );


                        button.classList.add(
                            paletteClass(
                                statusOf(
                                    question
                                )
                            )
                        );


                        if (
                            index ===
                            currentIndex
                        ) {

                            button.classList.add(
                                'current'
                            );
                        }

                    }
                );

        }


        /*
        |--------------------------------------------------------------------------
        | PAPER
        |--------------------------------------------------------------------------
        */

        function buildPaperPalette() {

            if (
                !paperPalette
            ) {

                return;
            }


            paperPalette.innerHTML =
                '';


            questions.forEach(
                function (
                    question,
                    index
                ) {

                    const button =
                        document.createElement(
                            'button'
                        );


                    button.type =
                        'button';


                    button.textContent =
                        String(
                            index + 1
                        );


                    button.style.border =
                        '1px solid #E3DCCE';


                    button.style.borderRadius =
                        '8px';


                    button.style.minHeight =
                        '42px';


                    button.style.background =
                        '#FFFDF9';


                    button.style.color =
                        '#3E2723';


                    button.style.fontWeight =
                        '800';


                    button.addEventListener(
                        'click',
                        function () {

                            paperModal?.hide();

                            navigateTo(
                                index
                            );

                        }
                    );


                    paperPalette.appendChild(
                        button
                    );

                }
            );

        }


        /*
        |--------------------------------------------------------------------------
        | RENDER
        |--------------------------------------------------------------------------
        */

        function renderQuestion() {

            const question =
                questions[
                    currentIndex
                ];


            if (
                !question ||
                !questionContainer
            ) {

                return;
            }


            question.visited =
                1;


            if (
                question.status ===
                'Not Visited'
            ) {

                question.status =
                    answered(
                        question
                    )
                        ? 'Answered'
                        : 'Not Answered';
            }


            question.status =
                statusOf(
                    question
                );


            const options =
                normalizeOptions(
                    question.options
                );


            let html =
                '';


            html +=
                '<div class="exm-question-heading">';


            html +=
                '<strong>Question No. ' +
                (
                    currentIndex + 1
                ) +
                '</strong>';


            html +=
                '<small>' +
                escapeHtml(
                    question.type ||
                    'MCQ'
                ) +
                '</small>';


            html +=
                '</div>';


            html +=
                '<div class="exm-question-top-line">';


            html +=
                '<div class="exm-question-number">' +
                escapeHtml(
                    'Question ' +
                    (
                        currentIndex + 1
                    )
                ) +
                '</div>';


            html +=
                '<div class="exm-question-status">' +

                '<i class="' +

                (
                    answered(
                        question
                    )
                        ? 'fa-solid fa-circle-check'
                        : reviewed(
                            question
                        )
                            ? 'fa-solid fa-bookmark'
                            : 'fa-regular fa-circle'
                ) +

                '"></i>' +

                escapeHtml(
                    statusOf(
                        question
                    )
                ) +

                '</div>';


            html +=
                '</div>';


            html +=
                '<h1 class="exm-question-text">' +

                escapeHtml(
                    question.text ||
                    ''
                ) +

                '</h1>';


            if (
                String(
                    question.image ||
                    ''
                ).trim() !== ''
            ) {

                html +=
                    '<img class="exm-question-image" src="' +

                    escapeHtml(
                        question.image
                    ) +

                    '" alt="Question image">';

            }


            html +=
                '<div class="exm-options">';


            options.forEach(
                function (
                    option
                ) {

                    const selected =
                        normalizeAnswer(
                            question.answer
                        ) ===
                        option.label;


                    html +=
                        '<label class="exm-option ' +

                        (
                            selected
                                ? 'exm-option-selected'
                                : ''
                        ) +

                        '">';


                    html +=
                        '<input ' +
                        'type="radio" ' +
                        'class="exm-option-input" ' +
                        'name="question-' +
                        escapeHtml(
                            question.id
                        ) +
                        '" ' +
                        'value="' +
                        escapeHtml(
                            option.label
                        ) +
                        '" ' +
                        (
                            selected
                                ? 'checked'
                                : ''
                        ) +
                        '>';


                    html +=
                        '<span class="exm-option-radio"></span>';


                    html +=
                        '<span class="exm-option-letter">' +
                        escapeHtml(
                            option.label
                        ) +
                        '</span>';


                    html +=
                        '<span class="exm-option-text">' +
                        escapeHtml(
                            option.text
                        ) +
                        '</span>';


                    html +=
                        '</label>';

                }
            );


            html +=
                '</div>';


            html +=
                '<div class="exm-state-line">' +

                '<i class="fa-solid fa-circle-info"></i>' +

                escapeHtml(
                    (
                        Number(
                            question.marks ||
                            0
                        )
                    ).toString()
                ) +

                ' marks for this question' +

                '</div>';


            questionContainer.innerHTML =
                html;


            attachOptionEvents();

            updateButtons();

            updateCounts();
        }


        /*
        |--------------------------------------------------------------------------
        | OPTION EVENTS
        |--------------------------------------------------------------------------
        */

        function attachOptionEvents() {

            questionContainer
                .querySelectorAll(
                    '.exm-option-input'
                )
                .forEach(
                    function (
                        input
                    ) {

                        input.addEventListener(
                            'change',
                            function () {

                                const question =
                                    questions[
                                        currentIndex
                                    ];


                                if (
                                    !question ||
                                    submitting
                                ) {

                                    return;
                                }


                                question.answer =
                                    normalizeAnswer(
                                        input.value
                                    );


                                question.visited =
                                    1;


                                question.status =
                                    reviewed(
                                        question
                                    )
                                        ? 'Answered & Marked for Review'
                                        : 'Answered';


                                refreshSelection();

                                updateCounts();

                                updateButtons();

                                scheduleSave();

                            }
                        );

                    }
                );


            questionContainer
                .querySelectorAll(
                    '.exm-option'
                )
                .forEach(
                    function (
                        option
                    ) {

                        option.addEventListener(
                            'click',
                            function (
                                event
                            ) {

                                if (
                                    event.target.closest(
                                        'input'
                                    )
                                ) {

                                    return;
                                }


                                const radio =
                                    option.querySelector(
                                        'input'
                                    );


                                if (
                                    radio
                                ) {

                                    radio.checked =
                                        true;


                                    radio.dispatchEvent(
                                        new Event(
                                            'change',
                                            {
                                                bubbles:
                                                    true
                                            }
                                        )
                                    );

                                }

                            }
                        );

                    }
                );

        }


        function refreshSelection() {

            questionContainer
                .querySelectorAll(
                    '.exm-option'
                )
                .forEach(
                    function (
                        option
                    ) {

                        const input =
                            option.querySelector(
                                'input'
                            );


                        option.classList.toggle(
                            'exm-option-selected',
                            !!(
                                input &&
                                input.checked
                            )
                        );

                    }
                );

        }


        /*
        |--------------------------------------------------------------------------
        | BUTTONS
        |--------------------------------------------------------------------------
        */

        function updateButtons() {

            const question =
                questions[
                    currentIndex
                ];


            if (
                previousButton
            ) {

                previousButton.disabled =
                    currentIndex ===
                    0 ||
                    transitionLock ||
                    submitting;

            }


            if (
                nextButton
            ) {

                nextButton.disabled =
                    transitionLock ||
                    submitting;
            }


            if (
                markNextButton
            ) {

                markNextButton.disabled =
                    transitionLock ||
                    submitting;


                if (
                    reviewed(
                        question
                    )
                ) {

                    markNextButton.classList.add(
                        'active'
                    );


                    markNextButton.innerHTML =
                        '<i class="fa-solid fa-bookmark"></i> Unmark & Next';

                } else {

                    markNextButton.classList.remove(
                        'active'
                    );


                    markNextButton.innerHTML =
                        '<i class="fa-regular fa-bookmark"></i> Mark for Review & Next';

                }

            }


            if (
                clearButton
            ) {

                clearButton.disabled =
                    transitionLock ||
                    submitting ||
                    !answered(
                        question
                    );

            }


            if (
                submitButton
            ) {

                submitButton.disabled =
                    submitting;
            }

        }


        /*
        |--------------------------------------------------------------------------
        | REQUEST BODY
        |--------------------------------------------------------------------------
        */

        function bodyFor(
            question,
            extras = {}
        ) {

            const body =
                new URLSearchParams();


            body.append(
                'attempt_id',
                String(
                    config.attemptId
                )
            );


            if (
                question
            ) {

                body.append(
                    'question_id',
                    String(
                        question.id
                    )
                );


                body.append(
                    'selected_answer',
                    normalizeAnswer(
                        question.answer
                    )
                );


                body.append(
                    'question_status',
                    statusOf(
                        question
                    )
                );

            }


            body.append(
                'csrf_token',
                String(
                    config.csrfToken ||
                    ''
                )
            );


            Object.entries(
                extras
            ).forEach(
                function (
                    [
                        key,
                        value
                    ]
                ) {

                    body.append(
                        key,
                        String(
                            value
                        )
                    );

                }
            );


            return body;
        }


        /*
        |--------------------------------------------------------------------------
        | REQUEST
        |--------------------------------------------------------------------------
        */

        async function request(
            url,
            body
        ) {

            const response =
                await fetch(
                    url,
                    {
                        method:
                            'POST',

                        credentials:
                            'same-origin',

                        cache:
                            'no-store',

                        headers:
                            {
                                'Content-Type':
                                    'application/x-www-form-urlencoded; charset=UTF-8',

                                'X-Requested-With':
                                    'XMLHttpRequest'
                            },

                        body:
                            body.toString()
                    }
                );


            const text =
                await response.text();


            let data;


            try {

                data =
                    text
                        ? JSON.parse(
                            text
                        )
                        : {};

            } catch (
                error
            ) {

                throw new Error(
                    'Invalid server response.'
                );
            }


            if (
                !response.ok ||
                data.status !== true
            ) {

                const error =
                    new Error(
                        data.message ||
                        'Server request failed.'
                    );


                error.data =
                    data;


                throw error;
            }


            return data;

        }


        /*
        |--------------------------------------------------------------------------
        | SAVE
        |--------------------------------------------------------------------------
        */

        async function saveQuestion(
            question,
            notify = false
        ) {

            if (
                !question ||
                submitting ||
                !answered(
                    question
                )
            ) {

                return true;
            }


            pending.add(
                Number(
                    question.id
                )
            );


            try {

                const data =
                    await request(
                        'ajax/save_answer.php',
                        bodyFor(
                            question
                        )
                    );


                if (
                    data.selected_answer
                ) {

                    question.answer =
                        normalizeAnswer(
                            data.selected_answer
                        );
                }


                if (
                    data.question_status
                ) {

                    question.status =
                        data.question_status;
                }


                pending.delete(
                    Number(
                        question.id
                    )
                );


                updateCounts();


                if (
                    notify
                ) {

                    showToast(
                        'Answer saved successfully.',
                        'success'
                    );
                }


                return true;

            } catch (
                error
            ) {

                if (
                    error.data &&
                    error.data.expired
                ) {

                    await autoSubmit();

                    return false;
                }


                if (
                    notify
                ) {

                    showToast(
                        error.message ||
                        'Unable to save answer.',
                        'error'
                    );

                }


                return false;

            }

        }


        function scheduleSave() {

            clearTimeout(
                saveTimeout
            );


            const question =
                questions[
                    currentIndex
                ];


            saveTimeout =
                setTimeout(
                    function () {

                        saveQuestion(
                            question,
                            false
                        );

                    },
                    450
                );

        }


        /*
        |--------------------------------------------------------------------------
        | MARK VISITED
        |--------------------------------------------------------------------------
        */

        async function markVisited(
            question
        ) {

            if (
                !question ||
                submitting
            ) {

                return;
            }


            question.visited =
                1;


            if (
                question.status ===
                'Not Visited'
            ) {

                question.status =
                    'Not Answered';
            }


            try {

                const data =
                    await request(
                        'ajax/update_status.php',
                        bodyFor(
                            question,
                            {
                                action:
                                    'OPEN'
                            }
                        )
                    );


                if (
                    data.question_status
                ) {

                    question.status =
                        data.question_status;
                }


                updateCounts();

            } catch (
                error
            ) {

                updateCounts();
            }

        }


        /*
        |--------------------------------------------------------------------------
        | NAVIGATION
        |--------------------------------------------------------------------------
        */

        async function navigateTo(
            index
        ) {

            if (
                index < 0 ||
                index >= questions.length ||
                transitionLock ||
                submitting
            ) {

                return;
            }


            if (
                index ===
                currentIndex
            ) {

                return;
            }


            transitionLock =
                true;


            updateButtons();


            try {

                const current =
                    questions[
                        currentIndex
                    ];


                if (
                    current &&
                    answered(
                        current
                    )
                ) {

                    const saved =
                        await saveQuestion(
                            current,
                            false
                        );


                    if (
                        !saved
                    ) {

                        return;
                    }

                }


                currentIndex =
                    index;


                renderQuestion();


                await markVisited(
                    questions[
                        currentIndex
                    ]
                );


                window.scrollTo(
                    {
                        top:
                            0,

                        behavior:
                            'smooth'
                    }
                );

            } finally {

                transitionLock =
                    false;

                updateButtons();

                updateCounts();

            }

        }


        async function nextQuestion() {

            const current =
                questions[
                    currentIndex
                ];


            if (
                current &&
                answered(
                    current
                )
            ) {

                const saved =
                    await saveQuestion(
                        current,
                        true
                    );


                if (
                    !saved
                ) {

                    return;
                }

            }


            if (
                currentIndex <
                questions.length - 1
            ) {

                await navigateTo(
                    currentIndex + 1
                );

            } else {

                showToast(
                    'You are on the last question.',
                    'success'
                );

            }

        }


        async function previousQuestion() {

            if (
                currentIndex > 0
            ) {

                await navigateTo(
                    currentIndex - 1
                );

            }

        }


        /*
        |--------------------------------------------------------------------------
        | MARK & NEXT
        |--------------------------------------------------------------------------
        */

        async function markAndNext() {

            const question =
                questions[
                    currentIndex
                ];


            if (
                !question ||
                submitting
            ) {

                return;
            }


            const mark =
                !reviewed(
                    question
                );


            try {

                const data =
                    await request(
                        'ajax/update_status.php',
                        bodyFor(
                            question,
                            {
                                action:
                                    mark
                                        ? 'MARK'
                                        : 'UNMARK'
                            }
                        )
                    );


                question.visited =
                    1;


                question.status =
                    data.question_status ||
                    (
                        mark
                            ? (
                                answered(
                                    question
                                )
                                    ? 'Answered & Marked for Review'
                                    : 'Marked for Review'
                            )
                            : (
                                answered(
                                    question
                                )
                                    ? 'Answered'
                                    : 'Not Answered'
                            )
                    );


                updateCounts();


                if (
                    currentIndex <
                    questions.length - 1
                ) {

                    await navigateTo(
                        currentIndex + 1
                    );

                } else {

                    renderQuestion();

                }

            } catch (
                error
            ) {

                showToast(
                    error.message ||
                    'Unable to update review status.',
                    'error'
                );

            }

        }


        /*
        |--------------------------------------------------------------------------
        | CLEAR
        |--------------------------------------------------------------------------
        */

        async function clearResponse() {

            const question =
                questions[
                    currentIndex
                ];


            if (
                !question ||
                !answered(
                    question
                ) ||
                submitting
            ) {

                return;
            }


            if (
                !window.confirm(
                    'Clear your selected answer?'
                )
            ) {

                return;
            }


            try {

                const data =
                    await request(
                        'ajax/clear_response.php',
                        bodyFor(
                            question
                        )
                    );


                question.answer =
                    '';


                question.visited =
                    1;


                question.status =
                    data.question_status ||
                    (
                        reviewed(
                            question
                        )
                            ? 'Marked for Review'
                            : 'Not Answered'
                    );


                renderQuestion();


                showToast(
                    'Response cleared.',
                    'success'
                );

            } catch (
                error
            ) {

                showToast(
                    error.message ||
                    'Unable to clear response.',
                    'error'
                );

            }

        }


        /*
        |--------------------------------------------------------------------------
        | SUBMIT
        |--------------------------------------------------------------------------
        */

        function openSubmit() {

            const counts =
                getCounts();


            const unanswered =
                counts.notAnswered +
                counts.notVisited;


            if (
                submitMessage
            ) {

                submitMessage.textContent =
                    unanswered > 0
                        ? (
                            'You still have ' +
                            String(
                                unanswered
                            ) +
                            ' unanswered question(s). You can submit anyway.'
                        )
                        : (
                            'All questions are answered. Your examination is ready for final submission.'
                        );

            }


            updateCounts();


            submitModal?.show();

        }


        async function saveAll() {

            for (
                const question of
                questions
            ) {

                if (
                    answered(
                        question
                    )
                ) {

                    const success =
                        await saveQuestion(
                            question,
                            false
                        );


                    if (
                        !success
                    ) {

                        throw new Error(
                            'One or more answers could not be saved.'
                        );
                    }

                }

            }

        }


        async function submitExam(
            autoMode = false
        ) {

            if (
                submitting
            ) {

                return;
            }


            submitting =
                true;


            if (
                timerInterval
            ) {

                clearInterval(
                    timerInterval
                );
            }


            if (
                submitButton
            ) {

                submitButton.disabled =
                    true;
            }


            if (
                confirmSubmit
            ) {

                confirmSubmit.disabled =
                    true;


                confirmSubmit.innerHTML =
                    '<i class="fa-solid fa-spinner fa-spin"></i> Submitting...';
            }


            try {

                const current =
                    questions[
                        currentIndex
                    ];


                if (
                    current &&
                    answered(
                        current
                    )
                ) {

                    await saveQuestion(
                        current,
                        false
                    );
                }


                await saveAll();


                if (
                    autoSubmitInput
                ) {

                    autoSubmitInput.value =
                        autoMode
                            ? '1'
                            : '0';
                }


                if (
                    submitForm
                ) {

                    submitForm.submit();

                } else {

                    throw new Error(
                        'Submission form unavailable.'
                    );
                }

            } catch (
                error
            ) {

                submitting =
                    false;


                if (
                    submitButton
                ) {

                    submitButton.disabled =
                        false;
                }


                if (
                    confirmSubmit
                ) {

                    confirmSubmit.disabled =
                        false;

                    confirmSubmit.innerHTML =
                        'Submit Now';
                }


                showToast(
                    error.message ||
                    'Unable to submit examination.',
                    'error'
                );

            }

        }


        async function autoSubmit() {

            if (
                autoSubmitting
            ) {

                return;
            }


            autoSubmitting =
                true;


            showToast(
                'Time is over. Submitting examination...',
                'error'
            );


            await submitExam(
                true
            );

        }


        /*
        |--------------------------------------------------------------------------
        | TIMER
        |--------------------------------------------------------------------------
        */

        function formatTime(
            seconds
        ) {

            seconds =
                Math.max(
                    0,
                    Math.floor(
                        Number(
                            seconds
                        ) || 0
                    )
                );


            const minutes =
                Math.floor(
                    seconds / 60
                );


            const remaining =
                seconds % 60;


            return (
                String(
                    minutes
                ).padStart(
                    2,
                    '0'
                ) +

                ':' +

                String(
                    remaining
                ).padStart(
                    2,
                    '0'
                )
            );

        }


        let fiveMinuteWarned =
            false;


        let oneMinuteWarned =
            false;


        function updateTimer() {

            const seconds =
                Math.max(
                    0,
                    Math.floor(
                        (
                            Number(
                                config.deadline
                            ) -
                            Date.now()
                        ) /
                        1000
                    )
                );


            if (
                examTimer
            ) {

                examTimer.textContent =
                    formatTime(
                        seconds
                    );
            }


            if (
                seconds <= 60
            ) {

                if (
                    examTimer
                ) {

                    examTimer.style.color =
                        '#F08B81';
                }


                if (
                    !oneMinuteWarned &&
                    seconds > 0
                ) {

                    oneMinuteWarned =
                        true;


                    showToast(
                        'Only 1 minute remaining.',
                        'error'
                    );

                }

            } else if (
                seconds <= 300
            ) {

                if (
                    examTimer
                ) {

                    examTimer.style.color =
                        '#E2BD5C';
                }


                if (
                    !fiveMinuteWarned
                ) {

                    fiveMinuteWarned =
                        true;


                    showToast(
                        'Only 5 minutes remaining.',
                        'error'
                    );
                }

            }


            if (
                seconds <= 0
            ) {

                clearInterval(
                    timerInterval
                );


                autoSubmit();

            }

        }


        /*
        |--------------------------------------------------------------------------
        | EVENTS
        |--------------------------------------------------------------------------
        */

        previousButton?.addEventListener(
            'click',
            previousQuestion
        );


        nextButton?.addEventListener(
            'click',
            nextQuestion
        );


        markNextButton?.addEventListener(
            'click',
            markAndNext
        );


        clearButton?.addEventListener(
            'click',
            clearResponse
        );


        submitButton?.addEventListener(
            'click',
            openSubmit
        );


        confirmSubmit?.addEventListener(
            'click',
            function () {

                submitModal?.hide();

                submitExam(
                    false
                );

            }
        );


        document
            .getElementById(
                'examInstructions'
            )
            ?.addEventListener(
                'click',
                function () {

                    instructionsModal?.show();

                }
            );


        document
            .getElementById(
                'examShortcuts'
            )
            ?.addEventListener(
                'click',
                function () {

                    shortcutsModal?.show();

                }
            );


        document
            .getElementById(
                'examPaper'
            )
            ?.addEventListener(
                'click',
                function () {

                    buildPaperPalette();

                    paperModal?.show();

                }
            );


        /*
        |--------------------------------------------------------------------------
        | KEYBOARD
        |--------------------------------------------------------------------------
        */

        document.addEventListener(
            'keydown',
            function (
                event
            ) {

                const tag =
                    String(
                        event.target?.tagName ||
                        ''
                    ).toLowerCase();


                if (
                    [
                        'input',
                        'textarea',
                        'select',
                        'button'
                    ].includes(
                        tag
                    )
                ) {

                    return;
                }


                if (
                    event.key ===
                    'ArrowLeft'
                ) {

                    event.preventDefault();

                    previousQuestion();

                }


                if (
                    event.key ===
                    'ArrowRight'
                ) {

                    event.preventDefault();

                    nextQuestion();

                }


                if (
                    event.key.toLowerCase() ===
                    'r'
                ) {

                    event.preventDefault();

                    markAndNext();

                }


                if (
                    event.key.toLowerCase() ===
                    'c'
                ) {

                    event.preventDefault();

                    clearResponse();

                }

            }
        );


        /*
        |--------------------------------------------------------------------------
        | ONLINE
        |--------------------------------------------------------------------------
        */

        window.addEventListener(
            'online',
            function () {

                showToast(
                    'Connection restored.',
                    'success'
                );


                pending.forEach(
                    function (
                        id
                    ) {

                        const question =
                            questions.find(
                                function (
                                    item
                                ) {

                                    return Number(
                                        item.id
                                    ) ===
                                    Number(
                                        id
                                    );

                                }
                            );


                        if (
                            question
                        ) {

                            saveQuestion(
                                question,
                                false
                            );
                        }

                    }
                );

            }
        );


        window.addEventListener(
            'offline',
            function () {

                showToast(
                    'Internet connection lost. Answers will retry automatically.',
                    'error'
                );

            }
        );


        /*
        |--------------------------------------------------------------------------
        | VISIBILITY
        |--------------------------------------------------------------------------
        */

        document.addEventListener(
            'visibilitychange',
            function () {

                if (
                    document.visibilityState ===
                    'visible'
                ) {

                    updateTimer();

                }

            }
        );


        /*
        |--------------------------------------------------------------------------
        | BEFORE LEAVE
        |--------------------------------------------------------------------------
        */

        window.addEventListener(
            'beforeunload',
            function () {

                if (
                    submitting ||
                    !navigator.sendBeacon
                ) {

                    return;
                }


                const question =
                    questions[
                        currentIndex
                    ];


                if (
                    !question ||
                    !answered(
                        question
                    )
                ) {

                    return;
                }


                const body =
                    bodyFor(
                        question
                    );


                const blob =
                    new Blob(
                        [
                            body.toString()
                        ],
                        {
                            type:
                                'application/x-www-form-urlencoded;charset=UTF-8'
                        }
                    );


                navigator.sendBeacon(
                    'ajax/save_answer.php',
                    blob
                );

            }
        );


        /*
        |--------------------------------------------------------------------------
        | INIT
        |--------------------------------------------------------------------------
        */

        buildPalette();

        buildPaperPalette();

        renderQuestion();

        updateCounts();

        updateButtons();

        updateTimer();

        markVisited(
            questions[
                currentIndex
            ]
        );


        timerInterval =
            setInterval(
                updateTimer,
                1000
            );


    }
);