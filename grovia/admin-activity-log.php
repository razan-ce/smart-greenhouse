<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';

$user = require_admin();
require_once __DIR__ . '/partials/current-user.php';

$db = get_db();

$actionFilter = trim((string) ($_GET['action'] ?? ''));
$search = trim((string) ($_GET['q'] ?? ''));

$where = [];
$params = [];
if ($actionFilter !== '') {
    $where[] = 'l.action = ?';
    $params[] = $actionFilter;
}
if ($search !== '') {
    $where[] = '(l.description LIKE ? OR u.full_name LIKE ? OR u.email LIKE ?)';
    $like = '%' . $search . '%';
    array_push($params, $like, $like, $like);
}
$whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

$stmt = $db->prepare(
    "SELECT l.log_id, l.action, l.target_table, l.target_id, l.description, l.action_time,
            u.full_name, u.email, u.profile_image
     FROM admin_activity_log l
     JOIN users u ON u.user_id = l.admin_id
     $whereSql
     ORDER BY l.action_time DESC
     LIMIT 200"
);
$stmt->execute($params);
$logs = $stmt->fetchAll();

$totalCount = (int) $db->query('SELECT COUNT(*) FROM admin_activity_log')->fetchColumn();
$actionTypes = $db->query('SELECT DISTINCT action FROM admin_activity_log ORDER BY action')->fetchAll(PDO::FETCH_COLUMN);

$actionIcon = [
    'Add User' => 'user-plus', 'Delete User' => 'user-x', 'Update User' => 'user-cog',
    'Send Email' => 'mail', 'Update Subscription' => 'credit-card',
];
function gh_action_icon(array $map, string $action): string {
    return $map[$action] ?? 'activity';
}
function gh_log_initials(string $name): string {
    $parts = preg_split('/\s+/', trim($name));
    if (count($parts) >= 2) return strtoupper(mb_substr($parts[0], 0, 1) . mb_substr($parts[1], 0, 1));
    return strtoupper(mb_substr($name, 0, 2));
}

$gh_page_title = 'Activity Log | Grovia';
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
            <i data-lucide="history" class="h-3.5 w-3.5"></i>
            Audit Trail
          </div>
          <h1 class="text-[26px] font-semibold tracking-tight text-text-primary sm:text-[30px]">Activity Log</h1>
          <p class="mt-1 text-[15px] text-text-secondary">Every action taken by an administrator, recorded automatically.</p>
        </div>
        <span class="inline-flex items-center gap-2 rounded-full border border-border/60 bg-white px-3.5 py-2 text-xs font-medium text-text-secondary shadow-soft">
          <i data-lucide="database" class="h-3.5 w-3.5"></i>
          <?= number_format($totalCount) ?> total entries
        </span>
      </div>

      <!-- Filters -->
      <form method="get" class="flex flex-wrap items-center gap-3 rounded-2xl border border-border/60 bg-white p-4 shadow-soft">
        <div class="relative min-w-[220px] flex-1">
          <i data-lucide="search" class="pointer-events-none absolute left-3.5 top-1/2 h-4 w-4 -translate-y-1/2 text-text-secondary"></i>
          <input type="text" name="q" value="<?= htmlspecialchars($search, ENT_QUOTES) ?>" placeholder="Search by admin or description…" class="h-10 w-full rounded-xl border border-border/70 bg-neutral-50/70 pl-10 pr-3 text-sm text-text-primary focus:outline-none focus:ring-2 focus:ring-primary/25 focus:border-primary/40 focus:bg-white">
        </div>
        <select name="action" class="h-10 rounded-xl border border-border/70 bg-neutral-50/70 px-3 text-sm text-text-primary focus:outline-none focus:ring-2 focus:ring-primary/25">
          <option value="">All actions</option>
          <?php foreach ($actionTypes as $a): ?>
          <option value="<?= htmlspecialchars($a, ENT_QUOTES) ?>" <?= $actionFilter === $a ? 'selected' : '' ?>><?= htmlspecialchars($a, ENT_QUOTES) ?></option>
          <?php endforeach; ?>
        </select>
        <button type="submit" class="rounded-xl bg-green-gradient px-4 py-2.5 text-sm font-semibold text-white shadow-sm">Filter</button>
        <?php if ($actionFilter !== '' || $search !== ''): ?>
        <a href="admin-activity-log.php" class="text-xs font-medium text-text-secondary hover:underline">Clear</a>
        <?php endif; ?>
      </form>

      <!-- Table -->
      <div class="rounded-2xl border border-border/60 bg-white shadow-soft">
        <div class="overflow-x-auto p-6">
          <table class="w-full border-collapse text-sm">
            <thead>
              <tr class="border-b border-border/70 text-left">
                <th class="pb-3 pr-4 text-xs font-medium uppercase tracking-wide text-text-secondary">Admin</th>
                <th class="pb-3 pr-4 text-xs font-medium uppercase tracking-wide text-text-secondary">Action</th>
                <th class="pb-3 pr-4 text-xs font-medium uppercase tracking-wide text-text-secondary">Description</th>
                <th class="pb-3 pr-0 text-xs font-medium uppercase tracking-wide text-text-secondary">Time</th>
              </tr>
            </thead>
            <tbody>
              <?php if (empty($logs)): ?>
              <tr><td colspan="4" class="py-10 text-center text-sm text-text-secondary">No activity found.</td></tr>
              <?php endif; ?>
              <?php foreach ($logs as $log): ?>
              <tr class="border-b border-border/40 last:border-0">
                <td class="py-3 pr-4">
                  <div class="flex items-center gap-2.5">
                    <?php if (!empty($log['profile_image'])): ?>
                      <img src="<?= htmlspecialchars($log['profile_image'], ENT_QUOTES) ?>" alt="" class="h-8 w-8 rounded-full object-cover">
                    <?php else: ?>
                      <span class="flex h-8 w-8 items-center justify-center rounded-full bg-green-gradient text-[10px] font-semibold text-white"><?= htmlspecialchars(gh_log_initials($log['full_name'] ?: $log['email']), ENT_QUOTES) ?></span>
                    <?php endif; ?>
                    <div class="min-w-0">
                      <p class="truncate font-medium text-text-primary"><?= htmlspecialchars($log['full_name'] ?: $log['email'], ENT_QUOTES) ?></p>
                    </div>
                  </div>
                </td>
                <td class="py-3 pr-4">
                  <span class="inline-flex items-center gap-1.5 rounded-full bg-light px-2.5 py-1 text-[11px] font-semibold text-secondary">
                    <i data-lucide="<?= gh_action_icon($actionIcon, $log['action']) ?>" class="h-3 w-3"></i>
                    <?= htmlspecialchars($log['action'], ENT_QUOTES) ?>
                  </span>
                </td>
                <td class="py-3 pr-4 max-w-[420px] text-text-secondary"><?= htmlspecialchars($log['description'], ENT_QUOTES) ?></td>
                <td class="py-3 pr-0 whitespace-nowrap text-xs text-text-secondary"><?= htmlspecialchars(date('M j, Y · g:ia', strtotime($log['action_time'])), ENT_QUOTES) ?></td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
      <?php if (count($logs) >= 200): ?>
      <p class="text-center text-xs text-text-secondary">Showing the 200 most recent entries. Use search/filter to narrow results.</p>
      <?php endif; ?>

    </div>
  </main>
</div>

<script src="js/dashboard-app.js?v=1"></script>
</body>
</html>
