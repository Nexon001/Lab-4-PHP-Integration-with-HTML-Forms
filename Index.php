<?php
/* =========================================================
   Dito 'yung PHP Logic natin mga Busseng, 'di pwedeng wala busseng.
   ========================================================= */
session_start();


$_SESSION['folders']        = $_SESSION['folders']        ?? [['id' => 1, 'name' => 'My Tasks', 'tasks' => []]];
$_SESSION['next_folder_id'] = $_SESSION['next_folder_id'] ?? 2;
$_SESSION['next_task_id']   = $_SESSION['next_task_id']   ?? 1;

$errors       = [];   // task validation messages
$folderErrors = [];   // folder validation messages
$old          = ['task_name' => '', 'task_description' => ''];
$oldFolder    = '';
$editId       = null;

/* ---------- Helper functions mga idol ---------- */


function e(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

/* Array index of a folder by id, or null */
function findFolder(int $id): ?int
{
    foreach ($_SESSION['folders'] as $index => $folder) {
        if ($folder['id'] === $id) {
            return $index;
        }
    }
    return null;
}

/* Array index of a task inside a folder, or null */
function findTask(int $folderIndex, int $taskId): ?int
{
    foreach ($_SESSION['folders'][$folderIndex]['tasks'] as $index => $task) {
        if ($task['id'] === $taskId) {
            return $index;
        }
    }
    return null;
}

function countDone(array $tasks): int
{
    return count(array_filter($tasks, fn($t) => $t['done']));
}

function validateTask(array $input, array &$errors): array
{
    $name = trim($input['task_name'] ?? '');
    $desc = trim($input['task_description'] ?? '');

    if ($name === '') {
        $errors[] = 'Task name cannot be empty.';
    } elseif (strlen($name) < 3) {
        $errors[] = 'Task name must be at least 3 characters long.';
    } elseif (strlen($name) > 60) {
        $errors[] = 'Task name cannot exceed 60 characters.';
    }

    if ($desc === '') {
        $errors[] = 'Task description is required.';
    } elseif (strlen($desc) > 255) {
        $errors[] = 'Task description cannot exceed 255 characters.';
    }

    return ['task_name' => $name, 'task_description' => $desc];
}

function validateFolderName(string $input, array &$errors): string
{
    $name = trim($input);

    if ($name === '') {
        $errors[] = 'Folder name cannot be empty.';
    } elseif (strlen($name) < 2) {
        $errors[] = 'Folder name must be at least 2 characters long.';
    } elseif (strlen($name) > 30) {
        $errors[] = 'Folder name cannot exceed 30 characters.';
    }

    return $name;
}

/* Redirect after a successful action so refreshing doesn't resubmit the form */
function redirectTo(int $folderId, ?string $flash = null): void
{
    if ($flash !== null) {
        $_SESSION['flash'] = $flash;
    }
    header('Location: index.php?folder=' . $folderId);
    exit();
}

/* ---------- Work out which folder is open ---------- */
$activeId = (int) ($_POST['folder_id'] ?? $_GET['folder'] ?? $_SESSION['active_folder'] ?? 0);
if (findFolder($activeId) === null) {
    $activeId = $_SESSION['folders'][0]['id'];
}
$_SESSION['active_folder'] = $activeId;

/* ---------- Handle form submissions ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $taskId = (int) ($_POST['id'] ?? 0);
    $fi     = findFolder($activeId); // index of the open folder

    switch ($action) {

        /* ----- Folders ----- */
        case 'create_folder':
            $name = validateFolderName($_POST['folder_name'] ?? '', $folderErrors);
            if (empty($folderErrors)) {
                $newId = $_SESSION['next_folder_id']++;
                $_SESSION['folders'][] = ['id' => $newId, 'name' => $name, 'tasks' => []];
                redirectTo($newId, 'Folder created.');
            }
            $oldFolder = $name;
            break;

        case 'delete_folder':
            if (count($_SESSION['folders']) <= 1) {
                $folderErrors[] = 'You need at least one folder.';
            } else {
                array_splice($_SESSION['folders'], $fi, 1);
                redirectTo($_SESSION['folders'][0]['id'], 'Folder deleted.');
            }
            break;

        /* ----- Tasks (always inside the open folder) ----- */
        case 'create':
            $clean = validateTask($_POST, $errors);
            if (empty($errors)) {
                $_SESSION['folders'][$fi]['tasks'][] = [
                    'id'          => $_SESSION['next_task_id']++,
                    'name'        => $clean['task_name'],
                    'description' => $clean['task_description'],
                    'done'        => false,
                ];
                redirectTo($activeId, 'Task added.');
            }
            $old = $clean;
            break;

        case 'update':
            $clean = validateTask($_POST, $errors);
            $ti    = findTask($fi, $taskId);
            if ($ti === null) {
                $errors[] = 'That task no longer exists.';
            } elseif (empty($errors)) {
                $_SESSION['folders'][$fi]['tasks'][$ti]['name']        = $clean['task_name'];
                $_SESSION['folders'][$fi]['tasks'][$ti]['description'] = $clean['task_description'];
                redirectTo($activeId, 'Task updated.');
            } else {
                $editId = $taskId; // stay in edit mode so errors show on that task
            }
            break;

        case 'delete':
            $ti = findTask($fi, $taskId);
            if ($ti !== null) {
                array_splice($_SESSION['folders'][$fi]['tasks'], $ti, 1);
                redirectTo($activeId, 'Task deleted.');
            }
            redirectTo($activeId);
            break;

        case 'toggle':
            $ti = findTask($fi, $taskId);
            if ($ti !== null) {
                $_SESSION['folders'][$fi]['tasks'][$ti]['done'] = !$_SESSION['folders'][$fi]['tasks'][$ti]['done'];
            }
            redirectTo($activeId);
            break;
    }
}

/* Edit mode is requested with a link: index.php?folder=1&edit=3 */
if ($editId === null && isset($_GET['edit'])) {
    $candidate = (int) $_GET['edit'];
    if (findTask(findFolder($activeId), $candidate) !== null) {
        $editId = $candidate;
    }
}

/* ---------- Data prepared for the page below ---------- */
$flash = $_SESSION['flash'] ?? '';
unset($_SESSION['flash']);

$folders    = $_SESSION['folders'];
$folder     = $folders[findFolder($activeId)];
$tasks      = $folder['tasks'];
$total      = count($tasks);
$doneCount  = countDone($tasks);
$undoneCount = $total - $doneCount;
?>
<!-- =========================================================
     HTML PAGE mga busseng
     ========================================================= -->
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Simple To-do List</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
<div class="layout">

    <!-- ===== Sidebar: folders ===== -->
    <aside class="sidebar" aria-label="Task folders">
        <h1>To-do List</h1>

        <nav>
            <ul class="folder-list">
                <?php foreach ($folders as $f): ?>
                    <?php $fDone = countDone($f['tasks']); ?>
                    <li class="folder <?= $f['id'] === $activeId ? 'is-active' : '' ?>">
                        <a href="index.php?folder=<?= $f['id'] ?>"
                           <?= $f['id'] === $activeId ? 'aria-current="page"' : '' ?>>
                            <span class="folder-name"><?= e($f['name']) ?></span>
                            <span class="folder-count" title="Done / total"><?= $fDone ?>/<?= count($f['tasks']) ?></span>
                        </a>
                        <form method="POST" class="delete-folder-form">
                            <input type="hidden" name="action" value="delete_folder">
                            <input type="hidden" name="folder_id" value="<?= $f['id'] ?>">
                            <button type="submit" class="icon-btn" aria-label="Delete folder <?= e($f['name']) ?>">&times;</button>
                        </form>
                    </li>
                <?php endforeach; ?>
            </ul>
        </nav>

        <?php if (!empty($folderErrors)): ?>
            <ul class="errors" role="alert">
                <?php foreach ($folderErrors as $error): ?>
                    <li><?= e($error) ?></li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>

        <form method="POST" class="folder-form">
            <input type="hidden" name="action" value="create_folder">
            <label for="folder_name">New folder</label>
            <input type="text" id="folder_name" name="folder_name" maxlength="30"
                   value="<?= e($oldFolder) ?>" placeholder="e.g. School" required>
            <button type="submit" class="btn primary">Create folder</button>
        </form>
    </aside>

    <!-- ===== Main: tasks of the open folder ===== -->
    <main class="content">
        <header class="content-header">
            <h2><?= e($folder['name']) ?></h2>
            <ul class="stats" aria-label="Task summary">
                <li><strong><?= $doneCount ?></strong> done</li>
                <li><strong><?= $undoneCount ?></strong> not done</li>
                <li><strong><?= $total ?></strong> total</li>
            </ul>
            <progress max="<?= max($total, 1) ?>" value="<?= $doneCount ?>"
                      aria-label="Tasks completed"></progress>
        </header>

        <?php if ($flash !== ''): ?>
            <p class="flash" role="status"><?= e($flash) ?></p>
        <?php endif; ?>

        <!-- Collapsed until "Add task" is clicked (stays open if the last submit had errors) -->
        <details class="add-task" id="add-task" <?= (!empty($errors) && $editId === null) ? 'open' : '' ?>>
          <summary class="btn primary">+ Add task</summary>
          <section class="panel" aria-labelledby="add-heading">
            <h3 id="add-heading">New task in <?= e($folder['name']) ?></h3>

            <?php if (!empty($errors) && $editId === null): ?>
                <ul class="errors" role="alert">
                    <?php foreach ($errors as $error): ?>
                        <li><?= e($error) ?></li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>

            <form method="POST" class="task-form" id="create-form">
                <input type="hidden" name="action" value="create">
                <input type="hidden" name="folder_id" value="<?= $activeId ?>">

                <label for="task_name">Task name</label>
                <input type="text" id="task_name" name="task_name" maxlength="60"
                       value="<?= e($old['task_name']) ?>" required>

                <label for="task_description">Task description</label>
                <input type="text" id="task_description" name="task_description" maxlength="255"
                       value="<?= e($old['task_description']) ?>" required>
                <span class="counter" data-for="task_description" data-max="255"></span>

                <button type="submit" class="btn primary">Save task</button>
            </form>
          </section>
        </details>

        <section class="panel" aria-labelledby="list-heading">
            <div class="list-head">
                <h3 id="list-heading">Tasks</h3>
                <div class="filters" role="group" aria-label="Filter tasks">
                    <button type="button" class="filter is-active" data-filter="all">All</button>
                    <button type="button" class="filter" data-filter="undone">Not done</button>
                    <button type="button" class="filter" data-filter="done">Done</button>
                </div>
            </div>

            <?php if (empty($tasks)): ?>
                <p class="empty">No tasks in this folder yet. Add your first one above.</p>
            <?php else: ?>
                <ul class="task-list">
                    <?php foreach ($tasks as $task): ?>
                        <li class="task <?= $task['done'] ? 'is-done' : '' ?>">

                            <?php if ($editId === $task['id']): ?>
                                <?php if (!empty($errors)): ?>
                                    <ul class="errors" role="alert">
                                        <?php foreach ($errors as $error): ?>
                                            <li><?= e($error) ?></li>
                                        <?php endforeach; ?>
                                    </ul>
                                <?php endif; ?>

                                <form method="POST" class="task-form">
                                    <input type="hidden" name="action" value="update">
                                    <input type="hidden" name="folder_id" value="<?= $activeId ?>">
                                    <input type="hidden" name="id" value="<?= $task['id'] ?>">

                                    <label for="edit_name_<?= $task['id'] ?>">Task name</label>
                                    <input type="text" id="edit_name_<?= $task['id'] ?>" name="task_name"
                                           maxlength="60" required
                                           value="<?= e($_POST['task_name'] ?? $task['name']) ?>">

                                    <label for="edit_desc_<?= $task['id'] ?>">Task description</label>
                                    <input type="text" id="edit_desc_<?= $task['id'] ?>" name="task_description"
                                           maxlength="255" required
                                           value="<?= e($_POST['task_description'] ?? $task['description']) ?>">

                                    <div class="actions">
                                        <button type="submit" class="btn primary">Save changes</button>
                                        <a class="btn" href="index.php?folder=<?= $activeId ?>">Cancel</a>
                                    </div>
                                </form>

                            <?php else: ?>
                                <form method="POST" class="toggle-form">
                                    <input type="hidden" name="action" value="toggle">
                                    <input type="hidden" name="folder_id" value="<?= $activeId ?>">
                                    <input type="hidden" name="id" value="<?= $task['id'] ?>">
                                    <button type="submit" class="check"
                                            aria-label="<?= $task['done'] ? 'Mark as not done' : 'Mark as done' ?>"
                                            aria-pressed="<?= $task['done'] ? 'true' : 'false' ?>"></button>
                                </form>

                                <div class="task-body">
                                    <strong><?= e($task['name']) ?></strong>
                                    <span><?= e($task['description']) ?></span>
                                </div>

                                <div class="actions">
                                    <a class="btn" href="index.php?folder=<?= $activeId ?>&amp;edit=<?= $task['id'] ?>">Edit</a>
                                    <form method="POST" class="delete-form">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="folder_id" value="<?= $activeId ?>">
                                        <input type="hidden" name="id" value="<?= $task['id'] ?>">
                                        <button type="submit" class="btn danger">Delete</button>
                                    </form>
                                </div>
                            <?php endif; ?>

                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </section>
    </main>
</div>

<script src="script.js"></script>
</body>
</html>