<?php
// Marks a task Completed/Skipped/Pending, or deletes it — the calendar's
// per-task checkbox and delete button both land here.
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../auth.php';

header('Content-Type: application/json');

$user = current_user();
if (!$user) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'Not logged in.']);
    exit;
}
if (!current_user_has_worker_access($user)) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'You do not have access to the greenhouse yet.']);
    exit;
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Use POST.']);
    exit;
}

$input = json_decode((string) file_get_contents('php://input'), true) ?? [];
$taskId = (int) ($input['task_id'] ?? 0);
if ($taskId <= 0) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'task_id is required.']);
    exit;
}

$db = get_db();

// Every write below is scoped to user_id = ? too, not just task_id — so
// one user can never touch another's task by guessing an ID.
$owns = $db->prepare('SELECT 1 FROM schedule_tasks WHERE task_id = ? AND user_id = ?');
$owns->execute([$taskId, $user['user_id']]);
if (!$owns->fetchColumn()) {
    http_response_code(404);
    echo json_encode(['ok' => false, 'error' => 'Task not found.']);
    exit;
}

if (!empty($input['delete'])) {
    $db->prepare('DELETE FROM schedule_tasks WHERE task_id = ? AND user_id = ?')->execute([$taskId, $user['user_id']]);
    echo json_encode(['ok' => true, 'deleted' => true]);
    exit;
}

if (isset($input['status'])) {
    $status = (string) $input['status'];
    if (!in_array($status, ['Pending', 'Completed', 'Skipped'], true)) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'status must be Pending, Completed, or Skipped.']);
        exit;
    }
    $db->prepare('UPDATE schedule_tasks SET status = ? WHERE task_id = ? AND user_id = ?')
        ->execute([$status, $taskId, $user['user_id']]);
    echo json_encode(['ok' => true]);
    exit;
}

http_response_code(400);
echo json_encode(['ok' => false, 'error' => 'Provide "status" or "delete".']);
