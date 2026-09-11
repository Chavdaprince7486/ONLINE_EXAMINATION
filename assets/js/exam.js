'use strict';

document.addEventListener(
    'DOMContentLoaded',
    function () {

        /*
        |--------------------------------------------------------------------------
        | EXAM CONFIGURATION
        |--------------------------------------------------------------------------
        */

        const config =
            window.EXAMSPHERE_EXAM || null;


        if (!config) {

            console.error(
                'ExamSphere exam configuration is missing.'
            );

            return;
        }


        const questions =
            Array.isArray(config.questions)
                ? config.questions
                : [];


        const attemptId =
            Number(
                config.attemptId || 0
            );


        const csrfToken =
            String(
                config.csrfToken || ''
            );


        if (
            attemptId <= 0
        ) {

            console.error(
                'Invalid examination attempt ID.'
            );

            return;
        }


        if (
            questions.length === 0
        ) {

            console.error(
                'No examination questions found.'
            );

            return;
        }


        /*
        |--------------------------------------------------------------------------
        | DEADLINE
        |--------------------------------------------------------------------------
        */

        function normalizeDeadline(value) {

            if (
                typeof value === 'number'
            ) {

                if (
                    value > 100000000000
                ) {

                    return value;
                }


                if (
                    value > 1000000000
                ) {

                    return value * 1000;
                }
            }


            if (
                typeof value === 'string'
            ) {

                const parsed =
                    Date.parse(
                        value
                    );


                if (
                    !Number.isNaN(
                        parsed
                    )
                ) {

                    return parsed;
                }


                const numeric =
                    Number(
                        value
                    );


                if (
                    Number.isFinite(
                        numeric
                    )
                ) {

                    return numeric > 100000000000
                        ? numeric
                        : numeric * 1000;
                }
            }


            return 0;
        }


        const deadline =
            normalizeDeadline(
                config.deadline
            );


        if (
            deadline <= 0
        ) {

            console.error(
                'Invalid examination deadline.'
            );

            return;
        }


        /*
        |--------------------------------------------------------------------------
        | STATE
        |--------------------------------------------------------------------------
        */

        let currentIndex =
            0;


        let examSubmitting =
            false;


        let navigationBusy =
            false;


        let statusRequestBusy =
            false;


        let saveSequence =
            0;


        let timerInterval =
            null;


        const answers =
            {};


        const visited =
            {};


        const reviewed =
            {};


        /*
        |--------------------------------------------------------------------------
        | RESTORE SERVER STATE
        |--------------------------------------------------------------------------
        */

        questions.forEach(
            function (
                question,
                index
            ) {

                const questionId =
                    Number(
                        question.id
                    );


                const selectedAnswer =
                    String(
                        question.answer || ''
                    ).toUpperCase();


                const status =
                    String(
                        question.status ||
                        'Not Visited'
                    );


                if (
                    [
                        'A',
                        'B',
                        'C',
                        'D'
                    ].includes(
                        selectedAnswer
                    )
                ) {

                    answers[
                        questionId
                    ] =
                        selectedAnswer;
                }


                visited[index] =
                    status !==
                    'Not Visited';


                reviewed[index] =
                    status ===
                        'Marked for Review'
                    ||
                    status ===
                        'Answered & Marked for Review';
            }
        );


        /*
        |--------------------------------------------------------------------------
        | DOM REFERENCES
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


        const questionPalette =
            document.getElementById(
                'questionPalette'
            );


        const answeredCount =
            document.getElementById(
                'answeredCount'
            );


        const examTimer =
            document.getElementById(
                'examTimer'
            );


        const timerBox =
            document.getElementById(
                'timerBox'
            );


        const examToast =
            document.getElementById(
                'examToast'
            );


        const openSubmitButton =
            document.getElementById(
                'openSubmitButton'
            );


        const confirmSubmitButton =
            document.getElementById(
                'confirmSubmitButton'
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
        | REQUIRED DOM CHECK
        |--------------------------------------------------------------------------
        */

        const requiredElements = [

            questionContainer,

            questionNumber,

            questionProgress,

            previousButton,

            reviewButton,

            clearButton,

            nextButton,

            questionPalette,

            answeredCount,

            examTimer,

            timerBox,

            examToast,

            openSubmitButton,

            confirmSubmitButton,

            realSubmitForm,

            autoSubmitField

        ];


        if (
            requiredElements.some(
                function (element) {

                    return !element;

                }
            )
        ) {

            console.error(
                'ExamSphere exam interface is incomplete.'
            );

            return;
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
        | CURRENT QUESTION
        |--------------------------------------------------------------------------
        */

        function getCurrentQuestion() {

            return questions[
                currentIndex
            ] || null;
        }


        /*
        |--------------------------------------------------------------------------
        | QUESTION ANSWER
        |--------------------------------------------------------------------------
        */

        function getAnswer(
            question
        ) {

            if (
                !question
            ) {

                return '';
            }


            return String(
                answers[
                    Number(
                        question.id
                    )
                ] || ''
            ).toUpperCase();
        }


        /*
        |--------------------------------------------------------------------------
        | QUESTION REVIEW
        |--------------------------------------------------------------------------
        */

        function isReviewed(
            index
        ) {

            return Boolean(
                reviewed[index]
            );
        }


        /*
        |--------------------------------------------------------------------------
        | QUESTION STATUS
        |--------------------------------------------------------------------------
        */

        function getStatus(
            index
        ) {

            const question =
                questions[index];


            if (
                !question
            ) {

                return 'Not Visited';
            }


            const hasAnswer =
                getAnswer(
                    question
                ) !== '';


            const review =
                isReviewed(
                    index
                );


            if (
                hasAnswer &&
                review
            ) {

                return 'Answered & Marked for Review';
            }


            if (
                hasAnswer
            ) {

                return 'Answered';
            }


            if (
                review
            ) {

                return 'Marked for Review';
            }


            return visited[index]
                ? 'Not Answered'
                : 'Not Visited';
        }


        /*
        |--------------------------------------------------------------------------
        | COUNTS
        |--------------------------------------------------------------------------
        */

        function getCounts() {

            let notVisited =
                0;


            let notAnswered =
                0;


            let answered =
                0;


            let reviewedCount =
                0;


            questions.forEach(
                function (
                    question,
                    index
                ) {

                    const status =
                        getStatus(
                            index
                        );


                    switch (
                        status
                    ) {

                        case 'Not Visited':

                            notVisited++;

                            break;


                        case 'Not Answered':

                            notAnswered++;

                            break;


                        case 'Answered':

                            answered++;

                            break;


                        case 'Marked for Review':

                            reviewedCount++;

                            break;


                        case 'Answered & Marked for Review':

                            answered++;

                            reviewedCount++;

                            break;

                    }

                }
            );


            return {

                notVisited,

                notAnswered,

                answered,

                reviewed:
                    reviewedCount

            };
        }


        /*
        |--------------------------------------------------------------------------
        | TOAST
        |--------------------------------------------------------------------------
        */

        let toastTimer =
            null;


        function showToast(
            message
        ) {

            examToast.textContent =
                String(
                    message || ''
                );


            examToast.classList.add(
                'show'
            );


            clearTimeout(
                toastTimer
            );


            toastTimer =
                setTimeout(
                    function () {

                        examToast.classList.remove(
                            'show'
                        );

                    },
                    2500
                );
        }


        /*
        |--------------------------------------------------------------------------
        | PALETTE
        |--------------------------------------------------------------------------
        */

        function renderPalette() {

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
                        'question-palette-btn';


                    button.dataset.index =
                        String(
                            index
                        );


                    button.textContent =
                        String(
                            index + 1
                        );


                    button.setAttribute(
                        'aria-label',
                        'Question ' +
                        String(
                            index + 1
                        )
                    );


                    const status =
                        getStatus(
                            index
                        );


                    if (
                        index ===
                        currentIndex
                    ) {

                        button.classList.add(
                            'current'
                        );
                    }


                    switch (
                        status
                    ) {

                        case 'Answered':

                            button.classList.add(
                                'answered'
                            );

                            break;


                        case 'Not Answered':

                            button.classList.add(
                                'not-answered'
                            );

                            break;


                        case 'Marked for Review':

                            button.classList.add(
                                'review'
                            );

                            break;


                        case 'Answered & Marked for Review':

                            button.classList.add(
                                'answered-review'
                            );

                            break;


                        default:

                            button.classList.add(
                                'not-visited'
                            );

                            break;
                    }


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
        }


        /*
        |--------------------------------------------------------------------------
        | UPDATE COUNTS
        |--------------------------------------------------------------------------
        */

        function updateCounts() {

            const counts =
                getCounts();


            answeredCount.textContent =
                counts.answered +
                ' answered';
        }


        /*
        |--------------------------------------------------------------------------
        | RENDER QUESTION
        |--------------------------------------------------------------------------
        */

        function renderQuestion() {

            const question =
                getCurrentQuestion();


            if (
                !question
            ) {

                return;
            }


            visited[
                currentIndex
            ] =
                true;


            const questionId =
                Number(
                    question.id
                );


            const selectedAnswer =
                getAnswer(
                    question
                );


            if (
                questionNumber
            ) {

                questionNumber.textContent =
                    String(
                        question.number ||
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


            let html =
                '';


            /*
            |--------------------------------------------------------------------------
            | IMAGE
            |--------------------------------------------------------------------------
            */

            if (
                String(
                    question.image || ''
                ).trim() !== ''
            ) {

                html += `

                    <img
                        src="${escapeHtml(question.image)}"
                        alt="Question image"
                        class="question-image"
                        loading="lazy"
                    >

                `;
            }


            /*
            |--------------------------------------------------------------------------
            | QUESTION
            |--------------------------------------------------------------------------
            */

            html += `

                <h2 class="exam-question-text">
                    ${escapeHtml(question.text)}
                </h2>

                <div class="options">

            `;


            /*
            |--------------------------------------------------------------------------
            | OPTIONS
            |--------------------------------------------------------------------------
            */

            const options =
                Array.isArray(
                    question.options
                )
                    ? question.options
                    : [];


            options.forEach(
                function (
                    option
                ) {

                    const label =
                        String(
                            option.label || ''
                        ).toUpperCase();


                    const text =
                        String(
                            option.text || ''
                        );


                    const checked =
                        selectedAnswer ===
                        label;


                    html += `

                        <label
                            class="
                                option
                                ${checked ? 'selected' : ''}
                            "
                            data-label="${escapeHtml(label)}"
                        >

                            <input
                                type="radio"
                                name="question_${questionId}"
                                value="${escapeHtml(label)}"
                                ${checked ? 'checked' : ''}
                            >

                            <span class="option-letter">
                                ${escapeHtml(label)}
                            </span>

                            <span class="option-text">
                                ${escapeHtml(text)}
                            </span>

                        </label>

                    `;
                }
            );


            html += `

                </div>

            `;


            /*
            |--------------------------------------------------------------------------
            | STATUS TEXT
            |--------------------------------------------------------------------------
            */

            html += `

                <div class="question-status-text">

                    <i
                        class="fa-solid fa-circle-info"
                    ></i>

                    <span id="currentQuestionStatus">

                        ${escapeHtml(
                            getStatus(
                                currentIndex
                            )
                        )}

                    </span>

                </div>

            `;


            questionContainer.innerHTML =
                html;


            /*
            |--------------------------------------------------------------------------
            | ANSWER EVENTS
            |--------------------------------------------------------------------------
            */

            questionContainer
                .querySelectorAll(
                    '.option'
                )
                .forEach(
                    function (
                        optionElement
                    ) {

                        optionElement.addEventListener(
                            'click',
                            function () {

                                selectAnswer(
                                    question,
                                    String(
                                        optionElement.dataset.label ||
                                        ''
                                    ).toUpperCase()
                                );

                            }
                        );
                    }
                );


            /*
            |--------------------------------------------------------------------------
            | NAVIGATION BUTTONS
            |--------------------------------------------------------------------------
            */

            previousButton.disabled =
                currentIndex === 0;


            if (
                currentIndex ===
                questions.length - 1
            ) {

                nextButton.innerHTML = `

                    Save answer

                    <i
                        class="
                            fa-solid
                            fa-check
                        "
                    ></i>

                `;

            } else {

                nextButton.innerHTML = `

                    Save & Next

                    <i
                        class="
                            fa-solid
                            fa-arrow-right
                        "
                    ></i>

                `;
            }


            /*
            |--------------------------------------------------------------------------
            | REVIEW BUTTON
            |--------------------------------------------------------------------------
            */

            if (
                reviewed[currentIndex]
            ) {

                reviewButton.innerHTML = `

                    <i
                        class="
                            fa-solid
                            fa-bookmark
                        "
                    ></i>

                    Remove review

                `;

            } else {

                reviewButton.innerHTML = `

                    <i
                        class="
                            fa-regular
                            fa-bookmark
                        "
                    ></i>

                    Mark for review

                `;
            }


            /*
            |--------------------------------------------------------------------------
            | UPDATE INTERFACE
            |--------------------------------------------------------------------------
            */

            renderPalette();

            updateCounts();

        }


        /*
        |--------------------------------------------------------------------------
        | SAVE ANSWER
        |--------------------------------------------------------------------------
        */

        async function saveAnswer(
            question,
            answer
        ) {

            const questionId =
                Number(
                    question.id
                );


            const requestId =
                ++saveSequence;


            const body =
                new URLSearchParams();


            body.append(
                'attempt_id',
                String(
                    attemptId
                )
            );


            body.append(
                'question_id',
                String(
                    questionId
                )
            );


            body.append(
                'selected_answer',
                String(
                    answer || ''
                ).toUpperCase()
            );


            body.append(
                'csrf_token',
                csrfToken
            );


            const response =
                await fetch(
                    'ajax/save_answer.php',
                    {
                        method:
                            'POST',

                        headers:
                            {
                                'Content-Type':
                                    'application/x-www-form-urlencoded; charset=UTF-8',

                                'X-Requested-With':
                                    'XMLHttpRequest'
                            },

                        body:
                            body.toString(),

                        credentials:
                            'same-origin',

                        cache:
                            'no-store'
                    }
                );


            let data =
                null;


            try {

                data =
                    await response.json();

            } catch (error) {

                throw new Error(
                    'The server returned an invalid response.'
                );
            }


            if (
                requestId !==
                saveSequence
            ) {

                return data;
            }


            if (
                !response.ok ||
                !data.status
            ) {

                if (
                    response.status === 409 &&
                    data.expired
                ) {

                    handleExpiredExam();

                    return data;
                }


                throw new Error(
                    String(
                        data.message ||
                        'Unable to save your answer.'
                    )
                );
            }


            return data;
        }


        /*
        |--------------------------------------------------------------------------
        | SAVE CURRENT QUESTION
        |--------------------------------------------------------------------------
        */

        async function saveCurrentQuestion() {

            const question =
                getCurrentQuestion();


            if (
                !question
            ) {

                return;
            }


            const answer =
                getAnswer(
                    question
                );


            if (
                answer === ''
            ) {

                /*
                |------------------------------------------------------------------
                | No answer to save.
                | Status endpoint handles visited state.
                |------------------------------------------------------------------
                */

                await updateStatus(
                    question,
                    'OPEN'
                );

                return;
            }


            await saveAnswer(
                question,
                answer
            );
        }


        /*
        |--------------------------------------------------------------------------
        | STATUS UPDATE
        |--------------------------------------------------------------------------
        */

        async function updateStatus(
            question,
            action
        ) {

            if (
                statusRequestBusy
            ) {

                return null;
            }


            if (
                !question
            ) {

                return null;
            }


            statusRequestBusy =
                true;


            try {

                const body =
                    new URLSearchParams();


                body.append(
                    'attempt_id',
                    String(
                        attemptId
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
                    String(
                        action || ''
                    ).toUpperCase()
                );


                body.append(
                    'csrf_token',
                    csrfToken
                );


                const response =
                    await fetch(
                        'ajax/update_status.php',
                        {
                            method:
                                'POST',

                            headers:
                                {
                                    'Content-Type':
                                        'application/x-www-form-urlencoded; charset=UTF-8',

                                    'X-Requested-With':
                                        'XMLHttpRequest'
                                },

                            body:
                                body.toString(),

                            credentials:
                                'same-origin',

                            cache:
                                'no-store'
                        }
                    );


                let data =
                    null;


                try {

                    data =
                        await response.json();

                } catch (error) {

                    throw new Error(
                        'Invalid status response.'
                    );
                }


                if (
                    !response.ok ||
                    !data.status
                ) {

                    if (
                        response.status === 409 &&
                        data.expired
                    ) {

                        handleExpiredExam();

                        return data;
                    }


                    throw new Error(
                        String(
                            data.message ||
                            'Unable to update question status.'
                        )
                    );
                }


                return data;

            } finally {

                statusRequestBusy =
                    false;
            }
        }


        /*
        |--------------------------------------------------------------------------
        | SELECT ANSWER
        |--------------------------------------------------------------------------
        */

        async function selectAnswer(
            question,
            answer
        ) {

            if (
                examSubmitting
            ) {

                return;
            }


            const normalizedAnswer =
                String(
                    answer || ''
                ).toUpperCase();


            if (
                ![
                    'A',
                    'B',
                    'C',
                    'D'
                ].includes(
                    normalizedAnswer
                )
            ) {

                return;
            }


            answers[
                Number(
                    question.id
                )
            ] =
                normalizedAnswer;


            visited[
                currentIndex
            ] =
                true;


            renderQuestion();


            try {

                await saveAnswer(
                    question,
                    normalizedAnswer
                );

            } catch (error) {

                showToast(
                    error.message
                );

                /*
                |--------------------------------------------------------------------------
                | Revert local answer if server save failed.
                |--------------------------------------------------------------------------
                */

                delete answers[
                    Number(
                        question.id
                    )
                ];


                renderQuestion();

                return;
            }


            /*
            |--------------------------------------------------------------------------
            | Preserve review state
            |--------------------------------------------------------------------------
            */

            try {

                await updateStatus(
                    question,
                    reviewed[currentIndex]
                        ? 'MARK'
                        : 'OPEN'
                );

            } catch (error) {

                showToast(
                    error.message
                );
            }
        }


        /*
        |--------------------------------------------------------------------------
        | MARK / REMOVE REVIEW
        |--------------------------------------------------------------------------
        */

        async function toggleReview() {

            if (
                examSubmitting
            ) {

                return;
            }


            const question =
                getCurrentQuestion();


            if (
                !question
            ) {

                return;
            }


            visited[
                currentIndex
            ] =
                true;


            const shouldMark =
                !reviewed[
                    currentIndex
                ];


            reviewed[
                currentIndex
            ] =
                shouldMark;


            renderQuestion();


            try {

                await updateStatus(
                    question,
                    shouldMark
                        ? 'MARK'
                        : 'UNMARK'
                );

            } catch (error) {

                reviewed[
                    currentIndex
                ] =
                    !shouldMark;


                renderQuestion();


                showToast(
                    error.message
                );
            }
        }


        /*
        |--------------------------------------------------------------------------
        | CLEAR ANSWER
        |--------------------------------------------------------------------------
        */

        async function clearCurrentResponse() {

            if (
                examSubmitting
            ) {

                return;
            }


            const question =
                getCurrentQuestion();


            if (
                !question
            ) {

                return;
            }


            const questionId =
                Number(
                    question.id
                );


            const previousAnswer =
                getAnswer(
                    question
                );


            if (
                previousAnswer === ''
            ) {

                showToast(
                    'There is no answer to clear.'
                );

                return;
            }


            delete answers[
                questionId
            ];


            renderQuestion();


            try {

                const body =
                    new URLSearchParams();


                body.append(
                    'attempt_id',
                    String(
                        attemptId
                    )
                );


                body.append(
                    'question_id',
                    String(
                        questionId
                    )
                );


                body.append(
                    'csrf_token',
                    csrfToken
                );


                const response =
                    await fetch(
                        'ajax/clear_response.php',
                        {
                            method:
                                'POST',

                            headers:
                                {
                                    'Content-Type':
                                        'application/x-www-form-urlencoded; charset=UTF-8',

                                    'X-Requested-With':
                                        'XMLHttpRequest'
                                },

                            body:
                                body.toString(),

                            credentials:
                                'same-origin',

                            cache:
                                'no-store'
                        }
                    );


                let data =
                    null;


                try {

                    data =
                        await response.json();

                } catch (error) {

                    throw new Error(
                        'Invalid server response.'
                    );
                }


                if (
                    response.status === 409 &&
                    data.expired
                ) {

                    handleExpiredExam();

                    return;
                }


                if (
                    !response.ok ||
                    !data.status
                ) {

                    throw new Error(
                        String(
                            data.message ||
                            'Unable to clear the response.'
                        )
                    );
                }


                /*
                |--------------------------------------------------------------------------
                | Preserve review state from server response
                |--------------------------------------------------------------------------
                */

                if (
                    String(
                        data.question_status || ''
                    ) ===
                    'Marked for Review'
                ) {

                    reviewed[
                        currentIndex
                    ] =
                        true;

                } else {

                    reviewed[
                        currentIndex
                    ] =
                        false;
                }


                visited[
                    currentIndex
                ] =
                    true;


                renderQuestion();


                showToast(
                    'Response cleared.'
                );

            } catch (error) {

                /*
                |--------------------------------------------------------------------------
                | Restore previous answer on failure
                |--------------------------------------------------------------------------
                */

                answers[
                    questionId
                ] =
                    previousAnswer;


                renderQuestion();


                showToast(
                    error.message
                );
            }
        }


        /*
        |--------------------------------------------------------------------------
        | NAVIGATION
        |--------------------------------------------------------------------------
        */

        async function navigateTo(
            targetIndex
        ) {

            if (
                navigationBusy ||
                examSubmitting
            ) {

                return;
            }


            if (
                !Number.isInteger(
                    targetIndex
                )
            ) {

                return;
            }


            if (
                targetIndex < 0 ||
                targetIndex >= questions.length
            ) {

                return;
            }


            if (
                targetIndex ===
                currentIndex
            ) {

                return;
            }


            navigationBusy =
                true;


            try {

                await saveCurrentQuestion();


                currentIndex =
                    targetIndex;


                renderQuestion();


                window.scrollTo(
                    {
                        top:
                            0,

                        behavior:
                            'smooth'
                    }
                );

            } catch (error) {

                showToast(
                    error.message
                );

            } finally {

                navigationBusy =
                    false;
            }
        }


        /*
        |--------------------------------------------------------------------------
        | SUBMIT COUNTS
        |--------------------------------------------------------------------------
        */

        function updateSubmitDialog() {

            const counts =
                getCounts();


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

                const unanswered =
                    counts.notAnswered +
                    counts.notVisited;


                submitWarning.textContent =
                    unanswered > 0

                        ? (
                            'You still have ' +
                            String(
                                unanswered
                            ) +
                            ' unanswered question' +
                            (
                                unanswered === 1
                                    ? ''
                                    : 's'
                            ) +
                            '. Are you sure you want to submit?'
                        )

                        : 'Are you sure you want to submit this examination?';
            }
        }


        /*
        |--------------------------------------------------------------------------
        | SUBMIT MODAL
        |--------------------------------------------------------------------------
        */

        let submitModal =
            null;


        const submitModalElement =
            document.getElementById(
                'submitModal'
            );


        if (
            submitModalElement &&
            window.bootstrap &&
            typeof window.bootstrap.Modal ===
                'function'
        ) {

            submitModal =
                new window.bootstrap.Modal(
                    submitModalElement
                );
        }


        function openSubmitDialog() {

            updateSubmitDialog();


            if (
                submitModal
            ) {

                submitModal.show();

                return;
            }


            const confirmed =
                window.confirm(
                    'Are you sure you want to submit this examination?'
                );


            if (
                confirmed
            ) {

                submitExam(
                    false
                );
            }
        }


        /*
        |--------------------------------------------------------------------------
        | BUILD SUBMIT FORM
        |--------------------------------------------------------------------------
        */

        function buildSubmitForm() {

            realSubmitForm
                .querySelectorAll(
                    'input[data-submit-answer="1"]'
                )
                .forEach(
                    function (
                        input
                    ) {

                        input.remove();

                    }
                );


            questions.forEach(
                function (
                    question
                ) {

                    const input =
                        document.createElement(
                            'input'
                        );


                    input.type =
                        'hidden';


                    input.name =
                        'answers[' +
                        String(
                            question.id
                        ) +
                        ']';


                    input.value =
                        getAnswer(
                            question
                        );


                    input.dataset.submitAnswer =
                        '1';


                    realSubmitForm.appendChild(
                        input
                    );
                }
            );
        }


        /*
        |--------------------------------------------------------------------------
        | FINAL SUBMIT
        |--------------------------------------------------------------------------
        */

        async function submitExam(
            autoSubmit
        ) {

            if (
                examSubmitting
            ) {

                return;
            }


            examSubmitting =
                true;


            if (
                timerInterval
            ) {

                clearInterval(
                    timerInterval
                );

                timerInterval =
                    null;
            }


            try {

                /*
                |--------------------------------------------------------------------------
                | Save current answer before final submit.
                |--------------------------------------------------------------------------
                */

                await saveCurrentQuestion();

            } catch (error) {

                /*
                |------------------------------------------------------------------
                | For timeout submission, server is authoritative.
                | For manual submit, do not submit with an unsaved answer silently.
                |------------------------------------------------------------------
                */

                if (
                    !autoSubmit
                ) {

                    examSubmitting =
                        false;


                    showToast(
                        error.message
                    );

                    return;
                }
            }


            buildSubmitForm();


            autoSubmitField.value =
                autoSubmit
                    ? '1'
                    : '0';


            confirmSubmitButton.disabled =
                true;


            openSubmitButton.disabled =
                true;


            if (
                autoSubmit
            ) {

                showToast(
                    'Time is over. Submitting your examination...'
                );

            } else {

                confirmSubmitButton.innerHTML = `

                    <i
                        class="
                            fa-solid
                            fa-spinner
                            fa-spin
                        "
                    ></i>

                    Submitting...

                `;
            }


            window.onbeforeunload =
                null;


            /*
            |--------------------------------------------------------------------------
            | Submit real server form
            |--------------------------------------------------------------------------
            */

            realSubmitForm.submit();
        }


        /*
        |--------------------------------------------------------------------------
        | EXPIRY
        |--------------------------------------------------------------------------
        */

        function handleExpiredExam() {

            if (
                examSubmitting
            ) {

                return;
            }


            submitExam(
                true
            );
        }


        /*
        |--------------------------------------------------------------------------
        | TIMER
        |--------------------------------------------------------------------------
        */

        function updateTimer() {

            if (
                examSubmitting
            ) {

                return;
            }


            const remaining =
                Math.max(
                    0,
                    Math.ceil(
                        (
                            deadline -
                            Date.now()
                        ) / 1000
                    )
                );


            const minutes =
                Math.floor(
                    remaining / 60
                );


            const seconds =
                remaining % 60;


            examTimer.textContent =
                String(
                    minutes
                ).padStart(
                    2,
                    '0'
                ) +
                ':' +
                String(
                    seconds
                ).padStart(
                    2,
                    '0'
                );


            timerBox.classList.remove(
                'warning',
                'danger'
            );


            if (
                remaining <= 60
            ) {

                timerBox.classList.add(
                    'danger'
                );

            } else if (
                remaining <= 300
            ) {

                timerBox.classList.add(
                    'warning'
                );
            }


            if (
                remaining <= 0
            ) {

                handleExpiredExam();
            }
        }


        /*
        |--------------------------------------------------------------------------
        | HEARTBEAT
        |--------------------------------------------------------------------------
        */

        let heartbeatBusy =
            false;


        async function heartbeat() {

            if (
                heartbeatBusy ||
                examSubmitting
            ) {

                return;
            }


            const question =
                getCurrentQuestion();


            if (
                !question
            ) {

                return;
            }


            heartbeatBusy =
                true;


            try {

                await updateStatus(
                    question,
                    'OPEN'
                );

            } catch (error) {

                /*
                |--------------------------------------------------------------------------
                | Silent heartbeat failure.
                | Server-side deadline remains authoritative.
                |--------------------------------------------------------------------------
                */
            } finally {

                heartbeatBusy =
                    false;
            }
        }


        /*
        |--------------------------------------------------------------------------
        | BUTTON EVENTS
        |--------------------------------------------------------------------------
        */

        previousButton.addEventListener(
            'click',
            function () {

                navigateTo(
                    currentIndex - 1
                );

            }
        );


        nextButton.addEventListener(
            'click',
            async function () {

                if (
                    examSubmitting
                ) {

                    return;
                }


                try {

                    await saveCurrentQuestion();

                } catch (error) {

                    showToast(
                        error.message
                    );

                    return;
                }


                if (
                    currentIndex <
                    questions.length - 1
                ) {

                    currentIndex++;


                    renderQuestion();


                    window.scrollTo(
                        {
                            top:
                                0,

                            behavior:
                                'smooth'
                        }
                    );

                } else {

                    openSubmitDialog();
                }

            }
        );


        reviewButton.addEventListener(
            'click',
            function () {

                toggleReview();

            }
        );


        clearButton.addEventListener(
            'click',
            function () {

                clearCurrentResponse();

            }
        );


        openSubmitButton.addEventListener(
            'click',
            function () {

                openSubmitDialog();

            }
        );


        if (
            confirmSubmitButton
        ) {

            confirmSubmitButton.addEventListener(
                'click',
                function () {

                    submitExam(
                        false
                    );

                }
            );
        }


        /*
        |--------------------------------------------------------------------------
        | BEFORE UNLOAD
        |--------------------------------------------------------------------------
        */

        window.onbeforeunload =
            function () {

                if (
                    !examSubmitting
                ) {

                    return 'Your examination is still in progress.';
                }


                return undefined;
            };


        /*
        |--------------------------------------------------------------------------
        | PAGE VISIBILITY
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

                    heartbeat();
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
            function (event) {

                if (
                    examSubmitting
                ) {

                    return;
                }


                if (
                    event.key ===
                    'ArrowLeft'
                ) {

                    if (
                        currentIndex > 0
                    ) {

                        navigateTo(
                            currentIndex - 1
                        );
                    }

                    return;
                }


                if (
                    event.key ===
                    'ArrowRight'
                ) {

                    if (
                        currentIndex <
                        questions.length - 1
                    ) {

                        navigateTo(
                            currentIndex + 1
                        );
                    }
                }
            }
        );


        /*
        |--------------------------------------------------------------------------
        | INITIALIZE
        |--------------------------------------------------------------------------
        */

        renderQuestion();

        updateCounts();

        renderPalette();

        updateTimer();


        timerInterval =
            setInterval(
                updateTimer,
                250
            );


        setInterval(
            heartbeat,
            30000
        );

    }
);