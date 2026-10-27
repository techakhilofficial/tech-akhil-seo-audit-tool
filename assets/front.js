(function () {
    'use strict';

    function requestFreshNonce() {
        if (!window.taSactFront || !taSactFront.ajaxUrl || !window.fetch) {
            return Promise.reject(new Error('Nonce refresh is unavailable'));
        }

        var url = taSactFront.ajaxUrl + (taSactFront.ajaxUrl.indexOf('?') === -1 ? '?' : '&') + 'action=ta_sact_refresh_nonce&_=' + Date.now();
        return fetch(url, {
            method: 'GET',
            credentials: 'same-origin',
            cache: 'no-store',
            headers: { 'Accept': 'application/json' }
        }).then(function (response) {
            if (!response.ok) {
                throw new Error('Nonce refresh request failed');
            }
            return response.json();
        }).then(function (payload) {
            if (!payload || !payload.success || !payload.data || !payload.data.nonce) {
                throw new Error('Nonce refresh response was invalid');
            }
            return payload.data.nonce;
        });
    }

    function refreshVisibleForms() {
        var forms = document.querySelectorAll('form.ta-sact-form');
        if (!forms.length) {
            return;
        }
        requestFreshNonce().then(function (nonce) {
            forms.forEach(function (form) {
                var field = form.querySelector('input[name="ta_sact_nonce"]');
                if (field) {
                    field.value = nonce;
                }
            });
        }).catch(function () {
            // Leave the rendered nonce in place if the refresh endpoint is temporarily unavailable.
        });
    }

    // Refresh once more immediately before submission so a cached or back-restored page
    // does not submit a stale nonce. The hidden action field supports native form.submit().
    document.addEventListener('submit', function (event) {
        var form = event.target;
        if (!form || !form.matches || !form.matches('form.ta-sact-form') || form.dataset.sactNonceSubmitting === '1') {
            return;
        }
        var field = form.querySelector('input[name="ta_sact_nonce"]');
        if (!field) {
            return;
        }
        event.preventDefault();
        requestFreshNonce().then(function (nonce) {
            field.value = nonce;
        }).catch(function () {
            // Keep the existing token as a fallback if AJAX is unavailable.
        }).then(function () {
            form.dataset.sactNonceSubmitting = '1';
            form.submit();
        });
    }, true);

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', refreshVisibleForms);
    } else {
        refreshVisibleForms();
    }
    window.addEventListener('pageshow', refreshVisibleForms);

    // Report issue filters, page-card accordions, and print actions are registered from an enqueued file.
    function initReportInteractions() {
        document.querySelectorAll('[data-sact-print]').forEach(function (button) {
            button.addEventListener('click', function () { window.print(); });
        });
        document.querySelectorAll('.ta-sact-report').forEach(function (root) {
            var tabs = root.querySelectorAll('[data-sact-filter]');
            var items = root.querySelectorAll('[data-sact-severity]');
            tabs.forEach(function (tab) {
                tab.addEventListener('click', function () {
                    var severity = tab.getAttribute('data-sact-filter');
                    tabs.forEach(function (otherTab) {
                        var active = otherTab === tab;
                        otherTab.classList.toggle('is-active', active);
                        otherTab.setAttribute('aria-selected', active ? 'true' : 'false');
                    });
                    items.forEach(function (item) {
                        item.hidden = item.getAttribute('data-sact-severity') !== severity;
                        if (item.hidden) {
                            item.open = false;
                        }
                    });
                });
            });
            var initialTab = root.querySelector('[data-sact-filter="error"]');
            if (initialTab) {
                initialTab.click();
            }
        });

        document.querySelectorAll('.ta-sact-page-data-section').forEach(function (section) {
            var cards = section.querySelectorAll('.ta-sact-page-card');
            cards.forEach(function (card) {
                card.addEventListener('toggle', function () {
                    if (!card.open) {
                        return;
                    }
                    cards.forEach(function (otherCard) {
                        if (otherCard !== card) {
                            otherCard.open = false;
                        }
                    });
                });
            });
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initReportInteractions);
    } else {
        initReportInteractions();
    }
})();
