<?php
// Shared sidebar (desktop + mobile drawer) for every app-shell page.
// Requires $user in scope (from require_login()/require_admin()) so the
// nav can differ for Admins — they get "Admin Panel" pointing at admin.php,
// not a link back to the regular user dashboard.
$current_page = basename($_SERVER['SCRIPT_NAME']);

$nav_items = ($user['role'] ?? '') === 'Admin'
    ? [['label' => 'Admin Panel', 'icon' => 'shield', 'href' => 'admin.php']]
    : [
        ['label' => 'Dashboard', 'icon' => 'layout-dashboard', 'href' => 'dashboard.php'],
        ['label' => 'Sensors', 'icon' => 'radar', 'href' => 'sensors.php'],
        ['label' => 'Schedule', 'icon' => 'calendar-clock', 'href' => 'schedule.php'],
        ['label' => 'AI Assistant', 'icon' => 'bot', 'href' => 'assistant.php'],
        ['label' => 'AI Camera', 'icon' => 'camera', 'href' => 'ai-camera.php'],
        ['label' => 'Greenhouse Planner', 'icon' => 'layout-grid', 'href' => 'greenhouse-planner.php'],
        ['label' => 'Report', 'icon' => 'file-bar-chart-2', 'href' => 'report.php'],
        ['label' => 'Settings', 'icon' => 'settings', 'href' => 'settings.php'],
    ];

function gh_render_nav(array $nav_items, string $current_page, bool $mobile = false): void {
    foreach ($nav_items as $item):
        $isActive = $item['href'] !== null && $item['href'] === $current_page;
        $tag = $item['href'] !== null ? 'a' : 'button';
        $classes = 'nav-item group relative flex w-full items-center gap-3 rounded-2xl px-3.5 py-3 text-sm font-medium transition-all duration-200 '
            . ($isActive ? 'is-active text-white' : 'text-text-secondary hover:bg-neutral-50 hover:text-text-primary');
        ?>
        <<?= $tag ?>
            <?= $tag === 'a' ? 'href="' . htmlspecialchars($item['href']) . '"' : 'type="button" disabled' ?>
            class="<?= $classes ?>"
        >
            <?php if ($isActive): ?>
            <span class="nav-pill absolute inset-0 rounded-2xl bg-green-gradient shadow-glow"></span>
            <?php endif; ?>
            <i data-lucide="<?= $item['icon'] ?>" class="relative z-10 h-[18px] w-[18px] shrink-0 transition-transform duration-200 group-hover:scale-105 <?= $isActive ? 'text-white' : 'text-text-secondary group-hover:text-primary' ?>" stroke-width="2"></i>
            <span class="relative z-10 truncate sidebar-label"><?= htmlspecialchars($item['label']) ?></span>
            <?php if ($isActive): ?>
            <span class="relative z-10 ml-auto h-1.5 w-1.5 rounded-full bg-white/90 sidebar-label"></span>
            <?php endif; ?>
        </<?= $tag ?>>
    <?php endforeach;
}
?>

<div id="dashBackdrop" class="dash-backdrop fixed inset-0 z-40 bg-black/30 backdrop-blur-sm lg:hidden"></div>

<!-- Desktop sidebar -->
<aside id="dashSidebarDesktop" class="fixed inset-y-0 left-0 z-40 hidden w-[272px] flex-col border-r border-border/70 bg-white/95 backdrop-blur-xl transition-all duration-300 lg:flex">
  <div class="flex items-center gap-3 px-6 pt-7 pb-6">
    <div class="relative flex h-11 w-11 shrink-0 items-center justify-center overflow-hidden rounded-2xl bg-white shadow-glow">
      <img src="assets/logo-mark.png" alt="Grovia logo" class="h-full w-full object-cover">
    </div>
    <div class="flex min-w-0 flex-col">
      <span class="truncate text-[15px] font-semibold tracking-tight text-text-primary">Grovia</span>
      <span class="truncate text-xs font-medium text-text-secondary">Smart Controller</span>
    </div>
  </div>

  <nav class="flex-1 space-y-1 overflow-y-auto px-4 custom-scroll sidebar-nav-desktop">
    <?php gh_render_nav($nav_items, $current_page); ?>
  </nav>

  <div class="px-4 pb-6 pt-2">
    <a href="logout.php" class="flex w-full items-center justify-center gap-2 rounded-xl border border-border/70 bg-white py-2.5 text-xs font-medium text-text-secondary transition-colors hover:bg-neutral-50 hover:text-text-primary">
      <i data-lucide="log-out" class="h-4 w-4"></i>
      Log Out
    </a>
  </div>
</aside>

<!-- Mobile drawer -->
<aside id="dashSidebarMobile" class="dash-drawer fixed inset-y-0 left-0 z-50 flex w-[80vw] max-w-[300px] flex-col bg-white shadow-2xl lg:hidden">
  <div class="flex items-center gap-3 px-6 pt-7 pb-6">
    <div class="relative flex h-11 w-11 shrink-0 items-center justify-center overflow-hidden rounded-2xl bg-white shadow-glow">
      <img src="assets/logo-mark.png" alt="Grovia logo" class="h-full w-full object-cover">
    </div>
    <div class="flex min-w-0 flex-col">
      <span class="truncate text-[15px] font-semibold tracking-tight text-text-primary">Grovia</span>
      <span class="truncate text-xs font-medium text-text-secondary">Smart Controller</span>
    </div>
    <button id="dashDrawerClose" type="button" aria-label="Close menu" class="ml-auto flex h-9 w-9 items-center justify-center rounded-xl text-text-secondary transition-colors hover:bg-neutral-100">
      <i data-lucide="x" class="h-4 w-4"></i>
    </button>
  </div>

  <nav class="flex-1 space-y-1 overflow-y-auto px-4 custom-scroll">
    <?php gh_render_nav($nav_items, $current_page, true); ?>
  </nav>

  <div class="px-4 pb-6 pt-2">
    <a href="logout.php" class="flex w-full items-center justify-center gap-2 rounded-xl border border-border/70 bg-white py-2.5 text-xs font-medium text-text-secondary transition-colors hover:bg-neutral-50 hover:text-text-primary">
      <i data-lucide="log-out" class="h-4 w-4"></i>
      Log Out
    </a>
  </div>
</aside>
