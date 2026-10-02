<?php
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../device-control.php';

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
    $state = $db->query(
        'SELECT fan_on, fan_mode, pump_on, pump_mode, lamp_on, lamp_mode, vent_on, vent_mode, esp32_connected, updated_at FROM device_state WHERE id = 1'
    )->fetch();
    // Cast every on/off flag to a real boolean before encoding — PDO can hand
    // these back as strings ("0"/"1"), and JS treats the string "0" as truthy,
    // which would make a disconnected/off state read as connected/on.
    foreach (['fan_on', 'pump_on', 'lamp_on', 'vent_on', 'esp32_connected'] as $flag) {
        if ($state && array_key_exists($flag, $state)) {
            $state[$flag] = (bool) $state[$flag];
        }
    }
    echo json_encode(['ok' => true, 'state' => $state]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Use GET or POST.']);
    exit;
}

$input = json_decode((string) file_get_contents('php://input'), true);
$device = $input['device'] ?? '';
if (!in_array($device, ['fan', 'pump', 'lamp', 'vent'], true)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'device must be "fan", "pump", "lamp", or "vent".']);
    exit;
}

// Switching Auto <-> Manual — allowed any time.
if (isset($input['mode'])) {
    if (!in_array($input['mode'], ['auto', 'manual'], true)) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'mode must be "auto" or "manual".']);
        exit;
    }
    $stmt = $db->prepare("UPDATE device_state SET {$device}_mode = ? WHERE id = 1");
    $stmt->execute([$input['mode']]);
    echo json_encode(['ok' => true]);
    exit;
}

// Manually flipping the device on/off — only meaningful while it's actually
// in manual mode. In auto mode, gh_update_actuators() owns the *_on column,
// so a manual toggle here would just get overwritten on the next poll anyway.
if (isset($input['on'])) {
    $newOn = (bool) $input['on'];

    $current = $db->prepare("SELECT {$device}_mode AS mode, {$device}_on AS is_on FROM device_state WHERE id = 1");
    $current->execute();
    $row = $current->fetch();
    $currentMode = $row['mode'] ?? null;

    if ($currentMode !== 'manual') {
        http_response_code(409);
        echo json_encode(['ok' => false, 'error' => ucfirst($device) . ' is in automatic mode — switch to manual first.']);
        exit;
    }

    if ($device === 'pump') {
        gh_log_pump_transition($db, (bool) $row['is_on'], $newOn, 'manual');
    }

    $stmt = $db->prepare("UPDATE device_state SET {$device}_on = ? WHERE id = 1");
    $stmt->execute([$newOn ? 1 : 0]);
    echo json_encode(['ok' => true]);
    exit;
}

http_response_code(400);
echo json_encode(['ok' => false, 'error' => 'Provide "mode" or "on".']);
