const enabledClasses = 'block w-full rounded-xl border border-transparent px-4 py-3 text-left text-sm font-medium text-slate-200 transition duration-200 ease-out hover:border-slate-700 hover:bg-slate-800/70 hover:text-white';
const activeClasses = 'block w-full rounded-xl border border-slate-700 bg-slate-800 px-4 py-3 text-left text-sm font-medium text-white shadow-sm transition duration-200 ease-out';
const disabledClasses = 'block w-full cursor-not-allowed rounded-xl border border-transparent px-4 py-3 text-left text-sm font-medium text-slate-500 opacity-70';
const hrefByEligibilityKey = {
    salesOrdersEnabled: '/sales/orders',
    purchaseOrdersEnabled: '/purchasing/orders',
    makeOrdersEnabled: '/manufacturing/make-orders',
};

function resolveHref(sourceElement) {
    const directHref = sourceElement.dataset.navHref || '';

    if (directHref) {
        return directHref;
    }

    return hrefByEligibilityKey[sourceElement.dataset.navEligibilityKey || ''] || '#';
}

function buildEnabledNode(sourceElement) {
    const anchor = document.createElement('a');
    const isActive = sourceElement.dataset.navActive === 'true';
    const href = resolveHref(sourceElement);

    anchor.href = href;
    anchor.className = isActive ? activeClasses : enabledClasses;
    anchor.textContent = sourceElement.dataset.navLabel || '';

    if (isActive) {
        anchor.setAttribute('aria-current', 'page');
    }

    anchor.dataset.navEligibilityKey = sourceElement.dataset.navEligibilityKey || '';
    anchor.dataset.navHref = href;
    anchor.dataset.navLabel = sourceElement.dataset.navLabel || '';
    anchor.dataset.navActive = sourceElement.dataset.navActive || 'false';

    return anchor;
}

function buildDisabledNode(sourceElement) {
    const span = document.createElement('span');

    span.className = disabledClasses;
    span.textContent = sourceElement.dataset.navLabel || '';
    span.dataset.navEligibilityKey = sourceElement.dataset.navEligibilityKey || '';
    span.dataset.navLabel = sourceElement.dataset.navLabel || '';
    span.dataset.navActive = sourceElement.dataset.navActive || 'false';

    return span;
}

function patchNavigationNodes(state) {
    document.querySelectorAll('[data-nav-eligibility-key]').forEach((element) => {
        const key = element.dataset.navEligibilityKey;

        if (!key || !(key in state)) {
            return;
        }

        const shouldEnable = Boolean(state[key]);
        const isEnabled = element.tagName === 'A';

        if (shouldEnable === isEnabled) {
            return;
        }

        element.replaceWith(shouldEnable ? buildEnabledNode(element) : buildDisabledNode(element));
    });
}

export async function refreshNavigationState(navigationStateUrl) {
    if (!navigationStateUrl) {
        return false;
    }

    const response = await fetch(navigationStateUrl, {
        headers: {
            Accept: 'application/json',
        },
    });

    if (!response.ok) {
        return false;
    }

    const state = await response.json();

    if (!state || typeof state !== 'object') {
        return false;
    }

    patchNavigationNodes(state);

    return true;
}
