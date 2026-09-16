document.addEventListener('DOMContentLoaded', () => {
  // Sidebar toggle (mobile)
  const toggle = document.getElementById('toggleSidebar');
  const sidebar = document.getElementById('appSidebar');
  if (toggle && sidebar) {
    toggle.addEventListener('click', () => sidebar.classList.toggle('show'));
  }

  // Auto age calculation
  const dobField = document.querySelector('[data-calc="age"]');
  const ageField = document.querySelector('[data-target="age"]');
  if (dobField && ageField) {
    const calc = () => {
      if (!dobField.value) { ageField.value = ''; return; }
      const dob = new Date(dobField.value);
      const today = new Date();
      let age = today.getFullYear() - dob.getFullYear();
      const m = today.getMonth() - dob.getMonth();
      if (m < 0 || (m === 0 && today.getDate() < dob.getDate())) age--;
      ageField.value = age >= 0 ? age : '';
    };
    dobField.addEventListener('change', calc);
    dobField.addEventListener('input', calc);
    calc();
  }

// Toggle "scholarship details" input based on checkbox
  document.querySelectorAll('input[type="checkbox"][name$="_scholarship"]').forEach(cb => {
    const detailsInput = cb.closest('.col-md-6')?.querySelector('input[name$="_scholarship_details"]');
    if (!detailsInput) return;
    const toggle = () => {
      detailsInput.disabled = !cb.checked;
      if (!cb.checked) detailsInput.value = '';
    };
    cb.addEventListener('change', toggle);
    toggle();
  });

  // Auto income sum
  const incomeFields = document.querySelectorAll('[data-sum]');
  const incomeTarget = document.querySelector('[data-target="combined_income"]');
  const totalTarget = document.querySelector('[data-target="total_family_income"]');
  if (incomeFields.length && incomeTarget) {
    const sum = () => {
      let total = 0;
      incomeFields.forEach(f => {
        const v = parseFloat(f.value);
        if (!isNaN(v)) total += v;
      });
      const formatted = total.toLocaleString('en-PH', {minimumFractionDigits: 2, maximumFractionDigits: 2});
      incomeTarget.value = formatted;
      if (totalTarget) totalTarget.value = formatted;
    };
    incomeFields.forEach(f => f.addEventListener('input', sum));
    sum();
  }

  // Multi-step wizard
  const wizard = document.querySelector('[data-wizard]');
  if (wizard) {
    const steps = wizard.querySelectorAll('.form-step');
    const indicators = wizard.querySelectorAll('.progress-step');
    let current = 0;

    const show = (i) => {
      steps.forEach((s, idx) => s.classList.toggle('active', idx === i));
      indicators.forEach((s, idx) => {
        s.classList.toggle('active', idx === i);
        s.classList.toggle('done', idx < i);
      });
      current = i;
      window.scrollTo({top: 0, behavior: 'smooth'});
    };

    wizard.querySelectorAll('[data-next]').forEach(btn => {
  btn.addEventListener('click', (e) => {
    e.preventDefault();
    const step = steps[current];
    // Only validate fields that are BOTH required AND visible AND non-disabled
    const required = step.querySelectorAll('input[required], select[required], textarea[required]');
    let firstInvalid = null;
    required.forEach(input => {
      // skip hidden or disabled
      if (input.offsetParent === null) return;
      if (!input.checkValidity()) {
        if (!firstInvalid) firstInvalid = input;
      }
    });
    if (firstInvalid) {
      firstInvalid.reportValidity();
      firstInvalid.focus();
      return;
    }
    if (current < steps.length - 1) show(current + 1);
    });
  });
    wizard.querySelectorAll('[data-prev]').forEach(btn => {
      btn.addEventListener('click', () => current > 0 && show(current - 1));
    });
    show(0);
  }

  // Notifications auto-mark on page
  const notifList = document.querySelector('[data-notifications]');
  if (notifList) {
    fetch(window.location.origin + '/sk-scholarship/public/index.php?r=applicant/notifications&mark_read=1', {method:'GET', credentials:'same-origin'});
  }
});