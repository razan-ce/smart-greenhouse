<?php



if (session_status() === PHP_SESSION_NONE) {
    session_name('GROVIASESSID');
    session_start();
}

function current_user(): ?array {
    return $_SESSION['user'] ?? null;
}



function gh_base_path(): string {
    return dirname($_SERVER['SCRIPT_NAME']);
}

function require_login(): array {
    $user = current_user();
    if (!$user) {
        header('Location: ' . gh_base_path() . '/login.php');
        exit;
    }
    return $user;
}

function require_admin(): array {
    $user = require_login();
    if ($user['role'] !== 'Admin') {
        header('Location: ' . gh_base_path() . '/dashboard.php');
        exit;
    }
    return $user;
}

// The Admin owns the one greenhouse and always has access; a 'User' is a
// worker who must be explicitly granted access first (see greenhouse_access
// table). Relies on db.php already being loaded, same as every caller.
function require_worker_access(): array {
    $user = require_login();
    if ($user['role'] === 'Admin') {
        return $user;
    }
    if (!current_user_has_worker_access($user)) {
        header('Location: ' . gh_base_path() . '/pending-access.php');
        exit;
    }
    get_db()->prepare('UPDATE users SET last_seen = NOW() WHERE user_id = ?')->execute([$user['user_id']]);//updates last seen only for granted workers
    return $user;
}

// Same check as require_worker_access(), but returns a bool instead of
// redirecting — for API endpoints that need to reject with JSON, not a
// page redirect.
function current_user_has_worker_access(?array $user = null): bool {
    $user = $user ?? current_user();
    if (!$user) {
        return false;
    }
    if ($user['role'] === 'Admin') {
        return true;
    }
    $stmt = get_db()->prepare('SELECT 1 FROM greenhouse_access WHERE user_id = ? LIMIT 1');
    $stmt->execute([$user['user_id']]);
    return (bool) $stmt->fetchColumn();
}

function csrf_token(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_check(?string $token): bool {
    return is_string($token) && !empty($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}
