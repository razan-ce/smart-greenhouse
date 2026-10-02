<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/admin-lib.php';
require_once __DIR__ . '/mail-lib.php';

$admin = require_admin();
require_once __DIR__ . '/partials/current-user.php';
$user = $admin; // partials/current-user.php expects $user in scope
$db = get_db();

$targetId = (int)($_GET['user_id'] ?? 0);
if (!$targetId) {
    header('Location: admin.php?tab=users');
    exit;
}

$flash = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update_user') {
    if (!csrf_check($_POST['csrf_token'] ?? null)) {
        $flash = ['type' => 'error', 'text' => 'Your session expired. Please try again.'];
    } else {
        $fullName = trim($_POST['full_name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $role = ($_POST['role'] ?? '') === 'Admin' ? 'Admin' : 'User';

        if ($fullName === '') {
            $flash = ['type' => 'error', 'text' => 'Full name is required.'];
        } elseif ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $flash = ['type' => 'error', 'text' => 'Enter a valid email address.'];
        } else {
            $stmt = $db->prepare('SELECT user_id FROM users WHERE email = ? AND user_id != ?');
            $stmt->execute([$email, $targetId]);
            if ($stmt->fetch()) {
                $flash = ['type' => 'error', 'text' => 'Another account already uses that email.'];
            } else {
                $stmt = $db->prepare('UPDATE users SET full_name = ?, email = ?, phone = ?, role = ? WHERE user_id = ?');
                $stmt->execute([$fullName, $email, $phone !== '' ? $phone : null, $role, $targetId]);
                gh_log_admin_action($db, (int)$admin['user_id'], 'Edit User', 'users', $targetId, "Updated profile for {$email}");
                $flash = ['type' => 'success', 'text' => 'User updated.'];
            }
        }
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'toggle_access') {
    if (!csrf_check($_POST['csrf_token'] ?? null)) {
        $flash = ['type' => 'error', 'text' => 'Your session expired. Please try again.'];
    } else {
        $stmt = $db->prepare('SELECT user_id, email, role FROM users WHERE user_id = ?');
        $stmt->execute([$targetId]);
        $accessTarget = $stmt->fetch();
        if (!$accessTarget) {
            $flash = ['type' => 'error', 'text' => 'User not found.'];
        } else {
            $result = gh_toggle_greenhouse_access($db, $accessTarget, (int) $admin['user_id']);
            $flash = ['type' => $result['ok'] ? 'success' : 'error', 'text' => $result['text']];
        }
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'send_verification') {
    if (!csrf_check($_POST['csrf_token'] ?? null)) {
        $flash = ['type' => 'error', 'text' => 'Your session expired. Please try again.'];
    } else {
        $stmt = $db->prepare('SELECT user_id, full_name, email, email_verified FROM users WHERE user_id = ?');
        $stmt->execute([$targetId]);
        $target = $stmt->fetch();
        if ($target['email_verified']) {
            $flash = ['type' => 'error', 'text' => 'This user is already verified.'];
        } else {
            $result = gh_send_verification_email($db, $target);
            gh_log_admin_action($db, (int)$admin['user_id'], 'Send Verification Email', 'users', $targetId, "Sent verification email to {$target['email']}");
            $flash = $result['success']
                ? ['type' => 'success', 'text' => "Verification email sent to {$target['email']}."]
                : ['type' => 'error', 'text' => "Logged but not delivered: {$result['error']}"];
        }
    }
}

$stmt = $db->prepare(
    "SELECT u.*, ga.granted_at, granter.full_name AS granted_by_name, granter.email AS granted_by_email
     FROM users u
     LEFT JOIN greenhouse_access ga ON ga.user_id = u.user_id
     LEFT JOIN users granter ON granter.user_id = ga.granted_by
     WHERE u.user_id = ?"
);
$stmt->execute([$targetId]);
$target = $stmt->fetch();

if (!$target) {
    header('Location: admin.php?tab=users');
    exit;
}

$stmt = $db->prepare("SELECT sent_at, status FROM email_log WHERE user_id = ? AND email_type = 'Verification' ORDER BY sent_at DESC LIMIT 1");
$stmt->execute([$targetId]);
$lastVerificationEmail = $stmt->fetch();

$editing = !empty($_GET['edit']) || $flash;
$token = csrf_token();
$gh_page_title = 'User Details | Admin';
require __DIR__ . '/partials/head.php';
?>
<body class="dash-body">

<?php require __DIR__ . '/partials/admin-sidebar.php'; ?>

<div class="lg:pl-[272px]">
  <?php require __DIR__ . '/partials/topbar.php'; ?>

  <main class="mx-auto max-w-[800px] px-5 py-7 sm:px-8 lg:px-10 lg:py-9">
    <a href="admin.php?tab=users" class="inline-flex items-center gap-1.5 text-sm font-medium text-text-secondary hover:text-secondary">
      <i data-lucide="arrow-left" class="h-4 w-4"></i> Back to Users
    </a>

    <div class="mt-4 flex items-center gap-4">
      <?php if (!empty($target['profile_image'])): ?>
        <img src="<?= htmlspecialchars($target['profile_image'], ENT_QUOTES) ?>" alt="" class="h-16 w-16 rounded-full object-cover">
      <?php else: ?>
        <span class="flex h-16 w-16 items-center justify-center rounded-full bg-green-gradient text-lg font-semibold text-white"><?= htmlspecialchars(strtoupper(mb_substr($target['full_name'] ?: $target['email'], 0, 2)), ENT_QUOTES) ?></span>
      <?php endif; ?>
      <div>
        <h1 class="text-xl font-semibold text-text-primary"><?= htmlspecialchars($target['full_name'] ?: 'Unnamed User', ENT_QUOTES) ?></h1>
        <p class="text-sm text-text-secondary"><?= htmlspecialchars($target['email'], ENT_QUOTES) ?></p>
      </div>
      <span class="ml-auto rounded-full px-2.5 py-1 text-[11px] font-semibold <?= $target['role'] === 'Admin' ? 'bg-amber-50 text-amber-600' : 'bg-light text-secondary' ?>"><?= htmlspecialchars($target['role'], ENT_QUOTES) ?></span>
    </div>

    <?php if ($flash): ?>
      <div class="mt-5 rounded-xl border px-4 py-3 text-sm font-semibold <?= $flash['type'] === 'success' ? 'border-primary/20 bg-light text-secondary' : 'border-red-200 bg-red-50 text-red-600' ?>">
        <?= htmlspecialchars($flash['text'], ENT_QUOTES) ?>
      </div>
    <?php endif; ?>

    <?php if ($editing): ?>
      <div class="mt-5 rounded-2xl border border-border/60 bg-white p-6 shadow-soft">
        <h2 class="text-base font-semibold text-text-primary">Edit Profile</h2>
        <form method="post" action="admin-user-edit.php?user_id=<?= $targetId ?>" class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2">
          <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($token, ENT_QUOTES) ?>">
          <input type="hidden" name="action" value="update_user">
          <div>
            <label class="mb-1.5 block text-xs font-semibold text-text-secondary">Full Name</label>
            <input type="text" name="full_name" value="<?= htmlspecialchars($target['full_name'] ?? '', ENT_QUOTES) ?>" class="w-full rounded-xl border border-border px-3.5 py-2.5 text-sm focus:border-primary focus:outline-none" required>
          </div>
          <div>
            <label class="mb-1.5 block text-xs font-semibold text-text-secondary">Email</label>
            <input type="email" name="email" value="<?= htmlspecialchars($target['email'], ENT_QUOTES) ?>" class="w-full rounded-xl border border-border px-3.5 py-2.5 text-sm focus:border-primary focus:outline-none" required>
          </div>
          <div>
            <label class="mb-1.5 block text-xs font-semibold text-text-secondary">Phone</label>
            <input type="tel" name="phone" value="<?= htmlspecialchars($target['phone'] ?? '', ENT_QUOTES) ?>" class="w-full rounded-xl border border-border px-3.5 py-2.5 text-sm focus:border-primary focus:outline-none">
          </div>
          <div>
            <label class="mb-1.5 block text-xs font-semibold text-text-secondary">Role</label>
            <select name="role" class="w-full rounded-xl border border-border px-3.5 py-2.5 text-sm focus:border-primary focus:outline-none">
              <option value="User" <?= $target['role'] === 'User' ? 'selected' : '' ?>>User</option>
              <option value="Admin" <?= $target['role'] === 'Admin' ? 'selected' : '' ?>>Admin</option>
            </select>
          </div>
          <div class="sm:col-span-2 flex gap-3">
            <button type="submit" class="inline-flex items-center justify-center gap-2 rounded-xl bg-green-gradient px-4 py-2.5 text-sm font-semibold text-white shadow-glow transition-all hover:brightness-105 active:scale-[0.98]">Save Changes</button>
            <a href="admin-user-edit.php?user_id=<?= $targetId ?>" class="rounded-xl border border-border px-4 py-2.5 text-sm font-semibold text-text-secondary hover:bg-neutral-50">Cancel</a>
          </div>
        </form>
      </div>
    <?php else: ?>
      <div class="mt-5 grid grid-cols-1 gap-4 sm:grid-cols-2">
        <div class="rounded-2xl border border-border/60 bg-white p-5 shadow-soft">
          <p class="text-xs font-semibold uppercase tracking-wide text-text-secondary">Account</p>
          <dl class="mt-3 space-y-2 text-sm">
            <div class="flex justify-between"><dt class="text-text-secondary">Phone</dt><dd class="font-medium text-text-primary"><?= htmlspecialchars($target['phone'] ?? '—', ENT_QUOTES) ?></dd></div>
            <div class="flex justify-between"><dt class="text-text-secondary">Status</dt><dd class="font-medium text-text-primary"><?= htmlspecialchars($target['account_status'], ENT_QUOTES) ?></dd></div>
            <div class="flex justify-between"><dt class="text-text-secondary">Joined</dt><dd class="font-medium text-text-primary"><?= htmlspecialchars(date('M j, Y', strtotime($target['created_at'])), ENT_QUOTES) ?></dd></div>
          </dl>
        </div>
        <div class="rounded-2xl border border-border/60 bg-white p-5 shadow-soft">
          <p class="text-xs font-semibold uppercase tracking-wide text-text-secondary">Worker Access</p>
          <?php if ($target['role'] === 'Admin'): ?>
          <p class="mt-3 text-sm text-text-secondary">Admins always have access to the greenhouse — nothing to grant.</p>
          <?php else: ?>
          <dl class="mt-3 space-y-2 text-sm">
            <div class="flex justify-between">
              <dt class="text-text-secondary">Status</dt>
              <dd><span class="rounded-full px-2.5 py-1 text-[11px] font-semibold <?= $target['granted_at'] ? 'bg-light text-secondary' : 'bg-amber-50 text-amber-600' ?>"><?= $target['granted_at'] ? 'Granted' : 'Pending' ?></span></dd>
            </div>
            <?php if ($target['granted_at']): ?>
            <div class="flex justify-between"><dt class="text-text-secondary">Granted</dt><dd class="font-medium text-text-primary"><?= htmlspecialchars(date('M j, Y', strtotime($target['granted_at'])), ENT_QUOTES) ?></dd></div>
            <div class="flex justify-between"><dt class="text-text-secondary">Granted By</dt><dd class="font-medium text-text-primary"><?= htmlspecialchars($target['granted_by_name'] ?: ($target['granted_by_email'] ?: '—'), ENT_QUOTES) ?></dd></div>
            <?php endif; ?>
          </dl>
          <form method="post" action="admin-user-edit.php?user_id=<?= $targetId ?>" class="mt-4">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($token, ENT_QUOTES) ?>">
            <input type="hidden" name="action" value="toggle_access">
            <button type="submit" class="rounded-xl border border-border px-4 py-2.5 text-sm font-semibold text-text-secondary hover:bg-neutral-50"><?= $target['granted_at'] ? 'Remove Access' : 'Allow Access' ?></button>
          </form>
          <?php endif; ?>
        </div>
      </div>

      <div class="mt-5 rounded-2xl border border-border/60 bg-white p-5 shadow-soft">
        <p class="text-xs font-semibold uppercase tracking-wide text-text-secondary">Email Verification</p>
        <dl class="mt-3 space-y-2 text-sm">
          <div class="flex justify-between"><dt class="text-text-secondary">Email</dt><dd class="font-medium text-text-primary"><?= htmlspecialchars($target['email'], ENT_QUOTES) ?></dd></div>
          <div class="flex justify-between">
            <dt class="text-text-secondary">Verification Status</dt>
            <dd>
              <span class="rounded-full px-2.5 py-1 text-[11px] font-semibold <?= $target['email_verified'] ? 'bg-light text-secondary' : 'bg-red-50 text-red-600' ?>">
                <?= $target['email_verified'] ? 'Verified' : 'Email Not Verified' ?>
              </span>
            </dd>
          </div>
          <div class="flex justify-between"><dt class="text-text-secondary">Verified Date</dt><dd class="font-medium text-text-primary"><?= $target['verified_at'] ? htmlspecialchars(date('M j, Y g:ia', strtotime($target['verified_at'])), ENT_QUOTES) : '—' ?></dd></div>
          <div class="flex justify-between"><dt class="text-text-secondary">Last Verification Email Sent</dt><dd class="font-medium text-text-primary"><?= $lastVerificationEmail ? htmlspecialchars(date('M j, Y g:ia', strtotime($lastVerificationEmail['sent_at'])) . ' (' . $lastVerificationEmail['status'] . ')', ENT_QUOTES) : 'Never' ?></dd></div>
        </dl>
        <?php if ($target['email_verified']): ?>
          <button type="button" disabled class="mt-4 cursor-not-allowed rounded-xl border border-border bg-neutral-50 px-4 py-2.5 text-sm font-semibold text-text-secondary">Already Verified</button>
        <?php else: ?>
          <form method="post" action="admin-user-edit.php?user_id=<?= $targetId ?>" class="mt-4">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($token, ENT_QUOTES) ?>">
            <input type="hidden" name="action" value="send_verification">
            <button type="submit" class="inline-flex items-center justify-center gap-2 rounded-xl bg-green-gradient px-4 py-2.5 text-sm font-semibold text-white shadow-glow transition-all hover:brightness-105 active:scale-[0.98]"><?= $lastVerificationEmail ? 'Resend Verification Email' : 'Send Verification Email' ?></button>
          </form>
        <?php endif; ?>
      </div>

      <a href="admin-user-edit.php?user_id=<?= $targetId ?>&edit=1" class="mt-5 inline-flex items-center justify-center gap-2 rounded-xl bg-green-gradient px-4 py-2.5 text-sm font-semibold text-white shadow-glow transition-all hover:brightness-105 active:scale-[0.98]">Edit Profile</a>
    <?php endif; ?>
  </main>
</div>

<script src="js/dashboard-app.js?v=1"></script>
</body>
</html>
