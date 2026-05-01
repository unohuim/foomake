import Alpine from "alpinejs";

export function mount(rootEl, payload) {
    const safePayload = payload || {};

    Alpine.data("manufacturingUomConversionsIndex", () => ({
        globalConversions: safePayload.globalConversions || [],
        tenantConversions: safePayload.tenantConversions || [],
        itemSpecificConversions: safePayload.itemSpecificConversions || [],
        items: safePayload.items || [],
        uoms: safePayload.uoms || [],
        uomOptions: safePayload.uomOptions || [],
        storeUrl: safePayload.storeUrl || "",
        updateUrlTemplate: safePayload.updateUrlTemplate || "",
        deleteUrlTemplate: safePayload.deleteUrlTemplate || "",
        itemStoreUrl: safePayload.itemStoreUrl || "",
        itemUpdateUrlTemplate: safePayload.itemUpdateUrlTemplate || "",
        itemDeleteUrlTemplate: safePayload.itemDeleteUrlTemplate || "",
        csrfToken: safePayload.csrfToken || "",
        generalFormOpen: false,
        itemFormOpen: false,
        deleteOpen: false,
        generalIsEditing: false,
        itemIsEditing: false,
        isSubmitting: false,
        generalForm: {
            id: null,
            from_uom_id: "",
            to_uom_id: "",
            multiplier: "",
        },
        itemForm: {
            id: null,
            item_id: "",
            from_uom_id: "",
            to_uom_id: "",
            conversion_factor: "",
        },
        generalErrors: {},
        itemErrors: {},
        errorMessage: "",
        toastMessage: "",
        toastVisible: false,
        deleteTarget: null,
        deleteType: null,

        get generalUomOptions() {
            return this.mergeSelectedUomOptions(this.uomOptions, [
                this.generalForm.from_uom_id,
                this.generalForm.to_uom_id,
            ]);
        },

        get itemUomOptions() {
            return this.mergeSelectedUomOptions(this.uomOptions, [
                this.itemForm.from_uom_id,
                this.itemForm.to_uom_id,
            ]);
        },

        resetErrors() {
            this.generalErrors = {};
            this.itemErrors = {};
            this.errorMessage = "";
        },

        showToast(message) {
            this.toastMessage = message;
            this.toastVisible = true;

            setTimeout(() => {
                this.toastVisible = false;
            }, 2000);
        },

        openGeneralCreate() {
            this.resetErrors();
            this.generalIsEditing = false;
            this.generalForm = {
                id: null,
                from_uom_id: "",
                to_uom_id: "",
                multiplier: "",
            };
            this.generalFormOpen = true;
        },

        openGeneralEdit(conversion) {
            this.resetErrors();
            this.generalIsEditing = true;
            this.generalForm = {
                id: conversion.id,
                from_uom_id: String(conversion.from_uom_id),
                to_uom_id: String(conversion.to_uom_id),
                multiplier: conversion.multiplier,
            };
            this.generalFormOpen = true;
        },

        closeGeneralForm() {
            this.generalFormOpen = false;
        },

        openItemCreate() {
            this.resetErrors();
            this.itemIsEditing = false;
            this.itemForm = {
                id: null,
                item_id: "",
                from_uom_id: "",
                to_uom_id: "",
                conversion_factor: "",
            };
            this.itemFormOpen = true;
        },

        openItemEdit(conversion) {
            this.resetErrors();
            this.itemIsEditing = true;
            this.itemForm = {
                id: conversion.id,
                item_id: String(conversion.item_id),
                from_uom_id: String(conversion.from_uom_id),
                to_uom_id: String(conversion.to_uom_id),
                conversion_factor: conversion.conversion_factor,
            };
            this.itemFormOpen = true;
        },

        closeItemForm() {
            this.itemFormOpen = false;
        },

        openGeneralDelete(conversion) {
            this.resetErrors();
            this.deleteTarget = conversion;
            this.deleteType = "general";
            this.deleteOpen = true;
        },

        openItemDelete(conversion) {
            this.resetErrors();
            this.deleteTarget = conversion;
            this.deleteType = "item";
            this.deleteOpen = true;
        },

        closeDelete() {
            this.deleteOpen = false;
            this.deleteTarget = null;
            this.deleteType = null;
        },

        findUom(id) {
            return this.uoms.find((uom) => String(uom.id) === String(id)) || null;
        },

        findItem(id) {
            return this.items.find((item) => String(item.id) === String(id)) || null;
        },

        mergeSelectedUomOptions(options, selectedIds) {
            const merged = [...options];

            selectedIds.forEach((selectedId) => {
                if (!selectedId) {
                    return;
                }

                const exists = merged.some((uom) => String(uom.id) === String(selectedId));

                if (exists) {
                    return;
                }

                const fallback = this.findUom(selectedId);

                if (fallback) {
                    merged.push(fallback);
                }
            });

            return merged;
        },

        hydrateGeneralConversion(row) {
            const fromUom = this.findUom(row.from_uom_id);
            const toUom = this.findUom(row.to_uom_id);

            return {
                ...row,
                from_symbol: row.from_symbol || fromUom?.symbol || "",
                to_symbol: row.to_symbol || toUom?.symbol || "",
                read_only: row.read_only ?? false,
                editable: row.editable ?? true,
            };
        },

        hydrateItemConversion(row) {
            const item = this.findItem(row.item_id);
            const fromUom = this.findUom(row.from_uom_id);
            const toUom = this.findUom(row.to_uom_id);

            return {
                ...row,
                item_name: row.item_name || item?.name || "",
                from_symbol: row.from_symbol || fromUom?.symbol || "",
                to_symbol: row.to_symbol || toUom?.symbol || "",
            };
        },

        upsertTenantConversion(row) {
            const hydrated = this.hydrateGeneralConversion(row);
            const index = this.tenantConversions.findIndex((conversion) => conversion.id === hydrated.id);

            if (index >= 0) {
                this.tenantConversions.splice(index, 1, hydrated);
            } else {
                this.tenantConversions.push(hydrated);
            }
        },

        removeTenantConversion(id) {
            this.tenantConversions = this.tenantConversions.filter((conversion) => conversion.id !== id);
        },

        upsertItemConversion(row) {
            const hydrated = this.hydrateItemConversion(row);
            const index = this.itemSpecificConversions.findIndex((conversion) => conversion.id === hydrated.id);

            if (index >= 0) {
                this.itemSpecificConversions.splice(index, 1, hydrated);
            } else {
                this.itemSpecificConversions.push(hydrated);
            }
        },

        removeItemConversion(id) {
            this.itemSpecificConversions = this.itemSpecificConversions.filter((conversion) => conversion.id !== id);
        },

        async submitGeneralForm() {
            this.resetErrors();
            this.isSubmitting = true;

            const url = this.generalIsEditing
                ? this.updateUrlTemplate.replace("__ID__", this.generalForm.id)
                : this.storeUrl;

            const method = this.generalIsEditing ? "PATCH" : "POST";

            const response = await fetch(url, {
                method,
                headers: {
                    Accept: "application/json",
                    "Content-Type": "application/json",
                    "X-CSRF-TOKEN": this.csrfToken,
                },
                body: JSON.stringify({
                    from_uom_id: this.generalForm.from_uom_id,
                    to_uom_id: this.generalForm.to_uom_id,
                    multiplier: this.generalForm.multiplier,
                }),
            });

            this.isSubmitting = false;

            if (response.status === 422) {
                const data = await response.json();
                this.generalErrors = data.errors || {};
                return;
            }

            if (!response.ok) {
                this.errorMessage = "Something went wrong. Please try again.";
                return;
            }

            const data = await response.json();
            this.upsertTenantConversion(data);
            this.closeGeneralForm();
            this.showToast(this.generalIsEditing ? "Conversion updated." : "Conversion created.");
        },

        async submitItemForm() {
            this.resetErrors();
            this.isSubmitting = true;

            const url = this.itemIsEditing
                ? this.itemUpdateUrlTemplate.replace("__ID__", this.itemForm.id)
                : this.itemStoreUrl;

            const method = this.itemIsEditing ? "PATCH" : "POST";

            const response = await fetch(url, {
                method,
                headers: {
                    Accept: "application/json",
                    "Content-Type": "application/json",
                    "X-CSRF-TOKEN": this.csrfToken,
                },
                body: JSON.stringify({
                    item_id: this.itemForm.item_id,
                    from_uom_id: this.itemForm.from_uom_id,
                    to_uom_id: this.itemForm.to_uom_id,
                    conversion_factor: this.itemForm.conversion_factor,
                }),
            });

            this.isSubmitting = false;

            if (response.status === 422) {
                const data = await response.json();
                this.itemErrors = data.errors || {};
                return;
            }

            if (!response.ok) {
                this.errorMessage = "Something went wrong. Please try again.";
                return;
            }

            const data = await response.json();
            this.upsertItemConversion(data);
            this.closeItemForm();
            this.showToast(this.itemIsEditing ? "Item conversion updated." : "Item conversion created.");
        },

        async confirmDelete() {
            if (!this.deleteTarget || !this.deleteType) {
                return;
            }

            this.isSubmitting = true;

            const url = this.deleteType === "general"
                ? this.deleteUrlTemplate.replace("__ID__", this.deleteTarget.id)
                : this.itemDeleteUrlTemplate.replace("__ID__", this.deleteTarget.id);

            const response = await fetch(url, {
                method: "DELETE",
                headers: {
                    Accept: "application/json",
                    "X-CSRF-TOKEN": this.csrfToken,
                },
            });

            this.isSubmitting = false;

            if (!response.ok) {
                this.errorMessage = "Unable to delete the conversion.";
                return;
            }

            if (this.deleteType === "general") {
                this.removeTenantConversion(this.deleteTarget.id);
            } else {
                this.removeItemConversion(this.deleteTarget.id);
            }

            this.closeDelete();
            this.showToast("Conversion deleted.");
        },
    }));
}
