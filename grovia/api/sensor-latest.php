<?php
// what's the latest reading?" every 0.5 seconds.
// reading from the ESP32, or null if nothing has come in yet.
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../notifications.php';
require_once __DIR__ . '/../device-control.php';
header('Content-Type: application/json');//respons is jason

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

//calculation 
$stmt = get_db()->query(
    'SELECT gas_value, light_value, soil_value, temperature_value, humidity_value, water_level_value, recorded_at, '
    . 'TIMESTAMPDIFF(SECOND, recorded_at, NOW()) AS age_seconds '// adesh sarlo msh kare2 men el database
    . 'FROM sensor_readings ORDER BY id DESC LIMIT 1'
);
$latest = $stmt->fetch();

// The ESP32 sends readings multiple times a second while connected, so if
// the newest row is more than 3 seconds old, treat it as disconnected
// rather than keep showing that stale number as if it were live.
$connected = $latest !== false && (int) $latest['age_seconds'] <= 3;

// The notification uses a separate, more forgiving definition of "offline"
// than the live UI badge above. This page gets polled twice a second, so a
// single brief WiFi hiccup could flip $connected true/false rapidly and
// fire a burst of alerts — unrealistic for something meant to represent a
// real outage. Declaring "disconnected" requires 15+ seconds of silence
// (a real, sustained drop), while declaring "reconnected" fires the moment
// a fresh reading arrives — the same asymmetric behavior real uptime
// monitors use: cautious to alarm, quick to clear.
$reallyConnected = $latest !== false && (int) $latest['age_seconds'] <= 15;
gh_check_connection_change(get_db(), (int) $user['user_id'], $reallyConnected);

if ($connected && $latest) {
    $waterPct = $latest['water_level_value'] !== null
        ? max(0, min(100, round(((float) $latest['water_level_value'] / 900) * 100)))
        : null;

    gh_check_and_notify(get_db(), (int) $user['user_id'], [
        'gas'         => $latest['gas_value'],
        'temperature' => $latest['temperature_value'],
        'water_level' => $waterPct,
        'soil'        => $latest['soil_value'],
        'humidity'    => $latest['humidity_value'],
    ]);

    gh_update_actuators(get_db(), [
        'gas'             => $latest['gas_value'],
        'temperature'     => $latest['temperature_value'],
        'soil'            => $latest['soil_value'],
        'water_level_pct' => $waterPct,
    ]);
}

// Time-based, not sensor-based — checked every poll regardless of whether
// the ESP32 is currently connected, since it's the clock that matters here.
// Placed after gh_update_actuators() above so it sees this poll's freshly
// resolved pump_on state, not last poll's.
gh_check_watering_reminder(get_db(), (int) $user['user_id']);
gh_check_schedule_reminders(get_db(), (int) $user['user_id']);

echo json_encode(['ok' => true, 'latest' => $latest ?: null, 'connected' => $connected]);
