<?php

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';

$user = require_admin();
header('Content-Type: application/json');

$db = get_db();

$totalWorkers = (int) $db->query("SELECT COUNT(*) FROM users WHERE role = 'User'")->fetchColumn();
$workersWithAccess = (int) $db->query('SELECT COUNT(*) FROM greenhouse_access')->fetchColumn();
$totalUsers = (int) $db->query('SELECT COUNT(*) FROM users')->fetchColumn();

echo json_encode([
    'total_workers' => $totalWorkers,
    'workers_with_access' => $workersWithAccess,
    'total_users' => $totalUsers,
]);
