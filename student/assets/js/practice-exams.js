(() => {

    'use strict';

    const form =
        document.getElementById(
            'practiceFilterForm'
        );

    const searchInput =
        form?.querySelector(
            'input[name="search"]'
        );

    const subjectSelect =
        form?.querySelector(
            'select[name="subject_id"]'
        );

    const clearButton =
        document.querySelector(
            '.practice-clear-btn'
        );

    const grid =
        document.getElementById(
            'practiceExamGrid'
        );

    const cards =
        Array.from(
            document.querySelectorAll(
                '.practice-card'
            )
        );

    const countElement =
        document.getElementById(
            'practiceResultCount'
        );

    let searchTimer =
        null;


    function normalize(
        value
    ) {

        return String(
            value ??
            ''
        )
            .trim()
            .toLocaleLowerCase();

    }


    function updateVisibleCount() {

        if (
            !countElement
        ) {

            return;

        }

        const visible =
            cards.filter(
                (
                    card
                ) =>
                    !card.hidden
            ).length;

        countElement.textContent =
            String(
                visible
            );

    }


    function filterLocalCards() {

        const query =
            normalize(
                searchInput
                    ?.value
            );

        cards.forEach(
            (
                card
            ) => {

                const text =
                    normalize(
                        card.dataset.search
                        ||
                        card.textContent
                    );

                card.hidden =
                    (
                        query !== ''
                        &&
                        !text.includes(
                            query
                        )
                    );

            }
        );

        updateVisibleCount();

        if (
            grid
        ) {

            grid.classList.toggle(
                'no-visible-cards',
                (
                    cards.length > 0
                    &&
                    cards.every(
                        (
                            card
                        ) =>
                            card.hidden
                    )
                )
            );

        }

    }


    function submitForm() {

        if (
            form
        ) {

            form.submit();

        }

    }


    if (
        searchInput
    ) {

        searchInput.addEventListener(
            'input',
            () => {

                filterLocalCards();

                window.clearTimeout(
                    searchTimer
                );

                searchTimer =
                    window.setTimeout(
                        submitForm,
                        700
                    );

            }
        );


        searchInput.addEventListener(
            'keydown',
            (
                event
            ) => {

                if (
                    event.key ===
                    'Escape'
                ) {

                    event.preventDefault();

                    searchInput.value =
                        '';

                    filterLocalCards();

                    submitForm();

                }

            }
        );

    }


    if (
        subjectSelect
    ) {

        subjectSelect.addEventListener(
            'change',
            submitForm
        );

    }


    if (
        clearButton
    ) {

        clearButton.addEventListener(
            'click',
            (
                event
            ) => {

                event.preventDefault();

                window.location.href =
                    'practice_exams.php';

            }
        );

    }


    document
        .querySelectorAll(
            '.practice-btn[href*="start_exam.php"]'
        )
        .forEach(
            (
                button
            ) => {

                button.addEventListener(
                    'click',
                    () => {

                        if (
                            button.dataset.loading
                            ===
                            '1'
                        ) {

                            return;

                        }

                        button.dataset.loading =
                            '1';

                        button.setAttribute(
                            'aria-busy',
                            'true'
                        );

                        button.classList.add(
                            'loading'
                        );

                        button.innerHTML =
                            '<i class="fa-solid fa-spinner fa-spin"></i> Opening...';

                    }
                );

            }
        );


    cards.forEach(
        (
            card
        ) => {

            card.addEventListener(
                'keydown',
                (
                    event
                ) => {

                    if (
                        event.key !==
                        'Enter'
                    ) {

                        return;

                    }

                    if (
                        event.target.closest(
                            'a,button,input,select,textarea'
                        )
                    ) {

                        return;

                    }

                    const action =
                        card.querySelector(
                            'a[href*="start_exam.php"]'
                        );

                    if (
                        action
                    ) {

                        action.click();

                    }

                }
            );

        }
    );


    filterLocalCards();

})();