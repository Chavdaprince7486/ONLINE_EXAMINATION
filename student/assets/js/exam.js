'use strict';

document.addEventListener(
    'DOMContentLoaded',
    function () {

        const config =
            window.EXAMSPHERE_EXAM || null;

        if (!config) {
            return;
        }


        const questions =
            Array.isArray(config.questions)
                ? config.questions
                : [];


        if (!questions.length) {
            return;
        }


        let currentIndex = 0;

        let saveTimer = null;

        let submitting = false;

        let busy = false;


        /*
        |--------------------------------------------------------------------------
        | DOM
        |--------------------------------------------------------------------------
        */

        const questionContainer =
            document.getElementById(
                'questionContainer'
            );

        const questionNumber =
            document.getElementById(
                'questionNumber'
            );

        const questionProgress =
            document.getElementById(
                'questionProgress'
            );

        const previousButton =
            document.getElementById(
                'previousButton'
            );

        const reviewButton =
            document.getElementById(
                'reviewButton'
            );

        const clearButton =
            document.getElementById(
                'clearButton'
            );

        const nextButton =
            document.getElementById(
                'nextButton'
            );

        const palette =
            document.getElementById(
                'questionPalette'
            );

        const answeredCount =
            document.getElementById(
                'answeredCount'
            );

        const timerBox =
            document.getElementById(
                'timerBox'
            );

        const examTimer =
            document.getElementById(
                'examTimer'
            );

        const submitModal =
            document.getElementById(
                'submitModal'
            );

        const openSubmitButton =
            document.getElementById(
                'openSubmitButton'
            );

        const confirmSubmitButton =
            document.getElementById(
                'confirmSubmitButton'
            );

        const dialogAnswered =
            document.getElementById(
                'dialogAnswered'
            );

        const dialogUnanswered =
            document.getElementById(
                'dialogUnanswered'
            );

        const dialogReviewed =
            document.getElementById(
                'dialogReviewed'
            );

        const submitWarning =
            document.getElementById(
                'submitWarning'
            );

        const examToast =
            document.getElementById(
                'examToast'
            );

        const realSubmitForm =
            document.getElementById(
                'realSubmitForm'
            );

        const autoSubmitField =
            document.getElementById(
                'autoSubmitField'
            );


        /*
        |--------------------------------------------------------------------------
        | QUESTION NORMALIZATION
        |--------------------------------------------------------------------------
        */

        questions.forEach(
            function (question) {

                question.selected_answer =
                    String(
                        question.answer || ''
                    )
                        .trim()
                        .toUpperCase();


                question.question_status =
                    normalizeStatus(
                        question.status
                    );


                question._visited =
                    question.question_status !==
                    'Not Visited'
                        ? 1
                        : 0;


                if (
                    question.question_status ===
                    'Answered'
                ) {

                    question._visited = 1;
                }


                if (
                    question.question_status ===
                    'Marked for Review'
                ) {

                    question._visited = 1;
                }


                if (
                    question.question_status ===
                    'Answered & Marked for Review'
                ) {

                    question._visited = 1;
                }

            }
        );


        /*
        |--------------------------------------------------------------------------
        | NORMALIZE STATUS
        |--------------------------------------------------------------------------
        */

        function normalizeStatus(
            status
        ) {

            const value =
                String(
                    status || ''
                ).trim();


            const valid = [

                'Not Visited',

                'Not Answered',

                'Answered',

                'Marked for Review',

                'Answered & Marked for Review'

            ];


            return valid.includes(
                value
            )
                ? value
                : 'Not Visited';
        }


        /*
        |--------------------------------------------------------------------------
        | HELPERS
        |--------------------------------------------------------------------------
        */

        function currentQuestion()
        {

            return questions[
                currentIndex
            ] || null;
        }


        function isAnswered(
            question
        )
        {

            return Boolean(
                String(
                    question.selected_answer || ''
                ).trim()
            );
        }


        function isReviewed(
            question
        )
        {

            return (
                question.question_status ===
                    'Marked for Review'

                ||

                question.question_status ===
                    'Answered & Marked for Review'
            );
        }


        function calculateStatus(
            question
        )
        {

            const answered =
                isAnswered(
                    question
                );


            const reviewed =
                isReviewed(
                    question
                );


            if (
                answered &&
                reviewed
            ) {

                return 'Answered & Marked for Review';
            }


            if (
                answered
            ) {

                return 'Answered';
            }


            if (
                reviewed
            ) {

                return 'Marked for Review';
            }


            if (
                Number(
                    question._visited
                ) === 1
            ) {

                return 'Not Answered';
            }


            return 'Not Visited';
        }


        function escapeHtml(
            value
        )
        {

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


        function showToast(
            message,
            type = 'normal'
        )
        {

            if (!examToast) {
                return;
            }


            examToast.textContent =
                message;


            examToast.classList.remove(
                'show',
                'error',
                'success'
            );


            if (
                type === 'error'
            ) {

                examToast.classList.add(
                    'error'
                );

            } else if (
                type === 'success'
            ) {

                examToast.classList.add(
                    'success'
                );
            }


            requestAnimationFrame(
                function () {

                    examToast.classList.add(
                        'show'
                    );
                }
            );


            window.setTimeout(
                function () {

                    examToast.classList.remove(
                        'show'
                    );

                },
                2200
            );
        }


        /*
        |--------------------------------------------------------------------------
        | SAVE INDICATOR
        |--------------------------------------------------------------------------
        */

        function setSaveIndicator(
            state
        )
        {

            /*
             * Current HTML does not have a dedicated save indicator.
             * Use toast silently where appropriate.
             */
        }


        /*
        |--------------------------------------------------------------------------
        | COUNTERS
        |--------------------------------------------------------------------------
        */

        function getCounts()
        {

            let notVisited = 0;

            let notAnswered = 0;

            let answered = 0;

            let reviewed = 0;


            questions.forEach(
                function (question) {

                    const status =
                        calculateStatus(
                            question
                        );


                    if (
                        status ===
                        'Not Visited'
                    ) {

                        notVisited++;

                    } else if (
                        status ===
                        'Not Answered'
                    ) {

                        notAnswered++;

                    } else if (
                        status ===
                        'Answered'
                    ) {

                        answered++;

                    } else if (
                        status ===
                        'Marked for Review'
                    ) {

                        reviewed++;

                    } else if (
                        status ===
                        'Answered & Marked for Review'
                    ) {

                        answered++;

                        reviewed++;
                    }

                }
            );


            return {

                notVisited,

                notAnswered,

                answered,

                reviewed

            };
        }


        function updateCounts()
        {

            const counts =
                getCounts();


            if (
                answeredCount
            ) {

                answeredCount.textContent =
                    String(
                        counts.answered
                    ) +
                    ' answered';
            }


            if (
                dialogAnswered
            ) {

                dialogAnswered.textContent =
                    String(
                        counts.answered
                    );
            }


            if (
                dialogUnanswered
            ) {

                dialogUnanswered.textContent =
                    String(
                        counts.notAnswered +
                        counts.notVisited
                    );
            }


            if (
                dialogReviewed
            ) {

                dialogReviewed.textContent =
                    String(
                        counts.reviewed
                    );
            }


            refreshPalette();
        }


        /*
        |--------------------------------------------------------------------------
        | QUESTION PALETTE
        |--------------------------------------------------------------------------
        */

        function buildPalette()
        {

            if (!palette) {
                return;
            }


            palette.innerHTML =
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
                        'not-visited';


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
                        async function () {

                            await navigateTo(
                                index
                            );

                        }
                    );


                    palette.appendChild(
                        button
                    );

                }
            );


            refreshPalette();
        }


        function refreshPalette()
        {

            if (!palette) {
                return;
            }


            const buttons =
                palette.querySelectorAll(
                    'button'
                );


            buttons.forEach(
                function (
                    button,
                    index
                ) {

                    const question =
                        questions[
                            index
                        ];


                    if (!question) {
                        return;
                    }


                    button.classList.remove(
                        'current',
                        'answered',
                        'review',
                        'answered-review',
                        'not-answered',
                        'not-visited'
                    );


                    const status =
                        calculateStatus(
                            question
                        );


                    if (
                        index ===
                        currentIndex
                    ) {

                        button.classList.add(
                            'current'
                        );
                    }


                    if (
                        status ===
                        'Answered & Marked for Review'
                    ) {

                        button.classList.add(
                            'answered-review'
                        );

                    } else if (
                        status ===
                        'Answered'
                    ) {

                        button.classList.add(
                            'answered'
                        );

                    } else if (
                        status ===
                        'Marked for Review'
                    ) {

                        button.classList.add(
                            'review'
                        );

                    } else if (
                        status ===
                        'Not Answered'
                    ) {

                        button.classList.add(
                            'not-answered'
                        );

                    } else {

                        button.classList.add(
                            'not-visited'
                        );
                    }

                }
            );
        }


        /*
        |--------------------------------------------------------------------------
        | RENDER QUESTION
        |--------------------------------------------------------------------------
        */

        function renderQuestion()
        {

            const question =
                currentQuestion();


            if (
                !question ||
                !questionContainer
            ) {

                return;
            }


            question._visited =
                1;


            const statusBeforeRender =
                calculateStatus(
                    question
                );


            if (
                statusBeforeRender ===
                'Not Visited'
            ) {

                question.question_status =
                    'Not Answered';
            }


            if (
                questionNumber
            ) {

                questionNumber.textContent =
                    String(
                        currentIndex + 1
                    );
            }


            if (
                questionProgress
            ) {

                questionProgress.textContent =
                    'Question ' +
                    String(
                        currentIndex + 1
                    ) +
                    ' of ' +
                    String(
                        questions.length
                    );
            }


            const questionType =
                String(
                    question.type || 'MCQ'
                );


            const options =
                question.options &&
                typeof question.options ===
                    'object'
                    ? question.options
                    : {};


            const optionEntries =
                Object.entries(
                    options
                ).filter(
                    function (entry) {

                        return (
                            String(
                                entry[1] ?? ''
                            ).trim() !== ''
                        );
                    }
                );


            let html =
                '';


            html +=
                '<div class="question-heading-row">';


            html +=
                '<span class="question-difficulty ' +
                escapeHtml(
                    String(
                        question.difficulty ||
                        'Medium'
                    ).toLowerCase()
                ) +
                '">' +
                escapeHtml(
                    String(
                        question.difficulty ||
                        'Medium'
                    )
                ) +
                '</span>';


            html +=
                '<span class="question-marks">' +
                escapeHtml(
                    String(
                        question.marks ??
                        0
                    )
                ) +
                ' marks</span>';


            html +=
                '</div>';


            html +=
                '<h2 class="exam-question-text">' +
                escapeHtml(
                    question.text ??
                    question.question_text ??
                    ''
                ) +
                '</h2>';


            if (
                question.image
            ) {

                html +=
                    '<div class="question-image-wrap">' +

                    '<img src="' +
                    escapeHtml(
                        question.image
                    ) +
                    '" alt="Question image">' +

                    '</div>';
            }


            html +=
                '<div class="question-options">';


            optionEntries.forEach(
                function (
                    entry
                ) {

                    const label =
                        String(
                            entry[0]
                        );


                    const text =
                        String(
                            entry[1]
                        );


                    const selected =
                        String(
                            question.selected_answer ||
                            ''
                        ).toUpperCase() ===
                        label.toUpperCase();


                    html +=
                        '<label class="question-option ' +
                        (
                            selected
                                ? 'selected'
                                : ''
                        ) +
                        '">';


                    html +=
                        '<input ' +

                        'type="radio" ' +

                        'name="question_' +
                        escapeHtml(
                            String(
                                question.id
                            )
                        ) +
                        '" ' +

                        'value="' +
                        escapeHtml(
                            label
                        ) +
                        '" ' +

                        (
                            selected
                                ? 'checked'
                                : ''
                        ) +

                        ' data-question-id="' +
                        escapeHtml(
                            String(
                                question.id
                            )
                        ) +
                        '">';


                    html +=
                        '<span class="option-label">' +
                        escapeHtml(
                            label
                        ) +
                        '</span>';


                    html +=
                        '<span class="option-text">' +
                        escapeHtml(
                            text
                        ) +
                        '</span>';


                    html +=
                        '</label>';
                }
            );


            html +=
                '</div>';


            html +=
                '<div class="question-status-text">' +
                escapeHtml(
                    calculateStatus(
                        question
                    )
                ) +
                '</div>';


            questionContainer.innerHTML =
                html;


            attachOptionHandlers();


            updateNavigationButtons();

            refreshPalette();

            updateCounts();
        }


        /*
        |--------------------------------------------------------------------------
        | OPTION HANDLERS
        |--------------------------------------------------------------------------
        */

        function attachOptionHandlers()
        {

            if (!questionContainer) {
                return;
            }


            const optionInputs =
                questionContainer.querySelectorAll(
                    'input[type="radio"]'
                );


            optionInputs.forEach(
                function (
                    input
                ) {

                    input.addEventListener(
                        'change',
                        async function () {

                            const question =
                                currentQuestion();


                            if (!question) {
                                return;
                            }


                            question.selected_answer =
                                String(
                                    input.value
                                )
                                    .trim()
                                    .toUpperCase();


                            question._visited =
                                1;


                            question.question_status =
                                calculateStatus(
                                    question
                                );


                            renderSelectedOptionState();


                            updateCounts();


                            scheduleSave();

                        }
                    );

                }
            );
        }


        function renderSelectedOptionState()
        {

            if (!questionContainer) {
                return;
            }


            const current =
                currentQuestion();


            const options =
                questionContainer.querySelectorAll(
                    '.question-option'
                );


            options.forEach(
                function (
                    option
                ) {

                    const radio =
                        option.querySelector(
                            'input'
                        );


                    if (!radio) {
                        return;
                    }


                    option.classList.toggle(
                        'selected',
                        radio.checked
                    );
                }
            );


            const statusText =
                questionContainer.querySelector(
                    '.question-status-text'
                );


            if (
                statusText &&
                current
            ) {

                statusText.textContent =
                    calculateStatus(
                        current
                    );
            }
        }


        /*
        |--------------------------------------------------------------------------
        | NAVIGATION
        |--------------------------------------------------------------------------
        */

        function updateNavigationButtons()
        {

            if (
                previousButton
            ) {

                previousButton.disabled =
                    currentIndex <= 0;
            }


            if (
                nextButton
            ) {

                nextButton.disabled =
                    currentIndex >=
                    questions.length - 1;
            }


            if (
                reviewButton
            ) {

                const question =
                    currentQuestion();


                if (question) {

                    reviewButton.innerHTML =
                        isReviewed(
                            question
                        )

                            ? '<i class="fa-solid fa-bookmark"></i> Unmark review'

                            : '<i class="fa-regular fa-bookmark"></i> Mark for review';
                }
            }
        }


        async function navigateTo(
            index
        )
        {

            if (
                index < 0 ||
                index >= questions.length
            ) {

                return;
            }


            if (
                busy ||
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


            busy =
                true;


            try {

                await saveCurrentQuestion(
                    false
                );


                currentIndex =
                    index;


                const question =
                    currentQuestion();


                if (
                    question
                ) {

                    question._visited =
                        1;


                    if (
                        calculateStatus(
                            question
                        ) ===
                        'Not Visited'
                    ) {

                        question.question_status =
                            'Not Answered';
                    }
                }


                renderQuestion();


                saveStatusOpen(
                    question
                );

            } finally {

                busy =
                    false;
            }
        }


        /*
        |--------------------------------------------------------------------------
        | SAVE
        |--------------------------------------------------------------------------
        */

        function scheduleSave()
        {

            if (
                saveTimer
            ) {

                window.clearTimeout(
                    saveTimer
                );
            }


            saveTimer =
                window.setTimeout(
                    function () {

                        saveCurrentQuestion(
                            false
                        );

                    },
                    450
                );
        }


        async function saveCurrentQuestion(
            showMessage = false
        )
        {

            const question =
                currentQuestion();


            if (!question) {
                return false;
            }


            const body =
                new URLSearchParams();


            body.append(
                'attempt_id',
                String(
                    config.attemptId
                )
            );


            body.append(
                'question_id',
                String(
                    question.id
                )
            );


            body.append(
                'selected_answer',
                String(
                    question.selected_answer ||
                    ''
                )
            );


            body.append(
                'question_status',
                calculateStatus(
                    question
                )
            );


            body.append(
                'csrf_token',
                String(
                    config.csrfToken ||
                    ''
                )
            );


            try {

                const response =
                    await fetch(
                        'ajax/save_answer.php',
                        {

                            method:
                                'POST',

                            headers: {

                                'Content-Type':
                                    'application/x-www-form-urlencoded; charset=UTF-8',

                                'X-Requested-With':
                                    'XMLHttpRequest'

                            },

                            credentials:
                                'same-origin',

                            body:
                                body.toString()

                        }
                    );


                const data =
                    await parseJson(
                        response
                    );


                if (
                    !response.ok ||
                    !data ||
                    data.status !== true
                ) {

                    throw new Error(
                        data &&
                        data.message
                            ? data.message
                            : 'Unable to save your answer.'
                    );
                }


                question.question_status =
                    data.question_status ||
                    calculateStatus(
                        question
                    );


                updateCounts();


                if (
                    showMessage
                ) {

                    showToast(
                        'Answer saved.',
                        'success'
                    );
                }


                return true;

            } catch (
                error
            ) {

                console.error(
                    error
                );


                if (
                    showMessage
                ) {

                    showToast(
                        error.message ||
                        'Unable to save your answer.',
                        'error'
                    );
                }


                return false;
            }
        }


        /*
        |--------------------------------------------------------------------------
        | OPEN STATUS
        |--------------------------------------------------------------------------
        */

        async function saveStatusOpen(
            question
        )
        {

            if (!question) {
                return;
            }


            const body =
                new URLSearchParams();


            body.append(
                'attempt_id',
                String(
                    config.attemptId
                )
            );


            body.append(
                'question_id',
                String(
                    question.id
                )
            );


            body.append(
                'action',
                'OPEN'
            );


            body.append(
                'csrf_token',
                String(
                    config.csrfToken ||
                    ''
                )
            );


            try {

                const response =
                    await fetch(
                        'ajax/update_status.php',
                        {

                            method:
                                'POST',

                            headers: {

                                'Content-Type':
                                    'application/x-www-form-urlencoded; charset=UTF-8',

                                'X-Requested-With':
                                    'XMLHttpRequest'

                            },

                            credentials:
                                'same-origin',

                            body:
                                body.toString()

                        }
                    );


                const data =
                    await parseJson(
                        response
                    );


                if (
                    response.ok &&
                    data &&
                    data.status === true
                ) {

                    if (
                        data.question_status
                    ) {

                        question.question_status =
                            data.question_status;
                    }


                    question._visited =
                        1;


                    updateCounts();

                    renderSelectedOptionState();
                }

            } catch (
                error
            ) {

                console.error(
                    error
                );
            }
        }


        /*
        |--------------------------------------------------------------------------
        | MARK / UNMARK REVIEW
        |--------------------------------------------------------------------------
        */

        async function toggleReview()
        {

            const question =
                currentQuestion();


            if (
                !question ||
                submitting
            ) {

                return;
            }


            const reviewed =
                isReviewed(
                    question
                );


            const action =
                reviewed
                    ? 'UNMARK'
                    : 'MARK';


            const body =
                new URLSearchParams();


            body.append(
                'attempt_id',
                String(
                    config.attemptId
                )
            );


            body.append(
                'question_id',
                String(
                    question.id
                )
            );


            body.append(
                'action',
                action
            );


            body.append(
                'csrf_token',
                String(
                    config.csrfToken ||
                    ''
                )
            );


            try {

                const response =
                    await fetch(
                        'ajax/update_status.php',
                        {

                            method:
                                'POST',

                            headers: {

                                'Content-Type':
                                    'application/x-www-form-urlencoded; charset=UTF-8',

                                'X-Requested-With':
                                    'XMLHttpRequest'

                            },

                            credentials:
                                'same-origin',

                            body:
                                body.toString()

                        }
                    );


                const data =
                    await parseJson(
                        response
                    );


                if (
                    !response.ok ||
                    !data ||
                    data.status !== true
                ) {

                    throw new Error(
                        data &&
                        data.message
                            ? data.message
                            : 'Unable to update review status.'
                    );
                }


                question.question_status =
                    data.question_status ||
                    calculateStatus(
                        question
                    );


                question._visited =
                    1;


                updateCounts();

                renderSelectedOptionState();

                updateNavigationButtons();

                showToast(
                    reviewed
                        ? 'Review mark removed.'
                        : 'Question marked for review.',
                    'success'
                );

            } catch (
                error
            ) {

                console.error(
                    error
                );


                showToast(
                    error.message ||
                    'Unable to update review status.',
                    'error'
                );
            }
        }


        /*
        |--------------------------------------------------------------------------
        | CLEAR RESPONSE
        |--------------------------------------------------------------------------
        */

        async function clearCurrentAnswer()
        {

            const question =
                currentQuestion();


            if (
                !question ||
                submitting
            ) {

                return;
            }


            const confirmed =
                window.confirm(
                    'Clear your answer for this question?'
                );


            if (
                !confirmed
            ) {

                return;
            }


            const body =
                new URLSearchParams();


            body.append(
                'attempt_id',
                String(
                    config.attemptId
                )
            );


            body.append(
                'question_id',
                String(
                    question.id
                )
            );


            body.append(
                'csrf_token',
                String(
                    config.csrfToken ||
                    ''
                )
            );


            try {

                const response =
                    await fetch(
                        'ajax/clear_response.php',
                        {

                            method:
                                'POST',

                            headers: {

                                'Content-Type':
                                    'application/x-www-form-urlencoded; charset=UTF-8',

                                'X-Requested-With':
                                    'XMLHttpRequest'

                            },

                            credentials:
                                'same-origin',

                            body:
                                body.toString()

                        }
                    );


                const data =
                    await parseJson(
                        response
                    );


                if (
                    !response.ok ||
                    !data ||
                    data.status !== true
                ) {

                    throw new Error(
                        data &&
                        data.message
                            ? data.message
                            : 'Unable to clear your answer.'
                    );
                }


                question.selected_answer =
                    '';


                question._visited =
                    1;


                question.question_status =
                    data.question_status ||
                    'Not Answered';


                renderQuestion();

                updateCounts();

                showToast(
                    'Answer cleared.',
                    'success'
                );

            } catch (
                error
            ) {

                console.error(
                    error
                );


                showToast(
                    error.message ||
                    'Unable to clear your answer.',
                    'error'
                );
            }
        }


        /*
        |--------------------------------------------------------------------------
        | SAVE & NEXT
        |--------------------------------------------------------------------------
        */

        async function saveAndNext()
        {

            if (
                submitting
            ) {

                return;
            }


            const saved =
                await saveCurrentQuestion(
                    true
                );


            if (
                !saved
            ) {

                return;
            }


            if (
                currentIndex <
                questions.length - 1
            ) {

                currentIndex++;

                const question =
                    currentQuestion();


                if (
                    question
                ) {

                    question._visited =
                        1;


                    if (
                        calculateStatus(
                            question
                        ) ===
                        'Not Visited'
                    ) {

                        question.question_status =
                            'Not Answered';
                    }
                }


                renderQuestion();

                saveStatusOpen(
                    question
                );

            } else {

                showToast(
                    'You are on the last question.',
                    'normal'
                );
            }
        }


        /*
        |--------------------------------------------------------------------------
        | PREVIOUS
        |--------------------------------------------------------------------------
        */

        async function goPrevious()
        {

            if (
                submitting
            ) {

                return;
            }


            await saveCurrentQuestion(
                false
            );


            if (
                currentIndex > 0
            ) {

                currentIndex--;


                const question =
                    currentQuestion();


                if (
                    question
                ) {

                    question._visited =
                        1;


                    if (
                        calculateStatus(
                            question
                        ) ===
                        'Not Visited'
                    ) {

                        question.question_status =
                            'Not Answered';
                    }
                }


                renderQuestion();

                saveStatusOpen(
                    question
                );
            }
        }


        /*
        |--------------------------------------------------------------------------
        | SUBMIT MODAL
        |--------------------------------------------------------------------------
        */

        let modalInstance =
            null;


        function getSubmitModal()
        {

            if (
                !submitModal
            ) {

                return null;
            }


            if (
                typeof window.bootstrap ===
                'undefined'
            ) {

                return null;
            }


            if (
                !modalInstance
            ) {

                modalInstance =
                    new window.bootstrap.Modal(
                        submitModal
                    );
            }


            return modalInstance;
        }


        function openSubmitDialog()
        {

            const counts =
                getCounts();


            if (
                dialogAnswered
            ) {

                dialogAnswered.textContent =
                    String(
                        counts.answered
                    );
            }


            if (
                dialogUnanswered
            ) {

                dialogUnanswered.textContent =
                    String(
                        counts.notAnswered +
                        counts.notVisited
                    );
            }


            if (
                dialogReviewed
            ) {

                dialogReviewed.textContent =
                    String(
                        counts.reviewed
                    );
            }


            if (
                submitWarning
            ) {

                if (
                    counts.notAnswered +
                    counts.notVisited >
                    0
                ) {

                    submitWarning.textContent =
                        'You still have ' +
                        String(
                            counts.notAnswered +
                            counts.notVisited
                        ) +
                        ' unanswered question(s). Are you sure you want to submit?';

                } else {

                    submitWarning.textContent =
                        'All questions are answered. Are you sure you want to submit the examination?';
                }
            }


            const modal =
                getSubmitModal();


            if (modal) {

                modal.show();

            } else {

                const confirmed =
                    window.confirm(
                        'Are you sure you want to submit the examination?'
                    );


                if (
                    confirmed
                ) {

                    submitExam();
                }
            }
        }


        /*
        |--------------------------------------------------------------------------
        | SUBMIT
        |--------------------------------------------------------------------------
        */

        async function submitExam()
        {

            if (
                submitting
            ) {

                return;
            }


            submitting =
                true;


            if (
                confirmSubmitButton
            ) {

                confirmSubmitButton.disabled =
                    true;


                confirmSubmitButton.innerHTML =
                    '<i class="fa-solid fa-spinner fa-spin"></i> Submitting...';
            }


            try {

                await saveAllAnswers();


                const body =
                    new URLSearchParams();


                body.append(
                    'attempt_id',
                    String(
                        config.attemptId
                    )
                );


                body.append(
                    'csrf_token',
                    String(
                        config.csrfToken ||
                        ''
                    )
                );


                body.append(
                    'auto_submit',
                    '0'
                );


                const response =
                    await fetch(
                        'ajax/submit_exam.php',
                        {

                            method:
                                'POST',

                            headers: {

                                'Content-Type':
                                    'application/x-www-form-urlencoded; charset=UTF-8',

                                'X-Requested-With':
                                    'XMLHttpRequest'

                            },

                            credentials:
                                'same-origin',

                            body:
                                body.toString()

                        }
                    );


                const data =
                    await parseJson(
                        response
                    );


                if (
                    !response.ok ||
                    !data ||
                    data.status !== true
                ) {

                    throw new Error(
                        data &&
                        data.message
                            ? data.message
                            : 'Unable to submit the examination.'
                    );
                }


                const redirect =
                    data.redirect
                        ? String(
                            data.redirect
                        )
                        : (
                            'result.php?id=' +
                            String(
                                data.result_id || ''
                            )
                        );


                window.location.href =
                    redirect;

            } catch (
                error
            ) {

                console.error(
                    error
                );


                submitting =
                    false;


                if (
                    confirmSubmitButton
                ) {

                    confirmSubmitButton.disabled =
                        false;


                    confirmSubmitButton.innerHTML =
                        'Submit now <i class="fa-solid fa-check"></i>';
                }


                showToast(
                    error.message ||
                    'Unable to submit the examination.',
                    'error'
                );
            }
        }


        /*
        |--------------------------------------------------------------------------
        | SAVE ALL
        |--------------------------------------------------------------------------
        */

        async function saveAllAnswers()
        {

            for (
                let index = 0;
                index < questions.length;
                index++
            ) {

                const question =
                    questions[
                        index
                    ];


                const body =
                    new URLSearchParams();


                body.append(
                    'attempt_id',
                    String(
                        config.attemptId
                    )
                );


                body.append(
                    'question_id',
                    String(
                        question.id
                    )
                );


                body.append(
                    'selected_answer',
                    String(
                        question.selected_answer ||
                        ''
                    )
                );


                body.append(
                    'question_status',
                    calculateStatus(
                        question
                    )
                );


                body.append(
                    'csrf_token',
                    String(
                        config.csrfToken ||
                        ''
                    )
                );


                const response =
                    await fetch(
                        'ajax/save_answer.php',
                        {

                            method:
                                'POST',

                            headers: {

                                'Content-Type':
                                    'application/x-www-form-urlencoded; charset=UTF-8',

                                'X-Requested-With':
                                    'XMLHttpRequest'

                            },

                            credentials:
                                'same-origin',

                            body:
                                body.toString()

                        }
                    );


                const data =
                    await parseJson(
                        response
                    );


                if (
                    !response.ok ||
                    !data ||
                    data.status !== true
                ) {

                    throw new Error(
                        data &&
                        data.message
                            ? data.message
                            : (
                                'Unable to save question ' +
                                String(
                                    index + 1
                                ) +
                                '.'
                            )
                    );
                }


                question.question_status =
                    data.question_status ||
                    calculateStatus(
                        question
                    );
            }
        }


        /*
        |--------------------------------------------------------------------------
        | AUTO SUBMIT
        |--------------------------------------------------------------------------
        */

        let autoSubmitting =
            false;


        async function autoSubmit()
        {

            if (
                autoSubmitting ||
                submitting
            ) {

                return;
            }


            autoSubmitting =
                true;


            submitting =
                true;


            try {

                await saveAllAnswers();


            } catch (
                error
            ) {

                console.error(
                    error
                );
            }


            try {

                const body =
                    new URLSearchParams();


                body.append(
                    'attempt_id',
                    String(
                        config.attemptId
                    )
                );


                body.append(
                    'csrf_token',
                    String(
                        config.csrfToken ||
                        ''
                    )
                );


                body.append(
                    'auto_submit',
                    '1'
                );


                const response =
                    await fetch(
                        'ajax/submit_exam.php',
                        {

                            method:
                                'POST',

                            headers: {

                                'Content-Type':
                                    'application/x-www-form-urlencoded; charset=UTF-8',

                                'X-Requested-With':
                                    'XMLHttpRequest'

                            },

                            credentials:
                                'same-origin',

                            body:
                                body.toString()

                        }
                    );


                const data =
                    await parseJson(
                        response
                    );


                if (
                    data &&
                    data.redirect
                ) {

                    window.location.href =
                        String(
                            data.redirect
                        );

                    return;
                }


                if (
                    data &&
                    data.result_id
                ) {

                    window.location.href =
                        'result.php?id=' +
                        encodeURIComponent(
                            data.result_id
                        );

                    return;
                }


                window.location.href =
                    'ajax/submit_exam.php?attempt_id=' +
                    encodeURIComponent(
                        config.attemptId
                    ) +
                    '&auto_submit=1';

            } catch (
                error
            ) {

                console.error(
                    error
                );


                window.location.href =
                    'ajax/submit_exam.php?attempt_id=' +
                    encodeURIComponent(
                        config.attemptId
                    ) +
                    '&auto_submit=1';
            }
        }


        /*
        |--------------------------------------------------------------------------
        | TIMER
        |--------------------------------------------------------------------------
        */

        let timerInterval =
            null;


        let timerWarned =
            false;


        function formatTime(
            totalSeconds
        )
        {

            const seconds =
                Math.max(
                    0,
                    Math.floor(
                        totalSeconds
                    )
                );


            const hours =
                Math.floor(
                    seconds / 3600
                );


            const minutes =
                Math.floor(
                    (
                        seconds % 3600
                    ) / 60
                );


            const secs =
                seconds % 60;


            if (
                hours > 0
            ) {

                return (

                    String(
                        hours
                    ).padStart(
                        2,
                        '0'
                    )

                    +

                    ':' +

                    String(
                        minutes
                    ).padStart(
                        2,
                        '0'
                    )

                    +

                    ':' +

                    String(
                        secs
                    ).padStart(
                        2,
                        '0'
                    )

                );
            }


            return (

                String(
                    minutes
                ).padStart(
                    2,
                    '0'
                )

                +

                ':' +

                String(
                    secs
                ).padStart(
                    2,
                    '0'
                )

            );
        }


        function updateTimer()
        {

            const deadline =
                Number(
                    config.deadline
                );


            if (
                !deadline ||
                !Number.isFinite(
                    deadline
                )
            ) {

                return;
            }


            const remainingMilliseconds =
                deadline -
                Date.now();


            const remainingSeconds =
                Math.max(
                    0,
                    Math.floor(
                        remainingMilliseconds /
                        1000
                    )
                );


            if (
                examTimer
            ) {

                examTimer.textContent =
                    formatTime(
                        remainingSeconds
                    );
            }


            if (
                timerBox
            ) {

                timerBox.classList.toggle(
                    'timer-warning',
                    remainingSeconds <= 300
                );


                timerBox.classList.toggle(
                    'timer-danger',
                    remainingSeconds <= 60
                );
            }


            if (
                remainingSeconds <= 60 &&
                !timerWarned
            ) {

                timerWarned =
                    true;


                showToast(
                    'Less than one minute remaining.',
                    'error'
                );
            }


            if (
                remainingSeconds <= 0
            ) {

                if (
                    timerInterval
                ) {

                    window.clearInterval(
                        timerInterval
                    );
                }


                showToast(
                    'Time is over. Submitting examination...',
                    'error'
                );


                window.setTimeout(
                    function () {

                        autoSubmit();

                    },
                    250
                );
            }
        }


        /*
        |--------------------------------------------------------------------------
        | JSON
        |--------------------------------------------------------------------------
        */

        async function parseJson(
            response
        )
        {

            const text =
                await response.text();


            if (
                !text
            ) {

                return {};
            }


            try {

                return JSON.parse(
                    text
                );

            } catch (
                error
            ) {

                throw new Error(
                    'Invalid server response.'
                );
            }
        }


        /*
        |--------------------------------------------------------------------------
        | BUTTON EVENTS
        |--------------------------------------------------------------------------
        */

        if (
            previousButton
        ) {

            previousButton.addEventListener(
                'click',
                goPrevious
            );
        }


        if (
            nextButton
        ) {

            nextButton.addEventListener(
                'click',
                saveAndNext
            );
        }


        if (
            reviewButton
        ) {

            reviewButton.addEventListener(
                'click',
                toggleReview
            );
        }


        if (
            clearButton
        ) {

            clearButton.addEventListener(
                'click',
                clearCurrentAnswer
            );
        }


        if (
            openSubmitButton
        ) {

            openSubmitButton.addEventListener(
                'click',
                openSubmitDialog
            );
        }


        if (
            confirmSubmitButton
        ) {

            confirmSubmitButton.addEventListener(
                'click',
                submitExam
            );
        }


        /*
        |--------------------------------------------------------------------------
        | BEFORE UNLOAD
        |--------------------------------------------------------------------------
        */

        window.addEventListener(
            'beforeunload',
            function () {

                if (
                    submitting
                ) {

                    return;
                }


                const question =
                    currentQuestion();


                if (
                    !question
                ) {

                    return;
                }


                /*
                * Best-effort save.
                */
                const body =
                    new URLSearchParams();


                body.append(
                    'attempt_id',
                    String(
                        config.attemptId
                    )
                );


                body.append(
                    'question_id',
                    String(
                        question.id
                    )
                );


                body.append(
                    'selected_answer',
                    String(
                        question.selected_answer ||
                        ''
                    )
                );


                body.append(
                    'question_status',
                    calculateStatus(
                        question
                    )
                );


                body.append(
                    'csrf_token',
                    String(
                        config.csrfToken ||
                        ''
                    )
                );


                if (
                    navigator.sendBeacon
                ) {

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
            }
        );


        /*
        |--------------------------------------------------------------------------
        | KEYBOARD NAVIGATION
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
                    tag === 'input' ||
                    tag === 'textarea' ||
                    tag === 'select'
                ) {

                    return;
                }


                if (
                    event.key ===
                    'ArrowRight'
                ) {

                    event.preventDefault();

                    saveAndNext();

                } else if (
                    event.key ===
                    'ArrowLeft'
                ) {

                    event.preventDefault();

                    goPrevious();

                } else if (
                    event.key.toLowerCase() ===
                    'r'
                ) {

                    event.preventDefault();

                    toggleReview();
                }
            }
        );


        /*
        |--------------------------------------------------------------------------
        | INITIALIZE
        |--------------------------------------------------------------------------
        */

        buildPalette();


        renderQuestion();


        saveStatusOpen(
            currentQuestion()
        );


        updateCounts();


        updateTimer();


        timerInterval =
            window.setInterval(
                updateTimer,
                1000
            );


    }
);