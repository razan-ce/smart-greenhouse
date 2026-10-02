<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/admin-lib.php';

$user = require_admin();
require_once __DIR__ . '/partials/current-user.php';

$db = get_db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_check($_POST['csrf_token'] ?? null)) {
        $_SESSION['admin_flash'] = ['type' => 'error', 'text' => 'Your session expired. Please try again.'];
    } else {
        $title = trim($_POST['title'] ?? '');
        $message = trim($_POST['message'] ?? '');
        $type = in_array($_POST['type'] ?? '', ['Sensor', 'Prediction', 'System'], true) ? $_POST['type'] : 'System';
        $target = $_POST['target'] ?? '';

        if ($title === '' || $message === '') {
            $_SESSION['admin_flash'] = ['type' => 'error', 'text' => 'Enter both a title and a message.'];
        } elseif ($target === 'all') {
            $userIds = $db->query("SELECT user_id FROM users WHERE role = 'User'")->fetchAll(PDO::FETCH_COLUMN);
            $insert = $db->prepare('INSERT INTO notifications (user_id, title, message, type, is_read) VALUES (?, ?, ?, ?, 0)');
            foreach ($userIds as $uid) {
                $insert->execute([$uid, $title, $message, $type]);
            }
            gh_log_admin_action($db, (int) $user['user_id'], 'Send Notification', 'notifications', null, "Broadcast \"{$title}\" to " . count($userIds) . ' users');
            $_SESSION['admin_flash'] = ['type' => 'success', 'text' => 'Notification sent to ' . count($userIds) . ' user(s).'];
        } else {
            $targetUserId = (int) $target;
            $stmt = $db->prepare("SELECT user_id, email FROM users WHERE user_id = ? AND role = 'User'");
            $stmt->execute([$targetUserId]);
            $targetUser = $stmt->fetch();
            if (!$targetUser) {
                $_SESSION['admin_flash'] = ['type' => 'error', 'text' => 'That user was not found.'];
            } else {
                $db->prepare('INSERT INTO notifications (user_id, title, message, type, is_read) VALUES (?, ?, ?, ?, 0)')
                    ->execute([$targetUserId, $title, $message, $type]);
                gh_log_admin_action($db, (int) $user['user_id'], 'Send Notification', 'notifications', $targetUserId, "Sent \"{$title}\" to {$targetUser['email']}");
                $_SESSION['admin_flash'] = ['type' => 'success', 'text' => "Notification sent to {$targetUser['email']}."];
            }
        }
    }
    header('Location: admin-notifications.php');
    exit;
}

$prefilledTarget = $_GET['to'] ?? '';

$flash = $_SESSION['admin_flash'] ?? null;
unset($_SESSION['admin_flash']);

$typeFilter = trim((string) ($_GET['type'] ?? ''));
// Defaults to unread-only — "Read + Unread" is still one click away in the
// dropdown, but the common case is checking what's new, not the whole history.
$readFilter = isset($_GET['read']) ? trim((string) $_GET['read']) : 'unread';
$search = trim((string) ($_GET['q'] ?? ''));

$where = [];
$params = [];
if ($typeFilter !== '') { $where[] = 'n.type = ?'; $params[] = $typeFilter; }
if ($readFilter === 'unread') { $where[] = 'n.is_read = 0'; }
elseif ($readFilter === 'read') { $where[] = 'n.is_read = 1'; }
if ($search !== '') {
    $where[] = '(n.title LIKE ? OR n.message LIKE ? OR u.email LIKE ?)';
    $like = '%' . $search . '%';
    array_push($params, $like, $like, $like);
}
$whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

$stmt = $db->prepare(
    "SELECT n.notification_id, n.title, n.message, n.type, n.is_read, n.created_at, u.full_name, u.email
     FROM notifications n
     JOIN users u ON u.user_id = n.user_id
     $whereSql
     ORDER BY n.created_at DESC
     LIMIT 200"
);
$stmt->execute($params);
$notifs = $stmt->fetchAll();

$totalCount = (int) $db->query('SELECT COUNT(*) FROM notifications')->fetchColumn();
$unreadCount = (int) $db->query('SELECT COUNT(*) FROM notifications WHERE is_read = 0')->fetchColumn();

$allUsersForSelect = $db->query("SELECT user_id, full_name, email FROM users WHERE role = 'User' ORDER BY full_name")->fetchAll();

$typeBadge = [
    'Sensor' => 'bg-amber-50 text-amber-600', 'System' => 'bg-light text-secondary',
    'Prediction' => 'bg-sky-50 text-sky-600',
];

$token = csrf_token();
$gh_page_title = 'Notifications | Grovia';
require __DIR__ . '/partials/head.php';
?>
<body class="dash-body">

<?php require __DIR__ . '/partials/admin-sidebar.php'; ?>

<div class="lg:pl-[272px]">
  <?php require __DIR__ . '/partials/topbar.php'; ?>

  <main class="mx-auto max-w-[1200px] px-5 py-7 sm:px-8 lg:px-10 lg:py-9">
    <div class="flex flex-col gap-6">

      <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
          <div class="mb-2 inline-flex items-center gap-2 rounded-full bg-light px-3 py-1.5 text-xs font-semibold text-secondary">
            <i data-lucide="bell-ring" class="h-3.5 w-3.5"></i>
            System-wide
          </div>
          <h1 class="text-[26px] font-semibold tracking-tight text-text-primary sm:text-[30px]">Notifications</h1>
          <p class="mt-1 text-[15px] text-text-secondary"><?= number_format($totalCount) ?> total · <?= number_format($unreadCount) ?> unread across all users</p>
        </div>
      </div>

      <?php if ($flash): ?>
        <div class="rounded-xl border px-4 py-3 text-sm font-semibold <?= $flash['type'] === 'success' ? 'border-primary/20 bg-light text-secondary' : 'border-red-200 bg-red-50 text-red-600' ?>">
          <?= htmlspecialchars($flash['text'], ENT_QUOTES) ?>
        </div>
      <?php endif; ?>

      <!-- Compose -->
      <div class="rounded-2xl border border-border/60 bg-white p-6 shadow-soft sm:p-8">
        <h3 class="text-sm font-semibold text-text-primary">Send a Notification</h3>
        <p class="mt-1 text-xs text-text-secondary">Send to one user, or broadcast to everyone.</p>

        <form method="post" class="mt-5 flex flex-col gap-4">
          <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($token, ENT_QUOTES) ?>">
          <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <div>
              <label class="text-xs font-medium text-text-secondary">Send To</label>
              <select name="target" class="mt-1.5 h-11 w-full rounded-xl border border-border/70 bg-neutral-50/70 px-3.5 text-sm text-text-primary focus:outline-none focus:ring-2 focus:ring-primary/25">
                <option value="all">All Users (Broadcast)</option>
                <?php foreach ($allUsersForSelect as $u): ?>
                <option value="<?= (int) $u['user_id'] ?>" <?= (string) $u['user_id'] === $prefilledTarget ? 'selected' : '' ?>><?= htmlspecialchars($u['full_name'] ?: $u['email'], ENT_QUOTES) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div>
              <label class="text-xs font-medium text-text-secondary">Type</label>
              <select name="type" class="mt-1.5 h-11 w-full rounded-xl border border-border/70 bg-neutral-50/70 px-3.5 text-sm text-text-primary focus:outline-none focus:ring-2 focus:ring-primary/25">
                <option value="System">System</option>
                <option value="Sensor">Sensor</option>
                <option value="Prediction">Prediction</option>
              </select>
            </div>
          </div>
          <div>
            <label class="text-xs font-medium text-text-secondary">Title</label>
            <input type="text" name="title" required maxlength="100" class="mt-1.5 h-11 w-full rounded-xl border border-border/70 bg-neutral-50/70 px-3.5 text-sm text-text-primary focus:outline-none focus:ring-2 focus:ring-primary/25">
          </div>
          <div>
            <label class="text-xs font-medium text-text-secondary">Message</label>
            <textarea name="message" required rows="3" class="mt-1.5 w-full rounded-xl border border-border/70 bg-neutral-50/70 px-3.5 py-2.5 text-sm text-text-primary focus:outline-none focus:ring-2 focus:ring-primary/25"></textarea>
          </div>
          <div>
            <button type="submit" class="inline-flex items-center gap-2 rounded-xl bg-green-gradient px-5 py-2.5 text-sm font-semibold text-white shadow-glow">
              <i data-lucide="send" class="h-4 w-4"></i> Send Notification
            </button>
          </div>
        </form>
      </div>

      <!-- Filters -->
      <form method="get" class="flex flex-wrap items-center gap-3 rounded-2xl border border-border/60 bg-white p-4 shadow-soft">
        <div class="relative min-w-[220px] flex-1">
          <i data-lucide="search" class="pointer-events-none absolute left-3.5 top-1/2 h-4 w-4 -translate-y-1/2 text-text-secondary"></i>
          <input type="text" name="q" value="<?= htmlspecialchars($search, ENT_QUOTES) ?>" placeholder="Search title, message, or user…" class="h-10 w-full rounded-xl border border-border/70 bg-neutral-50/70 pl-10 pr-3 text-sm text-text-primary focus:outline-none focus:ring-2 focus:ring-primary/25">
        </div>
        <select name="type" class="h-10 rounded-xl border border-border/70 bg-neutral-50/70 px-3 text-sm text-text-primary">
          <option value="">All types</option>
          <?php foreach (['Sensor', 'Prediction', 'System'] as $t): ?>
          <option value="<?= $t ?>" <?= $typeFilter === $t ? 'selected' : '' ?>><?= $t ?></option>
          <?php endforeach; ?>
        </select>
        <select name="read" class="h-10 rounded-xl border border-border/70 bg-neutral-50/70 px-3 text-sm text-text-primary">
          <option value="">Read + Unread</option>
          <option value="unread" <?= $readFilter === 'unread' ? 'selected' : '' ?>>Unread only</option>
          <option value="read" <?= $readFilter === 'read' ? 'selected' : '' ?>>Read only</option>
        </select>
        <button type="submit" class="rounded-xl bg-green-gradient px-4 py-2.5 text-sm font-semibold text-white shadow-sm">Filter</button>
        <?php if ($typeFilter !== '' || $readFilter !== 'unread' || $search !== ''): ?>
        <a href="admin-notifications.php" class="text-xs font-medium text-text-secondary hover:underline">Clear</a>
        <?php endif; ?>
      </form>

      <!-- Table -->
      <div class="rounded-2xl border border-border/60 bg-white shadow-soft">
        <div class="overflow-x-auto p-6">
          <table class="w-full border-collapse text-sm">
            <thead>
              <tr class="border-b border-border/70 text-left">
                <th class="pb-3 pr-4 text-xs font-medium uppercase tracking-wide text-text-secondary">User</th>
                <th class="pb-3 pr-4 text-xs font-medium uppercase tracking-wide text-text-secondary">Type</th>
                <th class="pb-3 pr-4 text-xs font-medium uppercase tracking-wide text-text-secondary">Title &amp; Message</th>
                <th class="pb-3 pr-4 text-xs font-medium uppercase tracking-wide text-text-secondary">Status</th>
                <th class="pb-3 pr-0 text-xs font-medium uppercase tracking-wide text-text-secondary">Time</th>
              </tr>
            </thead>
            <tbody>
              <?php if (empty($notifs)): ?>
              <tr><td colspan="5" class="py-10 text-center text-sm text-text-secondary">No notifications found.</td></tr>
              <?php endif; ?>
              <?php foreach ($notifs as $n): ?>
              <tr class="border-b border-border/40 last:border-0">
                <td class="py-3 pr-4 text-text-secondary"><?= htmlspecialchars($n['full_name'] ?: $n['email'], ENT_QUOTES) ?></td>
                <td class="py-3 pr-4"><span class="rounded-full px-2.5 py-1 text-[11px] font-semibold <?= $typeBadge[$n['type']] ?? 'bg-neutral-100 text-neutral-600' ?>"><?= htmlspecialchars($n['type'], ENT_QUOTES) ?></span></td>
                <td class="py-3 pr-4 max-w-[360px]">
                  <p class="font-medium text-text-primary"><?= htmlspecialchars($n['title'], ENT_QUOTES) ?></p>
                  <p class="truncate text-xs text-text-secondary"><?= htmlspecialchars($n['message'], ENT_QUOTES) ?></p>
                </td>
                <td class="py-3 pr-4">
                  <?php if ($n['is_read']): ?>
                  <span class="rounded-full bg-neutral-100 px-2.5 py-1 text-[11px] font-semibold text-neutral-500">Read</span>
                  <?php else: ?>
                  <span class="rounded-full bg-light px-2.5 py-1 text-[11px] font-semibold text-secondary">Unread</span>
                  <?php endif; ?>
                </td>
                <td class="py-3 pr-0 whitespace-nowrap text-xs text-text-secondary"><?= htmlspecialchars(date('M j, Y · g:ia', strtotime($n['created_at'])), ENT_QUOTES) ?></td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
      <?php if (count($notifs) >= 200): ?>
      <p class="text-center text-xs text-text-secondary">Showing the 200 most recent entries. Use search/filter to narrow results.</p>
      <?php endif; ?>

    </div>
  </main>
</div>

<script src="js/dashboard-app.js?v=1"></script>
</body>
</html>
