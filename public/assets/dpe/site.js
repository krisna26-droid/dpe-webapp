(() => {
    'use strict';

    const menuButton = document.querySelector('[data-menu-toggle]');
    const navigation = document.getElementById('site-navigation');
    if (menuButton && navigation) {
        document.documentElement.classList.add('dpe-enhanced');
        menuButton.hidden = false;
        const setMenu = (open) => {
            navigation.classList.toggle('is-open', open);
            menuButton.setAttribute('aria-expanded', String(open));
            menuButton.setAttribute('aria-label', open ? 'Tutup menu navigasi' : 'Buka menu navigasi');
        };
        menuButton.addEventListener('click', () => setMenu(menuButton.getAttribute('aria-expanded') !== 'true'));
        navigation.addEventListener('click', (event) => {
            if (event.target.closest('a')) setMenu(false);
        });
        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape' && menuButton.getAttribute('aria-expanded') === 'true') {
                setMenu(false);
                menuButton.focus();
            }
        });
        const media = window.matchMedia('(max-width: 800px)');
        if (media.addEventListener) media.addEventListener('change', () => setMenu(false));
    }

    const onePageSections = [...document.querySelectorAll('.onepage-anchor[id]')];
    const onePageLinks = [...document.querySelectorAll('.site-nav .nav-link[data-section]')];
    if (onePageSections.length && onePageLinks.length && 'IntersectionObserver' in window) {
        const activate = (id) => {
            onePageLinks.forEach((link) => {
                const active = link.dataset.section === id;
                link.classList.toggle('is-active', active);
                if (active) link.setAttribute('aria-current', 'page');
                else link.removeAttribute('aria-current');
            });
        };
        const observer = new IntersectionObserver((entries) => {
            const visible = entries.filter((entry) => entry.isIntersecting).sort((a, b) => b.intersectionRatio - a.intersectionRatio);
            if (visible[0]) activate(visible[0].target.id);
        }, { rootMargin: '-42% 0px -42% 0px', threshold: [0.05, 0.25, 0.6] });
        onePageSections.forEach((section) => observer.observe(section));
        const hash = window.location.hash.replace('#', '');
        if (hash && onePageSections.some((section) => section.id === hash)) activate(hash);
    }

    const directory = document.querySelector('[data-branch-directory]');
    const search = directory?.querySelector('[data-branch-search]');
    if (search) {
        const cards = [...directory.querySelectorAll('[data-branch-card]')];
        const count = directory.querySelector('[data-branch-count]');
        const empty = directory.querySelector('[data-branch-empty]');
        const normalize = (text) => text.normalize('NFKC').toLocaleLowerCase('id-ID').trim();
        search.addEventListener('input', () => {
            const query = normalize(search.value);
            let visible = 0;
            cards.forEach((card) => {
                const matches = normalize(card.dataset.search || '').includes(query);
                card.hidden = !matches;
                if (matches) visible += 1;
            });
            if (count) count.textContent = `${visible} cabang ditampilkan`;
            if (empty) empty.hidden = visible !== 0;
        });
    }

    const passwordButton = document.querySelector('[data-password-toggle]');
    const password = document.getElementById('password');
    if (passwordButton && password) {
        passwordButton.hidden = false;
        passwordButton.addEventListener('click', () => {
            const visible = password.type === 'password';
            password.type = visible ? 'text' : 'password';
            passwordButton.setAttribute('aria-pressed', String(visible));
            passwordButton.setAttribute('aria-label', visible ? 'Sembunyikan password' : 'Tampilkan password');
        });
    }

    const loginForm = document.querySelector('[data-login-form]');
    if (loginForm) {
        const submit = loginForm.querySelector('[type="submit"]');
        const label = loginForm.querySelector('[data-submit-label]');
        loginForm.addEventListener('submit', () => {
            submit.disabled = true;
            label.textContent = 'Sedang masuk…';
            loginForm.setAttribute('aria-busy', 'true');
        });
        window.addEventListener('pageshow', () => {
            submit.disabled = false;
            label.textContent = 'Masuk ke Portal';
            loginForm.removeAttribute('aria-busy');
        });
    }
})();
