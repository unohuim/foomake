import Alpine from 'alpinejs';
import { refreshNavigationState } from '../navigation/refresh-navigation-state';
import { mountCrudSection } from '../lib/js-crud-section';
import { mountPurchaseOrderCreate } from '../lib/js-purchase-order-create';

const asString = (value, fallback = '') => {
    if (typeof value === 'string' && value.trim() !== '') {
        return value;
    }

    return fallback;
};

const toStringValue = (value) => {
    if (value === null || value === undefined) {
        return '';
    }

    return String(value);
};

const packageDisplayText = (record) => {
    const quantity = asString(record.pack_quantity_display, asString(record.pack_quantity, '—'));
    const uomSymbol = asString(record.pack_uom_symbol);

    if (uomSymbol === '') {
        return quantity;
    }

    return `${quantity} ${uomSymbol}`;
};

const supplierPackageStateDisplay = (record) => {
    if (record.is_active === false || record.state === 'archived') {
        return {
            text: 'Archived',
            tone: 'muted',
        };
    }

    return {
        text: 'Active',
        tone: 'success',
    };
};

const buildSupplierPackagePayload = (form) => ({
    supplier_id: toStringValue(form.supplier_id),
    pack_quantity: asString(form.pack_quantity),
    pack_uom_id: toStringValue(form.pack_uom_id),
    supplier_sku: asString(form.supplier_sku),
    price_amount: asString(form.price_amount),
});

const formatMoney = (currencyCode, cents) => {
    const safeCurrencyCode = asString(currencyCode, 'USD');
    const safeCents = Number(cents || 0);

    return `${safeCurrencyCode} ${(safeCents / 100).toFixed(2)}`;
};

const purchaseOrderStatusDisplay = (record) => {
    switch (record.status) {
    case 'OPEN':
    case 'PARTIALLY-RECEIVED':
        return {
            text: record.status,
            tone: 'default',
        };
    case 'RECEIVED':
        return {
            text: record.status,
            tone: 'success',
        };
    case 'BACK-ORDERED':
    case 'SHORT-CLOSED':
    case 'CANCELLED':
        return {
            text: record.status,
            tone: 'muted',
        };
    default:
        return {
            text: asString(record.status, '—'),
            tone: 'muted',
        };
    }
};

const recipeStateDisplay = (record) => {
    if (record.is_default) {
        return {
            text: 'Default',
            tone: 'success',
        };
    }

    if (record.is_active) {
        return {
            text: 'Active',
            tone: 'default',
        };
    }

    return {
        text: 'Inactive',
        tone: 'muted',
    };
};

const makeOrderStatusDisplay = (record) => {
    switch (record.status) {
    case 'MADE':
        return {
            text: 'MADE',
            tone: 'success',
        };
    case 'SCHEDULED':
        return {
            text: 'SCHEDULED',
            tone: 'default',
        };
    case 'DRAFT':
        return {
            text: 'DRAFT',
            tone: 'muted',
        };
    default:
        return {
            text: asString(record.status, '—'),
            tone: 'muted',
        };
    }
};

const emptyRecipeErrors = () => ({
    item_id: [],
    recipe_type: [],
    name: [],
    output_quantity: [],
    is_active: [],
});

const emptyMakeOrderErrors = () => ({
    recipe_id: [],
    runs: [],
});

export function mount(rootEl, payload) {
    const safePayload = payload || {};
    const tenantCurrency = asString(safePayload.tenantCurrency, 'USD');
    const navigationStateUrl = asString(safePayload.navigationStateUrl);
    const materialId = safePayload.item?.id || null;
    const sectionRootsByKey = new Map();
    const purchaseOrderCreateRootEl = rootEl.querySelector('[data-purchase-order-create-root]');
    const purchaseOrderCreate = mountPurchaseOrderCreate(purchaseOrderCreateRootEl, safePayload.purchaseOrderCreate || {});
    const recipeCreate = safePayload.recipeCreate || {};
    const makeOrderCreate = safePayload.makeOrderCreate || {};

    rootEl.querySelectorAll('[data-js-crud-section-root]').forEach((sectionRootEl) => {
        const sectionKey = sectionRootEl.dataset.sectionKey || '';
        sectionRootsByKey.set(sectionKey, sectionRootEl);
    });

    const refreshSection = async (sectionKey) => {
        const sectionRootEl = sectionRootsByKey.get(sectionKey);

        if (!sectionRootEl?._jsCrudSectionApi?.refresh) {
            return;
        }

        await sectionRootEl._jsCrudSectionApi.refresh(1);
    };

    Alpine.data('materialsShowPage', () => ({
        manufacturableItems: Array.isArray(recipeCreate.manufacturableItems) ? recipeCreate.manufacturableItems : [],
        recipeStoreUrl: asString(recipeCreate.storeUrl),
        recipeCsrfToken: asString(recipeCreate.csrfToken),
        canManageRecipes: Boolean(recipeCreate.canManage),
        isCreateOpen: false,
        isCreateSubmitting: false,
        createOnlyWithoutRecipe: true,
        createForm: {
            item_id: '',
            recipe_type: 'manufacturing',
            name: '',
            output_quantity: '',
            is_active: true,
        },
        createManufacturingOutputQuantity: '',
        createErrors: emptyRecipeErrors(),
        createGeneralError: '',
        makeOrderCreateRecipes: Array.isArray(makeOrderCreate.recipes) ? makeOrderCreate.recipes : [],
        makeOrderStoreUrl: asString(makeOrderCreate.storeUrl),
        makeOrderCsrfToken: asString(makeOrderCreate.csrfToken),
        canExecute: Boolean(makeOrderCreate.canExecute),
        isMakeOrderCreateOpen: false,
        isMakeOrderCreateSubmitting: false,
        makeOrderCreateForm: {
            recipe_id: '',
            runs: '',
        },
        makeOrderCreateErrors: emptyMakeOrderErrors(),
        makeOrderCreateGeneralError: '',
        init() {
            this.createForm = this.defaultCreateForm();
            this.createManufacturingOutputQuantity = this.createForm.output_quantity;

            this.$watch('createForm.item_id', () => {
                this.syncCreateNameFromSelectedItem();
                this.syncCreateRecipeType();
            });

            this.$watch('createForm.recipe_type', () => {
                this.syncCreateOutputQuantity();
            });
        },
        normalizeRecipeErrors(errors) {
            if (!errors || typeof errors !== 'object') {
                return emptyRecipeErrors();
            }

            return {
                ...emptyRecipeErrors(),
                ...errors,
                item_id: Array.isArray(errors.item_id) ? errors.item_id : [],
                recipe_type: Array.isArray(errors.recipe_type) ? errors.recipe_type : [],
                name: Array.isArray(errors.name) ? errors.name : [],
                output_quantity: Array.isArray(errors.output_quantity) ? errors.output_quantity : [],
                is_active: Array.isArray(errors.is_active) ? errors.is_active : [],
            };
        },
        normalizeMakeOrderErrors(errors) {
            if (!errors || typeof errors !== 'object') {
                return emptyMakeOrderErrors();
            }

            return {
                ...emptyMakeOrderErrors(),
                ...errors,
                recipe_id: Array.isArray(errors.recipe_id) ? errors.recipe_id : [],
                runs: Array.isArray(errors.runs) ? errors.runs : [],
            };
        },
        defaultCreateForm() {
            return {
                item_id: '',
                recipe_type: 'manufacturing',
                name: '',
                output_quantity: this.defaultQuantityForItem(''),
                is_active: true,
            };
        },
        openRecipeCreate(prefill = {}) {
            if (!this.canManageRecipes) {
                this.createGeneralError = 'You do not have permission to create recipes.';
                return;
            }

            const prefillItemId = prefill.itemId || recipeCreate.prefillItemId || '';

            this.createErrors = emptyRecipeErrors();
            this.createGeneralError = '';
            this.createOnlyWithoutRecipe = !prefillItemId;
            this.createForm = this.defaultCreateForm();
            this.createForm.item_id = prefillItemId ? String(prefillItemId) : '';
            this.syncCreateRecipeType();
            this.syncCreateNameFromSelectedItem();
            this.createForm.output_quantity = this.defaultQuantityForItem(this.createForm.item_id);
            this.createManufacturingOutputQuantity = this.createForm.output_quantity;
            this.isCreateOpen = true;
            this.$nextTick(() => {
                const input = this.$refs.createOutputItemCombobox?.querySelector('input[role="combobox"]');
                input?.focus();
            });
        },
        closeCreate() {
            this.isCreateOpen = false;
            this.isCreateSubmitting = false;
            this.createErrors = emptyRecipeErrors();
            this.createGeneralError = '';
            this.createOnlyWithoutRecipe = true;
            this.createForm = this.defaultCreateForm();
            this.createManufacturingOutputQuantity = this.createForm.output_quantity;
        },
        filteredCreateItems() {
            return this.manufacturableItems.filter((item) => {
                if (this.createOnlyWithoutRecipe && Boolean(item.has_recipe)) {
                    return false;
                }

                return true;
            });
        },
        recipeTypeLabel(value) {
            if (value === 'fulfillment') {
                return 'Fulfillment';
            }

            return 'Manufacturing';
        },
        allRecipeTypeOptions() {
            return [
                { value: 'manufacturing', label: 'Manufacturing' },
                { value: 'fulfillment', label: 'Fulfillment' },
            ];
        },
        findOutputItem(itemId) {
            return this.manufacturableItems.find((item) => String(item.id) === String(itemId)) || null;
        },
        selectedOutputItemPrecision(itemId) {
            const outputItem = this.findOutputItem(itemId);

            return Number.isInteger(Number(outputItem?.uom_display_precision))
                ? Number(outputItem.uom_display_precision)
                : 6;
        },
        normalizeQuantityValue(value, precision) {
            const normalizedPrecision = Math.max(0, Math.min(6, Number(precision ?? 6)));
            const parsedValue = Number.parseFloat(String(value ?? '').trim());

            if (Number.isNaN(parsedValue)) {
                return normalizedPrecision === 0 ? '1' : (1).toFixed(normalizedPrecision);
            }

            return normalizedPrecision === 0
                ? String(Math.round(parsedValue))
                : parsedValue.toFixed(normalizedPrecision);
        },
        defaultQuantityForItem(itemId) {
            return this.normalizeQuantityValue('1', this.selectedOutputItemPrecision(itemId));
        },
        selectedOutputItemDisplayName(itemId) {
            return this.findOutputItem(itemId)?.name || '';
        },
        syncCreateNameFromSelectedItem() {
            this.createForm.name = this.selectedOutputItemDisplayName(this.createForm.item_id);
        },
        recipeTypeOptionsForItem(itemId) {
            const outputItem = this.findOutputItem(itemId);

            if (!outputItem || !Array.isArray(outputItem.allowed_recipe_types) || outputItem.allowed_recipe_types.length === 0) {
                return this.allRecipeTypeOptions();
            }

            return outputItem.allowed_recipe_types.map((recipeType) => ({
                value: recipeType,
                label: this.recipeTypeLabel(recipeType),
            }));
        },
        normalizeRecipeTypeSelection(selectedValue, allowedOptions) {
            if (!Array.isArray(allowedOptions) || allowedOptions.length === 0) {
                return 'manufacturing';
            }

            const selectedRecipeType = String(selectedValue || '');
            const allowedValues = allowedOptions.map((option) => option.value);

            if (allowedValues.includes(selectedRecipeType)) {
                return selectedRecipeType;
            }

            if (allowedValues.includes('manufacturing')) {
                return 'manufacturing';
            }

            return allowedValues[0];
        },
        availableCreateRecipeTypeOptions() {
            return this.recipeTypeOptionsForItem(this.createForm.item_id);
        },
        isFulfillmentRecipeType(recipeType) {
            return String(recipeType || '') === 'fulfillment';
        },
        resolvedCreateOutputQuantity() {
            return this.isFulfillmentRecipeType(this.createForm.recipe_type)
                ? '1.000000'
                : this.createForm.output_quantity;
        },
        syncCreateOutputQuantity() {
            if (this.isFulfillmentRecipeType(this.createForm.recipe_type)) {
                if (this.createForm.output_quantity !== '' && this.createForm.output_quantity !== this.defaultQuantityForItem(this.createForm.item_id)) {
                    this.createManufacturingOutputQuantity = this.createForm.output_quantity;
                }

                this.createForm.output_quantity = this.defaultQuantityForItem(this.createForm.item_id);
                return;
            }

            const fallbackValue = this.createManufacturingOutputQuantity || this.defaultQuantityForItem(this.createForm.item_id);
            this.createForm.output_quantity = this.normalizeQuantityValue(
                fallbackValue,
                this.selectedOutputItemPrecision(this.createForm.item_id)
            );
            this.createManufacturingOutputQuantity = this.createForm.output_quantity;
        },
        normalizeCreateOutputQuantity() {
            if (this.isFulfillmentRecipeType(this.createForm.recipe_type)) {
                this.createForm.output_quantity = this.defaultQuantityForItem(this.createForm.item_id);
                return;
            }

            this.createForm.output_quantity = this.normalizeQuantityValue(
                this.createForm.output_quantity,
                this.selectedOutputItemPrecision(this.createForm.item_id)
            );
            this.createManufacturingOutputQuantity = this.createForm.output_quantity;
        },
        syncCreateRecipeType() {
            this.createForm.recipe_type = this.normalizeRecipeTypeSelection(
                this.createForm.recipe_type,
                this.availableCreateRecipeTypeOptions()
            );
            this.syncCreateOutputQuantity();
        },
        async submitCreate() {
            if (!this.canManageRecipes) {
                this.createGeneralError = 'You do not have permission to create recipes.';
                return;
            }

            this.isCreateSubmitting = true;
            this.createGeneralError = '';
            this.createErrors = emptyRecipeErrors();

            const response = await fetch(this.recipeStoreUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': this.recipeCsrfToken,
                },
                body: JSON.stringify({
                    item_id: this.createForm.item_id,
                    recipe_type: this.createForm.recipe_type,
                    name: this.createForm.name,
                    output_quantity: this.resolvedCreateOutputQuantity(),
                    is_active: this.createForm.is_active,
                }),
            });

            if (response.status === 422) {
                const data = await response.json();
                this.createErrors = this.normalizeRecipeErrors(data.errors);
                this.isCreateSubmitting = false;
                return;
            }

            if (!response.ok) {
                this.createGeneralError = 'Something went wrong. Please try again.';
                this.isCreateSubmitting = false;
                return;
            }

            const data = await response.json();

            if (data?.data?.show_url) {
                window.location.assign(data.data.show_url);
                return;
            }

            if (data.data && Number(data.data.item_id) === Number(materialId) && data.data.recipe_type === 'manufacturing' && data.data.is_active) {
                this.upsertMakeOrderCreateRecipe(data.data);
            }

            await refreshSection('recipes');
            if (navigationStateUrl !== '') {
                await refreshNavigationState(navigationStateUrl);
            }
            this.closeCreate();
        },
        openMakeOrderCreate(prefill = {}) {
            this.makeOrderCreateErrors = emptyMakeOrderErrors();
            this.makeOrderCreateGeneralError = '';
            this.makeOrderCreateForm = {
                recipe_id: prefill.recipe_id ? String(prefill.recipe_id) : '',
                runs: '',
            };
            this.isMakeOrderCreateOpen = true;
            this.$nextTick(() => {
                this.$refs.makeOrderRecipeSelect?.focus();
            });
        },
        closeMakeOrderCreate() {
            this.isMakeOrderCreateOpen = false;
            this.isMakeOrderCreateSubmitting = false;
            this.makeOrderCreateErrors = emptyMakeOrderErrors();
            this.makeOrderCreateGeneralError = '';
            this.makeOrderCreateForm = {
                recipe_id: '',
                runs: '',
            };
        },
        upsertMakeOrderCreateRecipe(recipe) {
            const normalizedRecipe = {
                id: recipe.id,
                name: recipe.name,
                item_id: recipe.item_id,
                item_name: recipe.item_name || safePayload.item?.name || '—',
            };
            const index = this.makeOrderCreateRecipes.findIndex((existingRecipe) => existingRecipe.id === normalizedRecipe.id);

            if (index === -1) {
                this.makeOrderCreateRecipes.unshift(normalizedRecipe);
                return;
            }

            this.makeOrderCreateRecipes.splice(index, 1, {
                ...this.makeOrderCreateRecipes[index],
                ...normalizedRecipe,
            });
        },
        async submitMakeOrderCreate() {
            if (!this.canExecute) {
                this.makeOrderCreateGeneralError = 'You do not have permission to create make orders.';
                return;
            }

            this.isMakeOrderCreateSubmitting = true;
            this.makeOrderCreateGeneralError = '';
            this.makeOrderCreateErrors = emptyMakeOrderErrors();

            const response = await fetch(this.makeOrderStoreUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': this.makeOrderCsrfToken,
                },
                body: JSON.stringify({
                    recipe_id: this.makeOrderCreateForm.recipe_id
                        ? Number(this.makeOrderCreateForm.recipe_id)
                        : this.makeOrderCreateForm.recipe_id,
                    runs: this.makeOrderCreateForm.runs,
                }),
            });

            if (response.status === 422) {
                const data = await response.json();
                this.makeOrderCreateErrors = this.normalizeMakeOrderErrors(data.errors);
                this.makeOrderCreateGeneralError = data.message || 'Validation failed.';
                this.isMakeOrderCreateSubmitting = false;
                return;
            }

            if (!response.ok) {
                this.makeOrderCreateGeneralError = 'Something went wrong. Please try again.';
                this.isMakeOrderCreateSubmitting = false;
                return;
            }

            const data = await response.json();

            if (data?.data?.show_url) {
                window.location.assign(data.data.show_url);
                return;
            }

            await refreshSection('makeOrders');
            if (navigationStateUrl !== '') {
                await refreshNavigationState(navigationStateUrl);
            }
            this.closeMakeOrderCreate();
        },
    }));

    const adaptersBySectionKey = {
        purchaseOrders: {
            normalizeRow: (record) => {
                const status = purchaseOrderStatusDisplay(record);

                return {
                    ...record,
                    display: {
                        poNumberText: record.po_number ? `PO #${record.po_number}` : 'Draft PO',
                        orderDateText: asString(record.order_date, 'No order date'),
                        supplierText: asString(record.supplier_name, 'Supplier not set'),
                        totalText: formatMoney(tenantCurrency, record.po_grand_total_cents),
                        statusText: status.text,
                        statusTone: status.tone,
                        showUrl: asString(record.show_url),
                    },
                };
            },
            handleAction: async () => {},
        },
        recipes: {
            normalizeRow: (record) => {
                const state = recipeStateDisplay(record);

                return {
                    ...record,
                    display: {
                        nameText: asString(record.name, 'Unnamed recipe'),
                        recipeTypeText: asString(record.recipe_type_label, '—'),
                        updatedAtText: asString(record.updated_at, '—'),
                        outputQuantityText: asString(record.output_quantity_display, '—'),
                        stateText: state.text,
                        stateTone: state.tone,
                        showUrl: asString(record.show_url),
                    },
                };
            },
            handleCreateAction: ({ section }) => {
                rootEl.dispatchEvent(new CustomEvent('materials-show:open-recipe-create', {
                    detail: section.createAction.prefill || {},
                    bubbles: true,
                }));
            },
            handleAction: async ({ action, record }) => {
                if (action.handlerKey === 'openMakeOrderCreate') {
                    rootEl.dispatchEvent(new CustomEvent('materials-show:open-make-order-create', {
                        detail: record.make_prefill || {},
                        bubbles: true,
                    }));
                }
            },
        },
        makeOrders: {
            normalizeRow: (record) => {
                const status = makeOrderStatusDisplay(record);

                return {
                    ...record,
                    display: {
                        recipeNameText: asString(record.recipe_name, 'Unnamed recipe'),
                        runsText: asString(record.runs_display, '—'),
                        dueDateText: asString(record.due_date, 'No due date'),
                        totalOutputQuantityText: asString(record.total_output_quantity_display, '—'),
                        statusText: status.text,
                        statusTone: status.tone,
                        showUrl: asString(record.show_url),
                    },
                };
            },
            handleAction: async () => {},
        },
        supplierPackages: {
            normalizeRow: (record) => {
                const state = supplierPackageStateDisplay(record);

                return {
                    ...record,
                    formValues: {
                        supplier_id: toStringValue(record.supplier_id),
                        pack_quantity: asString(record.pack_quantity),
                        pack_uom_id: toStringValue(record.pack_uom_id),
                        supplier_sku: asString(record.supplier_sku),
                        price_amount: asString(record.price_amount),
                    },
                    display: {
                        primaryText: asString(record.supplier_name, 'Unknown supplier'),
                        packageText: packageDisplayText(record),
                        skuText: asString(record.supplier_sku, '—'),
                        stateText: state.text,
                        stateTone: state.tone,
                        priceText: asString(record.current_price_display, 'No price'),
                        showUrl: asString(record.show_url),
                    },
                };
            },
            buildCreatePayload: (form) => buildSupplierPackagePayload(form),
            buildUpdatePayload: (form) => buildSupplierPackagePayload(form),
            handleAction: async ({ action, record }) => {
                if (action.handlerKey === 'purchase' && purchaseOrderCreate) {
                    purchaseOrderCreate.openFromSupplierPackage({
                        supplier_id: record.supplier_id,
                        item_purchase_option_id: record.item_purchase_option_id ?? record.id,
                    });
                }
            },
        },
    };

    rootEl.querySelectorAll('[data-js-crud-section-root]').forEach((sectionRootEl) => {
        const sectionKey = sectionRootEl.dataset.sectionKey || '';
        const sectionConfig = safePayload.sections?.[sectionKey] || null;

        mountCrudSection(sectionRootEl, {
            section: sectionConfig,
            adapters: adaptersBySectionKey[sectionKey] || {},
        });
    });
}
