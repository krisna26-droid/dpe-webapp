/* Loaded after the sidebar and before the workspace: restore the tablet
   preference before the page content is laid out. */
(function () {
    'use strict';

    var body = document.body;
    var sidebar = document.querySelector('[data-sidebar]');
    if (!body || !sidebar) return;

    var owner = sidebar.getAttribute('data-sidebar-layout-key');
    var storageKey = owner ? 'dpe.sidebar-layout.v1.' + owner : null;
    var toggle = null;
    var close = document.querySelector('[data-sidebar-close]');
    var overlay = document.querySelector('[data-sidebar-overlay]');
    var mode = null;
    var visible = null;
    var tabletCollapsed = false;
    var drawerOpen = false;

    function restorePreference() {
        if (!storageKey) return;
        try {
            tabletCollapsed = window.sessionStorage.getItem(storageKey) === 'collapsed';
        } catch (error) {
            // A blocked storage API must not disable navigation on the page.
        }
    }

    function savePreference() {
        if (!storageKey) return;
        try {
            if (tabletCollapsed) window.sessionStorage.setItem(storageKey, 'collapsed');
            else window.sessionStorage.removeItem(storageKey);
        } catch (error) {
            // The current page still retains its preference in memory.
        }
    }

    function getMode() {
        var cssMode = window.getComputedStyle(body).getPropertyValue('--dpe-sidebar-mode').trim();
        if (cssMode === 'tablet' || cssMode === 'drawer' || cssMode === 'desktop') return cssMode;
        // Compatibility with the existing layout if the new stylesheet failed to load.
        var breakpoint = body.classList.contains('dpe-teacher-students-page') ? 1280 : 980;
        return window.innerWidth <= breakpoint ? 'drawer' : 'desktop';
    }

    function sync() {
        var nextMode = getMode();
        if (nextMode !== mode) drawerOpen = false;
        mode = nextMode;
        var drawerVisible = mode === 'drawer' && drawerOpen;
        var collapsed = mode === 'tablet' && tabletCollapsed;
        var nextVisible = mode === 'desktop' || (mode === 'tablet' && !collapsed) || drawerVisible;

        if (visible === true && !nextVisible) {
            sidebar.dispatchEvent(new Event('dpe:sidebar-before-hide'));
            if (toggle && sidebar.contains(document.activeElement)) toggle.focus({ preventScroll: true });
        }
        body.classList.toggle('is-tablet-sidebar-collapsed', collapsed);
        body.classList.toggle('is-sidebar-open', drawerVisible);
        sidebar.classList.toggle('is-open', drawerVisible);
        sidebar.inert = !nextVisible;
        sidebar.setAttribute('aria-hidden', String(!nextVisible));
        if (overlay) overlay.hidden = !drawerVisible;
        if (toggle) {
            toggle.setAttribute('aria-expanded', String(nextVisible));
            toggle.setAttribute('aria-label', nextVisible ? 'Sembunyikan navigasi' : 'Buka navigasi');
        }
        if (visible === false && nextVisible) sidebar.dispatchEvent(new Event('dpe:sidebar-shown'));
        visible = nextVisible;
    }

    function setOpen(open) {
        if (mode === 'tablet') {
            tabletCollapsed = !open;
            savePreference();
        } else if (mode === 'drawer') {
            drawerOpen = open;
        } else {
            return;
        }
        sync();
    }

    function attach() {
        toggle = document.querySelector('[data-sidebar-toggle]');
        sync();
        if (toggle) toggle.addEventListener('click', function () { setOpen(!visible); });
        if (close) close.addEventListener('click', function () { setOpen(false); });
        if (overlay) overlay.addEventListener('click', function () { if (mode === 'drawer') setOpen(false); });
        document.querySelectorAll('[data-sidebar] a:not([data-planned])').forEach(function (link) {
            link.addEventListener('click', function () { if (mode === 'drawer') setOpen(false); });
        });
        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape' && mode === 'drawer' && drawerOpen) setOpen(false);
        });
    }

    restorePreference();
    sync();
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', attach, { once: true });
    else attach();
    window.addEventListener('resize', sync);
    window.addEventListener('pageshow', function () { restorePreference(); sync(); });
}());
