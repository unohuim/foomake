export function mount(rootEl, payload) {
    const Alpine = window.Alpine;
    const safePayload = payload || {};

    const emptyReceiveErrors = () => ({
        received_at: [],
        reference: [],
        notes: [],
        lines: [],
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


    Alpine.data('purchasingOrdersIndex', () => ({
        orders: safePayload.orders || [],
        storeUrl: safePayload.storeUrl || '',
        csrfToken: safePayload.csrfToken || '',
        tenantCurrency: safePayload.tenantCurrency || 'USD',
        canReceive: safePayload.canReceive || false,
        isCreating: false,
        actionMenuOpen: false,
        actionMenuOrderId: null,
        actionMenuTop: 0,
        actionMenuLeft: 0,
        isReceiveOpen: false,
        receiveOrderId: null,
        receiveOrderLabel: '',
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
        toast: {
            visible: false,
            message: '',
            type: 'success',
            timeoutId: null,
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
        formatMoney(cents) {
            if (cents === null || cents === undefined) {
                return `${this.tenantCurrency} 0.00`;
            }

            return `${this.tenantCurrency} ${(cents / 100).toFixed(2)}`;
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
        toggleActionMenu(event, orderId) {
            if (this.actionMenuOpen && this.actionMenuOrderId === orderId) {
                this.closeActionMenu();
                return;
            }

            const button = event.currentTarget;
            if (!button) {
                return;
            }

            const rect = button.getBoundingClientRect();

            this.actionMenuTop = rect.bottom;
            this.actionMenuLeft = rect.right;
            this.actionMenuOrderId = orderId;
            this.actionMenuOpen = true;
        },
        closeActionMenu() {
            this.actionMenuOpen = false;
            this.actionMenuOrderId = null;
            this.actionMenuTop = 0;
            this.actionMenuLeft = 0;
        },
        get actionMenuOrder() {
            return this.orders.find((order) => order.id === this.actionMenuOrderId) || null;
        },
        canReceiveOrder(order) {
            if (!order) {
                return false;
            }

            const status = order.persisted_status || order.status;
            const hasReceivableLine = (order.lines || [])
                .some((line) => normalizeDecimal(line.remaining_balance) !== '0.000000');

            return ['CREATED', 'PARTIALLY_RECEIVED'].includes(status)
                && hasReceivableLine
                && !order.is_cancelled;
        },
        canBackOrder(order) {
            if (!order) {
                return false;
            }

            return order.status === 'CREATED' && !order.is_cancelled;
        },
        canCancelOrder(order) {
            if (!order) {
                return false;
            }

            return ['DRAFT', 'CREATED', 'RECEIVED'].includes(order.status) && !order.is_cancelled;
        },
        openReceive(order) {
            if (!order || !this.canReceiveOrder(order)) {
                return;
            }

            this.receiveOrderId = order.id;
            this.receiveOrderLabel = order.po_number ? `PO #${order.po_number}` : 'Purchase order';
            this.receiveForm = {
                received_at: '',
                reference: '',
                notes: '',
                lines: (order.lines || [])
                    .filter((line) => normalizeDecimal(line.remaining_balance || '0.000000') !== '0.000000')
                    .map((line) => ({
                        id: line.id,
                        item_name: line.item_name,
                        ordered_quantity_display: line.pack_count_display,
                        received_quantity_display: line.received_sum_display,
                        remaining_balance: normalizeDecimal(line.remaining_balance || '0.000000'),
                        remaining_balance_display: line.remaining_balance_display,
                        unit_context: line.unit_context || 'Pack quantity',
                        received_quantity: this.formatWholeQuantity(line.remaining_balance || '0.000000'),
                    })),
            };
            this.receiveErrors = emptyReceiveErrors();
            this.receiveLineErrors = {};
            this.receiveError = '';
            this.isReceiveOpen = true;
        },
        openReceiveFromActionMenu() {
            const order = this.actionMenuOrder;
            this.closeActionMenu();

            if (!order) {
                return;
            }

            this.openReceive(order);
        },
        closeReceive() {
            this.isReceiveOpen = false;
            this.receiveOrderId = null;
            this.receiveOrderLabel = '';
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
        async submitReceive() {
            if (!this.receiveOrderId || this.isReceiveSubmitting) {
                return;
            }

            const order = this.orders.find((entry) => entry.id === this.receiveOrderId);
            if (!order) {
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
                lines: this.receiveForm.lines
                    .filter((line) => line.received_quantity !== '' && line.received_quantity !== null)
                    .map((line) => ({
                        purchase_order_line_id: line.id,
                        received_quantity: normalizeDecimal(line.received_quantity),
                    })),
            };

            try {
                const response = await fetch(order.receipt_url, {
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
                    order.persisted_status = responseData.purchase_order.persisted_status
                        || responseData.purchase_order.status
                        || order.persisted_status;
                    order.is_cancelled = Boolean(responseData.purchase_order.is_cancelled);
                    order.is_back_ordered = Boolean(responseData.purchase_order.is_back_ordered);

                    if (responseData.purchase_order.workflow_status) {
                        order.status = responseData.purchase_order.workflow_status;
                    }
                }

                if (Array.isArray(responseData.lines)) {
                    order.lines = responseData.lines;
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
        async submitStatus(order, status) {
            if (!order || !order.status_url) {
                return;
            }

            try {
                const response = await fetch(order.status_url, {
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
                    this.showToast('error', data.message || 'Unable to update status.');
                    return;
                }

                if (!response.ok) {
                    this.showToast('error', 'Unable to update status.');
                    return;
                }

                const data = await response.json();
                const updatedStatus = data.data?.status || status;
                order.status = updatedStatus;
                order.persisted_status = data.data?.persisted_status || order.persisted_status || updatedStatus;
                order.is_cancelled = Boolean(data.data?.is_cancelled);
                order.is_back_ordered = Boolean(data.data?.is_back_ordered);
                this.showToast('success', 'Status updated.');
            } catch (error) {
                // eslint-disable-next-line no-console
                console.error(error);
                this.showToast('error', 'Unable to update status.');
            }
        },
        submitStatusFromActionMenu(status) {
            const order = this.actionMenuOrder;
            this.closeActionMenu();

            if (!order) {
                return;
            }

            this.submitStatus(order, status);
        },
        async submitAction(order, action) {
            if (!order || !order.status_url) {
                return;
            }

            try {
                const response = await fetch(order.status_url, {
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
                    this.showToast('error', data.message || 'Unable to apply action.');
                    return;
                }

                if (!response.ok) {
                    this.showToast('error', 'Unable to apply action.');
                    return;
                }

                const data = await response.json();
                order.status = data.data?.status || order.status;
                order.persisted_status = data.data?.persisted_status || order.persisted_status || order.status;
                order.is_cancelled = Boolean(data.data?.is_cancelled);
                order.is_back_ordered = Boolean(data.data?.is_back_ordered);
                this.showToast('success', 'Action applied.');
            } catch (error) {
                // eslint-disable-next-line no-console
                console.error(error);
                this.showToast('error', 'Unable to apply action.');
            }
        },
        submitActionFromActionMenu(action) {
            const order = this.actionMenuOrder;
            this.closeActionMenu();

            if (!order) {
                return;
            }

            this.submitAction(order, action);
        },
        async createOrder() {
            if (!this.storeUrl || this.isCreating) {
                return;
            }

            this.isCreating = true;

            try {
                const response = await fetch(this.storeUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        Accept: 'application/json',
                        'X-CSRF-TOKEN': this.csrfToken,
                    },
                    body: JSON.stringify({}),
                });

                if (!response.ok) {
                    this.showToast('error', 'Unable to create purchase order.');
                    return;
                }

                const data = await response.json();
                const showUrl = data.data?.show_url;

                if (showUrl) {
                    window.location.href = showUrl;
                    return;
                }

                this.showToast('error', 'Purchase order created, but redirect failed.');
            } catch (error) {
                // eslint-disable-next-line no-console
                console.error(error);
                this.showToast('error', 'Unable to create purchase order.');
            } finally {
                this.isCreating = false;
            }
        },
    }));
}
