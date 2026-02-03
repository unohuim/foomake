export function mount(rootEl, payload) {
    const Alpine = window.Alpine;
    const safePayload = payload || {};

    Alpine.data('purchasingOrdersIndex', () => ({
        orders: safePayload.orders || [],
        storeUrl: safePayload.storeUrl || '',
        csrfToken: safePayload.csrfToken || '',
        tenantCurrency: safePayload.tenantCurrency || 'USD',
        isCreating: false,
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
