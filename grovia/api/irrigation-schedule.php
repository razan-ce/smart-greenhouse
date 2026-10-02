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

$db = get_db();

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $schedule = $db->query('SELECT enabled, scheduled_time, duration_minutes FROM irrigation_schedule WHERE id = 1')->fetch();
    echo json_encode(['ok' => true, 'schedule' => $schedule]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Use GET or POST.']);
    exit;
}

$input = json_decode((string) file_get_contents('php://input'), true);

$fields = [];
$values = [];

if (isset($input['enabled'])) {
    $fields[] = 'enabled = ?';
    $values[] = $input['enabled'] ? 1 : 0;
}
if (isset($input['scheduled_time'])) {
    if (!preg_match('/^\d{2}:\d{2}$/', (string) $input['scheduled_time'])) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'scheduled_time must be HH:MM.']);
        exit;
    }
    $fields[] = 'scheduled_time = ?';
    $values[] = $input['scheduled_time'] . ':00';
}
if (isset($input['duration_minutes'])) {
    $minutes = (int) $input['duration_minutes'];
    if ($minutes < 1 || $minutes > 180) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'duration_minutes must be between 1 and 180.']);
        exit;
    }
    $fields[] = 'duration_minutes = ?';
    $values[] = $minutes;
}

if (empty($fields)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Provide enabled, scheduled_time, and/or duration_minutes.']);
    exit;
}

$sql = 'UPDATE irrigation_schedule SET ' . implode(', ', $fields) . ' WHERE id = 1';
$db->prepare($sql)->execute($values);

echo json_encode(['ok' => true]);
