/**
 * Application returned with flagged items: highlight the flagged fields and lock everything else (the server keeps the
 * unflagged values regardless — ApplicationFormController::lockUnflagged). Data: #returnFlagData, written with js_json():
 * { flags: ["personal.contact_number", …], fields: { personal: [names…], education: […], family: […] } }.
 *
 * Loaded last and run straight away, not on DOMContentLoaded, so the fields are locked before app.js's DOMContentLoaded
 * code runs (that order matters for the scholarship "details" boxes, which app.js enables/disables from their checkbox).
 */
(function () {
  'use strict';

  var dataEl = document.getElementById('returnFlagData');
  if (!dataEl) return;
  var data = JSON.parse(dataEl.textContent), flags = data.flags, fields = data.fields;
  Object.keys(fields).forEach(function (step) {
    var form = document.querySelector('form[data-step="' + step + '"]');
    if (!form) return;
    fields[step].forEach(function (name) {
      form.querySelectorAll('[name="' + name + '"]').forEach(function (el) {
        if (flags.indexOf(step + '.' + name) !== -1) {
          el.classList.add('is-invalid');
          var note = document.createElement('div'); note.className = 'invalid-feedback d-block'; note.textContent = 'Flagged by the SK office — please correct.';
          (el.closest('.col-12, [class*="col-"]') || el.parentNode).appendChild(note);
        } else {
          el.disabled = true;
          el.title = 'Accepted as submitted';
        }
      });
    });
  });
})();
