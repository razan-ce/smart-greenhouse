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
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Use POST.']);
    exit;
}

$input = json_decode((string) file_get_contents('php://input'), true) ?? [];

$title = trim((string) ($input['title'] ?? ''));
$category = (string) ($input['category'] ?? 'Other');
$date = (string) ($input['scheduled_date'] ?? '');
$time = (string) ($input['scheduled_time'] ?? '');
$notes = trim((string) ($input['notes'] ?? ''));

$validCategories = ['Watering', 'Ventilation', 'Inspection', 'Fertilizing', 'Harvest', 'Pruning', 'Cleaning', 'Other'];

if ($title === '' || strlen($title) > 150) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Title is required (max 150 characters).']);
    exit;
}
if (!in_array($category, $validCategories, true)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Invalid category.']);
    exit;
}
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'scheduled_date must be YYYY-MM-DD.']);
    exit;
}
if (!preg_match('/^\d{2}:\d{2}$/', $time)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'scheduled_time must be HH:MM.']);
    exit;
}

$db = get_db();
$stmt = $db->prepare(
    'INSERT INTO schedule_tasks (user_id, title, notes, category, scheduled_date, scheduled_time, is_ai_suggested) '
    . 'VALUES (?, ?, ?, ?, ?, ?, 0)'
);
$stmt->execute([$user['user_id'], $title, $notes ?: null, $category, $date, $time . ':00']);

echo json_encode(['ok' => true, 'task_id' => (int) $db->lastInsertId()]);
