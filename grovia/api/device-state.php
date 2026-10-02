<?php
// Lets a physical device (ESP32, no browser session) read the current
// fan/pump on-off state so it can actually act on it — mirrors
// sensor-ingest.php's api_key auth rather than api/device-control.php's
// login-based auth, since a device can't hold a PHP session cookie.
// The dashboard's manual toggle and the auto-logic in device-control.php
// both write to the same device_state row this reads, so whichever one
// last decided fan_on is exactly what a device polling this sees.
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../device-config.php';

header('Content-Type: application/json');

$apiKey = (string) ($_GET['api_key'] ?? $_POST['api_key'] ?? '');
if (!hash_equals(GH_DEVICE_API_KEY, $apiKey)) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Invalid api_key.']);
    exit;
}

$state = get_db()->query('SELECT fan_on, fan_mode, pump_on, pump_mode FROM device_state WHERE id = 1')->fetch();

echo json_encode([
    'ok' => true,
    'fan_on' => (bool) $state['fan_on'],
    'fan_mode' => $state['fan_mode'],
    'pump_on' => (bool) $state['pump_on'],
    'pump_mode' => $state['pump_mode'],
]);
