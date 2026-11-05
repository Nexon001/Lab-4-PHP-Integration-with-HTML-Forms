document.addEventListener('DOMContentLoaded', function () {

    // 1. Confirm before deleting a task
    document.querySelectorAll('.delete-form').forEach(function (form) {
        form.addEventListener('submit', function (event) {
            var task = form.closest('.task');
            var name = task.querySelector('.task-body strong').textContent;
            if (!confirm('Delete task "' + name + '"?')) {
                event.preventDefault();
            } else {
                task.classList.add('removing');
            }
        });
    });

    // 2. Confirm before deleting a folder (its tasks are deleted too)
    document.querySelectorAll('.delete-folder-form').forEach(function (form) {
        form.addEventListener('submit', function (event) {
            var folder = form.closest('.folder');
            var name = folder.querySelector('.folder-name').textContent;
            if (!confirm('Delete folder "' + name + '" and all its tasks?')) {
                event.preventDefault();
            }
        });
    });

    // 3. Filter tasks (All / Not done / Done) without reloading the page
    var filterButtons = document.querySelectorAll('.filter');
    filterButtons.forEach(function (button) {
        button.addEventListener('click', function () {
            var filter = button.dataset.filter;

            filterButtons.forEach(function (b) { b.classList.remove('is-active'); });
            button.classList.add('is-active');

            document.querySelectorAll('.task').forEach(function (task) {
                var isDone = task.classList.contains('is-done');
                task.hidden = (filter === 'done' && !isDone) || (filter === 'undone' && isDone);
            });
        });
    });

    // 4. Live character counter for the description field
    document.querySelectorAll('.counter').forEach(function (counter) {
        var input = document.getElementById(counter.dataset.for);
        var max = Number(counter.dataset.max);

        function update() {
            counter.textContent = input.value.length + ' / ' + max;
        }

        input.addEventListener('input', update);
        update();
    });

    // 5. Fade out the success message after a few seconds
    var flash = document.querySelector('.flash');
    if (flash) {
        setTimeout(function () {
            flash.style.opacity = '0';
            setTimeout(function () { flash.remove(); }, 400);
        }, 3000);
    }

    // 6. Focus the right field: the edit field, or "task name" when the add form opens
    var addTask = document.getElementById('add-task');
    var nameField = document.getElementById('task_name');

    if (addTask) {
        addTask.addEventListener('toggle', function () {
            if (addTask.open) {
                nameField.focus();
            }
        });
    }

    var editField = document.querySelector('.task .task-form input[type="text"]');
    if (editField) {
        editField.focus();
    } else if (addTask && addTask.open) {
        nameField.focus();
    }
});