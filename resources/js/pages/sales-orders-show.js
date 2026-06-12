export function mount(rootEl, payload) {
    const Alpine = window.Alpine;
    const safePayload = payload || {};

    const escapeHtml = (value) => String(value ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');

    const emptyLineErrors = () => ({
        item_id: [],
        quantity: [],
    });

    const emptyLineForm = () => ({
        item_id: '',
        quantity: '1.000000',
    });

    Alpine.data('salesOrdersShow', () => ({
        order: safePayload.order || {},
        workflowProgressSteps: Array.isArray(safePayload.workflowProgressSteps)
            ? safePayload.workflowProgressSteps
            : [],
        sellableItems: safePayload.sellableItems || [],
        lineStoreUrlBase: safePayload.lineStoreUrlBase || '',
        indexUrl: safePayload.indexUrl || '/sales/orders',
        csrfToken: safePayload.csrfToken || '',
        lineForm: emptyLineForm(),
        lineErrors: emptyLineErrors(),
        lineGeneralError: '',
        lineEditQuantities: {},
        lineEditErrorsByLine: {},
        toast: {
            visible: false,
            message: '',
            type: 'success',
            timeoutId: null,
        },
        init() {
            this.syncLineState();
            window.addEventListener('sales-order-status-action-updated', (event) => {
                const nextOrder = event?.detail?.order && typeof event.detail.order === 'object'
                    ? event.detail.order
                    : {};
                const nextWorkflowProgressSteps = Array.isArray(event?.detail?.workflowProgressSteps)
                    ? event.detail.workflowProgressSteps
                    : null;

                this.order = {
                    ...this.order,
                    ...nextOrder,
                };

                if (nextWorkflowProgressSteps) {
                    this.workflowProgressSteps = nextWorkflowProgressSteps;
                }
            });
        },
        syncLineState() {
            (this.order.lines || []).forEach((line) => {
                this.lineEditQuantities[line.id] = line.quantity;

                if (!this.lineEditErrorsByLine[line.id]) {
                    this.lineEditErrorsByLine[line.id] = emptyLineErrors();
                }
            });
        },
        normalizeLineErrors(errors) {
            if (!errors || typeof errors !== 'object') {
                return emptyLineErrors();
            }

            return {
                ...emptyLineErrors(),
                item_id: Array.isArray(errors.item_id) ? errors.item_id : [],
                quantity: Array.isArray(errors.quantity) ? errors.quantity : [],
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
            }, 1500);
        },
        canManageOrderLines() {
            return !!this.order?.can_manage_lines;
        },
        canChangeStatus(order) {
            return Array.isArray(order?.available_status_transitions) && order.available_status_transitions.length > 0;
        },
        performHeaderWorkflowAction(action = null) {
            const status = typeof action === 'string'
                ? action
                : String(action?.type || action?.status || '').trim();

            if (!status) {
                return;
            }

            this.submitStatus(status);
        },
        formatLineMoney(amount, currencyCode) {
            return `${currencyCode} ${amount}`;
        },
        applyOrderLifecycleUpdate(data) {
            this.order = {
                ...this.order,
                status: data.status,
                can_edit: data.can_edit,
                can_manage_lines: data.can_manage_lines,
                available_status_transitions: data.available_status_transitions || [],
                current_stage_tasks: data.current_stage_tasks || [],
                display_label: data.workflow?.display_label || data.display_label || this.order.display_label,
                status_label: data.workflow?.status_label || data.status_label || this.order.status_label,
                currentLabel: data.workflow?.currentLabel || data.currentLabel || this.order.currentLabel,
            };

            if (Array.isArray(data.workflowProgressSteps)) {
                this.workflowProgressSteps = data.workflowProgressSteps;
            }

            if (data.workflow) {
                window.dispatchEvent(new CustomEvent('sales-order-status-action-updated', {
                    detail: {
                        order: this.order,
                        workflow: data.workflow,
                        workflowProgressSteps: Array.isArray(data.workflowProgressSteps)
                            ? data.workflowProgressSteps
                            : null,
                    },
                }));
            }
        },
        workflowProgressHtml() {
            let steps = Array.isArray(this.workflowProgressSteps)
                ? this.workflowProgressSteps
                : [];

            steps = steps
                .map((step, index) => ({
                    label: String(step?.label || ''),
                    status: ['completed', 'current', 'upcoming'].includes(step?.status)
                        ? step.status
                        : 'upcoming',
                    url: step?.url || null,
                    current: Boolean(step?.current || step?.status === 'current'),
                    number: String(index + 1).padStart(2, '0'),
                }))
                .filter((step) => step.label !== '' && step.label !== 'DRAFT')
                .map((step, index) => ({
                    ...step,
                    number: String(index + 1).padStart(2, '0'),
                }));

            if (
                steps.length > 0
                && !steps.some((step) => step.current)
                && !steps.some((step) => step.status === 'completed')
            ) {
                steps = steps.map((step, index) => ({
                    ...step,
                    status: index === 0 ? 'current' : step.status,
                    current: index === 0,
                }));
            }

            if (steps.length === 0) {
                return '';
            }

            let activeMobileStep = steps.findIndex((step) => step.current || step.status === 'current');

            if (activeMobileStep < 0) {
                activeMobileStep = steps.findIndex((step) => step.status === 'upcoming');
            }

            if (activeMobileStep < 0) {
                activeMobileStep = Math.max(0, steps.length - 1);
            }

            return `
                <nav class="w-full" aria-label="Progress" x-data="{ activeWorkflowStep: ${activeMobileStep} }" x-cloak>
                    <div class="flex overflow-hidden rounded-md border border-gray-300 bg-white md:hidden" role="tablist" aria-label="Workflow stages" data-workflow-progress-mobile-tabs>
                        ${steps.map((step, index) => this.workflowProgressMobileStepHtml(step, index, index === steps.length - 1)).join('')}
                    </div>
                    <ol role="list" class="hidden divide-y divide-gray-300 rounded-md border border-gray-300 bg-white md:flex md:divide-y-0">
                        ${steps.map((step, index) => this.workflowProgressStepHtml(step, index === steps.length - 1)).join('')}
                    </ol>
                </nav>
            `;
        },
        workflowProgressMobileStepHtml(step, index, isLast) {
            const label = escapeHtml(step.label);
            const isCompleted = step.status === 'completed';
            const isCurrent = step.status === 'current' || step.current;
            let circleClass = 'border-gray-300 bg-white text-gray-500';

            if (isCompleted) {
                circleClass = 'border-indigo-600 bg-indigo-600 text-white';
            } else if (isCurrent) {
                circleClass = 'border-indigo-600 bg-white text-indigo-600';
            }

            const labelClass = isCurrent ? 'text-indigo-600' : 'text-gray-900';
            const circleContent = isCompleted
                ? `
                    <svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true" class="size-5 text-white">
                        <path fill-rule="evenodd" clip-rule="evenodd" d="M19.916 4.626a.75.75 0 0 1 .208 1.04l-9 13.5a.75.75 0 0 1-1.154.114l-6-6a.75.75 0 0 1 1.06-1.06l5.353 5.353 8.493-12.74a.75.75 0 0 1 1.04-.207Z"></path>
                    </svg>
                `
                : `<span class="text-sm font-semibold">${escapeHtml(step.number)}</span>`;
            const separator = isLast ? '' : `
                <span aria-hidden="true" class="pointer-events-none absolute right-0 top-0 h-full w-5 md:hidden">
                    <svg viewBox="0 0 22 80" fill="none" preserveAspectRatio="none" class="size-full text-gray-300">
                        <path d="M0 -2L20 40L0 82" stroke="currentcolor" vector-effect="non-scaling-stroke" stroke-linejoin="round"></path>
                    </svg>
                </span>
            `;

            return `
                <button
                    type="button"
                    role="tab"
                    class="relative min-h-16 overflow-hidden bg-white py-2 pl-3 pr-6 transition-[flex-basis,flex-grow] duration-300 ease-out will-change-[flex-basis]"
                    :class="activeWorkflowStep === ${index} ? 'basis-0 grow' : 'basis-16 grow-0'"
                    :aria-selected="activeWorkflowStep === ${index} ? 'true' : 'false'"
                    x-on:click="activeWorkflowStep = ${index}"
                >
                    <span class="flex h-full items-center" :class="activeWorkflowStep === ${index} ? 'justify-start gap-3' : 'justify-center'">
                        <span class="flex size-9 shrink-0 items-center justify-center rounded-full border-2 ${circleClass}">
                            ${circleContent}
                        </span>
                        <span
                            class="min-w-0 truncate text-left text-sm font-medium transition-[max-width,opacity,transform] duration-300 ease-out ${labelClass}"
                            :class="activeWorkflowStep === ${index} ? 'max-w-48 translate-x-0 opacity-100' : 'max-w-0 -translate-x-1 opacity-0'"
                        >${label}</span>
                    </span>
                    ${separator}
                </button>
            `;
        },
        workflowProgressStepHtml(step, isLast) {
            const label = escapeHtml(step.label);
            const separator = isLast ? '' : `
                <div aria-hidden="true" class="absolute right-0 top-0 hidden h-full w-5 md:block">
                    <svg viewBox="0 0 22 80" fill="none" preserveAspectRatio="none" class="size-full text-gray-300">
                        <path d="M0 -2L20 40L0 82" stroke="currentcolor" vector-effect="non-scaling-stroke" stroke-linejoin="round"></path>
                    </svg>
                </div>
            `;

            if (step.status === 'completed') {
                return `
                    <li class="relative md:flex md:flex-1">
                        <span class="group flex w-full items-center">
                            <span class="flex items-center px-4 py-3 text-sm font-medium sm:px-6">
                                <span class="flex size-9 shrink-0 items-center justify-center rounded-full bg-indigo-600 group-hover:bg-indigo-700">
                                    <svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true" class="size-5 text-white">
                                        <path fill-rule="evenodd" clip-rule="evenodd" d="M19.916 4.626a.75.75 0 0 1 .208 1.04l-9 13.5a.75.75 0 0 1-1.154.114l-6-6a.75.75 0 0 1 1.06-1.06l5.353 5.353 8.493-12.74a.75.75 0 0 1 1.04-.207Z"></path>
                                    </svg>
                                </span>
                                <span class="ml-4 text-sm font-medium text-gray-900">${label}</span>
                            </span>
                        </span>
                        ${separator}
                    </li>
                `;
            }

            if (step.status === 'current') {
                return `
                    <li class="relative md:flex md:flex-1">
                        <span class="flex w-full items-center px-4 py-3 text-sm font-medium sm:px-6" aria-current="step">
                            <span class="flex size-9 shrink-0 items-center justify-center rounded-full border-2 border-indigo-600">
                                <span class="text-sm font-semibold text-indigo-600">${escapeHtml(step.number)}</span>
                            </span>
                            <span class="ml-4 text-sm font-medium text-indigo-600">${label}</span>
                        </span>
                        ${separator}
                    </li>
                `;
            }

            return `
                <li class="relative md:flex md:flex-1">
                    <span class="group flex w-full items-center">
                        <span class="flex items-center px-4 py-3 text-sm font-medium sm:px-6">
                            <span class="flex size-9 shrink-0 items-center justify-center rounded-full border-2 border-gray-300 group-hover:border-gray-400">
                                <span class="text-sm font-semibold text-gray-500 group-hover:text-gray-900">${escapeHtml(step.number)}</span>
                            </span>
                            <span class="ml-4 text-sm font-medium text-gray-500 group-hover:text-gray-900">${label}</span>
                        </span>
                    </span>
                    ${separator}
                </li>
            `;
        },
        async submitStatus(status) {
            if (!this.order?.status_update_url) {
                return;
            }

            const response = await fetch(this.order.status_update_url, {
                method: 'PATCH',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': this.csrfToken,
                },
                body: JSON.stringify({ status }),
            });

            if (response.status === 422) {
                const data = await response.json();
                this.showToast('error', data.message || 'Unable to update status.');
                return;
            }

            if (!response.ok) {
                this.showToast('error', 'Unable to update status.');
                return;
            }

            const data = await response.json();
            this.applyOrderLifecycleUpdate({
                ...(data.data || {}),
                workflow: data.workflow || null,
                workflowProgressSteps: data.data?.workflowProgressSteps || null,
            });
            this.showToast('success', 'Status updated.');
        },
        async completeTask(task) {
            if (!task?.complete_url || !task.can_complete) {
                return;
            }

            const response = await fetch(task.complete_url, {
                method: 'PATCH',
                headers: {
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': this.csrfToken,
                },
            });

            if (!response.ok) {
                this.showToast('error', 'Unable to complete task.');
                return;
            }

            const data = await response.json();
            const tasks = Array.isArray(this.order.current_stage_tasks) ? [...this.order.current_stage_tasks] : [];
            const taskIndex = tasks.findIndex((entry) => entry.id === data.data?.id);

            if (taskIndex === -1) {
                tasks.push(data.data || {});
            } else {
                tasks.splice(taskIndex, 1, data.data || {});
            }

            this.order.current_stage_tasks = tasks;
            this.showToast('success', 'Task completed.');
        },
        async submitLine() {
            if (!this.canManageOrderLines()) {
                return;
            }

            this.lineErrors = emptyLineErrors();
            this.lineGeneralError = '';

            const response = await fetch(`${this.lineStoreUrlBase}/${this.order.id}/lines`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': this.csrfToken,
                },
                body: JSON.stringify({
                    item_id: this.lineForm.item_id === '' ? null : Number(this.lineForm.item_id),
                    quantity: this.lineForm.quantity,
                }),
            });

            if (response.status === 422) {
                const data = await response.json();
                this.lineErrors = this.normalizeLineErrors(data.errors);
                this.lineGeneralError = data.message || 'Validation failed.';
                return;
            }

            if (!response.ok) {
                this.lineGeneralError = 'Unable to add line.';
                this.showToast('error', this.lineGeneralError);
                return;
            }

            const data = await response.json();
            this.order = data.data.order;
            this.lineForm = emptyLineForm();
            this.lineErrors = emptyLineErrors();
            this.lineGeneralError = '';
            this.syncLineState();
            this.showToast('success', 'Line added.');
        },
        async saveLineQuantity(line) {
            if (!this.canManageOrderLines()) {
                return;
            }

            this.lineEditErrorsByLine[line.id] = emptyLineErrors();

            const response = await fetch(`${this.lineStoreUrlBase}/${this.order.id}/lines/${line.id}`, {
                method: 'PATCH',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': this.csrfToken,
                },
                body: JSON.stringify({
                    quantity: this.lineEditQuantities[line.id] || line.quantity,
                }),
            });

            if (response.status === 422) {
                const data = await response.json();
                this.lineEditErrorsByLine[line.id] = this.normalizeLineErrors(data.errors);
                this.showToast('error', data.message || 'Unable to update line quantity.');
                return;
            }

            if (!response.ok) {
                this.showToast('error', 'Unable to update line quantity.');
                return;
            }

            const data = await response.json();
            this.order = data.data.order;
            this.syncLineState();
            this.showToast('success', 'Line quantity updated.');
        },
        async deleteLine(line) {
            if (!this.canManageOrderLines()) {
                return;
            }

            const response = await fetch(`${this.lineStoreUrlBase}/${this.order.id}/lines/${line.id}`, {
                method: 'DELETE',
                headers: {
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': this.csrfToken,
                },
            });

            if (response.status === 422) {
                const data = await response.json();
                this.showToast('error', data.message || 'Unable to remove line.');
                return;
            }

            if (!response.ok) {
                this.showToast('error', 'Unable to remove line.');
                return;
            }

            const data = await response.json();
            this.order = data.data.order;
            this.syncLineState();
            this.showToast('success', 'Line removed.');
        },
    }));
}
