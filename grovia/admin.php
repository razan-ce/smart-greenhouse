<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/device-lib.php';
require_once __DIR__ . '/admin-lib.php';

$user = require_admin();
require_once __DIR__ . '/partials/current-user.php';
$db = get_db();



if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $postTab = in_array($_POST['tab'] ?? '', ['overview', 'users'], true) ? $_POST['tab'] : 'overview';

    if (!csrf_check($_POST['csrf_token'] ?? null)) {
        $_SESSION['admin_flash'] = ['type' => 'error', 'text' => 'Your session expired. Please try again.'];
    } elseif (($_POST['action'] ?? '') === 'add_user') {
        $fullName = trim($_POST['full_name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $password = (string)($_POST['password'] ?? '');
        $role = ($_POST['role'] ?? '') === 'Admin' ? 'Admin' : 'User';

        if ($fullName === '') {
            $_SESSION['admin_flash'] = ['type' => 'error', 'text' => 'Enter a full name.'];
        } elseif ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $_SESSION['admin_flash'] = ['type' => 'error', 'text' => 'Enter a valid email address.'];
        } elseif (strlen($password) < 8) {
            $_SESSION['admin_flash'] = ['type' => 'error', 'text' => 'Password must be at least 8 characters.'];
        } else {
            $stmt = $db->prepare('SELECT user_id FROM users WHERE email = ?');
            $stmt->execute([$email]);
            if ($stmt->fetch()) {
                $_SESSION['admin_flash'] = ['type' => 'error', 'text' => 'An account with that email already exists.'];
            } else {
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $db->prepare('INSERT INTO users (full_name, email, password, role, onboarding_completed, account_status) VALUES (?, ?, ?, ?, 1, ?)');
                $stmt->execute([$fullName, $email, $hash, $role, 'Active']);
                gh_log_admin_action($db, (int)$user['user_id'], 'Add User', 'users', (int)$db->lastInsertId(), "Added {$role} account for {$email}");
                $_SESSION['admin_flash'] = ['type' => 'success', 'text' => "User {$email} created."];
            }
        }
    } elseif (($_POST['action'] ?? '') === 'delete_user') {
        $targetId = (int)($_POST['user_id'] ?? 0);
        $greenhouseOwnerId = (int) $db->query('SELECT user_id FROM greenhouses ORDER BY created_at ASC LIMIT 1')->fetchColumn();
        if ($targetId === (int)$user['user_id']) {
            $_SESSION['admin_flash'] = ['type' => 'error', 'text' => 'You cannot delete your own account.'];
        } elseif ($targetId === $greenhouseOwnerId) {
            $_SESSION['admin_flash'] = ['type' => 'error', 'text' => 'This account owns the greenhouse — deleting it would delete the greenhouse and its sensor history too.'];
        } else {
            $stmt = $db->prepare('SELECT email FROM users WHERE user_id = ?');
            $stmt->execute([$targetId]);
            $target = $stmt->fetch();
            if (!$target) {
                $_SESSION['admin_flash'] = ['type' => 'error', 'text' => 'User not found.'];
            } else {
                $stmt = $db->prepare('DELETE FROM users WHERE user_id = ?');
                $stmt->execute([$targetId]);
                gh_log_admin_action($db, (int)$user['user_id'], 'Delete User', 'users', $targetId, "Deleted user {$target['email']}");
                $_SESSION['admin_flash'] = ['type' => 'success', 'text' => "User {$target['email']} deleted."];
            }
        }
    } elseif (($_POST['action'] ?? '') === 'toggle_access') {
        $targetId = (int)($_POST['user_id'] ?? 0);
        $stmt = $db->prepare('SELECT user_id, email, role FROM users WHERE user_id = ?');
        $stmt->execute([$targetId]);
        $target = $stmt->fetch();
        if (!$target) {
            $_SESSION['admin_flash'] = ['type' => 'error', 'text' => 'User not found.'];
        } else {
            $result = gh_toggle_greenhouse_access($db, $target, (int) $user['user_id']);
            $_SESSION['admin_flash'] = ['type' => $result['ok'] ? 'success' : 'error', 'text' => $result['text']];
        }
    } elseif (($_POST['action'] ?? '') === 'toggle_status') {
        $targetId = (int)($_POST['user_id'] ?? 0);
        if ($targetId === (int)$user['user_id']) {
            $_SESSION['admin_flash'] = ['type' => 'error', 'text' => 'You cannot deactivate your own account.'];
        } else {
            $stmt = $db->prepare('SELECT email, account_status FROM users WHERE user_id = ?');
            $stmt->execute([$targetId]);
            $target = $stmt->fetch();
            if (!$target) {
                $_SESSION['admin_flash'] = ['type' => 'error', 'text' => 'User not found.'];
            } else {
                $newStatus = $target['account_status'] === 'Active' ? 'Inactive' : 'Active';
                $stmt = $db->prepare('UPDATE users SET account_status = ? WHERE user_id = ?');
                $stmt->execute([$newStatus, $targetId]);
                gh_log_admin_action($db, (int)$user['user_id'], 'Update User Status', 'users', $targetId, "Set {$target['email']} to {$newStatus}");
                $_SESSION['admin_flash'] = ['type' => 'success', 'text' => "{$target['email']} is now {$newStatus}."];
            }
        }
    }
    header('Location: admin.php?tab=' . $postTab);
    exit;
}

$flash = $_SESSION['admin_flash'] ?? null;
unset($_SESSION['admin_flash']);

$activeTab = in_array($_GET['tab'] ?? '', ['overview', 'users'], true) ? $_GET['tab'] : 'overview';

function gh_row_initials(string $name): string {
    $parts = preg_split('/\s+/', trim($name));
    if (count($parts) >= 2) return strtoupper(mb_substr($parts[0], 0, 1) . mb_substr($parts[1], 0, 1));
    return strtoupper(mb_substr($name, 0, 2));
}


$totalUsers = (int) $db->query('SELECT COUNT(*) FROM users')->fetchColumn();
$totalGreenhouses = (int) $db->query('SELECT COUNT(*) FROM greenhouses')->fetchColumn();


$deviceState = $db->query('SELECT esp32_connected FROM device_state WHERE id = 1')->fetch();
$deviceOnline = (bool) ($deviceState['esp32_connected'] ?? false);
$lastReading = $db->query('SELECT recorded_at FROM sensor_readings ORDER BY id DESC LIMIT 1')->fetch();
$deviceLastSeen = $lastReading['recorded_at'] ?? null;

$reportsGenerated = (int) $db->query('SELECT COUNT(*) FROM reports')->fetchColumn();
$totalWorkers = (int) $db->query("SELECT COUNT(*) FROM users WHERE role = 'User'")->fetchColumn();
$workersWithAccess = (int) $db->query('SELECT COUNT(*) FROM greenhouse_access')->fetchColumn();

$recentActivity = $db->query(
    'SELECT l.*, u.full_name AS admin_name
     FROM admin_activity_log l
     JOIN users u ON u.user_id = l.admin_id
     ORDER BY l.action_time DESC LIMIT 8'
)->fetchAll();

/* =====================================================================
   USERS TAB DATA
   ===================================================================== */
$verifiedFilter = $_GET['verified'] ?? 'all';
$verifiedWhere = '';
if ($verifiedFilter === 'verified') $verifiedWhere = 'WHERE u.email_verified = 1';
elseif ($verifiedFilter === 'unverified') $verifiedWhere = 'WHERE u.email_verified = 0';

$stmt = $db->query(
    "SELECT u.user_id, u.full_name, u.email, u.profile_image, u.role, u.account_status, u.created_at, u.email_verified,
            ga.user_id IS NOT NULL AS has_access
     FROM users u
     LEFT JOIN greenhouse_access ga ON ga.user_id = u.user_id
     {$verifiedWhere}
     ORDER BY u.created_at DESC"
);
$allUsers = $stmt->fetchAll();

$token = csrf_token();
$gh_page_title = 'Admin Console | Grovia';
require __DIR__ . '/partials/head.php';
?>
<body class="dash-body">

<?php require __DIR__ . '/partials/admin-sidebar.php'; ?>

<div class="lg:pl-[272px]">
  <?php require __DIR__ . '/partials/topbar.php'; ?>

  <main class="mx-auto max-w-[1600px] px-5 py-7 sm:px-8 lg:px-10 lg:py-9">
    <div class="flex flex-col gap-6">

      <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
          <div class="mb-2 inline-flex items-center gap-2 rounded-full bg-light px-3 py-1.5 text-xs font-semibold text-secondary">
            <i data-lucide="shield-check" class="h-3.5 w-3.5"></i>
            Admin Console
          </div>
          <h1 class="text-[26px] font-semibold tracking-tight text-text-primary sm:text-[30px]">Grovia Admin</h1>
          <p class="mt-1 text-[15px] text-text-secondary">Manage workers and platform activity — signed in as <?= htmlspecialchars($user['email'], ENT_QUOTES) ?></p>
        </div>
        <span class="inline-flex items-center gap-2 rounded-full border border-border/60 bg-white px-3.5 py-2 text-xs font-medium text-text-secondary shadow-soft" id="adminLiveIndicator">
          <span class="relative flex h-2 w-2">
            <span class="pulse-dot absolute inline-flex h-full w-full rounded-full bg-primary opacity-60"></span>
            <span class="relative inline-flex h-2 w-2 rounded-full bg-primary"></span>
          </span>
          Live — updates automatically
        </span>
      </div>

      <?php if ($flash): ?>
        <div class="rounded-xl border px-4 py-3 text-sm font-semibold <?= $flash['type'] === 'success' ? 'border-primary/20 bg-light text-secondary' : 'border-red-200 bg-red-50 text-red-600' ?>">
          <?= htmlspecialchars($flash['text'], ENT_QUOTES) ?>
        </div>
      <?php endif; ?>

      <!-- Tab bar -->
      <div class="inline-flex w-fit flex-wrap gap-1 rounded-2xl border border-border/60 bg-white p-1.5 shadow-soft">
        <?php foreach ([
          'overview' => ['label' => 'Overview', 'icon' => 'layout-dashboard'],
          'users' => ['label' => 'Users', 'icon' => 'users'],
        ] as $tabKey => $t): ?>
        <button type="button" data-tab-btn="<?= $tabKey ?>" class="admin-tab-btn inline-flex items-center gap-2 rounded-xl px-4 py-2 text-sm font-semibold transition-all duration-200 <?= $activeTab === $tabKey ? 'bg-green-gradient text-white shadow-glow' : 'text-text-secondary hover:bg-neutral-50 hover:text-text-primary' ?>">
          <i data-lucide="<?= $t['icon'] ?>" class="h-4 w-4"></i>
          <?= htmlspecialchars($t['label'], ENT_QUOTES) ?>
        </button>
        <?php endforeach; ?>
      </div>

    
      <div data-tab-panel="overview" class="flex flex-col gap-6<?= $activeTab === 'overview' ? '' : ' hidden' ?>">

        <div class="grid grid-cols-2 gap-4 sm:grid-cols-3 xl:grid-cols-5">
          <?php
          $stats = [
              ['label' => 'Total Users', 'value' => $totalUsers, 'icon' => 'users', 'id' => 'statTotalUsers'],
              ['label' => 'Total Greenhouses', 'value' => $totalGreenhouses, 'icon' => 'sprout'],
              ['label' => 'Device Status', 'value' => $deviceOnline ? 'Online' : 'Offline', 'icon' => $deviceOnline ? 'radio-tower' : 'wifi-off'],
              ['label' => 'Reports Generated', 'value' => $reportsGenerated, 'icon' => 'file-bar-chart-2'],
              ['label' => 'Total Workers', 'value' => $totalWorkers, 'icon' => 'users', 'id' => 'statTotalWorkers'],
              ['label' => 'Workers With Access', 'value' => $workersWithAccess, 'icon' => 'shield-check', 'id' => 'statWorkersWithAccess'],
          ];
          foreach ($stats as $s): ?>
          <div class="group rounded-2xl border border-border/60 bg-white p-5 shadow-soft transition-all duration-300 hover:-translate-y-1 hover:shadow-soft-lg">
            <div class="mb-3 flex h-9 w-9 items-center justify-center rounded-xl bg-light transition-transform duration-300 group-hover:scale-105">
              <i data-lucide="<?= $s['icon'] ?>" class="h-4 w-4 text-secondary" stroke-width="2.25"></i>
            </div>
            <div class="text-xl font-semibold text-text-primary"<?= isset($s['id']) ? ' id="' . $s['id'] . '"' : '' ?>><?= htmlspecialchars((string)$s['value'], ENT_QUOTES) ?></div>
            <div class="mt-0.5 text-xs text-text-secondary"><?= htmlspecialchars($s['label'], ENT_QUOTES) ?></div>
          </div>
          <?php endforeach; ?>
        </div>

        <!-- device statuts -->
        <div class="rounded-2xl border border-border/60 bg-white p-6 shadow-soft">
          <div class="flex items-center gap-2.5">
            <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-light"><i data-lucide="radio-tower" class="h-4 w-4 text-secondary"></i></div>
            <h3 class="text-base font-semibold text-text-primary">Greenhouse Device</h3>
          </div>
          <div class="mt-4 flex items-center gap-4 rounded-xl bg-neutral-50/70 p-5">
            <span class="flex h-3 w-3 shrink-0 rounded-full <?= $deviceOnline ? 'bg-green-500' : 'bg-amber-500' ?>"></span>
            <div>
              <p class="text-sm font-semibold text-text-primary"><?= $deviceOnline ? 'Online' : 'Offline' ?></p>
              <p class="text-xs text-text-secondary">Last reading: <?= htmlspecialchars(gh_format_last_seen($deviceLastSeen), ENT_QUOTES) ?></p>
            </div>
          </div>
        </div>

        <!-- Recent admin activity -->
        <div class="rounded-2xl border border-border/60 bg-white shadow-soft">
          <div class="flex items-center gap-2.5 p-6 pb-3">
            <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-light"><i data-lucide="history" class="h-4 w-4 text-secondary"></i></div>
            <div>
              <h3 class="text-base font-semibold text-text-primary">Recent Admin Activity</h3>
              <p class="text-xs text-text-secondary">Every action taken from this console, logged automatically</p>
            </div>
          </div>
          <?php if (!$recentActivity): ?>
            <div class="flex flex-col items-center justify-center gap-2 px-6 pb-8 pt-2 text-center">
              <i data-lucide="inbox" class="h-6 w-6 text-neutral-400"></i>
              <p class="text-sm text-text-secondary">No admin activity recorded yet.</p>
            </div>
          <?php else: ?>
            <div class="flex flex-col px-6 pb-4">
              <?php foreach ($recentActivity as $a): ?>
              <div class="flex items-start gap-3 border-b border-border/40 py-3 last:border-0">
                <div class="mt-0.5 flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-green-gradient text-[11px] font-semibold text-white"><?= htmlspecialchars(gh_row_initials($a['admin_name'] ?: 'A'), ENT_QUOTES) ?></div>
                <div class="min-w-0 flex-1">
                  <p class="text-sm text-text-primary"><span class="font-semibold"><?= htmlspecialchars($a['admin_name'] ?: 'Admin', ENT_QUOTES) ?></span> <?= htmlspecialchars(strtolower($a['action']), ENT_QUOTES) ?></p>
                  <p class="mt-0.5 truncate text-xs text-text-secondary"><?= htmlspecialchars($a['description'] ?? '', ENT_QUOTES) ?></p>
                </div>
                <span class="shrink-0 text-xs text-text-secondary"><?= htmlspecialchars(gh_format_last_seen($a['action_time']), ENT_QUOTES) ?></span>
              </div>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        </div>

      </div>

     
      <div data-tab-panel="users" class="flex flex-col gap-6<?= $activeTab === 'users' ? '' : ' hidden' ?>">

        <div>
          <h2 class="text-lg font-semibold text-text-primary">All Users</h2>
          <p class="mt-1 text-sm text-text-secondary">Every account on the platform — <?= count($allUsers) ?> total</p>
        </div>

        <div class="flex flex-wrap gap-2">
          <?php foreach (['all' => 'All', 'verified' => 'Verified', 'unverified' => 'Not Verified'] as $key => $label): ?>
            <a href="admin.php?tab=users&verified=<?= $key ?>" class="rounded-full border px-3.5 py-1.5 text-xs font-semibold transition-colors <?= $verifiedFilter === $key ? 'border-primary bg-light text-secondary' : 'border-border text-text-secondary hover:bg-neutral-50' ?>"><?= htmlspecialchars($label, ENT_QUOTES) ?></a>
          <?php endforeach; ?>
        </div>

        <div class="rounded-2xl border border-border/60 bg-white p-6 shadow-soft">
          <h3 class="text-base font-semibold text-text-primary">Add New User</h3>
          <form method="post" action="admin.php" class="mt-4 grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-5 xl:items-end">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($token, ENT_QUOTES) ?>">
            <input type="hidden" name="action" value="add_user">
            <input type="hidden" name="tab" value="users">
            <div>
              <label class="mb-1.5 block text-xs font-semibold text-text-secondary">Full Name</label>
              <input type="text" name="full_name" placeholder="Jane Doe" class="w-full rounded-xl border border-border px-3.5 py-2.5 text-sm focus:border-primary focus:outline-none" required>
            </div>
            <div>
              <label class="mb-1.5 block text-xs font-semibold text-text-secondary">Email</label>
              <input type="email" name="email" placeholder="you@example.com" class="w-full rounded-xl border border-border px-3.5 py-2.5 text-sm focus:border-primary focus:outline-none" required>
            </div>
            <div>
              <label class="mb-1.5 block text-xs font-semibold text-text-secondary">Password</label>
              <input type="password" name="password" placeholder="••••••••" minlength="8" class="w-full rounded-xl border border-border px-3.5 py-2.5 text-sm focus:border-primary focus:outline-none" required>
            </div>
            <div>
              <label class="mb-1.5 block text-xs font-semibold text-text-secondary">Role</label>
              <select name="role" class="w-full rounded-xl border border-border px-3.5 py-2.5 text-sm focus:border-primary focus:outline-none">
                <option value="User" selected>User</option>
                <option value="Admin">Admin</option>
              </select>
            </div>
            <button type="submit" class="inline-flex items-center justify-center gap-2 rounded-xl bg-green-gradient px-4 py-2.5 text-sm font-semibold text-white shadow-glow transition-all hover:brightness-105 active:scale-[0.98] whitespace-nowrap">Add User</button>
          </form>
        </div>

        <div class="rounded-2xl border border-border/60 bg-white shadow-soft">
          <div class="overflow-x-auto p-6">
            <table class="w-full border-collapse text-sm">
              <thead>
                <tr class="border-b border-border/70 text-left">
                  <th class="pb-3 pr-4 text-xs font-medium uppercase tracking-wide text-text-secondary">Photo</th>
                  <th class="pb-3 pr-4 text-xs font-medium uppercase tracking-wide text-text-secondary">Name</th>
                  <th class="pb-3 pr-4 text-xs font-medium uppercase tracking-wide text-text-secondary">Email</th>
                  <th class="pb-3 pr-4 text-xs font-medium uppercase tracking-wide text-text-secondary">Access</th>
                  <th class="pb-3 pr-4 text-xs font-medium uppercase tracking-wide text-text-secondary">Status</th>
                  <th class="pb-3 pr-0 text-xs font-medium uppercase tracking-wide text-text-secondary">Actions</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($allUsers as $u):
                  $isSelf = (int)$u['user_id'] === (int)$user['user_id'];
                ?>
                <tr class="border-b border-border/40 last:border-0">
                  <td class="py-3 pr-4">
                    <?php if (!empty($u['profile_image'])): ?>
                      <img src="<?= htmlspecialchars($u['profile_image'], ENT_QUOTES) ?>" alt="" class="h-9 w-9 rounded-full object-cover">
                    <?php else: ?>
                      <span class="flex h-9 w-9 items-center justify-center rounded-full bg-green-gradient text-xs font-semibold text-white"><?= htmlspecialchars(gh_row_initials($u['full_name'] ?: $u['email']), ENT_QUOTES) ?></span>
                    <?php endif; ?>
                  </td>
                  <td class="py-3 pr-4 font-medium text-text-primary"><?= htmlspecialchars($u['full_name'] ?: '—', ENT_QUOTES) ?></td>
                  <td class="py-3 pr-4 text-text-secondary">
                    <?= htmlspecialchars($u['email'], ENT_QUOTES) ?>
                    <?php if (!$u['email_verified']): ?>
                      <span class="ml-1.5 inline-flex items-center gap-1 rounded-full bg-red-50 px-2 py-0.5 text-[10px] font-semibold text-red-600" title="This user has not verified their email address">
                        <i data-lucide="shield-alert" class="h-3 w-3"></i> Email Not Verified
                      </span>
                    <?php endif; ?>
                  </td>
                  <td class="py-3 pr-4">
                    <?php if ($u['role'] === 'Admin'): ?>
                      <span class="rounded-full bg-light px-2.5 py-1 text-[11px] font-semibold text-secondary">Always</span>
                    <?php else: ?>
                      <span class="rounded-full px-2.5 py-1 text-[11px] font-semibold <?= $u['has_access'] ? 'bg-light text-secondary' : 'bg-amber-50 text-amber-600' ?>"><?= $u['has_access'] ? 'Granted' : 'Pending' ?></span>
                    <?php endif; ?>
                  </td>
                  <td class="py-3 pr-4">
                    <span class="rounded-full px-2.5 py-1 text-[11px] font-semibold <?= $u['account_status'] === 'Active' ? 'bg-light text-secondary' : 'bg-red-50 text-red-600' ?>"><?= htmlspecialchars($u['account_status'], ENT_QUOTES) ?></span>
                  </td>
                  <td class="py-3 pr-0">
                    <div class="flex flex-wrap items-center gap-2">
                      <a href="admin-user-edit.php?user_id=<?= (int)$u['user_id'] ?>" class="rounded-lg border border-border px-3 py-1.5 text-xs font-semibold text-text-secondary hover:bg-neutral-50">View</a>
                      <a href="admin-user-edit.php?user_id=<?= (int)$u['user_id'] ?>&edit=1" class="rounded-lg border border-border px-3 py-1.5 text-xs font-semibold text-text-secondary hover:bg-neutral-50">Edit</a>
                      <?php if (!$isSelf): ?>
                        <?php if ($u['role'] !== 'Admin'): ?>
                        <form method="post" action="admin.php" class="inline">
                          <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($token, ENT_QUOTES) ?>">
                          <input type="hidden" name="action" value="toggle_access">
                          <input type="hidden" name="tab" value="users">
                          <input type="hidden" name="user_id" value="<?= (int)$u['user_id'] ?>">
                          <button type="submit" class="rounded-lg border border-border px-3 py-1.5 text-xs font-semibold text-text-secondary hover:bg-neutral-50"><?= $u['has_access'] ? 'Revoke Access' : 'Grant Access' ?></button>
                        </form>
                        <?php endif; ?>
                        <form method="post" action="admin.php" class="inline">
                          <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($token, ENT_QUOTES) ?>">
                          <input type="hidden" name="action" value="toggle_status">
                          <input type="hidden" name="tab" value="users">
                          <input type="hidden" name="user_id" value="<?= (int)$u['user_id'] ?>">
                          <button type="submit" class="rounded-lg border border-border px-3 py-1.5 text-xs font-semibold text-text-secondary hover:bg-neutral-50"><?= $u['account_status'] === 'Active' ? 'Deactivate' : 'Activate' ?></button>
                        </form>
                        <form method="post" action="admin.php" class="inline" onsubmit="return confirm('Delete <?= htmlspecialchars($u['email'], ENT_QUOTES) ?>? This cannot be undone.');">
                          <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($token, ENT_QUOTES) ?>">
                          <input type="hidden" name="action" value="delete_user">
                          <input type="hidden" name="tab" value="users">
                          <input type="hidden" name="user_id" value="<?= (int)$u['user_id'] ?>">
                          <button type="submit" class="rounded-lg border border-red-200 bg-red-50 px-3 py-1.5 text-xs font-semibold text-red-600 hover:bg-red-100">Delete</button>
                        </form>
                      <?php else: ?>
                        <span class="text-xs italic text-text-secondary">You</span>
                      <?php endif; ?>
                    </div>
                  </td>
                </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>

    </div>
  </main>
</div>

<script src="js/dashboard-app.js?v=1"></script>
<script>


  (function () {
    const workersEl = document.getElementById('statTotalWorkers');
    const accessEl = document.getElementById('statWorkersWithAccess');
    const usersEl = document.getElementById('statTotalUsers');
    if (workersEl || accessEl || usersEl) {
      async function pollStats() {
        try {
          const res = await fetch('admin-stats.php', { headers: { 'X-Requested-With': 'fetch' } });
          if (!res.ok) return;
          const data = await res.json();
          if (workersEl) workersEl.textContent = data.total_workers;
          if (accessEl) accessEl.textContent = data.workers_with_access;
          if (usersEl) usersEl.textContent = data.total_users;
        } catch (err) { /* silent — next poll tries again */ }
      }
      setInterval(pollStats, 20000);
    }
  })();

  (function () {
    const btns = document.querySelectorAll('[data-tab-btn]');
    const panels = document.querySelectorAll('[data-tab-panel]');
    btns.forEach((btn) => {
      btn.addEventListener('click', () => {
        const target = btn.dataset.tabBtn;
        panels.forEach((p) => p.classList.toggle('hidden', p.dataset.tabPanel !== target));
        btns.forEach((b) => {
          const isActive = b === btn;
          b.classList.toggle('bg-green-gradient', isActive);
          b.classList.toggle('text-white', isActive);
          b.classList.toggle('shadow-glow', isActive);
          b.classList.toggle('text-text-secondary', !isActive);
        });
        const url = new URL(window.location.href);
        url.searchParams.set('tab', target);
        window.history.pushState({}, '', url);
      });
    });
  })();
</script>
</body>
</html>
