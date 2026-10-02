<?php
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

$db = get_db();

$cacheHours = 24;
$forceRefresh = isset($_GET['refresh']) && $_GET['refresh'] === '1';

$stmt = $db->prepare('SELECT * FROM ai_insights WHERE user_id = ? ORDER BY generated_at DESC LIMIT 1');
$stmt->execute([$user['user_id']]);
$cached = $stmt->fetch();

$ageHours = $cached ? (time() - strtotime($cached['generated_at'])) / 3600 : null;

// A forced refresh is still rate-limited to once every 5 minutes per user,
// so a click-happy user (or a stray request) can't rack up Gemini calls.
$minRefreshMinutes = 5;
$canForceRefresh = !$cached || $ageHours * 60 >= $minRefreshMinutes;

if ($cached && !($forceRefresh && $canForceRefresh) && $ageHours < $cacheHours) {
    echo json_encode([
        'ok' => true,
        'cached' => true,
        'headline' => $cached['headline'],
        'summary' => $cached['summary'],
        'recommendation' => $cached['recommendation'],
        'trend' => $cached['trend'],
        'generated_at' => $cached['generated_at'],
    ]);
    exit;
}

// 7-day sensor trend, grouped by day so the model can actually spot a
// pattern (e.g. "moisture dipped Tuesday/Wednesday") instead of one blob average.
$sensorRows = $db->query(
    "SELECT DATE(recorded_at) AS day, "
    . "ROUND(AVG(temperature_value), 1) AS avg_temp, ROUND(AVG(humidity_value), 1) AS avg_humidity, "
    . "ROUND(AVG(soil_value), 0) AS avg_soil, ROUND(AVG(water_level_value), 0) AS avg_water "
    . "FROM sensor_readings WHERE recorded_at >= NOW() - INTERVAL 7 DAY "
    . "GROUP BY DATE(recorded_at) ORDER BY day ASC"
)->fetchAll();

if ($sensorRows) {
    $sensorContext = "Daily sensor averages, last 7 days (soil moisture is a raw sensor value, lower = wetter):\n";
    foreach ($sensorRows as $r) {
        $sensorContext .= "- {$r['day']}: temp " . ($r['avg_temp'] ?? 'n/a') . "°C, humidity " . ($r['avg_humidity'] ?? 'n/a')
            . "%, soil " . ($r['avg_soil'] ?? 'n/a') . ", water level " . ($r['avg_water'] ?? 'n/a') . "\n";
    }
} else {
    $sensorContext = "No sensor readings recorded in the last 7 days (device may be disconnected).\n";
}

$pumpRows = $db->query(
    "SELECT trigger_source, COUNT(*) AS runs, ROUND(AVG(duration_seconds), 0) AS avg_duration "
    . "FROM pump_log WHERE started_at >= NOW() - INTERVAL 7 DAY GROUP BY trigger_source"
)->fetchAll();
if ($pumpRows) {
    $pumpContext = "Watering activity, last 7 days:\n";
    foreach ($pumpRows as $r) {
        $pumpContext .= "- {$r['runs']} run(s) triggered by '{$r['trigger_source']}', averaging {$r['avg_duration']}s each\n";
    }
} else {
    $pumpContext = "No watering events recorded in the last 7 days.\n";
}

$detectStmt = $db->prepare(
    "SELECT status, COUNT(*) AS n FROM disease_detections WHERE user_id = ? AND detected_at >= NOW() - INTERVAL 14 DAY GROUP BY status"
);
$detectStmt->execute([$user['user_id']]);
$detectRows = $detectStmt->fetchAll();
if ($detectRows) {
    $detectContext = "AI Camera plant health checks, last 14 days:\n";
    foreach ($detectRows as $r) {
        $detectContext .= "- {$r['status']}: {$r['n']}\n";
    }
} else {
    $detectContext = "No AI Camera plant health checks in the last 14 days.\n";
}

$scheduleRow = $db->query('SELECT enabled, scheduled_time, duration_minutes FROM irrigation_schedule LIMIT 1')->fetch();
$scheduleContext = $scheduleRow
    ? "Watering schedule: " . ($scheduleRow['enabled'] ? "enabled, runs daily at {$scheduleRow['scheduled_time']} for {$scheduleRow['duration_minutes']} min." : "currently disabled.") . "\n"
    : "No watering schedule configured.\n";

$alertStmt = $db->prepare(
    "SELECT title, message, created_at FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 15"
);
$alertStmt->execute([$user['user_id']]);
$alertRows = $alertStmt->fetchAll();
if ($alertRows) {
    $alertContext = "Most recent alerts (newest first):\n";
    foreach ($alertRows as $r) {
        $alertContext .= "- [{$r['created_at']}] {$r['title']}: {$r['message']}\n";
    }
} else {
    $alertContext = "No alerts recorded recently.\n";
}

$prompt = <<<PROMPT
You are a professional greenhouse operations analyst writing a brief status report for
the greenhouse owner. Review the data below — covering the past 1-2 weeks of sensor
trends AND the most recent individual alerts — and produce ONE clear, professional
summary of what has actually been happening, as if summarizing a log for someone who
hasn't checked in a while. Ground everything in the real data given; do not invent
numbers or events that aren't present. If several alerts point to the same underlying
issue (e.g. a string of "soil too dry" and "water tank empty" alerts), say so as one
connected story rather than listing them separately. If the data is too sparse to say
anything meaningful, say that plainly and encouragingly rather than fabricating a trend.

Respond with ONLY a JSON object, no other text, using exactly these fields:
{
  "headline": "a short, specific headline, under 60 characters",
  "summary": "2-4 sentences summarizing what has been happening, referencing real numbers/events from the data",
  "recommendation": "one concrete, actionable next step, 1-2 sentences",
  "trend": one of "positive", "neutral", "warning"
}

DATA:
{$sensorContext}
{$pumpContext}
{$detectContext}
{$scheduleContext}
{$alertContext}
PROMPT;

$url = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-3.6-flash:generateContent?key=' . urlencode(GEMINI_API_KEY);

$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
    CURLOPT_POSTFIELDS => json_encode([
        'contents' => [['role' => 'user', 'parts' => [['text' => $prompt]]]],
        'generationConfig' => ['temperature' => 0.4, 'responseMimeType' => 'application/json'],
    ]),
    CURLOPT_TIMEOUT => 20,
]);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($response === false || $httpCode !== 200) {
    if ($cached) {
        echo json_encode([
            'ok' => true,
            'cached' => true,
            'headline' => $cached['headline'],
            'summary' => $cached['summary'],
            'recommendation' => $cached['recommendation'],
            'trend' => $cached['trend'],
            'generated_at' => $cached['generated_at'],
        ]);
        exit;
    }
    http_response_code(502);
    echo json_encode(['ok' => false, 'error' => 'AI insight is unavailable right now, try again.']);
    exit;
}

$data = json_decode($response, true);
$resultText = $data['candidates'][0]['content']['parts'][0]['text'] ?? null;
$result = $resultText ? json_decode($resultText, true) : null;

if (!$result || !isset($result['headline'])) {
    http_response_code(502);
    echo json_encode(['ok' => false, 'error' => "Couldn't generate an insight, try again."]);
    exit;
}

$headline = substr((string) $result['headline'], 0, 150);
$summary = (string) ($result['summary'] ?? '');
$recommendation = (string) ($result['recommendation'] ?? '');
$trend = in_array($result['trend'] ?? 'neutral', ['positive', 'neutral', 'warning'], true) ? $result['trend'] : 'neutral';

$insert = $db->prepare('INSERT INTO ai_insights (user_id, headline, summary, recommendation, trend) VALUES (?, ?, ?, ?, ?)');
$insert->execute([$user['user_id'], $headline, $summary, $recommendation, $trend]);

echo json_encode([
    'ok' => true,
    'cached' => false,
    'headline' => $headline,
    'summary' => $summary,
    'recommendation' => $recommendation,
    'trend' => $trend,
    'generated_at' => date('Y-m-d H:i:s'),
]);
