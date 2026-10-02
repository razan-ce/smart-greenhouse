<?php
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../auth.php';

header('Content-Type: application/json');

$user = current_user();
if (!$user) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'Not logged in.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Use POST.']);
    exit;
}

$input = json_decode((string) file_get_contents('php://input'), true);

$fullName = trim((string) ($input['full_name'] ?? ''));
$email = trim((string) ($input['email'] ?? ''));
$phone = trim((string) ($input['phone'] ?? ''));

if ($fullName === '') {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Enter a full name.']);
    exit;
}
if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Enter a valid email address.']);
    exit;
}

$db = get_db();

$stmt = $db->prepare('SELECT user_id FROM users WHERE email = ? AND user_id != ?');
$stmt->execute([$email, $user['user_id']]);
if ($stmt->fetch()) {
    http_response_code(409);
    echo json_encode(['ok' => false, 'error' => 'That email is already in use by another account.']);
    exit;
}

// Changing the email means it hasn't been proven to belong to them anymore —
// same "unverified until proven" rule signup uses, not a special case.
// (email_verified isn't in the session, so this compares against the DB's
// current value rather than needing it there.)
$currentEmail = $db->prepare('SELECT email FROM users WHERE user_id = ?');
$currentEmail->execute([$user['user_id']]);
$emailChanged = strcasecmp($email, (string) $currentEmail->fetchColumn()) !== 0;

if ($emailChanged) {
    $stmt = $db->prepare('UPDATE users SET full_name = ?, email = ?, phone = ?, email_verified = 0 WHERE user_id = ?');
} else {
    $stmt = $db->prepare('UPDATE users SET full_name = ?, email = ?, phone = ? WHERE user_id = ?');
}
$stmt->execute([$fullName, $email, $phone !== '' ? $phone : null, $user['user_id']]);

// Keep the session in sync so the topbar/sidebar reflect the change
// immediately, without requiring the user to log back in.
$_SESSION['user']['full_name'] = $fullName;
$_SESSION['user']['email'] = $email;

echo json_encode(['ok' => true, 'email_changed' => $emailChanged]);
