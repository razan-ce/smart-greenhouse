<?php

require_once __DIR__ . '/../db.php';   // gives us get_db()
require_once __DIR__ . '/../auth.php'; // gives us current_user()
require_once __DIR__ . '/../config.php'; // has the GEMINI_API_KEY

header('Content-Type: application/json'); // we always answer in json here

$user = current_user();
if (!$user) {
    // no one logged in, stop right here
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'Not logged in.']);
    exit;
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    // this page only makes sense as a POST (someone sending us a photo)
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Use POST.']);
    exit;
}
if (empty($_FILES['photo']) || $_FILES['photo']['error'] !== UPLOAD_ERR_OK) {
    // no photo actually came through, or the upload broke somehow
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'No photo was captured, or the upload failed.']);
    exit;
}

$file = $_FILES['photo']; // the uploaded photo, as php sees it

$maxBytes = 8 * 1024 * 1024; // 8mb cap, dont let someone upload a huge file
if ($file['size'] > $maxBytes) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Photo must be 8MB or smaller.']);
    exit;
}

$allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
$detectedType = mime_content_type($file['tmp_name']); // check the REAL file type, not just what the browser claims
if (!isset($allowed[$detectedType])) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Photo must be a JPEG, PNG, or WebP image.']);
    exit;
}
$imageData = base64_encode(file_get_contents($file['tmp_name'])); // turn the photo into text so we can send it to gemini

// Sanity-check the capture before saving it as someone's login credential
// — reject blurry/empty/multi-face shots up front rather than silently
// saving a photo that Face ID sign-in could never actually match against.
$prompt = <<<PROMPT
Look at this photo. Respond with ONLY a JSON object, no other text:
{
  "has_exactly_one_face": true or false,
  "is_clear": true or false, meaning the face is in focus and reasonably well lit,
  "reason": "short reason if either is false, otherwise empty string"
}
PROMPT;

// ask gemini "is this actually a good, usable face photo?" before we save it
$ch = curl_init('https://generativelanguage.googleapis.com/v1beta/models/gemini-3.6-flash:generateContent?key=' . urlencode(GEMINI_API_KEY));
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
    CURLOPT_POSTFIELDS => json_encode([
        'contents' => [[
            'role' => 'user',
            'parts' => [
                ['text' => $prompt],
                ['inline_data' => ['mime_type' => $detectedType, 'data' => $imageData]], // the actual photo goes here
            ],
        ]],
        'generationConfig' => ['temperature' => 0.1, 'responseMimeType' => 'application/json'],
    ]),
    CURLOPT_TIMEOUT => 20,
]);
$response = curl_exec($ch); // actually send the request and wait for gemini's answer
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($response !== false && $httpCode === 200) {
    $data = json_decode($response, true);
    $resultText = $data['candidates'][0]['content']['parts'][0]['text'] ?? null;
    $result = $resultText ? json_decode($resultText, true) : null;
    if ($result && (empty($result['has_exactly_one_face']) || empty($result['is_clear']))) {
        // gemini says this photo isn't good enough, tell the user why and stop
        http_response_code(422);
        echo json_encode(['ok' => false, 'error' => $result['reason'] ?: 'Couldn\'t find a clear, single face in that photo. Try again with better lighting, facing the camera.']);
        exit;
    }
}
// If the AI check itself fails (network hiccup etc.), don't block
// registration on it — just save the photo as captured.

// The photo lives only in the database now — no file is ever written to
// disk. $imageData above is base64 (needed for the Gemini call); the raw
// bytes read here are what actually get stored.
$rawBytes = file_get_contents($file['tmp_name']); // the actual photo bytes, not the text version

$db = get_db();
// insert a new row, but if this user already has one (they registered before),
// just overwrite it instead of making a second row
$upsert = $db->prepare(
    'INSERT INTO face_id (user_id, photo_data, photo_mime) VALUES (?, ?, ?) '
    . 'ON DUPLICATE KEY UPDATE photo_data = VALUES(photo_data), photo_mime = VALUES(photo_mime), created_at = CURRENT_TIMESTAMP'
);
$upsert->execute([$user['user_id'], $rawBytes, $detectedType]); // actually save it to the database

$_SESSION['user']['has_face_id'] = true; // remember this for the rest of the session

echo json_encode(['ok' => true]); // tell the browser it worked
