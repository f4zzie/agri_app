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

});
