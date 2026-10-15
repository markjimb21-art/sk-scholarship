/**
 * Calendar page (FullCalendar): month/week grid plus the "Overdue / Today / This week / Later" list beside it; clicking
 * an event in either place highlights it in both. Server values come from data-* attributes on #calendar:
 * data-today, data-week-end (Y-m-d, server time) and data-feed-url (admin/calendar-feed).
 */
(function () {
  'use strict';

  document.addEventListener('DOMContentLoaded', function () {
    if (!window.FullCalendar) { document.getElementById('calList').textContent = 'The calendar library did not load.'; return; }
    var calEl = document.getElementById('calendar');
    var TODAY = calEl.dataset.today, WEEK_END = calEl.dataset.weekEnd;
    var select = document.getElementById('calProgram'), listEl = document.getElementById('calList');
    var selectedId = null;
    var mounted = {};   // event id -> Set of rendered elements
    // Month height in rem (36rem = 648px at A), so it grows with A+ / A++ and the grid never needs its own scrollbar.
    function monthHeight() { return Math.round(36 * parseFloat(getComputedStyle(document.documentElement).fontSize)); }
    function viewHeight(type) { return type === 'dayGridMonth' ? monthHeight() : 'auto'; }

    var calendar = new FullCalendar.Calendar(calEl, {
      initialView: 'dayGridMonth',
      headerToolbar: { left: 'prev,next today', center: 'title', right: 'dayGridMonth,dayGridWeek' },
      buttonText: { today: 'Today', month: 'Month', week: 'Week' },
      eventDisplay: 'block',
      displayEventEnd: true,
      eventTimeFormat: { hour: 'numeric', minute: '2-digit', meridiem: 'short' },   // 3:47pm
      defaultRangeSeparator: '-',                                                    // 3:47pm-6:47pm
      // A deadline has no end, so FullCalendar gives it 1 hour; at 11:06pm that would spill into the next day.
      // Only an event that runs past 9am the next day is drawn on that day too.
      nextDayThreshold: '09:00:00',
      // Month: fixed overall height; a day shows as many events as fit its row and puts the rest behind "+N more", so every week row stays the same height.
      // Week is a single row, so it keeps all its events and grows to fit them (see datesSet).
      height: monthHeight(),
      dayMaxEvents: true,
      views: { dayGridWeek: { dayMaxEvents: false } },
      datesSet: function (info) {
        var h = viewHeight(info.view.type);
        if (calendar.getOption('height') !== h) calendar.setOption('height', h);
      },
      events: { url: calEl.dataset.feedUrl, extraParams: function () { return { program_id: select.value }; } },
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

    // A / A+ / A++ (ui.js) changes the root font size without a window resize: re-measure the grid.
    new MutationObserver(function () {
      calendar.setOption('height', viewHeight(calendar.view.type));
      calendar.updateSize();
    }).observe(document.documentElement, { attributes: true, attributeFilter: ['data-text-size'] });

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
})();
