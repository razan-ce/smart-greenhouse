<?php
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../auth.php';

$user = current_user();
if (!$user) {
    // not logged in, don't show anyone's photo
    http_response_code(401);
    exit;
}

// grab this user's own saved photo, nobody else's
$stmt = get_db()->prepare('SELECT photo_data, photo_mime FROM face_id WHERE user_id = ?');
$stmt->execute([$user['user_id']]);
$row = $stmt->fetch();

if (!$row || !$row['photo_data']) {
    // this user never saved a face photo, nothing to show
    http_response_code(404);
    exit;
}

// tell the browser "this is a picture" (jpeg/png/whatever it actually is)
header('Content-Type: ' . ($row['photo_mime'] ?: 'image/jpeg'));
header('Cache-Control: private, max-age=0, no-cache'); // always get the latest photo, never a stale cached one
echo $row['photo_data']; // just dump the raw image bytes straight out, that's the whole response
