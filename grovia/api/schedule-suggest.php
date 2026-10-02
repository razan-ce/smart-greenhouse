<?php
// "Suggest My Week" — asks Gemini to propose a week of greenhouse tasks
// grounded in this user's actual recent sensor data (same context-gathering
// style as api/ai-insight.php), then inserts the result as is_ai_suggested
// rows the user can accept, edit, or delete like any other task.
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../config.php';

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

$db = get_db();
$userId = (int) $user['user_id'];
$today = new DateTime('today');
$weekEnd = (clone $today)->modify('+6 days');

// Rate-limit generation itself (not just re-fetching) — five minutes
// between requests, same cooldown shape as ai-insight.php's forced
// refresh, so a click-happy user can't rack up Gemini calls.
$lastGenStmt = $db->prepare(
    "SELECT MAX(created_at) FROM schedule_tasks WHERE user_id = ? AND is_ai_suggested = 1"
);
$lastGenStmt->execute([$userId]);
$lastGen = $lastGenStmt->fetchColumn();
if ($lastGen && (time() - strtotime($lastGen)) < 300) {
    http_response_code(429);
    echo json_encode(['ok' => false, 'error' => 'Give it a few minutes before generating another suggested week.']);
    exit;
}

// 7-day sensor context, same shape ai-insight.php already uses. Water level
// is deliberately excluded from this trend average — the reservoir sensor
// is a detect/no-detect float switch (not graduated), so an averaged
// number would be meaningless; its current status is reported separately
// below instead.
$sensorRows = $db->query(
    "SELECT DATE(recorded_at) AS day, ROUND(AVG(temperature_value), 1) AS avg_temp, "
    . "ROUND(AVG(humidity_value), 1) AS avg_humidity, ROUND(AVG(soil_value), 0) AS avg_soil, "
    . "ROUND(AVG(gas_value), 0) AS avg_gas "
    . "FROM sensor_readings WHERE recorded_at >= NOW() - INTERVAL 7 DAY "
    . "GROUP BY DATE(recorded_at) ORDER BY day ASC"
)->fetchAll();

if ($sensorRows) {
    $sensorContext = "Daily sensor averages, last 7 days (soil moisture is a raw sensor value, lower = wetter; "
        . "danger thresholds: gas >= 1000, temperature >= 38°C, soil > 2800 = too dry):\n";
    foreach ($sensorRows as $r) {
        $sensorContext .= "- {$r['day']}: temp {$r['avg_temp']}°C, humidity {$r['avg_humidity']}%, soil {$r['avg_soil']}, gas {$r['avg_gas']}\n";
    }
} else {
    $sensorContext = "No sensor readings in the last 7 days (device may be disconnected) — suggest a sensible general-purpose week instead.\n";
}

// Current water tank status — the latest single reading, interpreted as a
// plain Full/Empty state (same >=50% rule the dashboard and Sensors page
// use), since that's all this sensor can actually report.
$latestWater = $db->query('SELECT water_level_value FROM sensor_readings WHERE water_level_value IS NOT NULL ORDER BY recorded_at DESC LIMIT 1')->fetchColumn();
if ($latestWater !== false) {
    $waterPct = max(0, min(100, round(((float) $latestWater / 900) * 100)));
    $waterContext = $waterPct >= 50
        ? "Water reservoir tank: Full (most recent reading).\n"
        : "Water reservoir tank: EMPTY (most recent reading) — needs a physical refill, the pump cannot add water that isn't there.\n";
} else {
    $waterContext = "No water tank reading available yet.\n";
}

$state = $db->query('SELECT fan_mode, pump_mode FROM device_state WHERE id = 1')->fetch();
$stateContext = "Fan is in {$state['fan_mode']} mode, pump is in {$state['pump_mode']} mode.\n";

$detectStmt = $db->prepare(
    "SELECT status, COUNT(*) AS n FROM disease_detections WHERE user_id = ? AND detected_at >= NOW() - INTERVAL 14 DAY GROUP BY status"
);
$detectStmt->execute([$userId]);
$detectRows = $detectStmt->fetchAll();
$detectContext = $detectRows ? "AI Camera checks, last 14 days:\n" : "No AI Camera checks in the last 14 days.\n";
foreach ($detectRows as $r) {
    $detectContext .= "- {$r['status']}: {$r['n']}\n";
}

// So the model doesn't propose duplicates of tasks the user already has
// on the calendar this week.
$existingStmt = $db->prepare(
    "SELECT title, category, scheduled_date, scheduled_time FROM schedule_tasks "
    . "WHERE user_id = ? AND scheduled_date BETWEEN ? AND ? ORDER BY scheduled_date, scheduled_time"
);
$existingStmt->execute([$userId, $today->format('Y-m-d'), $weekEnd->format('Y-m-d')]);
$existingRows = $existingStmt->fetchAll();
$existingContext = $existingRows ? "Already on this user's calendar this week (don't duplicate these):\n" : "Nothing on the calendar yet this week.\n";
foreach ($existingRows as $r) {
    $existingContext .= "- {$r['scheduled_date']} {$r['scheduled_time']}: {$r['title']} ({$r['category']})\n";
}

$todayLabel = $today->format('Y-m-d (D)');

$prompt = <<<PROMPT
You are a professional greenhouse operations planner. Today is {$todayLabel}. Based on
the real data below from one user's smart greenhouse, propose a practical schedule of
tasks for the next 7 days (today through {$weekEnd->format('Y-m-d')}). Ground every
suggestion in the actual data given, using these rules:
- Do NOT create a "refill the water tank" task, even if the tank is reported Empty — this
  app already sends an automatic low-water notification for that, so a calendar task would
  just duplicate it. The water tank status is given only as context, e.g. to help you
  decide whether a moisture-check task is even useful right now.
- Soil consistently above 2800 (too dry): suggest a watering/irrigation-check task.
- Temperature averaging 38°C or higher on any day: suggest a ventilation/cooling task for
  that day.
- Gas averaging 1000 or higher on any day: suggest an urgent inspection task for that day
  (possible air-quality issue) — schedule it as early as possible, not later in the week.
- If none of the above trigger, don't invent a problem — say so via routine tasks instead.
Include routine good-practice tasks too (inspection, cleaning, fertilizing) even when
nothing is wrong, but keep the total to 4-8 tasks for the whole week, spread across
different days rather than bunched on one day. Avoid duplicating anything already on the
calendar.

Respond with ONLY a JSON object, no other text, using exactly this shape:
{
  "tasks": [
    {
      "date": "YYYY-MM-DD",
      "time": "HH:MM" (24-hour),
      "title": "short, specific, under 60 characters",
      "category": one of "Watering","Ventilation","Inspection","Fertilizing","Harvest","Pruning","Cleaning","Other",
      "reason": "1 sentence grounding this in the actual data, or 'Routine good practice.' if not data-driven"
    }
  ]
}

DATA:
{$sensorContext}
{$waterContext}
{$stateContext}
{$detectContext}
{$existingContext}
PROMPT;

$ch = curl_init('https://generativelanguage.googleapis.com/v1beta/models/gemini-3.6-flash:generateContent?key=' . urlencode(GEMINI_API_KEY));
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
    CURLOPT_POSTFIELDS => json_encode([
        'contents' => [['role' => 'user', 'parts' => [['text' => $prompt]]]],
        'generationConfig' => ['temperature' => 0.5, 'responseMimeType' => 'application/json'],
    ]),
    CURLOPT_TIMEOUT => 30,
]);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($response === false || $httpCode !== 200) {
    http_response_code(502);
    echo json_encode(['ok' => false, 'error' => 'AI scheduling is unavailable right now, try again shortly.']);
    exit;
}

$data = json_decode($response, true);
$resultText = $data['candidates'][0]['content']['parts'][0]['text'] ?? null;
$result = $resultText ? json_decode($resultText, true) : null;

if (!$result || !isset($result['tasks']) || !is_array($result['tasks'])) {
    http_response_code(502);
    echo json_encode(['ok' => false, 'error' => "Couldn't generate a schedule, try again."]);
    exit;
}

$validCategories = ['Watering', 'Ventilation', 'Inspection', 'Fertilizing', 'Harvest', 'Pruning', 'Cleaning', 'Other'];
$insert = $db->prepare(
    'INSERT INTO schedule_tasks (user_id, title, category, scheduled_date, scheduled_time, is_ai_suggested, ai_reason) '
    . 'VALUES (?, ?, ?, ?, ?, 1, ?)'
);

$inserted = [];
foreach ($result['tasks'] as $task) {
    $date = (string) ($task['date'] ?? '');
    $time = (string) ($task['time'] ?? '');
    $title = substr(trim((string) ($task['title'] ?? '')), 0, 150);
    $category = in_array($task['category'] ?? '', $validCategories, true) ? $task['category'] : 'Other';
    $reason = substr(trim((string) ($task['reason'] ?? '')), 0, 300);

    // Trust nothing from the model — reject anything outside the
    // requested date range or malformed, rather than silently coercing it.
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || $date < $today->format('Y-m-d') || $date > $weekEnd->format('Y-m-d')) {
        continue;
    }
    if (!preg_match('/^\d{2}:\d{2}$/', $time)) {
        continue;
    }
    if ($title === '') {
        continue;
    }

    $insert->execute([$userId, $title, $category, $date, $time . ':00', $reason ?: null]);
    $inserted[] = [
        'task_id' => (int) $db->lastInsertId(),
        'title' => $title,
        'category' => $category,
        'scheduled_date' => $date,
        'scheduled_time' => $time . ':00',
        'ai_reason' => $reason,
    ];
}

if (!$inserted) {
    http_response_code(502);
    echo json_encode(['ok' => false, 'error' => "The AI's response didn't contain any usable tasks, try again."]);
    exit;
}

echo json_encode(['ok' => true, 'tasks' => $inserted]);
