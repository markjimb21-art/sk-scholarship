/** Reports: the "Generate" modal adapts to the report chosen (data-report / data-title on the button that opened it). */
(function () {
  'use strict';

  document.getElementById('genModal').addEventListener('show.bs.modal', function (e) {
    var type = e.relatedTarget.dataset.report;
    document.getElementById('genReportType').value = type;
    document.getElementById('genTitle').textContent = e.relatedTarget.dataset.title;
    document.getElementById('statusRow').classList.toggle('d-none', type !== 'applicant_list');
    var needsProgram = type === 'summary' || type === 'profile_academic';
    document.getElementById('programRequired').classList.toggle('d-none', !needsProgram);
    document.getElementById('genProgram').required = needsProgram;
  });
})();
