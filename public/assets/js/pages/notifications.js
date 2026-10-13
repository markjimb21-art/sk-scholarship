/**
 * Notifications page: opening it marks the user's notifications as read. A POST with the CSRF token to the URL the
 * server wrote into data-mark-read-url; once it succeeds, the bell in the top bar loses its unread count.
 */
(function () {
  'use strict';

  var list = document.querySelector('[data-notifications]');
  if (!list) return;
  var body = new FormData();
  body.append('_csrf', list.dataset.csrf);
  fetch(list.dataset.markReadUrl, {
    method: 'POST', body: body, credentials: 'same-origin',
    headers: { 'X-CSRF-Token': list.dataset.csrf, 'X-Requested-With': 'XMLHttpRequest' },
  }).then(function (r) {
    if (!r.ok) return;
    var bell = document.querySelector('[data-notification-bell]');
    if (!bell) return;
    var badge = bell.querySelector('[data-unread-badge]');
    if (badge) badge.remove();
    bell.setAttribute('aria-label', 'Notifications');
  }).catch(function () { /* offline: they stay unread and the next visit tries again */ });
})();
