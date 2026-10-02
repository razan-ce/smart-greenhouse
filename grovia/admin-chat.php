<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/admin-lib.php';

$user = require_admin();
require_once __DIR__ . '/partials/current-user.php';
$db = get_db();

$selectedUserId = (int) ($_GET['user_id'] ?? 0);
$selectedUser = null;
if ($selectedUserId > 0) {
    $stmt = $db->prepare("SELECT user_id, full_name, email, profile_image FROM users WHERE user_id = ? AND role = 'User'");
    $stmt->execute([$selectedUserId]);
    $selectedUser = $stmt->fetch() ?: null;
}

// Every registered User, for the "start a new conversation" picker below —
// chat-threads.php only lists people who've already sent a message, so
// without this, an admin has no way to message someone first.
$allUsers = $db->query("SELECT user_id, full_name, email FROM users WHERE role = 'User' ORDER BY full_name")->fetchAll();

$token = csrf_token();
$gh_page_title = 'Live Chat | Admin';
require __DIR__ . '/partials/head.php';
?>
<body class="dash-body">

<?php require __DIR__ . '/partials/admin-sidebar.php'; ?>

<div class="lg:pl-[272px]">
  <?php require __DIR__ . '/partials/topbar.php'; ?>

  <main class="mx-auto max-w-[1600px] px-5 py-7 sm:px-8 lg:px-10 lg:py-9">
    <div class="flex flex-col gap-5">

      <div>
        <div class="mb-2 inline-flex items-center gap-2 rounded-full bg-light px-3 py-1.5 text-xs font-semibold text-secondary">
          <i data-lucide="message-circle" class="h-3.5 w-3.5"></i>
          Support
        </div>
        <h1 class="text-[26px] font-semibold tracking-tight text-text-primary sm:text-[30px]">Live Chat</h1>
        <p class="mt-1 text-[15px] text-text-secondary">Conversations with everyone using Grovia.</p>
      </div>

      <div class="grid grid-cols-1 gap-5 lg:grid-cols-[320px_1fr]">

        <!-- Conversation list -->
        <div class="rounded-2xl border border-border/60 bg-white shadow-soft">
          <div class="border-b border-border/60 px-4 py-3">
            <h2 class="text-sm font-semibold text-text-primary">Conversations</h2>
          </div>

          <!-- Start a new conversation — chat-threads.php below only shows
               people who already messaged first; this covers everyone else. -->
          <form method="get" action="admin-chat.php" class="flex items-center gap-2 border-b border-border/60 px-4 py-3">
            <select name="user_id" class="h-10 flex-1 rounded-xl border border-border/70 bg-neutral-50/70 px-3 text-sm text-text-primary focus:outline-none focus:ring-2 focus:ring-primary/25">
              <option value="">Start a new conversation…</option>
              <?php foreach ($allUsers as $u): ?>
                <option value="<?= (int) $u['user_id'] ?>"><?= htmlspecialchars($u['full_name'] ?: $u['email'], ENT_QUOTES) ?></option>
              <?php endforeach; ?>
            </select>
            <button type="submit" class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-green-gradient text-white shadow-glow transition-all hover:brightness-105 active:scale-95" aria-label="Start conversation">
              <i data-lucide="plus" class="h-4 w-4"></i>
            </button>
          </form>
          <div id="chatThreadList" class="max-h-[65vh] overflow-y-auto p-2">
            <p class="px-4 py-6 text-center text-xs text-text-secondary">Loading…</p>
          </div>
        </div>

        <!-- Selected thread -->
        <div class="flex flex-col rounded-2xl border border-border/60 bg-white shadow-soft">
          <?php if ($selectedUser): ?>
          <div class="flex items-center gap-3 border-b border-border/60 px-5 py-4">
            <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-green-gradient text-sm font-semibold text-white">
              <?= htmlspecialchars(strtoupper(mb_substr($selectedUser['full_name'], 0, 1)), ENT_QUOTES) ?>
            </span>
            <div>
              <p class="text-sm font-semibold text-text-primary"><?= htmlspecialchars($selectedUser['full_name'], ENT_QUOTES) ?></p>
              <p class="text-xs text-text-secondary"><?= htmlspecialchars($selectedUser['email'], ENT_QUOTES) ?></p>
            </div>
          </div>

          <div id="chatThreadMessages" class="flex-1 space-y-3 overflow-y-auto px-5 py-4" style="min-height: 360px; max-height: 55vh;">
            <p class="text-center text-xs text-text-secondary">Loading…</p>
          </div>

          <form id="chatReplyForm" class="flex items-center gap-2 border-t border-border/60 p-3" data-user-id="<?= (int) $selectedUser['user_id'] ?>">
            <input type="text" id="chatReplyInput" placeholder="Reply to <?= htmlspecialchars($selectedUser['full_name'], ENT_QUOTES) ?>…" autocomplete="off" maxlength="2000" class="h-11 flex-1 rounded-xl border border-border/70 bg-neutral-50/70 px-3.5 text-sm text-text-primary placeholder:text-text-secondary focus:outline-none focus:ring-2 focus:ring-primary/25 focus:border-primary/40 focus:bg-white">
            <button type="submit" aria-label="Send" class="flex h-12 w-12 shrink-0 items-center justify-center rounded-full bg-green-gradient text-white shadow-glow transition-all hover:brightness-105 active:scale-95">
              <i data-lucide="send" class="h-4 w-4"></i>
            </button>
          </form>
          <?php else: ?>
          <div class="flex flex-1 flex-col items-center justify-center gap-2 py-24 text-center">
            <i data-lucide="message-circle" class="h-8 w-8 text-neutral-300"></i>
            <p class="text-sm text-text-secondary">Select a conversation on the left.</p>
          </div>
          <?php endif; ?>
        </div>

      </div>
    </div>
  </main>
</div>

<style>
  .chat-msg { position: relative; max-width: 78%; padding: 8px 12px; border-radius: 14px; font-size: 0.85rem; line-height: 1.45; word-break: break-word; }
  .chat-msg-admin { margin-left: auto; background: linear-gradient(135deg, #22C55E, #16A34A); color: #fff; border-bottom-right-radius: 4px; }
  .chat-msg-user { margin-right: auto; background: #F3F4F6; color: #111827; border-bottom-left-radius: 4px; }
  .chat-msg-time { display: block; margin-top: 3px; font-size: 0.65rem; opacity: 0.7; }
  .chat-thread-row.is-active { background: #EAFBEF; box-shadow: inset 0 0 0 1.5px rgba(34,197,94,0.35); }
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
  const csrfToken = <?= json_encode($token) ?>;
  const selectedUserId = <?= json_encode($selectedUserId ?: null) ?>;
  const threadListEl = document.getElementById('chatThreadList');
  const messagesEl = document.getElementById('chatThreadMessages');
  const replyForm = document.getElementById('chatReplyForm');
  const replyInput = document.getElementById('chatReplyInput');

  function escapeHtml(str) {
    const div = document.createElement('div');
    div.textContent = str;
    return div.innerHTML;
  }

  function formatTime(iso) {
    const d = new Date(iso.replace(' ', 'T'));
    return d.toLocaleString([], { month: 'short', day: 'numeric', hour: 'numeric', minute: '2-digit' });
  }

  function loadThreads() {
    fetch('api/chat-threads.php')
      .then((r) => r.json())
      .then((data) => {
        if (!data.ok) return;
        if (!data.threads.length) {
          threadListEl.innerHTML = '<p class="px-4 py-6 text-center text-xs text-text-secondary">No conversations yet.</p>';
          return;
        }
        threadListEl.innerHTML = data.threads.map((t) => `
          <a href="admin-chat.php?user_id=${t.user_id}" class="chat-thread-row mb-1 flex items-center gap-3 rounded-2xl px-3 py-3 transition-all hover:bg-neutral-50 ${t.user_id == selectedUserId ? 'is-active' : ''}">
            <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-light text-xs font-semibold text-secondary">
              ${escapeHtml(t.full_name.charAt(0).toUpperCase())}
            </span>
            <span class="min-w-0 flex-1">
              <span class="flex items-center justify-between gap-2">
                <span class="truncate text-sm font-medium text-text-primary">${escapeHtml(t.full_name)}</span>
                ${t.unread_count > 0 ? `<span class="flex h-5 w-5 shrink-0 items-center justify-center rounded-full bg-green-gradient text-[10px] font-semibold text-white">${t.unread_count}</span>` : ''}
              </span>
              <span class="block truncate text-xs text-text-secondary">${t.last_message ? escapeHtml(t.last_message) : 'No messages yet'}</span>
            </span>
          </a>
        `).join('');
      })
      .catch(() => {});
  }

  function renderMessages(messages) {
    if (!messagesEl) return;
    if (!messages.length) {
      messagesEl.innerHTML = '<p class="text-center text-xs text-text-secondary">No messages yet.</p>';
      return;
    }
    messagesEl.innerHTML = messages.map((m) => {
      const isOwn = m.sender_role === 'Admin';
      return `
      <div class="chat-msg ${isOwn ? 'chat-msg-admin' : 'chat-msg-user'}" data-message-id="${m.message_id}">
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
    if (!selectedUserId || !messagesEl) return;
    fetch(`api/chat-fetch.php?user_id=${selectedUserId}`)
      .then((r) => r.json())
      .then((data) => { if (data.ok) renderMessages(data.messages); })
      .catch(() => {});
  }

  loadThreads();
  loadMessages();
  setInterval(loadThreads, 8000);
  if (selectedUserId) setInterval(loadMessages, 6000);

  replyForm?.addEventListener('submit', (e) => {
    e.preventDefault();
    const message = replyInput.value.trim();
    if (!message) return;
    replyInput.value = '';
    const body = new URLSearchParams({ csrf_token: csrfToken, message, user_id: replyForm.dataset.userId });
    fetch('api/chat-send.php', { method: 'POST', body })
      .then((r) => r.json())
      .then((data) => { if (data.ok) { loadMessages(); loadThreads(); } })
      .catch(() => {});
  });
})();
</script>

</body>
</html>
