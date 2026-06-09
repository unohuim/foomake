import Alpine from 'alpinejs';
import { refreshNavigationState } from '../navigation/refresh-navigation-state';
import { mountCrudSection } from '../lib/js-crud-section';

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

const formatMoneyAmount = (cents) => {
    const safeCents = Number(cents || 0);

    return (safeCents / 100).toFixed(2);
};

const materialPurchaseOrderQuantityCostText = (record) => {
    const quantity = asString(record.material_quantity_display);
    const uom = asString(record.material_uom_symbol);
    const unitCost = asString(record.material_unit_cost_amount_display);
    const currency = asString(record.material_unit_cost_currency_code);
    const quantityText = [quantity, uom].filter((value) => value !== '').join(' ');

    if (quantityText === '') {
        return '';
    }

    if (unitCost === '') {
        return quantityText;
    }

    const unitCostCurrency = currency === '' ? '' : ` ${currency}`;
    const unitCostSuffix = uom === '' ? '' : `/${uom}`;

    return `${quantityText} · ${unitCost}${unitCostCurrency}${unitCostSuffix}`;
};

const purchaseOrderStatusDisplay = (record) => {
    if (record.is_cancelled) {
        return {
            text: 'Cancelled',
            tone: 'muted',
        };
    }

    if (record.is_back_ordered) {
        return {
            text: 'Back Ordered',
            tone: 'muted',
        };
    }

    switch (record.status) {
    case 'CREATED':
        return {
            text: record.status,
            tone: 'default',
        };
    case 'RECEIVED':
    case 'COMPLETED':
        return {
            text: record.status,
            tone: 'success',
        };
    default:
        return {
            text: asString(record.status, '—'),
            tone: 'muted',
        };
    }
};

const recipeStateDisplay = (record) => {
    if (record.version_status === 'PUBLISHED') {
        return {
            text: 'Published',
            tone: 'success',
        };
    }

    if (record.version_status === 'DRAFT') {
        return {
            text: 'Draft',
            tone: 'default',
        };
    }

    if (record.version_status === 'ARCHIVED') {
        return {
            text: 'Archived',
            tone: 'muted',
        };
    }

    if (record.is_default) {
        return {
            text: 'Default',
            tone: 'success',
        };
    }

    if (record.is_active) {
        return {
            text: 'Draft',
            tone: 'default',
        };
    }

    return {
        text: 'Archived',
        tone: 'muted',
    };
};

const makeOrderStatusDisplay = (record) => {
    switch (record.workflow_state) {
    case 'DRAFT':
        return {
            text: 'DRAFT',
            tone: 'muted',
        };
    default:
        return {
            text: asString(record.workflow_state, '—'),
            tone: 'default',
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
    due_date: [],
});

const emptyInventoryCountErrors = () => ({
    name: [],
    counted_at: [],
    notes: [],
    assigned_to_user_id: [],
    general: [],
});

export function mount(rootEl, payload) {
    const safePayload = payload || {};
    const tenantCurrency = asString(safePayload.tenantCurrency, 'USD');
    const navigationStateUrl = asString(safePayload.navigationStateUrl);
    const materialId = safePayload.item?.id || null;
    const materialUpdateUrl = asString(safePayload.item?.update_url);
    const materialCsrfToken = asString(safePayload.item?.csrf_token);
    let materialBaseUomId = safePayload.item?.base_uom_id || '';
    const materialTypeFields = [
        'is_sellable',
        'is_purchasable',
        'is_manufacturable',
        'is_stockable',
    ];
    const sectionRootsByKey = new Map();
    let recipeCreate = safePayload.recipeCreate || {};
    let makeOrderCreate = safePayload.makeOrderCreate || {};
    let inventoryCountCreate = safePayload.inventoryCountCreate || {};
    let pageState = null;
    let inventoryCountCreateState = null;
    let baseUomDropdownState = null;
    let inventoryStatsState = null;
    let materialNameEditorState = null;
    const openInventoryCountCreate = () => {
        if (!inventoryCountCreateState || typeof inventoryCountCreateState.openCreate !== 'function') {
            return;
        }

        inventoryCountCreateState.openCreate();
    };

    rootEl.querySelectorAll('[data-js-crud-section-root]').forEach((sectionRootEl) => {
        const sectionKey = sectionRootEl.dataset.sectionKey || '';
        sectionRootsByKey.set(sectionKey, sectionRootEl);
    });

    Alpine.data('materialInventoryCountCreate', () => ({
        inventoryCountStoreUrl: asString(inventoryCountCreate.storeUrl),
        inventoryCountCsrfToken: asString(inventoryCountCreate.csrfToken),
        canCreateInventoryCounts: Boolean(inventoryCountCreate.canCreate),
        inventoryCountUsers: Array.isArray(inventoryCountCreate.users) ? inventoryCountCreate.users : [],
        showCountForm: false,
        inventoryCountSubmitting: false,
        form: {
            id: null,
            name: '',
            counted_at: '',
            notes: '',
            assigned_to_user_id: '',
            action: '',
            method: 'POST',
        },
        errors: emptyInventoryCountErrors(),
        init() {
            inventoryCountCreateState = this;
        },
        updateConfig(nextConfig) {
            const config = nextConfig || {};

            this.inventoryCountStoreUrl = asString(config.storeUrl);
            this.inventoryCountCsrfToken = asString(config.csrfToken);
            this.canCreateInventoryCounts = Boolean(config.canCreate);
            this.inventoryCountUsers = Array.isArray(config.users) ? config.users : [];

            if (!this.showCountForm) {
                this.form = this.defaultInventoryCountForm();
            }
        },
        defaultInventoryCountForm() {
            return {
                id: null,
                name: '',
                counted_at: '',
                notes: '',
                assigned_to_user_id: '',
                action: this.inventoryCountStoreUrl || '',
                method: 'POST',
            };
        },
        focusCountedAtNextField(fieldId) {
            if (!fieldId) {
                return;
            }

            const nextField = document.getElementById(fieldId);

            if (nextField instanceof HTMLElement && typeof nextField.focus === 'function') {
                nextField.focus({ preventScroll: true });
            }
        },
        handleCountedAtChange(event) {
            this.form.counted_at = event.target.value;

            if (!event?.target?.value) {
                return;
            }

            requestAnimationFrame(() => {
                event.target.blur();
                this.focusCountedAtNextField('notes');
            });
        },
        normalizeInventoryCountErrors(errors) {
            if (!errors || typeof errors !== 'object') {
                return emptyInventoryCountErrors();
            }

            return {
                ...emptyInventoryCountErrors(),
                ...errors,
                name: Array.isArray(errors.name) ? errors.name : [],
                counted_at: Array.isArray(errors.counted_at) ? errors.counted_at : [],
                notes: Array.isArray(errors.notes) ? errors.notes : [],
                assigned_to_user_id: Array.isArray(errors.assigned_to_user_id) ? errors.assigned_to_user_id : [],
                general: Array.isArray(errors.general) ? errors.general : [],
            };
        },
        openCreate() {
            if (!this.canCreateInventoryCounts) {
                return;
            }

            this.errors = emptyInventoryCountErrors();
            this.form = this.defaultInventoryCountForm();
            this.inventoryCountSubmitting = false;
            this.showCountForm = Boolean(this.form.action);
        },
        closeCountForm() {
            this.showCountForm = false;
            this.inventoryCountSubmitting = false;
            this.errors = emptyInventoryCountErrors();
            this.form = this.defaultInventoryCountForm();
        },
        async submitCountForm() {
            if (!this.canCreateInventoryCounts || asString(this.form.action) === '') {
                return;
            }

            this.inventoryCountSubmitting = true;
            this.errors = emptyInventoryCountErrors();

            try {
                const response = await fetch(this.form.action, {
                    method: this.form.method || 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        Accept: 'application/json',
                        'X-CSRF-TOKEN': this.inventoryCountCsrfToken,
                    },
                    body: JSON.stringify({
                        name: this.form.name,
                        counted_at: this.form.counted_at,
                        notes: this.form.notes,
                        assigned_to_user_id: this.form.assigned_to_user_id,
                    }),
                });

                if (response.status === 422) {
                    const data = await response.json();
                    this.errors = this.normalizeInventoryCountErrors(data?.errors);

                    if (!data?.errors) {
                        this.errors.general = [data?.message || 'Unable to save count.'];
                    }

                    return;
                }

                if (!response.ok) {
                    this.errors.general = ['Unable to save count.'];
                    return;
                }

                await refreshSection('inventoryCounts');
                this.closeCountForm();
            } finally {
                this.inventoryCountSubmitting = false;
            }
        },
    }));

    const refreshSection = async (sectionKey) => {
        const sectionRootEl = sectionRootsByKey.get(sectionKey);

        if (!sectionRootEl?._jsCrudSectionApi?.refresh) {
            return;
        }

        await sectionRootEl._jsCrudSectionApi.refresh(1);
    };

    const mountMaterialSection = (sectionKey, sectionConfig, initializeTree = false) => {
        const sectionRootEl = sectionRootsByKey.get(sectionKey);

        if (!sectionRootEl) {
            return;
        }

        if (!sectionConfig?.resource) {
            sectionRootEl.innerHTML = '';
            sectionRootEl.hidden = true;
            sectionRootEl._jsCrudSectionConfig = null;
            sectionRootEl._jsCrudSectionAdapters = null;
            sectionRootEl._jsCrudSectionApi = null;
            return;
        }

        sectionRootEl.hidden = false;
        mountCrudSection(sectionRootEl, {
            section: sectionConfig,
            adapters: adaptersBySectionKey[sectionKey] || {},
        });

        if (initializeTree && typeof Alpine.initTree === 'function') {
            Alpine.initTree(sectionRootEl);
        }
    };

    const syncMaterialDetailPayload = (nextPayload) => {
        if (!nextPayload || typeof nextPayload !== 'object') {
            return;
        }

        safePayload.item = nextPayload.item || safePayload.item;
        materialBaseUomId = safePayload.item?.base_uom_id || materialBaseUomId;
        safePayload.inventoryStats = nextPayload.inventoryStats || null;
        safePayload.sections = nextPayload.sections || {};
        safePayload.purchaseOrderCreate = nextPayload.purchaseOrderCreate || null;
        safePayload.recipeCreate = nextPayload.recipeCreate || null;
        safePayload.inventoryCountCreate = nextPayload.inventoryCountCreate || null;
        safePayload.makeOrderCreate = nextPayload.makeOrderCreate || null;
        recipeCreate = safePayload.recipeCreate || {};
        makeOrderCreate = safePayload.makeOrderCreate || {};
        inventoryCountCreate = safePayload.inventoryCountCreate || {};

        if (pageState && typeof pageState.syncMaterialCreateConfigs === 'function') {
            pageState.syncMaterialCreateConfigs();
        }

        if (inventoryCountCreateState && typeof inventoryCountCreateState.updateConfig === 'function') {
            inventoryCountCreateState.updateConfig(inventoryCountCreate);
        }

        if (baseUomDropdownState && typeof baseUomDropdownState.syncFromPayload === 'function') {
            baseUomDropdownState.syncFromPayload();
        }

        if (materialNameEditorState && typeof materialNameEditorState.syncFromPayload === 'function') {
            materialNameEditorState.syncFromPayload();
        }

        const titleEl = rootEl.querySelector('[data-resource-detail-header-title]')
            || document.querySelector('[data-resource-detail-header-title]');

        if (titleEl) {
            titleEl.textContent = asString(safePayload.item?.name, titleEl.textContent || '');
        }

        const breadcrumbCurrentEl = rootEl.querySelector('[data-resource-detail-breadcrumb] [aria-current="page"]')
            || document.querySelector('[data-resource-detail-breadcrumb] [aria-current="page"]');

        if (breadcrumbCurrentEl) {
            breadcrumbCurrentEl.textContent = asString(safePayload.item?.name, breadcrumbCurrentEl.textContent || '');
        }

        if (inventoryStatsState && typeof inventoryStatsState.syncFromPayload === 'function') {
            inventoryStatsState.syncFromPayload();
        }

        const inventoryStatsEl = rootEl.querySelector('[data-material-inventory-stats]');

        if (inventoryStatsEl) {
            inventoryStatsEl.hidden = !nextPayload.inventoryStats;
        }

        Object.entries(safePayload.sections).forEach(([sectionKey, sectionConfig]) => {
            mountMaterialSection(sectionKey, sectionConfig, true);
        });
    };

    const createPurchaseOrderFromSupplierPackage = async (record) => {
        const purchaseUrl = asString(record.purchase_url, asString(safePayload.purchaseOrderCreate?.storeUrl));

        if (purchaseUrl === '') {
            return;
        }

        const response = await fetch(purchaseUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-CSRF-TOKEN': asString(safePayload.purchaseOrderCreate?.csrfToken),
            },
            body: JSON.stringify({
                item_purchase_option_id: record.item_purchase_option_id ?? record.id,
            }),
        });

        if (!response.ok) {
            return;
        }

        const data = await response.json();

        if (data?.data?.show_url) {
            window.location.assign(data.data.show_url);
        }
    };

    const materialTypeState = () => ({
        canManageMaterialTypes: Boolean(safePayload.item?.can_manage),
        canToggleMaterialTypes: Boolean(safePayload.item?.can_toggle_types),
        materialTypeToggles: [
            { field: 'is_sellable', label: 'Sellable', icon: 'shopping-cart' },
            { field: 'is_purchasable', label: 'Purchasable', icon: 'credit-card' },
            { field: 'is_manufacturable', label: 'Makeable', icon: 'cog' },
            { field: 'is_stockable', label: 'Stockable', icon: 'rectangle-group' },
        ],
        materialTypes: {
            is_sellable: Boolean(safePayload.item?.is_sellable),
            is_purchasable: Boolean(safePayload.item?.is_purchasable),
            is_manufacturable: Boolean(safePayload.item?.is_manufacturable),
            is_stockable: Boolean(safePayload.item?.is_stockable),
        },
        materialTypeSaving: {
            is_sellable: false,
            is_purchasable: false,
            is_manufacturable: false,
            is_stockable: false,
        },
        materialTypeActive(field) {
            return Boolean(this.materialTypes[field]);
        },
        materialTypeTitle(typeToggle) {
            if (this.canToggleMaterialTypes) {
                return typeToggle.label;
            }

            return `${typeToggle.label} requires material management permission.`;
        },
        async toggleMaterialType(field) {
            if (
                !this.canToggleMaterialTypes
                || !materialTypeFields.includes(field)
                || this.materialTypeSaving[field]
                || materialUpdateUrl === ''
            ) {
                return;
            }

            const nextValue = !this.materialTypeActive(field);
            const previousValue = this.materialTypeActive(field);
            this.materialTypeSaving[field] = true;
            this.materialTypes[field] = nextValue;

            try {
                const response = await fetch(materialUpdateUrl, {
                    method: 'PATCH',
                    headers: {
                        'Content-Type': 'application/json',
                        Accept: 'application/json',
                        'X-CSRF-TOKEN': materialCsrfToken,
                    },
                    body: JSON.stringify({
                        name: asString(safePayload.item?.name),
                        base_uom_id: materialBaseUomId,
                        [field]: nextValue,
                    }),
                });

                if (!response.ok) {
                    this.materialTypes[field] = previousValue;
                    return;
                }

                const data = await response.json();
                const item = data?.data || {};
                const materialDetail = item.material_detail || null;

                if (materialDetail) {
                    syncMaterialDetailPayload(materialDetail);
                    this.canManageMaterialTypes = Boolean(materialDetail.item?.can_manage);
                    this.canToggleMaterialTypes = Boolean(materialDetail.item?.can_toggle_types);
                }

                materialTypeFields.forEach((typeField) => {
                    const source = materialDetail?.item || item;

                    if (Object.prototype.hasOwnProperty.call(source, typeField)) {
                        this.materialTypes[typeField] = Boolean(source[typeField]);
                    }
                });

                const label = this.materialTypeToggles.find((toggle) => toggle.field === field)?.label || 'Type';
                pageState?.showToast('success', `${label} ${this.materialTypeActive(field) ? 'enabled' : 'disabled'}.`);
            } catch (error) {
                this.materialTypes[field] = previousValue;
            } finally {
                this.materialTypeSaving[field] = false;
            }
        },
    });

    Alpine.data('materialTypeToggles', materialTypeState);

    const materialNameEditorStateFactory = () => ({
        isOpen: false,
        isSaving: false,
        draftName: asString(safePayload.item?.name),
        errorMessage: '',
        init() {
            materialNameEditorState = this;
        },
        syncFromPayload() {
            this.draftName = asString(safePayload.item?.name, this.draftName);
        },
        openEditor() {
            this.draftName = asString(safePayload.item?.name, this.draftName);
            this.errorMessage = '';
            this.isOpen = true;

            this.$nextTick(() => {
                this.$refs.nameInput?.focus();
                this.$refs.nameInput?.select();
            });
        },
        closeEditor() {
            if (this.isSaving) {
                return;
            }

            this.isOpen = false;
            this.errorMessage = '';
            this.draftName = asString(safePayload.item?.name, this.draftName);
        },
        async saveName() {
            const nextName = asString(this.draftName).trim();

            if (this.isSaving || nextName === '') {
                this.errorMessage = 'Material name is required.';
                return;
            }

            if (materialUpdateUrl === '') {
                this.errorMessage = 'Unable to update material name.';
                return;
            }

            this.isSaving = true;
            this.errorMessage = '';

            try {
                const response = await fetch(materialUpdateUrl, {
                    method: 'PATCH',
                    headers: {
                        'Content-Type': 'application/json',
                        Accept: 'application/json',
                        'X-CSRF-TOKEN': materialCsrfToken,
                    },
                    body: JSON.stringify({
                        name: nextName,
                        base_uom_id: materialBaseUomId,
                    }),
                });

                const data = await response.json();

                if (!response.ok) {
                    this.errorMessage = data?.errors?.name?.[0] || data?.message || 'Unable to update material name.';
                    return;
                }

                if (data?.data?.material_detail) {
                    syncMaterialDetailPayload(data.data.material_detail);
                } else {
                    safePayload.item.name = nextName;
                    syncMaterialDetailPayload({ ...safePayload });
                }

                this.isOpen = false;
                pageState?.showToast('success', 'Material name updated.');
            } catch (error) {
                this.errorMessage = 'Unable to update material name.';
            } finally {
                this.isSaving = false;
            }
        },
    });

    Alpine.data('materialNameEditor', materialNameEditorStateFactory);

    const materialBaseUomState = () => ({
        currentUomId: materialBaseUomId,
        currentUomName: asString(safePayload.item?.base_uom_name, asString(safePayload.item?.base_uom_symbol, '—')),
        canChangeBaseUom: Boolean(safePayload.item?.can_manage),
        isChangingBaseUom: false,
        uomOptions: Array.isArray(safePayload.item?.uom_options) ? safePayload.item.uom_options : [],
        init() {
            baseUomDropdownState = this;
        },
        syncFromPayload() {
            this.currentUomId = safePayload.item?.base_uom_id || this.currentUomId;
            this.currentUomName = asString(
                safePayload.item?.base_uom_name,
                asString(safePayload.item?.base_uom_symbol, '—')
            );
            this.canChangeBaseUom = Boolean(safePayload.item?.can_manage);
            this.uomOptions = Array.isArray(safePayload.item?.uom_options) ? safePayload.item.uom_options : [];
        },
        availableUomOptions() {
            return this.uomOptions.filter((option) => String(option.id) !== String(this.currentUomId));
        },
        optionLabel(option) {
            const name = asString(option?.name, 'Unnamed UoM');
            const symbol = asString(option?.symbol);

            return symbol === '' ? name : `${name} (${symbol})`;
        },
        async selectBaseUom(option) {
            if (
                !this.canChangeBaseUom
                || this.isChangingBaseUom
                || !option?.id
                || String(option.id) === String(this.currentUomId)
                || materialUpdateUrl === ''
            ) {
                return;
            }

            const confirmed = window.confirm(`Change base unit of measure to ${this.optionLabel(option)}?`);

            if (!confirmed) {
                return;
            }

            this.isChangingBaseUom = true;

            try {
                const response = await fetch(materialUpdateUrl, {
                    method: 'PATCH',
                    headers: {
                        'Content-Type': 'application/json',
                        Accept: 'application/json',
                        'X-CSRF-TOKEN': materialCsrfToken,
                    },
                    body: JSON.stringify({
                        name: asString(safePayload.item?.name),
                        base_uom_id: option.id,
                    }),
                });

                const data = await response.json();

                if (!response.ok) {
                    const error = data?.errors?.base_uom_id?.[0] || data?.message || 'Unable to change base unit of measure.';
                    window.alert(error);
                    return;
                }

                const materialDetail = data?.data?.material_detail || null;

                if (materialDetail) {
                    syncMaterialDetailPayload(materialDetail);
                }
            } finally {
                this.isChangingBaseUom = false;
            }
        },
    });

    Alpine.data('materialBaseUomDropdown', materialBaseUomState);

    const materialInventoryStatsState = () => ({
        cards: Array.isArray(safePayload.inventoryStats?.cards) ? safePayload.inventoryStats.cards : [],
        activeStat: safePayload.inventoryStats?.cards?.[0]?.key || null,
        init() {
            inventoryStatsState = this;
        },
        syncFromPayload() {
            this.cards = Array.isArray(safePayload.inventoryStats?.cards) ? safePayload.inventoryStats.cards : [];

            if (!this.cards.some((card) => card.key === this.activeStat)) {
                this.activeStat = this.cards[0]?.key || null;
            }
        },
        compactLabel(card) {
            return asString(card?.compact_label, asString(card?.mobile_label, asString(card?.label, '—')));
        },
        quantityDisplay(card) {
            return asString(card?.quantity_display_grouped, asString(card?.quantity_display, '0'));
        },
        uomSymbol(card) {
            return asString(card?.uom_symbol);
        },
        desktopGridClass() {
            switch (this.cards.length) {
            case 1:
                return 'sm:grid-cols-1';
            case 2:
                return 'sm:grid-cols-2';
            case 3:
                return 'sm:grid-cols-3';
            case 4:
                return 'sm:grid-cols-4';
            default:
                return 'sm:grid-cols-5';
            }
        },
    });

    Alpine.data('materialInventoryStats', materialInventoryStatsState);

    Alpine.data('materialsShowPage', () => ({
        manufacturableItems: Array.isArray(recipeCreate.manufacturableItems) ? recipeCreate.manufacturableItems : [],
        recipeStoreUrl: asString(recipeCreate.storeUrl),
        recipeCsrfToken: asString(recipeCreate.csrfToken),
        canManageRecipes: Boolean(recipeCreate.canManage),
        recipePrefillItemId: recipeCreate.prefillItemId || '',
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
        makeOrderRecipes: Array.isArray(makeOrderCreate.recipes) ? makeOrderCreate.recipes : [],
        makeOrderStoreUrl: asString(makeOrderCreate.storeUrl),
        makeOrderCsrfToken: asString(makeOrderCreate.csrfToken),
        canExecute: Boolean(makeOrderCreate.canExecute),
        isMakeOrderFormOpen: false,
        isMakeOrderEditMode: false,
        isMakeOrderFormSubmitting: false,
        makeOrderForm: {
            recipe_id: '',
            runs: '',
            due_date: '',
        },
        makeOrderFormErrors: emptyMakeOrderErrors(),
        makeOrderFormGeneralError: '',
        toast: {
            visible: false,
            type: 'success',
            message: '',
            timeoutId: null,
        },
        init() {
            pageState = this;
            this.syncMaterialCreateConfigs();
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
        showToast(type, message) {
            this.toast.type = type;
            this.toast.message = message;
            this.toast.visible = true;

            if (this.toast.timeoutId) {
                window.clearTimeout(this.toast.timeoutId);
            }

            this.toast.timeoutId = window.setTimeout(() => {
                this.toast.visible = false;
                this.toast.timeoutId = null;
            }, 1500);
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
                due_date: Array.isArray(errors.due_date) ? errors.due_date : [],
            };
        },
        syncMaterialCreateConfigs() {
            this.manufacturableItems = Array.isArray(recipeCreate.manufacturableItems) ? recipeCreate.manufacturableItems : [];
            this.recipeStoreUrl = asString(recipeCreate.storeUrl);
            this.recipeCsrfToken = asString(recipeCreate.csrfToken);
            this.canManageRecipes = Boolean(recipeCreate.canManage);
            this.recipePrefillItemId = recipeCreate.prefillItemId || '';
            this.makeOrderRecipes = Array.isArray(makeOrderCreate.recipes) ? makeOrderCreate.recipes : [];
            this.makeOrderStoreUrl = asString(makeOrderCreate.storeUrl);
            this.makeOrderCsrfToken = asString(makeOrderCreate.csrfToken);
            this.canExecute = Boolean(makeOrderCreate.canExecute);
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

            const prefillItemId = prefill.itemId || this.recipePrefillItemId || '';

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
            this.makeOrderFormErrors = emptyMakeOrderErrors();
            this.makeOrderFormGeneralError = '';
            this.isMakeOrderEditMode = false;
            this.makeOrderForm = {
                recipe_id: prefill.recipe_id ? String(prefill.recipe_id) : '',
                runs: '',
                due_date: '',
            };
            this.isMakeOrderFormOpen = true;
            this.$nextTick(() => {
                this.$refs.makeOrderRecipeSelect?.focus();
            });
        },
        closeMakeOrderCreate() {
            this.closeMakeOrderForm();
        },
        closeMakeOrderForm() {
            this.isMakeOrderFormOpen = false;
            this.isMakeOrderFormSubmitting = false;
            this.makeOrderFormErrors = emptyMakeOrderErrors();
            this.makeOrderFormGeneralError = '';
            this.makeOrderForm = {
                recipe_id: '',
                runs: '',
                due_date: '',
            };
        },
        upsertMakeOrderCreateRecipe(recipe) {
            const normalizedRecipe = {
                id: recipe.id,
                name: recipe.name,
                item_id: recipe.item_id,
                item_name: recipe.item_name || safePayload.item?.name || '—',
            };
            const index = this.makeOrderRecipes.findIndex((existingRecipe) => existingRecipe.id === normalizedRecipe.id);

            if (index === -1) {
                this.makeOrderRecipes.unshift(normalizedRecipe);
                return;
            }

            this.makeOrderRecipes.splice(index, 1, {
                ...this.makeOrderRecipes[index],
                ...normalizedRecipe,
            });
        },
        async submitMakeOrderCreate() {
            await this.submitMakeOrderForm();
        },
        async submitMakeOrderForm() {
            if (!this.canExecute) {
                this.makeOrderFormGeneralError = 'You do not have permission to create make orders.';
                return;
            }

            this.isMakeOrderFormSubmitting = true;
            this.makeOrderFormGeneralError = '';
            this.makeOrderFormErrors = emptyMakeOrderErrors();

            const response = await fetch(this.makeOrderStoreUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': this.makeOrderCsrfToken,
                },
                body: JSON.stringify({
                    recipe_id: this.makeOrderForm.recipe_id
                        ? Number(this.makeOrderForm.recipe_id)
                        : this.makeOrderForm.recipe_id,
                    runs: this.makeOrderForm.runs,
                }),
            });

            if (response.status === 422) {
                const data = await response.json();
                this.makeOrderFormErrors = this.normalizeMakeOrderErrors(data.errors);
                this.makeOrderFormGeneralError = data.message || 'Validation failed.';
                this.isMakeOrderFormSubmitting = false;
                return;
            }

            if (!response.ok) {
                this.makeOrderFormGeneralError = 'Something went wrong. Please try again.';
                this.isMakeOrderFormSubmitting = false;
                return;
            }

            await refreshSection('makeOrders');
            if (navigationStateUrl !== '') {
                await refreshNavigationState(navigationStateUrl);
            }
            this.closeMakeOrderForm();
        },
        async createMakeOrderFromUrl(makeUrl) {
            if (!this.canExecute || !makeUrl) {
                return;
            }

            const response = await fetch(makeUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': this.makeOrderCsrfToken,
                },
                body: JSON.stringify({
                    runs: '1.000000',
                }),
            });

            if (!response.ok) {
                return;
            }

            const data = await response.json();

            if (data?.data?.show_url) {
                window.location.assign(data.data.show_url);
            }
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
                        materialQuantityCostText: materialPurchaseOrderQuantityCostText(record),
                        materialLineTotalAmountText: asString(
                            record.material_line_total_amount_display,
                            formatMoneyAmount(record.po_grand_total_cents)
                        ),
                        materialLineTotalCurrencyText: asString(
                            record.material_line_total_currency_code,
                            tenantCurrency
                        ),
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
                const versionNumber = asString(
                    record.display_version_number_display,
                    asString(record.current_version_number_display, '—')
                );

                return {
                    ...record,
                    display: {
                        nameText: asString(record.name, 'Unnamed recipe'),
                        recipeTypeText: asString(record.recipe_type_label, '—'),
                        updatedAtText: asString(record.updated_at, '—'),
                        outputQuantityText: asString(record.output_quantity_display, '—'),
                        statusText: state.text,
                        statusTone: state.tone,
                        versionText: versionNumber === '—' ? 'v—' : `v${versionNumber}`,
                        versionTone: 'muted',
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
                if (action.handlerKey === 'createMakeOrder') {
                    await pageState?.createMakeOrderFromUrl(record.make_url || '');
                }
            },
        },
        inventoryCounts: {
            normalizeRow: (record) => ({
                ...record,
                display: {
                    nameText: asString(record.name, '—'),
                    countedAtText: asString(record.counted_at, '—'),
                    assignedToText: asString(record.assigned_to_user_name),
                    countedQuantityText: asString(record.counted_quantity_display),
                    uomNameText: asString(record.uom_name),
                    uomSymbolText: asString(record.uom_symbol),
                    statusText: asString(record.status_label),
                    statusTone: asString(record.status_tone, 'muted'),
                    showUrl: asString(record.show_url),
                },
            }),
            handleCreateAction: async ({ action }) => {
                if (!action || action.handlerKey !== 'openInventoryCountCreate') {
                    return;
                }

                openInventoryCountCreate();
            },
            handleAction: async () => {},
        },
        stockMoves: {
            normalizeRow: (record) => ({
                ...record,
                display: {
                    sourceText: asString(record.source_text, 'Stock Move'),
                    movedAtText: asString(record.moved_at_text, '—'),
                    quantityDisplay: asString(record.quantity_display, '—'),
                    uomSymbol: asString(record.uom_symbol),
                    typeText: asString(record.type_text, '—'),
                    typeTone: asString(record.type_tone, 'muted'),
                },
            }),
            handleAction: async () => {},
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
                        skuText: asString(record.supplier_sku),
                        stateText: state.text,
                        stateTone: state.tone,
                        priceText: asString(record.current_price_display, 'No price'),
                        showUrl: asString(record.show_url),
                    },
                };
            },
            buildCreatePayload: (form) => buildSupplierPackagePayload(form),
            buildUpdatePayload: (form) => buildSupplierPackagePayload(form),
            handleCreateSuccess: async ({ component }) => {
                component.isFormOpen = false;
                await component.fetchPage(component.meta.current_page || 1);

                return true;
            },
            handleAction: async ({ action, record }) => {
                if (action.handlerKey === 'purchase') {
                    await createPurchaseOrderFromSupplierPackage(record);
                }
            },
        },
    };

    rootEl.querySelectorAll('[data-js-crud-section-root]').forEach((sectionRootEl) => {
        const sectionKey = sectionRootEl.dataset.sectionKey || '';
        const sectionConfig = safePayload.sections?.[sectionKey] || null;

        mountMaterialSection(sectionKey, sectionConfig);
    });
}
