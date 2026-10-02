<?php

require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../config.php';

header('Content-Type: application/json');

$admin = current_user();
if (!$admin) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'Not logged in.']);
    exit;
}
if ($admin['role'] !== 'Admin') {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Admins only.']);
    exit;
}

$db = get_db();
$workerId = (int) ($_GET['user_id'] ?? 0);

$stmt = $db->prepare("SELECT user_id, full_name, email, account_status, last_seen, created_at FROM users WHERE user_id = ? AND role = 'User'");
$stmt->execute([$workerId]);
$worker = $stmt->fetch();
if (!$worker) {
    http_response_code(404);
    echo json_encode(['ok' => false, 'error' => 'Worker not found.']);
    exit;
}

$stmt = $db->prepare('SELECT granted_at FROM greenhouse_access WHERE user_id = ?');
$stmt->execute([$workerId]);
$grantedAt = $stmt->fetchColumn();

$stmt = $db->prepare('SELECT status, COUNT(*) AS n FROM schedule_tasks WHERE user_id = ? GROUP BY status');
$stmt->execute([$workerId]);
$taskRows = $stmt->fetchAll();
$taskCounts = ['Pending' => 0, 'Completed' => 0, 'Skipped' => 0];
foreach ($taskRows as $r) {
    $taskCounts[$r['status']] = (int) $r['n'];
}
$totalTasks = array_sum($taskCounts);

// Real numbers, computed here rather than left for the model to guess at —
// the model only ever turns these into sentences, it never invents them.
$daysWithAccess = $grantedAt ? (int) floor((time() - strtotime($grantedAt)) / 86400) : null;
$daysSinceActive = $worker['last_seen'] ? (int) floor((time() - strtotime($worker['last_seen'])) / 86400) : null;
$completionRate = $totalTasks > 0 ? round(($taskCounts['Completed'] / $totalTasks) * 100) : null;

$stats = [
    'days_with_access' => $daysWithAccess,
    'days_since_active' => $daysSinceActive,
    'tasks_completed' => $taskCounts['Completed'],
    'tasks_pending' => $taskCounts['Pending'],
    'tasks_skipped' => $taskCounts['Skipped'],
    'completion_rate' => $completionRate,
];

$cacheHours = 24;
$forceRefresh = isset($_GET['refresh']) && $_GET['refresh'] === '1';

$stmt = $db->prepare('SELECT * FROM worker_reports WHERE user_id = ? ORDER BY generated_at DESC LIMIT 1');
$stmt->execute([$workerId]);
$cached = $stmt->fetch();

$ageHours = $cached ? (time() - strtotime($cached['generated_at'])) / 3600 : null;

$minRefreshMinutes = 5;
$canForceRefresh = !$cached || $ageHours * 60 >= $minRefreshMinutes;

if ($cached && !($forceRefresh && $canForceRefresh) && $ageHours < $cacheHours) {
    echo json_encode([
        'ok' => true, 'cached' => true, 'stats' => $stats,
        'overview' => $cached['overview'], 'recommendation' => $cached['recommendation'],
        'generated_at' => $cached['generated_at'],
    ]);
    exit;
}

$context = "Worker: {$worker['full_name']} ({$worker['email']})\n";
$context .= "Account status: {$worker['account_status']}\n";
$context .= "Account created: {$worker['created_at']}\n";
$context .= $grantedAt ? "Granted greenhouse access {$daysWithAccess} day(s) ago, on {$grantedAt}.\n" : "Has not been granted greenhouse access yet.\n";
$context .= $worker['last_seen'] ? "Last active {$daysSinceActive} day(s) ago ({$worker['last_seen']}).\n" : "Has never been recorded as active.\n";
if ($totalTasks > 0) {
    $context .= "Schedule tasks — {$totalTasks} total: {$taskCounts['Completed']} completed, {$taskCounts['Pending']} pending, {$taskCounts['Skipped']} skipped ({$completionRate}% completion rate).\n";
} else {
    $context .= "No schedule tasks recorded for this worker.\n";
}

$prompt = <<<PROMPT
You are writing a section of a formal workforce performance report for a
greenhouse owner, covering ONE worker. Use ONLY the real data given below —
never invent numbers, dates, or events that aren't present. If the data is
too sparse to say much (e.g. never granted access, never active, no tasks),
say that plainly and professionally rather than padding it out.

Respond with ONLY a JSON object, no other text:
{
  "overview": "2-4 sentences, formal and professional, describing this worker's overall engagement and task performance, grounded in the real numbers given",
  "recommendation": "one concrete next step for the owner regarding this worker, 1-2 sentences"
}

DATA:
{$context}
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
            'ok' => true, 'cached' => true, 'stats' => $stats,
            'overview' => $cached['overview'], 'recommendation' => $cached['recommendation'],
            'generated_at' => $cached['generated_at'],
        ]);
        exit;
    }
    http_response_code(502);
    echo json_encode(['ok' => false, 'error' => 'The report is unavailable right now, try again.']);
    exit;
}

$data = json_decode($response, true);
$resultText = $data['candidates'][0]['content']['parts'][0]['text'] ?? null;
$result = $resultText ? json_decode($resultText, true) : null;

if (!$result || !isset($result['overview'])) {
    http_response_code(502);
    echo json_encode(['ok' => false, 'error' => "Couldn't generate the report, try again."]);
    exit;
}

$overview = (string) $result['overview'];
$recommendation = (string) ($result['recommendation'] ?? '');

$insert = $db->prepare('INSERT INTO worker_reports (user_id, overview, recommendation) VALUES (?, ?, ?)');
$insert->execute([$workerId, $overview, $recommendation]);

echo json_encode([
    'ok' => true, 'cached' => false, 'stats' => $stats,
    'overview' => $overview, 'recommendation' => $recommendation,
    'generated_at' => date('Y-m-d H:i:s'),
]);
