<?php
// Shared admin sidebar (desktop + mobile drawer) — same light theme/tokens
// as the user-facing partials/sidebar.php (white surface, green-gradient
// active pill) so the admin panel matches the rest of the site instead of
// looking like a different product. Self-contained — defines its own nav
// list (unlike partials/sidebar.php, which reads $nav_items from
// dashboard-data.php) since the admin menu is static and pages get built
// out stage by stage. 'href' => null means that admin page doesn't exist
// yet, rendered as an inert placeholder button. Requires
// $display_name/$initials/$profile_image_url from partials/current-user.php
// to already be in scope.
$admin_current_page = basename($_SERVER['SCRIPT_NAME']);
// Dashboard/Users both live inside admin.php now (tabs), so the
// active-nav check needs the ?tab= query too, not just the basename.
$admin_current_tab = $_GET['tab'] ?? 'overview';

$admin_nav_items = [
    ['label' => 'Dashboard',         'icon' => 'layout-dashboard', 'href' => 'admin.php', 'tab' => 'overview', 'group' => 'Main'],
    ['label' => 'Users',             'icon' => 'users',            'href' => 'admin.php?tab=users', 'tab' => 'users', 'group' => 'Main'],

    ['label' => 'Greenhouse',        'icon' => 'sprout',           'href' => 'admin-greenhouse.php', 'group' => 'Management'],
    ['label' => 'Reports',           'icon' => 'file-bar-chart-2', 'href' => 'admin-worker-report.php', 'group' => 'Management'],

    ['label' => 'Email Center',      'icon' => 'mail',             'href' => 'admin-email.php', 'group' => 'System'],
    ['label' => 'Live Chat',         'icon' => 'message-circle',   'href' => 'admin-chat.php', 'group' => 'System'],
    ['label' => 'Notifications',     'icon' => 'bell-ring',        'href' => 'admin-notifications.php', 'group' => 'System'],
    ['label' => 'Activity Log',      'icon' => 'history',          'href' => 'admin-activity-log.php', 'group' => 'System'],
    ['label' => 'Settings',          'icon' => 'settings',         'href' => 'admin-settings.php', 'group' => 'System'],
];

function gh_render_admin_nav(array $items, string $current_page, string $current_tab): void {
    $lastGroup = null;
    foreach ($items as $item):
        if ($item['group'] !== $lastGroup):
            $lastGroup = $item['group'];
            ?>
            <p class="<?= $lastGroup === 'Main' ? '' : 'mt-5' ?> mb-1.5 px-3.5 text-[10px] font-semibold uppercase tracking-wider text-neutral-400 sidebar-label"><?= htmlspecialchars($lastGroup) ?></p>
            <?php
        endif;

        if ($item['href'] === null) {
            $isActive = false;
        } elseif (isset($item['tab'])) {
            $isActive = $current_page === 'admin.php' && $current_tab === $item['tab'];
        } else {
            $isActive = $item['href'] === $current_page;
        }
        $tag = $item['href'] !== null ? 'a' : 'button';
        $classes = 'nav-item group relative flex w-full items-center gap-3 rounded-2xl px-3.5 py-2.5 text-sm font-medium transition-all duration-200 '
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
      <span class="truncate text-xs font-medium text-text-secondary">Admin Panel</span>
    </div>
  </div>

  <nav class="flex-1 space-y-1 overflow-y-auto px-4 custom-scroll sidebar-nav-desktop">
    <?php gh_render_admin_nav($admin_nav_items, $admin_current_page, $admin_current_tab); ?>
  </nav>

  <div class="px-4 pb-6 pt-2">
    <div class="mb-3 flex items-center gap-2.5 rounded-2xl border border-border/70 bg-neutral-50 px-3 py-2.5">
      <?php if (!empty($profile_image_url)): ?>
        <img src="<?= $profile_image_url ?>" alt="" class="h-8 w-8 shrink-0 rounded-full object-cover">
      <?php else: ?>
        <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-green-gradient text-[11px] font-semibold text-white"><?= htmlspecialchars($initials ?? 'AD', ENT_QUOTES) ?></span>
      <?php endif; ?>
      <div class="min-w-0 flex-1">
        <p class="truncate text-xs font-semibold text-text-primary"><?= htmlspecialchars($display_name ?? 'Admin', ENT_QUOTES) ?></p>
        <p class="truncate text-[10px] font-medium text-text-secondary">Administrator</p>
      </div>
    </div>
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
      <span class="truncate text-xs font-medium text-text-secondary">Admin Panel</span>
    </div>
    <button id="dashDrawerClose" type="button" aria-label="Close menu" class="ml-auto flex h-9 w-9 items-center justify-center rounded-xl text-text-secondary transition-colors hover:bg-neutral-100">
      <i data-lucide="x" class="h-4 w-4"></i>
    </button>
  </div>

  <nav class="flex-1 space-y-1 overflow-y-auto px-4 custom-scroll">
    <?php gh_render_admin_nav($admin_nav_items, $admin_current_page, $admin_current_tab); ?>
  </nav>

  <div class="px-4 pb-6 pt-2">
    <div class="mb-3 flex items-center gap-2.5 rounded-2xl border border-border/70 bg-neutral-50 px-3 py-2.5">
      <?php if (!empty($profile_image_url)): ?>
        <img src="<?= $profile_image_url ?>" alt="" class="h-8 w-8 shrink-0 rounded-full object-cover">
      <?php else: ?>
        <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-green-gradient text-[11px] font-semibold text-white"><?= htmlspecialchars($initials ?? 'AD', ENT_QUOTES) ?></span>
      <?php endif; ?>
      <div class="min-w-0 flex-1">
        <p class="truncate text-xs font-semibold text-text-primary"><?= htmlspecialchars($display_name ?? 'Admin', ENT_QUOTES) ?></p>
        <p class="truncate text-[10px] font-medium text-text-secondary">Administrator</p>
      </div>
    </div>
    <a href="logout.php" class="flex w-full items-center justify-center gap-2 rounded-xl border border-border/70 bg-white py-2.5 text-xs font-medium text-text-secondary transition-colors hover:bg-neutral-50 hover:text-text-primary">
      <i data-lucide="log-out" class="h-4 w-4"></i>
      Log Out
    </a>
  </div>
</aside>
