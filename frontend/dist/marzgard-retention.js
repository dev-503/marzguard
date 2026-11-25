/* MarzGard — abandoned cart recovery link handler (loaded on all front-end pages). */
(function () {
  'use strict';

  if (!window.MARZGARD_RETENTION) { return; }

  var params = new URLSearchParams(window.location.search);
  var token = params.get('mg_recover');
  if (!token) { return; }

  var url = window.MARZGARD_RETENTION.restUrl.replace(/\/$/, '') + '/retention/recover?token=' + encodeURIComponent(token) + '&nonce=' + encodeURIComponent(window.MARZGARD_RETENTION.nonce || '');

  fetch(url, { headers: { 'X-WP-Nonce': window.MARZGARD_RETENTION.nonce || '' } })
    .then(function (r) { return r.json(); })
    .then(function (data) {
      if (data && data.success && data.redirect) {
        window.location.href = data.redirect;
      }
    })
    .catch(function () {});
})();
