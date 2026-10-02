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

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
 http_response_code(405); 
echo json_encode(['ok' => false, 'error' => 'Use POST.']);  // Explain why it failed
    exit; 
 }
 $input = json_decode((string) file_get_contents('php://input'), true);
 $question = trim((string) ($input['message'] ?? ''));
 if ($question === '') {         
    http_response_code(400);                                                     
    echo json_encode(['ok' => false, 'error' => 'Message is required.']);       
    exit;                                                                        
}
$db = get_db();

// Real-time sensor snapshot — without this, the AI could only see past
// alerts, not the greenhouse's actual current condition.
$sensorStmt = $db->query(
    'SELECT gas_value, light_value, soil_value, temperature_value, humidity_value, water_level_value, '
    . 'recorded_at, TIMESTAMPDIFF(SECOND, recorded_at, NOW()) AS age_seconds '
    . 'FROM sensor_readings ORDER BY id DESC LIMIT 1'
);
$latest = $sensorStmt->fetch();

if ($latest && (int) $latest['age_seconds'] <= 15) {
    $waterPct = $latest['water_level_value'] !== null
        ? max(0, min(100, round(((float) $latest['water_level_value'] / 900) * 100)))
        : null;
    $sensorContext = "Live sensor readings as of {$latest['recorded_at']}:\n"
        . "- Air temperature: " . ($latest['temperature_value'] !== null ? $latest['temperature_value'] . '°C' : 'no data') . "\n"
        . "- Air humidity: " . ($latest['humidity_value'] !== null ? $latest['humidity_value'] . '%' : 'no data') . "\n"
        . "- Soil moisture (raw sensor value, lower = wetter): " . ($latest['soil_value'] ?? 'no data') . "\n"
        . "- Air quality / gas sensor (raw value, higher = worse): " . ($latest['gas_value'] ?? 'no data') . "\n"
        . "- Light level (raw sensor value): " . ($latest['light_value'] ?? 'no data') . "\n"
        . "- Water reservoir level: " . ($waterPct !== null ? $waterPct . '%' : 'no data') . "\n";
} else {
    $sensorContext = "The ESP32 device is currently disconnected — no live sensor readings are available right now.\n";
}

$stmt = $db->prepare(
'SELECT title, message, created_at FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 10'
);
$stmt->execute([$user['user_id']]);
$recentAlerts = $stmt->fetchAll();
$alertContext = "Recent alerts from this user's greenhouse:\n";
foreach ($recentAlerts as $alert) {
 $alertContext .= "- [{$alert['created_at']}] {$alert['title']}: {$alert['message']}\n";
}
if (empty($recentAlerts)) {
     $alertContext = "This user has no recent greenhouse alerts.\n";
}

$prompt = "You are a professional horticultural advisor for a smart greenhouse. Only answer questions about greenhouse care, plants, or this monitoring system — if asked something unrelated, say briefly that it's outside what you can help with, and redirect back to the greenhouse. Start with a direct, one-sentence answer to the exact question asked, then add 2 to 4 short supporting sentences. Give short, precise, science-based advice — no slang, no filler, no AI disclaimers, no markdown or asterisks, plain sentences only. Prefer clear everyday words over technical terms; if you do use a technical term, briefly explain what it means. Use the live sensor data and recent alerts below when relevant, and reference real numbers. If the data can't fully answer the question, say so briefly and give a practical rule of thumb instead of guessing.\n\n"
    . $sensorContext . "\n" . $alertContext
    . "\nUser question: " . $question;
    $url = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-3.6-flash:generateContent?key=' . urlencode(GEMINI_API_KEY);
    $ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
     CURLOPT_POST => true,
      CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
      CURLOPT_POSTFIELDS => json_encode([
          'contents' => [['role' => 'user', 'parts' => [['text' => $prompt]]]],
          // No maxOutputTokens cap, matching ai-insight.php/disease-detect.php —
          // gemini-3.6-flash spends part of any fixed token budget on a
          // hidden internal "thinking" pass before writing the real reply, so
          // a tight cap (this used to be 700) can cut the response off
          // mid-thought instead of returning the actual answer. This model
          // also rejects an explicit thinkingConfig override (400 Bad Request),
          // so leaving the budget uncapped is the only reliable fix.
          'generationConfig' => ['temperature' => 0.4],
            ]),
    CURLOPT_TIMEOUT => 20,
    ]);
    $response = curl_exec($ch); 
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($response === false || $httpCode !== 200) {
          http_response_code(502); 
    echo json_encode(['ok' => false, 'error' => 'AI is unavailable right now, try again.']); 
    exit;                                                                           
}
$data = json_decode($response, true);
$reply = $data['candidates'][0]['content']['parts'][0]['text'] ?? "Sorry, I couldn't think of an answer.";
echo json_encode(['ok' => true, 'reply' => $reply]);