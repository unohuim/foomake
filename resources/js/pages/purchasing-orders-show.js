export function mount(rootEl, payload) {
    const Alpine = window.Alpine;
    const safePayload = payload || {};
    const workflowActionLoadingEvent = 'workflow-action-button-loading';

    const emptyHeaderErrors = () => ({
        supplier_id: [],
        assigned_to_user_id: [],
        order_date: [],
        shipping_amount: [],
        po_number: [],
        notes: [],
    });

    const emptyLineErrors = () => ({
        item_id: [],
        item_purchase_option_id: [],
        pack_count: [],
        unit_price_cents: [],
        tax_percent: [],
        supplier_id: [],
    });

    const emptyEditErrors = () => ({
        pack_count: [],
        unit_price_cents: [],
        tax_percent: [],
    });

    const emptyReceiveErrors = () => ({
        received_at: [],
        reference: [],
        notes: [],
        lines: [],
    });

    const emptyShortCloseErrors = () => ({
        short_closed_at: [],
        reference: [],
        notes: [],
        short_closed_quantity: [],
    });

    const normalizeDecimal = (value) => {
        const raw = value === null || value === undefined ? '' : String(value).trim();
        if (raw === '') {
            return '0.000000';
        }

        const parts = raw.split('.');
        const whole = parts[0] === '' ? '0' : parts[0];
        const fraction = (parts[1] || '').padEnd(6, '0').slice(0, 6);

        return `${whole}.${fraction}`;
    };

    const normalizeId = (value) => (value === null || value === undefined ? '' : String(value));

    const escapeHtml = (value) => String(value ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');

    const setWorkflowActionLoading = (loading, error = '') => {
        window.dispatchEvent(new CustomEvent(workflowActionLoadingEvent, {
            detail: {
                loading,
                error,
            },
        }));
    };

    const initialSupplierId = normalizeId(safePayload.purchaseOrder?.supplier_id);

    const normalizedSuppliers = (safePayload.suppliers || []).map((supplier) => ({
        ...supplier,
        id: normalizeId(supplier.id),
    }));

    if (
        initialSupplierId !== ''
        && !normalizedSuppliers.some((supplier) => supplier.id === initialSupplierId)
        && safePayload.purchaseOrder?.supplier_name
    ) {
        normalizedSuppliers.push({
            id: initialSupplierId,
            company_name: safePayload.purchaseOrder.supplier_name,
        });
    }

    Alpine.data('purchasingOrdersShow', () => ({
        purchaseOrder: safePayload.purchaseOrder || {},
        workflow: safePayload.workflow || {},
        workflowProgressSteps: Array.isArray(safePayload.workflowProgressSteps)
            ? safePayload.workflowProgressSteps
            : [],
        lines: safePayload.lines || [],
        suppliers: normalizedSuppliers,
        purchaseOptions: safePayload.purchaseOptions || [],
        receipts: safePayload.receipts || [],
        shortClosures: safePayload.shortClosures || [],
        tenantCurrency: safePayload.tenantCurrency || 'USD',
        updateUrl: safePayload.updateUrl || '',
        deleteUrl: safePayload.deleteUrl || '',
        indexUrl: safePayload.indexUrl || '/purchasing/orders',
        lineStoreUrl: safePayload.lineStoreUrl || '',
        lineUpdateUrlBase: safePayload.lineUpdateUrlBase || '',
        lineDeleteUrlBase: safePayload.lineDeleteUrlBase || '',
        receiptStoreUrl: safePayload.receiptStoreUrl || '',
        shortCloseStoreUrl: safePayload.shortCloseStoreUrl || '',
        statusUpdateUrl: safePayload.statusUpdateUrl || '',
        canReceive: safePayload.canReceive || false,
        currentUserName: safePayload.currentUserName || '',
        csrfToken: safePayload.csrfToken || '',
        isEditable: Boolean(safePayload.purchaseOrder?.is_editable),
        isHeaderSubmitting: false,
        savedFields: {
            supplier_id: false,
            assigned_to_user_id: false,
            order_date: false,
            shipping_amount: false,
            po_number: false,
            notes: false,
        },
        savedFieldTimeouts: {},
        headerErrors: emptyHeaderErrors(),
        headerError: '',
        isLineSubmitting: false,
        lineErrors: emptyLineErrors(),
        lineError: '',
        editingLineId: null,
        editForm: {
            pack_count: 1,
            unit_price_cents: '',
            tax_percent: '',
        },
        editErrors: emptyEditErrors(),
        isEditSubmitting: false,
        savedLineFields: {},
        savedLineFieldTimeouts: {},
        savedLineFieldValues: {},
        savingLineFields: {},
        pendingLineFieldValues: {},
        isDeleteLineOpen: false,
        isDeleteLineSubmitting: false,
        deleteLineId: null,
        deleteLineLabel: '',
        deleteLineError: '',
        isDeleteOrderOpen: false,
        isDeleteOrderSubmitting: false,
        deleteOrderError: '',
        isReceiveOpen: false,
        receiveForm: {
            received_at: '',
            reference: '',
            notes: '',
            lines: [],
        },
        receiveErrors: emptyReceiveErrors(),
        receiveLineErrors: {},
        receiveError: '',
        isReceiveSubmitting: false,
        isShortCloseOpen: false,
        shortCloseForm: {
            short_closed_at: '',
            reference: '',
            notes: '',
            purchase_order_line_id: null,
            short_closed_quantity: '',
        },
        shortCloseLineLabel: '',
        shortCloseErrors: emptyShortCloseErrors(),
        shortCloseError: '',
        isShortCloseSubmitting: false,
        statusError: '',
        workflowTaskSavingIds: [],
        toast: {
            visible: false,
            message: '',
            type: 'success',
            timeoutId: null,
        },
        form: {
            supplier_id: initialSupplierId,
            assigned_to_user_id: normalizeId(safePayload.purchaseOrder?.assigned_to_user_id),
            order_date: safePayload.purchaseOrder?.order_date ?? '',
            shipping_amount: safePayload.purchaseOrder?.shipping_amount ?? '',
            po_number: safePayload.purchaseOrder?.po_number ?? '',
            notes: safePayload.purchaseOrder?.notes ?? '',
        },
        lineForm: {
            item_id: '',
            item_purchase_option_id: '',
            pack_count: 1,
            unit_price_cents: '',
            tax_percent: '0',
        },
        init() {
            this.form.supplier_id = normalizeId(this.purchaseOrder?.supplier_id);
            this.form.assigned_to_user_id = normalizeId(this.purchaseOrder?.assigned_to_user_id);

            this.$nextTick(() => {
                this.form.supplier_id = normalizeId(this.purchaseOrder?.supplier_id);
                this.form.assigned_to_user_id = normalizeId(this.purchaseOrder?.assigned_to_user_id);
            });

            this.$watch('lineForm.item_purchase_option_id', () => {
                this.handleOptionChange();
            });
        },
        normalizeErrors(errors, emptyFactory) {
            const defaults = emptyFactory();
            if (!errors || typeof errors !== 'object') {
                return defaults;
            }

            const normalized = { ...defaults };
            Object.keys(defaults).forEach((key) => {
                normalized[key] = Array.isArray(errors[key]) ? errors[key] : [];
            });

            return normalized;
        },
        showToast(type, message) {
            this.toast.type = type;
            this.toast.message = message;
            this.toast.visible = true;

            if (this.toast.timeoutId) {
                clearTimeout(this.toast.timeoutId);
            }

            this.toast.timeoutId = setTimeout(() => {
                this.toast.visible = false;
            }, 1500);
        },
        normalizeNullable(value) {
            if (value === '' || value === null || value === undefined) {
                return null;
            }

            return value;
        },
        normalizeNullableInt(value) {
            if (value === '' || value === null || value === undefined) {
                return null;
            }

            return Number(value);
        },
        formatMoney(cents) {
            const safeCents = cents ?? 0;
            return `${this.tenantCurrency} ${(safeCents / 100).toFixed(2)}`;
        },
        formatQuantity(value) {
            const raw = value === null || value === undefined ? '' : String(value);
            if (raw === '') {
                return '0';
            }

            return raw;
        },
        formatWholeQuantity(value) {
            const normalized = normalizeDecimal(value);
            const [whole, fraction = ''] = normalized.split('.');

            if (fraction !== '' && !/^0+$/.test(fraction)) {
                return this.formatQuantity(normalized);
            }

            return whole.replace(/^(-?)0+(?=\d)/, '$1');
        },
        lineFieldKey(line, field) {
            return `${line?.id || 'new'}:${field}`;
        },
        lineFieldSaved(line, field) {
            return Boolean(this.savedLineFields[this.lineFieldKey(line, field)]);
        },
        showLineFieldSaved(line, field) {
            const key = this.lineFieldKey(line, field);

            this.savedLineFields = {
                ...this.savedLineFields,
                [key]: true,
            };

            if (this.savedLineFieldTimeouts[key]) {
                clearTimeout(this.savedLineFieldTimeouts[key]);
            }

            this.savedLineFieldTimeouts[key] = setTimeout(() => {
                this.savedLineFields = {
                    ...this.savedLineFields,
                    [key]: false,
                };
                delete this.savedLineFieldTimeouts[key];
            }, 1000);
        },
        lineFieldValue(field, value) {
            if (field === 'pack_count') {
                return this.normalizeNullableInt(value);
            }

            if (field === 'tax_percent') {
                return this.normalizeNullable(value);
            }

            return value;
        },
        lineFieldComparableValue(field, value) {
            const normalized = this.lineFieldValue(field, value);

            return normalized === null || normalized === undefined ? '' : String(normalized);
        },
        applyLineFieldValue(line, field, value) {
            if (!line || !Object.prototype.hasOwnProperty.call(line, field)) {
                return;
            }

            line[field] = this.lineFieldValue(field, value);
        },
        get supplierOptions() {
            const supplierId = Number(this.form.supplier_id);
            if (!supplierId) {
                return [];
            }

            return this.purchaseOptions.filter((option) => option.supplier_id === supplierId);
        },
        get availableItems() {
            const seen = new Map();

            this.supplierOptions.forEach((option) => {
                if (!seen.has(option.item_id)) {
                    seen.set(option.item_id, {
                        id: option.item_id,
                        name: option.item_name || 'Item',
                    });
                }
            });

            return Array.from(seen.values());
        },
        get availableOptions() {
            const itemId = Number(this.lineForm.item_id);
            if (!itemId) {
                return this.supplierOptions.map((option) => this.decorateOption(option));
            }

            return this.supplierOptions
                .filter((option) => option.item_id === itemId)
                .map((option) => this.decorateOption(option));
        },
        get supplierPackageComboboxOptions() {
            if (!Number(this.form.supplier_id)) {
                return [];
            }

            return this.supplierOptions.map((option) => {
                const decorated = this.decorateOption(option);
                const price = this.formatMoney(option.current_price_cents ?? 0);

                return {
                    value: String(option.id),
                    label: decorated.label,
                    description: `${option.supplier_name || 'Supplier'} · ${price}`,
                };
            });
        },
        get canReceiveOrder() {
            const currentStage = this.workflow.currentStage || {};
            const status = this.purchaseOrder.persisted_status || this.purchaseOrder.status || '';
            const hasReceivableLine = this.lines.some((line) => normalizeDecimal(line.remaining_balance) !== '0.000000');
            const hasReceiveAction = this.statusMenuOptions()
                .some((action) => (action.action || action.type) === 'receive');

            return (currentStage.actionVerb === 'Receive' || hasReceiveAction)
                && ['CREATED', 'PARTIALLY_RECEIVED'].includes(status)
                && hasReceivableLine
                && !this.purchaseOrder.is_cancelled;
        },
        get canBackOrder() {
            return this.canReceiveOrder;
        },
        get canCancelOrder() {
            const actions = Array.isArray(this.workflow.actions) ? this.workflow.actions : [];

            return actions.some((action) => action.type === 'cancel')
                && !this.purchaseOrder.is_cancelled
                && !this.purchaseOrder.has_receipts;
        },
        statusMenuOptions() {
            return Array.isArray(this.workflow.actions) ? this.workflow.actions : [];
        },
        workflowUpdatedDetail(workflow, purchaseOrder, workflowProgressSteps = null) {
            const detail = {
                workflow,
                purchaseOrder,
            };

            if (Array.isArray(workflowProgressSteps)) {
                detail.workflowProgressSteps = workflowProgressSteps;
            }

            return detail;
        },
        workflowProgressHtml() {
            let steps = Array.isArray(this.workflowProgressSteps)
                ? this.workflowProgressSteps
                : [];

            steps = steps
                .map((step, index) => ({
                    label: String(step?.label || ''),
                    status: ['completed', 'current', 'upcoming'].includes(step?.status)
                        ? step.status
                        : 'upcoming',
                    url: step?.url || null,
                    current: Boolean(step?.current || step?.status === 'current'),
                    number: String(index + 1).padStart(2, '0'),
                }))
                .filter((step) => step.label !== '' && step.label !== 'DRAFT')
                .map((step, index) => ({
                    ...step,
                    number: String(index + 1).padStart(2, '0'),
                }));

            if (
                steps.length > 0
                && !steps.some((step) => step.current)
                && !steps.some((step) => step.status === 'completed')
            ) {
                steps = steps.map((step, index) => ({
                    ...step,
                    status: index === 0 ? 'current' : step.status,
                    current: index === 0,
                }));
            }

            if (steps.length === 0) {
                return '';
            }

            let activeMobileStep = steps.findIndex((step) => step.current || step.status === 'current');

            if (activeMobileStep < 0) {
                activeMobileStep = steps.findIndex((step) => step.status === 'upcoming');
            }

            if (activeMobileStep < 0) {
                activeMobileStep = Math.max(0, steps.length - 1);
            }

            return `
                <nav class="w-full" aria-label="Progress" x-data="{ activeWorkflowStep: ${activeMobileStep} }" x-cloak>
                    <div class="flex overflow-hidden rounded-md border border-gray-300 bg-white md:hidden" role="tablist" aria-label="Workflow stages" data-workflow-progress-mobile-tabs>
                        ${steps.map((step, index) => this.workflowProgressMobileStepHtml(step, index, index === steps.length - 1)).join('')}
                    </div>
                    <ol role="list" class="hidden divide-y divide-gray-300 rounded-md border border-gray-300 bg-white md:flex md:divide-y-0">
                        ${steps.map((step, index) => this.workflowProgressStepHtml(step, index === steps.length - 1)).join('')}
                    </ol>
                </nav>
            `;
        },
        workflowProgressMobileStepHtml(step, index, isLast) {
            const label = escapeHtml(step.label);
            const isCompleted = step.status === 'completed';
            const isCurrent = step.status === 'current' || step.current;
            let circleClass = 'border-gray-300 bg-white text-gray-500';

            if (isCompleted) {
                circleClass = 'border-indigo-600 bg-indigo-600 text-white';
            } else if (isCurrent) {
                circleClass = 'border-indigo-600 bg-white text-indigo-600';
            }
            const labelClass = isCurrent ? 'text-indigo-600' : 'text-gray-900';
            const circleContent = isCompleted
                ? `
                    <svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true" class="size-5 text-white">
                        <path fill-rule="evenodd" clip-rule="evenodd" d="M19.916 4.626a.75.75 0 0 1 .208 1.04l-9 13.5a.75.75 0 0 1-1.154.114l-6-6a.75.75 0 0 1 1.06-1.06l5.353 5.353 8.493-12.74a.75.75 0 0 1 1.04-.207Z"></path>
                    </svg>
                `
                : `<span class="text-sm font-semibold">${escapeHtml(step.number)}</span>`;
            const separator = isLast ? '' : `
                <span aria-hidden="true" class="pointer-events-none absolute right-0 top-0 h-full w-5 md:hidden">
                    <svg viewBox="0 0 22 80" fill="none" preserveAspectRatio="none" class="size-full text-gray-300">
                        <path d="M0 -2L20 40L0 82" stroke="currentcolor" vector-effect="non-scaling-stroke" stroke-linejoin="round"></path>
                    </svg>
                </span>
            `;

            return `
                <button
                    type="button"
                    role="tab"
                    class="relative min-h-16 overflow-hidden bg-white py-2 pl-3 pr-6 transition-[flex-basis,flex-grow] duration-300 ease-out will-change-[flex-basis]"
                    :class="activeWorkflowStep === ${index} ? 'basis-0 grow' : 'basis-16 grow-0'"
                    :aria-selected="activeWorkflowStep === ${index} ? 'true' : 'false'"
                    x-on:click="activeWorkflowStep = ${index}"
                >
                    <span class="flex h-full items-center" :class="activeWorkflowStep === ${index} ? 'justify-start gap-3' : 'justify-center'">
                        <span class="flex size-9 shrink-0 items-center justify-center rounded-full border-2 ${circleClass}">
                            ${circleContent}
                        </span>
                        <span
                            class="min-w-0 truncate text-left text-sm font-medium transition-[max-width,opacity,transform] duration-300 ease-out ${labelClass}"
                            :class="activeWorkflowStep === ${index} ? 'max-w-48 translate-x-0 opacity-100' : 'max-w-0 -translate-x-1 opacity-0'"
                        >${label}</span>
                    </span>
                    ${separator}
                </button>
            `;
        },
        workflowProgressStepHtml(step, isLast) {
            const label = escapeHtml(step.label);
            const separator = isLast ? '' : `
                <div aria-hidden="true" class="absolute right-0 top-0 hidden h-full w-5 md:block">
                    <svg viewBox="0 0 22 80" fill="none" preserveAspectRatio="none" class="size-full text-gray-300">
                        <path d="M0 -2L20 40L0 82" stroke="currentcolor" vector-effect="non-scaling-stroke" stroke-linejoin="round"></path>
                    </svg>
                </div>
            `;

            if (step.status === 'completed') {
                return `
                    <li class="relative md:flex md:flex-1">
                        <span class="group flex w-full items-center">
                            <span class="flex items-center px-4 py-3 text-sm font-medium sm:px-6">
                                <span class="flex size-9 shrink-0 items-center justify-center rounded-full bg-indigo-600 group-hover:bg-indigo-700">
                                    <svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true" class="size-5 text-white">
                                        <path fill-rule="evenodd" clip-rule="evenodd" d="M19.916 4.626a.75.75 0 0 1 .208 1.04l-9 13.5a.75.75 0 0 1-1.154.114l-6-6a.75.75 0 0 1 1.06-1.06l5.353 5.353 8.493-12.74a.75.75 0 0 1 1.04-.207Z"></path>
                                    </svg>
                                </span>
                                <span class="ml-4 text-sm font-medium text-gray-900">${label}</span>
                            </span>
                        </span>
                        ${separator}
                    </li>
                `;
            }

            if (step.status === 'current' || step.current) {
                return `
                    <li class="relative md:flex md:flex-1">
                        <span aria-current="step" class="flex w-full items-center px-4 py-3 text-sm font-medium sm:px-6">
                            <span class="flex size-9 shrink-0 items-center justify-center rounded-full border-2 border-indigo-600">
                                <span class="text-sm font-semibold text-indigo-600">${escapeHtml(step.number)}</span>
                            </span>
                            <span class="ml-4 text-sm font-medium text-indigo-600">${label}</span>
                        </span>
                        ${separator}
                    </li>
                `;
            }

            return `
                <li class="relative md:flex md:flex-1">
                    <span class="group flex w-full items-center">
                        <span class="flex items-center px-4 py-3 text-sm font-medium sm:px-6">
                            <span class="flex size-9 shrink-0 items-center justify-center rounded-full border-2 border-gray-300 group-hover:border-gray-400">
                                <span class="text-sm font-semibold text-gray-500 group-hover:text-gray-900">${escapeHtml(step.number)}</span>
                            </span>
                            <span class="ml-4 text-sm font-medium text-gray-500 group-hover:text-gray-900">${label}</span>
                        </span>
                    </span>
                    ${separator}
                </li>
            `;
        },
        workflowActionHandlers() {
            return {
                receive: () => this.openReceive(),
                short_close: () => {
                    const line = this.lines.find((entry) => this.canShortCloseLine(entry));

                    if (line) {
                        this.openShortCloseLine(line);
                    }
                },
            };
        },
        performStatusMenuAction(option) {
            if (!option || typeof option !== 'object') {
                return;
            }

            const action = option.action || option.type;
            const handlers = this.workflowActionHandlers();

            if (handlers[action]) {
                handlers[action](option);
                return;
            }

            if (action === 'back_order') {
                this.submitStatusAction(action);
                return;
            }

            if (option.endpoint) {
                this.submitWorkflowAction(option);
                return;
            }

            if (action) {
                this.submitStatusAction(action);
                return;
            }

            if (option.status) {
                this.submitStatus(option.status);
            }
        },
        handleWorkflowUpdated(detail) {
            const workflow = detail?.workflow || {};

            if (!workflow.status) {
                return;
            }

            const purchaseOrder = detail?.purchaseOrder || {};
            const persistedStatus = purchaseOrder.persisted_status
                || purchaseOrder.status
                || this.purchaseOrder.persisted_status
                || workflow.status;

            this.workflow = workflow;
            if (Array.isArray(detail?.workflowProgressSteps)) {
                this.workflowProgressSteps = detail.workflowProgressSteps;
            }
            this.purchaseOrder = {
                ...this.purchaseOrder,
                ...purchaseOrder,
                workflow_status: workflow.status,
                status: workflow.status,
                persisted_status: persistedStatus,
            };
            if (Object.prototype.hasOwnProperty.call(purchaseOrder, 'can_receive')) {
                this.canReceive = Boolean(purchaseOrder.can_receive);
            } else if (Object.prototype.hasOwnProperty.call(detail || {}, 'canReceive')) {
                this.canReceive = Boolean(detail.canReceive);
            }
            this.isEditable = this.purchaseOrder.is_editable;
        },
        async completeWorkflowTask(task) {
            if (!task?.can_complete || !task.complete_url || this.workflowTaskSavingIds.includes(task.id)) {
                return;
            }

            this.workflowTaskSavingIds.push(task.id);

            try {
                const response = await fetch(task.complete_url, {
                    method: 'PATCH',
                    headers: {
                        Accept: 'application/json',
                        'X-CSRF-TOKEN': this.csrfToken,
                    },
                });

                if (!response.ok) {
                    this.showToast('error', 'Unable to complete workflow task.');
                    return;
                }

                const data = await response.json();
                const updatedTask = data.data || {};
                const tasks = Array.isArray(this.workflow.currentStageTasks)
                    ? this.workflow.currentStageTasks
                    : [];

                this.workflow = {
                    ...this.workflow,
                    currentStageTasks: tasks.map((entry) => (
                        entry.id === task.id ? updatedTask : entry
                    )),
                };
                this.showToast('success', 'Workflow task completed.');
            } catch (error) {
                this.showToast('error', 'Unable to complete workflow task.');
            } finally {
                this.workflowTaskSavingIds = this.workflowTaskSavingIds.filter((id) => id !== task.id);
            }
        },
        decorateOption(option) {
            const quantity = option.pack_quantity_display || this.formatQuantity(option.pack_quantity);
            const uom = option.pack_uom_symbol || option.pack_uom_name || 'pack';
            return {
                ...option,
                label: `${option.item_name || 'Item'} (${quantity} ${uom})`,
            };
        },
        handleSupplierChange() {
            this.lineForm.item_id = '';
            this.lineForm.item_purchase_option_id = '';
            this.lineForm.pack_count = 1;
            this.lineForm.unit_price_cents = '';
            this.lineForm.tax_percent = '0';
            this.lineErrors = emptyLineErrors();
        },
        handleItemChange() {
            this.lineForm.item_purchase_option_id = '';
            this.lineForm.unit_price_cents = '';
            this.lineForm.tax_percent = '0';
            this.lineErrors = emptyLineErrors();
        },
        handleOptionChange() {
            const optionId = Number(this.lineForm.item_purchase_option_id);
            if (!optionId) {
                return;
            }

            const option = this.supplierOptions.find((entry) => entry.id === optionId);
            if (!option) {
                return;
            }

            this.lineForm.item_id = option.item_id;
            this.lineForm.unit_price_cents = option.current_price_cents ?? '';
            this.lineForm.pack_count = this.lineForm.pack_count || 1;
            this.lineForm.tax_percent = this.lineForm.tax_percent || '0';
        },
        lineLabel(line) {
            if (!line.pack_quantity) {
                return 'Pack';
            }

            const quantity = line.pack_quantity_display || this.formatQuantity(line.pack_quantity);
            const uom = line.pack_uom_symbol || line.pack_uom_name || 'pack';
            return `${quantity} ${uom} pack`;
        },
        lineSummary(line) {
            const unit = this.formatMoney(line.unit_price_cents);
            const subtotal = this.formatMoney(line.line_subtotal_cents);
            const packCount = line.pack_count_display || this.formatQuantity(line.pack_count);
            return `${unit} - Qty ${packCount} - Subtotal ${subtotal}`;
        },
        resetLineForm() {
            this.lineForm = {
                item_id: '',
                item_purchase_option_id: '',
                pack_count: 1,
                unit_price_cents: '',
                tax_percent: '0',
            };
        },
        receiptLineSummary(receipt) {
            const lineCount = receipt.lines_count ?? 0;
            const total = this.formatWholeQuantity(receipt.total_packs ?? '0.000000');
            return `${lineCount} lines, ${total} total packs`;
        },
        shortCloseLineSummary(shortClose) {
            const lineCount = shortClose.lines_count ?? 0;
            const total = this.formatQuantity(shortClose.total_packs ?? '0.000000');
            return `${lineCount} lines, ${total} total packs`;
        },
        canShortCloseLine(line) {
            return this.canReceiveOrder && normalizeDecimal(line.remaining_balance) !== '0.000000';
        },
        normalizeReceiveErrors(errors) {
            const defaults = emptyReceiveErrors();
            const normalized = { ...defaults };

            if (!errors || typeof errors !== 'object') {
                return normalized;
            }

            Object.keys(defaults).forEach((key) => {
                normalized[key] = Array.isArray(errors[key]) ? errors[key] : [];
            });

            const lineErrors = {};

            Object.keys(errors).forEach((key) => {
                if (key.startsWith('lines.')) {
                    const match = key.match(/^lines\.(\d+)\.received_quantity$/);
                    if (match) {
                        const index = Number(match[1]);
                        lineErrors[index] = Array.isArray(errors[key]) ? errors[key][0] : '';
                    }
                }
            });

            this.receiveLineErrors = lineErrors;

            return normalized;
        },
        receiveLineError(index) {
            if (!this.receiveLineErrors[index]) {
                return '';
            }

            return this.receiveLineErrors[index];
        },
        collapseReceiveDatePicker(event) {
            const input = event?.target;

            if (!input || typeof input.blur !== 'function') {
                return;
            }

            if (event.type === 'change' || input.value === '' || input.validity?.valid) {
                input.blur();
            }
        },
        openReceive() {
            if (!this.canReceive || !this.canReceiveOrder) {
                return;
            }

            const lines = this.lines
                .filter((line) => normalizeDecimal(line.remaining_balance) !== '0.000000')
                .map((line) => ({
                    id: line.id,
                    item_name: line.item_name,
                    ordered_quantity_display: line.pack_count_display,
                    received_quantity_display: line.received_sum_display,
                    remaining_balance: normalizeDecimal(line.remaining_balance),
                    remaining_balance_display: line.remaining_balance_display,
                    unit_context: this.lineLabel(line),
                    received_quantity: this.formatWholeQuantity(line.remaining_balance),
                }));

            if (lines.length === 0) {
                return;
            }

            this.receiveForm = {
                received_at: '',
                reference: '',
                notes: '',
                lines,
            };
            this.receiveErrors = emptyReceiveErrors();
            this.receiveLineErrors = {};
            this.receiveError = '';
            this.isReceiveOpen = true;
        },
        closeReceive() {
            this.isReceiveOpen = false;
            this.receiveForm = {
                received_at: '',
                reference: '',
                notes: '',
                lines: [],
            };
            this.receiveErrors = emptyReceiveErrors();
            this.receiveLineErrors = {};
            this.receiveError = '';
            this.isReceiveSubmitting = false;
        },
        openShortCloseLine(line) {
            if (!this.canShortCloseLine(line)) {
                return;
            }

            this.shortCloseForm = {
                short_closed_at: '',
                reference: '',
                notes: '',
                purchase_order_line_id: line.id,
                short_closed_quantity: normalizeDecimal(line.remaining_balance),
            };
            this.shortCloseLineLabel = line.item_name || 'Line';
            this.shortCloseErrors = emptyShortCloseErrors();
            this.shortCloseError = '';
            this.isShortCloseOpen = true;
        },
        closeShortClose() {
            this.isShortCloseOpen = false;
            this.shortCloseForm = {
                short_closed_at: '',
                reference: '',
                notes: '',
                purchase_order_line_id: null,
                short_closed_quantity: '',
            };
            this.shortCloseLineLabel = '';
            this.shortCloseErrors = emptyShortCloseErrors();
            this.shortCloseError = '';
            this.isShortCloseSubmitting = false;
        },
        updateDerivedStatus() {
            this.isEditable = Boolean(this.purchaseOrder.is_editable);
        },
        markSaved(field) {
            if (!Object.prototype.hasOwnProperty.call(this.savedFields, field)) {
                return;
            }

            this.savedFields[field] = true;

            if (this.savedFieldTimeouts[field]) {
                clearTimeout(this.savedFieldTimeouts[field]);
            }

            this.savedFieldTimeouts[field] = setTimeout(() => {
                this.savedFields[field] = false;
            }, 1000);
        },
        fieldPayload(field, detail = null) {
            const payloadData = {};
            const fieldValue = detail && Object.prototype.hasOwnProperty.call(detail, 'rawValue')
                ? detail.rawValue
                : this.form[field];

            if (field === 'supplier_id') {
                payloadData.supplier_id = this.normalizeNullableInt(this.form.supplier_id);
            }

            if (field === 'assigned_to_user_id') {
                payloadData.assigned_to_user_id = this.normalizeNullableInt(this.form.assigned_to_user_id);
            }

            if (field === 'order_date') {
                payloadData.order_date = this.normalizeNullable(this.form.order_date);
            }

            if (field === 'shipping_amount') {
                payloadData.shipping_amount = this.normalizeNullable(fieldValue);
                this.form.shipping_amount = payloadData.shipping_amount ?? '';
            }

            if (field === 'po_number') {
                payloadData.po_number = this.normalizeNullable(this.form.po_number);
            }

            if (field === 'notes') {
                payloadData.notes = this.normalizeNullable(this.form.notes);
            }

            return payloadData;
        },
        applyPurchaseOrderUpdate(updated, lines = null, preserveFormFields = []) {
            this.purchaseOrder = {
                ...this.purchaseOrder,
                ...updated,
            };

            const nextForm = {
                supplier_id: updated.supplier_id === null || updated.supplier_id === undefined
                    ? ''
                    : String(updated.supplier_id),
                assigned_to_user_id: updated.assigned_to_user_id === null || updated.assigned_to_user_id === undefined
                    ? ''
                    : String(updated.assigned_to_user_id),
                order_date: updated.order_date ?? '',
                shipping_amount: updated.shipping_amount ?? '',
                po_number: updated.po_number ?? '',
                notes: updated.notes ?? '',
            };

            preserveFormFields.forEach((field) => {
                if (Object.prototype.hasOwnProperty.call(this.form, field)) {
                    nextForm[field] = this.form[field];
                }
            });

            this.form = nextForm;

            if (Array.isArray(lines)) {
                this.lines = lines;
            }

            this.isEditable = Boolean(updated.is_editable);
        },
        async autosaveField(field, detail = null) {
            if (!this.isEditable || !this.updateUrl || this.isHeaderSubmitting) {
                return;
            }

            const payloadData = this.fieldPayload(field, detail);

            if (Object.keys(payloadData).length === 0) {
                return;
            }

            this.isHeaderSubmitting = true;
            this.headerErrors = emptyHeaderErrors();
            this.headerError = '';

            try {
                const response = await fetch(this.updateUrl, {
                    method: 'PATCH',
                    headers: {
                        'Content-Type': 'application/json',
                        Accept: 'application/json',
                        'X-CSRF-TOKEN': this.csrfToken,
                    },
                    body: JSON.stringify(payloadData),
                });

                if (response.status === 422) {
                    const data = await response.json();
                    this.headerErrors = this.normalizeErrors(data.errors, emptyHeaderErrors);
                    this.headerError = data.message || 'Unable to save field.';
                    return;
                }

                if (!response.ok) {
                    this.headerError = 'Unable to save field. Please try again.';
                    return;
                }

                const data = await response.json();
                const updated = data.data?.purchase_order || data.data || {};
                const preserveFormFields = field === 'shipping_amount' && detail?.source === 'input'
                    ? ['shipping_amount']
                    : [];

                this.applyPurchaseOrderUpdate(updated, data.data?.lines, preserveFormFields);

                if (field === 'supplier_id') {
                    this.handleSupplierChange();
                }

                this.markSaved(field);
            } catch (error) {
                // eslint-disable-next-line no-console
                console.error(error);
                this.headerError = 'Unable to save field. Please try again.';
            } finally {
                this.isHeaderSubmitting = false;
            }
        },
        async submitHeader() {
            if (!this.isEditable || !this.updateUrl || this.isHeaderSubmitting) {
                return;
            }

            this.isHeaderSubmitting = true;
            this.headerErrors = emptyHeaderErrors();
            this.headerError = '';

            const payloadData = {
                supplier_id: this.normalizeNullableInt(this.form.supplier_id),
                assigned_to_user_id: this.normalizeNullableInt(this.form.assigned_to_user_id),
                order_date: this.normalizeNullable(this.form.order_date),
                shipping_amount: this.normalizeNullable(this.form.shipping_amount),
                po_number: this.normalizeNullable(this.form.po_number),
                notes: this.normalizeNullable(this.form.notes),
            };

            try {
                const response = await fetch(this.updateUrl, {
                    method: 'PATCH',
                    headers: {
                        'Content-Type': 'application/json',
                        Accept: 'application/json',
                        'X-CSRF-TOKEN': this.csrfToken,
                    },
                    body: JSON.stringify(payloadData),
                });

                if (response.status === 422) {
                    const data = await response.json();
                    this.headerErrors = this.normalizeErrors(data.errors, emptyHeaderErrors);
                    this.headerError = data.message || 'Unable to save header.';
                    return;
                }

                if (!response.ok) {
                    this.headerError = 'Unable to save header. Please try again.';
                    return;
                }

                const data = await response.json();
                const updated = data.data?.purchase_order || data.data || {};

                this.applyPurchaseOrderUpdate(updated, data.data?.lines);
                this.handleSupplierChange();
                this.showToast('success', 'Header updated.');
            } catch (error) {
                // eslint-disable-next-line no-console
                console.error(error);
                this.headerError = 'Unable to save header. Please try again.';
            } finally {
                this.isHeaderSubmitting = false;
            }
        },
        async submitLine() {
            if (!this.isEditable || !this.lineStoreUrl || this.isLineSubmitting) {
                return;
            }

            this.isLineSubmitting = true;
            this.lineErrors = emptyLineErrors();
            this.lineError = '';
            this.handleOptionChange();

            const payloadData = {
                item_id: this.normalizeNullableInt(this.lineForm.item_id),
                item_purchase_option_id: this.normalizeNullableInt(this.lineForm.item_purchase_option_id),
                pack_count: this.normalizeNullableInt(this.lineForm.pack_count),
                unit_price_cents: this.normalizeNullableInt(this.lineForm.unit_price_cents),
                tax_percent: this.normalizeNullable(this.lineForm.tax_percent),
            };

            try {
                const response = await fetch(this.lineStoreUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        Accept: 'application/json',
                        'X-CSRF-TOKEN': this.csrfToken,
                    },
                    body: JSON.stringify(payloadData),
                });

                if (response.status === 422) {
                    const data = await response.json();
                    this.lineErrors = this.normalizeErrors(data.errors, emptyLineErrors);
                    this.lineError = data.message || 'Unable to add line.';
                    return;
                }

                if (!response.ok) {
                    this.lineError = 'Unable to add line. Please try again.';
                    return;
                }

                const data = await response.json();
                const line = data.data?.line;
                const totals = data.data?.purchase_order;

                if (line) {
                    this.lines.push(line);
                }

                if (totals) {
                    this.purchaseOrder.po_subtotal_cents = totals.po_subtotal_cents;
                    this.purchaseOrder.po_grand_total_cents = totals.po_grand_total_cents;
                    this.purchaseOrder.shipping_cents = totals.shipping_cents;
                    this.purchaseOrder.tax_cents = totals.tax_cents;
                }

                this.resetLineForm();
                this.showToast('success', 'Line added.');
            } catch (error) {
                // eslint-disable-next-line no-console
                console.error(error);
                this.lineError = 'Unable to add line. Please try again.';
            } finally {
                this.isLineSubmitting = false;
            }
        },
        openEditLine(line) {
            if (!this.isEditable) {
                return;
            }

            this.editingLineId = line.id;
            this.editForm = {
                pack_count: line.pack_count,
                unit_price_cents: line.unit_price_cents,
                tax_percent: line.tax_percent ?? '0',
            };
            this.editErrors = emptyEditErrors();
        },
        closeEditLine() {
            this.editingLineId = null;
            this.editForm = {
                pack_count: 1,
                unit_price_cents: '',
                tax_percent: '',
            };
            this.editErrors = emptyEditErrors();
        },
        async submitEditLine(line) {
            if (!this.isEditable || !this.lineUpdateUrlBase || this.isEditSubmitting) {
                return;
            }

            this.isEditSubmitting = true;
            this.editErrors = emptyEditErrors();

            const payloadData = {
                pack_count: this.normalizeNullableInt(this.editForm.pack_count),
                unit_price_cents: this.normalizeNullableInt(this.editForm.unit_price_cents),
                tax_percent: this.normalizeNullable(this.editForm.tax_percent),
            };

            try {
                const response = await fetch(`${this.lineUpdateUrlBase}/${line.id}`, {
                    method: 'PATCH',
                    headers: {
                        'Content-Type': 'application/json',
                        Accept: 'application/json',
                        'X-CSRF-TOKEN': this.csrfToken,
                    },
                    body: JSON.stringify(payloadData),
                });

                if (response.status === 422) {
                    const data = await response.json();
                    this.editErrors = this.normalizeErrors(data.errors, emptyEditErrors);
                    return;
                }

                if (!response.ok) {
                    this.showToast('error', 'Unable to update line.');
                    return;
                }

                const data = await response.json();
                const updatedLine = data.data?.line;
                const totals = data.data?.purchase_order;

                if (updatedLine) {
                    this.lines = this.lines.map((entry) => (entry.id === updatedLine.id ? updatedLine : entry));
                }

                if (totals) {
                    this.purchaseOrder.po_subtotal_cents = totals.po_subtotal_cents;
                    this.purchaseOrder.po_grand_total_cents = totals.po_grand_total_cents;
                    this.purchaseOrder.shipping_cents = totals.shipping_cents;
                    this.purchaseOrder.tax_cents = totals.tax_cents;
                }

                this.closeEditLine();
                this.showToast('success', 'Line updated.');
            } catch (error) {
                // eslint-disable-next-line no-console
                console.error(error);
                this.showToast('error', 'Unable to update line.');
            } finally {
                this.isEditSubmitting = false;
            }
        },
        async autosaveLineField(line, field, detail = null) {
            if (!this.isEditable || !line || !this.lineUpdateUrlBase || this.isEditSubmitting) {
                return;
            }

            const fieldValue = detail && Object.prototype.hasOwnProperty.call(detail, 'rawValue')
                ? detail.rawValue
                : line[field];
            const key = this.lineFieldKey(line, field);
            const comparableValue = this.lineFieldComparableValue(field, fieldValue);
            const previousValue = line[field];

            this.applyLineFieldValue(line, field, fieldValue);

            if (this.savingLineFields[key]) {
                this.pendingLineFieldValues = {
                    ...this.pendingLineFieldValues,
                    [key]: fieldValue,
                };

                return;
            }

            if (this.savedLineFieldValues[key] === comparableValue) {
                return;
            }

            this.savingLineFields = {
                ...this.savingLineFields,
                [key]: true,
            };

            const payloadData = {
                pack_count: this.normalizeNullableInt(line.pack_count),
                unit_price_cents: this.normalizeNullableInt(line.unit_price_cents),
                tax_percent: this.normalizeNullable(line.tax_percent),
            };

            if (field === 'pack_count') {
                payloadData.pack_count = this.normalizeNullableInt(fieldValue);
                line.pack_count = payloadData.pack_count;
            }

            if (field === 'tax_percent') {
                payloadData.tax_percent = this.normalizeNullable(fieldValue);
                line.tax_percent = payloadData.tax_percent;
            }

            try {
                const response = await fetch(`${this.lineUpdateUrlBase}/${line.id}`, {
                    method: 'PATCH',
                    headers: {
                        'Content-Type': 'application/json',
                        Accept: 'application/json',
                        'X-CSRF-TOKEN': this.csrfToken,
                    },
                    body: JSON.stringify(payloadData),
                });

                if (response.status === 422) {
                    const data = await response.json();
                    if (!Object.prototype.hasOwnProperty.call(this.pendingLineFieldValues, key)) {
                        this.applyLineFieldValue(line, field, previousValue);
                    }
                    this.showToast('error', data.message || `Unable to update ${field}.`);
                    return;
                }

                if (!response.ok) {
                    if (!Object.prototype.hasOwnProperty.call(this.pendingLineFieldValues, key)) {
                        this.applyLineFieldValue(line, field, previousValue);
                    }
                    this.showToast('error', `Unable to update ${field}.`);
                    return;
                }

                const data = await response.json();
                const updatedLine = data.data?.line;
                const totals = data.data?.purchase_order;
                const hasPendingValue = Object.prototype.hasOwnProperty.call(this.pendingLineFieldValues, key);

                if (updatedLine) {
                    const nextLine = {
                        ...updatedLine,
                        [field]: hasPendingValue
                            ? this.lineFieldValue(field, this.pendingLineFieldValues[key])
                            : payloadData[field],
                    };

                    this.lines = this.lines.map((entry) => (entry.id === updatedLine.id ? nextLine : entry));
                }

                if (totals && !hasPendingValue) {
                    this.purchaseOrder.po_subtotal_cents = totals.po_subtotal_cents;
                    this.purchaseOrder.po_grand_total_cents = totals.po_grand_total_cents;
                    this.purchaseOrder.shipping_cents = totals.shipping_cents;
                    this.purchaseOrder.tax_cents = totals.tax_cents;
                }

                this.savedLineFieldValues = {
                    ...this.savedLineFieldValues,
                    [key]: comparableValue,
                };

                if (!hasPendingValue) {
                    this.showLineFieldSaved(updatedLine || line, field);
                }
            } catch (error) {
                // eslint-disable-next-line no-console
                console.error(error);
                if (!Object.prototype.hasOwnProperty.call(this.pendingLineFieldValues, key)) {
                    this.applyLineFieldValue(line, field, previousValue);
                }
                this.showToast('error', `Unable to update ${field}.`);
            } finally {
                this.savingLineFields = {
                    ...this.savingLineFields,
                    [key]: false,
                };

                if (Object.prototype.hasOwnProperty.call(this.pendingLineFieldValues, key)) {
                    const pendingValue = this.pendingLineFieldValues[key];
                    const pendingValues = { ...this.pendingLineFieldValues };
                    delete pendingValues[key];
                    this.pendingLineFieldValues = pendingValues;

                    const latestLine = this.lines.find((entry) => entry.id === line.id) || line;
                    this.autosaveLineField(latestLine, field, { rawValue: pendingValue });
                }
            }
        },
        openDeleteLine(line) {
            if (!this.isEditable) {
                return;
            }

            this.deleteLineId = line.id;
            this.deleteLineLabel = line.item_name || 'Line';
            this.deleteLineError = '';
            this.isDeleteLineOpen = true;
        },
        closeDeleteLine() {
            this.isDeleteLineOpen = false;
            this.isDeleteLineSubmitting = false;
            this.deleteLineId = null;
            this.deleteLineLabel = '';
            this.deleteLineError = '';
        },
        async confirmDeleteLine() {
            if (!this.isEditable || !this.deleteLineId || !this.lineDeleteUrlBase) {
                return;
            }

            this.isDeleteLineSubmitting = true;
            this.deleteLineError = '';

            try {
                const response = await fetch(`${this.lineDeleteUrlBase}/${this.deleteLineId}`, {
                    method: 'DELETE',
                    headers: {
                        Accept: 'application/json',
                        'X-CSRF-TOKEN': this.csrfToken,
                    },
                });

                if (response.status === 422) {
                    const data = await response.json();
                    this.deleteLineError = data.message || 'Unable to delete line.';
                    return;
                }

                if (!response.ok) {
                    this.deleteLineError = 'Unable to delete line.';
                    return;
                }

                const data = await response.json();
                const totals = data.data?.purchase_order;

                this.lines = Array.isArray(data.data?.lines)
                    ? data.data.lines
                    : this.lines.filter((entry) => entry.id !== this.deleteLineId);

                if (totals) {
                    this.purchaseOrder.po_subtotal_cents = totals.po_subtotal_cents;
                    this.purchaseOrder.po_grand_total_cents = totals.po_grand_total_cents;
                    this.purchaseOrder.shipping_cents = totals.shipping_cents;
                    this.purchaseOrder.tax_cents = totals.tax_cents;
                }

                this.closeDeleteLine();
                this.showToast('success', 'Line removed.');
            } catch (error) {
                // eslint-disable-next-line no-console
                console.error(error);
                this.deleteLineError = 'Unable to delete line.';
            } finally {
                this.isDeleteLineSubmitting = false;
            }
        },
        async deleteLine(line) {
            if (!this.isEditable || !line || !this.lineDeleteUrlBase || this.isDeleteLineSubmitting) {
                return;
            }

            this.isDeleteLineSubmitting = true;
            this.deleteLineError = '';

            try {
                const response = await fetch(`${this.lineDeleteUrlBase}/${line.id}`, {
                    method: 'DELETE',
                    headers: {
                        Accept: 'application/json',
                        'X-CSRF-TOKEN': this.csrfToken,
                    },
                });

                if (response.status === 422) {
                    const data = await response.json();
                    this.showToast('error', data.message || 'Unable to delete line.');
                    return;
                }

                if (!response.ok) {
                    this.showToast('error', 'Unable to delete line.');
                    return;
                }

                const data = await response.json();
                const totals = data.data?.purchase_order;

                this.lines = Array.isArray(data.data?.lines)
                    ? data.data.lines
                    : this.lines.filter((entry) => entry.id !== line.id);

                if (totals) {
                    this.purchaseOrder.po_subtotal_cents = totals.po_subtotal_cents;
                    this.purchaseOrder.po_grand_total_cents = totals.po_grand_total_cents;
                    this.purchaseOrder.shipping_cents = totals.shipping_cents;
                    this.purchaseOrder.tax_cents = totals.tax_cents;
                }
            } catch (error) {
                // eslint-disable-next-line no-console
                console.error(error);
                this.showToast('error', 'Unable to delete line.');
            } finally {
                this.isDeleteLineSubmitting = false;
            }
        },
        openDeleteOrder() {
            if (!this.isEditable) {
                return;
            }

            this.isDeleteOrderOpen = true;
            this.deleteOrderError = '';
        },
        closeDeleteOrder() {
            this.isDeleteOrderOpen = false;
            this.isDeleteOrderSubmitting = false;
            this.deleteOrderError = '';
        },
        async confirmDeleteOrder() {
            if (!this.isEditable || !this.deleteUrl || this.isDeleteOrderSubmitting) {
                return;
            }

            this.isDeleteOrderSubmitting = true;
            this.deleteOrderError = '';

            try {
                const response = await fetch(this.deleteUrl, {
                    method: 'DELETE',
                    headers: {
                        Accept: 'application/json',
                        'X-CSRF-TOKEN': this.csrfToken,
                    },
                });

                if (response.status === 422) {
                    const data = await response.json();
                    this.deleteOrderError = data.message || 'Unable to delete purchase order.';
                    return;
                }

                if (!response.ok) {
                    this.deleteOrderError = 'Unable to delete purchase order.';
                    return;
                }

                window.location.href = this.indexUrl;
            } catch (error) {
                // eslint-disable-next-line no-console
                console.error(error);
                this.deleteOrderError = 'Unable to delete purchase order.';
            } finally {
                this.isDeleteOrderSubmitting = false;
            }
        },
        async submitReceive() {
            if (!this.receiptStoreUrl || this.isReceiveSubmitting) {
                return;
            }

            this.isReceiveSubmitting = true;
            this.receiveErrors = emptyReceiveErrors();
            this.receiveLineErrors = {};
            this.receiveError = '';

            const payloadData = {
                received_at: this.receiveForm.received_at || null,
                reference: this.receiveForm.reference || null,
                notes: this.receiveForm.notes || null,
                lines: this.receiveForm.lines.map((line) => ({
                    purchase_order_line_id: line.id,
                    received_quantity: normalizeDecimal(line.received_quantity),
                })),
            };

            try {
                const response = await fetch(this.receiptStoreUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        Accept: 'application/json',
                        'X-CSRF-TOKEN': this.csrfToken,
                    },
                    body: JSON.stringify(payloadData),
                });

                if (response.status === 422) {
                    const data = await response.json();
                    this.receiveErrors = this.normalizeReceiveErrors(data.errors);
                    this.receiveError = data.message || 'Unable to receive order.';
                    return;
                }

                if (!response.ok) {
                    this.receiveError = 'Unable to receive order.';
                    return;
                }

                const data = await response.json();
                const responseData = data.data || {};

                if (responseData.purchase_order) {
                    this.purchaseOrder = {
                        ...this.purchaseOrder,
                        ...responseData.purchase_order,
                        status: responseData.purchase_order.status || this.purchaseOrder.status,
                    };

                    if (responseData.purchase_order.workflow_status) {
                        this.workflow = {
                            ...this.workflow,
                            status: responseData.purchase_order.workflow_status,
                        };
                    }
                }

                if (responseData.workflow) {
                    this.workflow = responseData.workflow;
                    if (Array.isArray(responseData.workflowProgressSteps)) {
                        this.workflowProgressSteps = responseData.workflowProgressSteps;
                    }
                    const workflowUpdatedDetail = this.workflowUpdatedDetail(
                        this.workflow,
                        responseData.purchase_order || {},
                        responseData.workflowProgressSteps
                    );

                    document.dispatchEvent(new CustomEvent('workflow-updated', {
                        detail: workflowUpdatedDetail,
                    }));
                    this.$root.dispatchEvent(new CustomEvent('workflow-updated', {
                        bubbles: true,
                        detail: workflowUpdatedDetail,
                    }));
                }

                if (Array.isArray(responseData.lines)) {
                    this.lines = responseData.lines;
                }

                if (Array.isArray(responseData.receipts)) {
                    this.receipts = responseData.receipts;
                }

                if (Object.prototype.hasOwnProperty.call(responseData, 'can_receive')) {
                    this.canReceive = Boolean(responseData.can_receive);
                }

                this.closeReceive();
                this.showToast('success', 'Receipt recorded.');
            } catch (error) {
                // eslint-disable-next-line no-console
                console.error(error);
                this.receiveError = 'Unable to receive order.';
            } finally {
                this.isReceiveSubmitting = false;
            }
        },
        async submitShortClose() {
            if (!this.shortCloseStoreUrl || this.isShortCloseSubmitting) {
                return;
            }

            this.isShortCloseSubmitting = true;
            this.shortCloseErrors = emptyShortCloseErrors();
            this.shortCloseError = '';

            const payloadData = {
                short_closed_at: this.shortCloseForm.short_closed_at || null,
                reference: this.shortCloseForm.reference || null,
                notes: this.shortCloseForm.notes || null,
                purchase_order_line_id: this.shortCloseForm.purchase_order_line_id,
                short_closed_quantity: normalizeDecimal(this.shortCloseForm.short_closed_quantity),
            };

            try {
                const response = await fetch(this.shortCloseStoreUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        Accept: 'application/json',
                        'X-CSRF-TOKEN': this.csrfToken,
                    },
                    body: JSON.stringify(payloadData),
                });

                if (response.status === 422) {
                    const data = await response.json();
                    this.shortCloseErrors = this.normalizeErrors(data.errors, emptyShortCloseErrors);
                    this.shortCloseError = data.message || 'Unable to short-close line.';
                    return;
                }

                if (!response.ok) {
                    this.shortCloseError = 'Unable to short-close line.';
                    return;
                }

                const data = await response.json();
                const responseData = data.data || {};

                if (responseData.purchase_order) {
                    this.purchaseOrder = {
                        ...this.purchaseOrder,
                        ...responseData.purchase_order,
                        status: responseData.purchase_order.status || this.purchaseOrder.status,
                    };

                    this.isEditable = Boolean(this.purchaseOrder.is_editable);
                }

                if (responseData.workflow) {
                    this.workflow = responseData.workflow;
                    if (Array.isArray(responseData.workflowProgressSteps)) {
                        this.workflowProgressSteps = responseData.workflowProgressSteps;
                    }
                    const workflowUpdatedDetail = this.workflowUpdatedDetail(
                        this.workflow,
                        responseData.purchase_order || {},
                        responseData.workflowProgressSteps
                    );

                    document.dispatchEvent(new CustomEvent('workflow-updated', {
                        detail: workflowUpdatedDetail,
                    }));
                    this.$root.dispatchEvent(new CustomEvent('workflow-updated', {
                        bubbles: true,
                        detail: workflowUpdatedDetail,
                    }));
                }

                if (Array.isArray(responseData.lines)) {
                    this.lines = responseData.lines;
                }

                if (Array.isArray(responseData.shortClosures)) {
                    this.shortClosures = responseData.shortClosures;
                }

                if (Object.prototype.hasOwnProperty.call(responseData, 'can_receive')) {
                    this.canReceive = Boolean(responseData.can_receive);
                }

                this.closeShortClose();
                this.showToast('success', 'Short close recorded.');
            } catch (error) {
                // eslint-disable-next-line no-console
                console.error(error);
                this.shortCloseError = 'Unable to short-close line.';
            } finally {
                this.isShortCloseSubmitting = false;
            }
        },
        async submitStatus(status) {
            if (!this.statusUpdateUrl) {
                setWorkflowActionLoading(false);
                return;
            }

            this.statusError = '';

            try {
                const response = await fetch(this.statusUpdateUrl, {
                    method: 'PATCH',
                    headers: {
                        'Content-Type': 'application/json',
                        Accept: 'application/json',
                        'X-CSRF-TOKEN': this.csrfToken,
                    },
                    body: JSON.stringify({ status }),
                });

                if (response.status === 422) {
                    const data = await response.json();
                    this.statusError = data.message || 'Unable to update status.';
                    this.showToast('error', this.statusError);
                    return;
                }

                if (!response.ok) {
                    this.statusError = 'Unable to update status.';
                    this.showToast('error', this.statusError);
                    return;
                }

                const data = await response.json();
                const responseData = data.data || {};
                const { workflow, workflowProgressSteps, ...purchaseOrderData } = responseData;

                this.purchaseOrder = {
                    ...this.purchaseOrder,
                    ...purchaseOrderData,
                    status: purchaseOrderData.status || status,
                    persisted_status: purchaseOrderData.persisted_status || this.purchaseOrder.persisted_status,
                    is_cancelled: Boolean(purchaseOrderData.is_cancelled),
                    is_back_ordered: Boolean(purchaseOrderData.is_back_ordered),
                };
                this.isEditable = Boolean(this.purchaseOrder.is_editable);
                if (workflow) {
                    this.workflow = workflow;
                    if (Array.isArray(workflowProgressSteps)) {
                        this.workflowProgressSteps = workflowProgressSteps;
                    }
                    const workflowUpdatedDetail = this.workflowUpdatedDetail(
                        workflow,
                        purchaseOrderData,
                        workflowProgressSteps
                    );

                    document.dispatchEvent(new CustomEvent('workflow-updated', {
                        detail: workflowUpdatedDetail,
                    }));
                    this.$root.dispatchEvent(new CustomEvent('workflow-updated', {
                        bubbles: true,
                        detail: workflowUpdatedDetail,
                    }));
                }
                this.showToast('success', 'Status updated.');
            } catch (error) {
                // eslint-disable-next-line no-console
                console.error(error);
                this.statusError = 'Unable to update status.';
                this.showToast('error', this.statusError);
            } finally {
                setWorkflowActionLoading(false);
            }
        },
        async submitStatusAction(action) {
            if (!this.statusUpdateUrl) {
                setWorkflowActionLoading(false);
                return;
            }

            this.statusError = '';

            try {
                const response = await fetch(this.statusUpdateUrl, {
                    method: 'PATCH',
                    headers: {
                        'Content-Type': 'application/json',
                        Accept: 'application/json',
                        'X-CSRF-TOKEN': this.csrfToken,
                    },
                    body: JSON.stringify({ action }),
                });

                if (response.status === 422) {
                    const data = await response.json();
                    this.statusError = data.message || 'Unable to apply action.';
                    this.showToast('error', this.statusError);
                    return;
                }

                if (!response.ok) {
                    this.statusError = 'Unable to apply action.';
                    this.showToast('error', this.statusError);
                    return;
                }

                const data = await response.json();
                this.applyWorkflowResponse(data.data || {}, {
                    status,
                });
                this.showToast('success', 'Action applied.');
            } catch (error) {
                // eslint-disable-next-line no-console
                console.error(error);
                this.statusError = 'Unable to apply action.';
                this.showToast('error', this.statusError);
            } finally {
                setWorkflowActionLoading(false);
            }
        },
        async submitWorkflowAction(option) {
            if (!option?.endpoint) {
                return;
            }

            this.statusError = '';

            try {
                const response = await fetch(option.endpoint, {
                    method: String(option.method || 'POST').toUpperCase(),
                    headers: {
                        Accept: 'application/json',
                        'X-CSRF-TOKEN': this.csrfToken,
                    },
                });

                if (response.status === 422) {
                    const data = await response.json();
                    this.statusError = data.message || 'Unable to apply action.';
                    this.showToast('error', this.statusError);
                    return;
                }

                if (!response.ok) {
                    this.statusError = 'Unable to apply action.';
                    this.showToast('error', this.statusError);
                    return;
                }

                const data = await response.json();
                this.applyWorkflowResponse(data.data || {});
                this.showToast('success', 'Action applied.');
            } catch (error) {
                // eslint-disable-next-line no-console
                console.error(error);
                this.statusError = 'Unable to apply action.';
                this.showToast('error', this.statusError);
            } finally {
                setWorkflowActionLoading(false);
            }
        },
        applyWorkflowResponse(responseData, fallback = {}) {
            const { workflow, workflowProgressSteps, ...purchaseOrderData } = responseData || {};

            this.purchaseOrder = {
                ...this.purchaseOrder,
                ...purchaseOrderData,
                status: purchaseOrderData.status || fallback.status || this.purchaseOrder.status,
                persisted_status: purchaseOrderData.persisted_status || this.purchaseOrder.persisted_status,
                is_cancelled: Boolean(purchaseOrderData.is_cancelled),
                is_back_ordered: Boolean(purchaseOrderData.is_back_ordered),
            };
            this.isEditable = Boolean(this.purchaseOrder.is_editable);

            if (workflow) {
                this.workflow = workflow;
                if (Array.isArray(workflowProgressSteps)) {
                    this.workflowProgressSteps = workflowProgressSteps;
                }

                const workflowUpdatedDetail = this.workflowUpdatedDetail(
                    workflow,
                    purchaseOrderData,
                    workflowProgressSteps
                );

                document.dispatchEvent(new CustomEvent('workflow-updated', {
                    detail: workflowUpdatedDetail,
                }));
                this.$root.dispatchEvent(new CustomEvent('workflow-updated', {
                    bubbles: true,
                    detail: workflowUpdatedDetail,
                }));
            }
        },
    }));
}
