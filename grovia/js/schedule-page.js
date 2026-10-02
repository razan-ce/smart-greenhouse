(function () {
  const CATS = window.GH_TASK_CATEGORIES;
  const catByKey = Object.fromEntries(CATS.map((c) => [c.key, c]));

  const HOUR_H = 56;      // px per hour on the time grid
  const TASK_H = 40;      // px — fixed block height for a point-in-time task
  const DAY_MS = 24 * HOUR_H;

  const headerRow = document.getElementById('calHeaderRow');
  const gutter = document.getElementById('calGutter');
  const daysEl = document.getElementById('calDays');
  const scrollEl = document.getElementById('calScroll');
  const weekRangeLabel = document.getElementById('weekRangeLabel');
  const prevBtn = document.getElementById('weekPrevBtn');
  const nextBtn = document.getElementById('weekNextBtn');
  const todayBtn = document.getElementById('weekTodayBtn');
  const suggestBtn = document.getElementById('suggestWeekBtn');
  const suggestLabel = document.getElementById('suggestWeekLabel');
  const addTaskBtn = document.getElementById('addTaskBtn');

  const modalBackdrop = document.getElementById('taskModalBackdrop');
  const modalCloseBtn = document.getElementById('taskModalCloseBtn');
  const taskForm = document.getElementById('taskForm');
  const taskTitle = document.getElementById('taskTitle');
  const taskDate = document.getElementById('taskDate');
  const taskTime = document.getElementById('taskTime');
  const taskNotes = document.getElementById('taskNotes');
  const taskCategory = document.getElementById('taskCategory');
  const categoryPicker = document.getElementById('categoryPicker');
  const taskFormError = document.getElementById('taskFormError');
  const taskSaveBtn = document.getElementById('taskSaveBtn');

  const detailBackdrop = document.getElementById('taskDetailBackdrop');
  const detailCloseBtn = document.getElementById('taskDetailCloseBtn');
  const detailCatBadge = document.getElementById('detailCatBadge');
  const detailTitleEl = document.getElementById('detailTitle');
  const detailDateEl = document.getElementById('detailDate');
  const detailTimeEl = document.getElementById('detailTime');
  const detailStatusRow = document.getElementById('detailStatusRow');
  const detailNotesBox = document.getElementById('detailNotesBox');
  const detailNotesEl = document.getElementById('detailNotes');
  const detailAiBox = document.getElementById('detailAiBox');
  const detailAiReasonEl = document.getElementById('detailAiReason');
  const detailToggleBtn = document.getElementById('detailToggleBtn');
  const detailToggleLabel = document.getElementById('detailToggleLabel');
  const detailDeleteBtn = document.getElementById('detailDeleteBtn');
  let activeDetailTask = null;

  let agendaEl = null; // built lazily, shown on narrow screens via CSS

  let weekStart = mondayOf(new Date());
  let tasksByDate = {};
  let nowLineTimer = null;

  function mondayOf(d) {
    const date = new Date(d);
    const day = date.getDay();
    const diff = day === 0 ? -6 : 1 - day;
    date.setDate(date.getDate() + diff);
    date.setHours(0, 0, 0, 0);
    return date;
  }
  function ymd(d) {
    return d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0');
  }
  function addDays(d, n) { const c = new Date(d); c.setDate(c.getDate() + n); return c; }
  function escapeHtml(s) { const d = document.createElement('div'); d.textContent = s; return d.innerHTML; }
  function formatTime12(hhmmss) {
    const [h, m] = hhmmss.split(':').map(Number);
    const period = h >= 12 ? 'PM' : 'AM';
    const h12 = h % 12 === 0 ? 12 : h % 12;
    return h12 + ':' + String(m).padStart(2, '0') + ' ' + period;
  }
  function timeToTop(hhmmss) {
    const [h, m] = hhmmss.split(':').map(Number);
    return (h + m / 60) * HOUR_H;
  }

  // ---- Category picker ----
  function buildCategoryPicker() {
    categoryPicker.innerHTML = CATS.map((c) => `
      <button type="button" class="cat-pick-btn" data-key="${c.key}" data-color="${c.color}">
        <i data-lucide="${c.icon}"></i> ${c.key}
      </button>
    `).join('');
    if (window.lucide) window.lucide.createIcons();
    selectCategory('Other');
  }
  function selectCategory(key) {
    taskCategory.value = key;
    categoryPicker.querySelectorAll('.cat-pick-btn').forEach((btn) => btn.classList.toggle('is-selected', btn.dataset.key === key));
  }
  categoryPicker?.addEventListener('click', (e) => {
    const btn = e.target.closest('.cat-pick-btn');
    if (btn) selectCategory(btn.dataset.key);
  });

  // ---- Static scaffolding (built once; only the header's "today" cell and task blocks change per week) ----
  function buildScaffold() {
    // Header row
    let headerHtml = '<div class="cal-header-cell" style="border-left:none;"></div>';
    for (let i = 0; i < 7; i++) headerHtml += `<div class="cal-header-cell" data-col="${i}"><div class="cal-header-day"></div><div class="cal-header-num"></div></div>`;
    headerRow.innerHTML = headerHtml;

    // Gutter hour labels
    let gutterHtml = '';
    for (let h = 0; h < 24; h++) {
      const label = h === 0 ? '12 AM' : h < 12 ? h + ' AM' : h === 12 ? '12 PM' : (h - 12) + ' PM';
      gutterHtml += `<div class="cal-hour-label" style="top:${h * HOUR_H}px">${label}</div>`;
    }
    gutter.style.height = DAY_MS + 'px';
    gutter.innerHTML = gutterHtml;

    // Day columns with hour/half-hour gridlines
    let linesHtml = '';
    for (let h = 0; h < 24; h++) {
      linesHtml += `<div class="cal-hour-line" style="top:${h * HOUR_H}px"></div>`;
      linesHtml += `<div class="cal-hour-line is-half" style="top:${h * HOUR_H + HOUR_H / 2}px"></div>`;
    }
    daysEl.style.height = DAY_MS + 'px';
    daysEl.innerHTML = '';
    for (let i = 0; i < 7; i++) {
      const col = document.createElement('div');
      col.className = 'cal-day-col is-clickable';
      col.dataset.col = i;
      col.innerHTML = linesHtml;
      col.addEventListener('click', (e) => {
        if (e.target.closest('.task-chip')) return;
        const rect = col.getBoundingClientRect();
        const totalMinutes = Math.round((e.clientY - rect.top) / HOUR_H * 60 / 30) * 30;
        const h = Math.floor(totalMinutes / 60);
        const m = totalMinutes % 60;
        const date = addDays(weekStart, i);
        openModal(ymd(date), String(h).padStart(2, '0') + ':' + String(m).padStart(2, '0'));
      });
      daysEl.appendChild(col);
    }

    // Agenda fallback (mobile) — built once, lives after the grid frame
    if (!agendaEl) {
      agendaEl = document.createElement('div');
      agendaEl.className = 'cal-agenda';
      document.querySelector('.cal-frame').insertAdjacentElement('afterend', agendaEl);
    }
  }

  function layoutOverlaps(items) {
    // items: [{top, ...}], mutates in place adding col/totalCols so
    // same-time tasks sit side-by-side instead of stacking on top of
    // each other. Simple sweep — good enough for a handful of tasks/day.
    const sorted = [...items].sort((a, b) => a.top - b.top);
    let cluster = [];
    let clusterEnd = -Infinity;
    const clusters = [];
    sorted.forEach((t) => {
      if (t.top >= clusterEnd) {
        if (cluster.length) clusters.push(cluster);
        cluster = [];
      }
      cluster.push(t);
      clusterEnd = Math.max(clusterEnd, t.top + TASK_H);
    });
    if (cluster.length) clusters.push(cluster);
    clusters.forEach((c) => c.forEach((t, i) => { t.col = i; t.totalCols = c.length; }));
  }

  function render() {
    const weekEnd = addDays(weekStart, 6);
    const rangeFmt = { month: 'short', day: 'numeric' };
    weekRangeLabel.textContent = weekStart.toLocaleDateString(undefined, rangeFmt) + ' – '
      + weekEnd.toLocaleDateString(undefined, { ...rangeFmt, year: 'numeric' });

    const todayKey = ymd(new Date());

    // Header cells
    headerRow.querySelectorAll('.cal-header-cell[data-col]').forEach((cell) => {
      const day = addDays(weekStart, +cell.dataset.col);
      const key = ymd(day);
      cell.classList.toggle('is-today', key === todayKey);
      cell.querySelector('.cal-header-day').textContent = day.toLocaleDateString(undefined, { weekday: 'short' });
      cell.querySelector('.cal-header-num').textContent = day.getDate();
    });

    // Day columns: today highlight, now-line, task blocks
    daysEl.querySelectorAll('.cal-day-col').forEach((col) => {
      col.querySelectorAll('.task-chip, .cal-now-line').forEach((el) => el.remove());

      const i = +col.dataset.col;
      const day = addDays(weekStart, i);
      const key = ymd(day);
      col.classList.toggle('is-today', key === todayKey);

      if (key === todayKey) {
        const now = new Date();
        const line = document.createElement('div');
        line.className = 'cal-now-line';
        line.id = 'calNowLine';
        line.style.top = ((now.getHours() + now.getMinutes() / 60) * HOUR_H) + 'px';
        col.appendChild(line);
      }

      const dayTasks = (tasksByDate[key] || []).map((t) => ({ task: t, top: timeToTop(t.scheduled_time) }));
      layoutOverlaps(dayTasks);
      dayTasks.forEach((item) => col.appendChild(renderBlock(item)));
    });

    renderAgenda(todayKey);
    if (window.lucide) window.lucide.createIcons();
  }

  function renderBlock({ task: t, top, col, totalCols }) {
    const cat = catByKey[t.category] || catByKey.Other;
    const gap = 2;
    const widthPct = 100 / totalCols;
    const el = document.createElement('div');
    el.className = 'task-chip cat-' + cat.color + (t.status === 'Completed' ? ' is-completed' : '');
    el.style.top = top + 'px';
    el.style.height = TASK_H + 'px';
    el.style.left = `calc(${col * widthPct}% + ${col > 0 ? gap : 0}px)`;
    el.style.width = `calc(${widthPct}% - ${gap}px)`;
    el.dataset.taskId = t.task_id;
    el.title = t.ai_reason ? ('AI suggested: ' + t.ai_reason) : t.title;

    el.innerHTML = `
      ${t.is_ai_suggested == 1 ? '<span class="ai-badge"><i data-lucide="sparkles"></i></span>' : ''}
      <button type="button" class="task-chip-del" aria-label="Delete task"><i data-lucide="trash-2"></i></button>
      <div class="task-chip-row">
        <span class="task-chip-check" role="checkbox" aria-checked="${t.status === 'Completed'}"><i data-lucide="check"></i></span>
        <div class="task-chip-body">
          <div class="task-chip-time">${formatTime12(t.scheduled_time)}</div>
          <div class="task-chip-title">${escapeHtml(t.title)}</div>
        </div>
      </div>
    `;

    el.querySelector('.task-chip-check').addEventListener('click', (e) => {
      e.stopPropagation();
      updateTask(t.task_id, { status: t.status === 'Completed' ? 'Pending' : 'Completed' });
    });
    el.querySelector('.task-chip-del').addEventListener('click', (e) => {
      e.stopPropagation();
      if (confirm(`Delete "${t.title}"?`)) deleteTask(t.task_id);
    });
    el.addEventListener('click', () => openDetailModal(t));

    return el;
  }

  function renderAgenda(todayKey) {
    agendaEl.innerHTML = '';
    for (let i = 0; i < 7; i++) {
      const day = addDays(weekStart, i);
      const key = ymd(day);
      const dayTasks = (tasksByDate[key] || []).slice().sort((a, b) => a.scheduled_time.localeCompare(b.scheduled_time));

      const box = document.createElement('div');
      box.className = 'cal-agenda-day' + (key === todayKey ? ' is-today' : '');
      box.innerHTML = `<div class="cal-agenda-date">${day.toLocaleDateString(undefined, { weekday: 'long', month: 'short', day: 'numeric' })}</div>`;

      if (!dayTasks.length) {
        box.innerHTML += '<p class="cal-agenda-empty">Nothing scheduled</p>';
      } else {
        dayTasks.forEach((t) => {
          const cat = catByKey[t.category] || catByKey.Other;
          const row = document.createElement('div');
          row.className = 'cal-agenda-row cat-' + cat.color + (t.status === 'Completed' ? ' is-completed' : '');
          row.innerHTML = `
            <span class="cal-agenda-time">${formatTime12(t.scheduled_time)}</span>
            <span class="cal-agenda-title">${escapeHtml(t.title)}</span>
            <button type="button" class="task-chip-del" style="opacity:1;" aria-label="Delete"><i data-lucide="trash-2"></i></button>
          `;
          row.addEventListener('click', (e) => {
            if (e.target.closest('.task-chip-del')) return;
            openDetailModal(t);
          });
          row.querySelector('.task-chip-del').addEventListener('click', (e) => {
            e.stopPropagation();
            if (confirm(`Delete "${t.title}"?`)) deleteTask(t.task_id);
          });
          box.appendChild(row);
        });
      }
      agendaEl.appendChild(box);
    }
  }

  function tickNowLine() {
    const line = document.getElementById('calNowLine');
    if (!line) return;
    const now = new Date();
    line.style.top = ((now.getHours() + now.getMinutes() / 60) * HOUR_H) + 'px';
  }

  // ---- Data ----
  // A request token guards against out-of-order responses: saving a task
  // fires a reload, and if the user does something else quickly enough
  // (another save, a week-nav click) two loadWeek() calls can be in
  // flight at once — without this, whichever fetch happens to resolve
  // *last* wins even if it was fired *first*, rendering stale data over
  // fresh data (duplicate/wrong chips). Only the response matching the
  // most recently fired request is allowed to render.
  let loadToken = 0;
  function loadWeek() {
    const token = ++loadToken;
    const start = ymd(weekStart);
    const end = ymd(addDays(weekStart, 6));
    fetch(`api/schedule-list.php?start=${start}&end=${end}`)
      .then((r) => r.json())
      .then((data) => {
        if (token !== loadToken) return;
        tasksByDate = {};
        if (data.ok) {
          data.tasks.forEach((t) => { (tasksByDate[t.scheduled_date] = tasksByDate[t.scheduled_date] || []).push(t); });
        }
        render();
      })
      .catch(() => { if (token === loadToken) render(); });
  }
  function updateTask(taskId, patch) {
    fetch('api/schedule-update.php', {
      method: 'POST', headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ task_id: taskId, ...patch }),
    }).then((r) => r.json()).then((data) => { if (data.ok) loadWeek(); });
  }
  function deleteTask(taskId) { updateTask(taskId, { delete: true }); }

  // ---- Week navigation ----
  prevBtn.addEventListener('click', () => { weekStart = addDays(weekStart, -7); loadWeek(); });
  nextBtn.addEventListener('click', () => { weekStart = addDays(weekStart, 7); loadWeek(); });
  todayBtn.addEventListener('click', () => { weekStart = mondayOf(new Date()); loadWeek(); scrollToNow(); });

  function scrollToNow() {
    const now = new Date();
    scrollEl.scrollTop = Math.max(0, (now.getHours() - 2) * HOUR_H);
  }

  // ---- Add Task modal ----
  function openModal(prefillDate, prefillTime) {
    taskForm.reset();
    buildCategoryPicker();
    taskDate.value = prefillDate || ymd(new Date());
    taskTime.value = prefillTime || '09:00';
    taskFormError.classList.add('hidden');
    modalBackdrop.classList.add('is-open');
    modalBackdrop.classList.remove('hidden');
    setTimeout(() => taskTitle.focus(), 50);
  }
  function closeModal() {
    modalBackdrop.classList.remove('is-open');
    modalBackdrop.classList.add('hidden');
  }
  addTaskBtn.addEventListener('click', () => openModal());
  modalCloseBtn.addEventListener('click', closeModal);
  modalBackdrop.addEventListener('click', (e) => { if (e.target === modalBackdrop) closeModal(); });

  // ---- Task Detail modal — full, untruncated view of whatever was clicked ----
  function openDetailModal(t) {
    activeDetailTask = t;
    const cat = catByKey[t.category] || catByKey.Other;

    detailCatBadge.dataset.color = cat.color;
    detailCatBadge.innerHTML = `<i data-lucide="${cat.icon}"></i> ${escapeHtml(t.category)}`;
    detailTitleEl.textContent = t.title;

    const dateObj = new Date(t.scheduled_date + 'T00:00:00');
    detailDateEl.textContent = dateObj.toLocaleDateString(undefined, { weekday: 'long', month: 'long', day: 'numeric' });
    detailTimeEl.textContent = formatTime12(t.scheduled_time);

    detailStatusRow.innerHTML = `<span class="status-pill is-${t.status.toLowerCase()}">${t.status}</span>`;

    if (t.notes) {
      detailNotesEl.textContent = t.notes;
      detailNotesBox.classList.remove('hidden');
    } else {
      detailNotesBox.classList.add('hidden');
    }

    if (t.is_ai_suggested == 1 && t.ai_reason) {
      detailAiReasonEl.textContent = t.ai_reason;
      detailAiBox.classList.remove('hidden');
    } else {
      detailAiBox.classList.add('hidden');
    }

    detailToggleLabel.textContent = t.status === 'Completed' ? 'Mark as Pending' : 'Mark Complete';

    detailBackdrop.classList.add('is-open');
    detailBackdrop.classList.remove('hidden');
    if (window.lucide) window.lucide.createIcons();
  }
  function closeDetailModal() {
    detailBackdrop.classList.remove('is-open');
    detailBackdrop.classList.add('hidden');
    activeDetailTask = null;
  }
  detailCloseBtn.addEventListener('click', closeDetailModal);
  detailBackdrop.addEventListener('click', (e) => { if (e.target === detailBackdrop) closeDetailModal(); });
  detailToggleBtn.addEventListener('click', () => {
    if (!activeDetailTask) return;
    updateTask(activeDetailTask.task_id, { status: activeDetailTask.status === 'Completed' ? 'Pending' : 'Completed' });
    closeDetailModal();
  });
  detailDeleteBtn.addEventListener('click', () => {
    if (!activeDetailTask) return;
    if (confirm(`Delete "${activeDetailTask.title}"?`)) {
      deleteTask(activeDetailTask.task_id);
      closeDetailModal();
    }
  });

  taskForm.addEventListener('submit', (e) => {
    e.preventDefault();
    taskFormError.classList.add('hidden');
    taskSaveBtn.disabled = true;

    fetch('api/schedule-add.php', {
      method: 'POST', headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        title: taskTitle.value.trim(), category: taskCategory.value,
        scheduled_date: taskDate.value, scheduled_time: taskTime.value, notes: taskNotes.value.trim(),
      }),
    })
      .then((r) => r.json())
      .then((data) => {
        taskSaveBtn.disabled = false;
        if (data.ok) {
          closeModal();
          weekStart = mondayOf(new Date(taskDate.value + 'T00:00:00'));
          loadWeek();
        } else {
          taskFormError.textContent = data.error || 'Could not save the task.';
          taskFormError.classList.remove('hidden');
        }
      })
      .catch(() => {
        taskSaveBtn.disabled = false;
        taskFormError.textContent = 'Network error, try again.';
        taskFormError.classList.remove('hidden');
      });
  });

  // ---- AI Suggest My Week ----
  suggestBtn.addEventListener('click', () => {
    suggestBtn.disabled = true;
    suggestLabel.textContent = 'Thinking…';
    suggestBtn.querySelector('svg')?.classList.add('spin');

    fetch('api/schedule-suggest.php', { method: 'POST' })
      .then((r) => r.json())
      .then((data) => {
        suggestBtn.disabled = false;
        suggestLabel.textContent = 'Suggest My Week';
        suggestBtn.querySelector('svg')?.classList.remove('spin');
        if (data.ok) { weekStart = mondayOf(new Date()); loadWeek(); }
        else alert(data.error || 'Could not generate a schedule right now.');
      })
      .catch(() => {
        suggestBtn.disabled = false;
        suggestLabel.textContent = 'Suggest My Week';
        suggestBtn.querySelector('svg')?.classList.remove('spin');
        alert('Network error, try again.');
      });
  });

  buildScaffold();
  loadWeek();
  scrollToNow();
  nowLineTimer = setInterval(tickNowLine, 60000);
})();
