document.addEventListener('DOMContentLoaded', function () {
    const search = document.getElementById('questionSearch');
    const levelFilter = document.getElementById('questionDifficultyFilter');
    const rows = document.querySelectorAll('#questionTable tbody tr:not(.subject-empty)');

    function applyFilters() {
        const term = search ? search.value.trim().toLowerCase() : '';
        const level = levelFilter ? levelFilter.value : '';
        rows.forEach(function (row) {
            const matchesSearch = term === '' || row.textContent.toLowerCase().includes(term);
            const matchesLevel = level === '' || row.dataset.difficulty === level;
            row.hidden = !(matchesSearch && matchesLevel);
        });
    }

    if (search) search.addEventListener('input', applyFilters);
    if (levelFilter) levelFilter.addEventListener('change', applyFilters);

    document.querySelectorAll('.delete-confirm').forEach(function (form) {
        form.addEventListener('submit', function (event) {
            if (!window.confirm('Delete this question? It cannot be restored.')) {
                event.preventDefault();
            }
        });
    });
});
