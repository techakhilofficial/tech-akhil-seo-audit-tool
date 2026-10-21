/**
 * Tech Akhil SEO Audit Tool admin interactions.
 * Source file is intentionally readable and has no build step.
 */
(function () {
    'use strict';

    function copyText(text) {
        if (navigator.clipboard && window.isSecureContext) {
            return navigator.clipboard.writeText(text);
        }
        return new Promise(function (resolve, reject) {
            var field = document.createElement('textarea');
            field.value = text;
            field.setAttribute('readonly', 'readonly');
            field.style.position = 'fixed';
            field.style.opacity = '0';
            document.body.appendChild(field);
            field.select();
            try {
                var copied = document.execCommand('copy');
                document.body.removeChild(field);
                copied ? resolve() : reject(new Error('Copy command failed'));
            } catch (error) {
                document.body.removeChild(field);
                reject(error);
            }
        });
    }

    document.addEventListener('click', function (event) {
        var button = event.target.closest('[data-sact-copy]');
        if (!button) {
            return;
        }
        var targetId = button.getAttribute('data-sact-copy');
        var target = document.getElementById(targetId);
        var status = button.parentNode.querySelector('.sact-copy-status');
        if (!target) {
            return;
        }
        var value = target.value !== undefined ? target.value : target.textContent;
        copyText(value.trim()).then(function () {
            button.textContent = 'Copied!';
            if (status) {
                status.textContent = 'Shortcode copied to clipboard.';
            }
            window.setTimeout(function () {
                button.textContent = 'Copy shortcode';
            }, 1800);
        }).catch(function () {
            if (status) {
                status.textContent = 'Copy failed. Select the shortcode and copy it manually.';
            }
        });
    });
}());
