'use strict';

document.addEventListener('DOMContentLoaded', function () {
    const config = window.EXAMSPHERE_EXAM || null;

    if (!config || !Array.isArray(config.questions) || !config.questions.length) {
        return;
    }

    const questions = config.questions;

    let currentIndex = 0;
    let saveTimer = null;
    let timerInterval = null;
    let submitting = false;
    let autoSubmitting = false;

    const pending = new Set();

    const $ = function (id) {
        return document.getElementById(id);
    };

    const questionContainer = $('questionContainer');
    const questionNumber = $('questionNumber');
    const questionProgress = $('questionProgress');
    const previousButton = $('previousButton');
    const reviewButton = $('reviewButton');
    const clearButton = $('clearButton');
    const nextButton = $('nextButton');
    const palette = $('questionPalette');
    const answeredCount = $('answeredCount');
    const timerBox = $('timerBox');
    const examTimer = $('examTimer');
    const submitModal = $('submitModal');
    const openSubmitButton = $('openSubmitButton');
    const confirmSubmitButton = $('confirmSubmitButton');
    const dialogAnswered = $('dialogAnswered');
    const dialogUnanswered = $('dialogUnanswered');
    const dialogReviewed = $('dialogReviewed');
    const submitWarning = $('submitWarning');
    const examToast = $('examToast');
    const realSubmitForm = $('realSubmitForm');
    const autoSubmitField = $('autoSubmitField');

    const validStatuses = [
        'Not Visited',
        'Not Answered',
        'Answered',
        'Marked for Review',
        'Answered & Marked for Review'
    ];

    function normalizeStatus(value) {
        value = String(value || '').trim();

        return validStatuses.includes(value)
            ? value
            : 'Not Visited';
    }

    questions.forEach(function (question) {
        question.selected_answer = String(
            question.answer ||
            question.selected_answer ||
            ''
        )
            .trim()
            .toUpperCase();

        question.question_status = normalizeStatus(
            question.status ||
            question.question_status
        );

        question._visited =
            question.question_status !== 'Not Visited'
                ? 1
                : 0;
    });

    function currentQuestion() {
        return questions[currentIndex] || null;
    }

    function isAnswered(question) {
        return [
            'A',
            'B',
            'C',
            'D'
        ].includes(
            String(
                question.selected_answer || ''
            ).toUpperCase()
        );
    }

    function isReviewed(question) {
        return (
            question.question_status ===
                'Marked for Review' ||

            question.question_status ===
                'Answered & Marked for Review'
        );
    }

    function calculateStatus(question) {
        const answered =
            isAnswered(question);

        const reviewed =
            isReviewed(question);

        if (
            answered &&
            reviewed
        ) {
            return 'Answered & Marked for Review';
        }

        if (answered) {
            return 'Answered';
        }

        if (reviewed) {
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

    function escapeHtml(value) {
        const div =
            document.createElement('div');

        div.textContent =
            String(value ?? '');

        return div.innerHTML;
    }

    function showToast(
        message,
        type = 'normal'
    ) {
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
        }

        if (
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

        clearTimeout(
            showToast._timer
        );

        showToast._timer =
            setTimeout(
                function () {
                    examToast.classList.remove(
                        'show'
                    );
                },
                2500
            );
    }

    function getCounts() {
        const counts = {
            notVisited: 0,
            notAnswered: 0,
            answered: 0,
            reviewed: 0
        };

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
                    counts.notVisited++;
                }

                else if (
                    status ===
                    'Not Answered'
                ) {
                    counts.notAnswered++;
                }

                else if (
                    status ===
                    'Answered'
                ) {
                    counts.answered++;
                }

                else if (
                    status ===
                        'Marked for Review' ||

                    status ===
                        'Answered & Marked for Review'
                ) {
                    counts.reviewed++;
                }
            }
        );

        return counts;
    }

    function updateCounts() {
        const counts =
            getCounts();

        let answered =
            counts.answered;

        questions.forEach(
            function (question) {

                if (
                    question.question_status ===
                    'Answered & Marked for Review'
                ) {
                    answered++;
                }
            }
        );

        if (answeredCount) {
            answeredCount.textContent =
                String(answered);
        }

        refreshPalette();
    }

    function buildPalette() {
        if (!palette) {
            return;
        }

        palette.innerHTML = '';

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
                    'question-palette-item';

                button.dataset.index =
                    String(index);

                button.textContent =
                    String(index + 1);

                button.addEventListener(
                    'click',
                    function () {
                        navigateTo(index);
                    }
                );

                palette.appendChild(
                    button
                );
            }
        );

        refreshPalette();
    }

    function refreshPalette() {
        if (!palette) {
            return;
        }

        palette
            .querySelectorAll(
                '.question-palette-item'
            )
            .forEach(
                function (
                    button,
                    index
                ) {
                    const question =
                        questions[index];

                    const status =
                        calculateStatus(
                            question
                        );

                    button.classList.remove(
                        'current',
                        'not-visited',
                        'not-answered',
                        'answered',
                        'reviewed',
                        'answered-reviewed'
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
                        'Not Visited'
                    ) {
                        button.classList.add(
                            'not-visited'
                        );
                    }

                    else if (
                        status ===
                        'Not Answered'
                    ) {
                        button.classList.add(
                            'not-answered'
                        );
                    }

                    else if (
                        status ===
                        'Answered'
                    ) {
                        button.classList.add(
                            'answered'
                        );
                    }

                    else if (
                        status ===
                        'Marked for Review'
                    ) {
                        button.classList.add(
                            'reviewed'
                        );
                    }

                    else {
                        button.classList.add(
                            'answered-reviewed'
                        );
                    }
                }
            );
    }

    function updateNavigationButtons() {
        if (previousButton) {
            previousButton.disabled =
                currentIndex === 0 ||
                submitting;
        }

        if (nextButton) {
            nextButton.disabled =
                submitting;
        }

        if (reviewButton) {
            const question =
                currentQuestion();

            reviewButton.disabled =
                submitting;

            reviewButton.innerHTML =
                isReviewed(question)
                    ? '<i class="fa-solid fa-bookmark"></i> Unmark Review'
                    : '<i class="fa-regular fa-bookmark"></i> Mark for Review';
        }

        if (clearButton) {
            clearButton.disabled =
                submitting ||
                !isAnswered(
                    currentQuestion()
                );
        }
    }

    function renderQuestion() {
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

        if (
            calculateStatus(question) ===
            'Not Visited'
        ) {
            question.question_status =
                'Not Answered';
        }

        if (questionNumber) {
            questionNumber.textContent =
                String(
                    currentIndex + 1
                );
        }

        if (questionProgress) {
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

        const options =
            question.options &&
            typeof question.options ===
                'object'
                ? question.options
                : {};

        const entries =
            Object.entries(
                options
            ).filter(
                function (
                    entry
                ) {
                    return (
                        String(
                            entry[1] ?? ''
                        ).trim() !== ''
                    );
                }
            );

        let html = '';

        html +=
            '<div class="question-heading-row">';

        html +=
            '<span class="question-difficulty">' +
            escapeHtml(
                question.difficulty ||
                'Medium'
            ) +
            '</span>';

        html +=
            '<span class="question-marks">' +
            escapeHtml(
                question.marks ?? 0
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

        if (question.image) {
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

        entries.forEach(
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
                    '<label class="question-option' +
                    (
                        selected
                            ? ' selected'
                            : ''
                    ) +
                    '">';

                html +=
                    '<input ' +
                    'type="radio" ' +
                    'name="question_' +
                    escapeHtml(
                        question.id
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
                        question.id
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

    function attachOptionHandlers() {
        if (!questionContainer) {
            return;
        }

        questionContainer
            .querySelectorAll(
                'input[type="radio"]'
            )
            .forEach(
                function (
                    input
                ) {
                    input.addEventListener(
                        'change',
                        function () {

                            const question =
                                currentQuestion();

                            if (
                                !question ||
                                submitting
                            ) {
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

    function renderSelectedOptionState() {
        const question =
            currentQuestion();

        if (!questionContainer) {
            return;
        }

        questionContainer
            .querySelectorAll(
                '.question-option'
            )
            .forEach(
                function (
                    option
                ) {
                    const radio =
                        option.querySelector(
                            'input'
                        );

                    option.classList.toggle(
                        'selected',
                        !!(
                            radio &&
                            radio.checked
                        )
                    );
                }
            );

        const statusText =
            questionContainer.querySelector(
                '.question-status-text'
            );

        if (
            statusText &&
            question
        ) {
            statusText.textContent =
                calculateStatus(
                    question
                );
        }

        updateNavigationButtons();
    }

    function markPending(question) {
        if (question) {
            pending.add(
                Number(
                    question.id
                )
            );
        }
    }

    function clearPending(question) {
        if (question) {
            pending.delete(
                Number(
                    question.id
                )
            );
        }
    }

    function makeBody(
        question,
        extra = {}
    ) {
        const body =
            new URLSearchParams();

        body.append(
            'attempt_id',
            String(
                config.attemptId
            )
        );

        if (question) {
            body.append(
                'question_id',
                String(
                    question.id
                )
            );
        }

        if (question) {
            body.append(
                'selected_answer',
                String(
                    question.selected_answer ||
                    ''
                )
            );
        }

        if (question) {
            body.append(
                'question_status',
                calculateStatus(
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
            extra
        ).forEach(
            function (
                entry
            ) {
                body.append(
                    entry[0],
                    String(
                        entry[1]
                    )
                );
            }
        );

        return body;
    }

    async function parseJson(
        response
    ) {
        const text =
            await response.text();

        if (!text) {
            return {};
        }

        try {
            return JSON.parse(
                text
            );
        }

        catch (error) {
            throw new Error(
                'Invalid server response.'
            );
        }
    }

    async function post(
        url,
        body
    ) {
        const response =
            await fetch(
                url,
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
            const error =
                new Error(
                    data?.message ||
                    'Request failed.'
                );

            error.data =
                data;

            throw error;
        }

        return data;
    }

    function scheduleSave() {
        clearTimeout(
            saveTimer
        );

        saveTimer =
            setTimeout(
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
    ) {
        const question =
            currentQuestion();

        if (
            !question ||
            submitting
        ) {
            return false;
        }

        try {
            const data =
                await post(
                    'ajax/save_answer.php',
                    makeBody(
                        question
                    )
                );

            question.question_status =
                normalizeStatus(
                    data.question_status ||
                    calculateStatus(
                        question
                    )
                );

            clearPending(
                question
            );

            updateCounts();

            if (showMessage) {
                showToast(
                    'Answer saved.',
                    'success'
                );
            }

            return true;
        }

        catch (error) {
            console.error(
                error
            );

            markPending(
                question
            );

            if (
                error.data &&
                error.data.expired
            ) {
                showToast(
                    'The examination time has expired.',
                    'error'
                );

                autoSubmit();
            }

            else if (showMessage) {
                showToast(
                    error.message ||
                    'Unable to save your answer.',
                    'error'
                );
            }

            return false;
        }
    }

    async function saveStatusOpen(
        question
    ) {
        if (
            !question ||
            submitting
        ) {
            return;
        }

        try {
            const data =
                await post(
                    'ajax/update_status.php',
                    makeBody(
                        question,
                        {
                            action:
                                'OPEN'
                        }
                    )
                );

            question.question_status =
                normalizeStatus(
                    data.question_status ||
                    calculateStatus(
                        question
                    )
                );

            question._visited =
                1;

            renderSelectedOptionState();
            updateCounts();
        }

        catch (error) {
            console.error(
                error
            );
        }
    }

    async function toggleReview() {
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

        try {
            const data =
                await post(
                    'ajax/update_status.php',
                    makeBody(
                        question,
                        {
                            action:
                                reviewed
                                    ? 'UNMARK'
                                    : 'MARK'
                        }
                    )
                );

            question.question_status =
                normalizeStatus(
                    data.question_status ||
                    calculateStatus(
                        question
                    )
                );

            question._visited =
                1;

            renderSelectedOptionState();
            updateCounts();

            showToast(
                reviewed
                    ? 'Review mark removed.'
                    : 'Question marked for review.',
                'success'
            );
        }

        catch (error) {
            showToast(
                error.message ||
                'Unable to update review status.',
                'error'
            );
        }
    }

    async function clearCurrentAnswer() {
        const question =
            currentQuestion();

        if (
            !question ||
            submitting ||
            !isAnswered(
                question
            )
        ) {
            return;
        }

        if (
            !window.confirm(
                'Clear your answer for this question?'
            )
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
            const data =
                await post(
                    'ajax/clear_response.php',
                    body
                );

            question.selected_answer =
                '';

            question._visited =
                1;

            question.question_status =
                normalizeStatus(
                    data.question_status ||
                    'Not Answered'
                );

            renderQuestion();

            showToast(
                'Answer cleared.',
                'success'
            );
        }

        catch (error) {
            showToast(
                error.message ||
                'Unable to clear your answer.',
                'error'
            );
        }
    }

    async function navigateTo(
        index
    ) {
        if (
            submitting ||
            index < 0 ||
            index >= questions.length ||
            index === currentIndex
        ) {
            return;
        }

        await saveCurrentQuestion(
            false
        );

        currentIndex =
            index;

        const question =
            currentQuestion();

        if (question) {
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

        await saveStatusOpen(
            question
        );
    }

    async function saveAndNext() {
        if (submitting) {
            return;
        }

        const saved =
            await saveCurrentQuestion(
                true
            );

        if (!saved) {
            return;
        }

        if (
            currentIndex <
            questions.length - 1
        ) {
            await navigateTo(
                currentIndex + 1
            );
        }

        else {
            showToast(
                'You are on the last question.',
                'normal'
            );
        }
    }

    async function goPrevious() {
        if (
            submitting ||
            currentIndex <= 0
        ) {
            return;
        }

        await saveCurrentQuestion(
            false
        );

        await navigateTo(
            currentIndex - 1
        );
    }

    function getSubmitModal() {
        if (
            !submitModal ||
            typeof window.bootstrap ===
                'undefined'
        ) {
            return null;
        }

        return window.bootstrap.Modal.getOrCreateInstance(
            submitModal
        );
    }

    function openSubmitDialog() {
        const counts =
            getCounts();

        const unanswered =
            counts.notVisited +
            counts.notAnswered;

        let answered =
            counts.answered;

        questions.forEach(
            function (
                question
            ) {
                if (
                    question.question_status ===
                    'Answered & Marked for Review'
                ) {
                    answered++;
                }
            }
        );

        if (dialogAnswered) {
            dialogAnswered.textContent =
                String(
                    answered
                );
        }

        if (dialogUnanswered) {
            dialogUnanswered.textContent =
                String(
                    unanswered
                );
        }

        if (dialogReviewed) {
            dialogReviewed.textContent =
                String(
                    counts.reviewed
                );
        }

        if (submitWarning) {
            submitWarning.textContent =
                unanswered
                    ? 'You still have ' +
                      String(
                          unanswered
                      ) +
                      ' unanswered question(s).'
                    : 'All questions have been answered.';
        }

        const modal =
            getSubmitModal();

        if (modal) {
            modal.show();
        }

        else if (
            window.confirm(
                unanswered
                    ? 'You have ' +
                      String(
                          unanswered
                      ) +
                      ' unanswered question(s). Submit anyway?'
                    : 'Are you sure you want to submit the examination?'
            )
        ) {
            submitExam();
        }
    }

    function fallbackSubmitForm(
        autoSubmit = false
    ) {
        if (!realSubmitForm) {
            return;
        }

        if (autoSubmitField) {
            autoSubmitField.value =
                autoSubmit
                    ? '1'
                    : '0';
        }

        realSubmitForm.submit();
    }

    async function retryPendingSaves() {
        if (
            !pending.size ||
            submitting
        ) {
            return;
        }

        for (
            let index = 0;
            index < questions.length;
            index++
        ) {
            const question =
                questions[index];

            if (
                pending.has(
                    Number(
                        question.id
                    )
                )
            ) {
                await saveQuestionDirect(
                    question
                );
            }
        }
    }

    async function saveQuestionDirect(
        question
    ) {
        try {
            const data =
                await post(
                    'ajax/save_answer.php',
                    makeBody(
                        question
                    )
                );

            question.question_status =
                normalizeStatus(
                    data.question_status ||
                    calculateStatus(
                        question
                    )
                );

            clearPending(
                question
            );

            return true;
        }

        catch (error) {
            markPending(
                question
            );

            return false;
        }
    }

    async function saveAllAnswers() {
        const failures = [];

        for (
            const question of
            questions
        ) {
            const ok =
                await saveQuestionDirect(
                    question
                );

            if (!ok) {
                failures.push(
                    Number(
                        question.id
                    )
                );
            }
        }

        updateCounts();

        return failures;
    }

    async function submitExam() {
        if (submitting) {
            return;
        }

        submitting =
            true;

        clearTimeout(
            saveTimer
        );

        if (timerInterval) {
            clearInterval(
                timerInterval
            );
        }

        if (confirmSubmitButton) {
            confirmSubmitButton.disabled =
                true;

            confirmSubmitButton.innerHTML =
                '<i class="fa-solid fa-spinner fa-spin"></i> Submitting...';
        }

        try {
            await saveCurrentQuestion(
                false
            );

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

            const data =
                await post(
                    'ajax/submit_exam.php',
                    body
                );

            const redirect =
                data.redirect
                    ? String(
                        data.redirect
                    )
                    : 'result.php?id=' +
                      encodeURIComponent(
                          data.result_id ||
                          ''
                      );

            window.location.href =
                redirect;
        }

        catch (error) {
            console.error(
                error
            );

            submitting =
                false;

            if (confirmSubmitButton) {
                confirmSubmitButton.disabled =
                    false;

                confirmSubmitButton.innerHTML =
                    'Submit now <i class="fa-solid fa-check"></i>';
            }

            showToast(
                'Online submission failed. Retrying through secure form submission.',
                'error'
            );

            fallbackSubmitForm(
                false
            );
        }
    }

    async function autoSubmit() {
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

        clearTimeout(
            saveTimer
        );

        if (timerInterval) {
            clearInterval(
                timerInterval
            );
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
                '1'
            );

            const data =
                await post(
                    'ajax/submit_exam.php',
                    body
                );

            window.location.href =
                data.redirect
                    ? String(
                        data.redirect
                    )
                    : 'result.php?id=' +
                      encodeURIComponent(
                          data.result_id ||
                          ''
                      );
        }

        catch (error) {
            console.error(
                error
            );

            fallbackSubmitForm(
                true
            );
        }
    }

    function formatTime(
        seconds
    ) {
        seconds =
            Math.max(
                0,
                Number(seconds) || 0
            );

        const hours =
            Math.floor(
                seconds / 3600
            );

        const minutes =
            Math.floor(
                (seconds % 3600) / 60
            );

        const secs =
            Math.floor(
                seconds % 60
            );

        if (hours > 0) {
            return (
                String(
                    hours
                ).padStart(
                    2,
                    '0'
                ) +
                ':' +
                String(
                    minutes
                ).padStart(
                    2,
                    '0'
                ) +
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
            ) +
            ':' +
            String(
                secs
            ).padStart(
                2,
                '0'
            )
        );
    }

    let timerWarned =
        false;

    function updateTimer() {
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

        const remaining =
            Math.max(
                0,
                Math.floor(
                    (
                        deadline -
                        Date.now()
                    ) / 1000
                )
            );

        if (examTimer) {
            examTimer.textContent =
                formatTime(
                    remaining
                );
        }

        if (timerBox) {
            timerBox.classList.toggle(
                'timer-warning',
                remaining <= 300
            );

            timerBox.classList.toggle(
                'timer-danger',
                remaining <= 60
            );
        }

        if (
            remaining <= 60 &&
            !timerWarned &&
            remaining > 0
        ) {
            timerWarned =
                true;

            showToast(
                'Less than one minute remaining.',
                'error'
            );
        }

        if (
            remaining <= 0
        ) {
            if (timerInterval) {
                clearInterval(
                    timerInterval
                );
            }

            showToast(
                'Time is over. Submitting examination...',
                'error'
            );

            setTimeout(
                autoSubmit,
                250
            );
        }
    }

    if (previousButton) {
        previousButton.addEventListener(
            'click',
            goPrevious
        );
    }

    if (nextButton) {
        nextButton.addEventListener(
            'click',
            saveAndNext
        );
    }

    if (reviewButton) {
        reviewButton.addEventListener(
            'click',
            toggleReview
        );
    }

    if (clearButton) {
        clearButton.addEventListener(
            'click',
            clearCurrentAnswer
        );
    }

    if (openSubmitButton) {
        openSubmitButton.addEventListener(
            'click',
            openSubmitDialog
        );
    }

    if (confirmSubmitButton) {
        confirmSubmitButton.addEventListener(
            'click',
            submitExam
        );
    }

    window.addEventListener(
        'online',
        function () {
            showToast(
                'Connection restored. Synchronizing saved answers...',
                'success'
            );

            retryPendingSaves();
        }
    );

    window.addEventListener(
        'offline',
        function () {
            showToast(
                'Connection lost. Unsynchronized changes will be retried when you reconnect.',
                'error'
            );
        }
    );

    window.addEventListener(
        'beforeunload',
        function () {

            if (submitting) {
                return;
            }

            const question =
                currentQuestion();

            if (
                !question ||
                !navigator.sendBeacon
            ) {
                return;
            }

            const body =
                makeBody(
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
                'ArrowRight'
            ) {
                event.preventDefault();
                saveAndNext();
            }

            else if (
                event.key ===
                'ArrowLeft'
            ) {
                event.preventDefault();
                goPrevious();
            }

            else if (
                event.key.toLowerCase() ===
                'r'
            ) {
                event.preventDefault();
                toggleReview();
            }
        }
    );

    buildPalette();

    renderQuestion();

    saveStatusOpen(
        currentQuestion()
    );

    updateCounts();

    updateTimer();

    timerInterval =
        setInterval(
            updateTimer,
            1000
        );
});