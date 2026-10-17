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
        sidebarToggle.addEventListener('click', function () {
            sidebar.classList.toggle('show');
        });

        // Close sidebar when clicking outside on mobile
        document.addEventListener('click', function (e) {
            if (
                window.innerWidth <= 768 &&
                sidebar.classList.contains('show') &&
                !sidebar.contains(e.target) &&
                !sidebarToggle.contains(e.target)
            ) {
                sidebar.classList.remove('show');
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
