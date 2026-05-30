export function mount(rootEl, payload) {
    const Alpine = window.Alpine;
    const safePayload = payload || {};

    const emptyHeaderErrors = () => ({
        supplier_id: [],
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
        toast: {
            visible: false,
            message: '',
            type: 'success',
            timeoutId: null,
        },
        form: {
            supplier_id: initialSupplierId,
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

            this.$nextTick(() => {
                this.form.supplier_id = normalizeId(this.purchaseOrder?.supplier_id);
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
            }, 2500);
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
            }, 1500);
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
            }, 1500);
        },
        fieldPayload(field) {
            const payloadData = {};

            if (field === 'supplier_id') {
                payloadData.supplier_id = this.normalizeNullableInt(this.form.supplier_id);
            }

            if (field === 'order_date') {
                payloadData.order_date = this.normalizeNullable(this.form.order_date);
            }

            if (field === 'shipping_amount') {
                payloadData.shipping_amount = this.normalizeNullable(this.form.shipping_amount);
            }

            if (field === 'po_number') {
                payloadData.po_number = this.normalizeNullable(this.form.po_number);
            }

            if (field === 'notes') {
                payloadData.notes = this.normalizeNullable(this.form.notes);
            }

            return payloadData;
        },
        applyPurchaseOrderUpdate(updated, lines = null) {
            this.purchaseOrder = {
                ...this.purchaseOrder,
                ...updated,
            };

            this.form = {
                supplier_id: updated.supplier_id === null || updated.supplier_id === undefined
                    ? ''
                    : String(updated.supplier_id),
                order_date: updated.order_date ?? '',
                shipping_amount: updated.shipping_amount ?? '',
                po_number: updated.po_number ?? '',
                notes: updated.notes ?? '',
            };

            if (Array.isArray(lines)) {
                this.lines = lines;
            }

            this.isEditable = Boolean(updated.is_editable);
        },
        async autosaveField(field) {
            if (!this.isEditable || !this.updateUrl || this.isHeaderSubmitting) {
                return;
            }

            const payloadData = this.fieldPayload(field);

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

                this.applyPurchaseOrderUpdate(updated, data.data?.lines);

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
        async autosaveLineField(line, field) {
            if (!this.isEditable || !line || !this.lineUpdateUrlBase || this.isEditSubmitting) {
                return;
            }

            this.isEditSubmitting = true;

            const payloadData = {
                pack_count: this.normalizeNullableInt(line.pack_count),
                unit_price_cents: this.normalizeNullableInt(line.unit_price_cents),
                tax_percent: this.normalizeNullable(line.tax_percent),
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
                    this.showToast('error', data.message || `Unable to update ${field}.`);
                    return;
                }

                if (!response.ok) {
                    this.showToast('error', `Unable to update ${field}.`);
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

                this.showLineFieldSaved(updatedLine || line, field);
            } catch (error) {
                // eslint-disable-next-line no-console
                console.error(error);
                this.showToast('error', `Unable to update ${field}.`);
            } finally {
                this.isEditSubmitting = false;
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
                    document.dispatchEvent(new CustomEvent('workflow-updated', {
                        detail: {
                            workflow: this.workflow,
                            purchaseOrder: responseData.purchase_order || {},
                        },
                    }));
                    this.$root.dispatchEvent(new CustomEvent('workflow-updated', {
                        bubbles: true,
                        detail: {
                            workflow: this.workflow,
                            purchaseOrder: responseData.purchase_order || {},
                        },
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

                window.location.reload();
                return;
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
                const { workflow, ...purchaseOrderData } = responseData;

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
                    document.dispatchEvent(new CustomEvent('workflow-updated', {
                        detail: {
                            workflow,
                            purchaseOrder: purchaseOrderData,
                        },
                    }));
                    this.$root.dispatchEvent(new CustomEvent('workflow-updated', {
                        bubbles: true,
                        detail: {
                            workflow,
                            purchaseOrder: purchaseOrderData,
                        },
                    }));
                }
                this.showToast('success', 'Status updated.');
            } catch (error) {
                // eslint-disable-next-line no-console
                console.error(error);
                this.statusError = 'Unable to update status.';
                this.showToast('error', this.statusError);
            }
        },
        async submitStatusAction(action) {
            if (!this.statusUpdateUrl) {
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
                const responseData = data.data || {};
                const { workflow, ...purchaseOrderData } = responseData;

                this.purchaseOrder = {
                    ...this.purchaseOrder,
                    ...purchaseOrderData,
                    status: purchaseOrderData.status || this.purchaseOrder.status,
                    persisted_status: purchaseOrderData.persisted_status || this.purchaseOrder.persisted_status,
                    is_cancelled: Boolean(purchaseOrderData.is_cancelled),
                    is_back_ordered: Boolean(purchaseOrderData.is_back_ordered),
                };
                this.isEditable = Boolean(this.purchaseOrder.is_editable);
                if (workflow) {
                    this.workflow = workflow;
                    document.dispatchEvent(new CustomEvent('workflow-updated', {
                        detail: {
                            workflow,
                            purchaseOrder: purchaseOrderData,
                        },
                    }));
                    this.$root.dispatchEvent(new CustomEvent('workflow-updated', {
                        bubbles: true,
                        detail: {
                            workflow,
                            purchaseOrder: purchaseOrderData,
                        },
                    }));
                }
                this.showToast('success', 'Action applied.');
            } catch (error) {
                // eslint-disable-next-line no-console
                console.error(error);
                this.statusError = 'Unable to apply action.';
                this.showToast('error', this.statusError);
            }
        },
    }));
}
