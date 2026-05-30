export function registerWorkflowActionButton(Alpine) {
    Alpine.data('workflowActionButton', (initialWorkflow = {}, csrfToken = '') => ({
        workflow: initialWorkflow || {},
        csrfToken,
        loading: false,
        error: '',
        init() {
            this.$el.addEventListener('workflow-updated', (event) => {
                if (event.detail?.workflow) {
                    this.workflow = event.detail.workflow;
                }
            });

            document.addEventListener('workflow-updated', (event) => {
                if (event.detail?.workflow) {
                    this.workflow = event.detail.workflow;
                }
            });
        },
        hasActions() {
            return Array.isArray(this.workflow.actions) && this.workflow.actions.length > 0;
        },
        async submit(action) {
            if (!action || this.loading) {
                return;
            }

            if (['receive', 'short_close'].includes(action.type)) {
                window.dispatchEvent(new CustomEvent('purchase-order-status-action', {
                    detail: {
                        ...action,
                        action: action.type,
                    },
                }));
                return;
            }

            if (action.requiresConfirmation && !window.confirm(action.description || 'Continue?')) {
                return;
            }

            this.loading = true;
            this.error = '';

            try {
                const response = await fetch(action.endpoint, {
                    method: action.method || 'POST',
                    headers: {
                        Accept: 'application/json',
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': this.csrfToken,
                    },
                    body: JSON.stringify({ action: action.type }),
                });

                const data = await response.json().catch(() => ({}));

                if (!response.ok) {
                    this.error = data.message || 'Unable to update workflow.';
                    return;
                }

                this.workflow = data.data?.workflow || this.workflow;
                const purchaseOrder = data.data?.purchase_order || {};
                const canReceive = Object.prototype.hasOwnProperty.call(purchaseOrder, 'can_receive')
                    ? purchaseOrder.can_receive
                    : data.data?.can_receive;

                this.$root.dispatchEvent(new CustomEvent('workflow-updated', {
                    bubbles: true,
                    detail: {
                        workflow: this.workflow,
                        purchaseOrder,
                        canReceive,
                    },
                }));
                document.dispatchEvent(new CustomEvent('workflow-updated', {
                    detail: {
                        workflow: this.workflow,
                        purchaseOrder,
                        canReceive,
                    },
                }));
            } catch (error) {
                this.error = 'Unable to update workflow.';
            } finally {
                this.loading = false;
            }
        },
    }));
}
