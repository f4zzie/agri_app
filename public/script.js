/* ============================================================
   AgriTrack – script.js
   ============================================================ */

document.addEventListener('DOMContentLoaded', function () {

    // ------------------------------------------------------------------
    // 1. Mobile Sidebar Toggle
    // ------------------------------------------------------------------
    const sidebarToggle = document.getElementById('sidebarToggle');
    const sidebar = document.getElementById('sidebar');

    if (sidebarToggle && sidebar) {
        const setOpen = function (open) {
            sidebar.classList.toggle('show', open);
            sidebarToggle.setAttribute('aria-expanded', open ? 'true' : 'false');
        };
        sidebarToggle.addEventListener('click', function () {
            setOpen(!sidebar.classList.contains('show'));
        });

        // Close the overlay sidebar on outside click or Escape
        document.addEventListener('click', function (e) {
            if (window.innerWidth < 992 && sidebar.classList.contains('show') &&
                !sidebar.contains(e.target) && !sidebarToggle.contains(e.target)) {
                setOpen(false);
            }
        });
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && sidebar.classList.contains('show')) {
                setOpen(false);
                sidebarToggle.focus();
            }
        });
    }

    // ------------------------------------------------------------------
    // 2. Auto-dismiss Flash Alerts after 4 seconds
    // ------------------------------------------------------------------
    const flashAlerts = document.querySelectorAll('.flash-alert');
    flashAlerts.forEach(function (alert) {
        setTimeout(function () {
            // Use Bootstrap's dismiss API if available
            if (typeof bootstrap !== 'undefined') {
                const bsAlert = bootstrap.Alert.getOrCreateInstance(alert);
                bsAlert.close();
            } else {
                alert.style.display = 'none';
            }
        }, 4000);
    });

    // ------------------------------------------------------------------
    // 3. Logout: clear the service worker cache first, so a shared phone
    //    keeps nothing from this account (e.g. the cached listing form)
    // ------------------------------------------------------------------
    document.querySelectorAll('form[action$="logout.php"]').forEach(function (form) {
        form.addEventListener('submit', function (e) {
            if (!('caches' in window) || form.dataset.cleared) {
                return;
            }
            e.preventDefault();
            form.dataset.cleared = '1';
            caches.keys()
                .then(function (keys) { return Promise.all(keys.map(function (k) { return caches.delete(k); })); })
                .finally(function () { form.submit(); });
        });
    });

    // ------------------------------------------------------------------
    // 4. Offline-friendly forms: <form data-draft-key="..."> keeps a draft
    //    in localStorage. Submitting while offline keeps the draft instead
    //    of losing it; it is restored next time the page opens online.
    // ------------------------------------------------------------------
    document.querySelectorAll('form[data-draft-key]').forEach(function (form) {
        const key = form.dataset.draftKey;
        const notice = document.getElementById('offlineNotice');
        const say = function (msg, withReload) {
            if (!notice || !msg) return;
            notice.textContent = msg + ' ';
            if (withReload) {
                const btn = document.createElement('button');
                btn.type = 'button';
                btn.className = 'btn btn-sm btn-success ms-1';
                btn.textContent = notice.dataset.reloadLabel || 'Reload';
                btn.addEventListener('click', function () { location.reload(); });
                notice.appendChild(btn);
            }
            notice.classList.remove('d-none');
        };
        const fields = function () {
            return Array.prototype.filter.call(form.elements, function (el) {
                return el.name && el.type !== 'file' && el.type !== 'hidden' && el.type !== 'submit';
            });
        };
        const read = function () {
            try { return JSON.parse(localStorage.getItem(key) || 'null'); } catch (err) { return null; }
        };
        const save = function () {
            const data = {};
            fields().forEach(function (el) { data[el.name] = el.value; });
            try { localStorage.setItem(key, JSON.stringify(data)); } catch (err) { /* storage full or blocked */ }
        };

        // Restore a saved draft into an empty form (not over server-side values)
        const draft = read();
        const nameField = form.elements.namedItem('name');
        if (draft && nameField && nameField.value === '') {
            fields().forEach(function (el) {
                if (Object.prototype.hasOwnProperty.call(draft, el.name)) el.value = draft[el.name];
            });
            say(notice && notice.dataset.restored);
        }

        form.addEventListener('input', save);
        form.addEventListener('change', save);
        form.addEventListener('submit', function (e) {
            if (!navigator.onLine) {
                e.preventDefault();
                save();
                say(notice && notice.dataset.offlineSaved);
                return;
            }
            try { localStorage.removeItem(key); } catch (err) { /* ignore */ }
        });
        window.addEventListener('online', function () {
            if (read()) say(notice && notice.dataset.backOnline, true);
        });
    });

    // ------------------------------------------------------------------
    // 5. Installable app (PWA): register the service worker
    // ------------------------------------------------------------------
    if ('serviceWorker' in navigator) {
        navigator.serviceWorker.register('sw.js').catch(function (err) {
            console.warn('Service worker not registered:', err);
        });
    }
});
