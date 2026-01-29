import Alpine from "alpinejs";

export function mount(rootEl, payload) {
    const safePayload = payload || {};

    Alpine.data("manufacturingUomsIndex", () => ({
        categories: safePayload.categories || [],
        storeUrl: safePayload.storeUrl || "",
        updateUrlTemplate: safePayload.updateUrlTemplate || "",
        deleteUrlTemplate: safePayload.deleteUrlTemplate || "",
        csrfToken: safePayload.csrfToken || "",
        formOpen: false,
        deleteOpen: false,
        isEditing: false,
        isSubmitting: false,
        form: { id: null, name: "", symbol: "", uom_category_id: "" },
        errors: {},
        errorMessage: "",
        toastMessage: "",
        toastVisible: false,
        deleteTarget: null,

        init() {},

        hasUoms() {
            return this.categories.some(
                (category) => (category.uoms || []).length > 0,
            );
        },

        openCreate() {
            this.resetErrors();
            this.form = { id: null, name: "", symbol: "", uom_category_id: "" };
            this.isEditing = false;
            this.formOpen = true;
        },

        openEdit(uom) {
            this.resetErrors();
            this.form = {
                id: uom.id,
                name: uom.name,
                symbol: uom.symbol,
                uom_category_id: uom.uom_category_id,
            };
            this.isEditing = true;
            this.formOpen = true;
        },

        closeForm() {
            this.formOpen = false;
        },

        openDelete(uom) {
            this.resetErrors();
            this.deleteTarget = uom;
            this.deleteOpen = true;
        },

        closeDelete() {
            this.deleteOpen = false;
            this.deleteTarget = null;
        },

        resetErrors() {
            this.errors = {};
            this.errorMessage = "";
        },

        showToast(message) {
            this.toastMessage = message;
            this.toastVisible = true;

            setTimeout(() => {
                this.toastVisible = false;
            }, 2000);
        },

        normalizeUom(uom) {
            if (!uom) {
                return null;
            }

            return {
                ...uom,
                id: String(uom.id),
                uom_category_id: String(uom.uom_category_id),
            };
        },

        updateUom(uomPayload) {
            const updated = this.normalizeUom(uomPayload);

            if (!updated) {
                return;
            }

            this.removeUom(updated.id);

            const category = this.categories.find(
                (item) => String(item.id) === updated.uom_category_id,
            );

            if (!category) {
                return;
            }

            if (!Array.isArray(category.uoms)) {
                category.uoms = [];
            }

            category.uoms.push(updated);
            category.uoms = category.uoms.sort((a, b) =>
                String(a.name).localeCompare(String(b.name)),
            );
        },

        removeUom(id) {
            const normalizedId = String(id);

            this.categories.forEach((category) => {
                if (!Array.isArray(category.uoms)) {
                    category.uoms = [];
                }

                category.uoms = category.uoms.filter(
                    (uom) => String(uom.id) !== normalizedId,
                );
            });
        },

        async submitForm() {
            this.resetErrors();
            this.isSubmitting = true;

            const url = this.isEditing
                ? this.updateUrlTemplate.replace("__ID__", this.form.id)
                : this.storeUrl;

            const method = this.isEditing ? "PATCH" : "POST";

            const response = await fetch(url, {
                method,
                headers: {
                    Accept: "application/json",
                    "Content-Type": "application/json",
                    "X-CSRF-TOKEN": this.csrfToken,
                },
                body: JSON.stringify({
                    name: this.form.name,
                    symbol: this.form.symbol,
                    uom_category_id: this.form.uom_category_id,
                }),
            });

            this.isSubmitting = false;

            if (response.status === 422) {
                const data = await response.json();
                this.errors = data.errors || {};
                return;
            }

            if (!response.ok) {
                this.errorMessage = "Something went wrong. Please try again.";
                return;
            }

            const data = await response.json();
            this.updateUom(data);
            this.closeForm();
            this.showToast(this.isEditing ? "Unit updated." : "Unit created.");
        },

        async confirmDelete() {
            if (!this.deleteTarget) {
                return;
            }

            this.isSubmitting = true;

            const response = await fetch(
                this.deleteUrlTemplate.replace("__ID__", this.deleteTarget.id),
                {
                    method: "DELETE",
                    headers: {
                        Accept: "application/json",
                        "X-CSRF-TOKEN": this.csrfToken,
                    },
                },
            );

            this.isSubmitting = false;

            if (!response.ok) {
                this.errorMessage = "Unable to delete the unit.";
                return;
            }

            this.removeUom(this.deleteTarget.id);
            this.closeDelete();
            this.showToast("Unit deleted.");
        },
    }));
}
