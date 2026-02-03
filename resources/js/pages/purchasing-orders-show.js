export function mount(rootEl, payload) {
    const Alpine = window.Alpine;
    const safePayload = payload || {};

    const emptyHeaderErrors = () => ({
        supplier_id: [],
        order_date: [],
        shipping_cents: [],
        tax_cents: [],
        po_number: [],
        notes: [],
    });

    const emptyLineErrors = () => ({
        item_id: [],
        item_purchase_option_id: [],
        pack_count: [],
        unit_price_cents: [],
        supplier_id: [],
    });

    const emptyEditErrors = () => ({
        pack_count: [],
        unit_price_cents: [],
    });

    Alpine.data('purchasingOrdersShow', () => ({
        purchaseOrder: safePayload.purchaseOrder || {},
        lines: safePayload.lines || [],
        suppliers: safePayload.suppliers || [],
        purchaseOptions: safePayload.purchaseOptions || [],
        tenantCurrency: safePayload.tenantCurrency || 'USD',
        updateUrl: safePayload.updateUrl || '',
        deleteUrl: safePayload.deleteUrl || '',
        indexUrl: safePayload.indexUrl || '/purchasing/orders',
        lineStoreUrl: safePayload.lineStoreUrl || '',
        lineUpdateUrlBase: safePayload.lineUpdateUrlBase || '',
        lineDeleteUrlBase: safePayload.lineDeleteUrlBase || '',
        csrfToken: safePayload.csrfToken || '',
        isEditable: (safePayload.purchaseOrder?.status || '') === 'DRAFT',
        isHeaderSubmitting: false,
        headerErrors: emptyHeaderErrors(),
        headerError: '',
        isLineSubmitting: false,
        lineErrors: emptyLineErrors(),
        lineError: '',
        editingLineId: null,
        editForm: {
            pack_count: 1,
            unit_price_cents: '',
        },
        editErrors: emptyEditErrors(),
        isEditSubmitting: false,
        isDeleteLineOpen: false,
        isDeleteLineSubmitting: false,
        deleteLineId: null,
        deleteLineLabel: '',
        deleteLineError: '',
        isDeleteOrderOpen: false,
        isDeleteOrderSubmitting: false,
        deleteOrderError: '',
        toast: {
            visible: false,
            message: '',
            type: 'success',
            timeoutId: null,
        },
        form: {
            supplier_id: safePayload.purchaseOrder?.supplier_id ?? '',
            order_date: safePayload.purchaseOrder?.order_date ?? '',
            shipping_cents: safePayload.purchaseOrder?.shipping_cents ?? '',
            tax_cents: safePayload.purchaseOrder?.tax_cents ?? '',
            po_number: safePayload.purchaseOrder?.po_number ?? '',
            notes: safePayload.purchaseOrder?.notes ?? '',
        },
        lineForm: {
            item_id: '',
            item_purchase_option_id: '',
            pack_count: 1,
            unit_price_cents: '',
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

            return raw.replace(/\.0+$/, '').replace(/(\.\d*[1-9])0+$/, '$1');
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
        decorateOption(option) {
            const quantity = this.formatQuantity(option.pack_quantity);
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
            this.lineErrors = emptyLineErrors();
        },
        handleItemChange() {
            this.lineForm.item_purchase_option_id = '';
            this.lineForm.unit_price_cents = '';
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
        },
        lineLabel(line) {
            if (!line.pack_quantity) {
                return 'Pack';
            }

            const quantity = this.formatQuantity(line.pack_quantity);
            const uom = line.pack_uom_symbol || line.pack_uom_name || 'pack';
            return `${quantity} ${uom} pack`;
        },
        lineSummary(line) {
            const unit = this.formatMoney(line.unit_price_cents);
            const subtotal = this.formatMoney(line.line_subtotal_cents);
            return `${unit} - Qty ${line.pack_count} - Subtotal ${subtotal}`;
        },
        resetLineForm() {
            this.lineForm = {
                item_id: '',
                item_purchase_option_id: '',
                pack_count: 1,
                unit_price_cents: '',
            };
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
                shipping_cents: this.normalizeNullableInt(this.form.shipping_cents),
                tax_cents: this.normalizeNullableInt(this.form.tax_cents),
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
                const updated = data.data || {};

                this.purchaseOrder = {
                    ...this.purchaseOrder,
                    ...updated,
                };

                this.form = {
                    supplier_id: updated.supplier_id ?? '',
                    order_date: updated.order_date ?? '',
                    shipping_cents: updated.shipping_cents ?? '',
                    tax_cents: updated.tax_cents ?? '',
                    po_number: updated.po_number ?? '',
                    notes: updated.notes ?? '',
                };

                this.isEditable = updated.status === 'DRAFT';
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

            const payloadData = {
                item_id: this.normalizeNullableInt(this.lineForm.item_id),
                item_purchase_option_id: this.normalizeNullableInt(this.lineForm.item_purchase_option_id),
                pack_count: this.normalizeNullableInt(this.lineForm.pack_count),
                unit_price_cents: this.normalizeNullableInt(this.lineForm.unit_price_cents),
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
            };
            this.editErrors = emptyEditErrors();
        },
        closeEditLine() {
            this.editingLineId = null;
            this.editForm = {
                pack_count: 1,
                unit_price_cents: '',
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

                this.lines = this.lines.filter((entry) => entry.id !== this.deleteLineId);

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
    }));
}
