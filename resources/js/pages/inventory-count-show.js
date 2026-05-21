import Alpine from 'alpinejs';
import { mountCrudSection } from '../lib/js-crud-section';

const inventoryCountToastEvent = 'inventory-count-toast';

export function mount(rootEl, payload) {
    const safePayload = payload || {};
    const csrfToken = document.querySelector('meta[name=csrf-token]')?.getAttribute('content') ?? '';

    rootEl.querySelectorAll('[data-js-crud-section-root]').forEach((sectionRootEl) => {
        const sectionKey = sectionRootEl.dataset.sectionKey || '';
        const sectionConfig = safePayload.sections?.[sectionKey] || null;
        const adapters = sectionKey === 'tasks'
            ? {
                handleAction: async ({ action, record }) => {
                    if (action.handlerKey !== 'completeTask' || !record?.complete_url) {
                        return;
                    }

                    const response = await fetch(record.complete_url, {
                        method: 'PATCH',
                        headers: {
                            Accept: 'application/json',
                            'X-CSRF-TOKEN': csrfToken,
                        },
                    });

                    const data = await response.json().catch(() => ({}));

                    if (!response.ok) {
                        window.dispatchEvent(new CustomEvent(inventoryCountToastEvent, {
                            detail: {
                                type: 'error',
                                message: data?.message || 'Unable to complete task.',
                            },
                        }));

                        return;
                    }

                    window.location.reload();
                },
            }
            : {};

        mountCrudSection(sectionRootEl, {
            section: sectionConfig,
            adapters,
        });
    });

    Alpine.data('inventoryCountShow', () => ({
        csrf: csrfToken,
        toast: { show: false, type: 'success', message: '' },
        count: safePayload.count || {},

        init() {
            window.addEventListener(inventoryCountToastEvent, (event) => {
                const detail = event?.detail || {};

                this.showToast(detail.type || 'error', detail.message || 'Unable to update task.');
            });
        },

        showToast(type, message) {
            this.toast.type = type;
            this.toast.message = message;
            this.toast.show = true;

            window.clearTimeout(this._toastTimeoutId);
            this._toastTimeoutId = window.setTimeout(() => {
                this.toast.show = false;
            }, 2500);
        },

        async submitToWorkflow() {
            if (!this.count.submit_url) {
                this.showToast('error', 'Unable to submit count.');
                return;
            }

            const response = await fetch(this.count.submit_url, {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': this.csrf,
                },
            });

            const data = await response.json().catch(() => ({}));

            if (!response.ok) {
                this.showToast('error', data.message || 'Unable to submit count.');
                return;
            }

            window.location.reload();
        },

        async moveToPreviousWorkflowStage() {
            if (!this.count.previous_url) {
                this.showToast('error', 'Unable to move count to the previous stage.');
                return;
            }

            const response = await fetch(this.count.previous_url, {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': this.csrf,
                },
            });

            const data = await response.json().catch(() => ({}));

            if (!response.ok) {
                this.showToast('error', data.message || 'Unable to move count to the previous stage.');
                return;
            }

            window.location.reload();
        },

        async advanceWorkflow() {
            if (!this.count.advance_url) {
                this.showToast('error', 'Unable to advance count.');
                return;
            }

            const response = await fetch(this.count.advance_url, {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': this.csrf,
                },
            });

            const data = await response.json().catch(() => ({}));

            if (!response.ok) {
                this.showToast('error', data.message || 'Unable to advance count.');
                return;
            }

            window.location.reload();
        },
    }));
}
