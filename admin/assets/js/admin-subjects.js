document.addEventListener('DOMContentLoaded', function () {
    const search = document.getElementById('subjectSearch');
    const rows = document.querySelectorAll('#subjectTable tbody tr:not(.subject-empty)');

    if (!search) return;

    search.addEventListener('input', function () {
        const term = this.value.trim().toLowerCase();
        rows.forEach(function (row) {
            row.hidden = term !== '' && !row.textContent.toLowerCase().includes(term);
        });
    });
});
