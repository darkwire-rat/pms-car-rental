(function () {
  'use strict';

  // Theme toggle click handling + persistence.

  var STORAGE_KEY = 'pms-theme';

  function currentTheme() {
    return document.documentElement.getAttribute('data-bs-theme') || 'light';
  }

  function applyTheme(theme) {
    document.documentElement.setAttribute('data-bs-theme', theme);
    $('.js-theme-toggle').each(function () {
      var $btn = $(this);
      var $icon = $btn.find('i');
      if (theme === 'dark') {
        $icon.removeClass('fa-moon').addClass('fa-sun');
        $btn.attr('aria-label', 'Switch to light mode');
        $btn.attr('aria-pressed', 'true');
      } else {
        $icon.removeClass('fa-sun').addClass('fa-moon');
        $btn.attr('aria-label', 'Switch to dark mode');
        $btn.attr('aria-pressed', 'false');
      }
    });
  }

  $(function () {
    // Bring the toggle button into agreement with whatever the inline <head> script already applied to <html> before first paint.
    applyTheme(currentTheme());

    $(document).on('click', '.js-theme-toggle', function () {
      var next = currentTheme() === 'dark' ? 'light' : 'dark';
      localStorage.setItem(STORAGE_KEY, next);
      applyTheme(next);
    });
  });
})();
