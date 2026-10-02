<?php

$gh_is_admin_topbar = ($user['role'] ?? '') === 'Admin';
$gh_chat_token = csrf_token();



$gh_profile_extra = get_db()->prepare('SELECT phone, created_at FROM users WHERE user_id = ?');
$gh_profile_extra->execute([$user['user_id']]);
$gh_profile_extra = $gh_profile_extra->fetch() ?: ['phone' => null, 'created_at' => null];

if ($gh_is_admin_topbar) {
    $gh_chat_unread = (int) get_db()->query(
        "SELECT COUNT(*) FROM chat_messages WHERE sender_role = 'User' AND is_read_by_admin = 0"
    )->fetchColumn();
} else {
    $gh_chat_online = (bool) get_db()->query(
        "SELECT 1 FROM users WHERE role = 'Admin' AND last_seen >= DATE_SUB(NOW(), INTERVAL 5 MINUTE) LIMIT 1"
    )->fetchColumn();

    $stmt = get_db()->prepare("SELECT COUNT(*) FROM chat_messages WHERE user_id = ? AND sender_role = 'Admin' AND is_read_by_user = 0");
    $stmt->execute([$user['user_id']]);
    $gh_chat_unread = (int) $stmt->fetchColumn();
}
?>
<header class="sticky top-0 z-30 flex items-center justify-between gap-4 border-b border-border/60 bg-white/80 px-5 py-4 backdrop-blur-xl sm:px-6 lg:px-10 lg:py-5">
  <div class="flex min-w-0 flex-1 items-center gap-3">
    <button id="dashMenuBtn" type="button" aria-label="Open menu" class="flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl border border-border/70 bg-white text-text-secondary transition-colors hover:bg-neutral-50 lg:hidden">
      <i data-lucide="menu" class="h-[18px] w-[18px]"></i>
    </button>

    <div class="relative w-full max-w-md">
      <i data-lucide="search" class="pointer-events-none absolute left-4 top-1/2 h-4 w-4 -translate-y-1/2 text-text-secondary"></i>
      <input type="text" placeholder="Search zones, sensors, reports..." class="h-11 w-full rounded-2xl border border-border/70 bg-neutral-50/70 pl-11 pr-4 text-sm text-text-primary placeholder:text-text-secondary shadow-none transition-colors focus:outline-none focus:ring-2 focus:ring-primary/25 focus:border-primary/40 focus:bg-white">
      <kbd class="kbd-hint pointer-events-none absolute right-3.5 top-1/2 hidden -translate-y-1/2 rounded-md border border-border bg-white px-1.5 py-0.5 text-[10px] font-medium text-text-secondary sm:block">⌘K</kbd>
    </div>
  </div>

  <div class="flex items-center gap-2.5 sm:gap-3">
  
  <?php if ($gh_is_admin_topbar): ?>
  <a href="admin-chat.php" aria-label="Messages" class="relative flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl border border-border/70 bg-white text-text-secondary transition-all duration-200 hover:border-primary/30 hover:bg-light/60 hover:text-secondary active:scale-95">
      <i data-lucide="message-circle" class="h-[18px] w-[18px]"></i>
      <?php if ($gh_chat_unread > 0): ?><span class="absolute right-2.5 top-2.5 h-2 w-2 rounded-full bg-primary ring-2 ring-white"></span><?php endif; ?>
    </a>
  <?php else: ?>
  <button id="supportChatToggleBtn" type="button" aria-label="Messages" class="relative flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl border border-border/70 bg-white text-text-secondary transition-all duration-200 hover:border-primary/30 hover:bg-light/60 hover:text-secondary active:scale-95">
      <i data-lucide="message-circle" class="h-[18px] w-[18px]"></i>
      <?php if ($gh_chat_unread > 0): ?><span class="absolute right-2.5 top-2.5 h-2 w-2 rounded-full bg-primary ring-2 ring-white"></span><?php endif; ?>
    </button>
  <?php endif; ?>

    <div class="relative">
      <button id="notifBellBtn" aria-label="Notifications" class="relative flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl border border-border/70 bg-white text-text-secondary transition-all duration-200 hover:border-primary/30 hover:bg-light/60 hover:text-secondary active:scale-95">
        <i data-lucide="bell" class="h-[18px] w-[18px]"></i>
        <span id="notifBadge" class="absolute -right-1 -top-1 hidden min-w-[18px] items-center justify-center rounded-full bg-red-500 px-1 text-[10px] font-bold leading-none text-white ring-2 ring-white">0</span>
      </button>

      <div id="notifDropdown" class="hidden absolute right-0 top-[calc(100%+10px)] z-40 w-80 max-w-[90vw] overflow-hidden rounded-2xl border border-border/60 bg-white shadow-soft-lg">
        <div class="flex items-center justify-between border-b border-border/60 px-4 py-3">
          <h3 class="text-sm font-semibold text-text-primary">Notifications</h3>
          <button id="notifMarkAllBtn" type="button" class="text-xs font-medium text-secondary hover:underline">Mark all as read</button>
        </div>
        <div id="notifList" class="max-h-80 overflow-y-auto">
          <p id="notifEmpty" class="px-4 py-8 text-center text-xs text-text-secondary">No notifications yet.</p>
        </div>
      </div>
    </div>

    <div class="mx-1 hidden h-8 w-px bg-border sm:block"></div>

    <div class="relative">
      <button id="profileMenuBtn" type="button" class="group flex items-center gap-3 rounded-2xl border border-border/70 bg-white py-1.5 pl-1.5 pr-3 transition-all duration-200 hover:border-primary/25 hover:shadow-soft active:scale-[0.98]">
        <?php if (!empty($profile_image_url)): ?>
        <span class="h-9 w-9 shrink-0 overflow-hidden rounded-full ring-2 ring-white shadow-sm">
          <img src="<?= $profile_image_url ?>" alt="" class="h-full w-full object-cover">
        </span>
        <?php else: ?>
        <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-green-gradient text-sm font-medium text-white ring-2 ring-white shadow-sm">
          <?= htmlspecialchars($initials) ?>
        </span>
        <?php endif; ?>
        <div class="hidden flex-col items-start leading-tight sm:flex">
          <span class="text-sm font-semibold text-text-primary"><?= htmlspecialchars($display_name) ?></span>
          <span class="text-[11px] font-medium text-text-secondary"><?= htmlspecialchars($user['role'] ?? 'User') ?></span>
        </div>
        <i data-lucide="chevron-down" class="hidden h-4 w-4 text-text-secondary transition-transform duration-200 sm:block" id="profileMenuChevron"></i>
      </button>

      <div id="profileMenuDropdown" class="hidden absolute right-0 top-[calc(100%+10px)] z-40 w-64 overflow-hidden rounded-2xl border border-border/60 bg-white shadow-soft-lg">
        <div class="flex items-center gap-3 border-b border-border/60 px-4 py-4">
          <?php if (!empty($profile_image_url)): ?>
          <span class="h-11 w-11 shrink-0 overflow-hidden rounded-full ring-2 ring-white shadow-sm">
            <img src="<?= $profile_image_url ?>" alt="" class="h-full w-full object-cover">
          </span>
          <?php else: ?>
          <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-full bg-green-gradient text-sm font-semibold text-white ring-2 ring-white shadow-sm">
            <?= htmlspecialchars($initials) ?>
          </span>
          <?php endif; ?>
          <div class="min-w-0">
            <p class="truncate text-sm font-semibold text-text-primary"><?= htmlspecialchars($display_name) ?></p>
            <p class="truncate text-xs text-text-secondary"><?= htmlspecialchars($email) ?></p>
          </div>
        </div>
        <div class="flex flex-col gap-2.5 px-4 py-3 text-xs">
          <div class="flex items-center justify-between">
            <span class="text-text-secondary">Role</span>
            <span class="font-medium text-text-primary"><?= htmlspecialchars($user['role'] ?? 'User') ?></span>
          </div>
          <?php if (!empty($gh_profile_extra['phone'])): ?>
          <div class="flex items-center justify-between">
            <span class="text-text-secondary">Phone</span>
            <span class="font-medium text-text-primary"><?= htmlspecialchars($gh_profile_extra['phone']) ?></span>
          </div>
          <?php endif; ?>
          <?php if (!empty($gh_profile_extra['created_at'])): ?>
          <div class="flex items-center justify-between">
            <span class="text-text-secondary">Member since</span>
            <span class="font-medium text-text-primary"><?= htmlspecialchars(date('M Y', strtotime($gh_profile_extra['created_at']))) ?></span>
          </div>
          <?php endif; ?>
          <div class="pt-1">
            <a href="settings.php" class="flex items-center gap-1.5 text-[11px] font-medium text-secondary hover:underline">
              <i data-lucide="settings" class="h-3 w-3"></i> Edit profile
            </a>
          </div>
        </div>
        <a href="logout.php" class="flex items-center gap-2 border-t border-border/60 px-4 py-3 text-xs font-medium text-text-secondary transition-colors hover:bg-neutral-50 hover:text-text-primary">
          <i data-lucide="log-out" class="h-3.5 w-3.5"></i>
          Log Out
        </a>
      </div>
    </div>
  </div>
</header>

<?php if (!$gh_is_admin_topbar): ?>
<!-- Chat with Admin — small slide-in panel, not a full page. IDs are all
     prefixed "support" so they never collide with assistant.php's own
     chat box (which uses plain #chatMessages/#chatInput) even though both
     can exist on the same page load. -->
<div id="supportChatBackdrop" class="chat-backdrop fixed inset-0 z-40 hidden bg-black/30 backdrop-blur-sm"></div>
<aside id="supportChatPanel" class="chat-panel fixed inset-y-0 right-0 z-50 flex w-full max-w-[380px] translate-x-full flex-col border-l border-border/70 bg-white shadow-2xl transition-transform duration-300">
  <div class="flex items-center justify-between gap-3 border-b border-border/60 px-5 py-4">
    <div>
      <h2 class="text-sm font-semibold text-text-primary">Chat with Admin</h2>
      <p class="mt-0.5 flex items-center gap-1.5 text-xs text-text-secondary">
        <span class="h-1.5 w-1.5 rounded-full <?= $gh_chat_online ? 'bg-primary' : 'bg-neutral-300' ?>"></span>
        <?= $gh_chat_online ? 'Online now' : "Offline — we'll reply when we're back" ?>
      </p>
    </div>
    <button id="supportChatCloseBtn" type="button" aria-label="Close chat" class="flex h-9 w-9 items-center justify-center rounded-xl text-text-secondary transition-colors hover:bg-neutral-100">
      <i data-lucide="x" class="h-4 w-4"></i>
    </button>
  </div>

  <div id="supportChatMessages" class="flex-1 space-y-3 overflow-y-auto px-5 py-4">
    <p class="text-center text-xs text-text-secondary">Loading…</p>
  </div>

  <form id="supportChatForm" class="flex items-center gap-2 border-t border-border/60 p-3">
    <input type="text" id="supportChatInput" placeholder="Type a message…" autocomplete="off" maxlength="2000" class="h-11 flex-1 rounded-xl border border-border/70 bg-neutral-50/70 px-3.5 text-sm text-text-primary placeholder:text-text-secondary focus:outline-none focus:ring-2 focus:ring-primary/25 focus:border-primary/40 focus:bg-white">
    <button type="submit" aria-label="Send" class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-green-gradient text-white shadow-glow transition-all hover:brightness-105 active:scale-95">
      <i data-lucide="send" class="h-4 w-4"></i>
    </button>
  </form>
</aside>

<style>
  .chat-panel.is-open { transform: translateX(0); }
  .chat-backdrop.is-open { display: block; }
  .chat-msg { position: relative; max-width: 82%; padding: 8px 12px; border-radius: 14px; font-size: 0.85rem; line-height: 1.45; word-break: break-word; }
  .chat-msg-user { margin-left: auto; background: linear-gradient(135deg, #22C55E, #16A34A); color: #fff; border-bottom-right-radius: 4px; }
  .chat-msg-admin { margin-right: auto; background: #F3F4F6; color: #111827; border-bottom-left-radius: 4px; }
  .chat-msg-time { display: block; margin-top: 3px; font-size: 0.65rem; opacity: 0.7; }
  .chat-msg-menu { position: absolute; top: -6px; right: -6px; display: none; }
  .chat-msg:hover .chat-msg-menu { display: block; }
  .chat-msg-menu-btn { display: flex; align-items: center; justify-content: center; width: 20px; height: 20px; border-radius: 9999px; background: #fff; border: 1px solid rgba(0,0,0,0.08); color: #6B7280; cursor: pointer; }
  .chat-msg-menu-btn svg { width: 12px; height: 12px; }
  .chat-msg-menu-dropdown { position: absolute; top: 22px; right: 0; min-width: 100px; background: #fff; border: 1px solid rgba(0,0,0,0.08); border-radius: 10px; box-shadow: 0 8px 20px rgba(0,0,0,0.12); overflow: hidden; z-index: 10; }
  .chat-msg-menu-dropdown button { display: block; width: 100%; text-align: left; padding: 8px 12px; font-size: 0.8rem; background: none; border: none; cursor: pointer; color: #111827; }
  .chat-msg-menu-dropdown button:hover { background: #F3F4F6; }
  .chat-msg-menu-dropdown .chat-msg-delete-btn { color: #DC2626; }
  .chat-msg-edit-input { display: block; width: 100%; background: rgba(255,255,255,0.15); border: 1px solid rgba(255,255,255,0.4); border-radius: 6px; padding: 3px 6px; font: inherit; color: inherit; }
</style>

<script>
(function () {
  const toggleBtn = document.getElementById('supportChatToggleBtn');
  const closeBtn = document.getElementById('supportChatCloseBtn');
  const panel = document.getElementById('supportChatPanel');
  const backdrop = document.getElementById('supportChatBackdrop');
  const messagesEl = document.getElementById('supportChatMessages');
  const form = document.getElementById('supportChatForm');
  const input = document.getElementById('supportChatInput');
  const csrfToken = <?= json_encode($gh_chat_token) ?>;

  let pollTimer = null;

  function escapeHtml(str) {
    const div = document.createElement('div');
    div.textContent = str;
    return div.innerHTML;
  }

  function formatTime(iso) {
    const d = new Date(iso.replace(' ', 'T'));
    return d.toLocaleTimeString([], { hour: 'numeric', minute: '2-digit' });
  }

  function renderMessages(messages) {
    if (!messages.length) {
      messagesEl.innerHTML = '<p class="text-center text-xs text-text-secondary">Send a message and the Grovia team will get back to you.</p>';
      return;
    }
    messagesEl.innerHTML = messages.map((m) => {
      const isOwn = m.sender_role === 'User';
      return `
      <div class="chat-msg ${isOwn ? 'chat-msg-user' : 'chat-msg-admin'}" data-message-id="${m.message_id}">
        ${isOwn ? `
          <div class="chat-msg-menu">
            <button type="button" class="chat-msg-menu-btn" aria-label="Message options"><i data-lucide="more-vertical"></i></button>
            <div class="chat-msg-menu-dropdown" hidden>
              <button type="button" class="chat-msg-edit-btn">Edit</button>
              <button type="button" class="chat-msg-delete-btn">Delete</button>
            </div>
          </div>
        ` : ''}
        <span class="chat-msg-text">${escapeHtml(m.message)}</span>
        <span class="chat-msg-time">${formatTime(m.created_at)}</span>
      </div>
    `;
    }).join('');
    if (window.lucide) window.lucide.createIcons();
    wireMessageMenus();
    messagesEl.scrollTop = messagesEl.scrollHeight;
  }

  function closeAllMenus() {
    messagesEl.querySelectorAll('.chat-msg-menu-dropdown').forEach((d) => { d.hidden = true; });
  }
  document.addEventListener('click', closeAllMenus);

  function wireMessageMenus() {
    messagesEl.querySelectorAll('.chat-msg-menu-btn').forEach((btn) => {
      btn.addEventListener('click', (e) => {
        e.stopPropagation();
        const dropdown = btn.nextElementSibling;
        const wasHidden = dropdown.hidden;
        closeAllMenus();
        dropdown.hidden = !wasHidden;
      });
    });
    messagesEl.querySelectorAll('.chat-msg-delete-btn').forEach((btn) => {
      btn.addEventListener('click', () => {
        closeAllMenus();
        deleteMessage(btn.closest('.chat-msg').dataset.messageId);
      });
    });
    messagesEl.querySelectorAll('.chat-msg-edit-btn').forEach((btn) => {
      btn.addEventListener('click', () => {
        closeAllMenus();
        startEditing(btn.closest('.chat-msg'));
      });
    });
  }

  function startEditing(bubble) {
    const textEl = bubble.querySelector('.chat-msg-text');
    const currentText = textEl.textContent;
    const editInput = document.createElement('input');
    editInput.type = 'text';
    editInput.className = 'chat-msg-edit-input';
    editInput.value = currentText;
    textEl.replaceWith(editInput);
    editInput.focus();
    editInput.setSelectionRange(editInput.value.length, editInput.value.length);

    function save() {
      const newText = editInput.value.trim();
      if (!newText || newText === currentText) { loadMessages(); return; }
      const body = new URLSearchParams({ csrf_token: csrfToken, message_id: bubble.dataset.messageId, message: newText });
      fetch('api/chat-edit.php', { method: 'POST', body })
        .then((r) => r.json())
        .then(() => loadMessages())
        .catch(() => loadMessages());
    }
    editInput.addEventListener('keydown', (e) => {
      if (e.key === 'Enter') { e.preventDefault(); save(); }
      if (e.key === 'Escape') { loadMessages(); }
    });
    editInput.addEventListener('blur', save);
  }

  function deleteMessage(messageId) {
    if (!confirm('Delete this message?')) return;
    const body = new URLSearchParams({ csrf_token: csrfToken, message_id: messageId });
    fetch('api/chat-delete.php', { method: 'POST', body })
      .then((r) => r.json())
      .then((data) => { if (data.ok) loadMessages(); })
      .catch(() => {});
  }

  function loadMessages() {
    fetch('api/chat-fetch.php')
      .then((r) => r.json())
      .then((data) => { if (data.ok) renderMessages(data.messages); })
      .catch(() => {});
  }

  function openPanel() {
    panel.classList.add('is-open');
    backdrop.classList.add('is-open');
    loadMessages();
    if (!pollTimer) pollTimer = setInterval(loadMessages, 6000);
    setTimeout(() => input.focus(), 300);
  }

  function closePanel() {
    panel.classList.remove('is-open');
    backdrop.classList.remove('is-open');
    if (pollTimer) { clearInterval(pollTimer); pollTimer = null; }
  }

  toggleBtn?.addEventListener('click', () => {
    panel.classList.contains('is-open') ? closePanel() : openPanel();
  });
  closeBtn?.addEventListener('click', closePanel);
  backdrop?.addEventListener('click', closePanel);

  form?.addEventListener('submit', (e) => {
    e.preventDefault();
    const message = input.value.trim();
    if (!message) return;
    input.value = '';
    const body = new URLSearchParams({ csrf_token: csrfToken, message });
    fetch('api/chat-send.php', { method: 'POST', body })
      .then((r) => r.json())
      .then((data) => { if (data.ok) loadMessages(); })
      .catch(() => {});
  });
})();
</script>
<?php endif; ?>

<!-- Toast popups for new alerts — drawn entirely by our own page, so unlike
     the browser's native Notification popup, this always works the same
     way in every browser, with no permission prompt needed. -->
<div id="toastContainer" class="pointer-events-none fixed right-5 top-20 z-50 flex w-80 max-w-[90vw] flex-col gap-2"></div>

<script>
(function () {
  const bellBtn = document.getElementById('notifBellBtn');
  const dropdown = document.getElementById('notifDropdown');
  const badge = document.getElementById('notifBadge');
  const list = document.getElementById('notifList');
  const emptyMsg = document.getElementById('notifEmpty');
  const markAllBtn = document.getElementById('notifMarkAllBtn');
  const toastContainer = document.getElementById('toastContainer');

  if (!bellBtn) return;

  let lastNotifiedId = 0; // highest notification_id we've already popped up (as a toast and/or desktop notification), so the same alert never shows twice

  function timeAgo(mysqlDatetime) {
    const then = new Date(mysqlDatetime.replace(' ', 'T'));
    const diffSec = Math.max(0, Math.round((Date.now() - then.getTime()) / 1000));
    if (diffSec < 60) return 'just now';
    const diffMin = Math.round(diffSec / 60);
    if (diffMin < 60) return diffMin + 'm ago';
    const diffHr = Math.round(diffMin / 60);
    if (diffHr < 24) return diffHr + 'h ago';
    return Math.round(diffHr / 24) + 'd ago';
  }

  // notifications.type is now always 'Sensor' (a fixed database ENUM), so
  // the icon is picked from the first word of the title instead, e.g.
  // "Gas Level Danger" -> 'Gas' -> wind icon.
  const TITLE_ICON = {
    'Gas': 'wind',
    'Temperature': 'thermometer',
    'Water': 'waves',
    'Soil': 'sprout',
    'Humidity': 'droplets',
    'ESP32': 'wifi',
  };

  function render(notifications) {
    list.innerHTML = '';
    if (!notifications.length) {
      list.appendChild(emptyMsg);
      return;
    }
    notifications.forEach((n) => {
      const row = document.createElement('div');
      row.className = 'flex gap-3 border-b border-border/40 px-4 py-3 last:border-0 cursor-pointer transition-colors hover:bg-neutral-50 ' + (n.is_read == 0 ? 'bg-light/40' : '');
      row.dataset.id = n.notification_id;

      const icon = TITLE_ICON[n.title.split(' ')[0]] || 'bell';
      row.innerHTML =
        '<div class="mt-0.5 flex h-8 w-8 shrink-0 items-center justify-center rounded-xl bg-white border border-border/60">' +
          '<i data-lucide="' + icon + '" class="h-4 w-4 text-secondary"></i>' +
        '</div>' +
        '<div class="min-w-0 flex-1">' +
          '<p class="text-xs font-semibold text-text-primary">' + n.title + '</p>' +
          '<p class="mt-0.5 text-xs text-text-secondary">' + n.message + '</p>' +
          '<p class="mt-1 text-[10px] text-text-secondary">' + timeAgo(n.created_at) + '</p>' +
        '</div>' +
        (n.is_read == 0 ? '<span class="mt-1 h-2 w-2 shrink-0 rounded-full bg-primary"></span>' : '');

      row.addEventListener('click', () => markRead(n.notification_id, row));
      list.appendChild(row);
    });
    if (window.lucide) window.lucide.createIcons();
  }

  // Draws one toast card in the top-right corner and removes it a few
  // seconds later. This is plain HTML we control, so — unlike the browser's
  // native Notification popup — it always works, in every browser, with no
  // permission prompt needed.
  function showToast(n) {
    const icon = TITLE_ICON[n.title.split(' ')[0]] || 'bell';

    const toast = document.createElement('div');
    toast.className = 'pointer-events-auto flex gap-3 rounded-2xl border border-border/60 bg-white p-4 shadow-soft-lg transition-all duration-300 translate-x-[120%] opacity-0';
    toast.innerHTML =
      '<div class="mt-0.5 flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-light">' +
        '<i data-lucide="' + icon + '" class="h-4 w-4 text-secondary"></i>' +
      '</div>' +
      '<div class="min-w-0 flex-1">' +
        '<p class="text-sm font-semibold text-text-primary">' + n.title + '</p>' +
        '<p class="mt-0.5 text-xs text-text-secondary">' + n.message + '</p>' +
      '</div>' +
      '<button type="button" aria-label="Dismiss" class="shrink-0 text-text-secondary hover:text-text-primary">' +
        '<i data-lucide="x" class="h-4 w-4"></i>' +
      '</button>';

    toastContainer.appendChild(toast);
    if (window.lucide) window.lucide.createIcons();

    // Slide in on the next frame (starting from the translate/opacity set
    // above lets the transition actually animate instead of snapping in).
    requestAnimationFrame(() => {
      toast.classList.remove('translate-x-[120%]', 'opacity-0');
    });

    function dismiss() {
      toast.classList.add('opacity-0');
      setTimeout(() => toast.remove(), 300); // matches the transition duration above
    }

    toast.querySelector('button').addEventListener('click', dismiss);
    toast.addEventListener('click', () => markRead(n.notification_id, toast));
    setTimeout(dismiss, 6000); // auto-dismiss after 6s if nobody interacts with it
  }

  // Shows a toast (always) and an OS-level popup (only if permission was
  // granted) for each unread alert the browser hasn't shown yet.
  function notifyNewAlerts(notifications) {
    notifications.forEach((n) => {
      if (n.is_read == 0 && n.notification_id > lastNotifiedId) {
        showToast(n);
        if (Notification.permission === 'granted') {
          new Notification(n.title, { body: n.message });
        }
      }
    });

    const maxId = notifications.reduce((max, n) => Math.max(max, n.notification_id), lastNotifiedId);
    lastNotifiedId = maxId;
  }

  function poll() {
    fetch('api/notifications-latest.php')
      .then((r) => r.json())
      .then((data) => {
        if (!data.ok) return;
        if (data.unread_count > 0) {
          badge.textContent = data.unread_count > 9 ? '9+' : data.unread_count;
          badge.classList.remove('hidden');
          badge.classList.add('flex');
        } else {
          badge.classList.add('hidden');
          badge.classList.remove('flex');
        }
        render(data.notifications || []);
        notifyNewAlerts(data.notifications || []);
      })
      .catch(() => {});
  }

  function markRead(id, row) {
    if (row.dataset.read === '1') return;
    row.dataset.read = '1';
    fetch('api/notifications-read.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: 'notification_id=' + encodeURIComponent(id),
    }).then(poll).catch(() => {});
  }

  bellBtn.addEventListener('click', (e) => {
    e.stopPropagation();
    dropdown.classList.toggle('hidden');
    if (Notification.permission === 'default') {
      Notification.requestPermission(); // must be triggered by a real click — Safari blocks this if called automatically
    }
  });

  markAllBtn.addEventListener('click', (e) => {
    e.stopPropagation();
    fetch('api/notifications-read.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: 'mark_all=1',
    }).then(poll).catch(() => {});
  });

  document.addEventListener('click', (e) => {
    if (!dropdown.contains(e.target) && !bellBtn.contains(e.target)) {
      dropdown.classList.add('hidden');
    }
  });

  poll();
  setInterval(poll, 60000); // was every 5s — checking that often made popups feel spammy, 30s is a calmer pace
})();
</script>

<script>
(function () {
  const btn = document.getElementById('profileMenuBtn');
  const dropdown = document.getElementById('profileMenuDropdown');
  const chevron = document.getElementById('profileMenuChevron');
  if (!btn || !dropdown) return;

  btn.addEventListener('click', (e) => {
    e.stopPropagation();
    dropdown.classList.toggle('hidden');
    chevron?.classList.toggle('rotate-180');
  });

  document.addEventListener('click', (e) => {
    if (!dropdown.contains(e.target) && !btn.contains(e.target)) {
      dropdown.classList.add('hidden');
      chevron?.classList.remove('rotate-180');
    }
  });
})();
</script>
