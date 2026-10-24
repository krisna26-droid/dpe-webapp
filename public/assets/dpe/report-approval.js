(function () {
    'use strict';

    document.querySelectorAll('[data-report-approval]').forEach(function (approval) {
        var open = approval.querySelector('[data-report-approval-open]');
        var form = approval.querySelector('[data-report-approval-form]');
        var submit = approval.querySelector('[data-report-approval-submit]');
        var cancel = approval.querySelector('[data-report-approval-cancel]');
        if (!open || !form || !submit || !cancel) return;

        var submitting = false;
        var restoreFrame = null;
        var anchoring;

        function toggle(confirming) {
            var left = window.scrollX;
            var top = window.scrollY;
            var root = document.documentElement;
            if (restoreFrame === null) {
                anchoring = root.style.overflowAnchor;
            } else {
                window.cancelAnimationFrame(restoreFrame);
            }
            // Keep layout changes and keyboard focus from moving the document.
            root.style.overflowAnchor = 'none';
            open.hidden = confirming;
            form.hidden = !confirming;
            (confirming ? cancel : open).focus({ preventScroll: true });
            window.scrollTo({ left: left, top: top, behavior: 'instant' });
            restoreFrame = window.requestAnimationFrame(function () {
                window.scrollTo({ left: left, top: top, behavior: 'instant' });
                root.style.overflowAnchor = anchoring;
                restoreFrame = null;
            });
        }

        open.addEventListener('click', function (event) {
            // Preserve the confirmation page for new tabs and modified clicks.
            if (event.button !== 0 || event.ctrlKey || event.metaKey || event.shiftKey || event.altKey) return;
            event.preventDefault();
            toggle(true);
        });

        cancel.addEventListener('click', function () {
            if (!submitting) toggle(false);
        });

        approval.addEventListener('keydown', function (event) {
            if (event.key === 'Escape' && !form.hidden && !submitting) {
                event.preventDefault();
                toggle(false);
            }
        });

        form.addEventListener('submit', function (event) {
            if (form.hidden || submit.disabled || submitting) {
                event.preventDefault();
                return;
            }
            submitting = true;
            submit.disabled = true;
            cancel.disabled = true;
            form.setAttribute('aria-busy', 'true');
        });

        // A browser Back navigation may restore the form after a successful POST.
        var unavailable = submit.disabled;
        window.addEventListener('pageshow', function (event) {
            if (!event.persisted) return;
            submitting = false;
            submit.disabled = unavailable;
            cancel.disabled = false;
            form.removeAttribute('aria-busy');
            open.hidden = false;
            form.hidden = true;
        });
    });
}());
