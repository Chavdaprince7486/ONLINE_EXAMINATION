document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.delete-confirm').forEach(function (form) {
        form.addEventListener('submit', function (event) {
            if (!window.confirm('Delete this study material? The uploaded file will also be removed.')) {
                event.preventDefault();
            }
        });
    });
});
