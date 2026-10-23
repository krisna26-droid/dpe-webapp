(() => {
    'use strict';

    const fallback = 'Alamat belum tersedia. Titik lokasi dapat dilihat di peta.';

    const start = async () => {
        if (typeof window.fetch !== 'function' || typeof window.AbortController !== 'function') return;

        // Two points at most on this page. Resolve in order instead of flooding the provider.
        for (const location of document.querySelectorAll('[data-attendance-location]')) {
            if (location.dataset.locationLoaded) continue;
            const address = location.querySelector('[data-location-address]');
            const credit = location.querySelector('[data-location-credit]');
            if (!address) continue;

            const rawUrl = location.dataset.locationUrl;
            let url;
            try {
                if (!rawUrl) continue;
                url = new URL(rawUrl, window.location.href);
                if (url.origin !== window.location.origin) continue;
            } catch (_) { continue; }

            location.dataset.locationLoaded = 'true';
            const controller = new window.AbortController();
            const timer = window.setTimeout(() => controller.abort(), 6500);
            address.textContent = 'Memuat alamat…';
            try {
                const response = await window.fetch(url.href, {
                    credentials: 'same-origin', cache: 'no-store', redirect: 'error',
                    headers: { Accept: 'application/json' }, signal: controller.signal,
                });
                if (!response.ok) throw new Error('Location unavailable');
                const result = await response.json();
                if (location.dataset.locationUrl !== rawUrl || !location.isConnected) continue;
                if (result && result.status === 'resolved' && typeof result.address === 'string'
                    && result.address.trim() && result.address.length <= 600) {
                    address.textContent = 'Alamat perkiraan: ' + result.address.trim();
                    if (credit) credit.hidden = false;
                } else {
                    address.textContent = fallback;
                }
            } catch (_) {
                if (location.dataset.locationUrl === rawUrl && location.isConnected) address.textContent = fallback;
            } finally {
                window.clearTimeout(timer);
            }
        }
    };

    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', start, { once: true });
    else start();
})();
