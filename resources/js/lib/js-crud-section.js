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

    return {
        name: asString(safeField.name),
        label: asString(safeField.label),
        type: asString(safeField.type, 'text'),
        required: Boolean(safeField.required),
        options: asArray(safeField.options).map((option) => ({
            value: asString(option?.value),
            label: asString(option?.label),
        })),
    };
};

const normalizeAction = (action) => {
    const safeAction = asRecord(action);

    return {
        id: asString(safeAction.id),
        label: asString(safeAction.label),
        type: asString(safeAction.type, asString(safeAction.id)),
        tone: asString(safeAction.tone, 'default'),
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
        suffixField: asString(safeEntry.suffixField),
        fallback: asString(safeEntry.fallback, '—'),
        toneField: asString(safeEntry.toneField),
        strong: asBoolean(safeEntry.strong),
    };
};

const normalizeSectionConfig = (config) => {
    const safeConfig = asRecord(config);
    const endpoints = asRecord(safeConfig.endpoints);
    const permissions = asRecord(safeConfig.permissions);
    const rowLayout = asRecord(safeConfig.rowLayout);

    return {
        resource: asString(safeConfig.resource),
        title: asString(safeConfig.title, 'Section'),
        description: asString(safeConfig.description),
        emptyState: asString(safeConfig.emptyState, 'No records found.'),
        csrfToken: asString(safeConfig.csrfToken),
        defaultOpen: asBoolean(safeConfig.defaultOpen),
        permissions: {
            canCreate: Boolean(permissions.canCreate),
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

const fieldMarkup = `
    <template x-for="field in section.fields" :key="field.name">
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

            <template x-if="field.type !== 'select'">
                <input
                    type="text"
                    class="mt-1 block w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500"
                    :id="\`section-field-\${field.name}\`"
                    x-model="form[field.name]"
                />
            </template>

            <p class="mt-1 text-xs text-red-600" x-text="firstError(field.name)"></p>
        </div>
    </template>
`;

const actionMenuMarkup = `
    <div
        class="relative inline-flex"
        x-data="{ open: false }"
        x-show="visibleActions(record).length > 0"
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
            class="absolute right-0 z-20 mt-2 w-40 rounded-md border border-gray-200 bg-white py-1 shadow-lg"
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

const renderCrudSection = () => `
    <section
        class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm"
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
                <div class="flex justify-end" data-js-crud-section-create-wrapper>
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

            <div class="space-y-3" x-show="records.length > 0">
                <template x-for="record in records" :key="record.id">
                    <article class="rounded-xl border border-gray-100 bg-gray-50 p-3 sm:p-4">
                        <div class="flex flex-col sm:flex-row gap-4 sm:items-start sm:justify-between">
                            <div class="min-w-0 flex-1">
                                <div class="flex items-center gap-3">
                                    <p class="truncate text-sm font-semibold text-gray-900" x-text="primaryText(record)"></p>
                                    <template x-for="badge in badgeItems(record)" :key="\`\${record.id}-\${badge.text}-badge\`">
                                        <span
                                            class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-medium"
                                            :class="badge.toneClass"
                                            x-text="badge.text"
                                        ></span>
                                    </template>
                                </div>
                                <template x-for="line in secondaryFieldItems(record)" :key="\`\${record.id}-\${line.label}-secondary\`">
                                    <p class="mt-1 text-sm text-gray-600">
                                        <template x-if="line.label">
                                            <span class="text-gray-500" x-text="\`\${line.label}: \`"></span>
                                        </template>
                                        <span class="text-gray-700" x-text="line.text"></span>
                                    </p>
                                </template>
                            </div>

                            <div class="flex items-start justify-between gap-3 sm:justify-end">
                                <div class="text-left sm:text-right">
                                    <template x-for="meta in rightMetaItems(record)" :key="\`\${record.id}-\${meta.label}-meta\`">
                                        <p class="text-sm" :class="meta.strong ? 'font-semibold text-gray-900' : 'text-gray-600'">
                                            <template x-if="meta.label">
                                                <span class="text-gray-500" x-text="\`\${meta.label}: \`"></span>
                                            </template>
                                            <span x-text="meta.text"></span>
                                        </p>
                                    </template>
                                </div>
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
                        <h4 class="text-lg font-semibold text-gray-900" x-text="formMode === 'create' ? 'Create record' : 'Edit record'"></h4>
                        <p class="mt-1 text-sm text-gray-500" x-text="section.title"></p>
                    </div>
                    <button type="button" class="text-sm text-gray-500 transition hover:text-gray-700" x-on:click="closeForm()">Close</button>
                </div>

                <div class="flex-1 space-y-5 px-4 py-5 sm:px-6">
                    <p class="text-sm text-red-600" x-show="formError" x-text="formError"></p>
                    ${fieldMarkup}
                </div>

                <div class="flex items-center justify-end gap-3 border-t border-gray-100 px-4 py-4 sm:px-6">
                    <button type="button" class="rounded-lg border border-gray-300 px-4 py-2 text-sm text-gray-700 transition hover:bg-gray-50" x-on:click="closeForm()">Cancel</button>
                    <button type="button" class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white transition hover:bg-blue-500 disabled:cursor-not-allowed disabled:opacity-50" x-bind:disabled="isSubmitting" x-on:click="submitForm()">
                        Save
                    </button>
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

const createSectionState = (section, adapters) => ({
    section,
    adapters,
    isOpen: asBoolean(section.defaultOpen),
    hasLoaded: false,
    isLoading: false,
    isFormOpen: false,
    isSubmitting: false,
    formMode: 'create',
    editingId: null,
    records: [],
    meta: {
        current_page: 1,
        last_page: 1,
        per_page: 10,
        total: 0,
    },
    form: buildEmptyForm(section),
    errors: {},
    sectionError: '',
    formError: '',
    init() {
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
    secondaryFieldItems(record) {
        return this.section.rowLayout.secondaryFields.map((entry) => ({
            label: entry.label,
            text: buildLayoutText(record, entry),
        }));
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
        return this.section.rowLayout.rightMeta.map((entry) => ({
            label: entry.label,
            text: buildLayoutText(record, entry),
            strong: entry.strong,
        }));
    },
    visibleActions(record) {
        const availableActions = asArray(record.availableActions || record.available_actions);

        if (availableActions.length === 0) {
            return this.section.actions;
        }

        return this.section.actions.filter((action) => availableActions.includes(action.id));
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
    async fetchPage(page) {
        if (!this.section.endpoints.list) {
            return;
        }

        const params = new URLSearchParams();
        params.set('page', String(page));
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

        this.formMode = 'create';
        this.editingId = null;
        this.form = buildEmptyForm(this.section);
        this.errors = {};
        this.formError = '';
        this.isFormOpen = true;
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
        this.isFormOpen = true;
    },
    closeForm() {
        this.isFormOpen = false;
        this.isSubmitting = false;
        this.errors = {};
        this.formError = '';
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
                return;
            }

            if (!response.ok) {
                this.formError = 'Unable to save record.';
                return;
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
        el.closest('[data-js-crud-section-root]')?._jsCrudSectionAdapters || adapters
    ));
}
