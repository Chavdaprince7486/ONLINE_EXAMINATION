/*=========================================
    MY EXAMS JAVASCRIPT
=========================================*/

document.addEventListener("DOMContentLoaded", function () {

    /*==============================
        AUTO SUBMIT SEARCH
    ==============================*/

    const searchBox = document.querySelector("input[name='search']");

    if (searchBox) {

        let timer = null;

        searchBox.addEventListener("keyup", function () {

            clearTimeout(timer);

            timer = setTimeout(function () {

                searchBox.closest("form").submit();

            }, 600);

        });

    }

    /*==============================
        FILTER CHANGE
    ==============================*/

    const status = document.querySelector("select[name='status']");

    if (status) {

        status.addEventListener("change", function () {

            this.closest("form").submit();

        });

    }

    /*==============================
        CARD HOVER
    ==============================*/

    const cards = document.querySelectorAll(".exam-card");

    cards.forEach(function(card){

        card.addEventListener("mouseenter",function(){

            this.style.transform="translateY(-8px)";

        });

        card.addEventListener("mouseleave",function(){

            this.style.transform="translateY(0px)";

        });

    });

    /*==============================
        BUTTON LOADING
    ==============================*/

    document.querySelectorAll(".exam-actions a").forEach(function(btn){

        btn.addEventListener("click",function(){

            this.innerHTML='<i class="fa-solid fa-spinner fa-spin"></i> Loading...';

        });

    });

});