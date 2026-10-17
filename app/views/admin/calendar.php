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
  /* The title gives up its space first; the time is cut only when even it doesn't fit (narrow cell at A++). Nothing leaves its day cell. */
  .fc .fc-daygrid-block-event .fc-event-main-frame { flex-wrap: nowrap; min-width: 0; overflow: hidden; }
  .fc .fc-daygrid-event .fc-event-time { white-space: nowrap; flex: 0 1 auto; min-width: 0; overflow: hidden; text-overflow: ellipsis; }
  .fc .fc-daygrid-event .fc-event-title { white-space: nowrap; overflow: hidden; text-overflow: ellipsis; min-width: 0; flex: 1 1 0; }
  /* Toolbar wraps instead of overflowing when the text is larger or the screen narrower. */
  .fc .fc-header-toolbar.fc-toolbar { flex-wrap: wrap; gap: .5rem; }
  .fc .fc-toolbar-title { font-size: 1.5rem; }
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
  <div class="card-body"><div id="calendar" data-today="<?= e($today) ?>" data-week-end="<?= e($weekEnd) ?>" data-feed-url="<?= e(url('admin/calendar-feed')) ?>"></div></div>
</div>

<div class="card border-0 shadow-sm">
  <div class="card-header bg-white d-flex justify-content-between align-items-center">
    <h6 class="mb-0">What’s coming up <span class="small text-muted fw-normal">(in the calendar’s current view)</span></h6>
    <span class="small text-muted" id="calCount"></span>
  </div>
  <div id="calList" class="cal-list"><p class="text-muted small p-3 mb-0">Loading…</p></div>
</div>

