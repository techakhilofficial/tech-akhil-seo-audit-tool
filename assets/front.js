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
})();
