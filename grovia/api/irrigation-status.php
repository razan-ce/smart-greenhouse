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

$last = $db->query('SELECT started_at, ended_at, duration_seconds, trigger_source FROM pump_log ORDER BY id DESC LIMIT 1')->fetch();

$schedule = $db->query('SELECT enabled, scheduled_time, duration_minutes FROM irrigation_schedule WHERE id = 1')->fetch();

$nextWatering = null;
if ($schedule && $schedule['enabled']) {
    $now = new DateTime();
    $todayScheduled = new DateTime($now->format('Y-m-d') . ' ' . $schedule['scheduled_time']);
    $nextWatering = $now < $todayScheduled
        ? $todayScheduled->format('Y-m-d H:i:s')
        : $todayScheduled->modify('+1 day')->format('Y-m-d H:i:s');
}

// Today's total watering time, across every trigger type — useful "water usage" style stat.
$todayTotal = $db->query(
    "SELECT COALESCE(SUM(duration_seconds), 0) AS total FROM pump_log "
    . "WHERE DATE(started_at) = CURDATE() AND ended_at IS NOT NULL"
)->fetchColumn();

echo json_encode([
    'ok' => true,
    'last_watered' => $last ?: null,
    'schedule' => $schedule,
    'next_watering' => $nextWatering,
    'today_total_seconds' => (int) $todayTotal,
]);
