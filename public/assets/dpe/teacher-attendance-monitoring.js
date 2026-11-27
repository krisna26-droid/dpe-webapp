(function () {
    'use strict';

    document.addEventListener('click', (event) => {
        const detailLink = event.target.closest?.('a[data-attendance-detail]');
        if (!detailLink || event.defaultPrevented || event.button !== 0 || event.ctrlKey || event.metaKey || event.shiftKey || event.altKey) {
            return;
        }

        const detailUrl = new URL(detailLink.href);
        detailUrl.searchParams.set('monitoring[scroll_y]', String(Math.max(0, Math.round(window.scrollY))));
        detailLink.href = detailUrl.href;
    });

    window.addEventListener('pageshow', () => {
        const position = /^#attendance-scroll-(\d+)$/.exec(window.location.hash);
        if (!position) {
            return;
        }

        window.requestAnimationFrame(() => {
            window.scrollTo({ top: Number(position[1]), behavior: 'instant' });
            const url = new URL(window.location.href);
            url.hash = '';
            window.history.replaceState(window.history.state, '', url.href);
        });
    });
})();
