export function registerWorkflowActionButton(Alpine) {
    Alpine.data('workflowActionButton', (initialWorkflow = {}, csrfToken = '', options = {}) => ({
        workflow: initialWorkflow || {},
        csrfToken,
        mode: options.mode || 'dispatch',
        actionEventName: options.actionEventName || 'workflow-action-button',
        syncEventName: options.syncEventName || 'workflow-updated',
        syncStateKey: options.syncStateKey || 'workflow',
        loading: false,
        error: '',
        init() {
            const syncHandler = (event) => {
                const nextState = event?.detail?.[this.syncStateKey];

                if (nextState && typeof nextState === 'object') {
                    this.workflow = nextState;
                }
            };

            this.$el.addEventListener(this.syncEventName, syncHandler);
            window.addEventListener(this.syncEventName, syncHandler);
            document.addEventListener(this.syncEventName, syncHandler);
        },
        hasActions() {
            return this.menuActions().length > 0;
        },
        isCancelledState() {
            return String(this.workflow.status || this.workflow.status_label || this.workflow.display_label || '').toUpperCase() === 'CANCELLED';
        },
        isCompletedState() {
            return String(this.workflow.status || this.workflow.status_label || this.workflow.display_label || '').toUpperCase() === 'COMPLETED';
        },
        menuActions() {
            const normalize = (actions) => {
                if (!Array.isArray(actions) || actions.length === 0) {
                    return [];
                }

                return [...actions].sort((left, right) => {
                    const leftIsCancel = String(left?.type || left?.id || '').toLowerCase() === 'cancel';
                    const rightIsCancel = String(right?.type || right?.id || '').toLowerCase() === 'cancel';

                    if (leftIsCancel === rightIsCancel) {
                        return 0;
                    }

                    return leftIsCancel ? 1 : -1;
                });
            };

            if (Array.isArray(this.workflow.actions) && this.workflow.actions.length > 0) {
                return normalize(this.workflow.actions);
            }

            if (this.workflow.next_stage_action && typeof this.workflow.next_stage_action === 'object') {
                return [this.workflow.next_stage_action];
            }

            if (Array.isArray(this.workflow.header_menu?.options) && this.workflow.header_menu.options.length > 0) {
                return normalize(this.workflow.header_menu.options);
            }

            return [];
        },
        triggerLabel() {
            if (this.isCancelledState()) {
                return 'Cancelled';
            }

            if (this.isCompletedState()) {
                return 'COMPLETED';
            }

            return this.workflow.display_label
                || this.workflow.status_label
                || this.workflow.currentLabel
                || this.workflow.current_stage_label
                || this.workflow.current_stage?.status_complete_label
                || this.workflow.current_stage?.action_verb
                || this.workflow.status
                || this.workflow.header_menu?.currentLabel
                || '';
        },
        async submit(action) {
            if (!action || this.loading) {
                return;
            }

            const actionToSubmit = action.action && typeof action.action === 'object'
                ? action.action
                : action;

            if (actionToSubmit.requiresConfirmation && !window.confirm(actionToSubmit.description || 'Continue?')) {
                return;
            }

            if (this.mode === 'dispatch') {
                window.dispatchEvent(new CustomEvent(this.actionEventName, {
                    detail: actionToSubmit,
                }));
                return;
            }

            this.loading = true;
            this.error = '';

            try {
                const response = await fetch(actionToSubmit.endpoint, {
                    method: actionToSubmit.method || 'POST',
                    headers: {
                        Accept: 'application/json',
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': this.csrfToken,
                    },
                    body: JSON.stringify({ action: actionToSubmit.type }),
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
                const workflowProgressSteps = data.data?.workflowProgressSteps;
                const workflowUpdatedDetail = {
                    workflow: this.workflow,
                    purchaseOrder,
                    canReceive,
                };

                if (Array.isArray(workflowProgressSteps)) {
                    workflowUpdatedDetail.workflowProgressSteps = workflowProgressSteps;
                }

                this.$root.dispatchEvent(new CustomEvent('workflow-updated', {
                    bubbles: true,
                    detail: workflowUpdatedDetail,
                }));
                document.dispatchEvent(new CustomEvent('workflow-updated', {
                    detail: workflowUpdatedDetail,
                }));
            } catch (error) {
                this.error = 'Unable to update workflow.';
            } finally {
                this.loading = false;
            }
        },
    }));
}
