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

    // ------------------------------------------------------------------
    // 3. Confirm Delete – intercept delete form submissions
    // ------------------------------------------------------------------
    const deleteForms = document.querySelectorAll('form.delete-form');
    deleteForms.forEach(function (form) {
        form.addEventListener('submit', function (e) {
            const confirmed = window.confirm(
                'Are you sure you want to delete this item? This action cannot be undone.'
            );
            if (!confirmed) {
                e.preventDefault();
            }
        });
    });

    // Also handle individual delete buttons with data-confirm attribute
    const deleteButtons = document.querySelectorAll('[data-confirm]');
    deleteButtons.forEach(function (btn) {
        btn.addEventListener('click', function (e) {
            const message = btn.getAttribute('data-confirm') ||
                'Are you sure you want to delete this item?';
            if (!window.confirm(message)) {
                e.preventDefault();
                e.stopPropagation();
            }
        });
    });

    // ------------------------------------------------------------------
    // 4. AJAX Live Search
    // ------------------------------------------------------------------
    const searchInput = document.getElementById('searchInput');
    const searchResults = document.getElementById('searchResults');

    if (searchInput && searchResults) {
        let searchTimeout = null;

        searchInput.addEventListener('input', function () {
            const query = searchInput.value.trim();

            clearTimeout(searchTimeout);

            if (query.length === 0) {
                searchResults.innerHTML = '';
                searchResults.style.display = 'none';
                return;
            }

            searchTimeout = setTimeout(function () {
                fetch('search.php?q=' + encodeURIComponent(query), {
                    method: 'GET',
                    headers: { 'X-Requested-With': 'XMLHttpRequest' }
                })
                    .then(function (response) {
                        if (!response.ok) throw new Error('Network error');
                        return response.json();
                    })
                    .then(function (data) {
                        searchResults.innerHTML = '';

                        if (!data || data.length === 0) {
                            searchResults.innerHTML =
                                '<div class="search-item text-muted">No results found.</div>';
                            searchResults.style.display = 'block';
                            return;
                        }

                        data.forEach(function (crop) {
                            const item = document.createElement('div');
                            item.className = 'search-item';
                            item.innerHTML =
                                '<strong>' + escapeHtml(crop.name) + '</strong>' +
                                (crop.variety
                                    ? ' <small class="text-muted">(' + escapeHtml(crop.variety) + ')</small>'
                                    : '') +
                                ' <span class="badge bg-secondary ms-1">' +
                                escapeHtml(crop.status) + '</span>';

                            item.addEventListener('click', function () {
                                window.location.href = 'edit_crop.php?id=' + crop.id;
                            });

                            searchResults.appendChild(item);
                        });

                        searchResults.style.display = 'block';
                    })
                    .catch(function (err) {
                        console.error('Search error:', err);
                    });
            }, 300); // debounce 300ms
        });

        // Hide results when clicking elsewhere
        document.addEventListener('click', function (e) {
            if (!searchInput.contains(e.target) && !searchResults.contains(e.target)) {
                searchResults.style.display = 'none';
            }
        });
    }

    // ------------------------------------------------------------------
    // 5. Image Preview on File Input Change
    // ------------------------------------------------------------------
    const imageInput = document.getElementById('imageInput');
    const imagePreview = document.getElementById('imagePreview');

    if (imageInput && imagePreview) {
        imageInput.addEventListener('change', function () {
            const file = imageInput.files[0];

            if (!file) {
                imagePreview.style.display = 'none';
                imagePreview.src = '';
                return;
            }

            if (!file.type.startsWith('image/')) {
                alert('Please select a valid image file.');
                imageInput.value = '';
                imagePreview.style.display = 'none';
                return;
            }

            const reader = new FileReader();
            reader.onload = function (e) {
                imagePreview.src = e.target.result;
                imagePreview.style.display = 'block';
            };
            reader.readAsDataURL(file);
        });
    }

    // ------------------------------------------------------------------
    // Helper: escape HTML to prevent XSS in dynamic content
    // ------------------------------------------------------------------
    function escapeHtml(str) {
        if (!str) return '';
        return str
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }
});
