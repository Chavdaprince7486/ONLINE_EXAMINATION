document.addEventListener('DOMContentLoaded', function () {
    'use strict';

    /*
     * ==========================================
     * Exam Listing Filters
     * ==========================================
     */

    const examSearch = document.getElementById('examSearch');
    const examModeFilter = document.getElementById('examModeFilter');

    const examRows = document.querySelectorAll(
        '#examTable tbody tr[data-mode]'
    );

    function applyExamFilters() {
        const searchTerm = examSearch
            ? examSearch.value.trim().toLowerCase()
            : '';

        const selectedMode = examModeFilter
            ? examModeFilter.value
            : '';

        examRows.forEach(function (row) {

            const rowText =
                row.textContent.toLowerCase();

            const rowMode =
                row.dataset.mode || '';

            const matchesSearch =
                searchTerm === '' ||
                rowText.includes(searchTerm);

            const matchesMode =
                selectedMode === '' ||
                rowMode === selectedMode;

            row.hidden =
                !(matchesSearch && matchesMode);
        });
    }

    if (examSearch) {
        examSearch.addEventListener(
            'input',
            applyExamFilters
        );
    }

    if (examModeFilter) {
        examModeFilter.addEventListener(
            'change',
            applyExamFilters
        );
    }


    /*
     * ==========================================
     * Category -> Subject AJAX
     * ==========================================
     *
     * Actual exam form fields:
     * category_id
     * subject_id
     *
     * The project already provides:
     * admin/exams/get_subjects.php
     */

    const categorySelect =
        document.getElementById('category_id');

    const subjectSelect =
        document.getElementById('subject_id');

    if (categorySelect && subjectSelect) {

        async function loadSubjects(categoryId) {

            subjectSelect.disabled = true;

            subjectSelect.innerHTML =
                '<option value="">Loading subjects...</option>';

            if (!categoryId) {

                subjectSelect.innerHTML =
                    '<option value="">Select Subject</option>';

                subjectSelect.disabled = false;

                return;
            }

            try {

                const response = await fetch(
                    'get_subjects.php?category_id=' +
                    encodeURIComponent(categoryId),
                    {
                        method: 'GET',
                        headers: {
                            'X-Requested-With':
                                'XMLHttpRequest'
                        },
                        credentials: 'same-origin'
                    }
                );

                if (!response.ok) {
                    throw new Error(
                        'Unable to load subjects.'
                    );
                }

                const html =
                    await response.text();

                subjectSelect.innerHTML =
                    html.trim() !== ''
                        ? html
                        : '<option value="">No subjects available</option>';

            } catch (error) {

                console.error(
                    'Subject loading failed:',
                    error
                );

                subjectSelect.innerHTML =
                    '<option value="">Unable to load subjects</option>';

            } finally {

                subjectSelect.disabled = false;
            }
        }

        categorySelect.addEventListener(
            'change',
            function () {
                loadSubjects(this.value);
            }
        );

        /*
         * Do not automatically reload an already-populated
         * subject dropdown on initial page load.
         */
    }


    /*
     * ==========================================
     * Exam Type UI
     * ==========================================
     */

    const examType =
        document.querySelector(
            '[name="exam_type"]'
        );

    const liveOnlyFields =
        document.querySelectorAll(
            '.live-only'
        );

    function updateExamTypeVisibility() {

        if (!examType) {
            return;
        }

        const isLive =
            examType.value === 'Live';

        liveOnlyFields.forEach(
            function (field) {

                field.hidden = !isLive;

            }
        );
    }

    if (examType) {

        examType.addEventListener(
            'change',
            updateExamTypeVisibility
        );

        updateExamTypeVisibility();
    }


    /*
     * ==========================================
     * Numeric Exam Validation
     * ==========================================
     */

    const totalQuestions =
        document.querySelector(
            '[name="total_questions"]'
        );

    const totalMarks =
        document.querySelector(
            '[name="total_marks"]'
        );

    const passingMarks =
        document.querySelector(
            '[name="passing_marks"]'
        );

    const duration =
        document.querySelector(
            '[name="duration"]'
        );

    function positiveInteger(value) {

        const number =
            Number(value);

        return Number.isInteger(number) &&
            number > 0;
    }

    function validateExamNumbers() {

        let valid = true;

        if (
            totalQuestions &&
            !positiveInteger(
                totalQuestions.value
            )
        ) {
            valid = false;
        }

        if (
            totalMarks &&
            !positiveInteger(
                totalMarks.value
            )
        ) {
            valid = false;
        }

        if (
            passingMarks &&
            !positiveInteger(
                passingMarks.value
            )
        ) {
            valid = false;
        }

        if (
            duration &&
            !positiveInteger(
                duration.value
            )
        ) {
            valid = false;
        }

        if (
            totalMarks &&
            passingMarks &&
            positiveInteger(totalMarks.value) &&
            positiveInteger(passingMarks.value) &&
            Number(passingMarks.value) >
                Number(totalMarks.value)
        ) {
            passingMarks.setCustomValidity(
                'Passing marks cannot exceed total marks.'
            );

            valid = false;

        } else if (passingMarks) {

            passingMarks.setCustomValidity('');
        }

        return valid;
    }

    [
        totalQuestions,
        totalMarks,
        passingMarks,
        duration
    ].forEach(function (field) {

        if (!field) {
            return;
        }

        field.addEventListener(
            'input',
            validateExamNumbers
        );

        field.addEventListener(
            'change',
            validateExamNumbers
        );
    });


    /*
     * ==========================================
     * Prevent Invalid Decimal/Negative Values
     * ==========================================
     */

    [
        totalQuestions,
        totalMarks,
        passingMarks,
        duration
    ].forEach(function (field) {

        if (!field) {
            return;
        }

        field.addEventListener(
            'input',
            function () {

                const value =
                    this.value;

                if (
                    value !== '' &&
                    Number(value) < 0
                ) {
                    this.value = '';
                }
            }
        );
    });


    /*
     * ==========================================
     * Exam Form Submission
     * ==========================================
     */

    const examForm =
        document.querySelector(
            'form[data-exam-form]'
        ) ||
        document.querySelector(
            'form.exam-form'
        );

    const saveExamButton =
        document.getElementById(
            'save_ExamBtn'
        );

    if (examForm && saveExamButton) {

        examForm.addEventListener(
            'submit',
            function (event) {

                validateExamNumbers();

                if (!examForm.checkValidity()) {

                    event.preventDefault();

                    examForm.classList.add(
                        'was-validated'
                    );

                    return;
                }

                saveExamButton.disabled = true;

                saveExamButton.innerHTML =
                    '<i class="fa-solid fa-spinner fa-spin"></i> ' +
                    'Saving Exam...';
            }
        );
    }


    /*
     * ==========================================
     * Delete Confirmation
     * ==========================================
     */

    document
        .querySelectorAll(
            '.delete-confirm'
        )
        .forEach(function (element) {

            /*
             * Support both:
             * - forms
             * - anchor links
             */

            if (
                element.tagName.toLowerCase() ===
                'form'
            ) {

                element.addEventListener(
                    'submit',
                    function (event) {

                        const confirmed =
                            window.confirm(
                                'Delete this exam? This action cannot be undone.'
                            );

                        if (!confirmed) {
                            event.preventDefault();
                        }
                    }
                );

            } else {

                element.addEventListener(
                    'click',
                    function (event) {

                        const confirmed =
                            window.confirm(
                                'Delete this exam? This action cannot be undone.'
                            );

                        if (!confirmed) {
                            event.preventDefault();
                        }
                    }
                );
            }
        });


    /*
     * ==========================================
     * Generic Exam Field Formatting
     * ==========================================
     */

    const percentageInputs =
        document.querySelectorAll(
            '[data-exam-number]'
        );

    percentageInputs.forEach(
        function (field) {

            field.addEventListener(
                'keydown',
                function (event) {

                    const blocked =
                        [
                            'e',
                            'E',
                            '+',
                            '-'
                        ];

                    if (
                        blocked.includes(
                            event.key
                        )
                    ) {
                        event.preventDefault();
                    }
                }
            );
        }
    );


    /*
     * ==========================================
     * Initial Validation State
     * ==========================================
     */

    validateExamNumbers();

});