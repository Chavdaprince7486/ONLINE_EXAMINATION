(function () {
    'use strict';

    const WIDGET =
        '[data-notification-widget]';

    const REFRESH_MS =
        15000;


    function widget() {

        return document.querySelector(
            WIDGET
        );
    }


    function baseUrl() {

        const value =
            window.EXAMSPHERE_NOTIFICATION_BASE
            ||
            '/ONLINE_EXAMINATION/';

        return String(value)
            .replace(/\/+$/, '') + '/';
    }


    function url(path) {

        return (
            baseUrl()
            +
            String(path)
                .replace(/^\/+/, '')
        );
    }


    function csrf() {

        return widget()?.dataset.csrf
            || '';
    }


    function esc(value) {

        return String(
            value ?? ''
        )
        .replace(
            /&/g,
            '&amp;'
        )
        .replace(
            /</g,
            '&lt;'
        )
        .replace(
            />/g,
            '&gt;'
        )
        .replace(
            /"/g,
            '&quot;'
        )
        .replace(
            /'/g,
            '&#039;'
        );
    }


    function icon(type) {

        const icons = {

            exam:
                'fa-file-lines',

            result:
                'fa-chart-column',

            material:
                'fa-book-open',

            subscription:
                'fa-crown',

            payment:
                'fa-credit-card',

            message:
                'fa-envelope',

            announcement:
                'fa-bullhorn',

            important:
                'fa-circle-exclamation',

            subject:
                'fa-book',

            category:
                'fa-layer-group',

            topic:
                'fa-tags',

            question:
                'fa-circle-question',

            system:
                'fa-bell'
        };


        return (
            'fa-solid '
            +
            (
                icons[
                    String(
                        type ||
                        'system'
                    ).toLowerCase()
                ]
                ||
                'fa-bell'
            )
        );
    }


    function dateText(value) {

        const date =
            new Date(
                String(
                    value || ''
                ).replace(
                    ' ',
                    'T'
                )
            );


        if (
            Number.isNaN(
                date.getTime()
            )
        ) {
            return esc(
                value || ''
            );
        }


        return new Intl.DateTimeFormat(
            undefined,
            {
                day: '2-digit',
                month: 'short',
                year: 'numeric',
                hour: '2-digit',
                minute: '2-digit'
            }
        ).format(date);
    }


    function setBadge(number) {

        const badge =
            document.getElementById(
                'esNotificationBadge'
            );


        if (!badge) {
            return;
        }


        const count =
            Math.max(
                0,
                Number(number) || 0
            );


        badge.hidden =
            count === 0;


        badge.textContent =
            count > 99
                ? '99+'
                : String(count);


        const old =
            document.getElementById(
                'notificationCount'
            );


        if (old) {

            old.hidden =
                count === 0;

            old.textContent =
                count > 99
                    ? '99+'
                    : String(count);
        }
    }


    function render(items) {

        const list =
            document.getElementById(
                'esNotificationList'
            );


        if (!list) {
            return;
        }


        if (
            !Array.isArray(items)
            ||
            !items.length
        ) {

            list.innerHTML =
                `
                <div
                    class="es-notification-empty"
                >

                    <div>

                        <i
                            class="
                                fa-regular
                                fa-bell-slash
                            "
                        ></i>

                        <div>
                            No notifications yet.
                        </div>

                    </div>

                </div>
                `;

            return;
        }


        list.innerHTML =
            items
                .map(function (notification) {

                    const unread =
                        Number(
                            notification.is_read
                        ) === 0;


                    const href =
                        notification.link
                            ? esc(
                                notification.link
                            )
                            : '#';


                    return `
                    <a
                        href="${href}"
                        class="
                            es-notification-item
                            ${
                                unread
                                    ? 'is-unread'
                                    : ''
                            }
                        "
                        data-id="${
                            Number(
                                notification.id
                            ) || 0
                        }"
                        data-type="${esc(
                            notification
                                .notification_type
                            ||
                            'system'
                        )}"
                    >

                        <div
                            class="
                                es-notification-icon
                            "
                        >

                            <i
                                class="${esc(
                                    icon(
                                        notification
                                            .notification_type
                                    )
                                )}"
                            ></i>

                        </div>


                        <div
                            class="
                                es-notification-content
                            "
                        >

                            <h5
                                class="
                                    es-notification-title
                                "
                            >
                                ${esc(
                                    notification.title
                                )}
                            </h5>


                            <p
                                class="
                                    es-notification-message
                                "
                            >
                                ${esc(
                                    notification.message
                                )}
                            </p>


                            <small
                                class="
                                    es-notification-time
                                "
                            >
                                ${dateText(
                                    notification.created_at
                                )}
                            </small>

                        </div>

                    </a>
                    `;

                })
                .join('');
    }


    async function json(
        path,
        options = {}
    ) {

        const response =
            await fetch(
                url(path),
                {
                    credentials:
                        'same-origin',

                    cache:
                        'no-store',

                    ...options
                }
            );


        const data =
            await response
                .json()
                .catch(
                    function () {
                        return {
                            status:
                                'error',

                            message:
                                'Invalid server response.'
                        };
                    }
                );


        if (
            !response.ok
            ||
            data.status === 'error'
        ) {

            throw new Error(
                data.message
                ||
                'Request failed'
            );
        }


        return data;
    }


    async function load() {

        if (!widget()) {
            return;
        }


        try {

            const data =
                await json(
                    'api/notification/list.php'
                );


            setBadge(
                data.unread_count || 0
            );


            render(
                data.notifications || []
            );

        } catch (error) {

            console.error(
                'ExamSphere notification load:',
                error
            );
        }
    }


    async function mark(id) {

        if (
            !id ||
            !csrf()
        ) {
            return;
        }


        const form =
            new FormData();


        form.append(
            'notification_id',
            String(id)
        );


        form.append(
            'csrf_token',
            csrf()
        );


        try {

            await json(
                'api/notification/read.php',
                {
                    method:
                        'POST',

                    body:
                        form
                }
            );

        } catch (error) {

            console.error(
                error
            );
        }
    }


    async function markAll() {

        if (!csrf()) {
            return;
        }


        const form =
            new FormData();


        form.append(
            'csrf_token',
            csrf()
        );


        try {

            const data =
                await json(
                    'api/notification/mark-all-read.php',
                    {
                        method:
                            'POST',

                        body:
                            form
                    }
                );


            setBadge(
                data.unread_count || 0
            );


            document
                .querySelectorAll(
                    '.es-notification-item.is-unread'
                )
                .forEach(
                    function (item) {

                        item.classList.remove(
                            'is-unread'
                        );

                    }
                );

        } catch (error) {

            console.error(
                error
            );
        }
    }


    function init() {

        const widgetElement =
            widget();


        if (!widgetElement) {
            return;
        }


        const bell =
            document.getElementById(
                'esNotificationBell'
            );


        const dropdown =
            document.getElementById(
                'esNotificationDropdown'
            );


        const list =
            document.getElementById(
                'esNotificationList'
            );


        const all =
            document.getElementById(
                'esNotificationMarkAll'
            );


        if (
            bell &&
            dropdown
        ) {

            bell.addEventListener(
                'click',
                function (event) {

                    event.preventDefault();
                    event.stopPropagation();


                    dropdown.hidden =
                        !dropdown.hidden;


                    if (
                        !dropdown.hidden
                    ) {

                        load();
                    }
                }
            );
        }


        if (all) {

            all.addEventListener(
                'click',
                function (event) {

                    event.preventDefault();
                    event.stopPropagation();


                    markAll();
                }
            );
        }


        if (list) {

            list.addEventListener(
                'click',
                async function (event) {

                    const item =
                        event.target.closest(
                            '.es-notification-item'
                        );


                    if (!item) {
                        return;
                    }


                    const id =
                        Number(
                            item.dataset.id
                        ) || 0;


                    if (
                        item.classList.contains(
                            'is-unread'
                        )
                    ) {

                        item.classList.remove(
                            'is-unread'
                        );


                        await mark(id);


                        setTimeout(
                            load,
                            200
                        );
                    }
                }
            );
        }


        document.addEventListener(
            'click',
            function (event) {

                if (
                    dropdown
                    &&
                    !widgetElement.contains(
                        event.target
                    )
                ) {

                    dropdown.hidden =
                        true;
                }
            }
        );


        document.addEventListener(
            'keydown',
            function (event) {

                if (
                    event.key === 'Escape'
                    &&
                    dropdown
                ) {

                    dropdown.hidden =
                        true;
                }
            }
        );


        /*
         * Initial notification load.
         */
        load();


        /*
         * Automatic refresh every 15 seconds.
         */
        setInterval(
            load,
            REFRESH_MS
        );
    }


    if (
        document.readyState ===
        'loading'
    ) {

        document.addEventListener(
            'DOMContentLoaded',
            init,
            {
                once: true
            }
        );

    } else {

        init();
    }

})();