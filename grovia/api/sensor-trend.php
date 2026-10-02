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

// One averaged reading per hour, today only — real historical data, not
// simulated. Missing hours (no readings yet, or device was offline) stay
// null rather than being guessed/interpolated.
$stmt = get_db()->query(
    'SELECT HOUR(recorded_at) AS hr, '
    . 'AVG(gas_value) AS gas, AVG(light_value) AS light, AVG(soil_value) AS soil, '
    . 'AVG(temperature_value) AS temperature, AVG(humidity_value) AS humidity, AVG(water_level_value) AS water_level '
    . 'FROM sensor_readings WHERE recorded_at >= CURDATE() GROUP BY HOUR(recorded_at) ORDER BY hr'
);
$byHour = [];
foreach ($stmt->fetchAll() as $row) {
    $byHour[(int) $row['hr']] = $row;
}

$currentHour = (int) date('G');
$labels = [];
$series = ['gas' => [], 'light' => [], 'soil' => [], 'temperature' => [], 'humidity' => [], 'water_level' => []];

for ($h = 0; $h <= $currentHour; $h++) {
    $labels[] = sprintf('%02d:00', $h);
    foreach (array_keys($series) as $key) {
        $series[$key][] = isset($byHour[$h]) && $byHour[$h][$key] !== null ? round((float) $byHour[$h][$key], 1) : null;
    }
}

echo json_encode(['ok' => true, 'labels' => $labels] + $series);
