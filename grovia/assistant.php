<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';

$user = require_login();
if ($user['role'] === 'Admin') {
    header('Location: admin.php');
    exit;
}
require_once __DIR__ . '/partials/current-user.php';

$gh_page_title = 'AI Assistant | Grovia';
require __DIR__ . '/partials/head.php';
?>
<body class="dash-body">

<?php require __DIR__ . '/partials/sidebar.php'; ?>

<div class="lg:pl-[272px]">
  <?php require __DIR__ . '/partials/topbar.php'; ?>

  <main class="mx-auto max-w-[900px] px-5 py-7 sm:px-8 lg:px-10 lg:py-9">
    <div class="flex flex-col gap-8">

      <div>
        <h1 class="text-[28px] font-semibold tracking-tight text-text-primary sm:text-[32px]">AI Assistant</h1>
        <p class="mt-1 text-sm text-text-secondary">Ask anything about your greenhouse, your sensors, or plant care in general.</p>
      </div>

      <div class="flex flex-col overflow-hidden rounded-2xl border border-border/60 bg-white shadow-soft">
        <div id="chatMessages" class="flex h-[480px] flex-col gap-3 overflow-y-auto p-5">
          <p id="chatEmpty" class="mt-auto mb-auto text-center text-xs text-text-secondary">Ask me something like "why is my soil sensor reading low?"</p>
        </div>

        <div class="flex items-center gap-3 border-t border-border/60 p-4">
          <input id="chatInput" type="text" placeholder="Ask about your greenhouse..." class="h-11 flex-1 rounded-2xl border border-border/70 bg-neutral-50/70 px-4 text-sm text-text-primary placeholder:text-text-secondary focus:outline-none focus:ring-2 focus:ring-primary/25 focus:border-primary/40 focus:bg-white">
          <button id="chatSendBtn" type="button" class="flex h-11 shrink-0 items-center justify-center gap-2 rounded-2xl bg-green-gradient px-5 text-sm font-medium text-white shadow-sm transition-all duration-200 active:scale-95">
            <i data-lucide="send" class="h-4 w-4"></i>
            Send
          </button>
        </div>
      </div>

    </div>
  </main>
</div>

<script>
(function () {
  const messages = document.getElementById('chatMessages');
  const emptyMsg = document.getElementById('chatEmpty');
  const input = document.getElementById('chatInput');
  const sendBtn = document.getElementById('chatSendBtn');

  function addBubble(text, fromUser) {
    if (emptyMsg) emptyMsg.remove();

    const row = document.createElement('div');
    row.className = 'flex ' + (fromUser ? 'justify-end' : 'justify-start');
    row.innerHTML =
      '<span class="max-w-[80%] rounded-2xl px-4 py-2.5 text-sm leading-relaxed ' +
      (fromUser ? 'bg-green-gradient text-white' : 'bg-neutral-50 text-text-primary border border-border/60') +
      '"></span>';
    row.firstElementChild.textContent = text; 
    messages.appendChild(row);
    messages.scrollTop = messages.scrollHeight;
  }

  function setSending(isSending) {
    sendBtn.disabled = isSending;
    sendBtn.classList.toggle('opacity-60', isSending);
  }

  function send() {
    const text = input.value.trim();
    if (!text) return;
    addBubble(text, true);
    input.value = '';
    setSending(true);

    fetch('api/ai-chat.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ message: text }),
    })
      .then((r) => r.json())
      .then((data) => {
        addBubble(data.ok ? data.reply : (data.error || 'Something went wrong.'), false);
      })
      .catch(() => addBubble('Network error, try again.', false))
      .finally(() => setSending(false));
  }

  sendBtn.addEventListener('click', send);
  input.addEventListener('keydown', (e) => { if (e.key === 'Enter') send(); });
})();
</script>
</body>
</html>
