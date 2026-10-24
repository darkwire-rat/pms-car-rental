// Drives the shared notification bell on both the customer and admin shells.

(function () {
  'use strict';

  function init() {
    var bell = document.getElementById('notificationBell');
    if (!bell) return;
    if (bell.dataset.notificationsInitialized === 'true') return;
    bell.dataset.notificationsInitialized = 'true';

    var audience = bell.getAttribute('data-audience') || 'user';
    var badge = document.getElementById('notificationBadge');
    var badgeCount = document.getElementById('notificationBadgeCount');
    var list = document.getElementById('notificationList');
    var markAll = document.getElementById('notificationMarkAll');
    var toggle = document.getElementById('notificationBellToggle');
    var q = '?audience=' + encodeURIComponent(audience);
    var listRequest = 0;

    function setBadge(n) {
      if (n > 0) {
        badgeCount.textContent = n > 99 ? '99+' : String(n);
        badge.classList.remove('d-none');
      } else {
        badge.classList.add('d-none');
      }
    }

    function refreshCount() {
      fetch('notifications_unread_count.php' + q, { credentials: 'same-origin' })
        .then(function (r) { return r.json(); })
        .then(function (data) {
          if (!data || !data.authenticated) { bell.classList.add('d-none'); return; }
          bell.classList.remove('d-none');
          setBadge(data.count || 0);
        })
        .catch(function () { /* leave the bell as-is on a transient error */ });
    }

    function renderItems(items) {
      list.innerHTML = '';
      if (!items || !items.length) {
        var empty = document.createElement('p');
        empty.className = 'text-body-secondary small text-center py-4 mb-0';
        empty.textContent = 'No notifications yet.';
        list.appendChild(empty);
        return;
      }
      items.forEach(function (it) {
        var row = document.createElement(it.link_url ? 'a' : 'div');
        row.className = 'd-block px-3 py-2 border-bottom text-decoration-none text-body notification-item'
          + (it.is_read ? '' : ' bg-body-tertiary');
        if (it.link_url) row.href = it.link_url;

        var title = document.createElement('div');
        title.className = 'small' + (it.is_read ? '' : ' fw-semibold');
        title.textContent = it.title;
        row.appendChild(title);

        if (it.body) {
          var body = document.createElement('div');
          body.className = 'small text-body-secondary';
          body.textContent = it.body;
          row.appendChild(body);
        }

        var ago = document.createElement('div');
        ago.className = 'small text-body-secondary';
        ago.textContent = it.ago;
        row.appendChild(ago);

        row.addEventListener('click', function () {
          if (!it.is_read) {
            fetch('notifications_mark_read.php', {
              method: 'POST',
              credentials: 'same-origin',
              headers: { 'Content-Type': 'application/json' },
              body: JSON.stringify({ id: it.id, audience: audience })
            }).then(function () {
              loadList();
              refreshCount();
            });
          }
        });

        list.appendChild(row);
      });
    }

    function loadList() {
      var requestId = ++listRequest;
      list.innerHTML = '<p class="text-body-secondary small text-center py-4 mb-0">Loading…</p>';
      fetch('notifications_list.php' + q, { credentials: 'same-origin' })
        .then(function (r) { return r.json(); })
        .then(function (data) {
          if (requestId !== listRequest) return;
          if (!data || !data.authenticated) { bell.classList.add('d-none'); return; }
          setBadge(data.unread || 0);
          renderItems(data.items);
        })
        .catch(function () {
          if (requestId !== listRequest) return;
          list.innerHTML = '<p class="text-danger small text-center py-4 mb-0">Could not load notifications.</p>';
        });
    }

    if (toggle) {
      toggle.addEventListener('click', function () {
        // Refresh when opening.
        if (toggle.getAttribute('aria-expanded') === 'false') loadList();
      });
    }

    if (markAll) {
      markAll.addEventListener('click', function (e) {
        e.preventDefault();
        e.stopPropagation();
        fetch('notifications_mark_read.php', {
          method: 'POST',
          credentials: 'same-origin',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ audience: audience })
        })
          .then(function (r) { return r.json(); })
          .then(function () { loadList(); refreshCount(); });
      });
    }

    // Pre-populate the dropdown on load so opening it is instant even while a heavy page is still busy on the main thread.
    refreshCount();
    loadList();
    setInterval(function () {
      refreshCount();
      if (document.visibilityState === 'visible') loadList();
    }, 30000);
    document.addEventListener('visibilitychange', function () {
      if (document.visibilityState === 'visible') {
        refreshCount();
        loadList();
      }
    });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
