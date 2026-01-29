import './bootstrap';

import Alpine from 'alpinejs';

window.Alpine = Alpine;

// Page module contract: export function mount(rootEl, payload)
const pageModules = import.meta.glob('./pages/*.js');
const nestedPageModules = import.meta.glob('./pages/**/*.js');
const availablePageModules = { ...pageModules, ...nestedPageModules };

(async () => {
    let alpineStarted = false;
    const startAlpineOnce = () => {
        if (alpineStarted) {
            return;
        }
        alpineStarted = true;
        Alpine.start();
    };

    const rootEl = document.querySelector('[data-page]');

    if (!rootEl) {
        startAlpineOnce();
        return;
    }

    const slug = rootEl.getAttribute('data-page');
    const payloadId = rootEl.getAttribute('data-payload');
    let payload = {};

    if (payloadId) {
        const payloadEl = document.getElementById(payloadId);

        if (payloadEl && payloadEl.tagName === 'SCRIPT' && payloadEl.type === 'application/json') {
            try {
                payload = JSON.parse(payloadEl.textContent || '{}');
            } catch (error) {
                payload = {};
            }
        }
    }

    const moduleKey = `./pages/${slug}.js`;
    const loader = availablePageModules[moduleKey];

    if (!loader) {
        if (import.meta.env?.DEV) {
            console.error(`[pages] Unable to load ${slug}.`);
        }
        startAlpineOnce();
        return;
    }

    try {
        const module = await loader();

        if (typeof module.mount !== 'function') {
            if (import.meta.env?.DEV) {
                console.error(`[pages] Missing mount() for ${slug}.`);
            }
            startAlpineOnce();
            return;
        }

        module.mount(rootEl, payload);
        startAlpineOnce();
    } catch (error) {
        if (import.meta.env?.DEV) {
            console.error(`[pages] Unable to load ${slug}.`, error);
        }
        startAlpineOnce();
    }
})();
