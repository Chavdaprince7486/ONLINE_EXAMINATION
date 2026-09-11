(() => {
    "use strict";

    const filterForm =
        document.querySelector(
            ".practice-filter-form"
        );

    const searchInput =
        document.querySelector(
            'input[name="search"]'
        );

    const subjectSelect =
        document.querySelector(
            'select[name="subject_id"]'
        );

    const clearButton =
        document.querySelector(
            ".practice-clear-btn"
        );

    const cards =
        Array.from(
            document.querySelectorAll(
                ".practice-exam-card"
            )
        );

    const resultCount =
        document.querySelector(
            ".practice-results-count span"
        );

    const grid =
        document.querySelector(
            ".practice-exam-grid"
        );

    let searchTimer = null;

    function normalize(value) {
        return String(
            value ?? ""
        )
            .trim()
            .toLocaleLowerCase();
    }

    function setResultCount(count) {
        if (resultCount) {
            resultCount.textContent =
                String(count);
        }
    }

    function filterCards() {
        if (!cards.length) {
            setResultCount(0);
            return;
        }

        const query =
            normalize(
                searchInput
                    ? searchInput.value
                    : ""
            );

        let visibleCount = 0;

        cards.forEach(
            function (card) {
                const text =
                    normalize(
                        card.textContent
                    );

                const matches =
                    query === "" ||
                    text.includes(
                        query
                    );

                card.hidden =
                    !matches;

                if (matches) {
                    visibleCount += 1;
                }
            }
        );

        setResultCount(
            visibleCount
        );

        if (grid) {
            grid.classList.toggle(
                "has-filtered-results",
                visibleCount > 0
            );

            grid.classList.toggle(
                "has-no-filtered-results",
                visibleCount === 0
            );
        }
    }

    function submitFilters() {
        if (!filterForm) {
            return;
        }

        filterForm.submit();
    }

    if (searchInput) {

        searchInput.addEventListener(
            "input",
            function () {

                filterCards();

                window.clearTimeout(
                    searchTimer
                );

                searchTimer =
                    window.setTimeout(
                        submitFilters,
                        650
                    );

            }
        );

        searchInput.addEventListener(
            "keydown",
            function (event) {

                if (
                    event.key === "Escape"
                ) {

                    event.preventDefault();

                    searchInput.value =
                        "";

                    filterCards();

                    submitFilters();

                }

            }
        );

    }

    if (subjectSelect) {

        subjectSelect.addEventListener(
            "change",
            function () {

                submitFilters();

            }
        );

    }

    if (clearButton) {

        clearButton.addEventListener(
            "click",
            function (event) {

                event.preventDefault();

                window.location.assign(
                    "practice_exams.php"
                );

            }
        );

    }

    cards.forEach(
        function (card) {

            const startButton =
                card.querySelector(
                    ".practice-start-btn"
                );

            if (startButton) {

                startButton.addEventListener(
                    "click",
                    function () {

                        if (
                            startButton.dataset.loading ===
                            "1"
                        ) {
                            return;
                        }

                        startButton.dataset.loading =
                            "1";

                        startButton.setAttribute(
                            "aria-busy",
                            "true"
                        );

                        startButton.classList.add(
                            "is-loading"
                        );

                        startButton.innerHTML =
                            '<i class="fa-solid fa-spinner fa-spin" aria-hidden="true"></i> Opening...';

                    }
                );

            }

            card.addEventListener(
                "keydown",
                function (event) {

                    if (
                        event.key !==
                        "Enter"
                    ) {
                        return;
                    }

                    if (
                        event.target.closest(
                            "a, button, input, select, textarea"
                        )
                    ) {
                        return;
                    }

                    if (
                        startButton
                    ) {

                        startButton.click();

                    }

                }
            );

        }
    );

    filterCards();

})();