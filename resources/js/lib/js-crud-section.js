const asRecord = (value) =>
    value && typeof value === "object" && !Array.isArray(value) ? value : {};

const asArray = (value) => (Array.isArray(value) ? value : []);

const asString = (value, fallback = "") =>
    typeof value === "string" && value.trim() !== "" ? value : fallback;

const asBoolean = (value) => Boolean(value);

const normalizePositiveInteger = (value, fallback) => {
    const number = Number(value);

    return Number.isInteger(number) && number > 0 ? number : fallback;
};

const normalizePaginationOptions = (value) => {
    const options = asArray(value)
        .map((option) => Number(option))
        .filter((option) => Number.isInteger(option) && option > 0);

    return options.length > 0 ? options : [5, 10, 25];
};

const mobileRecordClass = (className) =>
    asString(className)
        .split(/\s+/)
        .filter((token) => token !== "")
        .map((token) => {
            if (token.includes(":")) {
                return token;
            }

            if (token === "rounded-xl") {
                return "sm:rounded-xl";
            }

            if (token === "rounded-lg") {
                return "sm:rounded-lg";
            }

            if (token === "p-3" || token === "p-4") {
                return `px-3 py-2 sm:${token}`;
            }

            if (token === "py-1") {
                return "py-2 sm:py-1";
            }

            if (token === "px-4") {
                return "px-3 sm:px-4";
            }

            if (token === "border-gray-200") {
                return "border-gray-300 sm:border-gray-200";
            }

            if (token === "border-gray-100") {
                return "border-gray-300 sm:border-gray-100";
            }

            if (token.startsWith("border-gray-")) {
                return "border-gray-300";
            }

            return token;
        })
        .join(" ");

const resolvePathValue = (source, path, fallback = "") => {
    if (!path) {
        return fallback;
    }

    return (
        path.split(".").reduce((carry, key) => {
            if (carry && typeof carry === "object" && key in carry) {
                return carry[key];
            }

            return undefined;
        }, source) ?? fallback
    );
};

const normalizeField = (field) => {
    const safeField = asRecord(field);
    const inlineCreate = asRecord(safeField.inlineCreate);

    return {
        name: asString(safeField.name),
        label: asString(safeField.label),
        type: asString(safeField.type, "text"),
        numberType: asString(safeField.numberType, "decimal"),
        precision: safeField.precision,
        currency: asString(safeField.currency),
        currencyFromField: asString(safeField.currencyFromField),
        currencyOptionField: asString(
            safeField.currencyOptionField,
            "currency_code",
        ),
        rawMode: asString(safeField.rawMode, "value"),
        debounceMs: safeField.debounceMs,
        required: Boolean(safeField.required),
        options: asArray(safeField.options).map((option) => ({
            ...asRecord(option),
            value: asString(option?.value),
            label: asString(option?.label),
        })),
        rowGroup: asString(safeField.rowGroup),
        width: asString(safeField.width),
        inlineCreate: {
            label: asString(inlineCreate.label, "Create"),
            storeUrl: asString(inlineCreate.storeUrl),
            fields: asArray(inlineCreate.fields)
                .map((createField) => ({
                    name: asString(createField?.name),
                    label: asString(createField?.label),
                    type: asString(createField?.type, "text"),
                    required: Boolean(createField?.required),
                }))
                .filter((createField) => createField.name !== ""),
        },
    };
};

const normalizeAction = (action) => {
    const safeAction = asRecord(action);

    return {
        id: asString(safeAction.id),
        label: asString(safeAction.label),
        ariaLabel: asString(safeAction.ariaLabel),
        confirmMessage: asString(safeAction.confirmMessage),
        type: asString(safeAction.type, asString(safeAction.id)),
        tone: asString(safeAction.tone, "default"),
        icon: asString(safeAction.icon),
        tooltip: asString(safeAction.tooltip),
        urlField: asString(safeAction.urlField),
        endpointKey: asString(safeAction.endpointKey, "remove"),
        method: asString(safeAction.method, "DELETE").toUpperCase(),
        handlerKey: asString(safeAction.handlerKey),
    };
};

const normalizeLayoutEntry = (entry) => {
    const safeEntry = asRecord(entry);

    return {
        key: asString(safeEntry.key),
        type: asString(safeEntry.type),
        label: asString(safeEntry.label),
        field: asString(safeEntry.field),
        urlField: asString(safeEntry.urlField),
        suffixField: asString(safeEntry.suffixField),
        hideLabelOnMobile: Boolean(safeEntry.hideLabelOnMobile),
        compactOnMobile: Boolean(safeEntry.compactOnMobile),
        mobilePlacement: asString(safeEntry.mobilePlacement),
        textClass: asString(safeEntry.textClass),
        textValueClass: asString(safeEntry.textValueClass),
        linkClass: asString(safeEntry.linkClass),
        suffixClass: asString(safeEntry.suffixClass),
        fullWidth: Boolean(safeEntry.fullWidth),
        requiredWhenBlank: Boolean(safeEntry.requiredWhenBlank),
        fallback: Object.prototype.hasOwnProperty.call(safeEntry, "fallback")
            ? String(safeEntry.fallback ?? "")
            : "—",
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
    const pagination = asRecord(safeConfig.pagination);

    return {
        resource: asString(safeConfig.resource),
        title: asString(safeConfig.title, "Section"),
        description: asString(safeConfig.description),
        emptyState: asString(safeConfig.emptyState, "No records found."),
        recordClass: asString(safeConfig.recordClass),
        rowClass: asString(safeConfig.rowClass),
        rightMetaClass: asString(safeConfig.rightMetaClass),
        rowActionsMenuClass: asString(safeConfig.rowActionsMenuClass),
        showRowActionsMenuOnMobile:
            safeConfig.showRowActionsMenuOnMobile !== false,
        mobileRowUrlField: asString(safeConfig.mobileRowUrlField),
        secondaryFieldsClass: asString(safeConfig.secondaryFieldsClass),
        inlineActionsOnMobile: Boolean(safeConfig.inlineActionsOnMobile),
        csrfToken: asString(safeConfig.csrfToken),
        defaultOpen: asBoolean(safeConfig.defaultOpen),
        mobilePageSize: Number.isInteger(safeConfig.mobilePageSize)
            ? safeConfig.mobilePageSize
            : null,
        initialRecords: asArray(
            safeConfig.initialRecords || safeConfig.initial_records,
        ),
        pagination: {
            enabled: pagination.enabled !== false,
            perPage: normalizePositiveInteger(pagination.perPage, 5),
            perPageOptions: normalizePaginationOptions(
                pagination.perPageOptions,
            ),
            allowPerPageChange: Boolean(pagination.allowPerPageChange),
        },
        showRowActionsMenu: safeConfig.showRowActionsMenu !== false,
        toolbarToggles: asArray(safeConfig.toolbarToggles)
            .map((toggle) => ({
                key: asString(toggle?.key),
                label: asString(toggle?.label),
                checked: asBoolean(toggle?.checked),
            }))
            .filter((toggle) => toggle.key !== ""),
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
            placeholder: asString(addRow.placeholder, "Search"),
            noResultsText: asString(addRow.noResultsText, "No items found."),
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
        fields: asArray(safeConfig.fields)
            .map(normalizeField)
            .filter((field) => field.name !== ""),
        actions: asArray(safeConfig.actions)
            .map(normalizeAction)
            .filter((action) => action.id !== ""),
        rowLayout: {
            primaryText: normalizeLayoutEntry(rowLayout.primaryText),
            secondaryFields: asArray(rowLayout.secondaryFields).map(
                normalizeLayoutEntry,
            ),
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
                <div :class="fieldWrapperClass(field)">
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
                            x-on:focusout="handleFocusAway($event)"
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

                    <template x-if="field.type === 'smart-number'">
                        <div
                            class="mt-1"
                            data-smart-number-input-root
                            x-data="smartNumberInput({
                                name: field.name,
                                value: form[field.name],
                                type: field.numberType || 'decimal',
                                currency: smartNumberFieldCurrency(field),
                                precision: smartNumberFieldPrecision(field),
                                debounceMs: field.debounceMs || 300,
                                rawMode: field.rawMode || 'value',
                            })"
                            x-modelable="rawValue"
                            x-model="form[field.name]"
                        >
                            <span class="flex w-full items-center rounded-lg border border-gray-300 bg-white shadow-sm transition focus-within:border-blue-500 focus-within:ring-2 focus-within:ring-blue-500/20">
                                <template x-if="field.numberType === 'money'">
                                    <span class="pointer-events-none flex shrink-0 items-center pl-3 text-sm text-gray-500">$</span>
                                </template>
                                <input
                                    type="text"
                                    class="block min-w-0 flex-1 border-0 bg-transparent px-3 py-2 text-sm text-gray-900 placeholder:text-gray-400 focus:outline-none focus:ring-0"
                                    :id="\`section-field-\${field.name}\`"
                                    inputmode="decimal"
                                    x-model="displayValue"
                                    x-bind:size="inputSize()"
                                    x-on:input="handleInput($event)"
                                    x-on:change="handleChange($event)"
                                    x-on:blur="handleBlur($event)"
                                />
                                <template x-if="field.numberType === 'money' && smartNumberFieldCurrency(field) !== ''">
                                    <span class="pointer-events-none flex shrink-0 items-center border-l border-gray-200 px-3 text-xs font-medium uppercase tracking-wide text-gray-500" x-text="smartNumberFieldCurrency(field)"></span>
                                </template>
                            </span>
                        </div>
                    </template>

                    <template x-if="field.type !== 'select' && field.type !== 'combobox' && field.type !== 'smart-number'">
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
        :class="section.rowActionsMenuClass"
        x-data="{ open: false }"
        x-show="rowActionsMenuVisible(record)"
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
            <button
                type="button"
                class="inline-flex items-center transition"
                :class="inlineActionButtonClass(action)"
                x-bind:aria-label="action.ariaLabel || actionLabel(record, action)"
                x-bind:title="actionTooltip(record, action)"
                x-on:click="performAction(record, action)"
            >
                <template x-if="action.icon === 'credit-card'">
                    <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 8.25h19.5M2.25 9h19.5m-16.5 5.25h6m-6 2.25h3m-3.75 3h15a2.25 2.25 0 0 0 2.25-2.25V6.75A2.25 2.25 0 0 0 19.5 4.5h-15a2.25 2.25 0 0 0-2.25 2.25v10.5A2.25 2.25 0 0 0 4.5 19.5Z" />
                    </svg>
                </template>
                <template x-if="action.icon === 'x-mark'">
                    <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                    </svg>
                </template>
                <template x-if="action.icon !== 'x-mark' && action.icon !== 'credit-card'">
                    <span x-text="actionLabel(record, action)"></span>
                </template>
            </button>
        </template>
    </div>
`;

const renderCrudSection = () => `
    <section
        class="-mx-1 !-mt-px overflow-visible border border-gray-500 bg-white shadow-sm first:!mt-0 sm:mx-0 sm:!mt-6 sm:first:!mt-0 sm:rounded-2xl sm:border-gray-200"
        data-js-crud-section-card
        x-data="jsCrudSection($el)"
    >
        <div class="flex items-start justify-between gap-2 bg-blue-50 px-3 py-4 sm:gap-3 sm:px-6 sm:py-5">
            <div class="min-w-0 flex-1">
                <h3 class="text-lg font-semibold text-gray-900" x-text="section.title"></h3>
                <p
                    class="mt-1 text-sm text-gray-500 sm:overflow-visible sm:whitespace-normal sm:text-clip"
                    :class="descriptionExpanded ? 'whitespace-normal' : 'truncate'"
                    x-text="section.description"
                ></p>
            </div>
            <button
                type="button"
                class="inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-lg border border-gray-300 text-gray-600 transition hover:bg-gray-50 hover:text-gray-900"
                aria-expanded="false"
                x-bind:aria-expanded="isOpen ? 'true' : 'false'"
                x-on:click="toggleOpen()"
                aria-label="Toggle section"
                data-js-crud-section-toggle
            >
                <svg class="h-4 w-4 text-gray-400 transition duration-[400ms] ease-in-out" :class="isOpen ? 'rotate-180' : ''" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5" />
                </svg>
            </button>
        </div>

        <div
            class="grid transition-[grid-template-rows] duration-[400ms] ease-in-out"
            :class="isOpen ? 'grid-rows-[1fr]' : 'grid-rows-[0fr]'"
            :aria-hidden="isOpen ? 'false' : 'true'"
            x-cloak
        >
            <div class="min-h-0 overflow-hidden">
                <div
                    class="border-t border-gray-100 bg-white px-3 py-2 opacity-0 transition-opacity duration-[400ms] ease-in-out sm:px-6 sm:py-5"
                    :class="isOpen ? 'opacity-100' : 'opacity-0'"
                >
                    <div class="mb-2 flex flex-col gap-2 sm:mb-4 sm:gap-3">
                        <p class="text-sm text-red-600" x-show="sectionError" x-text="sectionError"></p>
                        <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between sm:gap-3">
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
                            class="inline-flex h-7 w-7 items-center justify-center rounded-lg border border-gray-300 text-gray-600 transition hover:bg-gray-50 hover:text-gray-900 sm:h-8 sm:w-8"
                            x-show="section.permissions.canCreate"
                            x-on:click.stop.prevent="openCreateForm()"
                            aria-label="Create"
                            data-js-crud-section-create-button
                        >
                            <svg class="h-3.5 w-3.5 sm:h-4 sm:w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                            </svg>
                        </button>
                    </div>
                </div>
            </div>

            <div
                class="mb-2 flex flex-col gap-2 sm:mb-4 sm:flex-row sm:items-end sm:justify-between sm:gap-3"
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
                            x-on:focusout="handleFocusAway($event)"
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
                        class="inline-flex h-7 w-7 items-center justify-center rounded-lg border border-gray-300 text-gray-700 transition hover:bg-gray-50 disabled:cursor-not-allowed disabled:opacity-50 sm:h-8 sm:w-8"
                        x-bind:disabled="addRowValue === '' || addRowSubmitting"
                        x-bind:aria-label="section.addRow.action.ariaLabel || 'Add record'"
                        x-on:click.stop.prevent="submitAddRow()"
                    >
                        <svg class="h-3.5 w-3.5 sm:h-4 sm:w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                        </svg>
                    </button>
                </div>
            </div>

            <div
                class="-mx-3 space-y-0 border-t border-gray-300 sm:mx-0 sm:space-y-3 sm:border-t-0"
                :class="section.pagination.enabled && paginationLastPage() > 1 ? 'min-h-[22rem] sm:min-h-[20rem]' : ''"
                x-show="records.length > 0"
                data-js-crud-section-records
            >
                <template x-for="record in paginatedRecords()" :key="record.id">
                    <article :class="recordClass(record)">
                        <template x-if="mobileRowUrl(record) !== ''">
                            <a
                                class="absolute inset-0 z-10 sm:hidden"
                                x-bind:href="mobileRowUrl(record)"
                                x-bind:aria-label="'View ' + primaryText(record)"
                            ></a>
                        </template>
                        <div :class="recordRowClass(record)">
                            <div class="min-w-0 flex-1">
                                <div class="flex items-center gap-4 sm:gap-3">
                                    <template x-if="primaryTextUrl(record) !== ''">
                                        <a
                                            :class="primaryTextLinkClass()"
                                            x-bind:href="primaryTextUrl(record)"
                                            x-text="primaryText(record)"
                                        ></a>
                                    </template>
                                    <template x-if="primaryTextUrl(record) === ''">
                                        <p class="truncate text-sm font-semibold text-gray-900" x-text="primaryText(record)"></p>
                                    </template>
                                    <template x-for="badge in badgeItems(record)" :key="\`\${record.id}-\${badge.text}-badge\`">
                                        <span
                                            class="inline-flex items-center rounded-full px-2.5 py-1 font-medium"
                                            :class="[badge.toneClass, badge.textClass || 'text-xs']"
                                            x-text="badge.text"
                                        ></span>
                                    </template>
                                    <template x-for="line in mobilePrimaryFieldItems(record)" :key="\`\${record.id}-\${line.label}-mobile-primary\`">
                                        <p class="hidden shrink-0 text-xs text-gray-700 max-sm:block">
                                            <span x-text="line.text"></span>
                                            <template x-if="line.suffix">
                                                <span class="ml-1 align-baseline text-[0.65rem] font-medium text-gray-500" x-text="line.suffix"></span>
                                            </template>
                                        </p>
                                    </template>
                                </div>
                                <div :class="secondaryFieldsClass(record)">
                                    <template x-for="line in secondaryFieldItems(record)" :key="\`\${record.id}-\${line.key}-secondary\`">
                                        <p class="text-gray-600" :class="secondaryLineClass(line)">
                                            <template x-if="line.label">
                                                <span
                                                    class="text-gray-500"
                                                    :class="line.hideLabelOnMobile ? 'hidden sm:inline' : ''"
                                                    x-text="\`\${line.label}: \`"
                                                ></span>
                                            </template>
                                            <span :class="line.textValueClass || 'text-gray-700'" x-text="line.text"></span>
                                            <template x-if="line.suffix">
                                                <span class="ml-1 align-baseline text-xs font-medium text-gray-500" x-text="line.suffix"></span>
                                            </template>
                                        </p>
                                    </template>
                                </div>
                            </div>

                            <div class="relative z-20 flex items-center justify-end gap-3 self-center">
                                <div :class="rightMetaColumnClass(record)">
                                    <template x-for="meta in rightMetaItems(record)" :key="\`\${record.id}-\${meta.key}-meta\`">
                                        <div>
                                            <template x-if="meta.type === 'input'">
                                                <label :class="rightMetaControlLabelClass(meta)">
                                                    <template x-if="meta.showSuccessIcon">
                                                        <svg class="h-5 w-5 shrink-0 text-emerald-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                                                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75" />
                                                            <path stroke-linecap="round" stroke-linejoin="round" d="M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                                                        </svg>
                                                    </template>
                                                    <template x-if="meta.label">
                                                        <span :class="rightMetaLabelClass(meta)" x-text="meta.labelBare ? meta.label : \`\${meta.label}: \`"></span>
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
                                            <template x-if="meta.type === 'smart-number'">
                                                <label :class="rightMetaControlLabelClass(meta)">
                                                    <template x-if="meta.showSuccessIcon">
                                                        <svg class="h-5 w-5 shrink-0 text-emerald-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                                                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75" />
                                                            <path stroke-linecap="round" stroke-linejoin="round" d="M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                                                        </svg>
                                                    </template>
                                                    <template x-if="meta.requiredWhenBlank && rightMetaValueIsBlank(record, meta)">
                                                        <span :class="rightMetaRequiredClass()">required</span>
                                                    </template>
                                                    <template x-if="meta.label">
                                                        <span :class="rightMetaLabelClass(meta)" x-text="meta.labelBare ? meta.label : \`\${meta.label}: \`"></span>
                                                    </template>
                                                    <span
                                                        data-smart-number-input-root
                                                        :class="smartNumberMetaRootClass(meta)"
                                                        x-data="smartNumberInput({
                                                            name: meta.field,
                                                            value: record[meta.field],
                                                            type: meta.numberType || 'decimal',
                                                            currency: meta.currency || '',
                                                            precision: smartNumberPrecision(record, meta),
                                                            debounceMs: meta.debounceMs || 300,
                                                            emitOnChange: false,
                                                            rawMode: meta.rawMode || 'value',
                                                        })"
                                                        x-init="$watch(() => record[meta.field], (value) => {
                                                            rawValue = value === null || value === undefined ? '' : String(value);
                                                            displayValue = formatDisplayValue(rawValue);
                                                        })"
                                                        x-modelable="rawValue"
                                                        x-on:smart-number-input:changed="handleSmartNumberMetaChanged(record, meta, $event.detail)"
                                                    >
                                                        <span :class="smartNumberMetaFrameClass(meta)">
                                                            <input
                                                                type="text"
                                                                :class="smartNumberMetaInputClass(meta)"
                                                                inputmode="decimal"
                                                                x-model="displayValue"
                                                                x-bind:size="inputSize()"
                                                                x-on:input="handleInput($event); syncSmartNumberMetaValue(record, meta, rawValue)"
                                                                x-on:change="handleChange($event); syncSmartNumberMetaValue(record, meta, rawValue)"
                                                                x-on:blur="handleBlur($event); syncSmartNumberMetaValue(record, meta, rawValue)"
                                                            />
                                                        </span>
                                                    </span>
                                                </label>
                                            </template>
                                            <template x-if="meta.type === 'badge'">
                                                <span
                                                    class="inline-flex items-center rounded-full px-2 py-0.5 text-[0.55rem] font-semibold uppercase tracking-wide"
                                                    :class="[meta.toneClass, meta.textClass]"
                                                    x-text="meta.text"
                                                ></span>
                                            </template>
                                            <template x-if="meta.type !== 'input' && meta.type !== 'smart-number' && meta.type !== 'badge'">
                                                <p :class="[meta.textClass || 'text-sm', meta.strong ? 'font-semibold text-gray-900' : 'text-gray-600']">
                                                    <template x-if="meta.label">
                                                        <span :class="rightMetaLabelClass(meta)" x-text="meta.labelBare ? meta.label : \`\${meta.label}: \`"></span>
                                                    </template>
                                                    <span x-text="meta.text"></span>
                                                    <template x-if="meta.suffix">
                                                        <span
                                                            class="ml-1 align-baseline font-medium text-gray-500"
                                                            :class="meta.suffixClass || 'text-xs'"
                                                            x-text="meta.suffix"
                                                        ></span>
                                                    </template>
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

            <div
                class="mt-4 flex items-center justify-between border-t border-gray-200 bg-white px-4 py-3 sm:px-6"
                x-show="section.pagination.enabled && paginationLastPage() > 1"
                data-js-crud-section-pagination
            >
                <div class="flex flex-1 justify-between sm:hidden">
                    <button
                        type="button"
                        class="relative inline-flex items-center rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 transition hover:bg-gray-50 disabled:cursor-not-allowed disabled:opacity-50"
                        x-on:click="goToPaginationPage(paginationCurrentPage() - 1)"
                        x-bind:disabled="paginationCurrentPage() <= 1 || isLoading"
                    >Previous</button>
                    <button
                        type="button"
                        class="relative ml-3 inline-flex items-center rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 transition hover:bg-gray-50 disabled:cursor-not-allowed disabled:opacity-50"
                        x-on:click="goToPaginationPage(paginationCurrentPage() + 1)"
                        x-bind:disabled="paginationCurrentPage() >= paginationLastPage() || isLoading"
                    >Next</button>
                </div>

                <div class="hidden sm:flex sm:flex-1 sm:items-center sm:justify-between">
                    <div>
                        <p class="text-sm text-gray-700">
                            Showing
                            <span class="font-medium" x-text="paginationShowingFrom()"></span>
                            to
                            <span class="font-medium" x-text="paginationShowingTo()"></span>
                            of
                            <span class="font-medium" x-text="paginationTotal()"></span>
                            results
                        </p>
                    </div>

                    <div class="flex items-center gap-3">
                        <template x-if="section.pagination.allowPerPageChange">
                            <label class="flex items-center gap-2 text-sm text-gray-500">
                                <span>Rows</span>
                                <select
                                    class="rounded-md border-gray-300 py-1.5 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500"
                                    x-model.number="section.pagination.perPage"
                                    x-on:change="resetPagination(); goToPaginationPage(1)"
                                >
                                    <template x-for="option in section.pagination.perPageOptions" :key="\`pagination-option-\${option}\`">
                                        <option :value="option" x-text="option"></option>
                                    </template>
                                </select>
                            </label>
                        </template>

                        <nav aria-label="Pagination" class="isolate inline-flex -space-x-px rounded-md shadow-sm">
                            <button
                                type="button"
                                class="relative inline-flex items-center rounded-l-md px-2 py-2 text-gray-400 ring-1 ring-inset ring-gray-300 transition hover:bg-gray-50 focus:z-20 focus:outline-offset-0 disabled:cursor-not-allowed disabled:opacity-50"
                                x-on:click="goToPaginationPage(paginationCurrentPage() - 1)"
                                x-bind:disabled="paginationCurrentPage() <= 1 || isLoading"
                            >
                                <span class="sr-only">Previous</span>
                                <svg viewBox="0 0 20 20" fill="currentColor" aria-hidden="true" class="size-5">
                                    <path d="M11.78 5.22a.75.75 0 0 1 0 1.06L8.06 10l3.72 3.72a.75.75 0 1 1-1.06 1.06l-4.25-4.25a.75.75 0 0 1 0-1.06l4.25-4.25a.75.75 0 0 1 1.06 0Z" clip-rule="evenodd" fill-rule="evenodd" />
                                </svg>
                            </button>

                            <template x-for="page in paginationPages()" :key="\`pagination-page-\${page.key}\`">
                                <template x-if="page.type === 'ellipsis'">
                                    <span class="relative inline-flex items-center px-4 py-2 text-sm font-semibold text-gray-700 ring-1 ring-inset ring-gray-300 focus:outline-offset-0">...</span>
                                </template>
                                <template x-if="page.type === 'page'">
                                    <button
                                        type="button"
                                        class="relative inline-flex items-center px-4 py-2 text-sm font-semibold transition focus:z-20"
                                        :class="page.current ? 'z-10 bg-blue-600 text-white focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue-600' : 'text-gray-900 ring-1 ring-inset ring-gray-300 hover:bg-gray-50 focus:outline-offset-0'"
                                        x-bind:aria-current="page.current ? 'page' : null"
                                        x-bind:disabled="isLoading || page.current"
                                        x-text="page.label"
                                        x-on:click="goToPaginationPage(page.value)"
                                    ></button>
                                </template>
                            </template>

                            <button
                                type="button"
                                class="relative inline-flex items-center rounded-r-md px-2 py-2 text-gray-400 ring-1 ring-inset ring-gray-300 transition hover:bg-gray-50 focus:z-20 focus:outline-offset-0 disabled:cursor-not-allowed disabled:opacity-50"
                                x-on:click="goToPaginationPage(paginationCurrentPage() + 1)"
                                x-bind:disabled="paginationCurrentPage() >= paginationLastPage() || isLoading"
                            >
                                <span class="sr-only">Next</span>
                                <svg viewBox="0 0 20 20" fill="currentColor" aria-hidden="true" class="size-5">
                                    <path d="M8.22 5.22a.75.75 0 0 1 1.06 0l4.25 4.25a.75.75 0 0 1 0 1.06l-4.25 4.25a.75.75 0 1 1-1.06-1.06L11.94 10 8.22 6.28a.75.75 0 0 1 0-1.06Z" clip-rule="evenodd" fill-rule="evenodd" />
                                </svg>
                            </button>
                        </nav>
                    </div>
                </div>
            </div>
        </div>
        </div>
        </div>

        <div
            class="fixed inset-0 z-50 overflow-hidden"
            x-show="isFormOpen"
            x-cloak
            role="dialog"
            aria-modal="true"
            aria-labelledby="js-crud-section-create-title"
        >
            <div class="absolute inset-0 overflow-hidden">
                <div
                    class="absolute inset-0 bg-gray-500 bg-opacity-25 transition-opacity"
                    x-show="isFormOpen"
                    x-on:click="closeForm()"
                ></div>

                <div
                    tabindex="0"
                    class="absolute inset-0 pl-10 focus:outline-none sm:pl-16"
                    x-on:click="closeForm()"
                >
                    <div class="pointer-events-none fixed inset-y-0 right-0 flex max-w-full pl-10">
                        <div class="pointer-events-auto w-screen max-w-md" x-on:click.stop>
                            <form
                                class="relative flex h-full flex-col divide-y divide-gray-200 bg-white shadow-xl"
                                x-show="isFormOpen"
                                x-on:submit.prevent="submitForm()"
                            >
                                <div class="h-0 flex-1 overflow-y-auto">
                                    <div class="bg-blue-600 px-4 py-6 sm:px-6">
                                        <div class="flex items-start justify-between gap-4">
                                            <div>
                                                <h4 id="js-crud-section-create-title" class="text-lg font-semibold text-white" x-text="createFormTitle()"></h4>
                                                <p class="mt-1 text-sm text-blue-100" x-show="createFormDescription() !== ''" x-text="createFormDescription()"></p>
                                            </div>

                                            <div class="flex h-7 items-center">
                                                <button
                                                    type="button"
                                                    class="relative rounded-md text-blue-100 hover:text-white focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-white"
                                                    x-on:click="closeForm()"
                                                >
                                                    <span class="absolute -inset-2.5"></span>
                                                    <span class="sr-only">Close panel</span>
                                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true" class="size-6">
                                                        <path d="M6 18 18 6M6 6l12 12" stroke-linecap="round" stroke-linejoin="round" />
                                                    </svg>
                                                </button>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="space-y-5 px-4 py-6 sm:px-6">
                                        <p class="text-sm text-red-600" x-show="formError" x-text="formError"></p>
                                        ${fieldMarkup}
                                    </div>
                                </div>

                                <div class="flex shrink-0 justify-end gap-3 px-4 py-4 sm:px-6">
                                    <button type="button" class="rounded-lg border border-gray-300 px-4 py-2 text-sm text-gray-700 transition hover:bg-gray-50" x-on:click="closeForm()">Cancel</button>
                                    <button
                                        type="submit"
                                        class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white transition hover:bg-blue-500 disabled:cursor-not-allowed disabled:opacity-50"
                                        x-bind:disabled="isSubmitting"
                                        x-text="createFormSubmitLabel()"
                                    ></button>
                                </div>
                            </form>
                        </div>
                    </div>
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

const buildEmptyForm = (section) =>
    section.fields.reduce((carry, field) => {
        carry[field.name] = "";

        return carry;
    }, {});

const resolveUrl = (template, id) =>
    asString(template).replace("{id}", encodeURIComponent(String(id)));

const defaultToneClass = (tone) =>
    ({
        success: "bg-emerald-100 text-emerald-700",
        info: "bg-sky-100 text-sky-700",
        warning: "bg-yellow-100 text-yellow-700",
        danger: "bg-red-100 text-red-700",
        muted: "bg-gray-200 text-gray-700",
        default: "bg-blue-100 text-blue-700",
    })[tone] || "bg-blue-100 text-blue-700";

const buildLayoutText = (record, entry) => {
    const base = resolvePathValue(record, entry.field, "");
    const suffix = resolvePathValue(record, entry.suffixField, "");
    const parts = [base, suffix].filter(
        (part) => part !== null && part !== undefined && String(part) !== "",
    );

    if (parts.length === 0) {
        return entry.fallback;
    }

    return parts.join(" ");
};

const buildLayoutBaseText = (record, entry) => {
    const base = resolvePathValue(record, entry.field, "");

    if (base === null || base === undefined || String(base) === "") {
        return entry.fallback;
    }

    return String(base);
};

const buildLayoutSuffixText = (record, entry) => {
    const base = resolvePathValue(record, entry.field, "");
    const suffix = resolvePathValue(record, entry.suffixField, "");

    if (base === null || base === undefined || String(base) === "") {
        return "";
    }

    if (suffix === null || suffix === undefined || String(suffix) === "") {
        return "";
    }

    return String(suffix);
};

const createSectionState = (section, adapters, hostEl) => ({
    section,
    adapters,
    isOpen: asBoolean(section.defaultOpen),
    descriptionExpanded: asBoolean(section.defaultOpen),
    descriptionTimer: null,
    hasLoaded: false,
    isLoading: false,
    isFormOpen: false,
    isSubmitting: false,
    formMode: "create",
    editingId: null,
    records: asArray(section.initialRecords).map((record) => asRecord(record)),
    paginationPage: 1,
    meta: {
        current_page: 1,
        last_page: Math.max(
            Math.ceil(
                asArray(section.initialRecords).length /
                    section.pagination.perPage,
            ),
            1,
        ),
        per_page: section.pagination.perPage,
        total: asArray(section.initialRecords).length,
    },
    form: buildEmptyForm(section),
    errors: {},
    sectionError: "",
    formError: "",
    missingConversionModal: {
        open: false,
        itemName: "",
        fromUomLabel: "",
        toUomLabel: "",
        endpoint: "",
        retryAfterSuccess: false,
        originalPayload: {},
        form: {
            item_id: "",
            from_uom_id: "",
            to_uom_id: "",
            conversion_factor: "",
        },
        errors: {},
        error: "",
        submitting: false,
    },
    inlineCreateFieldName: "",
    inlineCreateForm: {},
    inlineCreateErrors: {},
    inlineCreateFormError: "",
    inlineCreateSubmitting: false,
    addRowValue: "",
    addRowSubmitting: false,
    toggleValues: section.toolbarToggles.reduce((carry, toggle) => {
        carry[toggle.key] = Boolean(toggle.checked);

        return carry;
    }, {}),
    init() {
        const rootEl = hostEl?.closest("[data-js-crud-section-root]");

        if (rootEl) {
            rootEl._jsCrudSectionApi = {
                close: () => {
                    this.closeSection();
                },
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
    hasRemotePagination() {
        return this.section.endpoints.list !== "";
    },
    paginationPerPage() {
        const mobilePageSize = Number.isInteger(this.section.mobilePageSize)
            ? this.section.mobilePageSize
            : null;

        if (
            mobilePageSize !== null &&
            mobilePageSize > 0 &&
            typeof globalThis.matchMedia === "function" &&
            globalThis.matchMedia("(max-width: 639px)").matches
        ) {
            return mobilePageSize;
        }

        return normalizePositiveInteger(this.section.pagination.perPage, 5);
    },
    paginationCurrentPage() {
        return this.hasRemotePagination()
            ? this.meta.current_page
            : this.paginationPage;
    },
    paginationLastPage() {
        if (this.hasRemotePagination()) {
            return Math.max(
                normalizePositiveInteger(this.meta.last_page, 1),
                1,
            );
        }

        return Math.max(
            Math.ceil(this.records.length / this.paginationPerPage()),
            1,
        );
    },
    paginationTotal() {
        return this.hasRemotePagination()
            ? normalizePositiveInteger(this.meta.total, 0)
            : this.records.length;
    },
    paginationShowingFrom() {
        if (this.paginationTotal() === 0) {
            return 0;
        }

        return (
            (this.paginationCurrentPage() - 1) * this.paginationPerPage() + 1
        );
    },
    paginationShowingTo() {
        return Math.min(
            this.paginationCurrentPage() * this.paginationPerPage(),
            this.paginationTotal(),
        );
    },
    paginationPages() {
        const current = this.paginationCurrentPage();
        const last = this.paginationLastPage();

        if (last <= 7) {
            return Array.from({ length: last }, (_, index) =>
                this.paginationPageItem(index + 1, current),
            );
        }

        const pages = [this.paginationPageItem(1, current)];
        const windowStart = Math.max(2, current - 1);
        const windowEnd = Math.min(last - 1, current + 1);

        if (windowStart > 2) {
            pages.push(this.paginationEllipsisItem("start"));
        }

        for (let page = windowStart; page <= windowEnd; page += 1) {
            pages.push(this.paginationPageItem(page, current));
        }

        if (windowEnd < last - 1) {
            pages.push(this.paginationEllipsisItem("end"));
        }

        pages.push(this.paginationPageItem(last, current));

        return pages;
    },
    paginationPageItem(page, current) {
        return {
            type: "page",
            key: `page-${page}`,
            value: page,
            label: String(page),
            current: page === current,
        };
    },
    paginationEllipsisItem(position) {
        return {
            type: "ellipsis",
            key: `ellipsis-${position}`,
        };
    },
    paginatedRecords() {
        if (!this.section.pagination.enabled || this.hasRemotePagination()) {
            return this.records;
        }

        const page = this.paginationCurrentPage();
        const perPage = this.paginationPerPage();
        const offset = (page - 1) * perPage;

        return this.records.slice(offset, offset + perPage);
    },
    resetPagination() {
        this.paginationPage = 1;
    },
    clampPaginationPage() {
        this.paginationPage = Math.min(
            Math.max(this.paginationPage, 1),
            this.paginationLastPage(),
        );
    },
    async goToPaginationPage(page) {
        const targetPage = Math.min(
            Math.max(Number(page) || 1, 1),
            this.paginationLastPage(),
        );

        if (this.hasRemotePagination()) {
            await this.fetchPage(targetPage);
            return;
        }

        this.paginationPage = targetPage;
        this.clampPaginationPage();
    },
    async toggleOpen() {
        const nextOpen = !this.isOpen;

        if (nextOpen && this.isMobileViewport()) {
            this.closeSiblingSections();
        }

        this.isOpen = nextOpen;
        this.syncDescriptionExpandedAfterTransition();

        if (nextOpen && !this.hasLoaded) {
            await this.fetchPage(1);
        }
    },
    closeSection() {
        this.isOpen = false;
        this.syncDescriptionExpandedAfterTransition();
    },
    syncDescriptionExpandedAfterTransition() {
        clearTimeout(this.descriptionTimer);
        this.descriptionTimer = setTimeout(() => {
            this.descriptionExpanded = this.isOpen;
        }, 400);
    },
    closeSiblingSections() {
        const rootEl = hostEl?.closest("[data-js-crud-section-root]");
        const parentEl = rootEl?.parentElement;

        if (!rootEl || !parentEl) {
            return;
        }

        parentEl
            .querySelectorAll(":scope > [data-js-crud-section-root]")
            .forEach((sectionRoot) => {
                if (sectionRoot === rootEl) {
                    return;
                }

                sectionRoot._jsCrudSectionApi?.close?.();
            });
    },
    normalizeRow(record) {
        const adapter = this.adapters.normalizeRow;

        if (typeof adapter === "function") {
            return adapter(record) || record;
        }

        return record;
    },
    primaryText(record) {
        return buildLayoutText(record, this.section.rowLayout.primaryText);
    },
    primaryTextUrl(record) {
        return asString(
            resolvePathValue(
                record,
                this.section.rowLayout.primaryText.urlField,
            ),
        );
    },
    primaryTextLinkClass() {
        return asString(
            this.section.rowLayout.primaryText.linkClass,
            "truncate text-sm font-semibold text-blue-700 transition hover:text-blue-600 hover:underline",
        );
    },
    mobileRowUrl(record) {
        return asString(
            resolvePathValue(record, this.section.mobileRowUrlField),
        );
    },
    secondaryFieldItems(record) {
        return this.section.rowLayout.secondaryFields
            .map((entry, index) => ({
                key: entry.key || entry.field || `secondary-${index}`,
                label: entry.label,
                text: buildLayoutBaseText(record, entry),
                suffix: buildLayoutSuffixText(record, entry),
                hideLabelOnMobile: entry.hideLabelOnMobile,
                compactOnMobile: entry.compactOnMobile,
                mobilePlacement: entry.mobilePlacement,
                textClass: entry.textClass,
                textValueClass: entry.textValueClass,
                fullWidth: entry.fullWidth,
            }))
            .filter((entry) => entry.text !== "" || entry.suffix !== "");
    },
    mobilePrimaryFieldItems(record) {
        return this.secondaryFieldItems(record).filter(
            (entry) => entry.mobilePlacement === "primary-end",
        );
    },
    secondaryLineClass(line) {
        const classes = [
            line.textClass ||
                (line.compactOnMobile ? "text-xs sm:text-sm" : "text-sm"),
        ];

        if (line.mobilePlacement === "primary-end") {
            classes.push("hidden sm:block");
        }

        if (line.fullWidth) {
            classes.push("basis-full");
        }

        return classes.join(" ");
    },
    secondaryFieldsClass(record) {
        const baseClass = asString(
            this.section.secondaryFieldsClass,
            "mt-1 flex flex-wrap items-center gap-4",
        );
        const recordLevelClass = asString(record.secondaryFieldsClass);

        return [baseClass, recordLevelClass]
            .filter((value) => value !== "")
            .join(" ");
    },
    badgeItems(record) {
        return this.section.rowLayout.badges
            .map((entry) => ({
                text: buildLayoutText(record, entry),
                toneClass: defaultToneClass(
                    asString(
                        resolvePathValue(record, entry.toneField, "muted"),
                    ),
                ),
                textClass: entry.textClass,
            }))
            .filter((badge) => badge.text !== "—" && badge.text !== "");
    },
    rightMetaItems(record) {
        if (typeof this.adapters.rightMetaItems === "function") {
            return asArray(
                this.adapters.rightMetaItems({
                    record,
                    component: this,
                    section: this.section,
                }),
            );
        }

        return this.section.rowLayout.rightMeta.map((entry, index) => ({
            key: entry.key || entry.field || `right-meta-${index}`,
            type: entry.type,
            label: entry.label,
            text: buildLayoutBaseText(record, entry),
            suffix: buildLayoutSuffixText(record, entry),
            toneClass: defaultToneClass(
                asString(resolvePathValue(record, entry.toneField, "muted")),
            ),
            textClass: entry.textClass,
            suffixClass: entry.suffixClass,
            strong: entry.strong,
        }));
    },
    isMobileViewport() {
        return (
            typeof globalThis.matchMedia === "function" &&
            globalThis.matchMedia("(max-width: 639px)").matches
        );
    },
    rowActionsMenuVisible(record) {
        if (
            !this.section.showRowActionsMenu ||
            this.visibleActions(record).length === 0
        ) {
            return false;
        }

        return (
            this.section.showRowActionsMenuOnMobile || !this.isMobileViewport()
        );
    },
    visibleActions(record) {
        const hasExplicitAvailableActions =
            Array.isArray(record.availableActions) ||
            Array.isArray(record.available_actions);
        const availableActions = asArray(
            record.availableActions || record.available_actions,
        );
        const canComplete = Boolean(record.canComplete || record.can_complete);

        if (!hasExplicitAvailableActions && availableActions.length === 0) {
            return this.section.actions.filter(
                (action) => action.id !== "complete" || canComplete,
            );
        }

        return this.section.actions.filter(
            (action) =>
                availableActions.includes(action.id) ||
                (action.id === "complete" && canComplete),
        );
    },
    actionLabel(record, action) {
        const labels = asRecord(record.actionLabels || record.action_labels);

        return labels[action.id] || action.label;
    },
    actionTooltip(record, action) {
        return (
            action.tooltip ||
            action.ariaLabel ||
            this.actionLabel(record, action)
        );
    },
    inlineActionButtonClass(action) {
        if (action.icon === "credit-card" || action.icon === "x-mark") {
            return action.icon === "x-mark"
                ? "h-8 w-8 justify-center rounded-full border border-slate-300 text-slate-500 hover:border-blue-600 hover:bg-blue-50 hover:text-blue-700"
                : "h-8 w-8 justify-center rounded-full border border-slate-300 text-slate-600 hover:border-blue-600 hover:bg-blue-50 hover:text-blue-700";
        }

        return action.tone === "warning"
            ? "rounded-lg border border-yellow-300 px-3 py-1.5 text-xs font-semibold uppercase tracking-widest text-yellow-700 hover:bg-yellow-50"
            : "rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-semibold uppercase tracking-widest text-slate-700 hover:bg-slate-50";
    },
    firstError(fieldName) {
        const values = this.errors[fieldName];

        if (!Array.isArray(values) || values.length === 0) {
            return "";
        }

        return values[0];
    },
    missingConversionError(fieldName) {
        const values = this.missingConversionModal.errors[fieldName];

        if (!Array.isArray(values) || values.length === 0) {
            return "";
        }

        return values[0];
    },
    openMissingConversionModal(meta, originalPayload = {}) {
        const item = asRecord(meta.item);
        const fromUom = asRecord(meta.from_uom);
        const toUom = asRecord(meta.to_uom);

        this.missingConversionModal = {
            open: true,
            itemName: asString(item.name, "Selected material"),
            fromUomLabel: asString(fromUom.symbol, asString(fromUom.name)),
            toUomLabel: asString(toUom.symbol, asString(toUom.name)),
            endpoint: asString(meta.conversion_create_url),
            retryAfterSuccess: true,
            originalPayload,
            form: {
                item_id:
                    item.id === null || item.id === undefined
                        ? ""
                        : String(item.id),
                from_uom_id:
                    fromUom.id === null || fromUom.id === undefined
                        ? ""
                        : String(fromUom.id),
                to_uom_id:
                    toUom.id === null || toUom.id === undefined
                        ? ""
                        : String(toUom.id),
                conversion_factor: "",
            },
            errors: {},
            error: "",
            submitting: false,
        };
    },
    closeMissingConversionModal() {
        this.missingConversionModal = {
            ...this.missingConversionModal,
            open: false,
            errors: {},
            error: "",
            submitting: false,
        };
    },
    async submitMissingConversion() {
        if (
            !this.missingConversionModal.endpoint ||
            this.missingConversionModal.submitting
        ) {
            return;
        }

        this.missingConversionModal.submitting = true;
        this.missingConversionModal.errors = {};
        this.missingConversionModal.error = "";

        try {
            const response = await fetch(this.missingConversionModal.endpoint, {
                method: "POST",
                headers: {
                    "Content-Type": "application/json",
                    Accept: "application/json",
                    "X-CSRF-TOKEN": this.section.csrfToken,
                },
                body: JSON.stringify(this.missingConversionModal.form),
            });
            const data = await response.json().catch(() => ({}));

            if (response.status === 422) {
                this.missingConversionModal.errors = asRecord(data.errors);
                this.missingConversionModal.error = asString(
                    data.message,
                    "Unable to create conversion.",
                );
                return;
            }

            if (!response.ok) {
                this.missingConversionModal.error = asString(
                    data.message,
                    "Unable to create conversion.",
                );
                return;
            }

            this.closeMissingConversionModal();
            this.errors = {};
            this.formError = "";

            await this.submitForm();
        } catch (error) {
            this.missingConversionModal.error = "Unable to create conversion.";
        } finally {
            this.missingConversionModal.submitting = false;
        }
    },
    firstInlineCreateError(fieldName) {
        const values = this.inlineCreateErrors[fieldName];

        if (!Array.isArray(values) || values.length === 0) {
            return "";
        }

        return values[0];
    },
    resetInlineCreateState() {
        this.inlineCreateFieldName = "";
        this.inlineCreateForm = {};
        this.inlineCreateErrors = {};
        this.inlineCreateFormError = "";
        this.inlineCreateSubmitting = false;
    },
    openInlineCreate(field) {
        this.inlineCreateFieldName = field.name;
        this.inlineCreateForm = field.inlineCreate.fields.reduce(
            (carry, createField) => {
                carry[createField.name] = "";

                return carry;
            },
            {},
        );
        this.inlineCreateErrors = {};
        this.inlineCreateFormError = "";
        this.inlineCreateSubmitting = false;
    },
    closeInlineCreate() {
        this.resetInlineCreateState();
    },
    async submitAddRow() {
        if (
            !this.section.addRow.enabled ||
            this.addRowSubmitting ||
            this.addRowValue === ""
        ) {
            return;
        }

        if (typeof this.adapters.handleAddRow !== "function") {
            return;
        }

        this.addRowSubmitting = true;
        this.sectionError = "";

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

            if (field.rowGroup !== "") {
                const row = [field];

                this.section.fields.forEach(
                    (candidateField, candidateIndex) => {
                        if (candidateIndex <= index) {
                            return;
                        }

                        if (candidateField.rowGroup !== field.rowGroup) {
                            return;
                        }

                        row.push(candidateField);
                        consumedIndexes.add(candidateIndex);
                    },
                );

                rows.push(row);
                return;
            }

            rows.push([field]);
        });

        return rows;
    },
    rowClass(row) {
        if (row.length <= 1) {
            return "";
        }

        if (
            row.some((field) => field.width === "short") &&
            row.some((field) => field.width === "right")
        ) {
            return "grid grid-cols-1 gap-4 sm:grid-cols-[minmax(0,10rem)_minmax(0,1fr)]";
        }

        if (row.some((field) => field.width === "short")) {
            return "grid grid-cols-1 gap-4 sm:grid-cols-[minmax(0,10rem)_minmax(0,1fr)]";
        }

        return "grid grid-cols-1 gap-4 sm:grid-cols-2";
    },
    fieldWrapperClass(field) {
        if (field.width === "short") {
            return "min-w-0";
        }

        if (field.width === "right") {
            return "min-w-0";
        }

        return "";
    },
    recordClass(record) {
        const baseClass = asString(
            this.section.recordClass,
            "rounded-xl border border-gray-100 bg-gray-50 p-3 sm:p-4",
        );
        const adapterClass =
            typeof this.adapters.recordClass === "function"
                ? asString(
                      this.adapters.recordClass({
                          record,
                          component: this,
                          section: this.section,
                      }),
                  )
                : "";
        const recordLevelClass = asString(record.rowClass);
        const mobileClass = mobileRecordClass(baseClass);

        return [
            "relative",
            "max-sm:border-t-0 sm:mt-0",
            mobileClass,
            mobileRecordClass(adapterClass),
            mobileRecordClass(recordLevelClass),
        ]
            .filter((value) => value !== "")
            .join(" ");
    },
    recordRowClass(record) {
        const baseClass = asString(
            this.section.rowClass,
            this.section.inlineActionsOnMobile
                ? "flex flex-row items-center justify-between gap-4"
                : "flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between",
        );
        const recordLevelClass = asString(record.rowContainerClass);

        return [baseClass, recordLevelClass]
            .filter((value) => value !== "")
            .join(" ");
    },
    rightMetaColumnClass(record) {
        const baseClass = asString(
            this.section.rightMetaClass,
            "flex min-w-[5rem] flex-col items-end justify-center gap-2 text-right",
        );
        const recordLevelClass = asString(record.rightMetaClass);

        return [baseClass, recordLevelClass]
            .filter((value) => value !== "")
            .join(" ");
    },
    rightMetaLabelClass(meta) {
        const baseClass = meta.compactOnMobile
            ? "text-[0.65rem] font-semibold uppercase tracking-wide text-gray-500 sm:text-sm sm:font-normal sm:normal-case sm:tracking-normal"
            : "text-gray-500";

        return [baseClass, asString(meta.labelClass)]
            .filter((value) => value !== "")
            .join(" ");
    },
    rightMetaControlLabelClass(meta) {
        return meta.compactOnMobile
            ? "flex items-center gap-1.5 text-xs sm:gap-2.5 sm:text-sm"
            : "flex items-center gap-2.5 text-sm";
    },
    rightMetaRequiredClass() {
        return "shrink-0 text-[0.65rem] font-medium text-red-600";
    },
    rightMetaValueIsBlank(record, meta) {
        const value = record?.[meta.field];

        return value === null || value === undefined || String(value).trim() === "";
    },
    smartNumberMetaRootClass(meta) {
        return meta.compactOnMobile ? "w-20 sm:w-24" : "w-24";
    },
    smartNumberMetaFrameClass(meta) {
        const baseClass =
            "flex w-full items-center rounded-lg border border-gray-300 bg-white shadow-sm transition focus-within:border-blue-500 focus-within:ring-2 focus-within:ring-blue-500/20";

        return meta.compactOnMobile ? `${baseClass} min-h-8` : baseClass;
    },
    smartNumberMetaInputClass(meta) {
        const baseClass =
            "block min-w-0 flex-1 border-0 bg-transparent text-right text-gray-900 placeholder:text-gray-400 focus:outline-none focus:ring-0";
        const sizingClass = meta.compactOnMobile
            ? "px-2 py-1 text-xs sm:px-3 sm:py-1.5 sm:text-sm"
            : "px-3 py-1.5 text-sm";

        return `${baseClass} ${sizingClass}`;
    },
    updateSectionConfig(nextSection) {
        if (!nextSection || typeof nextSection !== "object") {
            return;
        }

        const normalizedSection = normalizeSectionConfig(nextSection);
        const currentToggleValues = asRecord(this.toggleValues);

        this.section = normalizedSection;
        this.toggleValues = normalizedSection.toolbarToggles.reduce(
            (carry, toggle) => {
                carry[toggle.key] = Object.prototype.hasOwnProperty.call(
                    currentToggleValues,
                    toggle.key,
                )
                    ? Boolean(currentToggleValues[toggle.key])
                    : Boolean(toggle.checked);

                return carry;
            },
            {},
        );

        if (
            !this.section.permissions.canCreate &&
            this.isFormOpen &&
            this.formMode === "create"
        ) {
            this.closeForm();
        }

        this.resetPagination();
        this.clampPaginationPage();
    },
    findField(fieldName) {
        return (
            this.section.fields.find((field) => field.name === fieldName) ||
            null
        );
    },
    async submitInlineCreate(field) {
        if (!field.inlineCreate.storeUrl || this.inlineCreateSubmitting) {
            return;
        }

        this.inlineCreateSubmitting = true;
        this.inlineCreateErrors = {};
        this.inlineCreateFormError = "";

        try {
            const response = await fetch(field.inlineCreate.storeUrl, {
                method: "POST",
                headers: {
                    "Content-Type": "application/json",
                    Accept: "application/json",
                    "X-CSRF-TOKEN": this.section.csrfToken,
                },
                body: JSON.stringify(this.inlineCreateForm),
            });

            if (response.status === 422) {
                const data = await response.json();
                this.inlineCreateErrors = asRecord(data.errors);
                this.inlineCreateFormError = asString(
                    data.message,
                    "Unable to create record.",
                );
                return;
            }

            if (!response.ok) {
                this.inlineCreateFormError = "Unable to create record.";
                return;
            }

            const data = await response.json();
            const createdId = data.data?.id;
            const createdName = asString(data.data?.company_name);

            if (
                createdId === null ||
                createdId === undefined ||
                createdName === ""
            ) {
                this.inlineCreateFormError = "Unable to create record.";
                return;
            }

            const targetField = this.findField(field.name);

            if (!targetField) {
                this.inlineCreateFormError = "Unable to create record.";
                return;
            }

            const optionValue = String(createdId);
            const existingOption = targetField.options.find(
                (option) => option.value === optionValue,
            );

            if (!existingOption) {
                targetField.options.push({
                    value: optionValue,
                    label: createdName,
                });
            }

            this.form[field.name] = optionValue;
            this.resetInlineCreateState();
        } catch (error) {
            this.inlineCreateFormError = "Unable to create record.";
        } finally {
            this.inlineCreateSubmitting = false;
        }
    },
    async fetchPage(page) {
        if (!this.section.endpoints.list) {
            return;
        }

        const params = new URLSearchParams();
        params.set("page", String(page));
        params.set("per_page", String(this.paginationPerPage()));

        if (typeof this.adapters.buildListParams === "function") {
            const adapterParams = this.adapters.buildListParams(
                this.toggleValues,
                this.section,
            );

            Object.entries(asRecord(adapterParams)).forEach(([key, value]) => {
                if (value === null || value === undefined || value === "") {
                    return;
                }

                params.set(key, String(value));
            });
        }
        this.isLoading = true;
        this.sectionError = "";

        try {
            const response = await fetch(
                `${this.section.endpoints.list}?${params.toString()}`,
                {
                    headers: {
                        Accept: "application/json",
                    },
                },
            );

            if (!response.ok) {
                this.sectionError = "Unable to load records.";
                return;
            }

            const data = await response.json();
            this.records = asArray(data.data).map((record) =>
                this.normalizeRow(record),
            );
            this.meta = {
                current_page: data.meta?.current_page || 1,
                last_page: data.meta?.last_page || 1,
                per_page: data.meta?.per_page || this.paginationPerPage(),
                total: data.meta?.total || 0,
            };
            this.hasLoaded = true;
            this.clampPaginationPage();
        } catch (error) {
            this.sectionError = "Unable to load records.";
        } finally {
            this.isLoading = false;
        }
    },
    openCreateForm() {
        if (!this.section.permissions.canCreate) {
            return;
        }

        if (
            this.section.createAction.type === "view" &&
            this.section.createAction.url !== ""
        ) {
            globalThis.location.assign(this.section.createAction.url);
            return;
        }

        if (
            this.section.createAction.type === "custom" &&
            typeof this.adapters.handleCreateAction === "function"
        ) {
            this.adapters.handleCreateAction({
                action: this.section.createAction,
                section: this.section,
                component: this,
            });
            return;
        }

        this.formMode = "create";
        this.editingId = null;
        this.form = buildEmptyForm(this.section);
        this.errors = {};
        this.formError = "";
        this.resetInlineCreateState();
        this.isFormOpen = true;
    },
    async toggleToolbar(key, checked = null) {
        this.toggleValues[key] =
            checked === null ? !this.toggleValues[key] : Boolean(checked);
        this.resetPagination();
        await this.fetchPage(1);
    },
    openEditForm(record) {
        this.formMode = "edit";
        this.editingId = record.id;
        const formValues = asRecord(
            record.formValues || record.form_values || record.raw || record,
        );

        this.form = buildEmptyForm(this.section);
        this.section.fields.forEach((field) => {
            const value = formValues[field.name];
            this.form[field.name] =
                value === null || value === undefined ? "" : String(value);
        });
        this.errors = {};
        this.formError = "";
        this.resetInlineCreateState();
        this.isFormOpen = true;
    },
    closeForm() {
        this.isFormOpen = false;
        this.isSubmitting = false;
        this.errors = {};
        this.formError = "";
        this.resetInlineCreateState();
    },
    createFormTitle() {
        if (this.formMode === "create") {
            return asString(this.section.createAction.title, "Create record");
        }

        return "Edit record";
    },
    createFormDescription() {
        if (this.formMode === "create") {
            return asString(
                this.section.createAction.description,
                this.section.title,
            );
        }

        return asString(this.section.title);
    },
    createFormSubmitLabel() {
        if (this.formMode === "create") {
            return asString(this.section.createAction.submitLabel, "Save");
        }

        return "Save";
    },
    buildCreatePayload() {
        if (typeof this.adapters.buildCreatePayload === "function") {
            return this.adapters.buildCreatePayload(this.form, this.section);
        }

        return this.form;
    },
    buildUpdatePayload(record) {
        if (typeof this.adapters.buildUpdatePayload === "function") {
            return this.adapters.buildUpdatePayload(
                this.form,
                record,
                this.section,
            );
        }

        return this.form;
    },
    async submitForm() {
        const record =
            this.records.find((entry) => entry.id === this.editingId) || null;
        const endpoint =
            this.formMode === "create"
                ? this.section.endpoints.create
                : resolveUrl(this.section.endpoints.update, this.editingId);
        const method = this.formMode === "create" ? "POST" : "PATCH";
        const body =
            this.formMode === "create"
                ? this.buildCreatePayload()
                : this.buildUpdatePayload(record);

        if (!endpoint) {
            return;
        }

        this.isSubmitting = true;
        this.errors = {};
        this.formError = "";

        try {
            const response = await fetch(endpoint, {
                method,
                headers: {
                    "Content-Type": "application/json",
                    Accept: "application/json",
                    "X-CSRF-TOKEN": this.section.csrfToken,
                },
                body: JSON.stringify(body),
            });

            if (response.status === 422) {
                const data = await response.json();
                this.errors = asRecord(data.errors);
                this.formError = asString(
                    data.message,
                    "Unable to save record.",
                );

                if (data.meta?.requires_conversion) {
                    this.openMissingConversionModal(data.meta, body);
                }

                return;
            }

            if (!response.ok) {
                this.formError = "Unable to save record.";
                return;
            }

            const data = await response.json();

            if (
                this.formMode === "create" &&
                typeof this.adapters.handleCreateSuccess === "function"
            ) {
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
            const nextPage =
                this.formMode === "create" ? 1 : this.paginationCurrentPage();

            if (this.formMode === "create") {
                this.resetPagination();
            }

            await this.fetchPage(nextPage);
        } catch (error) {
            this.formError = "Unable to save record.";
        } finally {
            this.isSubmitting = false;
        }
    },
    async performAction(record, action) {
        if (
            action.confirmMessage !== "" &&
            !globalThis.confirm(action.confirmMessage)
        ) {
            return;
        }

        switch (action.type) {
            case "view": {
                const targetUrl = asString(
                    resolvePathValue(record, action.urlField),
                );

                if (targetUrl !== "") {
                    globalThis.location.assign(targetUrl);
                }

                return;
            }
            case "edit":
                this.openEditForm(record);
                return;
            case "custom":
                if (typeof this.adapters.handleAction === "function") {
                    await this.adapters.handleAction({
                        action,
                        record,
                        component: this,
                    });
                }
                return;
            case "remove":
            case "archive":
            case "deactivate":
                break;
            default:
                if (typeof this.adapters.handleAction === "function") {
                    await this.adapters.handleAction({
                        action,
                        record,
                        component: this,
                    });
                }
                return;
        }

        const endpoint = resolveUrl(
            this.section.endpoints[action.endpointKey],
            record.id,
        );

        if (!endpoint) {
            return;
        }

        this.sectionError = "";

        try {
            const response = await fetch(endpoint, {
                method: action.method,
                headers: {
                    Accept: "application/json",
                    "X-CSRF-TOKEN": this.section.csrfToken,
                },
            });

            if (!response.ok) {
                this.sectionError = "Unable to update record.";
                return;
            }

            await this.fetchPage(this.meta.current_page || 1);
            this.clampPaginationPage();
        } catch (error) {
            this.sectionError = "Unable to update record.";
        }
    },
    smartNumberPrecision(record, meta) {
        const precisionField = asString(meta.precisionField);
        const configuredPrecision = meta.precision;

        if (precisionField !== "") {
            const precision = parseInt(
                resolvePathValue(record, precisionField, configuredPrecision),
                10,
            );

            if (Number.isInteger(precision)) {
                return precision;
            }
        }

        const precision = parseInt(configuredPrecision, 10);

        return Number.isInteger(precision) ? precision : 0;
    },
    smartNumberFieldPrecision(field) {
        const precision = parseInt(field.precision, 10);

        return Number.isInteger(precision) ? precision : 0;
    },
    smartNumberFieldCurrency(field) {
        const configuredCurrency = asString(field.currency);

        if (configuredCurrency !== "") {
            return configuredCurrency.toUpperCase();
        }

        const currencyFromField = asString(field.currencyFromField);

        if (currencyFromField === "") {
            return "";
        }

        const selectedValue = asString(this.form[currencyFromField]);
        const sourceField = this.section.fields.find(
            (candidate) => candidate.name === currencyFromField,
        );

        if (!sourceField || selectedValue === "") {
            return "";
        }

        const selectedOption = sourceField.options.find(
            (option) => asString(option.value) === selectedValue,
        );
        const optionCurrencyField = asString(
            field.currencyOptionField,
            "currency_code",
        );
        const optionCurrency = asString(selectedOption?.[optionCurrencyField]);

        return optionCurrency.toUpperCase();
    },
    syncSmartNumberMetaValue(record, meta, rawValue) {
        if (!meta.field) {
            return;
        }

        record[meta.field] = rawValue;
    },
    async handleSmartNumberMetaChanged(record, meta, detail) {
        this.syncSmartNumberMetaValue(record, meta, detail?.rawValue);
        await this.performInlineMetaAction(record, meta);
    },
    async performInlineMetaAction(record, meta) {
        if (typeof this.adapters.handleInlineMetaAction === "function") {
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
    targetEl.classList.add(
        "!-mt-px",
        "first:!mt-0",
        "sm:!mt-6",
        "sm:first:!mt-0",
    );
    targetEl.innerHTML = renderCrudSection();

    Alpine.data("jsCrudSection", (el) =>
        createSectionState(
            el.closest("[data-js-crud-section-root]")?._jsCrudSectionConfig ||
                section,
            el.closest("[data-js-crud-section-root]")?._jsCrudSectionAdapters ||
                adapters,
            el,
        ),
    );
}
