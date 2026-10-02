<?php
// Passwordless sign-in: takes a live photo (no email/password given),
// compares it against every registered face photo via Gemini Vision, and
// logs in as the first confident match. This is a visual-similarity
// check, not cryptographic proof of identity — good enough for a
// capstone demo, not a substitute for real biometric auth in production.
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../config.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Use POST.']);
    exit;
}
if (!csrf_check($_POST['csrf_token'] ?? null)) {
    // stops someone else's site from tricking your browser into logging in here
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Your session expired. Reload the page and try again.']);
    exit;
}
if (empty($_FILES['photo']) || $_FILES['photo']['error'] !== UPLOAD_ERR_OK) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'No photo was captured, or the upload failed.']);
    exit;
}

$file = $_FILES['photo']; // the live photo just taken, trying to log in
if ($file['size'] > 8 * 1024 * 1024) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Photo must be 8MB or smaller.']);
    exit;
}
$allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
$liveType = mime_content_type($file['tmp_name']); // check the real file type
if (!isset($allowed[$liveType])) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Photo must be a JPEG, PNG, or WebP image.']);
    exit;
}
$liveData = base64_encode(file_get_contents($file['tmp_name'])); // turn it into text so gemini can read it

$db = get_db();
// grab every account that has a face saved — this is the "lineup" we compare against
$candidates = $db->query(
    "SELECT u.user_id, u.full_name, u.email, u.role, u.onboarding_completed, u.profile_image, "
    . "f.photo_data, f.photo_mime "
    . "FROM face_id f JOIN users u ON u.user_id = f.user_id "
    . "WHERE u.account_status = 'Active' AND f.photo_data IS NOT NULL "
    . "ORDER BY u.user_id LIMIT 25"
)->fetchAll();

if (!$candidates) {
    // nobody has face id set up at all, nothing to compare against
    http_response_code(404);
    echo json_encode(['ok' => false, 'error' => 'No accounts have Face ID set up yet.']);
    exit;
}

// One Gemini call per login attempt, not one per candidate — the live
// photo plus every registered face photo (numbered) go in a single
// request, and the model picks which number (if any) matches. Looping a
// separate API call per user burns through the free-tier quota (20
// requests) almost immediately as more accounts add Face ID; batching
// keeps this at a flat cost regardless of how many people have it set up.
$parts = []; // this becomes the list of photos we send to gemini
$indexToCandidate = []; // remembers which number = which user, so we can look them up later
foreach ($candidates as $candidate) {
    $idx = count($indexToCandidate); // 0, 1, 2, 3... one number per person
    $indexToCandidate[$idx] = $candidate;
    $parts[] = ['text' => "Registered photo #{$idx}:"];
    $parts[] = ['inline_data' => ['mime_type' => $candidate['photo_mime'] ?: 'image/jpeg', 'data' => base64_encode($candidate['photo_data'])]];
}

if (!$indexToCandidate) {
    http_response_code(404);
    echo json_encode(['ok' => false, 'error' => 'No accounts have Face ID set up yet.']);
    exit;
}

// THIS is "how it knows if it's the same face" — same Gemini model as the
// disease-detection feature, just asked a completely different question.
// this text block is that question. this is the actual "face recognition"
// part of Face ID — not a fingerprint-style algorithm, just this prompt.
$promptText = <<<PROMPT
The first image below is a live photo from someone trying to sign in. The
photos after it are numbered registered face photos on file. Determine
whether the live photo is the SAME PERSON as exactly one of the numbered
photos. Be reasonably strict: different people who look somewhat alike
should NOT be called a match. Respond with ONLY a JSON object:
{
  "matched_index": the matching photo's number, or null if no confident match,
  "confidence": number 0-100
}
PROMPT;//the actual instruction sent to Gemini asking "is this the same person."

// stick the live photo first, then every registered photo after it, all in one request
$allParts = array_merge(
    [['text' => $promptText], ['text' => 'Live photo:'], ['inline_data' => ['mime_type' => $liveType, 'data' => $liveData]]],
    $parts
);

$match = null; // starts empty, filled in below if we find a real match
$ch = curl_init('https://generativelanguage.googleapis.com/v1beta/models/gemini-3.6-flash:generateContent?key=' . urlencode(GEMINI_API_KEY));
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
    CURLOPT_POSTFIELDS => json_encode([
        'contents' => [['role' => 'user', 'parts' => $allParts]],
        'generationConfig' => ['temperature' => 0.1, 'responseMimeType' => 'application/json'],
    ]),
    CURLOPT_TIMEOUT => 30,
]);
$response = curl_exec($ch); // send it and wait for gemini to answer
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($response === false || $httpCode !== 200) {
    // gemini didn't respond properly, can't check the face right now
    http_response_code(502);
    echo json_encode(['ok' => false, 'error' => 'Face matching is unavailable right now, try again or use your password.']);
    exit;
}

$data = json_decode($response, true);
$resultText = $data['candidates'][0]['content']['parts'][0]['text'] ?? null;
$result = $resultText ? json_decode($resultText, true) : null;

// only trust the match if gemini is at least 75% sure — anything lower, treat as no match
if ($result && isset($result['matched_index']) && $result['matched_index'] !== null //confidence check that decides whether to accept the match.
    && isset($indexToCandidate[(int) $result['matched_index']])
    && (float) ($result['confidence'] ?? 0) >= 75
) {
    $match = $indexToCandidate[(int) $result['matched_index']];
}

if (!$match) {
    // no confident match found, can't log this person in
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'No matching face found. Try again with better lighting, or use your password.']);
    exit;
}

// found a match! log them in, same as a normal password login would
session_regenerate_id(true); // fresh session id, safer than reusing the old one
$sessionUser = [
    'user_id' => $match['user_id'],
    'full_name' => $match['full_name'],
    'email' => $match['email'],
    'role' => $match['role'],
    'onboarding_completed' => (bool) $match['onboarding_completed'],
    'profile_image' => $match['profile_image'],
    'has_face_id' => true,
];
$_SESSION['user'] = $sessionUser; // this is what makes them actually "logged in"

echo json_encode(['ok' => true, 'redirect' => $sessionUser['role'] === 'Admin' ? 'admin.php' : 'dashboard.php']);
