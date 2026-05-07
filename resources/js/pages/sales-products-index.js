import { refreshNavigationState } from '../navigation/refresh-navigation-state';

export function mount(rootEl, payload) {
    const Alpine = window.Alpine;
    const safePayload = payload || {};
    const loadingPreviewLabel = 'Loading preview...';

    const emptyErrors = () => ({
        source: [],
    });

    Alpine.data('salesProductsIndex', () => ({
        products: safePayload.products || [],
        uoms: safePayload.uoms || [],
        sources: safePayload.sources || [],
        canManageImports: Boolean(safePayload.canManageImports),
        canManageConnections: Boolean(safePayload.canManageConnections),
        connectorsPageUrl: safePayload.connectorsPageUrl || '',
        previewUrl: safePayload.previewUrl || '',
        importUrl: safePayload.importUrl || '',
        navigationStateUrl: safePayload.navigationStateUrl || '',
        csrfToken: safePayload.csrfToken || '',
        isImportPanelOpen: false,
        selectedSource: '',
        previewRows: [],
        errors: emptyErrors(),
        previewError: '',
        importError: '',
        importValidationErrors: {},
        bulkManufacturable: false,
        bulkPurchasable: false,
        bulkBaseUomId: '',
        createFulfillmentRecipes: true,
        isLoadingPreview: false,
        loadingPreviewLabel,
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
        openImportPanel() {
            if (!this.canManageImports) {
                return;
            }

            this.isImportPanelOpen = true;
            this.resetImportState();
        },
        closeImportPanel() {
            this.isImportPanelOpen = false;
            this.resetImportState();
        },
        resetImportState() {
            this.selectedSource = '';
            this.previewRows = [];
            this.errors = emptyErrors();
            this.previewError = '';
            this.importError = '';
            this.importValidationErrors = {};
            this.isLoadingPreview = false;
            this.bulkManufacturable = false;
            this.bulkPurchasable = false;
            this.bulkBaseUomId = '';
            this.createFulfillmentRecipes = true;
        },
        handleSourceChange() {
            this.previewRows = [];
            this.errors = emptyErrors();
            this.previewError = '';
            this.importError = '';
            this.importValidationErrors = {};
            this.isLoadingPreview = false;
        },
        selectedSourceMeta() {
            return this.sources.find((source) => source.value === this.selectedSource) || null;
        },
        selectedSourceEnabled() {
            return Boolean(this.selectedSourceMeta()?.enabled);
        },
        sourceConnected() {
            return Boolean(this.selectedSourceMeta()?.connected);
        },
        selectedSourceStatusLabel() {
            return this.selectedSourceMeta()?.status_label || '';
        },
        rowError(index, field) {
            const key = `rows.${index}.${field}`;
            const errors = this.importValidationErrors[key];

            return Array.isArray(errors) && errors.length > 0 ? errors[0] : '';
        },
        async loadPreview() {
            this.previewError = '';
            this.importError = '';
            this.importValidationErrors = {};
            this.isLoadingPreview = true;

            try {
                const response = await fetch(this.previewUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        Accept: 'application/json',
                        'X-CSRF-TOKEN': this.csrfToken,
                    },
                    body: JSON.stringify({
                        source: this.selectedSource,
                    }),
                });

                if (response.status === 422) {
                    const data = await response.json();
                    this.previewError = data.message || 'Unable to load preview.';
                    this.errors.source = Array.isArray(data.errors?.source) ? data.errors.source : [];
                    return;
                }

                if (!response.ok) {
                    this.previewError = 'Unable to load preview.';
                    return;
                }

                const data = await response.json();
                this.previewRows = (data.data?.rows || []).map((row) => ({
                    ...row,
                    selected: true,
                    base_uom_id: row.base_uom_id ? String(row.base_uom_id) : '',
                    is_manufacturable: null,
                    is_purchasable: null,
                }));
            } catch (error) {
                this.previewError = 'Unable to load preview.';
            } finally {
                this.isLoadingPreview = false;
            }
        },
        async submitImport() {
            this.importError = '';
            this.importValidationErrors = {};

            const rows = this.previewRows
                .filter((row) => row.selected)
                .map((row) => {
                    const payload = {
                        external_id: row.external_id,
                        name: row.name,
                        sku: row.sku,
                        base_uom_id: row.base_uom_id === '' ? null : Number(row.base_uom_id),
                        is_active: Boolean(row.is_active),
                    };

                    if (row.is_manufacturable !== null && row.is_manufacturable !== undefined) {
                        payload.is_manufacturable = Boolean(row.is_manufacturable);
                    }

                    if (row.is_purchasable !== null && row.is_purchasable !== undefined) {
                        payload.is_purchasable = Boolean(row.is_purchasable);
                    }

                    return payload;
                });

            const response = await fetch(this.importUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': this.csrfToken,
                },
                body: JSON.stringify({
                    source: this.selectedSource,
                    create_fulfillment_recipes: this.createFulfillmentRecipes,
                    import_all_as_manufacturable: this.bulkManufacturable,
                    import_all_as_purchasable: this.bulkPurchasable,
                    bulk_base_uom_id: this.bulkBaseUomId === '' ? null : Number(this.bulkBaseUomId),
                    rows,
                }),
            });

            if (response.status === 422) {
                const data = await response.json();
                this.importError = data.message || 'Unable to import products.';
                this.importValidationErrors = data.errors || {};
                return;
            }

            if (!response.ok) {
                this.importError = 'Unable to import products.';
                return;
            }

            const data = await response.json();
            this.products = [...(data.data?.imported || []), ...this.products];
            await refreshNavigationState(this.navigationStateUrl);
            this.showToast('success', 'Products imported.');
            this.closeImportPanel();
        },
    }));
}
