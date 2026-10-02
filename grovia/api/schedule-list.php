<?php
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

// ?start=YYYY-MM-DD&end=YYYY-MM-DD — defaults to the current Mon-Sun week
// if not given, since that's what the calendar view shows on load.
$start = $_GET['start'] ?? null;
$end = $_GET['end'] ?? null;
if (!$start || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $start)) {
    $start = (new DateTime('monday this week'))->format('Y-m-d');
}
if (!$end || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $end)) {
    $end = (new DateTime($start . ' +6 days'))->format('Y-m-d');
}

$stmt = get_db()->prepare(
    'SELECT task_id, title, notes, category, scheduled_date, scheduled_time, is_ai_suggested, ai_reason, status '
    . 'FROM schedule_tasks WHERE user_id = ? AND scheduled_date BETWEEN ? AND ? '
    . 'ORDER BY scheduled_date ASC, scheduled_time ASC'
);
$stmt->execute([$user['user_id'], $start, $end]);

echo json_encode(['ok' => true, 'start' => $start, 'end' => $end, 'tasks' => $stmt->fetchAll()]);
