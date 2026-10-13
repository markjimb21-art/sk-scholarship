/** E-mail code pages (sign-up and password reset): counts down the code's 10 minutes, then says it expired. */
(function () {
  'use strict';

  var total = 10 * 60, time = document.getElementById('otpTime');
  var timer = setInterval(function () {
    total--;
    if (total <= 0) {
      clearInterval(timer);
      document.getElementById('otpCountdown').hidden = true;
      document.getElementById('otpExpired').innerHTML = '<p class="field-error">The code has expired. Press "Resend Code" to get a new one.</p>';
      return;
    }
    var m = Math.floor(total / 60), s = total % 60;
    time.textContent = m + ':' + (s < 10 ? '0' : '') + s;
  }, 1000);
})();
