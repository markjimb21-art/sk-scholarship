/**
 * Claim / renewal schedule page: the New / Edit window modal.
 *  - data-schedule-modal: empty for "New window", the window as JSON for Edit;
 *  - data-schedule-prefill: "Create window" for a semester that has none yet (program, year and semester filled in);
 *  - data-open-edit on the modal: the window to open straight away (?edit=<id>, e.g. from a notification link).
 */
(function () {
  'use strict';

  function openScheduleModal(s, prefill) {
    var edit = !!s, v = function (id, val) { document.getElementById(id).value = val == null ? '' : val; };
    v('schId', edit ? s.id : '');
    document.getElementById('schTitle').textContent = edit ? 'Edit window' : 'New window';
    // Program / A.Y. / semester can be chosen on a new window, and changed on one nobody has submitted under yet.
    var locked = edit && Number(s.submitted_count) > 0;
    document.getElementById('schFixed').classList.toggle('d-none', locked);
    ['schProgram', 'schYear', 'schSemester'].forEach(function (id) { document.getElementById(id).disabled = locked; });
    document.getElementById('schFixedText').classList.toggle('d-none', !locked);
    document.getElementById('schFixedName').textContent = locked ? s.program_name + ' — ' + s.academic_year + ' ' + s.semester : '';
    var p = edit ? s : (prefill || {});
    var year = document.getElementById('schYear');
    if (p.academic_year && !Array.from(year.options).some(function (o) { return o.value === p.academic_year; })) {
      year.add(new Option(p.academic_year, p.academic_year));   // a year not in the list (e.g. an older window)
    }
    v('schProgram', p.program_id || '');
    if (p.academic_year) v('schYear', p.academic_year);
    if (p.semester) v('schSemester', p.semester);
    v('schOpens', edit ? s.opens_at : '');
    v('schDeadline', edit ? s.submission_deadline : '');
    v('schPlanned', edit ? s.planned_date : '');
    v('schNotes', edit ? s.notes : '');
    v('schStatus', edit ? s.status : 'Open');
    document.getElementById('schStatusWrap').classList.toggle('d-none', !edit);
  }

  document.querySelectorAll('[data-schedule-modal]').forEach(function (btn) {
    btn.addEventListener('click', function () { openScheduleModal(btn.dataset.scheduleModal ? JSON.parse(btn.dataset.scheduleModal) : null); });
  });
  document.querySelectorAll('[data-schedule-prefill]').forEach(function (btn) {
    btn.addEventListener('click', function () { openScheduleModal(null, JSON.parse(btn.dataset.schedulePrefill)); });
  });

  var editId = Number(document.getElementById('scheduleModal').dataset.openEdit);
  if (editId) {
    document.addEventListener('DOMContentLoaded', function () {
      var b = document.querySelector('[data-edit-id="' + editId + '"]');
      if (b) b.click();
    });
  }
})();
