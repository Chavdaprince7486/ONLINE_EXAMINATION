(() => {
    "use strict";

    const negativeCheckbox =
        document.getElementById(
            "negative_marking"
        );

    const negativeGroup =
        document.getElementById(
            "negative_marks_group"
        );

    const negativeMarksInput =
        document.querySelector(
            '[name="negative_marks"]'
        );

    if (
        negativeCheckbox &&
        negativeGroup
    ) {
        const syncNegativeMarking =
            function () {
                const enabled =
                    negativeCheckbox.checked;

                negativeGroup.style.display =
                    enabled
                        ? ""
                        : "none";

                if (
                    !enabled &&
                    negativeMarksInput
                ) {
                    negativeMarksInput.value =
                        "0.00";
                }
            };

        negativeCheckbox.addEventListener(
            "change",
            syncNegativeMarking
        );

        syncNegativeMarking();
    }

    const totalMarksInput =
        document.querySelector(
            '[name="total_marks"]'
        );

    const passingMarksInput =
        document.querySelector(
            '[name="passing_marks"]'
        );

    if (
        totalMarksInput &&
        passingMarksInput
    ) {
        const validatePassingMarks =
            function () {
                const totalMarks =
                    Number.parseFloat(
                        totalMarksInput.value
                    ) || 0;

                const passingMarks =
                    Number.parseFloat(
                        passingMarksInput.value
                    ) || 0;

                if (
                    passingMarks > totalMarks
                ) {
                    window.alert(
                        "Passing Marks cannot be greater than Total Marks."
                    );

                    passingMarksInput.value =
                        "";

                    passingMarksInput.focus();
                }
            };

        passingMarksInput.addEventListener(
            "change",
            validatePassingMarks
        );

        passingMarksInput.addEventListener(
            "input",
            function () {
                if (
                    Number.parseFloat(
                        passingMarksInput.value
                    ) >
                    Number.parseFloat(
                        totalMarksInput.value
                    )
                ) {
                    passingMarksInput.setCustomValidity(
                        "Passing Marks cannot be greater than Total Marks."
                    );
                } else {
                    passingMarksInput.setCustomValidity(
                        ""
                    );
                }
            }
        );
    }

    const startDateInput =
        document.querySelector(
            '[name="start_datetime"], [name="starts_at"]'
        );

    const endDateInput =
        document.querySelector(
            '[name="end_datetime"], [name="ends_at"]'
        );

    if (
        startDateInput &&
        endDateInput
    ) {
        const validateExamDates =
            function () {
                if (
                    !startDateInput.value ||
                    !endDateInput.value
                ) {
                    endDateInput.setCustomValidity(
                        ""
                    );

                    return;
                }

                const startDate =
                    new Date(
                        startDateInput.value
                    );

                const endDate =
                    new Date(
                        endDateInput.value
                    );

                if (
                    Number.isNaN(
                        startDate.getTime()
                    ) ||
                    Number.isNaN(
                        endDate.getTime()
                    )
                ) {
                    endDateInput.setCustomValidity(
                        ""
                    );

                    return;
                }

                if (
                    endDate <= startDate
                ) {
                    endDateInput.setCustomValidity(
                        "End Date & Time must be greater than Start Date & Time."
                    );
                } else {
                    endDateInput.setCustomValidity(
                        ""
                    );
                }
            };

        startDateInput.addEventListener(
            "change",
            validateExamDates
        );

        endDateInput.addEventListener(
            "change",
            validateExamDates
        );
    }

    const examForm =
        document.querySelector(
            "form"
        );

    if (examForm) {
        examForm.addEventListener(
            "submit",
            function (event) {
                const possibleNames = [
                    "exam_title",
                    "title"
                ];

                let valid = true;

                possibleNames.some(
                    function (name) {
                        const field =
                            document.querySelector(
                                `[name="${name}"]`
                            );

                        if (!field) {
                            return false;
                        }

                        if (
                            String(
                                field.value || ""
                            ).trim() === ""
                        ) {
                            field.setCustomValidity(
                                "This field is required."
                            );

                            valid = false;
                        } else {
                            field.setCustomValidity(
                                ""
                            );
                        }

                        return true;
                    }
                );

                if (!valid) {
                    event.preventDefault();

                    window.alert(
                        "Please complete the required exam title."
                    );

                    return;
                }

                const submitButton =
                    document.getElementById(
                        "saveExamBtn"
                    );

                if (
                    submitButton &&
                    !submitButton.disabled
                ) {
                    submitButton.disabled =
                        true;

                    submitButton.innerHTML =
                        '<i class="fa-solid fa-spinner fa-spin"></i> Saving...';
                }
            }
        );
    }
})();