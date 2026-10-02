<?php
// Shown to a 'User' (worker) account that hasn't been granted access to the
// greenhouse yet. Admins never see this — they own the greenhouse. Uses
// plain require_login(), not require_worker_access(), since that would
// redirect back here in a loop.
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';

$user = require_login();
if ($user['role'] === 'Admin') {
    header('Location: admin.php');
    exit;
}
if (current_user_has_worker_access($user)) {
    // Access was granted since this tab was last loaded — no need to wait here.
    header('Location: dashboard.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Waiting for Access | Grovia</title>
<meta name="theme-color" content="#16a34a">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@500;600;700&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="css/style.css?v=4">
<style>
  .pending-section { min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 40px 24px; background: var(--bg-soft); }
  .pending-card { width: 100%; max-width: 440px; background: #fff; border: 1px solid var(--border); border-radius: var(--radius-lg); box-shadow: var(--shadow-lg); padding: 44px 36px; text-align: center; }
  .pending-icon { width: 64px; height: 64px; border-radius: 20px; display: flex; align-items: center; justify-content: center; margin: 0 auto 20px; background: var(--green-50); color: var(--green-600); }
  .pending-title { font-size: 1.4rem; margin-bottom: 10px; }
  .pending-message { color: var(--ink-dim); font-size: 0.95rem; line-height: 1.6; }
  .pending-actions { margin-top: 26px; display: flex; flex-direction: column; gap: 10px; }
</style>
</head>
<body>
<main>
  <section class="pending-section">
    <div class="pending-card">
      <div class="pending-icon">
        <svg viewBox="0 0 24 24" width="30" height="30" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 3"/></svg>
      </div>
      <h1 class="pending-title">Waiting for Access</h1>
      <p class="pending-message">Your account isn't connected to the greenhouse yet. The owner needs to grant you access before you can see sensors, controls, or anything else. This page will let you in automatically once that happens.</p>
      <div class="pending-actions">
        <a href="settings.php" class="btn btn-primary">Message the Owner</a>
        <a href="logout.php" class="btn" style="border:1.5px solid var(--border); color:var(--ink);">Log Out</a>
      </div>
    </div>
  </section>
</main>
</body>
</html>
