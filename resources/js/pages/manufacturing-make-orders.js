import Alpine from 'alpinejs';

export function mount(rootEl, payload) {
    const safePayload = payload || {};
    const emptyCreateErrors = () => ({
        recipe_id: [],
        output_quantity: [],
    });
    const emptyScheduleErrors = () => ({
        due_date: [],
        recipe_id: [],
    });
    const emptyMakeErrors = () => ({
        recipe_id: [],
    });

    Alpine.data('manufacturingMakeOrders', () => ({
        makeOrders: safePayload.make_orders || [],
        recipes: safePayload.recipes || [],
        storeUrl: safePayload.store_url || '',
        scheduleUrlBase: safePayload.schedule_url_base || '',
        makeUrlBase: safePayload.make_url_base || '',
        csrfToken: safePayload.csrf_token || '',
        canExecute: Boolean(safePayload.can_execute),
        createForm: {
            recipe_id: '',
            output_quantity: '',
        },
        createErrors: emptyCreateErrors(),
        createGeneralError: '',
        isCreateSubmitting: false,
        scheduleDates: {},
        scheduleErrors: {},
        scheduleSubmitting: {},
        makeErrors: {},
        makeSubmitting: {},
        toast: {
            visible: false,
            message: '',
            type: 'success',
            timeoutId: null,
        },
        normalizeCreateErrors(errors) {
            if (!errors || typeof errors !== 'object') {
                return emptyCreateErrors();
            }

            return {
                ...emptyCreateErrors(),
                ...errors,
                recipe_id: Array.isArray(errors.recipe_id) ? errors.recipe_id : [],
                output_quantity: Array.isArray(errors.output_quantity) ? errors.output_quantity : [],
            };
        },
        normalizeScheduleErrors(errors) {
            if (!errors || typeof errors !== 'object') {
                return emptyScheduleErrors();
            }

            return {
                ...emptyScheduleErrors(),
                ...errors,
                due_date: Array.isArray(errors.due_date) ? errors.due_date : [],
                recipe_id: Array.isArray(errors.recipe_id) ? errors.recipe_id : [],
            };
        },
        normalizeMakeErrors(errors) {
            if (!errors || typeof errors !== 'object') {
                return emptyMakeErrors();
            }

            return {
                ...emptyMakeErrors(),
                ...errors,
                recipe_id: Array.isArray(errors.recipe_id) ? errors.recipe_id : [],
            };
        },
        getScheduleErrors(id) {
            if (!this.scheduleErrors[id]) {
                this.scheduleErrors[id] = emptyScheduleErrors();
            }

            return this.scheduleErrors[id];
        },
        getMakeErrors(id) {
            if (!this.makeErrors[id]) {
                this.makeErrors[id] = emptyMakeErrors();
            }

            return this.makeErrors[id];
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
        updateOrderInList(order) {
            const index = this.makeOrders.findIndex((item) => item.id === order.id);

            if (index === -1) {
                return;
            }

            this.makeOrders.splice(index, 1, {
                ...this.makeOrders[index],
                ...order,
            });
        },
        async submitCreate() {
            if (!this.canExecute) {
                this.createGeneralError = 'You do not have permission to create make orders.';
                return;
            }

            this.isCreateSubmitting = true;
            this.createGeneralError = '';
            this.createErrors = emptyCreateErrors();

            const response = await fetch(this.storeUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': this.csrfToken,
                },
                body: JSON.stringify({
                    recipe_id: this.createForm.recipe_id ? Number(this.createForm.recipe_id) : this.createForm.recipe_id,
                    output_quantity: this.createForm.output_quantity,
                }),
            });

            if (response.status === 422) {
                const data = await response.json();
                this.createErrors = this.normalizeCreateErrors(data.errors);
                this.createGeneralError = data.message || 'Validation failed.';
                this.isCreateSubmitting = false;
                return;
            }

            if (!response.ok) {
                this.createGeneralError = 'Something went wrong. Please try again.';
                this.showToast('error', this.createGeneralError);
                this.isCreateSubmitting = false;
                return;
            }

            const data = await response.json();
            if (data.data) {
                this.makeOrders.unshift(data.data);
            }

            this.showToast('success', 'Make order created.');
            this.createForm = { recipe_id: '', output_quantity: '' };
            this.isCreateSubmitting = false;
        },
        async scheduleOrder(orderId) {
            if (!this.canExecute) {
                this.showToast('error', 'You do not have permission to schedule make orders.');
                return;
            }

            this.scheduleSubmitting[orderId] = true;
            this.scheduleErrors[orderId] = emptyScheduleErrors();

            const dueDate = this.scheduleDates[orderId] || '';
            const response = await fetch(`${this.scheduleUrlBase}/${orderId}/schedule`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': this.csrfToken,
                },
                body: JSON.stringify({
                    due_date: dueDate,
                }),
            });

            if (response.status === 422) {
                const data = await response.json();
                this.scheduleErrors[orderId] = this.normalizeScheduleErrors(data.errors);
                this.showToast('error', data.message || 'Unable to schedule make order.');
                this.scheduleSubmitting[orderId] = false;
                return;
            }

            if (!response.ok) {
                this.showToast('error', 'Unable to schedule make order.');
                this.scheduleSubmitting[orderId] = false;
                return;
            }

            const data = await response.json();
            if (data.data) {
                this.updateOrderInList(data.data);
                this.scheduleDates[orderId] = data.data.due_date || '';
            }

            this.showToast('success', 'Make order scheduled.');
            this.scheduleSubmitting[orderId] = false;
        },
        async makeOrder(orderId) {
            if (!this.canExecute) {
                this.showToast('error', 'You do not have permission to make orders.');
                return;
            }

            this.makeSubmitting[orderId] = true;
            this.makeErrors[orderId] = emptyMakeErrors();

            const response = await fetch(`${this.makeUrlBase}/${orderId}/make`, {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': this.csrfToken,
                },
            });

            if (response.status === 422) {
                const data = await response.json();
                this.makeErrors[orderId] = this.normalizeMakeErrors(data.errors);
                this.showToast('error', data.message || 'Unable to make order.');
                this.makeSubmitting[orderId] = false;
                return;
            }

            if (!response.ok) {
                this.showToast('error', 'Unable to make order.');
                this.makeSubmitting[orderId] = false;
                return;
            }

            const data = await response.json();
            if (data.data) {
                this.updateOrderInList(data.data);
            }

            this.showToast('success', 'Make order completed.');
            this.makeSubmitting[orderId] = false;
        },
    }));
}
