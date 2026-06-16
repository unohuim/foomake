import Alpine from 'alpinejs';

const asRecord = (value) => (value && typeof value === 'object' && !Array.isArray(value) ? value : {});
const asArray = (value) => (Array.isArray(value) ? value : []);
const SCALE = 6;
const SCALE_FACTOR = 10n ** 6n;
const workflowActionLoadingEvent = 'workflow-action-button-loading';

const escapeHtml = (value) => String(value ?? '')
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#039;');
const setWorkflowActionLoading = (loading, error = '') => {
    window.dispatchEvent(new CustomEvent(workflowActionLoadingEvent, {
        detail: {
            loading,
            error,
        },
    }));
};

const normalizePrecision = (value, fallback = SCALE) => {
    const precision = Number.parseInt(String(value ?? fallback), 10);

    if (Number.isNaN(precision)) {
        return fallback;
    }

    return Math.max(0, Math.min(SCALE, precision));
};

const compactQuantityDisplay = (value) => {
    const normalized = String(value ?? '').trim();

    if (normalized === '' || !/^\d+(?:\.\d+)?$/.test(normalized)) {
        return '';
    }

    const trimmed = normalized.replace(/(\.\d*?[1-9])0+$/u, '$1').replace(/\.0+$/u, '');

    return trimmed === '' ? '0' : trimmed;
};

const canonicalizeScaleSix = (value) => {
    const normalized = String(value ?? '').trim();

    if (!/^\d+(?:\.\d+)?$/.test(normalized)) {
        return null;
    }

    const [wholePart, decimalPart = ''] = normalized.split('.', 2);

    return `${wholePart}.${decimalPart.padEnd(SCALE, '0').slice(0, SCALE)}`;
};

const scaledIntegerFromCanonical = (value) => {
    const canonical = canonicalizeScaleSix(value);

    if (canonical === null) {
        return null;
    }

    return BigInt(canonical.replace('.', ''));
};

const multiplyCanonicalQuantities = (left, right) => {
    const leftScaled = scaledIntegerFromCanonical(left);
    const rightScaled = scaledIntegerFromCanonical(right);

    if (leftScaled === null || rightScaled === null) {
        return null;
    }

    const product = (leftScaled * rightScaled) / SCALE_FACTOR;
    const sign = product < 0n ? '-' : '';
    const absolute = (product < 0n ? -product : product).toString().padStart(SCALE + 1, '0');
    const splitAt = absolute.length - SCALE;

    return `${sign}${absolute.slice(0, splitAt)}.${absolute.slice(splitAt)}`;
};

const formatQuantityForPrecision = (value, precision) => {
    const scaled = scaledIntegerFromCanonical(value);

    if (scaled === null) {
        return '';
    }

    const normalizedPrecision = normalizePrecision(precision, SCALE);
    const reductionPower = SCALE - normalizedPrecision;
    const factor = 10n ** BigInt(reductionPower);
    let rounded = scaled / factor;

    if ((scaled % factor) * 2n >= factor) {
        rounded += 1n;
    }

    if (normalizedPrecision === 0) {
        return rounded.toString();
    }

    const absolute = rounded.toString().padStart(normalizedPrecision + 1, '0');
    const splitAt = absolute.length - normalizedPrecision;

    return `${absolute.slice(0, splitAt)}.${absolute.slice(splitAt)}`;
};

const isMakeEndpoint = (endpoint) => {
    const candidate = String(endpoint || '').trim();

    if (!candidate) {
        return false;
    }

    try {
        const parsed = new URL(candidate, window.location.origin);
        return /\/make\/?$/.test(parsed.pathname);
    } catch (error) {
        return /\/make\/?$/.test(candidate);
    }
};

export function mount(rootEl, payload) {
    const safePayload = payload || {};
    const workflowPayload = asRecord(safePayload.workflow);
    const ingredientsPayload = asRecord(safePayload.ingredients);

    Alpine.data('makeOrderHeaderState', (payloadId) => ({
        makeOrder: {},
        action: null,
        init() {
            const payloadEl = document.getElementById(payloadId);
            let headerPayload = {};

            if (payloadEl) {
                try {
                    headerPayload = JSON.parse(payloadEl.textContent || '{}');
                } catch (error) {
                    headerPayload = {};
                }
            }

            this.makeOrder = asRecord(headerPayload.makeOrder);
            this.action = asRecord(headerPayload.workflow?.actions?.[0] || headerPayload.workflow?.next_stage_action);

            window.addEventListener('make-order-header-make-order-updated', (event) => {
                this.makeOrder = {
                    ...this.makeOrder,
                    ...asRecord(event.detail?.makeOrder),
                };
            });

            window.addEventListener('make-order-header-action-updated', (event) => {
                this.action = asRecord(
                    event.detail?.workflow?.actions?.[0]
                    || event.detail?.workflow?.next_stage_action
                    || event.detail?.action
                );
            });
        },
    }));

    Alpine.data('manufacturingMakeOrdersShow', () => ({
        makeOrder: asRecord(safePayload.makeOrder),
        workflowProgressSteps: Array.isArray(safePayload.workflowProgressSteps)
            ? safePayload.workflowProgressSteps
            : [],
        workflow: {
            default_open: Boolean(workflowPayload.default_open),
            transition_url: workflowPayload.transition_url || '',
            can_move_stage: Boolean(workflowPayload.can_move_stage),
            due_date_update_url: workflowPayload.due_date_update_url || '',
            can_edit_due_date: Boolean(workflowPayload.can_edit_due_date),
            assignment_update_url: workflowPayload.assignment_update_url || '',
            can_edit_assignment: Boolean(workflowPayload.can_edit_assignment),
            current_stage: asRecord(workflowPayload.current_stage),
            current_stage_label: workflowPayload.current_stage_label || '',
            actions: asArray(workflowPayload.actions),
            next_stage_action: asRecord(workflowPayload.next_stage_action),
            available_stages: asArray(workflowPayload.available_stages),
            assignee_options: asArray(workflowPayload.assignee_options),
            due_date: workflowPayload.due_date || '',
            made_by_user_id: workflowPayload.made_by_user_id || null,
            owner_user_name: workflowPayload.owner_user_name || '',
            tasked_by_user_id: workflowPayload.tasked_by_user_id || null,
            tasked_by_user_name: workflowPayload.tasked_by_user_name || '',
            current_stage_tasks: asArray(workflowPayload.current_stage_tasks),
        },
        ingredients: {
            can_edit: Boolean(ingredientsPayload.can_edit),
            item_options: asArray(ingredientsPayload.item_options),
            lines: asArray(ingredientsPayload.lines),
            store_url: ingredientsPayload.store_url || '',
            update_url_template: ingredientsPayload.update_url_template || '',
            remove_url_template: ingredientsPayload.remove_url_template || '',
        },
        csrfToken: safePayload.csrf_token || '',
        selectedIngredientItemId: '',
        selectedWorkflowStageId: '',
        ingredientsSaving: false,
        workflowDueDateSaving: false,
        workflowDueDateAutosaveReady: false,
        lastSavedWorkflowDueDate: workflowPayload.due_date || '',
        workflowAssignmentSaving: false,
        workflowOwnerAutosaveReady: false,
        lastSavedWorkflowOwnerId: workflowPayload.made_by_user_id === null || workflowPayload.made_by_user_id === undefined
            ? ''
            : String(workflowPayload.made_by_user_id),
        workflowTransitionSaving: false,
        workflowTaskSavingIds: [],
        makeOrderDetailSaving: false,
        ingredientSavedState: {},
        toast: {
            visible: false,
            message: '',
            type: 'success',
            timeoutId: null,
        },
        hydrateMakeOrderResponse(data) {
            if (data && typeof data === 'object') {
                this.makeOrder = {
                    ...this.makeOrder,
                    ...asRecord(data),
                };
                this.syncHeaderMakeOrder();
            }
        },
        syncHeaderMakeOrder() {
            window.dispatchEvent(new CustomEvent('make-order-header-make-order-updated', {
                detail: {
                    makeOrder: this.makeOrder,
                },
            }));
        },
        refreshMaterialsIndex() {
            window.dispatchEvent(new CustomEvent('materials-index-refresh'));
        },
        outputUomDisplayPrecision() {
            return normalizePrecision(this.makeOrder.output_uom_display_precision, SCALE);
        },
        recalculateExpectedOutputQtyFromRuns() {
            const canonicalRuns = canonicalizeScaleSix(this.makeOrder.runs_text);
            const perRunOutputQty = canonicalizeScaleSix(this.makeOrder.recipe_version_output_qty);

            if (canonicalRuns === null || perRunOutputQty === null) {
                this.makeOrder.expected_output_qty_text = '';
                return;
            }

            const expectedOutputQty = multiplyCanonicalQuantities(canonicalRuns, perRunOutputQty);

            if (expectedOutputQty === null) {
                this.makeOrder.expected_output_qty_text = '';
                return;
            }

            this.makeOrder.expected_output_qty_text = formatQuantityForPrecision(
                expectedOutputQty,
                this.outputUomDisplayPrecision()
            );
            this.recalculateIngredientQuantitiesFromRuns(canonicalRuns);
            this.refreshMaterialsIndex();
        },
        recalculateIngredientQuantitiesFromRuns(canonicalRuns) {
            if (canonicalRuns === null) {
                return;
            }

            this.ingredients.lines = this.ingredients.lines.map((line) => {
                const recipeQuantity = canonicalizeScaleSix(line.recipe_quantity);

                if (line.line_type !== 'recipe' || recipeQuantity === null) {
                    return line;
                }

                const nextQuantity = multiplyCanonicalQuantities(recipeQuantity, canonicalRuns);

                if (nextQuantity === null) {
                    return line;
                }

                const displayPrecision = normalizePrecision(line.quantity_display_precision, SCALE);
                const nextDisplayQuantity = formatQuantityForPrecision(nextQuantity, displayPrecision);

                return {
                    ...line,
                    quantity: nextQuantity,
                    quantity_input: nextDisplayQuantity,
                    quantity_display: nextDisplayQuantity,
                };
            });
        },
        hydrateWorkflowResponse(data) {
            if (!data || typeof data !== 'object') {
                return;
            }

            this.workflow = {
                ...this.workflow,
                ...asRecord(data),
                current_stage: asRecord(data.current_stage),
                current_stage_label: data.current_stage_label || '',
                actions: asArray(data.actions),
                next_stage_action: asRecord(data.next_stage_action),
                available_stages: asArray(data.available_stages),
                assignee_options: asArray(data.assignee_options),
                current_stage_tasks: asArray(data.current_stage_tasks),
            };
            this.lastSavedWorkflowDueDate = this.workflow.due_date || '';
            this.lastSavedWorkflowOwnerId = this.normalizedWorkflowOwnerId(this.workflow.made_by_user_id);
            this.syncHeaderWorkflowAction();
        },
        hydrateIngredientsResponse(data) {
            if (!data || typeof data !== 'object') {
                return;
            }

            this.ingredients = {
                ...this.ingredients,
                ...asRecord(data),
                item_options: asArray(data.item_options),
                lines: asArray(data.lines),
                store_url: data.store_url || '',
                update_url_template: data.update_url_template || '',
                remove_url_template: data.remove_url_template || '',
                can_edit: Boolean(data.can_edit),
            };
        },
        syncHeaderWorkflowAction() {
            window.dispatchEvent(new CustomEvent('make-order-header-action-updated', {
                detail: {
                    workflow: this.workflow,
                    action: this.workflow.actions?.[0] && this.workflow.actions[0].label
                        ? this.workflow.actions[0]
                        : (this.workflow.next_stage_action && this.workflow.next_stage_action.label
                            ? this.workflow.next_stage_action
                            : null),
                },
            }));
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

            if (step.status === 'current' || step.current) {
                return `
                    <li class="relative md:flex md:flex-1">
                        <span aria-current="step" class="flex w-full items-center px-4 py-3 text-sm font-medium sm:px-6">
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
        init() {
            this.$watch('workflow.made_by_user_id', async (value) => {
                if (!this.workflowOwnerAutosaveReady) {
                    return;
                }

                const normalizedValue = this.normalizedWorkflowOwnerId(value);

                if (normalizedValue === this.lastSavedWorkflowOwnerId || this.workflowAssignmentSaving) {
                    return;
                }

                await this.saveWorkflowAssignment();
            });

            this.$nextTick(() => {
                this.lastSavedWorkflowDueDate = this.workflow.due_date || '';
                this.workflowDueDateAutosaveReady = true;
                this.lastSavedWorkflowOwnerId = this.normalizedWorkflowOwnerId(this.workflow.made_by_user_id);
                this.workflowOwnerAutosaveReady = true;
            });
        },
        normalizedWorkflowOwnerId(value) {
            return value === '' || value === null || value === undefined
                ? ''
                : String(value);
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
        goTo(url) {
            if (!url) {
                return;
            }

            window.location.assign(url);
        },
        async moveWorkflowStage() {
            if (!this.workflow.can_move_stage || !this.workflow.transition_url || !this.selectedWorkflowStageId) {
                setWorkflowActionLoading(false);
                return;
            }

            this.workflowTransitionSaving = true;

            try {
                const response = await fetch(this.workflow.transition_url, {
                    method: 'PATCH',
                    headers: {
                        'Content-Type': 'application/json',
                        Accept: 'application/json',
                        'X-CSRF-TOKEN': this.csrfToken,
                    },
                    body: JSON.stringify({
                        workflow_stage_id: Number(this.selectedWorkflowStageId),
                    }),
                });

                if (!response.ok) {
                    this.showToast('error', 'Unable to move workflow stage.');
                    return;
                }

                const data = await response.json();
                this.hydrateMakeOrderResponse(data.data);
                this.hydrateWorkflowResponse(data.workflow);
                this.hydrateIngredientsResponse(data.ingredients);
                this.refreshMaterialsIndex();
                if (Array.isArray(data.workflowProgressSteps)) {
                    this.workflowProgressSteps = data.workflowProgressSteps;
                }
                this.selectedWorkflowStageId = '';
                this.showToast('success', 'Workflow stage updated.');
            } catch (error) {
                this.showToast('error', 'Unable to move workflow stage.');
            } finally {
                this.workflowTransitionSaving = false;
                setWorkflowActionLoading(false);
            }
        },
        async moveWorkflowStageTo(workflowStageId) {
            if (!workflowStageId) {
                return;
            }

            this.selectedWorkflowStageId = String(workflowStageId);
            await this.moveWorkflowStage();
        },
        async performHeaderWorkflowAction(action = null) {
            const actionRecord = asRecord(
                action?.label
                    ? action
                    : action?.action && typeof action.action === 'object'
                        ? action.action
                    : this.workflow.actions?.[0] || this.workflow.next_stage_action
            );

            if (!actionRecord.id || this.workflowTransitionSaving) {
                setWorkflowActionLoading(false);
                return;
            }

            if (String(actionRecord.method || '').toUpperCase() === 'DELETE' || actionRecord.type === 'cancel') {
                await this.cancelCurrentOrder(actionRecord);
                return;
            }

            if (isMakeEndpoint(actionRecord.endpoint)) {
                await this.makeCurrentOrder(actionRecord);
                return;
            }

            await this.moveWorkflowStageTo(actionRecord.id);
        },
        async cancelCurrentOrder(action) {
            const endpoint = action.endpoint || '';

            if (!endpoint) {
                this.showToast('error', 'Unable to archive make order.');
                setWorkflowActionLoading(false);
                return;
            }

            this.workflowTransitionSaving = true;

            try {
                const response = await fetch(endpoint, {
                    method: 'DELETE',
                    headers: {
                        Accept: 'application/json',
                        'X-CSRF-TOKEN': this.csrfToken,
                    },
                });

                const data = await response.json().catch(() => ({}));

                if (!response.ok) {
                    this.showToast('error', data.message || 'Unable to archive make order.');
                    return;
                }

                this.hydrateMakeOrderResponse(data.data);
                this.hydrateWorkflowResponse(data.workflow);
                this.hydrateIngredientsResponse(data.ingredients);
                if (Array.isArray(data.workflowProgressSteps)) {
                    this.workflowProgressSteps = data.workflowProgressSteps;
                }
                this.showToast('success', data.message || 'Archived.');
            } catch (error) {
                this.showToast('error', 'Unable to archive make order.');
            } finally {
                this.workflowTransitionSaving = false;
                setWorkflowActionLoading(false);
            }
        },
        async makeCurrentOrder(action) {
            const endpoint = action.endpoint || '';

            if (!endpoint) {
                this.showToast('error', 'Unable to make order.');
                setWorkflowActionLoading(false);
                return;
            }

            this.workflowTransitionSaving = true;

            try {
                const response = await fetch(endpoint, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        Accept: 'application/json',
                        'X-CSRF-TOKEN': this.csrfToken,
                    },
                    body: JSON.stringify({
                        actual_output_qty: this.makeOrder.actual_output_qty_text || null,
                    }),
                });

                const data = await response.json().catch(() => ({}));

                if (!response.ok) {
                    this.showToast('error', data.message || 'Unable to make order.');
                    return;
                }

                this.hydrateMakeOrderResponse(data.data);
                this.hydrateWorkflowResponse(data.workflow);
                this.hydrateIngredientsResponse(data.ingredients);
                this.refreshMaterialsIndex();
                if (Array.isArray(data.workflowProgressSteps)) {
                    this.workflowProgressSteps = data.workflowProgressSteps;
                }
                this.showToast('success', 'Make order completed.');
            } catch (error) {
                this.showToast('error', 'Unable to make order.');
            } finally {
                this.workflowTransitionSaving = false;
                setWorkflowActionLoading(false);
            }
        },
        async saveWorkflowAssignment() {
            if (!this.workflow.can_edit_assignment || !this.workflow.assignment_update_url) {
                return;
            }

            const previousOwnerId = this.lastSavedWorkflowOwnerId;
            this.workflowAssignmentSaving = true;

            try {
            const response = await fetch(this.workflow.assignment_update_url, {
                    method: 'PATCH',
                    headers: {
                        'Content-Type': 'application/json',
                        Accept: 'application/json',
                        'X-CSRF-TOKEN': this.csrfToken,
                    },
                    body: JSON.stringify({
                        made_by_user_id: this.workflow.made_by_user_id === '' || this.workflow.made_by_user_id === null
                            ? null
                            : Number(this.workflow.made_by_user_id),
                    }),
                });

                if (!response.ok) {
                    this.workflow.made_by_user_id = previousOwnerId;
                    this.showToast('error', 'Unable to save make order owner.');
                    return;
                }

                const data = await response.json();
                this.hydrateMakeOrderResponse(data.data);
                this.hydrateWorkflowResponse(data.workflow);
                this.showToast('success', 'Make order owner updated.');
            } catch (error) {
                this.workflow.made_by_user_id = previousOwnerId;
                this.showToast('error', 'Unable to save make order owner.');
            } finally {
                this.workflowAssignmentSaving = false;
            }
        },
        async saveMakeOrderDetailQuantity(field) {
            if (!this.makeOrder.details_update_url || this.makeOrderDetailSaving) {
                return;
            }

            this.makeOrderDetailSaving = true;

            try {
                const payload = { field };

                if (field === 'runs') {
                    payload.runs = this.makeOrder.runs_text;
                }

                if (field === 'actual_output_qty') {
                    payload.actual_output_qty = this.makeOrder.actual_output_qty_text === '' ? null : this.makeOrder.actual_output_qty_text;
                }

                const response = await fetch(this.makeOrder.details_update_url, {
                    method: 'PATCH',
                    headers: {
                        'Content-Type': 'application/json',
                        Accept: 'application/json',
                        'X-CSRF-TOKEN': this.csrfToken,
                    },
                    body: JSON.stringify(payload),
                });

                if (!response.ok) {
                    this.showToast('error', 'Unable to save make order details.');
                    return;
                }

                const data = await response.json();
                this.hydrateMakeOrderResponse(data.data);
                this.hydrateWorkflowResponse(data.workflow);
                this.hydrateIngredientsResponse(data.ingredients);
                this.showToast('success', 'Make order details updated.');
            } catch (error) {
                this.showToast('error', 'Unable to save make order details.');
            } finally {
                this.makeOrderDetailSaving = false;
            }
        },
        async saveWorkflowDueDate() {
            if (!this.workflowDueDateAutosaveReady || !this.workflow.can_edit_due_date || !this.workflow.due_date_update_url) {
                return;
            }

            const nextDueDate = this.workflow.due_date || '';

            if (nextDueDate === this.lastSavedWorkflowDueDate || this.workflowDueDateSaving) {
                return;
            }

            const previousDueDate = this.lastSavedWorkflowDueDate;
            this.workflowDueDateSaving = true;

            try {
                const response = await fetch(this.workflow.due_date_update_url, {
                    method: 'PATCH',
                    headers: {
                        'Content-Type': 'application/json',
                        Accept: 'application/json',
                        'X-CSRF-TOKEN': this.csrfToken,
                    },
                    body: JSON.stringify({
                        due_date: nextDueDate === '' ? null : nextDueDate,
                    }),
                });

                if (!response.ok) {
                    this.workflow.due_date = previousDueDate;
                    this.showToast('error', 'Unable to save due date.');
                    return;
                }

                const data = await response.json();
                this.hydrateMakeOrderResponse(data.data);
                this.hydrateWorkflowResponse(data.workflow);
                this.showToast('success', 'Due date updated.');
            } catch (error) {
                this.workflow.due_date = previousDueDate;
                this.showToast('error', 'Unable to save due date.');
            } finally {
                this.workflowDueDateSaving = false;
            }
        },
        async completeWorkflowTask(task) {
            if (!task?.can_complete || !task.complete_url || this.workflowTaskSavingIds.includes(task.id)) {
                return;
            }

            this.workflowTaskSavingIds.push(task.id);

            try {
                const response = await fetch(task.complete_url, {
                    method: 'PATCH',
                    headers: {
                        Accept: 'application/json',
                        'X-CSRF-TOKEN': this.csrfToken,
                    },
                });

                if (!response.ok) {
                    this.showToast('error', 'Unable to complete workflow task.');
                    return;
                }

                const data = await response.json();
                const updatedTask = asRecord(data.data);

                this.workflow.current_stage_tasks = this.workflow.current_stage_tasks.map((entry) => (
                    entry.id === task.id ? updatedTask : entry
                ));
                this.showToast('success', 'Workflow task completed.');
            } catch (error) {
                this.showToast('error', 'Unable to complete workflow task.');
            } finally {
                this.workflowTaskSavingIds = this.workflowTaskSavingIds.filter((id) => id !== task.id);
            }
        },
        async addIngredient() {
            if (!this.ingredients.can_edit || !this.ingredients.store_url || !this.selectedIngredientItemId) {
                return;
            }

            this.ingredientsSaving = true;

            try {
                const response = await fetch(this.ingredients.store_url, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        Accept: 'application/json',
                        'X-CSRF-TOKEN': this.csrfToken,
                    },
                    body: JSON.stringify({
                        item_id: Number(this.selectedIngredientItemId),
                        quantity: '1.000000',
                    }),
                });

                if (!response.ok) {
                    this.showToast('error', 'Unable to add ingredient.');
                    return;
                }

                const data = await response.json();
                this.ingredients.lines.push(data.data);
                this.selectedIngredientItemId = '';
                this.refreshMaterialsIndex();
                this.showToast('success', 'Ingredient added.');
            } catch (error) {
                this.showToast('error', 'Unable to add ingredient.');
            } finally {
                this.ingredientsSaving = false;
            }
        },
        async saveIngredientQuantity(line) {
            if (!this.ingredients.can_edit || !this.ingredients.update_url_template || !line?.id) {
                return;
            }

            this.ingredientSavedState[line.id] = 'saving';

            try {
                const response = await fetch(
                    this.ingredients.update_url_template.replace('__LINE__', encodeURIComponent(String(line.id))),
                    {
                        method: 'PATCH',
                        headers: {
                            'Content-Type': 'application/json',
                            Accept: 'application/json',
                            'X-CSRF-TOKEN': this.csrfToken,
                        },
                        body: JSON.stringify({
                            quantity: line.quantity_input,
                        }),
                    }
                );

                if (!response.ok) {
                    this.ingredientSavedState[line.id] = 'error';
                    this.showToast('error', 'Unable to save ingredient quantity.');
                    return;
                }

                const data = await response.json();
                const nextLine = asRecord(data.data);
                this.ingredients.lines = this.ingredients.lines.map((entry) => (entry.id === line.id ? nextLine : entry));
                this.refreshMaterialsIndex();
                this.ingredientSavedState[line.id] = 'saved';
                this.showToast('success', 'Ingredient saved.');

                setTimeout(() => {
                    if (this.ingredientSavedState[line.id] === 'saved') {
                        this.ingredientSavedState[line.id] = '';
                    }
                }, 1000);
            } catch (error) {
                this.ingredientSavedState[line.id] = 'error';
                this.showToast('error', 'Unable to save ingredient quantity.');
            }
        },
        async removeIngredient(line) {
            if (!this.ingredients.can_edit || !line?.remove_url) {
                return;
            }

            try {
                const response = await fetch(line.remove_url, {
                    method: 'DELETE',
                    headers: {
                        Accept: 'application/json',
                        'X-CSRF-TOKEN': this.csrfToken,
                    },
                });

                if (!response.ok) {
                    this.showToast('error', 'Unable to remove ingredient.');
                    return;
                }

                const data = await response.json();
                const deletedLineId = data?.deleted_line_id ?? line.id;
                const nextLines = asArray(data?.lines);

                this.ingredients.lines = nextLines.length > 0
                    ? nextLines
                    : this.ingredients.lines.filter((entry) => entry.id !== deletedLineId);
                this.refreshMaterialsIndex();
            } catch (error) {
                this.showToast('error', 'Unable to remove ingredient.');
            }
        },
    }));
}
