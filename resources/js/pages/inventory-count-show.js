import Alpine from 'alpinejs';
import { mountCrudSection } from '../lib/js-crud-section';

const inventoryCountToastEvent = 'inventory-count-toast';
const asRecord = (value) => (value && typeof value === 'object' && !Array.isArray(value) ? value : {});
const asString = (value, fallback = '') => (typeof value === 'string' ? value : fallback);

export function mount(rootEl, payload) {
    const safePayload = payload || {};
    const csrfMeta = document.querySelector('meta[name=csrf-token]');
    const csrfToken = csrfMeta ? (csrfMeta.getAttribute('content') || '') : '';
    const sectionsPayload = asRecord(safePayload.sections);
    const countPayload = asRecord(safePayload.count);

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
                                label: 'QTY',
                                field: 'counted_quantity_input',
                                strong: true,
                                type: 'input',
                                handlerKey: 'updateCountedQuantity',
                                labelBare: true,
                                icon: 'check-circle',
                                showSuccessIcon: Boolean(safeRecord._countedQuantitySaved),
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

        hydrateCountResponse(data) {
            if (!data || typeof data !== 'object') {
                return;
            }

            this.count = {
                ...this.count,
                ...data,
            };
            this.details.counted_at_iso = data.counted_at_iso || '';
            this.details.assigned_to_user_id = this.normalizedAssignedToUserId(data.assigned_to_user_id);
            this.details.notes = data.notes || '';
            this.details.assignee_options = Array.isArray(data.assignee_options) ? data.assignee_options : [];
            this.lastSavedDetailsCountedAtIso = this.details.counted_at_iso;
            this.lastSavedDetailsAssignedToUserId = this.details.assigned_to_user_id;
            this.lastSavedDetailsNotes = this.details.notes;
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
            }, 2500);
        },

        async completeInventoryCountTask(event) {
            const form = event?.target;

            if (!(form instanceof HTMLFormElement) || !form.action) {
                return;
            }

            const button = form.querySelector('[data-inventory-count-task-complete-button]');

            if (button) {
                button.disabled = true;
                button.classList.add('cursor-not-allowed', 'opacity-50');
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
                const row = form.closest('[data-inventory-count-task-row]');
                const status = row?.querySelector('[data-inventory-count-task-status]');
                const assignedTo = row?.querySelector('[data-inventory-count-task-assigned-to]');
                const completedBy = row?.querySelector('[data-inventory-count-task-completed-by]');
                const completedByName = completedBy?.querySelector('[data-inventory-count-task-completed-by-name]');

                if (status) {
                    status.textContent = task.status || 'completed';
                    status.classList.remove('bg-gray-200', 'text-gray-700');
                    status.classList.add('bg-emerald-100', 'text-emerald-700');
                }

                assignedTo?.remove();

                if (completedBy) {
                    completedBy.classList.remove('hidden');

                    if (completedByName) {
                        if (!completedBy.textContent.includes('Completed By:')) {
                            const label = document.createElement('span');
                            label.className = 'text-gray-500';
                            label.textContent = 'Completed By: ';
                            completedBy.prepend(label);
                        }

                        completedByName.textContent = task.completed_by_user_name || '—';
                    }
                }

                form.remove();
                this.showToast('success', 'Task completed.');
            } catch (error) {
                this.showToast('error', 'Unable to complete task.');

                if (button) {
                    button.disabled = false;
                    button.classList.remove('cursor-not-allowed', 'opacity-50');
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

                this.hydrateCountResponse(responseData.count || {});
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
