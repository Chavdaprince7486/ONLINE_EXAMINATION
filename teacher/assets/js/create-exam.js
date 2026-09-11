document.addEventListener('DOMContentLoaded', function () {
    var subject = document.getElementById('subjectId'), type = document.getElementById('examType'), schedule = document.getElementById('scheduleWrap'), choices = document.querySelectorAll('.question-choice'), summary = document.getElementById('selectionSummary');
    var questionButton = document.querySelector('.portal-topbar a[href="questions.php"]');
    if (questionButton) {
        var importButton = document.createElement('a');
        importButton.href = 'import_questions.php';
        importButton.className = 'btn btn-outline-secondary me-2';
        importButton.innerHTML = '<i class="fa-solid fa-file-csv me-1"></i>Import CSV';
        questionButton.parentNode.insertBefore(importButton, questionButton);
    }
    function refresh() { var total=0, marks=0, selectedSubject=subject.value; choices.forEach(function(choice){var input=choice.querySelector('input'); var show=!selectedSubject || choice.dataset.subject===selectedSubject; choice.classList.toggle('d-none',!show); if(!show){input.checked=false;} if(input.checked){total++;marks+=parseFloat(input.dataset.marks||0);}}); summary.textContent=total+' selected · Total Marks: '+marks.toFixed(2); }
    subject.addEventListener('change',refresh); document.querySelectorAll('.exam-question').forEach(function(input){input.addEventListener('change',refresh);}); type.addEventListener('change',function(){schedule.classList.toggle('d-none',type.value!=='Live');}); refresh();
});
