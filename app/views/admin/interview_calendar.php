<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
  <div>
    <h4 class="fw-bold mb-0">Interview Calendar</h4>
    <p class="text-muted small mb-0">Day / Week / Month views · Click event to open schedule</p>
  </div>
  <div class="d-flex gap-2">
    <select id="programFilter" class="form-select form-select-sm">
      <option value="">All Programs</option>
      <?php foreach ($programs as $p): ?>
        <option value="<?= (int)$p['id'] ?>"><?= e($p['program_name']) ?></option>
      <?php endforeach; ?>
    </select>
    <a href="<?= url('admin/interview-scheduling') ?>" class="btn btn-outline-primary btn-sm">
      <i class="bi bi-list-ul"></i> List View
    </a>
  </div>
</div>

<!-- Legend -->
<div class="mb-3 d-flex gap-3 flex-wrap small">
  <span><span class="badge" style="background:#1d4ed8;">&nbsp;</span> Available</span>
  <span><span class="badge" style="background:#f59e0b;">&nbsp;</span> Full</span>
  <span><span class="badge" style="background:#16a34a;">&nbsp;</span> Completed</span>
  <span><span class="badge" style="background:#dc2626;">&nbsp;</span> Cancelled</span>
</div>

<div class="card border-0 shadow-sm">
  <div class="card-body">
    <div id="calendar"></div>
  </div>
</div>

<!-- SweetAlert2 for popup (Must load before inline script) -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
document.addEventListener('DOMContentLoaded', () => {
  const calEl = document.getElementById('calendar');
  const programFilter = document.getElementById('programFilter');

  const calendar = new FullCalendar.Calendar(calEl, {
    initialView: 'dayGridMonth',
    headerToolbar: {
      left:   'prev,next today',
      center: 'title',
      right:  'dayGridMonth,timeGridWeek,timeGridDay,listWeek'
    },
    height: 'auto',
    eventTimeFormat: { hour: 'numeric', minute: '2-digit', meridiem: 'short' },
    eventClick: (info) => {
      info.jsEvent.preventDefault();
      const props = info.event.extendedProps;
      const html = `
        <div class="text-start">
          <p class="mb-1"><strong>Venue:</strong> ${props.venue}</p>
          <p class="mb-1"><strong>Interviewer:</strong> ${props.interviewer}</p>
          <p class="mb-1"><strong>Status:</strong> ${props.status}</p>
          <p class="mb-1"><strong>Slots:</strong> ${props.assigned}/${props.max_slots}</p>
        </div>`;
      Swal.fire({
        title: info.event.title,
        html: html,
        showCancelButton: true,
        confirmButtonText: 'Open Schedule',
        cancelButtonText: 'Close'
      }).then(r => { if (r.isConfirmed && info.event.url) window.location.href = info.event.url; });
    },
    events: (info, success, failure) => {
      const params = new URLSearchParams({
        start: info.startStr.split('T')[0],
        end:   info.endStr.split('T')[0],
      });
      if (programFilter.value) params.set('program_id', programFilter.value);
      fetch('<?= url('admin/interview-calendar-feed') ?>&' + params)
        .then(r => r.json()).then(success).catch(failure);
    }
  });
  calendar.render();

  programFilter.addEventListener('change', () => calendar.refetchEvents());
});
</script>