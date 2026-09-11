(() => {
    "use strict";

    const searchInput =
        document.getElementById(
            "examSearch"
        );

    const table =
        document.getElementById(
            "examTable"
        );

    if (
        !searchInput ||
        !table
    ) {
        return;
    }

    const rows =
        Array.from(
            table.querySelectorAll(
                "tbody tr"
            )
        );

    const emptyRow =
        document.createElement(
            "tr"
        );

    emptyRow.innerHTML =
        '<td colspan="7" class="text-center text-muted py-5">No exams match your search.</td>';

    function applyFilter(
        value
    ) {

        const query =
            String(
                value || ""
            )
            .trim()
            .toLocaleLowerCase();

        let visibleRows =
            0;

        rows.forEach(
            function (row) {

                if (
                    row === emptyRow
                ) {
                    return;
                }

                const text =
                    String(
                        row.textContent || ""
                    )
                    .toLocaleLowerCase();

                const visible =
                    query === "" ||
                    text.includes(
                        query
                    );

                row.hidden =
                    !visible;

                if (
                    visible
                ) {
                    visibleRows++;
                }

            }
        );

        if (
            visibleRows === 0 &&
            rows.length > 0
        ) {

            if (
                !emptyRow.isConnected
            ) {

                table
                    .querySelector(
                        "tbody"
                    )
                    ?.appendChild(
                        emptyRow
                    );

            }

        } else if (
            emptyRow.isConnected
        ) {

            emptyRow.remove();

        }

    }

    searchInput.addEventListener(
        "input",
        function () {

            applyFilter(
                searchInput.value
            );

        }
    );

    searchInput.addEventListener(
        "keydown",
        function (
            event
        ) {

            if (
                event.key ===
                "Escape"
            ) {

                searchInput.value =
                    "";

                applyFilter(
                    ""
                );

                searchInput.focus();

            }

        }
    );

    applyFilter(
        searchInput.value
    );

})();