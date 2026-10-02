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
$currentPassword = (string) ($input['current_password'] ?? '');
$newPassword = (string) ($input['new_password'] ?? '');
$confirmPassword = (string) ($input['confirm_password'] ?? '');

if ($currentPassword === '' || $newPassword === '') {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Fill in both your current and new password.']);
    exit;
}
if (strlen($newPassword) < 8) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'New password must be at least 8 characters.']);
    exit;
}
if ($newPassword !== $confirmPassword) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'New password and confirmation do not match.']);
    exit;
}

$db = get_db();
$stmt = $db->prepare('SELECT password FROM users WHERE user_id = ?');
$stmt->execute([$user['user_id']]);
$row = $stmt->fetch();

if (!$row || !password_verify($currentPassword, $row['password'])) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Your current password is incorrect.']);
    exit;
}

$hash = password_hash($newPassword, PASSWORD_DEFAULT);
$db->prepare('UPDATE users SET password = ? WHERE user_id = ?')->execute([$hash, $user['user_id']]);

echo json_encode(['ok' => true]);
