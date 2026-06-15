import Alpine from 'alpinejs';
import { mountCrudSection } from '../lib/js-crud-section';

const inventoryCountToastEvent = 'inventory-count-toast';
const workflowActionLoadingEvent = 'workflow-action-button-loading';
const asRecord = (value) => (value && typeof value === 'object' && !Array.isArray(value) ? value : {});
const asString = (value, fallback = '') => (typeof value === 'string' ? value : fallback);
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

export function mount(rootEl, payload) {
    const safePayload = payload || {};
    const csrfMeta = document.querySelector('meta[name=csrf-token]');
    const csrfToken = csrfMeta ? (csrfMeta.getAttribute('content') || '') : '';
    const sectionsPayload = asRecord(safePayload.sections);
    const countPayload = asRecord(safePayload.count);
    const workflowPayload = asRecord(safePayload.workflow);
    let showRequiredCountedQuantityCue = false;

    rootEl.querySelectorAll('[data-js-crud-section-root]').forEach((sectionRootEl) => {
        const sectionKey = sectionRootEl.dataset.sectionKey || '';
        const sectionConfig = sectionsPayload[sectionKey] || null;

        if (sectionKey === 'tasks') {
            return;
        }

        const adapters = sectionKey === 'countLines'
                ? {
                    normalizeRow: (record) => {
                        const safeRecord = asRecord(record);

                        return {
                            ...safeRecord,
                            counted_quantity_input: typeof safeRecord.counted_quantity_input === 'string'
                                ? safeRecord.counted_quantity_input
                                : (safeRecord.counted_quantity ?? ''),
                            _lastSavedCountedQuantityInput: typeof safeRecord.counted_quantity_input === 'string'
                                ? safeRecord.counted_quantity_input
                                : (safeRecord.counted_quantity ?? ''),
                            _countedQuantitySaved: false,
                        };
                    },
                    rightMetaItems: ({ record, section }) => {
                        const safeRecord = asRecord(record);
                        const safeSection = asRecord(section);
                        const rowLayout = asRecord(safeSection.rowLayout);
                        const rightMeta = Array.isArray(rowLayout.rightMeta) ? rowLayout.rightMeta : [];

                        if (safeRecord.can_edit_counted_quantity) {
                            return [{
                                key: 'counted_quantity',
                                label: 'QTY',
                                field: 'counted_quantity_input',
                                strong: true,
                                type: 'smart-number',
                                numberType: 'decimal',
                                precisionField: 'uom_display_precision',
                                handlerKey: 'updateCountedQuantity',
                                labelBare: true,
                                compactOnMobile: true,
                                icon: 'check-circle',
                                showSuccessIcon: Boolean(safeRecord._countedQuantitySaved),
                                requiredWhenBlank: showRequiredCountedQuantityCue,
                            }];
                        }

                        if (!safeRecord.shows_counted_quantity) {
                            return [];
                        }

                        return rightMeta.map((entry) => ({
                            ...entry,
                            text: safeRecord.counted_quantity_display ?? '—',
                        }));
                    },
                    handleAction: async ({ action, record, component }) => {
                        const safeAction = asRecord(action);
                        const safeRecord = asRecord(record);

                        if (safeAction.handlerKey !== 'removeCountLine' || !safeRecord.delete_url) {
                            return;
                        }

                        const response = await fetch(safeRecord.delete_url, {
                            method: 'DELETE',
                            headers: {
                                Accept: 'application/json',
                                'X-CSRF-TOKEN': csrfToken,
                            },
                        });

                        const data = await response.json().catch(() => ({}));
                        const responseData = asRecord(data);

                        if (!response.ok) {
                            window.dispatchEvent(new CustomEvent(inventoryCountToastEvent, {
                                detail: {
                                    type: 'error',
                                    message: responseData.message || 'Unable to remove material line.',
                                },
                            }));

                            return;
                        }

                        const deletedLineId = responseData.deleted_line_id ?? safeRecord.id;
                        const remainingLines = Array.isArray(responseData.lines)
                            ? responseData.lines.map((line) => component.normalizeRow(line))
                            : component.records.filter((line) => line.id !== deletedLineId);

                        component.records = remainingLines;
                        component.meta = {
                            ...component.meta,
                            total: remainingLines.length,
                        };

                        if (responseData.section) {
                            component.updateSectionConfig(responseData.section);
                        }
                    },
                    handleInlineMetaAction: async ({ meta, record, component }) => {
                        const safeMeta = asRecord(meta);
                        const safeRecord = asRecord(record);

                        if (safeMeta.handlerKey !== 'updateCountedQuantity' || !safeRecord.update_url) {
                            return;
                        }

                        if (safeRecord._countedQuantitySaving) {
                            return;
                        }

                        const nextValue = typeof safeRecord.counted_quantity_input === 'string'
                            ? safeRecord.counted_quantity_input
                            : '';
                        const previousValue = typeof safeRecord._lastSavedCountedQuantityInput === 'string'
                            ? safeRecord._lastSavedCountedQuantityInput
                            : '';

                        if (nextValue === previousValue) {
                            return;
                        }

                        safeRecord._countedQuantitySaving = true;

                        try {
                            const response = await fetch(safeRecord.update_url, {
                                method: 'PATCH',
                                headers: {
                                    Accept: 'application/json',
                                    'Content-Type': 'application/json',
                                    'X-CSRF-TOKEN': csrfToken,
                                },
                                body: JSON.stringify({
                                    counted_quantity: nextValue,
                                }),
                            });

                            const data = await response.json().catch(() => ({}));
                            const responseData = asRecord(data);

                            if (!response.ok) {
                                safeRecord.counted_quantity_input = previousValue;

                                window.dispatchEvent(new CustomEvent(inventoryCountToastEvent, {
                                    detail: {
                                        type: 'error',
                                        message: responseData.message || 'Unable to update counted quantity.',
                                    },
                                }));

                                return;
                            }

                            const updatedLine = component.normalizeRow(responseData.line || safeRecord);
                            updatedLine._countedQuantitySaved = true;

                            const saveFeedbackTimers = component._countedQuantitySaveTimers || {};
                            if (saveFeedbackTimers[updatedLine.id]) {
                                window.clearTimeout(saveFeedbackTimers[updatedLine.id]);
                            }

                            component.records = component.records.map((line) => (
                                line.id === updatedLine.id ? updatedLine : line
                            ));

                            component._countedQuantitySaveTimers = {
                                ...saveFeedbackTimers,
                                [updatedLine.id]: window.setTimeout(() => {
                                    component.records = component.records.map((line) => (
                                        line.id === updatedLine.id
                                            ? { ...line, _countedQuantitySaved: false }
                                            : line
                                    ));
                                }, 1000),
                            };
                        } catch (error) {
                            safeRecord.counted_quantity_input = previousValue;

                            window.dispatchEvent(new CustomEvent(inventoryCountToastEvent, {
                                detail: {
                                    type: 'error',
                                    message: 'Unable to update counted quantity.',
                                },
                            }));
                        } finally {
                            safeRecord._countedQuantitySaving = false;
                        }
                    },
                    handleAddRow: async ({ action, selectedValue, component, section }) => {
                        const safeAction = asRecord(action);
                        const safeSection = asRecord(section);
                        const endpoints = asRecord(safeSection.endpoints);

                        if (safeAction.handlerKey !== 'addSelectedCountLine' || !endpoints.create) {
                            return;
                        }

                        const response = await fetch(endpoints.create, {
                            method: 'POST',
                            headers: {
                                Accept: 'application/json',
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': csrfToken,
                            },
                            body: JSON.stringify({
                                item_id: selectedValue,
                            }),
                        });

                        const data = await response.json().catch(() => ({}));
                        const responseData = asRecord(data);

                        if (!response.ok) {
                            component.sectionError = responseData.message || 'Unable to add material line.';
                            return;
                        }

                        const line = component.normalizeRow(responseData.line || {});

                        component.records = [line, ...component.records];
                        component.meta = {
                            ...component.meta,
                            total: Number(component.meta.total || 0) + 1,
                        };
                        component.addRowValue = '';

                        if (responseData.section) {
                            component.updateSectionConfig(responseData.section);
                        }
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
        count: countPayload,
        workflow: workflowPayload,
        workflowProgressSteps: Array.isArray(safePayload.workflowProgressSteps) ? safePayload.workflowProgressSteps : [],
        currentStageTasks: Array.isArray(countPayload.current_stage_tasks) ? countPayload.current_stage_tasks : [],
        details: {
            counted_at_iso: asString(countPayload.counted_at_iso),
            assigned_to_user_id: countPayload.assigned_to_user_id === null || countPayload.assigned_to_user_id === undefined
                ? ''
                : String(countPayload.assigned_to_user_id),
            notes: asString(countPayload.notes),
            assignee_options: Array.isArray(countPayload.assignee_options) ? countPayload.assignee_options : [],
        },
        detailsCountedAtSaving: false,
        detailsAssignmentSaving: false,
        detailsNotesSaving: false,
        detailsAssignmentAutosaveReady: false,
        lastSavedDetailsCountedAtIso: asString(countPayload.counted_at_iso),
        lastSavedDetailsAssignedToUserId: countPayload.assigned_to_user_id === null || countPayload.assigned_to_user_id === undefined
            ? ''
            : String(countPayload.assigned_to_user_id),
        lastSavedDetailsNotes: asString(countPayload.notes),
        taskCompletingIds: [],

        hydrateCountResponse(data) {
            const responseData = asRecord(data);
            const countData = asRecord(responseData.count || responseData);
            const workflowData = asRecord(responseData.workflow);
            const sectionsData = asRecord(responseData.sections);

            if (!Object.keys(countData).length && !Object.keys(workflowData).length) {
                return;
            }

            this.count = {
                ...this.count,
                ...countData,
            };
            this.workflow = Object.keys(workflowData).length > 0
                ? {
                    ...this.workflow,
                    ...workflowData,
                }
                : this.workflow;
            this.workflowProgressSteps = Array.isArray(responseData.workflowProgressSteps)
                ? responseData.workflowProgressSteps
                : this.workflowProgressSteps;
            this.currentStageTasks = Array.isArray(countData.current_stage_tasks)
                ? this.sortTasks(countData.current_stage_tasks)
                : this.currentStageTasks;
            this.details.counted_at_iso = countData.counted_at_iso || '';
            this.details.assigned_to_user_id = this.normalizedAssignedToUserId(countData.assigned_to_user_id);
            this.details.notes = countData.notes || '';
            this.details.assignee_options = Array.isArray(countData.assignee_options) ? countData.assignee_options : [];
            this.lastSavedDetailsCountedAtIso = this.details.counted_at_iso;
            this.lastSavedDetailsAssignedToUserId = this.details.assigned_to_user_id;
            this.lastSavedDetailsNotes = this.details.notes;

            if (sectionsData.countLines) {
                void this.refreshCrudSection('countLines', sectionsData.countLines);
            }

            this.refreshHeaderStatusBadge();
            this.dispatchInventoryWorkflowSync();
        },

        init() {
            window.addEventListener(inventoryCountToastEvent, (event) => {
                const detail = asRecord(event ? event.detail : null);

                this.showToast(detail.type || 'error', detail.message || 'Unable to update task.');
            });

            this.$watch('details.assigned_to_user_id', async (value) => {
                if (!this.detailsAssignmentAutosaveReady) {
                    return;
                }

                const normalizedValue = this.normalizedAssignedToUserId(value);

                if (normalizedValue === this.lastSavedDetailsAssignedToUserId || this.detailsAssignmentSaving) {
                    return;
                }

                await this.saveDetails('assigned_to_user_id');
            });

            this.$nextTick(() => {
                this.lastSavedDetailsCountedAtIso = this.details.counted_at_iso || '';
                this.lastSavedDetailsAssignedToUserId = this.normalizedAssignedToUserId(this.details.assigned_to_user_id);
                this.lastSavedDetailsNotes = this.details.notes || '';
                this.detailsAssignmentAutosaveReady = true;
            });
        },

        normalizedAssignedToUserId(value) {
            return value === '' || value === null || value === undefined
                ? ''
                : String(value);
        },
        sortTasks(tasks) {
            return Array.isArray(tasks)
                ? [...tasks]
                    .map((task) => asRecord(task))
                    .sort((left, right) => {
                        const leftSortOrder = Number(left.sort_order ?? 0);
                        const rightSortOrder = Number(right.sort_order ?? 0);

                        if (leftSortOrder !== rightSortOrder) {
                            return leftSortOrder - rightSortOrder;
                        }

                        return Number(left.id ?? 0) - Number(right.id ?? 0);
                    })
                : [];
        },
        appendCreatedTask(event) {
            const task = asRecord(event?.detail?.task);

            if (!task.id) {
                return;
            }

            const nextTasks = this.currentStageTasks.filter((existingTask) => Number(existingTask.id) !== Number(task.id));
            nextTasks.push(task);
            this.currentStageTasks = this.sortTasks(nextTasks);
        },
        taskStatusClasses(task) {
            return asRecord(task).is_completed
                ? 'bg-emerald-100 text-emerald-700'
                : 'bg-amber-100 text-amber-700';
        },
        isTaskCompleting(task) {
            return this.taskCompletingIds.includes(Number(asRecord(task).id));
        },
        refreshHeaderStatusBadge() {
            const badge = document.querySelector('[data-workflow-status-badge]');

            if (!badge) {
                return;
            }

            badge.textContent = this.count.workflow_status_label
                || this.workflow.currentLabel
                || 'Draft';
        },
        dispatchInventoryWorkflowSync() {
            window.dispatchEvent(new CustomEvent('inventory-count-header-action-updated', {
                detail: {
                    workflow: this.workflow,
                    workflowProgressSteps: this.workflowProgressSteps,
                },
            }));
        },
        async refreshCrudSection(sectionKey, sectionConfig = null) {
            const sectionRootEl = document.querySelector(`[data-section-key="${sectionKey}"]`);

            if (!sectionRootEl || !sectionRootEl._jsCrudSectionApi) {
                return;
            }

            if (sectionConfig) {
                sectionRootEl._jsCrudSectionApi.updateSectionConfig(sectionConfig);
            }

            if (typeof sectionRootEl._jsCrudSectionApi.refresh === 'function') {
                await sectionRootEl._jsCrudSectionApi.refresh(1);
            }
        },
        countLinesHaveBlankQuantities() {
            const sectionRootEl = document.querySelector('[data-section-key="countLines"]');

            if (!sectionRootEl) {
                return false;
            }

            return Array.from(sectionRootEl.querySelectorAll('[data-smart-number-input-root] input'))
                .filter((input) => input.type !== 'hidden' && !input.disabled)
                .some((input) => String(input.value || '').trim() === '');
        },
        async setCountedQuantityRequiredCue(visible) {
            if (showRequiredCountedQuantityCue === visible) {
                return;
            }

            showRequiredCountedQuantityCue = visible;
            await this.refreshCrudSection('countLines');
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

            const hasActiveStep = steps.some((step) => step.current)
                || steps.some((step) => step.status === 'completed');

            if (
                steps.length > 0
                && !this.count.is_draft_setup
                && !hasActiveStep
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

            let activeMobileStep = null;

            if (steps.length > 0 && (hasActiveStep || !this.count.is_draft_setup)) {
                activeMobileStep = steps.findIndex((step) => step.current || step.status === 'current');

                if (activeMobileStep < 0) {
                    activeMobileStep = steps.findIndex((step) => step.status === 'upcoming');
                }

                if (activeMobileStep < 0) {
                    activeMobileStep = Math.max(0, steps.length - 1);
                }
            }

            const activeMobileStepLiteral = activeMobileStep === null ? 'null' : String(activeMobileStep);

            return `
                <nav class="w-full" aria-label="Progress" x-data="{ activeWorkflowStep: ${activeMobileStepLiteral} }" x-cloak>
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
            const label = asString(step.label);
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
                        >${escapeHtml(label)}</span>
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
        performHeaderWorkflowAction(action = null) {
            const actionType = asString(action?.type || action?.handlerKey || action?.id, '');

            if (String(action?.method || '').toUpperCase() === 'DELETE' || actionType === 'cancel') {
                this.cancelInventoryCount(action);
                return;
            }

            if (actionType === 'previous') {
                this.moveToPreviousWorkflowStage();
                return;
            }

            if (actionType === 'submit') {
                this.submitToWorkflow();
                return;
            }

            if (actionType === 'advance') {
                this.advanceWorkflow();
                return;
            }

            setWorkflowActionLoading(false);
        },

        async cancelInventoryCount(action = null) {
            const endpoint = asString(action?.endpoint || this.count.delete_url || '', '');

            if (!endpoint) {
                setWorkflowActionLoading(false);
                return;
            }

            try {
                const response = await fetch(endpoint, {
                    method: 'DELETE',
                    headers: {
                        Accept: 'application/json',
                        'X-CSRF-TOKEN': this.csrf,
                    },
                });

                const data = await response.json().catch(() => ({}));
                const responseData = asRecord(data);

                if (!response.ok) {
                    this.showToast('error', responseData.message || 'Unable to cancel inventory count.');
                    return;
                }

                this.hydrateCountResponse(responseData);
                this.showToast('success', 'Inventory count cancelled.');
            } catch (error) {
                this.showToast('error', 'Unable to cancel inventory count.');
            } finally {
                setWorkflowActionLoading(false);
            }
        },

        detailsPayload(field) {
            if (field === 'notes') {
                return {
                    notes: this.details.notes,
                };
            }

            if (field === 'counted_at') {
                return {
                    counted_at: this.details.counted_at_iso,
                    notes: this.details.notes,
                };
            }

            return {
                counted_at: this.details.counted_at_iso,
                notes: this.details.notes,
                assigned_to_user_id: this.details.assigned_to_user_id === ''
                    ? null
                    : Number.parseInt(String(this.details.assigned_to_user_id), 10),
            };
        },

        showToast(type, message) {
            this.toast.type = type;
            this.toast.message = message;
            this.toast.show = true;

            window.clearTimeout(this._toastTimeoutId);
            this._toastTimeoutId = window.setTimeout(() => {
                this.toast.show = false;
            }, 1500);
        },

        async completeInventoryCountTask(event) {
            const form = event ? event.target : null;

            if (!(form instanceof HTMLFormElement) || !form.action) {
                return;
            }

            const button = form.querySelector('[data-inventory-count-task-complete-button]');
            const row = form.closest('[data-inventory-count-task-row]');
            const taskId = Number(row?.dataset.inventoryCountTaskId || 0);

            if (button) {
                button.disabled = true;
                button.classList.add('cursor-not-allowed', 'opacity-50');
            }

            if (taskId > 0 && !this.taskCompletingIds.includes(taskId)) {
                this.taskCompletingIds = [...this.taskCompletingIds, taskId];
            }

            try {
                const response = await fetch(form.action, {
                    method: 'POST',
                    headers: {
                        Accept: 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    body: new FormData(form),
                });

                const data = await response.json().catch(() => ({}));
                const responseData = asRecord(data);

                if (!response.ok) {
                    this.showToast('error', responseData.message || 'Unable to complete task.');

                    if (button) {
                        button.disabled = false;
                        button.classList.remove('cursor-not-allowed', 'opacity-50');
                    }

                    return;
                }

                const task = asRecord(responseData.data);

                if (task.id) {
                    const nextTasks = this.currentStageTasks.filter((currentTask) => Number(currentTask.id) !== Number(task.id));
                    nextTasks.push(task);
                    this.currentStageTasks = this.sortTasks(nextTasks);
                }

                this.showToast('success', 'Task completed.');
            } catch (error) {
                this.showToast('error', 'Unable to complete task.');

                if (button) {
                    button.disabled = false;
                    button.classList.remove('cursor-not-allowed', 'opacity-50');
                }
            } finally {
                if (taskId > 0) {
                    this.taskCompletingIds = this.taskCompletingIds.filter((value) => Number(value) !== taskId);
                }
            }
        },

        async saveDetails(field) {
            if (!this.count.update_url) {
                return;
            }

            if (field === 'notes' && !this.count.can_edit_notes) {
                return;
            }

            if (field === 'counted_at' && !this.count.can_edit_counted_at) {
                return;
            }

            if (field === 'assigned_to_user_id' && !this.count.can_edit_assignment) {
                return;
            }

            const previousCountedAtIso = this.lastSavedDetailsCountedAtIso;
            const previousAssignedToUserId = this.lastSavedDetailsAssignedToUserId;
            const previousNotes = this.lastSavedDetailsNotes;

            if (field === 'counted_at' && (this.details.counted_at_iso || '') === previousCountedAtIso) {
                return;
            }

            if (field === 'notes' && (this.details.notes || '') === previousNotes) {
                return;
            }

            if (field === 'assigned_to_user_id' && this.normalizedAssignedToUserId(this.details.assigned_to_user_id) === previousAssignedToUserId) {
                return;
            }

            if (field === 'counted_at') {
                this.detailsCountedAtSaving = true;
            } else if (field === 'assigned_to_user_id') {
                this.detailsAssignmentSaving = true;
            } else if (field === 'notes') {
                this.detailsNotesSaving = true;
            }

            try {
                const response = await fetch(this.count.update_url, {
                    method: 'PATCH',
                    headers: {
                        Accept: 'application/json',
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': this.csrf,
                    },
                    body: JSON.stringify(this.detailsPayload(field)),
                });

                const data = await response.json().catch(() => ({}));
                const responseData = asRecord(data);

                if (!response.ok) {
                    this.details.counted_at_iso = previousCountedAtIso;
                    this.details.assigned_to_user_id = previousAssignedToUserId;
                    this.details.notes = previousNotes;
                    this.showToast('error', responseData.message || 'Unable to update inventory count details.');
                    return;
                }

                this.hydrateCountResponse(responseData);
                this.showToast('success', 'Inventory count details updated.');
            } catch (error) {
                this.details.counted_at_iso = previousCountedAtIso;
                this.details.assigned_to_user_id = previousAssignedToUserId;
                this.details.notes = previousNotes;
                this.showToast('error', 'Unable to update inventory count details.');
            } finally {
                this.detailsCountedAtSaving = false;
                this.detailsAssignmentSaving = false;
                this.detailsNotesSaving = false;
            }
        },

        async submitToWorkflow() {
            if (!this.count.submit_url) {
                setWorkflowActionLoading(false);
                this.showToast('error', 'Unable to submit count.');
                return;
            }

            try {
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

                this.hydrateCountResponse(data);
                this.showToast('success', 'Inventory count submitted.');
            } catch (error) {
                this.showToast('error', 'Unable to submit count.');
            } finally {
                setWorkflowActionLoading(false);
            }
        },

        async moveToPreviousWorkflowStage() {
            if (!this.count.previous_url) {
                setWorkflowActionLoading(false);
                this.showToast('error', 'Unable to move count to the previous stage.');
                return;
            }

            try {
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

                this.hydrateCountResponse(data);
                this.showToast('success', 'Inventory count moved to the previous stage.');
            } catch (error) {
                this.showToast('error', 'Unable to move count to the previous stage.');
            } finally {
                setWorkflowActionLoading(false);
            }
        },

        async advanceWorkflow() {
            if (!this.count.advance_url) {
                setWorkflowActionLoading(false);
                this.showToast('error', 'Unable to advance count.');
                return;
            }

            try {
                if ((this.count.workflow_stage_key || '') === 'counting') {
                    if (this.countLinesHaveBlankQuantities()) {
                        await this.setCountedQuantityRequiredCue(true);
                        this.showToast('error', 'Enter a counted quantity for every item before moving past Counting.');
                        return;
                    }

                    await this.setCountedQuantityRequiredCue(false);
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

                this.hydrateCountResponse(data);
                this.showToast('success', 'Inventory count advanced.');
            } catch (error) {
                this.showToast('error', 'Unable to advance count.');
            } finally {
                setWorkflowActionLoading(false);
            }
        },
    }));
}
