/*=========================================================
   EXAMSPHERE STUDENT DASHBOARD JS
=========================================================*/

"use strict";


/* =========================================================
   CURRENT TIME / DATE
========================================================= */

function updateClock() {

    const now =
        new Date();

    const timeElement =
        document.getElementById(
            "currentTime"
        );

    const dateElement =
        document.getElementById(
            "currentDate"
        );

    if (timeElement) {

        timeElement.textContent =
            now.toLocaleTimeString();

    }

    if (dateElement) {

        dateElement.textContent =
            now.toDateString();

    }

}


updateClock();


window.setInterval(
    updateClock,
    1000
);


/* =========================================================
   PERFORMANCE CHART
========================================================= */

const performanceCanvas =
    document.getElementById(
        "performanceChart"
    );


if (

    performanceCanvas &&

    typeof Chart !== "undefined"

) {

    const chartAccuracy =
        Number(
            performanceCanvas.dataset.accuracy
            || 0
        );

    const safeAccuracy =
        Math.max(
            0,
            Math.min(
                100,
                chartAccuracy
            )
        );

    const remaining =
        Math.max(
            0,
            100 - safeAccuracy
        );


    new Chart(
        performanceCanvas,
        {

            type:
                "doughnut",

            data:
                {

                    datasets:
                        [

                            {

                                data:
                                    [

                                        safeAccuracy,

                                        remaining

                                    ],

                                backgroundColor:
                                    [

                                        "#8B5A2B",

                                        "#EFE7DE"

                                    ],

                                borderWidth:
                                    0

                            }

                        ]

                },

            options:
                {

                    responsive:
                        true,

                    maintainAspectRatio:
                        true,

                    cutout:
                        "78%",

                    plugins:
                        {

                            legend:
                                {

                                    display:
                                        false

                                },

                            tooltip:
                                {

                                    enabled:
                                        false

                                }

                        }

                }

        }

    );

}


/* =========================================================
   NOTIFICATION ELEMENTS
========================================================= */

const notificationBell =
    document.getElementById(
        "notificationBell"
    );

const notificationDropdown =
    document.getElementById(
        "notificationDropdown"
    );

const notificationBody =
    document.getElementById(
        "notificationBody"
    );

const notificationCount =
    document.getElementById(
        "notificationCount"
    );

const csrfToken =
    document.querySelector(
        'meta[name="csrf-token"]'
    )?.getAttribute(
        "content"
    ) || "";


/* =========================================================
   REQUEST CONTROL
========================================================= */

let notificationRequestRunning =
    false;


/* =========================================================
   DROPDOWN TOGGLE
========================================================= */

if (

    notificationBell &&

    notificationDropdown

) {

    notificationBell.addEventListener(

        "click",

        function (event) {

            event.preventDefault();

            event.stopPropagation();

            notificationDropdown.classList.toggle(
                "show"
            );


            if (

                notificationDropdown.classList.contains(
                    "show"
                )

            ) {

                loadNotifications();

            }

        }

    );

}


/* =========================================================
   KEEP DROPDOWN OPEN INSIDE
========================================================= */

if (

    notificationDropdown

) {

    notificationDropdown.addEventListener(

        "click",

        function (event) {

            event.stopPropagation();

        }

    );

}


/* =========================================================
   CLOSE DROPDOWN OUTSIDE CLICK
========================================================= */

document.addEventListener(

    "click",

    function () {

        if (

            notificationDropdown

        ) {

            notificationDropdown.classList.remove(
                "show"
            );

        }

    }

);


/* =========================================================
   LOAD NOTIFICATIONS
========================================================= */

async function loadNotifications() {

    if (

        notificationRequestRunning

    ) {

        return;

    }


    notificationRequestRunning =
        true;


    try {

        const response =
            await fetch(

                "../student/ajax/load_notifications.php",

                {

                    method:
                        "GET",

                    cache:
                        "no-store",

                    credentials:
                        "same-origin",

                    headers:
                        {

                            "Accept":
                                "application/json"

                        }

                }

            );


        if (

            !response.ok

        ) {

            throw new Error(
                "Notification request failed."
            );

        }


        const data =
            await response.json();


        if (

            !data ||

            data.status !== "success"

        ) {

            throw new Error(
                data?.message ||
                "Unable to load notifications."
            );

        }


        /*==================================================
            UNREAD BADGE
        ==================================================*/

        if (

            notificationCount

        ) {

            const count =
                Math.max(

                    0,

                    Number(
                        data.unread_count ||
                        0
                    )

                );


            notificationCount.textContent =
                String(count);


            notificationCount.style.display =
                count > 0
                    ? "flex"
                    : "none";

        }


        /*==================================================
            NOTIFICATION BODY
        ==================================================*/

        if (

            notificationBody

        ) {

            notificationBody.innerHTML =
                data.html ||
                '<div class="notification-loading">No notifications found.</div>';

        }


    } catch (error) {

        console.error(

            "ExamSphere notification error:",

            error

        );


        if (

            notificationBody

        ) {

            notificationBody.innerHTML =
                '<div class="notification-loading">Unable to load notifications.</div>';

        }

    } finally {

        notificationRequestRunning =
            false;

    }

}


/* =========================================================
   INITIAL LOAD
========================================================= */

if (

    notificationBody ||

    notificationCount

) {

    loadNotifications();

}


/* =========================================================
   AUTO REFRESH
========================================================= */

window.setInterval(

    loadNotifications,

    30000

);


/* =========================================================
   MARK NOTIFICATION AS READ
========================================================= */

document.addEventListener(

    "click",

    async function (event) {

        const item =
            event.target.closest(
                ".notification-item"
            );


        if (!item) {

            return;

        }


        event.preventDefault();


        const notificationId =
            item.dataset.id ||
            "";


        const destination =
            item.getAttribute(
                "href"
            ) || "#";


        if (!notificationId) {

            if (

                destination !== "#"

            ) {

                window.location.assign(
                    destination
                );

            }

            return;

        }


        const bodyData =
            new URLSearchParams();


        bodyData.append(

            "notification_id",

            notificationId

        );


        bodyData.append(

            "csrf_token",

            csrfToken

        );


        try {

            const response =
                await fetch(

                    "../student/ajax/mark_notification_read.php",

                    {

                        method:
                            "POST",

                        credentials:
                            "same-origin",

                        headers:
                            {

                                "Content-Type":
                                    "application/x-www-form-urlencoded; charset=UTF-8",

                                "Accept":
                                    "application/json"

                            },

                        body:
                            bodyData.toString()

                    }

                );


            if (!response.ok) {

                throw new Error(
                    "Unable to mark notification."
                );

            }


            const data =
                await response.json();


            if (

                data &&

                data.status !== "success"

            ) {

                throw new Error(

                    data.message ||

                    "Unable to mark notification."

                );

            }


            item.classList.remove(
                "unread"
            );


            await loadNotifications();


        } catch (error) {

            console.error(

                "Mark notification error:",

                error

            );

        }


        if (

            destination !== "#"

        ) {

            window.location.assign(
                destination
            );

        }

    }

);


/* =========================================================
   COUNTER ANIMATION
========================================================= */

document

    .querySelectorAll(
        ".stat-info h2"
    )

    .forEach(

        function (counter) {

            const target =
                parseFloat(
                    counter.textContent
                );


            if (

                Number.isNaN(
                    target
                ) ||

                target <= 0

            ) {

                return;

            }


            let value =
                0;


            const steps =
                40;


            const speed =
                target / steps;


            function updateCounter() {

                value +=
                    speed;


                if (

                    value >=
                    target

                ) {

                    counter.textContent =
                        Number.isInteger(
                            target
                        )

                            ? target

                            : target.toFixed(
                                1
                            );

                    return;

                }


                counter.textContent =
                    String(
                        Math.floor(
                            value
                        )
                    );


                requestAnimationFrame(
                    updateCounter
                );

            }


            updateCounter();

        }

    );


/* =========================================================
   SMOOTH CARD ANIMATION
========================================================= */

const dashboardCards =
    document.querySelectorAll(

        ".stat-card, .dashboard-card"

    );


if (

    "IntersectionObserver"
    in window

) {

    const cardObserver =
        new IntersectionObserver(

            function (
                entries
            ) {

                entries.forEach(

                    function (
                        entry
                    ) {

                        if (

                            entry.isIntersecting

                        ) {

                            entry.target.style.opacity =
                                "1";


                            entry.target.style.transform =
                                "translateY(0)";


                            cardObserver.unobserve(
                                entry.target
                            );

                        }

                    }

                );

            },

            {

                threshold:
                    0.15

            }

        );


    dashboardCards.forEach(

        function (card) {

            card.style.opacity =
                "0";


            card.style.transform =
                "translateY(20px)";


            card.style.transition =
                "opacity .5s ease, transform .5s ease";


            cardObserver.observe(
                card
            );

        }

    );

} else {

    dashboardCards.forEach(

        function (card) {

            card.style.opacity =
                "1";


            card.style.transform =
                "translateY(0)";

        }

    );

}