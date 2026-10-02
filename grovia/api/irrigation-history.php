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

$rows = $db->query(
    "SELECT started_at, ended_at, duration_seconds, trigger_source FROM pump_log "
    . "WHERE started_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) ORDER BY started_at DESC LIMIT 100"
)->fetchAll();

$totalSeconds = 0;
foreach ($rows as $r) {
    if ($r['duration_seconds'] !== null) {
        $totalSeconds += (int) $r['duration_seconds'];
    }
}

echo json_encode([
    'ok' => true,
    'events' => $rows,
    'total_events' => count($rows),
    'total_seconds' => $totalSeconds,
]);
