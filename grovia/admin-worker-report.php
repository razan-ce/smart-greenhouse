<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/device-lib.php';

$user = require_admin();
require_once __DIR__ . '/partials/current-user.php';
$db = get_db();

$stmt = $db->query(
    "SELECT u.user_id, u.full_name, u.email, u.account_status, u.last_seen,
            ga.granted_at, granter.full_name AS granted_by_name
     FROM users u
     LEFT JOIN greenhouse_access ga ON ga.user_id = u.user_id
     LEFT JOIN users granter ON granter.user_id = ga.granted_by
     WHERE u.role = 'User'
     ORDER BY u.last_seen DESC"
);
$workers = $stmt->fetchAll();

$gh_page_title = 'Worker Activity | Admin';
require __DIR__ . '/partials/head.php';
?>
<link rel="stylesheet" href="css/report.css?v=1">
<body class="dash-body">

<?php require __DIR__ . '/partials/admin-sidebar.php'; ?>

<div class="lg:pl-[272px]">
  <?php require __DIR__ . '/partials/topbar.php'; ?>

  <main class="mx-auto max-w-[1100px] px-5 py-7 sm:px-8 lg:px-10 lg:py-9">
    <div class="flex flex-col gap-6">

      <div class="noprint flex flex-wrap items-center justify-between gap-3">
        <div>
          <div class="mb-2 inline-flex items-center gap-2 rounded-full bg-light px-3 py-1.5 text-xs font-semibold text-secondary">
            <i data-lucide="activity" class="h-3.5 w-3.5"></i>
            Reports
          </div>
          <h1 class="text-[26px] font-semibold tracking-tight text-text-primary sm:text-[30px]">Worker Activity</h1>
          <p class="mt-1 text-[15px] text-text-secondary">Who has access, and how recently each worker has actually used the app.</p>
        </div>
        <button type="button" id="printBtn" class="inline-flex items-center gap-2 rounded-xl bg-green-gradient px-4 py-2.5 text-sm font-semibold text-white shadow-glow transition-all hover:brightness-105 active:scale-[0.98]">
          <i data-lucide="printer" class="h-4 w-4"></i> Print / Save as PDF
        </button>
      </div>

      <div id="reportCard" class="report-card rounded-2xl border border-border/60 bg-white p-8 shadow-soft">
        <h2 class="text-lg font-semibold text-text-primary">Worker Activity Report</h2>
        <p class="mt-1 text-sm text-text-secondary">Generated <?= htmlspecialchars(date('M j, Y g:ia'), ENT_QUOTES) ?></p>

        <div class="mt-6 overflow-x-auto">
          <table class="w-full border-collapse text-sm">
            <thead>
              <tr class="border-b border-border/70 text-left">
                <th class="pb-3 pr-4 text-xs font-medium uppercase tracking-wide text-text-secondary">Worker</th>
                <th class="pb-3 pr-4 text-xs font-medium uppercase tracking-wide text-text-secondary">Access</th>
                <th class="pb-3 pr-4 text-xs font-medium uppercase tracking-wide text-text-secondary">Granted On</th>
                <th class="pb-3 pr-4 text-xs font-medium uppercase tracking-wide text-text-secondary">Last Active</th>
                <th class="pb-3 pr-4 text-xs font-medium uppercase tracking-wide text-text-secondary">Status</th>
                <th class="pb-3 pr-0 text-xs font-medium uppercase tracking-wide text-text-secondary">Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($workers as $w): ?>
              <tr class="border-b border-border/40 last:border-0">
                <td class="py-3 pr-4">
                  <p class="font-medium text-text-primary"><?= htmlspecialchars($w['full_name'] ?: '—', ENT_QUOTES) ?></p>
                  <p class="text-xs text-text-secondary"><?= htmlspecialchars($w['email'], ENT_QUOTES) ?></p>
                </td>
                <td class="py-3 pr-4">
                  <span class="rounded-full px-2.5 py-1 text-[11px] font-semibold <?= $w['granted_at'] ? 'bg-light text-secondary' : 'bg-amber-50 text-amber-600' ?>"><?= $w['granted_at'] ? 'Granted' : 'Pending' ?></span>
                </td>
                <td class="py-3 pr-4 text-text-secondary"><?= $w['granted_at'] ? htmlspecialchars(date('M j, Y', strtotime($w['granted_at'])), ENT_QUOTES) : '—' ?></td>
                <td class="py-3 pr-4 text-text-secondary"><?= htmlspecialchars(gh_format_last_seen($w['last_seen']), ENT_QUOTES) ?></td>
                <td class="py-3 pr-4">
                  <span class="rounded-full px-2.5 py-1 text-[11px] font-semibold <?= $w['account_status'] === 'Active' ? 'bg-light text-secondary' : 'bg-red-50 text-red-600' ?>"><?= htmlspecialchars($w['account_status'], ENT_QUOTES) ?></span>
                </td>
                <td class="py-3 pr-0 noprint">
                  <div class="flex flex-wrap items-center gap-2">
                    <a href="admin-notifications.php?to=<?= (int) $w['user_id'] ?>" class="rounded-lg border border-border px-3 py-1.5 text-xs font-semibold text-text-secondary hover:bg-neutral-50 whitespace-nowrap">Send a Note</a>
                    <button type="button" class="report-btn rounded-lg border border-border px-3 py-1.5 text-xs font-semibold text-text-secondary hover:bg-neutral-50 whitespace-nowrap" data-user-id="<?= (int) $w['user_id'] ?>" data-worker-name="<?= htmlspecialchars($w['full_name'] ?: $w['email'], ENT_QUOTES) ?>">Full Report</button>
                  </div>
                </td>
              </tr>
              <tr class="border-b border-border/40 last:border-0 report-detail-row" id="reportRow<?= (int) $w['user_id'] ?>" style="display:none;">
                <td colspan="6" class="py-4 pr-0">
                  <div class="rounded-2xl border border-border/60 bg-neutral-50 p-5" id="reportBody<?= (int) $w['user_id'] ?>"></div>
                </td>
              </tr>
              <?php endforeach; ?>
              <?php if (empty($workers)): ?>
              <tr><td colspan="6" class="py-6 text-center text-text-secondary">No workers yet.</td></tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>

    </div>
  </main>
</div>

<script>
  document.getElementById('printBtn').addEventListener('click', () => window.print());

  function escapeHtml(str) {
    const div = document.createElement('div');
    div.textContent = str;
    return div.innerHTML;
  }

  function statBlock(value, label) {
    return `<div><p class="text-lg font-semibold text-text-primary">${value}</p><p class="text-xs text-text-secondary">${label}</p></div>`;
  }

  function renderReport(body, workerName, data) {
    const s = data.stats;
    body.innerHTML = `
      <p class="text-xs font-semibold uppercase tracking-wide text-text-secondary">Performance Report — ${escapeHtml(workerName)}</p>
      <div class="mt-4 grid grid-cols-2 gap-4 sm:grid-cols-4">
        ${statBlock(s.days_with_access !== null ? s.days_with_access : '—', 'Days With Access')}
        ${statBlock(s.days_since_active !== null ? s.days_since_active : 'Never active', 'Days Since Active')}
        ${statBlock(s.tasks_completed + ' / ' + (s.tasks_completed + s.tasks_pending + s.tasks_skipped), 'Tasks Completed')}
        ${statBlock(s.completion_rate !== null ? s.completion_rate + '%' : '—', 'Completion Rate')}
      </div>
      <div class="mt-5">
        <p class="text-xs font-semibold uppercase tracking-wide text-text-secondary">Overview</p>
        <p class="mt-1.5 text-sm leading-relaxed text-text-primary">${escapeHtml(data.overview)}</p>
      </div>
      <div class="mt-4">
        <p class="text-xs font-semibold uppercase tracking-wide text-text-secondary">Recommendation</p>
        <p class="mt-1.5 text-sm leading-relaxed text-text-primary">${escapeHtml(data.recommendation)}</p>
      </div>
    `;
  }

 
  document.querySelectorAll('.report-btn').forEach((btn) => {
    btn.addEventListener('click', () => {
      const userId = btn.dataset.userId;
      const workerName = btn.dataset.workerName;
      const row = document.getElementById('reportRow' + userId);
      const body = document.getElementById('reportBody' + userId);
      row.style.display = '';
      body.innerHTML = '<p class="text-sm text-text-secondary">Generating report…</p>';
      btn.disabled = true;
      fetch('api/worker-report.php?user_id=' + userId)
        .then((r) => r.json())
        .then((data) => {
          if (data.ok) {
            renderReport(body, workerName, data);
          } else {
            body.innerHTML = `<p class="text-sm text-red-600">${escapeHtml(data.error || 'Could not generate the report.')}</p>`;
          }
        })
        .catch(() => { body.innerHTML = '<p class="text-sm text-red-600">Network error, try again.</p>'; })
        .finally(() => { btn.disabled = false; });
    });
  });
</script>
</body>
</html>