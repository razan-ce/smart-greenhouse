<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';

$user = require_worker_access();
if ($user['role'] === 'Admin') {
    header('Location: admin.php');
    exit;
}
require_once __DIR__ . '/partials/current-user.php';

$gh_page_title = 'Schedule | Grovia';
$gh_extra_head = '<link rel="stylesheet" href="css/schedule-page.css?v=1">';
require __DIR__ . '/partials/head.php';
?>
<body class="dash-body">

<?php require __DIR__ . '/partials/sidebar.php'; ?>

<div class="lg:pl-[272px]">
  <?php require __DIR__ . '/partials/topbar.php'; ?>

  <main class="mx-auto max-w-[1400px] px-5 py-6 sm:px-8 lg:px-8 lg:py-7">
    <div class="flex flex-col gap-6">

      <!-- Header -->
      <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
        <div>
          <div class="mb-2 inline-flex items-center gap-2 rounded-full bg-light px-3 py-1.5 text-xs font-semibold text-secondary">
            <i data-lucide="calendar-clock" class="h-3.5 w-3.5"></i>
            Weekly Schedule
          </div>
          <h1 class="text-[26px] font-semibold tracking-tight text-text-primary sm:text-[30px]">Schedule</h1>
          <p class="mt-1 max-w-2xl text-[15px] text-text-secondary">Plan your week, or let AI build one from your live greenhouse data — you'll get a reminder the moment each task is due.</p>
        </div>
        <div class="flex shrink-0 items-center gap-2.5">
          <button type="button" id="addTaskBtn" class="flex items-center gap-2 rounded-xl border border-border/70 bg-white px-4 py-2.5 text-sm font-medium text-text-primary shadow-soft transition-all hover:bg-neutral-50 active:scale-95">
            <i data-lucide="plus" class="h-4 w-4"></i> Add Task
          </button>
          <button type="button" id="suggestWeekBtn" class="ai-suggest-btn flex items-center gap-2 rounded-xl px-4 py-2.5 text-sm font-semibold text-white shadow-glow transition-all active:scale-95">
            <i data-lucide="sparkles" class="h-4 w-4"></i>
            <span id="suggestWeekLabel">Suggest My Week</span>
          </button>
        </div>
      </div>

      <!-- Week navigator -->
      <div class="flex items-center justify-between rounded-2xl border border-border/60 bg-white p-3 shadow-soft">
        <button type="button" id="weekPrevBtn" aria-label="Previous week" class="flex h-10 w-10 items-center justify-center rounded-xl text-text-secondary transition-colors hover:bg-neutral-50 hover:text-text-primary">
          <i data-lucide="chevron-left" class="h-4 w-4"></i>
        </button>
        <div class="flex items-center gap-3">
          <span id="weekRangeLabel" class="text-sm font-semibold text-text-primary">—</span>
          <button type="button" id="weekTodayBtn" class="rounded-lg border border-border/70 px-2.5 py-1 text-xs font-medium text-text-secondary transition-colors hover:bg-neutral-50 hover:text-text-primary">Today</button>
        </div>
        <button type="button" id="weekNextBtn" aria-label="Next week" class="flex h-10 w-10 items-center justify-center rounded-xl text-text-secondary transition-colors hover:bg-neutral-50 hover:text-text-primary">
          <i data-lucide="chevron-right" class="h-4 w-4"></i>
        </button>
      </div>

      <!-- Calendar -->
      <div class="cal-frame">
        <div class="cal-header-row" id="calHeaderRow"></div>
        <div class="cal-scroll" id="calScroll">
          <div class="cal-body">
            <div class="cal-gutter" id="calGutter"></div>
            <div class="cal-days" id="calDays"></div>
          </div>
        </div>
      </div>

    </div>
  </main>
</div>

<!-- Add Task modal -->
<div id="taskModalBackdrop" class="task-modal-backdrop fixed inset-0 z-50 hidden items-center justify-center bg-black/40 backdrop-blur-sm p-4">
  <div class="w-full max-w-md rounded-2xl bg-white p-6 shadow-soft-lg sm:p-7">
    <div class="mb-5 flex items-center justify-between">
      <h2 class="text-lg font-semibold text-text-primary">New Task</h2>
      <button type="button" id="taskModalCloseBtn" aria-label="Close" class="flex h-8 w-8 items-center justify-center rounded-xl text-text-secondary hover:bg-neutral-100">
        <i data-lucide="x" class="h-4 w-4"></i>
      </button>
    </div>

    <form id="taskForm" class="flex flex-col gap-4">
      <div>
        <label class="mb-1.5 block text-xs font-medium text-text-secondary">Title</label>
        <input type="text" id="taskTitle" required maxlength="150" placeholder="e.g. Check soil moisture" class="h-11 w-full rounded-xl border border-border/70 bg-neutral-50/70 px-3.5 text-sm text-text-primary placeholder:text-text-secondary focus:outline-none focus:ring-2 focus:ring-primary/25 focus:border-primary/40 focus:bg-white">
      </div>

      <div>
        <label class="mb-1.5 block text-xs font-medium text-text-secondary">Category</label>
        <div id="categoryPicker" class="flex flex-wrap gap-2"></div>
        <input type="hidden" id="taskCategory" value="Other">
      </div>

      <div class="grid grid-cols-2 gap-3">
        <div>
          <label class="mb-1.5 block text-xs font-medium text-text-secondary">Date</label>
          <input type="date" id="taskDate" required class="h-11 w-full rounded-xl border border-border/70 bg-neutral-50/70 px-3.5 text-sm text-text-primary focus:outline-none focus:ring-2 focus:ring-primary/25 focus:border-primary/40 focus:bg-white">
        </div>
        <div>
          <label class="mb-1.5 block text-xs font-medium text-text-secondary">Time</label>
          <input type="time" id="taskTime" required class="h-11 w-full rounded-xl border border-border/70 bg-neutral-50/70 px-3.5 text-sm text-text-primary focus:outline-none focus:ring-2 focus:ring-primary/25 focus:border-primary/40 focus:bg-white">
        </div>
      </div>

      <div>
        <label class="mb-1.5 block text-xs font-medium text-text-secondary">Notes <span class="text-text-secondary/60">(optional)</span></label>
        <textarea id="taskNotes" rows="2" maxlength="500" placeholder="Any details worth remembering" class="w-full resize-none rounded-xl border border-border/70 bg-neutral-50/70 px-3.5 py-2.5 text-sm text-text-primary placeholder:text-text-secondary focus:outline-none focus:ring-2 focus:ring-primary/25 focus:border-primary/40 focus:bg-white"></textarea>
      </div>

      <p id="taskFormError" class="hidden text-xs font-medium text-red-500"></p>

      <button type="submit" id="taskSaveBtn" class="mt-1 flex items-center justify-center gap-2 rounded-xl bg-green-gradient px-5 py-3 text-sm font-semibold text-white shadow-glow transition-all active:scale-95">
        <i data-lucide="check" class="h-4 w-4"></i> Save Task
      </button>
    </form>
  </div>
</div>

<!-- Task Detail modal — opened by clicking any task on the calendar -->
<div id="taskDetailBackdrop" class="task-modal-backdrop fixed inset-0 z-50 hidden items-center justify-center bg-black/40 backdrop-blur-sm p-4">
  <div class="w-full max-w-md rounded-2xl bg-white p-6 shadow-soft-lg sm:p-7">
    <div class="mb-4 flex items-start justify-between gap-3">
      <span id="detailCatBadge" class="cat-pick-btn is-selected cat-badge"></span>
      <button type="button" id="taskDetailCloseBtn" aria-label="Close" class="flex h-8 w-8 shrink-0 items-center justify-center rounded-xl text-text-secondary hover:bg-neutral-100">
        <i data-lucide="x" class="h-4 w-4"></i>
      </button>
    </div>

    <h2 id="detailTitle" class="text-xl font-semibold leading-snug text-text-primary"></h2>

    <div class="mt-3 flex flex-wrap items-center gap-x-4 gap-y-1.5 text-sm text-text-secondary">
      <span class="flex items-center gap-1.5"><i data-lucide="calendar" class="h-3.5 w-3.5"></i><span id="detailDate"></span></span>
      <span class="flex items-center gap-1.5"><i data-lucide="clock" class="h-3.5 w-3.5"></i><span id="detailTime"></span></span>
    </div>

    <div id="detailStatusRow" class="mt-3"></div>

    <div id="detailNotesBox" class="mt-4 hidden rounded-xl bg-neutral-50/70 p-3.5">
      <div class="mb-1 text-xs font-medium text-text-secondary">Notes</div>
      <p id="detailNotes" class="whitespace-pre-wrap text-sm text-text-primary"></p>
    </div>

    <div id="detailAiBox" class="mt-4 hidden rounded-xl border border-primary/15 bg-light/50 p-3.5">
      <div class="mb-1 flex items-center gap-1.5 text-xs font-semibold text-secondary"><i data-lucide="sparkles" class="h-3.5 w-3.5"></i> Why AI suggested this</div>
      <p id="detailAiReason" class="text-sm text-text-primary"></p>
    </div>

    <div class="mt-6 flex items-center gap-2.5">
      <button type="button" id="detailToggleBtn" class="flex flex-1 items-center justify-center gap-2 rounded-xl bg-green-gradient px-4 py-2.5 text-sm font-semibold text-white shadow-glow transition-all active:scale-95">
        <i data-lucide="check" class="h-4 w-4"></i> <span id="detailToggleLabel">Mark Complete</span>
      </button>
      <button type="button" id="detailDeleteBtn" aria-label="Delete task" class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl border border-border/70 text-red-500 transition-colors hover:bg-red-50">
        <i data-lucide="trash-2" class="h-4 w-4"></i>
      </button>
    </div>
  </div>
</div>

<script>
  window.GH_TASK_CATEGORIES = [
    { key: 'Watering',     icon: 'droplet',      color: 'sky' },
    { key: 'Ventilation',  icon: 'wind',         color: 'cyan' },
    { key: 'Inspection',   icon: 'search',       color: 'violet' },
    { key: 'Fertilizing',  icon: 'leaf',         color: 'amber' },
    { key: 'Harvest',      icon: 'shopping-basket', color: 'orange' },
    { key: 'Pruning',      icon: 'scissors',     color: 'pink' },
    { key: 'Cleaning',     icon: 'sparkles',     color: 'slate' },
    { key: 'Other',        icon: 'circle-dot',   color: 'neutral' },
  ];
</script>
<script src="js/schedule-page.js?v=1"></script>

</body>
</html>
