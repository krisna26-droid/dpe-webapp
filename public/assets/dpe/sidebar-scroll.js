/* Loaded immediately after the sidebar so its position is restored before the main content. */
(function () {
    'use strict';

    var sidebar = document.querySelector('[data-sidebar]');
    var owner = sidebar ? sidebar.getAttribute('data-sidebar-scroll-key') : '';
    if (!sidebar || !owner) {
        return;
    }

    var key = 'dpe.sidebar-scroll.v1.' + owner;

    function restorePosition() {
        try {
            var saved = window.sessionStorage.getItem(key);
            if (saved === null || saved.trim() === '') {
                return;
            }

            var position = Number(saved);
            if (Number.isFinite(position) && position >= 0) {
                sidebar.scrollTop = Math.min(position, Math.max(0, sidebar.scrollHeight - sidebar.clientHeight));
            }
        } catch (error) {
            // Navigation still works when the browser does not allow session storage.
        }
    }

    function savePosition() {
        try {
            var position = sidebar.scrollTop;
            if (sidebar.clientHeight > 0 && Number.isFinite(position) && position >= 0) {
                window.sessionStorage.setItem(key, String(position));
            }
        } catch (error) {
            // The saved value is only a convenience, never a requirement for navigation.
        }
    }

    restorePosition();
    sidebar.addEventListener('scroll', savePosition, { passive: true });
    sidebar.addEventListener('click', savePosition, true);
    window.addEventListener('pagehide', savePosition);
    window.addEventListener('pageshow', restorePosition);
    document.addEventListener('visibilitychange', function () {
        if (document.visibilityState === 'hidden') {
            savePosition();
        }
    });
}());
