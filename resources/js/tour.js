import Shepherd from 'shepherd.js';

/**
 * STS Vending guided setup tour.
 *
 * Anchored to the dashboard tiles + navbar links. Walks the user
 * through the 6-step setup that the API expects (supply group →
 * vending key → tariff → customer → meter → token), plus a final
 * step that calls out the Dart engine bridge config.
 *
 * Auto-starts on the dashboard the first time a visitor lands there,
 * unless localStorage[`stsTour:dismissed`] is `1`. The "Restart tour"
 * link in the navbar clears the flag and re-runs the tour.
 */
const STORAGE_KEY = 'stsTour:dismissed';

function buildTour() {
    const tour = new Shepherd.Tour({
        useModalOverlay: true,
        defaultStepOptions: {
            classes: 'shepherd-theme-sts',
            scrollTo: { behavior: 'smooth', block: 'center' },
            cancelIcon: { enabled: true },
            arrow: true,
        },
    });

    const back = {
        text: 'Back',
        secondary: true,
        action() { return this.back(); },
    };
    const next = {
        text: 'Next',
        action() { return this.next(); },
    };
    const done = {
        text: 'Got it',
        action() { return this.complete(); },
    };

    tour.addStep({
        id: 'welcome',
        title: 'Welcome to STS Vending',
        text: `
            <p>This quick tour shows the 6 things you need to set up
               before you can issue your first electricity token.</p>
            <p class="mt-2 text-xs text-gray-500">You can replay this
               any time from the <strong>Restart tour</strong> link in
               the top-right.</p>`,
        buttons: [
            {
                text: 'Skip',
                secondary: true,
                action() {
                    localStorage.setItem(STORAGE_KEY, '1');
                    return this.cancel();
                },
            },
            { text: "Let's go", action() { return this.next(); } },
        ],
    });

    tour.addStep({
        id: 'supply-groups',
        title: '1. Supply Group',
        text: `Start here. A <strong>supply group</strong> holds the
               SGC + utility / region grouping. Every meter belongs to
               one.`,
        attachTo: { element: '[data-tour="tile-supply-groups"]', on: 'bottom' },
        buttons: [back, next],
    });

    tour.addStep({
        id: 'vending-keys',
        title: '2. Vending Key',
        text: `Add at least one <strong>vending key</strong> (VUDK).
               It is the DES key the Dart engine uses to encrypt
               tokens. Must match the key in
               <code>nectar_sts_dart/.env</code>.`,
        attachTo: { element: '[data-tour="tile-vending-keys"]', on: 'bottom' },
        buttons: [back, next],
    });

    tour.addStep({
        id: 'tariffs',
        title: '3. Tariff',
        text: `Define your <strong>rate per kWh</strong> and the max
               power limits. Tariffs are referenced by meters when
               you issue tokens.`,
        attachTo: { element: '[data-tour="tile-tariffs"]', on: 'bottom' },
        buttons: [back, next],
    });

    tour.addStep({
        id: 'customers',
        title: '4. Customer',
        text: `Create the billing account that will own the meter.
               One customer can own many meters.`,
        attachTo: { element: '[data-tour="tile-customers"]', on: 'bottom' },
        buttons: [back, next],
    });

    tour.addStep({
        id: 'meters',
        title: '5. Meter',
        text: `Provision the STS meter itself. You'll need the
               <strong>IIN</strong> (6 digits) and <strong>IAIN</strong>
               (11 or 13 digits) printed on the device, plus the
               supply group, tariff, customer and vending key you
               just created.`,
        attachTo: { element: '[data-tour="tile-meters"]', on: 'bottom' },
        buttons: [back, next],
    });

    tour.addStep({
        id: 'tokens',
        title: '6. Issue a Token',
        text: `Pick the meter, enter the kWh, and the Dart engine on
               <code>STS_ENGINE_URL</code> mints a 20-digit STS token
               you can type into the meter.`,
        attachTo: { element: '[data-tour="tile-tokens"]', on: 'bottom' },
        buttons: [back, next],
    });

    tour.addStep({
        id: 'engine-bridge',
        title: 'Engine bridge',
        text: `Last thing — confirm this URL + bearer match what the
               Dart server prints on startup (<code>dart run
               bin/server.dart</code>). If they don't, token issuing
               will return 502.`,
        attachTo: { element: '[data-tour="engine-bridge"]', on: 'top' },
        buttons: [back, done],
    });

    tour.on('complete', () => localStorage.setItem(STORAGE_KEY, '1'));
    tour.on('cancel',   () => localStorage.setItem(STORAGE_KEY, '1'));

    return tour;
}

function elementExists(selector) {
    return Boolean(document.querySelector(selector));
}

export function startStsTour({ force = false } = {}) {
    // The tour only makes sense on the dashboard (that's where every
    // anchor lives). Bail silently elsewhere.
    if (!elementExists('[data-tour="tile-supply-groups"]')) return;

    if (!force && localStorage.getItem(STORAGE_KEY) === '1') return;

    // Clear the dismissed flag for a forced re-run so completing /
    // skipping it again re-sets the flag cleanly.
    if (force) localStorage.removeItem(STORAGE_KEY);

    buildTour().start();
}

// Auto-start on page load (first-visit case).
document.addEventListener('DOMContentLoaded', () => {
    startStsTour();

    // Wire the "Restart tour" link if present.
    document.querySelectorAll('[data-tour-trigger]').forEach((el) => {
        el.addEventListener('click', (event) => {
            event.preventDefault();
            // If we're not on the dashboard, send the user there with
            // ?tour=1 so the next page load re-runs the tour.
            if (!elementExists('[data-tour="tile-supply-groups"]')) {
                window.location.href = '/?tour=1';
                return;
            }
            startStsTour({ force: true });
        });
    });

    // Honor ?tour=1 in the URL (used by the restart link from
    // non-dashboard pages).
    const params = new URLSearchParams(window.location.search);
    if (params.get('tour') === '1') {
        startStsTour({ force: true });
    }
});
