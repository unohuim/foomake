const escapeAttribute = (value) => String(value)
    .replaceAll('&', '&amp;')
    .replaceAll('<', '&lt;')
    .replaceAll('>', '&gt;')
    .replaceAll('"', '&quot;')
    .replaceAll("'", '&#039;');

const cleanExpression = (value, fallback = '') => (
    typeof value === 'string' && value.trim() !== '' ? value : fallback
);

/**
 * Render a local Alpine toggle control that emits row-aware toggle events.
 *
 * @param {object} options
 * @returns {string}
 */
export function renderToggle(options = {}) {
    const name = cleanExpression(options.name, 'toggle');
    const checkedExpression = cleanExpression(options.checkedExpression, 'false');
    const disabledExpression = cleanExpression(options.disabledExpression, 'false');
    const showExpression = cleanExpression(options.showExpression, 'true');
    const eventName = cleanExpression(options.eventName, 'ui-toggle:changed');
    const handler = cleanExpression(options.handler);
    const ariaLabelExpression = cleanExpression(options.ariaLabelExpression, `'Toggle ${name}'`);
    const handlerExpression = handler !== '' ? `; ${handler}` : '';

    return `
        <button
            type="button"
            role="switch"
            class="group relative inline-flex h-6 w-11 shrink-0 cursor-pointer items-center rounded-full transition-colors duration-200 ease-in-out focus:outline-none focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-lime-500 disabled:cursor-not-allowed disabled:opacity-50"
            x-show="${showExpression}"
            x-bind:aria-label='${ariaLabelExpression}'
            x-bind:aria-checked="${checkedExpression} ? 'true' : 'false'"
            x-bind:disabled="${disabledExpression}"
            x-bind:class="${checkedExpression} ? 'bg-lime-500' : 'bg-gray-200'"
            data-ui-toggle
            x-on:click.stop.prevent="const toggleDetail = { name: '${escapeAttribute(name)}', checked: !(${checkedExpression}), value: !(${checkedExpression}), row: record, record, id: record?.id ?? null }; $dispatch('${escapeAttribute(eventName)}', toggleDetail)${handlerExpression}"
        >
            <span class="sr-only" x-text='${ariaLabelExpression}'></span>
            <span
                aria-hidden="true"
                class="pointer-events-none inline-block h-5 w-5 rounded-full bg-white shadow transition duration-200 ease-in-out"
                x-bind:class="${checkedExpression} ? 'translate-x-5' : 'translate-x-0'"
            ></span>
        </button>
    `;
}
