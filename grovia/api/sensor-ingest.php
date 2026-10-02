<?php


require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../device-config.php';//the shared password the ESP32 must send
require_once __DIR__ . '/../device-control.php';
require_once __DIR__ . '/../notifications.php';


header('Content-Type: application/json');//response you get will be jason 

function gh_json_error(int $httpCode, string $message): void {//function la el error 
    http_response_code($httpCode);
    echo json_encode(['ok' => false, 'error' => $message]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    gh_json_error(405, 'Use POST.');
}

$input = $_POST;//read el incoming data 
if (empty($input)) {
    $decoded = json_decode((string) file_get_contents('php://input'), true);
    if (is_array($decoded)) {
        $input = $decoded;
    }
}

$apiKey = (string) ($input['api_key'] ?? '');// hayde kermal el key grovia123
if (!hash_equals(GH_DEVICE_API_KEY, $apiKey)) {
    gh_json_error(403, 'Invalid api_key.');
}
// hon read every sensor vlaue la kel el sensors 
$gas = isset($input['gas']) && $input['gas'] !== '' ? filter_var($input['gas'], FILTER_VALIDATE_FLOAT) : null;
$light = isset($input['light']) && $input['light'] !== '' ? filter_var($input['light'], FILTER_VALIDATE_FLOAT) : null;
$soil = isset($input['soil']) && $input['soil'] !== '' ? filter_var($input['soil'], FILTER_VALIDATE_FLOAT) : null;
$temperature = isset($input['temperature']) && $input['temperature'] !== '' ? filter_var($input['temperature'], FILTER_VALIDATE_FLOAT) : null;
$humidity = isset($input['humidity']) && $input['humidity'] !== '' ? filter_var($input['humidity'], FILTER_VALIDATE_FLOAT) : null;
$waterLevel = isset($input['water_level']) && $input['water_level'] !== '' ? filter_var($input['water_level'], FILTER_VALIDATE_FLOAT) : null;// hon el value men el analog read

if ($temperature !== null && $temperature < 0) { $temperature = null; }
if ($humidity !== null && $humidity < 0) { $humidity = null; }

$db = get_db();

$stmt = $db->prepare('INSERT INTO sensor_readings (gas_value, light_value, soil_value, temperature_value, humidity_value, water_level_value) VALUES (?, ?, ?, ?, ?, ?)');
$stmt->execute([$gas, $light, $soil, $temperature, $humidity, $waterLevel]);

$readingId = (int) $db->lastInsertId();

// Evaluate the fan/pump auto-logic right here on every reading the device

$waterPct = $waterLevel !== null ? max(0, min(100, round(($waterLevel / 900) * 100))) : null;
gh_update_actuators($db, [
    'gas'             => $gas,
    'temperature'     => $temperature,
    'soil'            => $soil,
    'water_level_pct' => $waterPct,
    'light'           => $light,
]);

// Schedule reminders are time-based, not device-specific, but this
// endpoint (unlike sensor-latest.php) has no logged-in user to check —
// the ESP32 posts with just an api_key. Firing off every reading (which
// happens constantly per the ESP32 firmware, unlike a browser dashboard
// that might not be open) is what makes these reminders actually
// reliable, so check it here for every user who currently has one
// pending rather than skip it for lack of a single "current" user.
$pendingUserIds = $db->query(
    "SELECT DISTINCT user_id FROM schedule_tasks WHERE status = 'Pending' AND reminder_sent = 0"
)->fetchAll(PDO::FETCH_COLUMN);
foreach ($pendingUserIds as $pendingUserId) {
    gh_check_schedule_reminders($db, (int) $pendingUserId);
}

// The ESP32 doesn't poll a separate endpoint for on/off commands — it just
// reads them out of this same response, right after every reading it sends.
// So the current switch states have to actually be included here.
$deviceState = $db->query(
    'SELECT fan_on, lamp_on, pump_on, vent_on FROM device_state WHERE id = 1'
)->fetch();

echo json_encode([
    'ok' => true,
    'reading_id' => $readingId,
    'fan_on' => (bool) ($deviceState['fan_on'] ?? false),
    'lamp_on' => (bool) ($deviceState['lamp_on'] ?? false),
    'pump_on' => (bool) ($deviceState['pump_on'] ?? false),
    'vent_on' => (bool) ($deviceState['vent_on'] ?? false),
]);
