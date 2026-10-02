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
    echo json_encode(['ok' => false, 'error' => 'Use POST.']);
    exit;
}

if (empty($_FILES['photo']) || $_FILES['photo']['error'] !== UPLOAD_ERR_OK) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'No photo was uploaded, or the upload failed.']);
    exit;
}

$file = $_FILES['photo'];

$maxBytes = 8 * 1024 * 1024;
if ($file['size'] > $maxBytes) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Photo must be 8MB or smaller.']);
    exit;
}

// Trust the actual file bytes, not the client-supplied MIME type.
$allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
$detectedType = mime_content_type($file['tmp_name']);
if (!isset($allowed[$detectedType])) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Photo must be a JPEG, PNG, or WebP image.']);
    exit;
}
$ext = $allowed[$detectedType];

$db = get_db();

$uploadDir = __DIR__ . '/../uploads/plant-photos/';
$publicPathPrefix = 'uploads/plant-photos/';
$filename = 'capture_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . $ext;

if (!move_uploaded_file($file['tmp_name'], $uploadDir . $filename)) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Could not save the uploaded photo.']);
    exit;
}
$publicPath = $publicPathPrefix . $filename;

// Ask Gemini as a plant pathologist, forcing structured JSON output so the
// result can be reliably stored/displayed rather than parsed out of prose.
$imageData = base64_encode(file_get_contents($uploadDir . $filename));

// THIS is "how it knows about diseases" — it's not a special disease-detecting
// AI, it's the exact same Gemini model as everywhere else in the app. this text
// block is the specific question we're asking it this time. change this prompt
// and you change what the AI is being asked to do, without touching any other feature.
$prompt = <<<PROMPT
You are an expert plant pathologist examining a photo taken inside a greenhouse.
Look closely for signs of disease, pest damage, or nutrient deficiency on any visible
plant, leaf, or crop in the image. If no plant is clearly visible, say so honestly
rather than guessing.

Respond with ONLY a JSON object, no other text, using exactly these fields:
{
  "crop_name": "your best guess at the plant/crop type, or 'Unknown'",
  "status": one of "Healthy", "Disease Detected", "Pest Damage", "Nutrient Deficiency", "No Plant Visible",
  "disease_name": "specific name if found (e.g. 'Leaf Spot (Cercospora)'), or 'None'",
  "confidence": number 0-100, your confidence in this diagnosis,
  "severity": one of "None", "Mild", "Moderate", "Severe",
  "affected_area_pct": number 0-100, estimated percent of visible plant area affected,
  "recommendation": "a concise, actionable recommendation, 1-3 sentences"
}
PROMPT;

$url = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-3.6-flash:generateContent?key=' . urlencode(GEMINI_API_KEY);

$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
    CURLOPT_POSTFIELDS => json_encode([
        'contents' => [[
            'role' => 'user',
            'parts' => [
                ['text' => $prompt],
                ['inline_data' => ['mime_type' => $detectedType, 'data' => $imageData]],
            ],
        ]],
        'generationConfig' => ['temperature' => 0.3, 'responseMimeType' => 'application/json'],
    ]),
    CURLOPT_TIMEOUT => 30,
]);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($response === false || $httpCode !== 200) {
    http_response_code(502);
    echo json_encode(['ok' => false, 'error' => 'AI analysis is unavailable right now, try again.']);
    exit;
}

$data = json_decode($response, true);
$resultText = $data['candidates'][0]['content']['parts'][0]['text'] ?? null;
$result = $resultText ? json_decode($resultText, true) : null;

if (!$result || !isset($result['status'])) {
    http_response_code(502);
    echo json_encode(['ok' => false, 'error' => "Couldn't understand the AI's analysis, try again."]);
    exit;
}

$cropName = substr((string) ($result['crop_name'] ?? 'Unknown'), 0, 100);
$status = substr((string) $result['status'], 0, 50);
$diseaseName = ($result['disease_name'] ?? 'None') !== 'None' ? substr((string) $result['disease_name'], 0, 150) : null;
$confidence = isset($result['confidence']) ? max(0, min(100, (float) $result['confidence'])) : null;
$severity = substr((string) ($result['severity'] ?? 'None'), 0, 20);
$affectedArea = isset($result['affected_area_pct']) ? max(0, min(100, (float) $result['affected_area_pct'])) : null;
$recommendation = (string) ($result['recommendation'] ?? '');

$insert = $db->prepare(
    'INSERT INTO disease_detections (user_id, image_path, crop_name, status, disease_name, confidence, severity, affected_area_pct, recommendation) '
    . 'VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
);
$insert->execute([$user['user_id'], $publicPath, $cropName, $status, $diseaseName, $confidence, $severity, $affectedArea, $recommendation]);

echo json_encode([
    'ok' => true,
    'id' => (int) $db->lastInsertId(),
    'image_path' => $publicPath,
    'crop_name' => $cropName,
    'status' => $status,
    'disease_name' => $diseaseName,
    'confidence' => $confidence,
    'severity' => $severity,
    'affected_area_pct' => $affectedArea,
    'recommendation' => $recommendation,
]);
