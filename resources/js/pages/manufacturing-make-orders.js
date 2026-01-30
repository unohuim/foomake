import Alpine from 'alpinejs';

export function mount(rootEl, payload) {
    const safePayload = payload || {};
    const emptyErrors = () => ({
        recipe_id: [],
        output_quantity: [],
    });
    const emptySummary = () => ({
        recipe_id: '',
        output_item_id: '',
        output_item_name: '',
        output_quantity: '',
        issue_count: 0,
        receipt_count: 0,
        move_count: 0,
    });

    Alpine.data('manufacturingMakeOrders', () => ({
        recipes: safePayload.recipes || [],
        executeUrl: safePayload.execute_url || '',
        csrfToken: safePayload.csrf_token || '',
        canExecute: Boolean(safePayload.can_execute),
        form: {
            recipe_id: '',
            output_quantity: '',
        },
        errors: emptyErrors(),
        generalError: '',
        isSubmitting: false,
        summary: emptySummary(),
        summaryVisible: false,
        toast: {
            visible: false,
            message: '',
            type: 'success',
            timeoutId: null,
        },
        normalizeErrors(errors) {
            if (!errors || typeof errors !== 'object') {
                return emptyErrors();
            }

            return {
                ...emptyErrors(),
                ...errors,
                recipe_id: Array.isArray(errors.recipe_id) ? errors.recipe_id : [],
                output_quantity: Array.isArray(errors.output_quantity) ? errors.output_quantity : [],
            };
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
        async submit() {
            if (!this.canExecute) {
                this.generalError = 'You do not have permission to execute make orders.';
                return;
            }

            this.isSubmitting = true;
            this.errors = emptyErrors();
            this.generalError = '';
            this.summaryVisible = false;

            const payload = {
                recipe_id: this.form.recipe_id ? Number(this.form.recipe_id) : this.form.recipe_id,
                output_quantity: this.form.output_quantity,
            };

            const response = await fetch(this.executeUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': this.csrfToken,
                },
                body: JSON.stringify(payload),
            });

            if (response.status === 422) {
                const data = await response.json();
                this.errors = this.normalizeErrors(data.errors);
                this.generalError = data.message || 'Validation failed.';
                this.isSubmitting = false;
                return;
            }

            if (!response.ok) {
                this.generalError = 'Something went wrong. Please try again.';
                this.showToast('error', this.generalError);
                this.isSubmitting = false;
                return;
            }

            const data = await response.json();
            this.summary = data.summary || emptySummary();
            this.summaryVisible = Boolean(data.summary);
            this.showToast('success', data.toast?.message || 'Make order executed.');
            this.isSubmitting = false;
        },
    }));
}
