import Alpine from 'alpinejs';

const asRecord = (value) => (value && typeof value === 'object' && !Array.isArray(value) ? value : {});
const asArray = (value) => (Array.isArray(value) ? value : []);
const SCALE = 6;
const SCALE_FACTOR = 10n ** 6n;

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

export function mount(rootEl, payload) {
    const safePayload = payload || {};
    const workflowPayload = asRecord(safePayload.workflow);
    const ingredientsPayload = asRecord(safePayload.ingredients);

    Alpine.data('manufacturingMakeOrdersShow', () => ({
        makeOrder: asRecord(safePayload.makeOrder),
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
            }
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
                next_stage_action: asRecord(data.next_stage_action),
                available_stages: asArray(data.available_stages),
                assignee_options: asArray(data.assignee_options),
                current_stage_tasks: asArray(data.current_stage_tasks),
            };
            this.lastSavedWorkflowDueDate = this.workflow.due_date || '';
            this.lastSavedWorkflowOwnerId = this.normalizedWorkflowOwnerId(this.workflow.made_by_user_id);
            this.syncHeaderWorkflowAction();
        },
        syncHeaderWorkflowAction() {
            window.dispatchEvent(new CustomEvent('make-order-header-action-updated', {
                detail: {
                    action: this.workflow.next_stage_action && this.workflow.next_stage_action.label
                        ? this.workflow.next_stage_action
                        : null,
                },
            }));
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
                this.selectedWorkflowStageId = '';
                this.showToast('success', 'Workflow stage updated.');
            } catch (error) {
                this.showToast('error', 'Unable to move workflow stage.');
            } finally {
                this.workflowTransitionSaving = false;
            }
        },
        async moveWorkflowStageTo(workflowStageId) {
            if (!workflowStageId) {
                return;
            }

            this.selectedWorkflowStageId = String(workflowStageId);
            await this.moveWorkflowStage();
        },
        async performHeaderWorkflowAction() {
            const action = asRecord(this.workflow.next_stage_action);

            if (!action.id || this.workflowTransitionSaving) {
                return;
            }

            if (action.type === 'make') {
                await this.makeCurrentOrder(action);
                return;
            }

            await this.moveWorkflowStageTo(action.id);
        },
        async makeCurrentOrder(action) {
            const endpoint = action.endpoint || '';

            if (!endpoint) {
                this.showToast('error', 'Unable to make order.');
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
                this.showToast('success', 'Make order completed.');
            } catch (error) {
                this.showToast('error', 'Unable to make order.');
            } finally {
                this.workflowTransitionSaving = false;
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
            } catch (error) {
                this.showToast('error', 'Unable to remove ingredient.');
            }
        },
    }));
}
