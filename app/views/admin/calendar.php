<?php
// Admin calendar: interviews + claim/renewal windows. Expects $programs, $programId, $today, $weekEnd.
// Events come from admin/calendar-feed; the list under the calendar is built from the same events.
?>
<style>
  /* Today's cell: pale blue with a yellow bar on top (yellow never sits behind text). */
  .fc .fc-daygrid-day.fc-day-today { background-color: var(--brand-blue-tint) !important; box-shadow: inset 0 4px 0 var(--brand-yellow); }
  .fc .fc-daygrid-day.fc-day-today .fc-daygrid-day-number { font-weight: 700; }
  .fc .fc-daygrid-day.cal-day-flash { box-shadow: inset 0 0 0 3px var(--brand-blue); }
  /* Bootstrap styles every <a>; FullCalendar's day numbers and weekday headers are links. */
  .fc a.fc-daygrid-day-number, .fc a.fc-col-header-cell-cushion { color: inherit; text-decoration: none; }
  /* Every event is ONE line (full text in the hover tooltip), so a busy week never makes its row taller than the others. */
  .fc .fc-daygrid-block-event .fc-event-main-frame { flex-wrap: nowrap; min-width: 0; }
  .fc .fc-daygrid-event .fc-event-time { white-space: nowrap; flex: none; }
  .fc .fc-daygrid-event .fc-event-title { white-space: nowrap; overflow: hidden; text-overflow: ellipsis; min-width: 0; flex: 1 1 auto; }
  .fc .fc-daygrid-event { font-size: .875rem; line-height: 1.3; }
  .fc .fc-daygrid-more-link { font-size: .875rem; font-weight: 700; }
  .fc .fc-button { min-height: var(--target-min); font-weight: 700; }
  .fc-ev-cancelled .fc-event-title { text-decoration: line-through; }
  .fc-ev-planned { border-style: dashed !important; border-width: 2px !important; }
  .fc-ev-overdue { font-weight: 700; }
  .fc-event.highlighted { box-shadow: 0 0 0 3px var(--brand-blue-dark); z-index: 5; }
  .cal-pulse { display: inline-block; width: .55rem; height: .55rem; border-radius: 50%; background: #fff; margin-right: .3rem;
               animation: cal-pulse 1.2s ease-in-out infinite; vertical-align: middle; }
  .cal-list .cal-dot { display: inline-block; width: .75rem; height: .75rem; border-radius: 50%; flex: none; margin-top: .3rem; }
  .cal-list .cal-dot.cal-pulse { background: #fd7e14; margin-right: 0; }
  .cal-list .cal-dot.outlined { background: #fff !important; border: 2px dashed currentColor; }
  .cal-list li { cursor: pointer; transition: background-color .3s; }
  .cal-list li.highlighted { background: var(--info-bg); }
  .cal-flash { animation: cal-flash 2s ease-out; }
  @keyframes cal-pulse { 0%, 100% { opacity: 1; transform: scale(1); } 50% { opacity: .35; transform: scale(.7); } }
  @keyframes cal-flash { 0% { background: var(--info-bg); } 100% { background: inherit; } }
  @media (prefers-reduced-motion: reduce) { .cal-pulse, .cal-flash { animation: none; } }
</style>

<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
  <div>
    <h4 class="fw-bold mb-0">Calendar</h4>
    <p class="text-muted small mb-0">Interview schedules and claim/renewal deadlines. Click an event or a row below to find it in the other.</p>
  </div>
  <form method="GET" action="<?= url('admin/calendar') ?>">
    <div><label class="form-label" for="calProgram">Program</label><select name="program_id" id="calProgram" class="form-select form-select-sm">
      <option value="">All programs</option>
      <?php foreach ($programs as $p): ?>
        <option value="<?= (int)$p['id'] ?>" <?= $programId === (int)$p['id'] ? 'selected' : '' ?>><?= e($p['program_name']) ?> (<?= e($p['academic_year']) ?>)</option>
      <?php endforeach; ?>
    </select></div>
  </form>
</div>

<div class="d-flex gap-3 flex-wrap small mb-2">
  <?php foreach (CalendarController::LEGEND as [$label, $key, $outlined]): $c = CalendarController::COLORS[$key]; ?>
    <span class="d-inline-flex align-items-center gap-1">
      <span style="display:inline-block;width:.9rem;height:.9rem;border-radius:.2rem;<?= $outlined ? 'background:#fff;border:2px dashed ' . e($c) : 'background:' . e($c) ?>"></span>
      <?= e($label) ?>
    </span>
  <?php endforeach; ?>
</div>

<div class="card border-0 shadow-sm mb-3">
  <div class="card-body"><div id="calendar"></div></div>
</div>

<div class="card border-0 shadow-sm">
  <div class="card-header bg-white d-flex justify-content-between align-items-center">
    <h6 class="mb-0">What’s coming up <span class="small text-muted fw-normal">(in the calendar’s current view)</span></h6>
    <span class="small text-muted" id="calCount"></span>
  </div>
  <div id="calList" class="cal-list"><p class="text-muted small p-3 mb-0">Loading…</p></div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
  if (!window.FullCalendar) { document.getElementById('calList').textContent = 'The calendar library did not load.'; return; }
  var TODAY = <?= js_json($today) ?>, WEEK_END = <?= js_json($weekEnd) ?>;
  var select = document.getElementById('calProgram'), listEl = document.getElementById('calList');
  var selectedId = null;
  var mounted = {};   // event id -> Set of rendered elements
  var MONTH_HEIGHT = 640;

  var calendar = new FullCalendar.Calendar(document.getElementById('calendar'), {
    initialView: 'dayGridMonth',
    headerToolbar: { left: 'prev,next today', center: 'title', right: 'dayGridMonth,dayGridWeek' },
    buttonText: { today: 'Today', month: 'Month', week: 'Week' },
    eventDisplay: 'block',
    displayEventEnd: true,
    // Month: fixed overall height; a day shows as many events as fit its row and puts the rest behind "+N more", so every week row stays the same height.
    // Week is a single row, so it keeps all its events and grows to fit them (see datesSet).
    height: MONTH_HEIGHT,
    dayMaxEvents: true,
    views: { dayGridWeek: { dayMaxEvents: false } },
    datesSet: function (info) {
      var h = info.view.type === 'dayGridMonth' ? MONTH_HEIGHT : 'auto';
      if (calendar.getOption('height') !== h) calendar.setOption('height', h);
    },
    events: { url: <?= js_json(url('admin/calendar-feed')) ?>, extraParams: function () { return { program_id: select.value }; } },
    eventDidMount: function (info) {
      var id = info.event.id;
      (mounted[id] = mounted[id] || new Set()).add(info.el);
      info.el.title = info.event.title + '\n' + info.event.extendedProps.when + '\n' + info.event.extendedProps.state;
      if (info.event.extendedProps.dueSoon) {
        var dot = document.createElement('span'); dot.className = 'cal-pulse'; dot.setAttribute('aria-hidden', 'true');
        var title = info.el.querySelector('.fc-event-title') || info.el; title.prepend(dot);
      }
      if (id === selectedId) info.el.classList.add('highlighted');
    },
    eventWillUnmount: function (info) { if (mounted[info.event.id]) mounted[info.event.id].delete(info.el); },
    eventsSet: renderList,
    eventClick: function (info) { info.jsEvent.preventDefault(); select_(info.event.id, 'calendar'); }
  });
  calendar.render();

  select.addEventListener('change', function () {
    var u = new URL(window.location.href);
    if (select.value) u.searchParams.set('program_id', select.value); else u.searchParams.delete('program_id');
    history.replaceState(null, '', u);
    calendar.refetchEvents();
  });

  function group(ev) {
    var p = ev.extendedProps;
    if (p.overdue) return 'Overdue';
    if (p.date < TODAY) return null;           // past and not overdue: not listed
    if (p.date === TODAY) return 'Today';
    return p.date <= WEEK_END ? 'This week' : 'Later';
  }

  function renderList(events) {
    var groups = { 'Overdue': [], 'Today': [], 'This week': [], 'Later': [] };
    events.forEach(function (ev) { var g = group(ev); if (g) groups[g].push(ev); });
    listEl.innerHTML = '';
    var total = 0;
    Object.keys(groups).forEach(function (name) {
      var evs = groups[name].sort(function (a, b) { return a.start - b.start; });
      if (!evs.length) return;
      total += evs.length;
      var h = document.createElement('div');
      h.className = 'px-3 pt-3 pb-1 small text-uppercase fw-semibold ' + (name === 'Overdue' ? 'text-danger' : 'text-muted');
      h.textContent = name;
      var ul = document.createElement('ul'); ul.className = 'list-group list-group-flush';
      evs.forEach(function (ev) { ul.appendChild(row(ev)); });
      listEl.appendChild(h); listEl.appendChild(ul);
    });
    document.getElementById('calCount').textContent = total + ' event(s)';
    if (!total) listEl.innerHTML = '<p class="text-muted small p-3 mb-0">Nothing overdue or coming up in this view.</p>';
  }

  function row(ev) {
    var p = ev.extendedProps;
    var li = document.createElement('li');
    li.className = 'list-group-item d-flex gap-2 align-items-start' + (ev.id === selectedId ? ' highlighted' : '');
    li.dataset.eventId = ev.id; li.tabIndex = 0;
    var dot = document.createElement('span');
    dot.className = 'cal-dot' + (p.dueSoon ? ' cal-pulse' : '') + (p.outlined ? ' outlined' : '');
    dot.style.background = p.color; dot.style.color = p.color;
    var body = document.createElement('div'); body.className = 'flex-grow-1';
    var t = document.createElement('div'); t.className = 'fw-semibold' + (p.overdue ? ' text-danger' : ''); t.textContent = ev.title;
    var w = document.createElement('div'); w.className = 'small text-muted'; w.textContent = p.when;
    var s = document.createElement('span'); s.className = 'badge'; s.style.background = p.outlined ? '#fff' : p.color;
    s.style.color = p.outlined ? p.color : (p.color === '#ffc107' ? '#212529' : '#fff'); if (p.outlined) s.style.border = '1px dashed ' + p.color;
    s.textContent = p.state;
    body.appendChild(t); body.appendChild(w); body.appendChild(s);
    var a = document.createElement('a'); a.href = p.link; a.className = 'btn btn-sm btn-outline-secondary'; a.textContent = 'Open';
    a.addEventListener('click', function (e) { e.stopPropagation(); });
    li.appendChild(dot); li.appendChild(body); li.appendChild(a);
    li.addEventListener('click', function () { select_(ev.id, 'list'); });
    li.addEventListener('keydown', function (e) { if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); select_(ev.id, 'list'); } });
    return li;
  }

  function flash(el) { el.classList.remove('cal-flash'); void el.offsetWidth; el.classList.add('cal-flash'); }

  // Highlight one event in BOTH places; from = where the click happened.
  function select_(id, from) {
    selectedId = id;
    document.querySelectorAll('.highlighted').forEach(function (el) { el.classList.remove('highlighted'); });
    document.querySelectorAll('.cal-day-flash').forEach(function (el) { el.classList.remove('cal-day-flash'); });
    var ev = calendar.getEventById(id);
    if (!ev) return;
    var li = listEl.querySelector('[data-event-id="' + CSS.escape(id) + '"]');
    if (li) { li.classList.add('highlighted'); flash(li); if (from === 'calendar') li.scrollIntoView({ behavior: 'smooth', block: 'center' }); }
    if (from === 'list') calendar.gotoDate(ev.start);
    setTimeout(function () {   // after gotoDate re-renders
      (mounted[id] || new Set()).forEach(function (el) { el.classList.add('highlighted'); });
      var cell = document.querySelector('.fc-daygrid-day[data-date="' + ev.extendedProps.date + '"]');
      if (cell) {
        cell.classList.add('cal-day-flash');
        if (from === 'list') cell.scrollIntoView({ behavior: 'smooth', block: 'center' });
      }
    }, 60);
  }
});
</script>
