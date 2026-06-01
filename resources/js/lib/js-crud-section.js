const asRecord = (value) => (value && typeof value === 'object' && !Array.isArray(value) ? value : {});

const asArray = (value) => (Array.isArray(value) ? value : []);

const asString = (value, fallback = '') => (typeof value === 'string' && value.trim() !== '' ? value : fallback);

const asBoolean = (value) => Boolean(value);

const resolvePathValue = (source, path, fallback = '') => {
    if (!path) {
        return fallback;
    }

    return path.split('.').reduce((carry, key) => {
        if (carry && typeof carry === 'object' && key in carry) {
            return carry[key];
        }

        return undefined;
    }, source) ?? fallback;
};

const normalizeField = (field) => {
    const safeField = asRecord(field);
    const inlineCreate = asRecord(safeField.inlineCreate);

    return {
        name: asString(safeField.name),
        label: asString(safeField.label),
        type: asString(safeField.type, 'text'),
        required: Boolean(safeField.required),
        options: asArray(safeField.options).map((option) => ({
            value: asString(option?.value),
            label: asString(option?.label),
        })),
        rowGroup: asString(safeField.rowGroup),
        inlineCreate: {
            label: asString(inlineCreate.label, 'Create'),
            storeUrl: asString(inlineCreate.storeUrl),
            fields: asArray(inlineCreate.fields).map((createField) => ({
                name: asString(createField?.name),
                label: asString(createField?.label),
                type: asString(createField?.type, 'text'),
                required: Boolean(createField?.required),
            })).filter((createField) => createField.name !== ''),
        },
    };
};

const normalizeAction = (action) => {
    const safeAction = asRecord(action);

    return {
        id: asString(safeAction.id),
        label: asString(safeAction.label),
        ariaLabel: asString(safeAction.ariaLabel),
        type: asString(safeAction.type, asString(safeAction.id)),
        tone: asString(safeAction.tone, 'default'),
        icon: asString(safeAction.icon),
        urlField: asString(safeAction.urlField),
        endpointKey: asString(safeAction.endpointKey, 'remove'),
        method: asString(safeAction.method, 'DELETE').toUpperCase(),
        handlerKey: asString(safeAction.handlerKey),
    };
};

const normalizeLayoutEntry = (entry) => {
    const safeEntry = asRecord(entry);

    return {
        label: asString(safeEntry.label),
        field: asString(safeEntry.field),
        urlField: asString(safeEntry.urlField),
        suffixField: asString(safeEntry.suffixField),
        fallback: Object.prototype.hasOwnProperty.call(safeEntry, 'fallback')
            ? String(safeEntry.fallback ?? '')
            : '—',
        toneField: asString(safeEntry.toneField),
        strong: asBoolean(safeEntry.strong),
    };
};

const normalizeSectionConfig = (config) => {
    const safeConfig = asRecord(config);
    const endpoints = asRecord(safeConfig.endpoints);
    const permissions = asRecord(safeConfig.permissions);
    const rowLayout = asRecord(safeConfig.rowLayout);
    const createAction = asRecord(safeConfig.createAction);
    const createActionPrefill = asRecord(createAction.prefill);
    const addRow = asRecord(safeConfig.addRow);
    const addRowAction = asRecord(addRow.action);

    return {
        resource: asString(safeConfig.resource),
        title: asString(safeConfig.title, 'Section'),
        description: asString(safeConfig.description),
        emptyState: asString(safeConfig.emptyState, 'No records found.'),
        recordClass: asString(safeConfig.recordClass),
        csrfToken: asString(safeConfig.csrfToken),
        defaultOpen: asBoolean(safeConfig.defaultOpen),
        mobilePageSize: Number.isInteger(safeConfig.mobilePageSize) ? safeConfig.mobilePageSize : null,
        initialRecords: asArray(safeConfig.initialRecords || safeConfig.initial_records),
        showRowActionsMenu: safeConfig.showRowActionsMenu !== false,
        toolbarToggles: asArray(safeConfig.toolbarToggles).map((toggle) => ({
            key: asString(toggle?.key),
            label: asString(toggle?.label),
            checked: asBoolean(toggle?.checked),
        })).filter((toggle) => toggle.key !== ''),
        permissions: {
            canCreate: Boolean(permissions.canCreate),
        },
        createAction: {
            type: asString(createAction.type),
            url: asString(createAction.url),
            handlerKey: asString(createAction.handlerKey),
            title: asString(createAction.title),
            description: asString(createAction.description),
            submitLabel: asString(createAction.submitLabel),
            prefill: createActionPrefill,
        },
        addRow: {
            enabled: asBoolean(addRow.enabled),
            type: asString(addRow.type),
            fieldName: asString(addRow.fieldName),
            placeholder: asString(addRow.placeholder, 'Search'),
            noResultsText: asString(addRow.noResultsText, 'No items found.'),
            options: asArray(addRow.options).map((option) => ({
                value: asString(option?.value),
                label: asString(option?.label),
                description: asString(option?.description),
            })),
            action: {
                handlerKey: asString(addRowAction.handlerKey),
                ariaLabel: asString(addRowAction.ariaLabel),
            },
        },
        endpoints: {
            list: asString(endpoints.list),
            create: asString(endpoints.create),
            update: asString(endpoints.update),
            remove: asString(endpoints.remove),
        },
        fields: asArray(safeConfig.fields).map(normalizeField).filter((field) => field.name !== ''),
        actions: asArray(safeConfig.actions).map(normalizeAction).filter((action) => action.id !== ''),
        rowLayout: {
            primaryText: normalizeLayoutEntry(rowLayout.primaryText),
            secondaryFields: asArray(rowLayout.secondaryFields).map(normalizeLayoutEntry),
            badges: asArray(rowLayout.badges).map(normalizeLayoutEntry),
            rightMeta: asArray(rowLayout.rightMeta).map(normalizeLayoutEntry),
        },
    };
};

// Shared CRUD contract reference: x-for="field in section.fields"
const fieldMarkup = `
    <template x-for="(row, rowIndex) in fieldRows()" :key="\`field-row-\${rowIndex}\`">
        <div :class="rowClass(row)">
            <template x-for="field in row" :key="field.name">
                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wide text-gray-500" :for="\`section-field-\${field.name}\`" x-text="field.label"></label>

                    <template x-if="field.type === 'select'">
                        <select
                            class="mt-1 block w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500"
                            :id="\`section-field-\${field.name}\`"
                            x-model="form[field.name]"
                        >
                            <option value="">Select</option>
                            <template x-for="option in field.options" :key="\`\${field.name}-\${option.value}\`">
                                <option :value="option.value" x-text="option.label"></option>
                            </template>
                        </select>
                    </template>

                    <template x-if="field.type === 'combobox'">
                        <div
                            class="mt-1"
                            x-data="combobox({
                                name: field.name,
                                options: field.options,
                                selectedValue: form[field.name],
                                placeholder: \`Search \${field.label.toLowerCase()}\`,
                                noResultsText: \`No \${field.label.toLowerCase()} found.\`,
                                inputId: \`section-field-\${field.name}\`,
                                listId: \`section-field-\${field.name}-listbox\`,
                            })"
                            x-modelable="selectedValue"
                            x-model="form[field.name]"
                            x-on:click.outside="closeDropdown()"
                            x-on:keydown.arrow-down.prevent="highlightNext()"
                            x-on:keydown.arrow-up.prevent="highlightPrevious()"
                            x-on:keydown.enter.prevent="selectHighlighted()"
                            x-on:keydown.escape.prevent="closeDropdown()"
                            x-effect="configuredOptions = field.options"
                        >
                            <div class="relative">
                                <input
                                    type="text"
                                    role="combobox"
                                    autocomplete="off"
                                    class="block w-full rounded-xl border border-gray-300 bg-white px-4 py-3 pr-11 text-sm text-gray-900 shadow-sm transition focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20"
                                    :id="\`section-field-\${field.name}\`"
                                    :placeholder="placeholder"
                                    x-model="query"
                                    x-on:focus="openDropdown()"
                                    x-on:input="handleQueryInput($event.target.value)"
                                    x-bind:aria-expanded="open.toString()"
                                    x-bind:aria-controls="listId"
                                    x-bind:aria-activedescendant="activeDescendantId()"
                                />

                                <input type="hidden" :name="field.name" x-model="selectedValue" />

                                <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center pr-3 text-gray-400">
                                    <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                        <path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 0 1 1.06.02L10 11.168l3.71-3.938a.75.75 0 1 1 1.08 1.04l-4.25 4.5a.75.75 0 0 1-1.08 0l-4.25-4.5a.75.75 0 0 1 .02-1.06Z" clip-rule="evenodd" />
                                    </svg>
                                </div>

                                <div x-ref="slotOptions" class="hidden"></div>

                                <div
                                    class="absolute z-20 mt-2 max-h-72 w-full overflow-auto rounded-2xl border border-gray-200 bg-white p-2 shadow-xl ring-1 ring-black/5"
                                    x-cloak
                                    x-show="open"
                                    role="listbox"
                                    x-bind:id="listId"
                                >
                                    <div class="flex items-center justify-end border-b border-gray-100 px-1 pb-2" x-show="field.inlineCreate.storeUrl">
                                        <button
                                            type="button"
                                            class="text-sm text-blue-600 transition hover:text-blue-500"
                                            x-text="field.inlineCreate.label"
                                            x-on:click="openInlineCreate(field); closeDropdown()"
                                        ></button>
                                    </div>

                                    <template x-if="filteredOptions().length === 0">
                                        <div class="rounded-xl px-3 py-3 text-sm text-gray-500" x-text="noResultsText"></div>
                                    </template>

                                    <template x-for="(option, index) in filteredOptions()" :key="option.value">
                                        <button
                                            type="button"
                                            class="flex w-full items-start justify-between rounded-xl px-3 py-3 text-left transition"
                                            role="option"
                                            x-bind:id="optionDomId(index)"
                                            x-bind:aria-selected="isSelected(option).toString()"
                                            x-on:mouseenter="highlightedIndex = index"
                                            x-on:click="selectOption(option)"
                                            x-bind:class="highlightedIndex === index ? 'bg-blue-50 text-blue-900' : 'text-gray-900 hover:bg-gray-50'"
                                        >
                                            <span class="min-w-0">
                                                <span class="block truncate text-sm font-medium" x-text="option.label"></span>
                                                <span class="mt-1 block truncate text-xs text-gray-500" x-show="option.description" x-text="option.description"></span>
                                            </span>

                                            <span class="ml-3 text-blue-600" x-show="isSelected(option)">
                                                <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                                    <path fill-rule="evenodd" d="M16.704 5.29a1 1 0 0 1 .006 1.414l-8 8.07a1 1 0 0 1-1.42 0l-4-4.035a1 1 0 0 1 1.42-1.41l3.29 3.32 7.29-7.36a1 1 0 0 1 1.414 0Z" clip-rule="evenodd" />
                                                </svg>
                                            </span>
                                        </button>
                                    </template>
                                </div>
                            </div>
                        </div>
                    </template>

                    <template x-if="field.type !== 'select' && field.type !== 'combobox'">
                        <input
                            :type="['email', 'url', 'date', 'datetime-local'].includes(field.type) ? field.type : 'text'"
                            class="mt-1 block w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500"
                            :id="\`section-field-\${field.name}\`"
                            x-model="form[field.name]"
                        />
                    </template>

                    <p class="mt-1 text-xs text-red-600" x-text="firstError(field.name)"></p>

                    <div class="mt-3 space-y-3 rounded-xl border border-gray-200 bg-gray-50 p-4" x-show="inlineCreateFieldName === field.name" x-cloak>
                        <div class="flex items-center justify-between gap-3">
                            <p class="text-sm font-medium text-gray-900" x-text="field.inlineCreate.label"></p>
                            <button
                                type="button"
                                class="text-sm text-gray-500 transition hover:text-gray-700"
                                x-on:click="closeInlineCreate()"
                            >
                                Cancel
                            </button>
                        </div>

                        <template x-for="createField in field.inlineCreate.fields" :key="\`\${field.name}-inline-create-\${createField.name}\`">
                            <div>
                                <label
                                    class="block text-xs font-semibold uppercase tracking-wide text-gray-500"
                                    :for="\`section-inline-create-\${field.name}-\${createField.name}\`"
                                    x-text="createField.label"
                                ></label>
                                <input
                                    :id="\`section-inline-create-\${field.name}-\${createField.name}\`"
                                    :type="createField.type === 'email' || createField.type === 'url' ? createField.type : 'text'"
                                    class="mt-1 block w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500"
                                    x-model="inlineCreateForm[createField.name]"
                                />
                                <p class="mt-1 text-xs text-red-600" x-text="firstInlineCreateError(createField.name)"></p>
                            </div>
                        </template>

                        <p class="text-xs text-red-600" x-show="inlineCreateFormError" x-text="inlineCreateFormError"></p>

                        <div class="flex justify-end">
                            <button
                                type="button"
                                class="rounded-lg bg-blue-600 px-3 py-2 text-sm font-medium text-white transition hover:bg-blue-500 disabled:cursor-not-allowed disabled:opacity-50"
                                x-bind:disabled="inlineCreateSubmitting"
                                x-on:click="submitInlineCreate(field)"
                            >
                                Create Supplier
                            </button>
                        </div>
                    </div>
                </div>
            </template>
        </div>
    </template>
`;

const actionMenuMarkup = `
    <div
        class="relative inline-flex overflow-visible"
        x-data="{ open: false }"
        x-show="section.showRowActionsMenu && visibleActions(record).length > 0"
        x-on:keydown.escape.window="open = false"
        x-on:click.outside="open = false"
    >
        <button
            type="button"
            class="inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-md text-gray-500 transition hover:bg-gray-100 hover:text-gray-700"
            aria-label="Actions"
            aria-haspopup="menu"
            x-bind:aria-expanded="open ? 'true' : 'false'"
            x-on:click="open = !open"
        >
            <svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 6.75a.75.75 0 1 0 0-1.5.75.75 0 0 0 0 1.5Zm0 6a.75.75 0 1 0 0-1.5.75.75 0 0 0 0 1.5Zm0 6a.75.75 0 1 0 0-1.5.75.75 0 0 0 0 1.5Z" />
            </svg>
        </button>

        <div
            class="absolute right-0 z-30 mt-2 w-40 rounded-md border border-gray-200 bg-white py-1 shadow-lg"
            x-show="open"
            x-cloak
            role="menu"
        >
            <template x-for="action in visibleActions(record)" :key="\`\${record.id}-\${action.id}\`">
                <button
                    type="button"
                    class="flex w-full items-center px-3 py-2 text-left text-sm transition"
                    :class="action.tone === 'warning' ? 'text-yellow-700 hover:bg-yellow-50 hover:text-yellow-800' : 'text-gray-700 hover:bg-gray-50'"
                    x-text="actionLabel(record, action)"
                    x-on:click="open = false; performAction(record, action)"
                ></button>
            </template>
        </div>
    </div>
`;

const inlineActionsMarkup = `
    <div
        class="flex flex-wrap items-center justify-end gap-2 self-center"
        x-show="!section.showRowActionsMenu && visibleActions(record).length > 0"
    >
        <template x-for="action in visibleActions(record)" :key="\`\${record.id}-inline-\${action.id}\`">
            <template x-if="action.icon === 'x-mark'">
                <button
                    type="button"
                    class="inline-flex h-8 w-8 items-center justify-center rounded-full border border-slate-300 text-slate-500 transition hover:bg-slate-50 hover:text-slate-700"
                    x-bind:aria-label="action.ariaLabel || actionLabel(record, action)"
                    x-on:click="performAction(record, action)"
                >
                    <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                    </svg>
                </button>
            </template>
            <template x-if="action.icon !== 'x-mark'">
                <button
                    type="button"
                    class="inline-flex items-center rounded-lg border px-3 py-1.5 text-xs font-semibold uppercase tracking-widest transition"
                    :class="action.tone === 'warning'
                        ? 'border-yellow-300 text-yellow-700 hover:bg-yellow-50'
                        : 'border-slate-300 text-slate-700 hover:bg-slate-50'"
                    x-text="actionLabel(record, action)"
                    x-on:click="performAction(record, action)"
                ></button>
            </template>
        </template>
    </div>
`;

const renderCrudSection = () => `
    <section
        class="overflow-visible rounded-2xl border border-gray-200 bg-white shadow-sm"
        data-js-crud-section-card
        x-data="jsCrudSection($el)"
    >
        <div class="flex items-start justify-between gap-3 px-3 py-4 sm:px-6 sm:py-5">
            <div class="min-w-0 flex-1">
                <h3 class="text-lg font-semibold text-gray-900" x-text="section.title"></h3>
                <p class="mt-1 text-sm text-gray-500" x-text="section.description"></p>
            </div>
            <button
                type="button"
                class="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-lg border border-gray-300 text-gray-600 transition hover:bg-gray-50 hover:text-gray-900"
                aria-expanded="false"
                x-bind:aria-expanded="isOpen ? 'true' : 'false'"
                x-on:click="toggleOpen()"
                aria-label="Toggle section"
                data-js-crud-section-toggle
            >
                <svg class="h-5 w-5 text-gray-400 transition" :class="isOpen ? 'rotate-180' : ''" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5" />
                </svg>
            </button>
        </div>

        <div class="border-t border-gray-100 px-3 sm:px-6 py-4 sm:py-5" x-show="isOpen" x-cloak>
            <div class="mb-4 flex flex-col gap-3">
                <p class="text-sm text-red-600" x-show="sectionError" x-text="sectionError"></p>
                <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <div class="flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-center" x-show="section.toolbarToggles.length > 0">
                        <template x-for="toggle in section.toolbarToggles" :key="toggle.key">
                            <button
                                type="button"
                                class="flex items-center justify-between gap-4 text-left focus:outline-none focus-visible:ring-2 focus-visible:ring-slate-500 focus-visible:ring-offset-2 sm:min-w-52"
                                role="switch"
                                :aria-checked="toggleValues[toggle.key] ? 'true' : 'false'"
                                x-on:click="toggleToolbar(toggle.key)"
                            >
                                <span class="min-w-0 flex-1 text-sm font-medium leading-6 text-gray-900" x-text="toggle.label"></span>
                                <span
                                    class="inline-flex h-6 w-11 shrink-0 items-center rounded-full p-0.5 transition duration-200 ease-in-out"
                                    :class="toggleValues[toggle.key] ? 'bg-slate-900' : 'bg-slate-300'"
                                >
                                    <span
                                        class="inline-block h-5 w-5 rounded-full bg-white shadow-sm ring-1 ring-slate-900/10 transition duration-200 ease-in-out"
                                        :class="toggleValues[toggle.key] ? 'translate-x-5' : 'translate-x-0'"
                                    ></span>
                                </span>
                            </button>
                        </template>
                    </div>
                    <div class="flex w-full justify-end sm:ml-auto sm:w-auto" data-js-crud-section-create-wrapper>
                        <button
                            type="button"
                            class="inline-flex h-10 w-10 items-center justify-center rounded-lg border border-gray-300 text-gray-600 transition hover:bg-gray-50 hover:text-gray-900"
                            x-show="section.permissions.canCreate"
                            x-on:click.stop.prevent="openCreateForm()"
                            aria-label="Create"
                            data-js-crud-section-create-button
                        >
                            <svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                            </svg>
                        </button>
                    </div>
                </div>
            </div>

            <div
                class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between"
                x-show="section.addRow.enabled"
                data-detail-section-add-row
            >
                <div class="min-w-0 flex-1" data-detail-section-add-row-left>
                    <template x-if="section.addRow.type === 'combobox-add'">
                        <div
                            x-data="combobox({
                                name: section.addRow.fieldName,
                                options: section.addRow.options,
                                selectedValue: addRowValue,
                                placeholder: section.addRow.placeholder,
                                noResultsText: section.addRow.noResultsText,
                                inputId: \`section-add-row-\${section.resource}-input\`,
                                listId: \`section-add-row-\${section.resource}-listbox\`,
                            })"
                            x-modelable="selectedValue"
                            x-model="addRowValue"
                            x-effect="configuredOptions = section.addRow.options"
                            x-on:click.outside="closeDropdown()"
                            x-on:keydown.arrow-down.prevent="highlightNext()"
                            x-on:keydown.arrow-up.prevent="highlightPrevious()"
                            x-on:keydown.enter.prevent="selectHighlighted()"
                            x-on:keydown.escape.prevent="closeDropdown()"
                            data-inventory-count-material-add-combobox
                        >
                            <div class="relative mt-1">
                                <input
                                    :id="\`section-add-row-\${section.resource}-input\`"
                                    type="text"
                                    role="combobox"
                                    autocomplete="off"
                                    class="block w-full rounded-xl border border-gray-300 bg-white px-4 py-3 pr-11 text-sm text-gray-900 shadow-sm transition focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20"
                                    :placeholder="section.addRow.placeholder"
                                    x-model="query"
                                    x-on:focus="openDropdown()"
                                    x-on:input="handleQueryInput($event.target.value)"
                                    x-bind:aria-expanded="open.toString()"
                                    x-bind:aria-controls="listId"
                                    x-bind:aria-activedescendant="activeDescendantId()"
                                />

                                <input type="hidden" x-model="selectedValue" />

                                <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center pr-3 text-gray-400">
                                    <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                        <path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 0 1 1.06.02L10 11.168l3.71-3.938a.75.75 0 1 1 1.08 1.04l-4.25 4.5a.75.75 0 0 1-1.08 0l-4.25-4.5a.75.75 0 0 1 .02-1.06Z" clip-rule="evenodd" />
                                    </svg>
                                </div>

                                <div
                                    class="absolute z-20 mt-2 max-h-72 w-full overflow-auto rounded-2xl border border-gray-200 bg-white p-2 shadow-xl ring-1 ring-black/5"
                                    x-cloak
                                    x-show="open"
                                    role="listbox"
                                    x-bind:id="listId"
                                >
                                    <template x-if="filteredOptions().length === 0">
                                        <div class="rounded-xl px-3 py-3 text-sm text-gray-500" x-text="section.addRow.noResultsText"></div>
                                    </template>

                                    <template x-for="(option, index) in filteredOptions()" :key="option.value">
                                        <button
                                            type="button"
                                            class="flex w-full items-start justify-between rounded-xl px-3 py-3 text-left transition"
                                            role="option"
                                            x-bind:id="optionDomId(index)"
                                            x-bind:aria-selected="isSelected(option).toString()"
                                            x-on:mouseenter="highlightedIndex = index"
                                            x-on:click="selectOption(option)"
                                            x-bind:class="highlightedIndex === index ? 'bg-blue-50 text-blue-900' : 'text-gray-900 hover:bg-gray-50'"
                                        >
                                            <span class="min-w-0">
                                                <span class="block truncate text-sm font-medium" x-text="option.label"></span>
                                                <span class="mt-1 block truncate text-xs text-gray-500" x-show="option.description" x-text="option.description"></span>
                                            </span>

                                            <span class="ml-3 text-blue-600" x-show="isSelected(option)">
                                                <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                                    <path fill-rule="evenodd" d="M16.704 5.29a1 1 0 0 1 .006 1.414l-8 8.07a1 1 0 0 1-1.42 0l-4-4.035a1 1 0 0 1 1.42-1.41l3.29 3.32 7.29-7.36a1 1 0 0 1 1.414 0Z" clip-rule="evenodd" />
                                                </svg>
                                            </span>
                                        </button>
                                    </template>
                                </div>
                            </div>
                        </div>
                    </template>
                </div>

                <div class="flex justify-end sm:shrink-0" data-detail-section-add-row-right>
                    <button
                        type="button"
                        class="inline-flex h-10 w-10 items-center justify-center rounded-lg border border-gray-300 text-gray-700 transition hover:bg-gray-50 disabled:cursor-not-allowed disabled:opacity-50"
                        x-bind:disabled="addRowValue === '' || addRowSubmitting"
                        x-bind:aria-label="section.addRow.action.ariaLabel || 'Add record'"
                        x-on:click.stop.prevent="submitAddRow()"
                    >
                        <svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                        </svg>
                    </button>
                </div>
            </div>

            <div class="space-y-3" x-show="records.length > 0">
                <template x-for="record in records" :key="record.id">
                    <article :class="recordClass(record)">
                        <div class="flex flex-col sm:flex-row gap-4 sm:items-center sm:justify-between">
                            <div class="min-w-0 flex-1">
                                <div class="flex items-center gap-3">
                                    <template x-if="primaryTextUrl(record) !== ''">
                                        <a
                                            class="truncate text-sm font-semibold text-blue-700 transition hover:text-blue-600 hover:underline"
                                            x-bind:href="primaryTextUrl(record)"
                                            x-text="primaryText(record)"
                                        ></a>
                                    </template>
                                    <template x-if="primaryTextUrl(record) === ''">
                                        <p class="truncate text-sm font-semibold text-gray-900" x-text="primaryText(record)"></p>
                                    </template>
                                    <template x-for="badge in badgeItems(record)" :key="\`\${record.id}-\${badge.text}-badge\`">
                                        <span
                                            class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-medium"
                                            :class="badge.toneClass"
                                            x-text="badge.text"
                                        ></span>
                                    </template>
                                </div>
                                <div class="mt-1 flex flex-wrap items-center gap-4">
                                    <template x-for="line in secondaryFieldItems(record)" :key="\`\${record.id}-\${line.label}-secondary\`">
                                        <p class="text-sm text-gray-600">
                                            <template x-if="line.label">
                                                <span class="text-gray-500" x-text="\`\${line.label}: \`"></span>
                                            </template>
                                            <span class="text-gray-700" x-text="line.text"></span>
                                        </p>
                                    </template>
                                </div>
                            </div>

                            <div class="flex items-center justify-end gap-3 self-center">
                                <div class="text-left sm:text-right">
                                    <template x-for="meta in rightMetaItems(record)" :key="\`\${record.id}-\${meta.label}-meta\`">
                                        <div>
                                            <template x-if="meta.type === 'input'">
                                                <label class="flex items-center gap-2.5 text-sm">
                                                    <template x-if="meta.showSuccessIcon">
                                                        <svg class="h-5 w-5 shrink-0 text-emerald-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                                                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75" />
                                                            <path stroke-linecap="round" stroke-linejoin="round" d="M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                                                        </svg>
                                                    </template>
                                                    <template x-if="meta.label">
                                                        <span class="text-gray-500" x-text="meta.labelBare ? meta.label : \`\${meta.label}: \`"></span>
                                                    </template>
                                                    <input
                                                        type="text"
                                                        class="w-24 rounded-lg border border-gray-300 px-3 py-1.5 text-right text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500"
                                                        x-model="record[meta.field]"
                                                        x-on:change="performInlineMetaAction(record, meta)"
                                                        x-on:blur="performInlineMetaAction(record, meta)"
                                                    />
                                                </label>
                                            </template>
                                            <template x-if="meta.type !== 'input'">
                                                <p class="text-sm" :class="meta.strong ? 'font-semibold text-gray-900' : 'text-gray-600'">
                                                    <template x-if="meta.label">
                                                        <span class="text-gray-500" x-text="meta.labelBare ? meta.label : \`\${meta.label}: \`"></span>
                                                    </template>
                                                    <span x-text="meta.text"></span>
                                                </p>
                                            </template>
                                        </div>
                                    </template>
                                </div>
                                ${inlineActionsMarkup}
                                ${actionMenuMarkup}
                            </div>
                        </div>
                    </article>
                </template>
            </div>

            <div
                class="rounded-xl border border-dashed border-gray-300 px-4 py-8 text-center text-sm text-gray-500"
                x-show="!isLoading && records.length === 0"
                x-text="section.emptyState"
            ></div>

            <div class="mt-4 flex items-center justify-between" x-show="meta.last_page > 1">
                <button
                    type="button"
                    class="rounded-lg border border-gray-300 px-3 py-2 text-sm text-gray-700 transition hover:bg-gray-50 disabled:cursor-not-allowed disabled:opacity-50"
                    x-on:click="fetchPage(meta.current_page - 1)"
                    x-bind:disabled="meta.current_page <= 1 || isLoading"
                >
                    Previous
                </button>
                <p class="text-sm text-gray-500">
                    Page <span x-text="meta.current_page"></span> of <span x-text="meta.last_page"></span>
                </p>
                <button
                    type="button"
                    class="rounded-lg border border-gray-300 px-3 py-2 text-sm text-gray-700 transition hover:bg-gray-50 disabled:cursor-not-allowed disabled:opacity-50"
                    x-on:click="fetchPage(meta.current_page + 1)"
                    x-bind:disabled="meta.current_page >= meta.last_page || isLoading"
                >
                    Next
                </button>
            </div>
        </div>

        <div
            class="fixed inset-0 z-40 flex justify-end bg-gray-900/30"
            x-show="isFormOpen"
            x-cloak
        >
            <div class="flex h-full w-full max-w-xl flex-col overflow-y-auto bg-white shadow-xl">
                <div class="flex items-center justify-between border-b border-gray-100 px-4 py-4 sm:px-6">
                    <div>
                        <h4 class="text-lg font-semibold text-gray-900" x-text="createFormTitle()"></h4>
                        <p class="mt-1 text-sm text-gray-500" x-show="createFormDescription() !== ''" x-text="createFormDescription()"></p>
                    </div>
                    <button type="button" class="text-sm text-gray-500 transition hover:text-gray-700" x-on:click="closeForm()">Close</button>
                </div>

                <div class="flex-1 space-y-5 px-4 py-5 sm:px-6">
                    <p class="text-sm text-red-600" x-show="formError" x-text="formError"></p>
                    ${fieldMarkup}
                </div>

                <div class="flex items-center justify-end gap-3 border-t border-gray-100 px-4 py-4 sm:px-6">
                    <button type="button" class="rounded-lg border border-gray-300 px-4 py-2 text-sm text-gray-700 transition hover:bg-gray-50" x-on:click="closeForm()">Cancel</button>
                    <button
                        type="button"
                        class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white transition hover:bg-blue-500 disabled:cursor-not-allowed disabled:opacity-50"
                        x-bind:disabled="isSubmitting"
                        x-on:click="submitForm()"
                        x-text="createFormSubmitLabel()"
                    ></button>
                </div>
            </div>
        </div>

        <div
            class="fixed inset-0 z-50 flex items-center justify-center bg-gray-900/40 p-4"
            x-show="missingConversionModal.open"
            x-cloak
            data-missing-conversion-modal
        >
            <div class="w-full max-w-md rounded-xl bg-white shadow-xl">
                <div class="border-b border-gray-100 px-5 py-4">
                    <h4 class="text-base font-semibold text-gray-900">Create unit conversion</h4>
                    <p class="mt-1 text-sm text-gray-500">
                        <span x-text="missingConversionModal.itemName"></span>
                        <span x-show="missingConversionModal.fromUomLabel !== '' && missingConversionModal.toUomLabel !== ''">
                            needs a conversion from
                            <span class="font-medium text-gray-700" x-text="missingConversionModal.fromUomLabel"></span>
                            to
                            <span class="font-medium text-gray-700" x-text="missingConversionModal.toUomLabel"></span>.
                        </span>
                    </p>
                </div>
                <div class="space-y-4 px-5 py-4">
                    <p class="text-sm text-red-600" x-show="missingConversionModal.error" x-text="missingConversionModal.error"></p>
                    <div>
                        <label class="block text-xs font-semibold uppercase tracking-wide text-gray-500" for="missing-conversion-factor">Conversion factor</label>
                        <input
                            id="missing-conversion-factor"
                            type="text"
                            inputmode="decimal"
                            class="mt-1 block w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500"
                            x-model="missingConversionModal.form.conversion_factor"
                        />
                        <p class="mt-1 text-xs text-red-600" x-text="missingConversionError('conversion_factor')"></p>
                    </div>
                    <p class="text-xs text-gray-500">Direction: package UOM to item base UOM.</p>
                </div>
                <div class="flex items-center justify-end gap-3 border-t border-gray-100 px-5 py-4">
                    <button type="button" class="rounded-lg border border-gray-300 px-4 py-2 text-sm text-gray-700 transition hover:bg-gray-50" x-on:click="closeMissingConversionModal()">Cancel</button>
                    <button
                        type="button"
                        class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white transition hover:bg-blue-500 disabled:cursor-not-allowed disabled:opacity-50"
                        x-bind:disabled="missingConversionModal.submitting"
                        x-on:click="submitMissingConversion()"
                    >Create conversion</button>
                </div>
            </div>
        </div>
    </section>
`;

const buildEmptyForm = (section) => section.fields.reduce((carry, field) => {
    carry[field.name] = '';

    return carry;
}, {});

const resolveUrl = (template, id) => asString(template).replace('{id}', encodeURIComponent(String(id)));

const defaultToneClass = (tone) => ({
    success: 'bg-emerald-100 text-emerald-700',
    warning: 'bg-yellow-100 text-yellow-700',
    danger: 'bg-red-100 text-red-700',
    muted: 'bg-gray-200 text-gray-700',
    default: 'bg-blue-100 text-blue-700',
}[tone] || 'bg-blue-100 text-blue-700');

const buildLayoutText = (record, entry) => {
    const base = resolvePathValue(record, entry.field, '');
    const suffix = resolvePathValue(record, entry.suffixField, '');
    const parts = [base, suffix].filter((part) => part !== null && part !== undefined && String(part) !== '');

    if (parts.length === 0) {
        return entry.fallback;
    }

    return parts.join(' ');
};

const createSectionState = (section, adapters, hostEl) => ({
    section,
    adapters,
    isOpen: asBoolean(section.defaultOpen),
    hasLoaded: false,
    isLoading: false,
    isFormOpen: false,
    isSubmitting: false,
    formMode: 'create',
    editingId: null,
    records: asArray(section.initialRecords).map((record) => asRecord(record)),
    meta: {
        current_page: 1,
        last_page: 1,
        per_page: 10,
        total: asArray(section.initialRecords).length,
    },
    form: buildEmptyForm(section),
    errors: {},
    sectionError: '',
    formError: '',
    missingConversionModal: {
        open: false,
        itemName: '',
        fromUomLabel: '',
        toUomLabel: '',
        endpoint: '',
        retryAfterSuccess: false,
        originalPayload: {},
        form: {
            item_id: '',
            from_uom_id: '',
            to_uom_id: '',
            conversion_factor: '',
        },
        errors: {},
        error: '',
        submitting: false,
    },
    inlineCreateFieldName: '',
    inlineCreateForm: {},
    inlineCreateErrors: {},
    inlineCreateFormError: '',
    inlineCreateSubmitting: false,
    addRowValue: '',
    addRowSubmitting: false,
    toggleValues: section.toolbarToggles.reduce((carry, toggle) => {
        carry[toggle.key] = Boolean(toggle.checked);

        return carry;
    }, {}),
    init() {
        const rootEl = hostEl?.closest('[data-js-crud-section-root]');

        if (rootEl) {
            rootEl._jsCrudSectionApi = {
                refresh: async (page = 1) => {
                    await this.fetchPage(page);
                },
                updateSectionConfig: (nextSection) => {
                    this.updateSectionConfig(nextSection);
                },
            };
        }

        if (this.records.length > 0) {
            this.hasLoaded = true;
        }

        if (this.isOpen && !this.hasLoaded) {
            this.fetchPage(1);
        }
    },
    async toggleOpen() {
        const nextOpen = !this.isOpen;

        this.isOpen = nextOpen;

        if (nextOpen && !this.hasLoaded) {
            await this.fetchPage(1);
        }
    },
    normalizeRow(record) {
        const adapter = this.adapters.normalizeRow;

        if (typeof adapter === 'function') {
            return adapter(record) || record;
        }

        return record;
    },
    primaryText(record) {
        return buildLayoutText(record, this.section.rowLayout.primaryText);
    },
    primaryTextUrl(record) {
        return asString(resolvePathValue(record, this.section.rowLayout.primaryText.urlField));
    },
    secondaryFieldItems(record) {
        return this.section.rowLayout.secondaryFields.map((entry) => ({
            label: entry.label,
            text: buildLayoutText(record, entry),
        })).filter((entry) => entry.text !== '');
    },
    badgeItems(record) {
        return this.section.rowLayout.badges
            .map((entry) => ({
                text: buildLayoutText(record, entry),
                toneClass: defaultToneClass(asString(resolvePathValue(record, entry.toneField, 'muted'))),
            }))
            .filter((badge) => badge.text !== '—' && badge.text !== '');
    },
    rightMetaItems(record) {
        if (typeof this.adapters.rightMetaItems === 'function') {
            return asArray(this.adapters.rightMetaItems({
                record,
                component: this,
                section: this.section,
            }));
        }

        return this.section.rowLayout.rightMeta.map((entry) => ({
            label: entry.label,
            text: buildLayoutText(record, entry),
            strong: entry.strong,
        }));
    },
    visibleActions(record) {
        const hasExplicitAvailableActions = Array.isArray(record.availableActions) || Array.isArray(record.available_actions);
        const availableActions = asArray(record.availableActions || record.available_actions);
        const canComplete = Boolean(record.canComplete || record.can_complete);

        if (!hasExplicitAvailableActions && availableActions.length === 0) {
            return this.section.actions.filter((action) => action.id !== 'complete' || canComplete);
        }

        return this.section.actions.filter((action) => (
            availableActions.includes(action.id)
            || (action.id === 'complete' && canComplete)
        ));
    },
    actionLabel(record, action) {
        const labels = asRecord(record.actionLabels || record.action_labels);

        return labels[action.id] || action.label;
    },
    firstError(fieldName) {
        const values = this.errors[fieldName];

        if (!Array.isArray(values) || values.length === 0) {
            return '';
        }

        return values[0];
    },
    missingConversionError(fieldName) {
        const values = this.missingConversionModal.errors[fieldName];

        if (!Array.isArray(values) || values.length === 0) {
            return '';
        }

        return values[0];
    },
    openMissingConversionModal(meta, originalPayload = {}) {
        const item = asRecord(meta.item);
        const fromUom = asRecord(meta.from_uom);
        const toUom = asRecord(meta.to_uom);

        this.missingConversionModal = {
            open: true,
            itemName: asString(item.name, 'Selected material'),
            fromUomLabel: asString(fromUom.symbol, asString(fromUom.name)),
            toUomLabel: asString(toUom.symbol, asString(toUom.name)),
            endpoint: asString(meta.conversion_create_url),
            retryAfterSuccess: true,
            originalPayload,
            form: {
                item_id: item.id === null || item.id === undefined ? '' : String(item.id),
                from_uom_id: fromUom.id === null || fromUom.id === undefined ? '' : String(fromUom.id),
                to_uom_id: toUom.id === null || toUom.id === undefined ? '' : String(toUom.id),
                conversion_factor: '',
            },
            errors: {},
            error: '',
            submitting: false,
        };
    },
    closeMissingConversionModal() {
        this.missingConversionModal = {
            ...this.missingConversionModal,
            open: false,
            errors: {},
            error: '',
            submitting: false,
        };
    },
    async submitMissingConversion() {
        if (!this.missingConversionModal.endpoint || this.missingConversionModal.submitting) {
            return;
        }

        this.missingConversionModal.submitting = true;
        this.missingConversionModal.errors = {};
        this.missingConversionModal.error = '';

        try {
            const response = await fetch(this.missingConversionModal.endpoint, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': this.section.csrfToken,
                },
                body: JSON.stringify(this.missingConversionModal.form),
            });
            const data = await response.json().catch(() => ({}));

            if (response.status === 422) {
                this.missingConversionModal.errors = asRecord(data.errors);
                this.missingConversionModal.error = asString(data.message, 'Unable to create conversion.');
                return;
            }

            if (!response.ok) {
                this.missingConversionModal.error = asString(data.message, 'Unable to create conversion.');
                return;
            }

            this.closeMissingConversionModal();
            this.errors = {};
            this.formError = '';

            await this.submitForm();
        } catch (error) {
            this.missingConversionModal.error = 'Unable to create conversion.';
        } finally {
            this.missingConversionModal.submitting = false;
        }
    },
    firstInlineCreateError(fieldName) {
        const values = this.inlineCreateErrors[fieldName];

        if (!Array.isArray(values) || values.length === 0) {
            return '';
        }

        return values[0];
    },
    resetInlineCreateState() {
        this.inlineCreateFieldName = '';
        this.inlineCreateForm = {};
        this.inlineCreateErrors = {};
        this.inlineCreateFormError = '';
        this.inlineCreateSubmitting = false;
    },
    openInlineCreate(field) {
        this.inlineCreateFieldName = field.name;
        this.inlineCreateForm = field.inlineCreate.fields.reduce((carry, createField) => {
            carry[createField.name] = '';

            return carry;
        }, {});
        this.inlineCreateErrors = {};
        this.inlineCreateFormError = '';
        this.inlineCreateSubmitting = false;
    },
    closeInlineCreate() {
        this.resetInlineCreateState();
    },
    async submitAddRow() {
        if (!this.section.addRow.enabled || this.addRowSubmitting || this.addRowValue === '') {
            return;
        }

        if (typeof this.adapters.handleAddRow !== 'function') {
            return;
        }

        this.addRowSubmitting = true;
        this.sectionError = '';

        try {
            await this.adapters.handleAddRow({
                action: this.section.addRow.action,
                selectedValue: this.addRowValue,
                component: this,
                section: this.section,
            });
        } finally {
            this.addRowSubmitting = false;
        }
    },
    fieldRows() {
        const rows = [];
        const consumedIndexes = new Set();

        this.section.fields.forEach((field, index) => {
            if (consumedIndexes.has(index)) {
                return;
            }

            if (field.rowGroup !== '') {
                const row = [field];

                this.section.fields.forEach((candidateField, candidateIndex) => {
                    if (candidateIndex <= index) {
                        return;
                    }

                    if (candidateField.rowGroup !== field.rowGroup) {
                        return;
                    }

                    row.push(candidateField);
                    consumedIndexes.add(candidateIndex);
                });

                rows.push(row);
                return;
            }

            rows.push([field]);
        });

        return rows;
    },
    rowClass(row) {
        return row.length > 1 ? 'grid grid-cols-1 gap-4 sm:grid-cols-2' : '';
    },
    recordClass(record) {
        const baseClass = asString(this.section.recordClass, 'rounded-xl border border-gray-100 bg-gray-50 p-3 sm:p-4');
        const adapterClass = typeof this.adapters.recordClass === 'function'
            ? asString(this.adapters.recordClass({
                record,
                component: this,
                section: this.section,
            }))
            : '';
        const recordLevelClass = asString(record.rowClass);

        return [baseClass, adapterClass, recordLevelClass]
            .filter((value) => value !== '')
            .join(' ');
    },
    updateSectionConfig(nextSection) {
        if (!nextSection || typeof nextSection !== 'object') {
            return;
        }

        const normalizedSection = normalizeSectionConfig(nextSection);
        const currentToggleValues = asRecord(this.toggleValues);

        this.section = normalizedSection;
        this.toggleValues = normalizedSection.toolbarToggles.reduce((carry, toggle) => {
            carry[toggle.key] = Object.prototype.hasOwnProperty.call(currentToggleValues, toggle.key)
                ? Boolean(currentToggleValues[toggle.key])
                : Boolean(toggle.checked);

            return carry;
        }, {});

        if (!this.section.permissions.canCreate && this.isFormOpen && this.formMode === 'create') {
            this.closeForm();
        }
    },
    findField(fieldName) {
        return this.section.fields.find((field) => field.name === fieldName) || null;
    },
    async submitInlineCreate(field) {
        if (!field.inlineCreate.storeUrl || this.inlineCreateSubmitting) {
            return;
        }

        this.inlineCreateSubmitting = true;
        this.inlineCreateErrors = {};
        this.inlineCreateFormError = '';

        try {
            const response = await fetch(field.inlineCreate.storeUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': this.section.csrfToken,
                },
                body: JSON.stringify(this.inlineCreateForm),
            });

            if (response.status === 422) {
                const data = await response.json();
                this.inlineCreateErrors = asRecord(data.errors);
                this.inlineCreateFormError = asString(data.message, 'Unable to create record.');
                return;
            }

            if (!response.ok) {
                this.inlineCreateFormError = 'Unable to create record.';
                return;
            }

            const data = await response.json();
            const createdId = data.data?.id;
            const createdName = asString(data.data?.company_name);

            if ((createdId === null || createdId === undefined) || createdName === '') {
                this.inlineCreateFormError = 'Unable to create record.';
                return;
            }

            const targetField = this.findField(field.name);

            if (!targetField) {
                this.inlineCreateFormError = 'Unable to create record.';
                return;
            }

            const optionValue = String(createdId);
            const existingOption = targetField.options.find((option) => option.value === optionValue);

            if (!existingOption) {
                targetField.options.push({
                    value: optionValue,
                    label: createdName,
                });
            }

            this.form[field.name] = optionValue;
            this.resetInlineCreateState();
        } catch (error) {
            this.inlineCreateFormError = 'Unable to create record.';
        } finally {
            this.inlineCreateSubmitting = false;
        }
    },
    async fetchPage(page) {
        if (!this.section.endpoints.list) {
            return;
        }

        const params = new URLSearchParams();
        params.set('page', String(page));
        const mobilePageSize = Number.isInteger(this.section.mobilePageSize) ? this.section.mobilePageSize : null;

        if (
            mobilePageSize !== null
            && mobilePageSize > 0
            && typeof globalThis.matchMedia === 'function'
            && globalThis.matchMedia('(max-width: 639px)').matches
        ) {
            params.set('per_page', String(mobilePageSize));
        }

        if (typeof this.adapters.buildListParams === 'function') {
            const adapterParams = this.adapters.buildListParams(this.toggleValues, this.section);

            Object.entries(asRecord(adapterParams)).forEach(([key, value]) => {
                if (value === null || value === undefined || value === '') {
                    return;
                }

                params.set(key, String(value));
            });
        }
        this.isLoading = true;
        this.sectionError = '';

        try {
            const response = await fetch(`${this.section.endpoints.list}?${params.toString()}`, {
                headers: {
                    Accept: 'application/json',
                },
            });

            if (!response.ok) {
                this.sectionError = 'Unable to load records.';
                return;
            }

            const data = await response.json();
            this.records = asArray(data.data).map((record) => this.normalizeRow(record));
            this.meta = {
                current_page: data.meta?.current_page || 1,
                last_page: data.meta?.last_page || 1,
                per_page: data.meta?.per_page || 10,
                total: data.meta?.total || 0,
            };
            this.hasLoaded = true;
        } catch (error) {
            this.sectionError = 'Unable to load records.';
        } finally {
            this.isLoading = false;
        }
    },
    openCreateForm() {
        if (!this.section.permissions.canCreate) {
            return;
        }

        if (this.section.createAction.type === 'view' && this.section.createAction.url !== '') {
            globalThis.location.assign(this.section.createAction.url);
            return;
        }

        if (this.section.createAction.type === 'custom' && typeof this.adapters.handleCreateAction === 'function') {
            this.adapters.handleCreateAction({
                action: this.section.createAction,
                section: this.section,
                component: this,
            });
            return;
        }

        this.formMode = 'create';
        this.editingId = null;
        this.form = buildEmptyForm(this.section);
        this.errors = {};
        this.formError = '';
        this.resetInlineCreateState();
        this.isFormOpen = true;
    },
    async toggleToolbar(key, checked = null) {
        this.toggleValues[key] = checked === null
            ? !this.toggleValues[key]
            : Boolean(checked);
        await this.fetchPage(1);
    },
    openEditForm(record) {
        this.formMode = 'edit';
        this.editingId = record.id;
        const formValues = asRecord(record.formValues || record.form_values || record.raw || record);

        this.form = buildEmptyForm(this.section);
        this.section.fields.forEach((field) => {
            const value = formValues[field.name];
            this.form[field.name] = value === null || value === undefined ? '' : String(value);
        });
        this.errors = {};
        this.formError = '';
        this.resetInlineCreateState();
        this.isFormOpen = true;
    },
    closeForm() {
        this.isFormOpen = false;
        this.isSubmitting = false;
        this.errors = {};
        this.formError = '';
        this.resetInlineCreateState();
    },
    createFormTitle() {
        if (this.formMode === 'create') {
            return asString(this.section.createAction.title, 'Create record');
        }

        return 'Edit record';
    },
    createFormDescription() {
        if (this.formMode === 'create') {
            return asString(this.section.createAction.description, this.section.title);
        }

        return asString(this.section.title);
    },
    createFormSubmitLabel() {
        if (this.formMode === 'create') {
            return asString(this.section.createAction.submitLabel, 'Save');
        }

        return 'Save';
    },
    buildCreatePayload() {
        if (typeof this.adapters.buildCreatePayload === 'function') {
            return this.adapters.buildCreatePayload(this.form, this.section);
        }

        return this.form;
    },
    buildUpdatePayload(record) {
        if (typeof this.adapters.buildUpdatePayload === 'function') {
            return this.adapters.buildUpdatePayload(this.form, record, this.section);
        }

        return this.form;
    },
    async submitForm() {
        const record = this.records.find((entry) => entry.id === this.editingId) || null;
        const endpoint = this.formMode === 'create'
            ? this.section.endpoints.create
            : resolveUrl(this.section.endpoints.update, this.editingId);
        const method = this.formMode === 'create' ? 'POST' : 'PATCH';
        const body = this.formMode === 'create'
            ? this.buildCreatePayload()
            : this.buildUpdatePayload(record);

        if (!endpoint) {
            return;
        }

        this.isSubmitting = true;
        this.errors = {};
        this.formError = '';

        try {
            const response = await fetch(endpoint, {
                method,
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': this.section.csrfToken,
                },
                body: JSON.stringify(body),
            });

            if (response.status === 422) {
                const data = await response.json();
                this.errors = asRecord(data.errors);
                this.formError = asString(data.message, 'Unable to save record.');

                if (data.meta?.requires_conversion) {
                    this.openMissingConversionModal(data.meta, body);
                }

                return;
            }

            if (!response.ok) {
                this.formError = 'Unable to save record.';
                return;
            }

            const data = await response.json();

            if (this.formMode === 'create' && typeof this.adapters.handleCreateSuccess === 'function') {
                const handled = await this.adapters.handleCreateSuccess({
                    data,
                    component: this,
                    section: this.section,
                });

                if (handled === true) {
                    return;
                }
            }

            this.isFormOpen = false;
            await this.fetchPage(this.meta.current_page || 1);
        } catch (error) {
            this.formError = 'Unable to save record.';
        } finally {
            this.isSubmitting = false;
        }
    },
    async performAction(record, action) {
        switch (action.type) {
        case 'view': {
            const targetUrl = asString(resolvePathValue(record, action.urlField));

            if (targetUrl !== '') {
                globalThis.location.assign(targetUrl);
            }

            return;
        }
        case 'edit':
            this.openEditForm(record);
            return;
        case 'custom':
            if (typeof this.adapters.handleAction === 'function') {
                await this.adapters.handleAction({
                    action,
                    record,
                    component: this,
                });
            }
            return;
        case 'remove':
        case 'archive':
        case 'deactivate':
            break;
        default:
            if (typeof this.adapters.handleAction === 'function') {
                await this.adapters.handleAction({
                    action,
                    record,
                    component: this,
                });
            }
            return;
        }

        const endpoint = resolveUrl(this.section.endpoints[action.endpointKey], record.id);

        if (!endpoint) {
            return;
        }

        this.sectionError = '';

        try {
            const response = await fetch(endpoint, {
                method: action.method,
                headers: {
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': this.section.csrfToken,
                },
            });

            if (!response.ok) {
                this.sectionError = 'Unable to update record.';
                return;
            }

            await this.fetchPage(this.meta.current_page || 1);
        } catch (error) {
            this.sectionError = 'Unable to update record.';
        }
    },
    async performInlineMetaAction(record, meta) {
        if (typeof this.adapters.handleInlineMetaAction === 'function') {
            await this.adapters.handleInlineMetaAction({
                meta,
                record,
                component: this,
            });
        }
    },
});

export function mountCrudSection(targetEl, input) {
    if (!targetEl) {
        return;
    }

    const safeInput = asRecord(input);
    const section = normalizeSectionConfig(safeInput.section || safeInput);
    const adapters = asRecord(safeInput.adapters);

    if (!section.resource) {
        return;
    }

    const Alpine = globalThis.Alpine;

    targetEl._jsCrudSectionConfig = section;
    targetEl._jsCrudSectionAdapters = adapters;
    targetEl.innerHTML = renderCrudSection();

    Alpine.data('jsCrudSection', (el) => createSectionState(
        el.closest('[data-js-crud-section-root]')?._jsCrudSectionConfig || section,
        el.closest('[data-js-crud-section-root]')?._jsCrudSectionAdapters || adapters,
        el
    ));
}
