/**
 * Step V (Review & Submit): fills the review tables from what is currently in steps I–IV each time the step opens,
 * so it never shows stale data from when the page was loaded. Also refreshes the "Still missing" list using the same
 * rules as Application::completeness() (the server checks again on Submit).
 */
(function () {
  'use strict';

  const wizard = document.querySelector('[data-wizard]');
  const review = document.getElementById('applicationReview');
  if (!wizard || !review) return;
  const reviewStep = wizard.querySelector('.form-step[data-step="review"]');
  const PHONE = /^0[0-9]{10}$/;

  function field(name) { return wizard.querySelector(`form.form-step [name="${CSS.escape(name)}"]`); }
  function val(name) { const el = field(name); return el ? String(el.value).trim() : ''; }
  function checked(name) { const el = field(name); return !!(el && el.checked); }
  function selectedText(name) {
    const el = field(name);
    if (!el || el.tagName !== 'SELECT' || el.value === '') return '';
    return el.options[el.selectedIndex] ? el.options[el.selectedIndex].text.trim() : '';
  }
  function money(v) {
    const n = parseFloat(String(v).replace(/,/g, ''));
    return isNaN(n) ? '' : '₱' + n.toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
  }
  function measure(v, unit) {
    const n = parseFloat(v);
    return isNaN(n) ? '' : (Math.round(n * 10) / 10) + ' ' + unit;
  }
  function date(v) {
    if (!v) return '';
    const d = new Date(v + 'T00:00:00');
    return isNaN(d) ? v : d.toLocaleDateString('en-US', { month: 'short', day: '2-digit', year: 'numeric' });
  }

  const computed = {
    full_name: () => ['first_name', 'middle_name', 'last_name', 'suffix'].map(val).filter(Boolean).join(' '),
    age: () => { const el = wizard.querySelector('[data-target="age"]'); return el ? el.value : ''; },
    address: () => {
      const purok = selectedText('purok_id');
      const parts = [val('address_unit'), purok].filter(Boolean);
      return purok ? parts.concat(['BARANGAY ESTEFANIA', 'BACOLOD CITY']).join(' / ') : parts.join(' / ');
    },
    year_level: () => (val('year_level') === 'Other' && val('year_level_other')) ? val('year_level_other') : val('year_level'),
    total_income: () => { const el = wizard.querySelector('[data-target="total_family_income"]'); return el ? money(el.value) : ''; },
  };

  function valueFor(key) {
    if (key.charAt(0) === '@') return (computed[key.slice(1)] || (() => ''))();
    const [name, format] = key.split('|');
    const el = field(name);
    if (!el) return '';
    switch (format) {
      case 'date': return date(val(name));
      case 'cm': return measure(val(name), 'cm');
      case 'kg': return measure(val(name), 'kg');
      case 'money': return money(val(name));
      case 'yesno': {
        if (!checked(name)) return 'No';
        const details = val(name + '_details');
        return details ? 'Yes — ' + details : 'Yes';
      }
      default: return el.tagName === 'SELECT' ? selectedText(name) : val(name);
    }
  }

  function refreshDocuments() {
    let saved = 0;
    const missing = [];
    review.querySelectorAll('[data-review-doc]').forEach((row) => {
      const type = row.dataset.reviewDoc;
      const card = wizard.querySelector(`[data-doc-card][data-doc-type="${CSS.escape(type)}"]`);
      if (!card) return;
      const ok = card.dataset.docHasFile === '1';
      const pill = card.querySelector('[data-doc-status-pill]');
      // Every saved side of the document ("Front: a.jpg View · Back: b.jpg View", or one file).
      const savedSlots = Array.from(card.querySelectorAll('[data-doc-slot][data-has-file="1"]'));
      const status = row.querySelector('[data-review-doc-status]');
      status.className = 'fw-semibold ' + (ok ? 'text-success' : 'text-danger');
      status.innerHTML = '';
      const icon = document.createElement('i');
      icon.className = 'bi ' + (ok ? 'bi-check-circle-fill' : 'bi-x-circle-fill');
      status.append(icon, ' ' + (ok ? (pill ? pill.textContent.trim() : 'Uploaded') : (savedSlots.length ? 'Add the other side' : 'Missing')));
      const files = row.querySelector('[data-review-doc-files]');
      files.innerHTML = '';
      savedSlots.forEach((slot) => {
        const name = slot.querySelector('[data-doc-filename]');
        const link = slot.querySelector('[data-doc-view-link]');
        const span = document.createElement('span');
        span.className = 'small text-muted ms-1';
        span.textContent = (slot.dataset.side ? slot.dataset.side + ': ' : '') + (name ? name.textContent : '');
        files.appendChild(span);
        if (link && link.href) {
          const a = document.createElement('a');
          a.className = 'small ms-1';
          a.target = '_blank';
          a.rel = 'noopener';
          a.href = link.href;
          a.textContent = 'View';
          files.appendChild(a);
        }
      });
      if (ok) saved++; else missing.push('Document: ' + type);
    });
    const count = review.querySelector('[data-review-doc-count]');
    if (count) count.textContent = saved + ' of ' + review.querySelectorAll('[data-review-doc]').length + ' uploaded';
    return missing;
  }

  function refreshMissing(docMissing) {
    const box = document.getElementById('reviewMissing');
    if (!box) return;
    const missing = [];
    if (!val('first_name')) missing.push('First name');
    if (!val('last_name')) missing.push('Last name');
    if (!val('date_of_birth')) missing.push('Date of birth');
    if (!PHONE.test(val('contact_number'))) missing.push('Contact number (11 digits)');
    if (!val('purok_id')) missing.push('Purok / subdivision');
    if (!val('school_name')) missing.push('School');
    if (!val('course_name')) missing.push('Course');
    [['father_contact', "Father's contact number"], ['mother_contact', "Mother's contact number"]].forEach(([n, label]) => {
      if (val(n) !== '' && !PHONE.test(val(n))) missing.push(label + ' (11 digits or blank)');
    });
    missing.push(...docMissing);

    const listBox = box.querySelector('[data-review-missing-list]');
    const ul = listBox.querySelector('ul');
    ul.innerHTML = '';
    missing.forEach((m) => { const li = document.createElement('li'); li.textContent = m; ul.appendChild(li); });
    listBox.classList.toggle('d-none', missing.length === 0);
    box.querySelector('[data-review-complete]').classList.toggle('d-none', missing.length > 0);
    box.classList.toggle('alert-success', missing.length === 0);
    box.classList.toggle('alert-light', missing.length > 0);
    box.classList.toggle('border', missing.length > 0);
  }

  function refresh() {
    review.querySelectorAll('[data-review]').forEach((cell) => {
      const v = valueFor(cell.dataset.review);
      cell.textContent = v === '' ? '—' : v;
    });
    refreshMissing(refreshDocuments());
  }

  // Refresh whenever the Review step becomes the visible one (Next, Edit/Back, or a restored step after reload).
  if (reviewStep) {
    new MutationObserver(() => { if (reviewStep.classList.contains('active')) refresh(); })
      .observe(reviewStep, { attributes: true, attributeFilter: ['class'] });
  }
  document.addEventListener('DOMContentLoaded', refresh);
  refresh();
})();
