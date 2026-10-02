<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/admin-lib.php';
require_once __DIR__ . '/mail-lib.php';

$user = require_admin();
require_once __DIR__ . '/partials/current-user.php';
$db = get_db();


function gh_handle_email_attachment(): ?array {
    if (empty($_FILES['attachment']) || $_FILES['attachment']['error'] === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    $file = $_FILES['attachment'];
    if ($file['error'] !== UPLOAD_ERR_OK) {
        throw new RuntimeException('The attachment failed to upload. Please try again.');
    }
    if ($file['size'] > 5 * 1024 * 1024) {
        throw new RuntimeException('Attachment must be 5MB or smaller.');
    }
    $allowed = [
        'application/pdf' => 'pdf', 'image/jpeg' => 'jpg', 'image/png' => 'png',
        'text/plain' => 'txt', 'application/msword' => 'doc',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
    ];
    $mime = mime_content_type($file['tmp_name']);
    if (!isset($allowed[$mime])) {
        throw new RuntimeException('Attachment type not allowed. Use PDF, Word, TXT, JPG, or PNG.');
    }
    $dir = __DIR__ . '/assets/uploads/email-attachments';
    if (!is_dir($dir)) mkdir($dir, 0775, true);
    $storedName = bin2hex(random_bytes(16)) . '.' . $allowed[$mime];
    if (!move_uploaded_file($file['tmp_name'], $dir . '/' . $storedName)) {
        throw new RuntimeException('Could not save the attachment. Please try again.');
    }
    return ['path' => $dir . '/' . $storedName, 'name' => $file['name']];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_check($_POST['csrf_token'] ?? null)) {
        $_SESSION['admin_flash'] = ['type' => 'error', 'text' => 'Your session expired. Please try again.'];
    } elseif (($_POST['action'] ?? '') === 'compose_email') {
        $recipientUserId = (int)($_POST['recipient_user_id'] ?? 0);
        $manualEmail = trim($_POST['manual_email'] ?? '');
        $subject = trim($_POST['subject'] ?? '');
        $message = trim($_POST['message'] ?? '');
        $emailType = in_array($_POST['email_type'] ?? '', ['Subscription Reminder', 'Payment Reminder', 'System Notification', 'Announcement', 'Custom'], true)
            ? $_POST['email_type'] : 'Custom';

        $recipient = null;
        if ($recipientUserId) {
            $stmt = $db->prepare('SELECT user_id, full_name, email FROM users WHERE user_id = ?');
            $stmt->execute([$recipientUserId]);
            $recipient = $stmt->fetch();
        } elseif ($manualEmail !== '') {
            $stmt = $db->prepare('SELECT user_id, full_name, email FROM users WHERE email = ?');
            $stmt->execute([$manualEmail]);
            $recipient = $stmt->fetch();
        }

        if (!$recipient) {
            $_SESSION['admin_flash'] = ['type' => 'error', 'text' => 'Choose a recipient, or enter the email of an existing registered user.'];
        } elseif ($subject === '' || $message === '') {
            $_SESSION['admin_flash'] = ['type' => 'error', 'text' => 'Subject and message are required.'];
        } else {
            try {
                $attachment = gh_handle_email_attachment();
                $bodyHtml = nl2br(htmlspecialchars($message, ENT_QUOTES));
                $result = gh_send_email_with_attachment($db, (int)$recipient['user_id'], $recipient['email'], $recipient['full_name'] ?? '', $subject, $bodyHtml, $emailType, $attachment);
                if ($result['success']) {
                    gh_log_admin_action($db, (int)$user['user_id'], 'Send Email', 'email_log', null, "Sent \"{$subject}\" to {$recipient['email']}");
                    $_SESSION['admin_flash'] = ['type' => 'success', 'text' => "Email sent to {$recipient['email']}."];
                } else {
                    $_SESSION['admin_flash'] = ['type' => 'error', 'text' => "Email logged but not delivered: {$result['error']}"];
                }
            } catch (RuntimeException $e) {
                $_SESSION['admin_flash'] = ['type' => 'error', 'text' => $e->getMessage()];
            }
        }
    } elseif (($_POST['action'] ?? '') === 'send_verification') {
        $targetId = (int)($_POST['user_id'] ?? 0);
        $stmt = $db->prepare('SELECT user_id, full_name, email, email_verified FROM users WHERE user_id = ?');
        $stmt->execute([$targetId]);
        $target = $stmt->fetch();
        if (!$target) {
            $_SESSION['admin_flash'] = ['type' => 'error', 'text' => 'User not found.'];
        } elseif ($target['email_verified']) {
            $_SESSION['admin_flash'] = ['type' => 'error', 'text' => 'That user is already verified.'];
        } else {
            $result = gh_send_verification_email($db, $target);
            gh_log_admin_action($db, (int)$user['user_id'], 'Send Verification Email', 'users', $targetId, "Sent verification email to {$target['email']}");
            $_SESSION['admin_flash'] = $result['success']
                ? ['type' => 'success', 'text' => "Verification email sent to {$target['email']}."]
                : ['type' => 'error', 'text' => "Logged but not delivered: {$result['error']}"];
        }
    } elseif (($_POST['action'] ?? '') === 'delete_email') {
        $emailId = (int)($_POST['email_id'] ?? 0);
        $stmt = $db->prepare('DELETE FROM email_log WHERE email_id = ?');
        $stmt->execute([$emailId]);
        gh_log_admin_action($db, (int)$user['user_id'], 'Delete Email Log', 'email_log', $emailId, 'Deleted an email log entry');
        $_SESSION['admin_flash'] = ['type' => 'success', 'text' => 'Email log entry deleted.'];
    }
    header('Location: admin-email.php' . (isset($_GET['q']) || isset($_GET['filter']) ? '?' . http_build_query($_GET) : ''));
    exit;
}

function gh_send_email_with_attachment(PDO $db, int $userId, string $toEmail, string $toName, string $subject, string $bodyHtml, string $emailType, ?array $attachment): array {
    if (!$attachment) {
        return gh_send_email($db, $userId, $toEmail, $toName, $subject, $bodyHtml, $emailType, 'Admin');
    }
    if (GH_SMTP_USERNAME === '' || GH_SMTP_PASSWORD === '') {
        $status = 'Failed';
        $error = 'SMTP not configured yet — fill in mail-config.php to send real email.';
    } else {
        try {
            $mail = new PHPMailer\PHPMailer\PHPMailer(true);
            $mail->isSMTP();
            $mail->Host = GH_SMTP_HOST;
            $mail->SMTPAuth = true;
            $mail->Username = GH_SMTP_USERNAME;
            $mail->Password = GH_SMTP_PASSWORD;
            $mail->SMTPSecure = GH_SMTP_ENCRYPTION === 'ssl' ? PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS : PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port = GH_SMTP_PORT;
            $mail->setFrom(GH_SMTP_FROM_EMAIL !== '' ? GH_SMTP_FROM_EMAIL : GH_SMTP_USERNAME, GH_SMTP_FROM_NAME);
            $mail->addAddress($toEmail, $toName);
            $mail->addAttachment($attachment['path'], $attachment['name']);
            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body = $bodyHtml;
            $mail->send();
            $status = 'Sent';
            $error = null;
        } catch (Throwable $e) {
            $status = 'Failed';
            $error = $mail->ErrorInfo ?: $e->getMessage();
        }
    }
    $stmt = $db->prepare('INSERT INTO email_log (user_id, subject, message, email_type, sent_by, status) VALUES (?, ?, ?, ?, ?, ?)');
    $stmt->execute([$userId, $subject, $bodyHtml, $emailType, 'Admin', $status]);
    return ['success' => $status === 'Sent', 'status' => $status, 'error' => $error];
}

$flash = $_SESSION['admin_flash'] ?? null;
unset($_SESSION['admin_flash']);


$totalSent = (int) $db->query("SELECT COUNT(*) FROM email_log WHERE status='Sent'")->fetchColumn();
$emailsToday = (int) $db->query("SELECT COUNT(*) FROM email_log WHERE DATE(sent_at) = CURDATE()")->fetchColumn();
$pendingEmails = (int) $db->query("SELECT COUNT(*) FROM email_log WHERE status='Pending'")->fetchColumn();
$failedEmails = (int) $db->query("SELECT COUNT(*) FROM email_log WHERE status='Failed'")->fetchColumn();
$verifiedUsers = (int) $db->query('SELECT COUNT(*) FROM users WHERE email_verified = 1')->fetchColumn();
$unverifiedUsers = (int) $db->query('SELECT COUNT(*) FROM users WHERE email_verified = 0')->fetchColumn();

$q = trim($_GET['q'] ?? '');
$filter = $_GET['filter'] ?? 'all';

$where = [];
$params = [];
if ($q !== '') {
    $where[] = '(u.full_name LIKE ? OR u.email LIKE ? OR el.subject LIKE ?)';
    $params[] = "%{$q}%"; $params[] = "%{$q}%"; $params[] = "%{$q}%";
}
$filterMap = [
    'sent' => ['col' => 'el.status', 'val' => 'Sent'],
    'failed' => ['col' => 'el.status', 'val' => 'Failed'],
    'pending' => ['col' => 'el.status', 'val' => 'Pending'],
    'verification' => ['col' => 'el.email_type', 'val' => 'Verification'],
    'subscription' => ['col' => 'el.email_type', 'val' => 'Subscription Reminder'],
    'payment' => ['col' => 'el.email_type', 'val' => 'Payment Reminder'],
    'announcement' => ['col' => 'el.email_type', 'val' => 'Announcement'],
];
if (isset($filterMap[$filter])) {
    $where[] = $filterMap[$filter]['col'] . ' = ?';
    $params[] = $filterMap[$filter]['val'];
}
$whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

$stmt = $db->prepare(
    "SELECT el.email_id, el.subject, el.message, el.email_type, el.status, el.sent_at, el.sent_by,
            u.user_id, u.full_name, u.email, u.profile_image
     FROM email_log el
     JOIN users u ON u.user_id = el.user_id
     {$whereSql}
     ORDER BY el.sent_at DESC
     LIMIT 200"
);
$stmt->execute($params);
$emails = $stmt->fetchAll();

$allUsers = $db->query('SELECT user_id, full_name, email FROM users ORDER BY full_name')->fetchAll();
$unverifiedList = $db->query('SELECT user_id, full_name, email FROM users WHERE email_verified = 0 ORDER BY full_name')->fetchAll();

function gh_email_initials(string $name): string {
    $parts = preg_split('/\s+/', trim($name));
    if (count($parts) >= 2) return strtoupper(mb_substr($parts[0], 0, 1) . mb_substr($parts[1], 0, 1));
    return strtoupper(mb_substr($name, 0, 2));
}

$token = csrf_token();
$gh_page_title = 'Email Center | Admin';
require __DIR__ . '/partials/head.php';
?>
<body class="dash-body">

<?php require __DIR__ . '/partials/admin-sidebar.php'; ?>

<div class="lg:pl-[272px]">
  <?php require __DIR__ . '/partials/topbar.php'; ?>

  <main class="mx-auto max-w-[1600px] px-5 py-7 sm:px-8 lg:px-10 lg:py-9">
    <div class="flex flex-col gap-6">

      <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
        <div>
          <h1 class="text-[26px] font-semibold tracking-tight text-text-primary">Email Center</h1>
          <p class="mt-1 text-[15px] text-text-secondary">Manage and monitor all outgoing emails sent to users.</p>
        </div>
        <div class="flex flex-wrap gap-2">
          <button type="button" onclick="ghOpenModal('composeModal')" class="inline-flex items-center justify-center gap-2 rounded-xl bg-green-gradient px-4 py-2.5 text-sm font-semibold text-white shadow-glow transition-all hover:brightness-105 active:scale-[0.98]">
            <i data-lucide="pencil" class="h-4 w-4"></i> Compose Email
          </button>
          <button type="button" onclick="ghOpenModal('verifyModal')" class="rounded-xl border border-border bg-white px-4 py-2.5 text-sm font-semibold text-text-primary hover:bg-neutral-50">
            <i data-lucide="shield-check" class="mr-1 inline h-4 w-4"></i> Send Verification Email
          </button>
          <button type="button" onclick="location.reload()" class="rounded-xl border border-border bg-white px-4 py-2.5 text-sm font-semibold text-text-primary hover:bg-neutral-50">
            <i data-lucide="refresh-cw" class="mr-1 inline h-4 w-4"></i> Refresh
          </button>
        </div>
      </div>

      <?php if ($flash): ?>
        <div class="rounded-xl border px-4 py-3 text-sm font-semibold <?= $flash['type'] === 'success' ? 'border-primary/20 bg-light text-secondary' : 'border-red-200 bg-red-50 text-red-600' ?>">
          <?= htmlspecialchars($flash['text'], ENT_QUOTES) ?>
        </div>
      <?php endif; ?>

      <!-- Stats -->
      <div class="grid grid-cols-2 gap-4 sm:grid-cols-3 xl:grid-cols-6">
        <?php
        $stats = [
            ['label' => 'Total Emails Sent', 'value' => $totalSent, 'icon' => 'send'],
            ['label' => 'Emails Today', 'value' => $emailsToday, 'icon' => 'calendar'],
            ['label' => 'Pending Emails', 'value' => $pendingEmails, 'icon' => 'clock'],
            ['label' => 'Failed Emails', 'value' => $failedEmails, 'icon' => 'circle-x'],
            ['label' => 'Verified Users', 'value' => $verifiedUsers, 'icon' => 'shield-check'],
            ['label' => 'Unverified Users', 'value' => $unverifiedUsers, 'icon' => 'shield-alert'],
        ];
        foreach ($stats as $s): ?>
        <div class="rounded-2xl border border-border/60 bg-white p-5 shadow-soft">
          <div class="mb-3 flex h-9 w-9 items-center justify-center rounded-xl bg-light">
            <i data-lucide="<?= $s['icon'] ?>" class="h-4 w-4 text-secondary" stroke-width="2.25"></i>
          </div>
          <div class="text-xl font-semibold text-text-primary"><?= (int)$s['value'] ?></div>
          <div class="mt-0.5 text-xs text-text-secondary"><?= htmlspecialchars($s['label'], ENT_QUOTES) ?></div>
        </div>
        <?php endforeach; ?>
      </div>


      <div class="rounded-2xl border border-border/60 bg-white p-5 shadow-soft">
        <form method="get" action="admin-email.php" class="flex flex-col gap-4">
          <div class="relative max-w-md">
            <i data-lucide="search" class="pointer-events-none absolute left-4 top-1/2 h-4 w-4 -translate-y-1/2 text-text-secondary"></i>
            <input type="text" name="q" value="<?= htmlspecialchars($q, ENT_QUOTES) ?>" placeholder="Search by name, email, or subject…" class="h-11 w-full rounded-xl border border-border pl-11 pr-4 text-sm focus:border-primary focus:outline-none">
          </div>
          <input type="hidden" name="filter" id="filterInput" value="<?= htmlspecialchars($filter, ENT_QUOTES) ?>">
          <div class="flex flex-wrap gap-2">
            <?php
            $filters = ['all' => 'All', 'sent' => 'Sent', 'failed' => 'Failed', 'pending' => 'Pending',
                'verification' => 'Verification Emails', 'subscription' => 'Subscription Emails',
                'payment' => 'Payment Emails', 'announcement' => 'Announcement Emails'];
            foreach ($filters as $key => $label): ?>
              <button type="submit" name="filter" value="<?= $key ?>" class="rounded-full border px-3.5 py-1.5 text-xs font-semibold transition-colors <?= $filter === $key ? 'border-primary bg-light text-secondary' : 'border-border text-text-secondary hover:bg-neutral-50' ?>"><?= htmlspecialchars($label, ENT_QUOTES) ?></button>
            <?php endforeach; ?>
          </div>
        </form>
      </div>

      <div class="rounded-2xl border border-border/60 bg-white shadow-soft">
        <div class="p-6 pb-3"><h2 class="text-base font-semibold text-text-primary">Email History</h2></div>
        <div class="overflow-x-auto p-6 pt-0">
          <table class="w-full border-collapse text-sm">
            <thead>
              <tr class="border-b border-border/70 text-left">
                <th class="pb-3 pr-4 text-xs font-medium uppercase tracking-wide text-text-secondary">Photo</th>
                <th class="pb-3 pr-4 text-xs font-medium uppercase tracking-wide text-text-secondary">Full Name</th>
                <th class="pb-3 pr-4 text-xs font-medium uppercase tracking-wide text-text-secondary">Email</th>
                <th class="pb-3 pr-4 text-xs font-medium uppercase tracking-wide text-text-secondary">Subject</th>
                <th class="pb-3 pr-4 text-xs font-medium uppercase tracking-wide text-text-secondary">Type</th>
                <th class="pb-3 pr-4 text-xs font-medium uppercase tracking-wide text-text-secondary">Status</th>
                <th class="pb-3 pr-4 text-xs font-medium uppercase tracking-wide text-text-secondary">Date Sent</th>
                <th class="pb-3 pr-0 text-xs font-medium uppercase tracking-wide text-text-secondary">Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($emails as $e):
                $statusClass = $e['status'] === 'Sent' ? 'bg-light text-secondary' : ($e['status'] === 'Pending' ? 'bg-amber-50 text-amber-600' : 'bg-red-50 text-red-600');
              ?>
              <tr class="border-b border-border/40 last:border-0">
                <td class="py-3 pr-4">
                  <?php if (!empty($e['profile_image'])): ?>
                    <img src="<?= htmlspecialchars($e['profile_image'], ENT_QUOTES) ?>" alt="" class="h-8 w-8 rounded-full object-cover">
                  <?php else: ?>
                    <span class="flex h-8 w-8 items-center justify-center rounded-full bg-green-gradient text-[11px] font-semibold text-white"><?= htmlspecialchars(gh_email_initials($e['full_name'] ?: $e['email']), ENT_QUOTES) ?></span>
                  <?php endif; ?>
                </td>
                <td class="py-3 pr-4 font-medium text-text-primary"><?= htmlspecialchars($e['full_name'] ?: '—', ENT_QUOTES) ?></td>
                <td class="py-3 pr-4 text-text-secondary"><?= htmlspecialchars($e['email'], ENT_QUOTES) ?></td>
                <td class="py-3 pr-4 text-text-primary"><?= htmlspecialchars($e['subject'] ?: '—', ENT_QUOTES) ?></td>
                <td class="py-3 pr-4 text-text-secondary"><?= htmlspecialchars($e['email_type'], ENT_QUOTES) ?></td>
                <td class="py-3 pr-4"><span class="rounded-full px-2.5 py-1 text-[11px] font-semibold <?= $statusClass ?>"><?= htmlspecialchars($e['status'], ENT_QUOTES) ?></span></td>
                <td class="py-3 pr-4 text-text-secondary"><?= htmlspecialchars(date('j M Y', strtotime($e['sent_at'])), ENT_QUOTES) ?></td>
                <td class="py-3 pr-0">
                  <div class="flex flex-wrap items-center gap-2">
                    <button type="button" class="rounded-lg border border-border px-3 py-1.5 text-xs font-semibold text-text-secondary hover:bg-neutral-50" onclick="ghShowEmailView(<?= htmlspecialchars(json_encode($e['subject']), ENT_QUOTES) ?>, <?= htmlspecialchars(json_encode($e['message']), ENT_QUOTES) ?>)">View</button>
                    <form method="post" action="admin-email.php" class="inline">
                      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($token, ENT_QUOTES) ?>">
                      <input type="hidden" name="action" value="send_verification">
                      <input type="hidden" name="user_id" value="<?= (int)$e['user_id'] ?>">
                      <?php if ($e['email_type'] === 'Verification'): ?>
                        <button type="submit" class="rounded-lg border border-border px-3 py-1.5 text-xs font-semibold text-text-secondary hover:bg-neutral-50">Resend</button>
                      <?php endif; ?>
                    </form>
                    <form method="post" action="admin-email.php" class="inline" onsubmit="return confirm('Delete this email log entry?');">
                      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($token, ENT_QUOTES) ?>">
                      <input type="hidden" name="action" value="delete_email">
                      <input type="hidden" name="email_id" value="<?= (int)$e['email_id'] ?>">
                      <button type="submit" class="rounded-lg border border-red-200 bg-red-50 px-3 py-1.5 text-xs font-semibold text-red-600 hover:bg-red-100">Delete</button>
                    </form>
                  </div>
                </td>
              </tr>
              <?php endforeach; ?>
              <?php if (empty($emails)): ?>
              <tr><td colspan="8" class="py-6 text-center text-text-secondary">No emails match this search/filter.</td></tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>

    </div>
  </main>
</div>


<div id="composeModal" class="fixed inset-0 z-[60] hidden items-center justify-center bg-black/40 p-4">
  <div class="w-full max-w-lg rounded-2xl bg-white p-6 shadow-2xl">
    <div class="mb-4 flex items-center justify-between">
      <h2 class="text-lg font-semibold text-text-primary">Compose Email</h2>
      <button type="button" onclick="ghCloseModal('composeModal')" class="text-text-secondary hover:text-text-primary"><i data-lucide="x" class="h-5 w-5"></i></button>
    </div>
    <form method="post" action="admin-email.php" enctype="multipart/form-data" class="flex flex-col gap-3">
      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($token, ENT_QUOTES) ?>">
      <input type="hidden" name="action" value="compose_email">
      <div>
        <label class="mb-1.5 block text-xs font-semibold text-text-secondary">Recipient</label>
        <select name="recipient_user_id" class="w-full rounded-xl border border-border px-3.5 py-2.5 text-sm focus:border-primary focus:outline-none">
          <option value="">— Or enter email manually below —</option>
          <?php foreach ($allUsers as $u): ?>
            <option value="<?= (int)$u['user_id'] ?>"><?= htmlspecialchars($u['full_name'] ?: $u['email'], ENT_QUOTES) ?> (<?= htmlspecialchars($u['email'], ENT_QUOTES) ?>)</option>
          <?php endforeach; ?>
        </select>
        <input type="email" name="manual_email" placeholder="or type a registered user's email" class="mt-2 w-full rounded-xl border border-border px-3.5 py-2.5 text-sm focus:border-primary focus:outline-none">
      </div>
      <div>
        <label class="mb-1.5 block text-xs font-semibold text-text-secondary">Email Type</label>
        <select name="email_type" class="w-full rounded-xl border border-border px-3.5 py-2.5 text-sm focus:border-primary focus:outline-none">
          <option value="Custom">Custom Email</option>
          <option value="Announcement">Announcement</option>
          <option value="Subscription Reminder">Subscription Reminder</option>
          <option value="Payment Reminder">Payment Reminder</option>
          <option value="System Notification">System Notification</option>
        </select>
      </div>
      <div>
        <label class="mb-1.5 block text-xs font-semibold text-text-secondary">Subject</label>
        <input type="text" name="subject" required class="w-full rounded-xl border border-border px-3.5 py-2.5 text-sm focus:border-primary focus:outline-none">
      </div>
      <div>
        <label class="mb-1.5 block text-xs font-semibold text-text-secondary">Message</label>
        <textarea name="message" rows="5" required class="w-full rounded-xl border border-border px-3.5 py-2.5 text-sm focus:border-primary focus:outline-none"></textarea>
      </div>
      <div>
        <label class="mb-1.5 block text-xs font-semibold text-text-secondary">Attachment <span style="font-weight:400;">(optional, max 5MB)</span></label>
        <input type="file" name="attachment" accept=".pdf,.doc,.docx,.txt,.jpg,.jpeg,.png" class="w-full text-sm">
      </div>
      <div class="mt-2 flex gap-3">
        <button type="submit" class="inline-flex items-center justify-center gap-2 rounded-xl bg-green-gradient px-4 py-2.5 text-sm font-semibold text-white shadow-glow transition-all hover:brightness-105 active:scale-[0.98] flex-1">Send</button>
        <button type="button" onclick="ghCloseModal('composeModal')" class="rounded-xl border border-border px-4 py-2.5 text-sm font-semibold text-text-secondary hover:bg-neutral-50">Cancel</button>
      </div>
    </form>
  </div>
</div>


<div id="verifyModal" class="fixed inset-0 z-[60] hidden items-center justify-center bg-black/40 p-4">
  <div class="w-full max-w-md rounded-2xl bg-white p-6 shadow-2xl">
    <div class="mb-4 flex items-center justify-between">
      <h2 class="text-lg font-semibold text-text-primary">Send Verification Email</h2>
      <button type="button" onclick="ghCloseModal('verifyModal')" class="text-text-secondary hover:text-text-primary"><i data-lucide="x" class="h-5 w-5"></i></button>
    </div>
    <?php if (empty($unverifiedList)): ?>
      <p class="text-sm text-text-secondary">Every user is already verified.</p>
    <?php else: ?>
      <form method="post" action="admin-email.php" class="flex flex-col gap-3">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($token, ENT_QUOTES) ?>">
        <input type="hidden" name="action" value="send_verification">
        <label class="text-xs font-semibold text-text-secondary">Unverified User</label>
        <select name="user_id" class="w-full rounded-xl border border-border px-3.5 py-2.5 text-sm focus:border-primary focus:outline-none">
          <?php foreach ($unverifiedList as $u): ?>
            <option value="<?= (int)$u['user_id'] ?>"><?= htmlspecialchars($u['full_name'] ?: $u['email'], ENT_QUOTES) ?> (<?= htmlspecialchars($u['email'], ENT_QUOTES) ?>)</option>
          <?php endforeach; ?>
        </select>
        <button type="submit" class="inline-flex items-center justify-center gap-2 rounded-xl bg-green-gradient px-4 py-2.5 text-sm font-semibold text-white shadow-glow transition-all hover:brightness-105 active:scale-[0.98] mt-2">Send Verification Email</button>
      </form>
    <?php endif; ?>
  </div>
</div>

<div id="viewModal" class="fixed inset-0 z-[60] hidden items-center justify-center bg-black/40 p-4">
  <div class="w-full max-w-lg rounded-2xl bg-white p-6 shadow-2xl">
    <div class="mb-4 flex items-center justify-between">
      <h2 id="viewModalSubject" class="text-lg font-semibold text-text-primary"></h2>
      <button type="button" onclick="ghCloseModal('viewModal')" class="text-text-secondary hover:text-text-primary"><i data-lucide="x" class="h-5 w-5"></i></button>
    </div>
    <div id="viewModalBody" class="max-h-[50vh] overflow-y-auto rounded-xl bg-neutral-50 p-4 text-sm text-text-primary"></div>
  </div>
</div>

<script src="js/dashboard-app.js?v=1"></script>
<script>
  function ghOpenModal(id) {
    const el = document.getElementById(id);
    el.classList.remove('hidden');
    el.classList.add('flex');
  }
  function ghCloseModal(id) {
    const el = document.getElementById(id);
    el.classList.add('hidden');
    el.classList.remove('flex');
  }
  function ghShowEmailView(subject, message) {
    document.getElementById('viewModalSubject').textContent = subject || '(no subject)';
    document.getElementById('viewModalBody').innerHTML = message || '(empty message)';
    ghOpenModal('viewModal');
  }
</script>
</body>
</html>
