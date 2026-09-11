document.addEventListener('DOMContentLoaded', function () {
    const picker = document.getElementById('managerPicker');
    const count = document.getElementById('managerCount');
    const hint = document.getElementById('managerHint');
    if (!picker || !count) return;
    const required = Number((count.textContent.split('/')[1] || '').trim());
    function refresh() {
        const selected = picker.querySelectorAll('input:checked').length;
        count.textContent = selected + ' / ' + required;
        count.className = 'status-pill ' + (selected === required ? 'active' : 'inactive');
        hint.textContent = selected === required ? 'Perfect — this exam is ready to publish.' : 'Select exactly ' + required + ' questions. ' + Math.max(required - selected, 0) + ' remaining.';
    }
    picker.addEventListener('change', function (event) {
        if (event.target.checked && picker.querySelectorAll('input:checked').length > required) {
            event.target.checked = false;
            window.alert('This exam requires exactly ' + required + ' questions.');
        }
        refresh();
    });
    refresh();
});
