// Attaches the session CSRF token to every same-origin.

(function () {
  'use strict';

  var token = null;
  var ready = fetch('csrf.php', { credentials: 'same-origin' })
    .then(function (r) { return r.json(); })
    .then(function (d) { token = d && d.token ? d.token : null; })
    .catch(function () { token = null; });

  function isSameOrigin(url) {
    if (typeof url !== 'string') return true; // Request object → assume same-origin
    return !/^https?:\/\//i.test(url) || url.indexOf(window.location.origin) === 0;
  }
  function needsToken(method) {
    method = (method || 'GET').toUpperCase();
    return method !== 'GET' && method !== 'HEAD' && method !== 'OPTIONS';
  }

  var origFetch = window.fetch;
  window.fetch = function (url, opts) {
    opts = opts || {};
    if (isSameOrigin(url) && needsToken(opts.method)) {
      return ready.then(function () {
        var headers = new Headers(opts.headers || {});
        if (token) headers.set('X-CSRF-Token', token);
        opts.headers = headers;
        return origFetch(url, opts);
      });
    }
    return origFetch(url, opts);
  };

  // JQuery may not be loaded yet.
  var tries = 0;
  var iv = setInterval(function () {
    if (window.jQuery) {
      clearInterval(iv);
      window.jQuery.ajaxPrefilter(function (options, originalOptions, jqXHR) {
        if (needsToken(options.type) && isSameOrigin(options.url) && token) {
          jqXHR.setRequestHeader('X-CSRF-Token', token);
        }
      });
    } else if (++tries > 100) {
      clearInterval(iv);
    }
  }, 50);
})();
