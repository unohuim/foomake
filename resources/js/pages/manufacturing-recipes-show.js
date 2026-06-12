import Alpine from 'alpinejs';
import { mountCrudSection } from '../lib/js-crud-section';

const emptyRecipeErrors = () => ({
    name: [],
    is_active: [],
    is_default: [],
});

const emptyVersionErrors = () => ({
    recipe_type: [],
    output_quantity: [],
});

const asRecord = (value) => (value && typeof value === 'object' && !Array.isArray(value) ? value : {});
const asArray = (value) => (Array.isArray(value) ? value : []);
const asString = (value, fallback = '') => (typeof value === 'string' && value.trim() !== '' ? value : fallback);

export function mount(rootEl, payload) {
    const safePayload = payload || {};
    const sectionRootsByKey = new Map();
    let pageState = null;

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

    const updateSectionConfig = (sectionKey, sectionConfig) => {
        const sectionRootEl = sectionRootsByKey.get(sectionKey);

        if (!sectionRootEl?._jsCrudSectionApi?.updateSectionConfig) {
            return;
        }

        sectionRootEl._jsCrudSectionApi.updateSectionConfig(sectionConfig);
    };

    Alpine.data('manufacturingRecipesShow', () => ({
        recipe: safePayload.recipe || {},
        sections: safePayload.sections || {},
        ingredients: safePayload.ingredients || {
            can_edit: false,
            display_version_id: null,
            display_version_number: '—',
            item_options: [],
            lines: [],
            store_url: null,
            update_url_template: null,
        },
        csrfToken: safePayload.csrf_token || '',
        indexUrl: safePayload.index_url || '',
        recipeTypeOptions: Array.isArray(safePayload.recipe_type_options) ? safePayload.recipe_type_options : [],
        selectedIngredientItemId: '',
        ingredientsSaving: false,
        ingredientSavedState: {},
        isEditOpen: false,
        isEditSubmitting: false,
        editForm: {
            name: '',
            is_active: true,
            is_default: false,
        },
        editErrors: emptyRecipeErrors(),
        editGeneralError: '',
        isDeleteOpen: false,
        isDeleteSubmitting: false,
        deleteError: '',
        deleteRecipeName: '',
        isVersionOpen: false,
        isVersionSubmitting: false,
        versionRecipeName: '',
        versionForm: {
            recipe_type: 'manufacturing',
            output_quantity: '1.000000',
        },
        versionErrors: emptyVersionErrors(),
        versionGeneralError: '',
        toast: {
            visible: false,
            message: '',
            type: 'success',
            timeoutId: null,
        },
        recentlyPublishedVersionId: null,
        recentlyPublishedHighlightTimeoutId: null,
        normalizeRecipeErrors(errors) {
            return {
                ...emptyRecipeErrors(),
                ...asRecord(errors),
            };
        },
        normalizeVersionErrors(errors) {
            return {
                ...emptyVersionErrors(),
                ...asRecord(errors),
            };
        },
        activeVersion() {
            const activeVersion = asRecord(this.recipe.active_version);

            return activeVersion.id ? activeVersion : null;
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
        hydrateRecipeResponse(data) {
            if (data.recipe && typeof data.recipe === 'object') {
                this.recipe = {
                    ...this.recipe,
                    ...data.recipe,
                };

                window.dispatchEvent(new CustomEvent('recipe-active-version-sync', {
                    detail: {
                        activeVersion: asRecord(data.recipe.active_version),
                    },
                }));
            }

            if (data.ingredients && typeof data.ingredients === 'object') {
                this.ingredients = {
                    ...this.ingredients,
                    ...data.ingredients,
                    item_options: asArray(data.ingredients.item_options),
                    lines: asArray(data.ingredients.lines),
                };
            }

            if (data.sections && typeof data.sections === 'object') {
                this.sections = {
                    ...this.sections,
                    ...data.sections,
                };

                Object.entries(data.sections).forEach(([sectionKey, sectionConfig]) => {
                    if (sectionConfig && typeof sectionConfig === 'object') {
                        updateSectionConfig(sectionKey, sectionConfig);
                    }
                });
            }

            this.applyPublishUiState(data.ui);
        },
        applyPublishUiState(ui) {
            const versionId = Number(ui?.recently_published_version_id || 0);
            const highlightDurationMs = Number(ui?.recently_published_highlight_ms || 1000);

            if (versionId <= 0) {
                return;
            }

            this.recentlyPublishedVersionId = versionId;

            if (this.recentlyPublishedHighlightTimeoutId) {
                clearTimeout(this.recentlyPublishedHighlightTimeoutId);
            }

            this.recentlyPublishedHighlightTimeoutId = setTimeout(async () => {
                this.recentlyPublishedVersionId = null;
                this.recentlyPublishedHighlightTimeoutId = null;
                await refreshSection('versions');
            }, highlightDurationMs);
        },
        openEditRecipe() {
            this.editErrors = emptyRecipeErrors();
            this.editGeneralError = '';
            this.editForm = {
                name: this.recipe.name || '',
                is_active: this.recipe.version_status !== 'ARCHIVED',
                is_default: Boolean(this.recipe.is_default),
            };
            this.isEditOpen = true;
        },
        closeEdit() {
            this.isEditOpen = false;
            this.isEditSubmitting = false;
            this.editErrors = emptyRecipeErrors();
            this.editGeneralError = '';
        },
        openDeleteRecipe() {
            this.deleteRecipeName = this.recipe.name || this.recipe.output_item_name || '';
            this.deleteError = '';
            this.isDeleteOpen = true;
        },
        closeDelete() {
            this.isDeleteOpen = false;
            this.isDeleteSubmitting = false;
            this.deleteError = '';
        },
        openVersion() {
            this.versionRecipeName = this.recipe.name || '';
            this.versionErrors = emptyVersionErrors();
            this.versionGeneralError = '';
            this.versionForm = {
                recipe_type: this.recipe.display_recipe_type || 'manufacturing',
                output_quantity: this.recipe.display_output_quantity || '1.000000',
            };
            this.isVersionOpen = true;
        },
        closeVersion() {
            this.isVersionOpen = false;
            this.isVersionSubmitting = false;
            this.versionErrors = emptyVersionErrors();
            this.versionGeneralError = '';
        },
        versionRecipeTypeOptions() {
            return this.recipeTypeOptions;
        },
        async submitVersion() {
            this.isVersionSubmitting = true;
            this.versionErrors = emptyVersionErrors();
            this.versionGeneralError = '';

            const response = await fetch(this.recipe.version_store_url, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': this.csrfToken,
                },
                body: JSON.stringify(this.versionForm),
            });

            if (response.status === 422) {
                const data = await response.json();
                this.versionErrors = this.normalizeVersionErrors(data.errors);
                this.versionGeneralError = data.message || 'Validation failed.';
                this.isVersionSubmitting = false;
                return;
            }

            if (!response.ok) {
                this.versionGeneralError = 'Something went wrong. Please try again.';
                this.isVersionSubmitting = false;
                return;
            }

            this.hydrateRecipeResponse(await response.json());
            await refreshSection('versions');
            this.closeVersion();
            this.showToast('success', 'Recipe version created.');
        },
        async performVersionAction(action, record, afterSuccess = null) {
            if (!record || !action || typeof action !== 'object') {
                return;
            }

            if (action.handlerKey === 'viewVersion' || action.type === 'view') {
                return;
            }

            const actionToUrl = {
                checkoutVersion: record.checkout_url,
                checkInVersion: record.check_in_url,
                publishVersion: record.publish_url,
                duplicateVersion: record.duplicate_url,
                archiveVersion: record.archive_url,
                deleteVersion: record.remove_url || record.delete_url,
            };
            const actionToMethod = {
                checkoutVersion: 'POST',
                checkInVersion: 'POST',
                publishVersion: 'PATCH',
                duplicateVersion: 'POST',
                archiveVersion: 'PATCH',
                deleteVersion: 'DELETE',
            };

            const endpoint = asString(action.endpoint || '', '')
                || actionToUrl[action.handlerKey]
                || actionToUrl[action.type];

            if (!endpoint) {
                return;
            }

            const response = await fetch(endpoint, {
                method: asString(action.method || '', '') || actionToMethod[action.handlerKey] || actionToMethod[action.type] || 'POST',
                headers: {
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': this.csrfToken,
                },
            });

            if (!response.ok) {
                this.showToast('error', 'Unable to update recipe version.');
                return;
            }

            const data = await response.json().catch(() => ({}));

            if (data?.data?.show_url) {
                window.location.assign(data.data.show_url);
                return;
            }

            if (data && typeof data === 'object') {
                this.hydrateRecipeResponse(data);
            }

            if (typeof afterSuccess === 'function') {
                await afterSuccess();
            }
            this.showToast('success', 'Recipe version updated.');
        },
        async performHeaderVersionAction(action) {
            const activeVersion = this.activeVersion();

            if (!activeVersion) {
                return;
            }

            await this.performVersionAction(action, activeVersion, async () => {
                await refreshSection('versions');
            });
        },
        async submitDelete() {
            this.isDeleteSubmitting = true;
            this.deleteError = '';

            const response = await fetch(this.recipe.delete_url, {
                method: 'DELETE',
                headers: {
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': this.csrfToken,
                },
            });

            if (!response.ok) {
                const data = await response.json().catch(() => ({}));
                this.deleteError = data.message || 'Unable to delete recipe.';
                this.showToast('error', this.deleteError);
                this.isDeleteSubmitting = false;
                return;
            }

            if (this.indexUrl) {
                window.location.assign(this.indexUrl);
            }
        },
        async submitEdit() {
            this.isEditSubmitting = true;
            this.editGeneralError = '';
            this.editErrors = emptyRecipeErrors();

            const response = await fetch(this.recipe.update_url, {
                method: 'PATCH',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': this.csrfToken,
                },
                body: JSON.stringify({
                    name: this.editForm.name,
                    is_active: this.editForm.is_active,
                    is_default: this.editForm.is_default,
                }),
            });

            if (response.status === 422) {
                const data = await response.json();
                this.editErrors = this.normalizeRecipeErrors(data.errors);
                this.editGeneralError = data.message || 'Validation failed.';
                this.isEditSubmitting = false;
                return;
            }

            if (!response.ok) {
                this.editGeneralError = 'Something went wrong. Please try again.';
                this.showToast('error', 'Unable to update recipe.');
                this.isEditSubmitting = false;
                return;
            }

            this.recipe = {
                ...this.recipe,
                ...(await response.json()).data,
            };

            this.closeEdit();
            this.showToast('success', 'Recipe updated.');
        },
        async addIngredient() {
            if (!this.ingredients.can_edit || !this.ingredients.store_url || !this.selectedIngredientItemId) {
                return;
            }

            this.ingredientsSaving = true;

            const response = await fetch(this.ingredients.store_url, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': this.csrfToken,
                },
                body: JSON.stringify({
                    item_id: Number(this.selectedIngredientItemId),
                }),
            });

            this.ingredientsSaving = false;

            if (!response.ok) {
                this.showToast('error', 'Unable to add ingredient.');
                return;
            }

            const data = await response.json();
            this.ingredients.lines.push(data.data);
            this.selectedIngredientItemId = '';
            this.showToast('success', 'Ingredient added.');
        },
        async saveIngredientQuantity(line) {
            if (!this.ingredients.can_edit || !this.ingredients.update_url_template) {
                return;
            }

            this.ingredientSavedState[line.id] = 'saving';

            const response = await fetch(this.ingredients.update_url_template.replace('__LINE__', encodeURIComponent(String(line.id))), {
                method: 'PATCH',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': this.csrfToken,
                },
                body: JSON.stringify({
                    quantity: line.quantity_input,
                }),
            });

            if (!response.ok) {
                this.ingredientSavedState[line.id] = 'error';
                this.showToast('error', 'Unable to save ingredient quantity.');
                return;
            }

            const data = await response.json();
            const nextLine = data.data || {};
            this.ingredients.lines = this.ingredients.lines.map((entry) => (entry.id === line.id ? nextLine : entry));
            this.ingredientSavedState[line.id] = data.meta?.saved ? 'saved' : '';
            this.showToast('success', 'Ingredient saved.');

            setTimeout(() => {
                if (this.ingredientSavedState[line.id] === 'saved') {
                    this.ingredientSavedState[line.id] = '';
                }
            }, 1000);
        },
        async removeIngredient(line) {
            if (!this.ingredients.can_edit || !line?.remove_url) {
                return;
            }

            const response = await fetch(line.remove_url, {
                method: 'DELETE',
                headers: {
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': this.csrfToken,
                },
            });

            if (!response.ok) {
                this.showToast('error', 'Unable to remove ingredient.');
                return;
            }

            this.hydrateRecipeResponse(await response.json());
            this.showToast('success', 'Ingredient removed.');
        },
        async createMakeOrder(makeUrl) {
            if (!makeUrl) {
                return;
            }

            const response = await fetch(makeUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': this.csrfToken,
                },
                body: JSON.stringify({
                    runs: '1.000000',
                }),
            });

            if (!response.ok) {
                this.showToast('error', 'Unable to create make order.');
                return;
            }

            const data = await response.json();

            if (data?.data?.show_url) {
                window.location.assign(data.data.show_url);
            }
        },
        init() {
            pageState = this;
            return undefined;
        },
    }));

    const adaptersBySectionKey = {
        versions: {
            normalizeRow: (record) => ({
                ...record,
                rowClass: (pageState ? pageState.recentlyPublishedVersionId : null) === Number(record.id)
                    ? 'border-l-4 border-lime-500'
                    : '',
                display: {
                    versionText: asString(record.display?.versionText, record.version_number_display || '—'),
                    typeText: asString(record.display?.typeText, record.recipe_type || '—'),
                    contextText: asString(record.display?.contextText, '—'),
                    statusText: asString(record.display?.statusText, record.status || '—'),
                    statusTone: asString(record.display?.statusTone, 'muted'),
                    outputQuantityText: asString(record.display?.outputQuantityText, record.output_quantity || '—'),
                    updatedAtText: asString(record.display?.updatedAtText, record.updated_at || '—'),
                },
                formValues: {
                    recipe_type: asString(record.formValues?.recipe_type, 'manufacturing'),
                    output_quantity: asString(record.formValues?.output_quantity, '1.000000'),
                },
            }),
            buildListParams: (toggleValues) => ({
                include_archived: toggleValues.include_archived ? '1' : '',
            }),
            buildUpdatePayload: (form) => ({
                recipe_type: asString(form.recipe_type, 'manufacturing'),
                output_quantity: asString(form.output_quantity, '1.000000'),
            }),
            handleCreateAction: () => {
                pageState?.openVersion();
            },
            handleAction: async ({ action, record, component }) => {
                await pageState?.performVersionAction(action, record, async () => {
                    await component.fetchPage(component.meta.current_page || 1);
                });
            },
        },
        makeOrders: {
            normalizeRow: (record) => ({
                ...record,
                display: {
                    recipeNameText: asString(record.recipe_name, 'Unnamed recipe'),
                    runsText: asString(record.runs_display, '—'),
                    dueDateText: asString(record.due_date, 'No due date'),
                    totalOutputQuantityText: asString(record.qty_display, '—'),
                    statusText: asString(record.workflow_state, '—'),
                    statusTone: record.workflow_state === 'DRAFT' ? 'muted' : 'default',
                    versionBadgeText: `v${asString(record.recipe_version_number_display, '—')}`,
                    versionBadgeTone: 'subtle',
                    showUrl: asString(record.show_url, ''),
                },
            }),
            buildListParams: (_toggleValues, section) => ({
                mobile_page_size: section?.mobilePageSize || '',
            }),
            handleCreateAction: async ({ section }) => {
                await pageState?.createMakeOrder(section?.endpoints?.create || '');
            },
            handleAction: async () => {},
        },
    };

    rootEl.querySelectorAll('[data-js-crud-section-root]').forEach((sectionRootEl) => {
        const sectionKey = sectionRootEl.dataset.sectionKey || '';
        const sectionConfig = safePayload.sections?.[sectionKey];

        if (!sectionConfig) {
            return;
        }

        mountCrudSection(sectionRootEl, {
            section: sectionConfig,
            adapters: adaptersBySectionKey[sectionKey] || {},
        });
    });
}
