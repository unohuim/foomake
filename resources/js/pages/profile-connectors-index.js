export function mount(rootEl, payload) {
    const Alpine = window.Alpine;
    const safePayload = payload || {};
    const safeWoo = safePayload.wooCommerce || {};

    Alpine.data('profileConnectorsIndex', () => ({
        form: {
            store_url: '',
            consumer_key: '',
            consumer_secret: '',
        },
        storeUrl: safePayload.storeUrl || '',
        disconnectUrl: safePayload.disconnectUrl || '',
        csrfToken: safePayload.csrfToken || '',
        isConnected: Boolean(safeWoo.connected),
        status: safeWoo.status || 'disconnected',
        lastVerifiedAt: safeWoo.last_verified_at || '',
        lastError: safeWoo.last_error || '',
        errors: {},
        formMessage: '',
        get statusLabel() {
            if (this.isConnected && this.lastVerifiedAt) {
                return `Connected. Last verified at ${this.lastVerifiedAt}.`;
            }

            return this.isConnected ? 'Connected.' : 'Disconnected.';
        },
        fieldError(field) {
            const messages = this.errors[field];

            return Array.isArray(messages) && messages.length > 0 ? messages[0] : '';
        },
        async save() {
            this.errors = {};
            this.formMessage = '';

            const response = await fetch(this.storeUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': this.csrfToken,
                },
                body: JSON.stringify(this.form),
            });

            if (response.status === 422) {
                const data = await response.json();
                this.errors = data.errors || {};
                this.formMessage = data.message || 'Unable to save the WooCommerce connection.';
                return;
            }

            if (!response.ok) {
                this.formMessage = 'Unable to save the WooCommerce connection.';
                return;
            }

            const data = await response.json();
            this.isConnected = Boolean(data.data?.connected);
            this.status = data.data?.status || 'connected';
            this.lastVerifiedAt = data.data?.last_verified_at || '';
            this.lastError = data.data?.last_error || '';
            this.form = {
                store_url: '',
                consumer_key: '',
                consumer_secret: '',
            };
            this.formMessage = '';
        },
        async disconnect() {
            this.errors = {};
            this.formMessage = '';

            const response = await fetch(this.disconnectUrl, {
                method: 'DELETE',
                headers: {
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': this.csrfToken,
                },
            });

            if (!response.ok) {
                this.formMessage = 'Unable to disconnect the WooCommerce connection.';
                return;
            }

            const data = await response.json();
            this.isConnected = Boolean(data.data?.connected);
            this.status = data.data?.status || 'disconnected';
            this.lastVerifiedAt = data.data?.last_verified_at || '';
            this.lastError = data.data?.last_error || '';
            this.form = {
                store_url: '',
                consumer_key: '',
                consumer_secret: '',
            };
        },
    }));
}
