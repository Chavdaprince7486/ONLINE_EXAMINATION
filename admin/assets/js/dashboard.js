(() => {
    "use strict";

    const examChart = document.getElementById("examChart");

    if (
        examChart &&
        typeof Chart !== "undefined"
    ) {
        let labels = [];
        let values = [];

        try {
            labels =
                JSON.parse(
                    examChart.dataset.labels || "[]"
                );

            values =
                JSON.parse(
                    examChart.dataset.values || "[]"
                );
        } catch (error) {
            console.error(
                "ExamSphere admin chart data is invalid.",
                error
            );
        }

        if (
            Array.isArray(labels) &&
            Array.isArray(values) &&
            labels.length === values.length &&
            labels.length > 0
        ) {
            new Chart(
                examChart,
                {
                    type: "line",

                    data: {
                        labels: labels,

                        datasets: [
                            {
                                label: "Exams",

                                data: values,

                                borderWidth: 3,

                                tension: 0.4,

                                fill: true
                            }
                        ]
                    },

                    options: {
                        responsive: true,

                        maintainAspectRatio: false,

                        plugins: {
                            legend: {
                                display: false
                            }
                        },

                        scales: {
                            y: {
                                beginAtZero: true,

                                ticks: {
                                    precision: 0
                                }
                            }
                        }
                    }
                }
            );
        }
    }

    const todayDate =
        document.getElementById(
            "today-date"
        );

    const todayDay =
        document.getElementById(
            "today-day"
        );

    const todayMonth =
        document.getElementById(
            "today-month"
        );

    const now =
        new Date();

    if (todayDate) {
        todayDate.textContent =
            String(
                now.getDate()
            );
    }

    if (todayDay) {
        todayDay.textContent =
            now.toLocaleDateString(
                undefined,
                {
                    weekday: "long"
                }
            );
    }

    if (todayMonth) {
        todayMonth.textContent =
            now.toLocaleDateString(
                undefined,
                {
                    month: "long",
                    year: "numeric"
                }
            );
    }

    const sidebar =
        document.getElementById(
            "sidebar"
        );

    const desktopToggle =
        document.getElementById(
            "toggleSidebar"
        );

    const mobileToggle =
        document.getElementById(
            "menuToggle"
        );

    const sidebarOverlay =
        document.getElementById(
            "sidebarOverlay"
        );

    const mainContent =
        document.querySelector(
            ".main-content"
        );

    if (
        desktopToggle &&
        sidebar
    ) {
        desktopToggle.addEventListener(
            "click",
            function () {
                sidebar.classList.toggle(
                    "hide"
                );

                if (mainContent) {
                    mainContent.classList.toggle(
                        "expand"
                    );
                }
            }
        );
    }

    if (
        mobileToggle &&
        sidebar
    ) {
        mobileToggle.addEventListener(
            "click",
            function () {
                sidebar.classList.toggle(
                    "show"
                );

                if (sidebarOverlay) {
                    sidebarOverlay.classList.toggle(
                        "show"
                    );
                }
            }
        );
    }

    if (
        sidebarOverlay &&
        sidebar
    ) {
        sidebarOverlay.addEventListener(
            "click",
            function () {
                sidebar.classList.remove(
                    "show"
                );

                sidebarOverlay.classList.remove(
                    "show"
                );
            }
        );
    }

    window.addEventListener(
        "resize",
        function () {
            if (
                window.innerWidth > 992 &&
                sidebar &&
                sidebarOverlay
            ) {
                sidebar.classList.remove(
                    "show"
                );

                sidebarOverlay.classList.remove(
                    "show"
                );
            }
        }
    );

    document
        .querySelectorAll(
            ".stat-top h2"
        )
        .forEach(
            function (counter) {
                const raw =
                    String(
                        counter.textContent || ""
                    )
                    .replace(
                        /,/g,
                        ""
                    )
                    .trim();

                const target =
                    Number(
                        raw
                    );

                if (
                    !Number.isFinite(target) ||
                    target <= 0 ||
                    target > 1000000
                ) {
                    return;
                }

                let current = 0;

                const step =
                    Math.max(
                        1,
                        Math.ceil(
                            target / 40
                        )
                    );

                const animate = () => {
                    current += step;

                    if (
                        current >= target
                    ) {
                        counter.textContent =
                            target.toLocaleString();

                        return;
                    }

                    counter.textContent =
                        Math.floor(
                            current
                        ).toLocaleString();

                    window.requestAnimationFrame(
                        animate
                    );
                };

                animate();
            }
        );
})();