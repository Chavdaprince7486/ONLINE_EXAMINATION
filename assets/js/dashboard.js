document.addEventListener("DOMContentLoaded", function () {

    const bell = document.getElementById("notificationBell");
    const dropdown = document.getElementById("notificationDropdown");

    if (!bell || !dropdown) return;

    bell.addEventListener("click", function (e) {

        e.stopPropagation();

        dropdown.classList.toggle("show");

    });

    document.addEventListener("click", function () {

        dropdown.classList.remove("show");

    });

    dropdown.addEventListener("click", function (e) {

        e.stopPropagation();

    });

});