/* DPE dashboard shell interactions. No build step required. */
(function () {
    'use strict';

    function initDashboard() {
        document.querySelectorAll('[data-account-avatar-image]').forEach(function (image) {
            image.addEventListener('error', function () { image.hidden = true; });
            if (image.hasAttribute('src') && image.complete && image.naturalWidth === 0) image.hidden = true;
        });
        var body = document.body;
        var sidebar = document.querySelector('[data-sidebar]');
        var sidebarToggle = document.querySelector('[data-sidebar-toggle]');
        var sidebarClose = document.querySelector('[data-sidebar-close]');
        var sidebarOverlay = document.querySelector('[data-sidebar-overlay]');
        var accountToggle = document.querySelector('[data-account-toggle]');
        var accountMenu = document.querySelector('[data-account-menu]');
        var notificationToggle = document.querySelector('[data-notification-toggle]');
        var notificationPanel = document.querySelector('[data-notification-panel]');
        var notificationClose = document.querySelector('[data-notification-close]');
        var toast = document.querySelector('[data-dashboard-toast]');
        var confirmModal = document.querySelector('[data-dashboard-confirm]');
        var confirmBackdrop = confirmModal ? confirmModal.querySelector('[data-confirm-backdrop]') : null;
        var confirmCancel = confirmModal ? confirmModal.querySelector('[data-confirm-cancel]') : null;
        var confirmSubmit = confirmModal ? confirmModal.querySelector('[data-confirm-submit]') : null;
        var confirmTitle = confirmModal ? confirmModal.querySelector('[data-confirm-title]') : null;
        var confirmMessage = confirmModal ? confirmModal.querySelector('[data-confirm-message]') : null;
        var confirmForm = null;
        var confirmTrigger = null;
        var toastTimer;

        function setHidden(element, hidden) {
            if (!element) {
                return;
            }

            element.hidden = hidden;
        }

        function setSidebar(open) {
            if (!sidebar) {
                return;
            }

            sidebar.classList.toggle('is-open', open);
            body.classList.toggle('is-sidebar-open', open);
            setHidden(sidebarOverlay, !open);

            if (sidebarToggle) {
                sidebarToggle.setAttribute('aria-expanded', String(open));
            }
        }

        function closeAccount() {
            setHidden(accountMenu, true);
            if (accountToggle) {
                accountToggle.setAttribute('aria-expanded', 'false');
            }
        }

        function closeNotifications() {
            setHidden(notificationPanel, true);
            if (notificationToggle) {
                notificationToggle.setAttribute('aria-expanded', 'false');
            }
        }

        function showToast(message) {
            if (!toast) {
                return;
            }

            window.clearTimeout(toastTimer);
            toast.textContent = message;
            setHidden(toast, false);
            toastTimer = window.setTimeout(function () {
                setHidden(toast, true);
            }, 2800);
        }

        function closeConfirm() {
            setHidden(confirmModal, true);
            body.classList.remove('is-confirm-open');

            if (confirmTrigger) {
                confirmTrigger.focus();
            }

            confirmForm = null;
            confirmTrigger = null;
        }

        function openConfirm(form) {
            if (!confirmModal) {
                return;
            }

            confirmForm = form;
            confirmTrigger = form.querySelector('button[type="submit"], input[type="submit"]');

            if (confirmTitle) {
                confirmTitle.textContent = form.dataset.confirmTitle || 'Konfirmasi tindakan';
            }

            if (confirmMessage) {
                confirmMessage.textContent = form.dataset.confirmMessage || 'Periksa kembali tindakan ini sebelum melanjutkan.';
            }

            if (confirmSubmit) {
                confirmSubmit.textContent = form.dataset.confirmAction || 'Lanjutkan';
                confirmSubmit.disabled = false;
            }

            setHidden(confirmModal, false);
            body.classList.add('is-confirm-open');

            if (confirmCancel) {
                confirmCancel.focus();
            }
        }

        if (sidebarToggle) {
            sidebarToggle.addEventListener('click', function () {
                setSidebar(!sidebar.classList.contains('is-open'));
            });
        }

        if (sidebarClose) {
            sidebarClose.addEventListener('click', function () {
                setSidebar(false);
            });
        }

        if (sidebarOverlay) {
            sidebarOverlay.addEventListener('click', function () {
                setSidebar(false);
            });
        }

        if (accountToggle && accountMenu) {
            accountToggle.addEventListener('click', function () {
                var willOpen = accountMenu.hidden;
                closeNotifications();
                setHidden(accountMenu, !willOpen);
                accountToggle.setAttribute('aria-expanded', String(willOpen));
            });
        }

        if (notificationToggle && notificationPanel) {
            notificationToggle.addEventListener('click', function () {
                var willOpen = notificationPanel.hidden;
                closeAccount();
                setHidden(notificationPanel, !willOpen);
                notificationToggle.setAttribute('aria-expanded', String(willOpen));
            });
        }

        if (notificationClose) {
            notificationClose.addEventListener('click', closeNotifications);
        }

        if (confirmCancel) {
            confirmCancel.addEventListener('click', closeConfirm);
        }

        if (confirmBackdrop) {
            confirmBackdrop.addEventListener('click', closeConfirm);
        }

        if (confirmSubmit) {
            confirmSubmit.addEventListener('click', function () {
                if (!confirmForm) {
                    return;
                }

                var submission = new CustomEvent('dpe:confirmed-submit', { bubbles: true, cancelable: true });
                if (!confirmForm.dispatchEvent(submission)) {
                    closeConfirm();
                    return;
                }

                confirmSubmit.disabled = true;
                HTMLFormElement.prototype.submit.call(confirmForm);
            });
        }

        document.querySelectorAll('[data-confirm-form]').forEach(function (form) {
            form.addEventListener('submit', function (event) {
                event.preventDefault();
                openConfirm(form);
            });
        });

        document.addEventListener('click', function (event) {
            if (accountMenu && accountToggle && !accountMenu.contains(event.target) && !accountToggle.contains(event.target)) {
                closeAccount();
            }

            if (notificationPanel && notificationToggle && !notificationPanel.contains(event.target) && !notificationToggle.contains(event.target)) {
                closeNotifications();
            }
        });

        document.addEventListener('keydown', function (event) {
            if (event.key !== 'Escape') {
                return;
            }

            setSidebar(false);
            closeAccount();
            closeNotifications();
            closeConfirm();
        });

        document.querySelectorAll('[data-planned="true"]').forEach(function (link) {
            link.addEventListener('click', function (event) {
                event.preventDefault();
                showToast('Modul ini akan tersedia pada tahap berikutnya.');
            });
        });

        document.querySelectorAll('[data-dashboard-retry]').forEach(function (button) {
            button.addEventListener('click', function () {
                window.location.reload();
            });
        });

        document.querySelectorAll('[data-sidebar] a:not([data-planned])').forEach(function (link) {
            link.addEventListener('click', function () {
                if (window.innerWidth <= 980) {
                    setSidebar(false);
                }
            });
        });

        window.addEventListener('resize', function () {
            if (window.innerWidth > 980) {
                setSidebar(false);
            }
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initDashboard);
    } else {
        initDashboard();
    }
}());
