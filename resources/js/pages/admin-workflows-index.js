export function mount(rootEl, payload) {
    const Alpine = window.Alpine;
    const safePayload = payload || {};

    const emptyStageForm = () => ({
        workflow_domain_id: '',
        key: '',
        name: '',
        description: '',
        sort_order: 10,
        is_active: true,
    });

    const emptyTaskTemplateForm = () => ({
        workflow_domain_id: '',
        workflow_stage_id: '',
        title: '',
        description: '',
        sort_order: 10,
        default_assignee_user_id: '',
        is_active: true,
    });

    Alpine.data('adminWorkflowsIndex', () => ({
        domains: safePayload.domains || [],
        stages: safePayload.stages || [],
        taskTemplates: safePayload.taskTemplates || [],
        users: safePayload.users || [],
        stageStoreUrl: safePayload.stageStoreUrl || '',
        stageUpdateUrlBase: safePayload.stageUpdateUrlBase || '',
        stageReorderUrl: safePayload.stageReorderUrl || '',
        taskTemplateStoreUrl: safePayload.taskTemplateStoreUrl || '',
        taskTemplateUpdateUrlBase: safePayload.taskTemplateUpdateUrlBase || '',
        taskTemplateReorderUrl: safePayload.taskTemplateReorderUrl || '',
        csrfToken: safePayload.csrfToken || '',
        stagesOpen: true,
        tasksOpen: true,
        showInactive: !!safePayload.showInactive,
        stageFormMode: 'create',
        editingStageId: null,
        stageForm: emptyStageForm(),
        taskTemplateFormMode: 'create',
        editingTaskTemplateId: null,
        taskTemplateForm: emptyTaskTemplateForm(),
        toast: {
            visible: false,
            message: '',
            type: 'success',
            timeoutId: null,
        },
        filteredStages() {
            return this.stages.filter((stage) => this.showInactive || stage.is_active);
        },
        filteredTaskTemplates() {
            return this.taskTemplates.filter((taskTemplate) => this.showInactive || taskTemplate.is_active);
        },
        stageOptionsForTaskForm() {
            if (!this.taskTemplateForm.workflow_domain_id) {
                return [];
            }

            return this.stages.filter((stage) => {
                return String(stage.workflow_domain_id) === String(this.taskTemplateForm.workflow_domain_id)
                    && (this.showInactive || stage.is_active);
            });
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
        openStageCreate() {
            this.stageFormMode = 'create';
            this.editingStageId = null;
            this.stageForm = emptyStageForm();
        },
        openStageEdit(stage) {
            this.stageFormMode = 'edit';
            this.editingStageId = stage.id;
            this.stageForm = {
                workflow_domain_id: String(stage.workflow_domain_id || ''),
                key: stage.key || '',
                name: stage.name || '',
                description: stage.description || '',
                sort_order: Number(stage.sort_order || 10),
                is_active: !!stage.is_active,
            };
        },
        resetStageForm() {
            this.openStageCreate();
        },
        async submitStageForm() {
            const isCreate = this.stageFormMode === 'create';
            const url = isCreate ? this.stageStoreUrl : `${this.stageUpdateUrlBase}/${this.editingStageId}`;
            const method = isCreate ? 'POST' : 'PATCH';
            const payload = {
                ...this.stageForm,
                workflow_domain_id: this.stageForm.workflow_domain_id === ''
                    ? null
                    : Number(this.stageForm.workflow_domain_id),
                sort_order: Number(this.stageForm.sort_order || 0),
            };

            const response = await fetch(url, {
                method,
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': this.csrfToken,
                },
                body: JSON.stringify(payload),
            });

            if (!response.ok) {
                this.showToast('error', 'Unable to save workflow stage.');
                return;
            }

            const data = await response.json();
            this.upsertStage(data.data || {});
            this.showToast('success', isCreate ? 'Workflow stage created.' : 'Workflow stage updated.');
            this.openStageCreate();
        },
        upsertStage(stage) {
            const existingIndex = this.stages.findIndex((entry) => entry.id === stage.id);

            if (existingIndex === -1) {
                this.stages.push(stage);
                return;
            }

            this.stages.splice(existingIndex, 1, stage);
        },
        async toggleStage(stage) {
            await this.submitExistingStage({
                ...stage,
                is_active: !stage.is_active,
            });
        },
        async submitExistingStage(stage) {
            const response = await fetch(`${this.stageUpdateUrlBase}/${stage.id}`, {
                method: 'PATCH',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': this.csrfToken,
                },
                body: JSON.stringify({
                    workflow_domain_id: stage.workflow_domain_id,
                    key: stage.key,
                    name: stage.name,
                    description: stage.description,
                    sort_order: stage.sort_order,
                    is_active: stage.is_active,
                }),
            });

            if (!response.ok) {
                this.showToast('error', 'Unable to update workflow stage.');
                return;
            }

            const data = await response.json();
            this.upsertStage(data.data || {});
            this.showToast('success', 'Workflow stage updated.');
        },
        async reorderStages() {
            const firstDomainId = this.filteredStages()[0]?.workflow_domain_id;

            if (!firstDomainId) {
                return;
            }

            const orderedIds = this.filteredStages()
                .filter((stage) => String(stage.workflow_domain_id) === String(firstDomainId) && stage.is_active)
                .sort((left, right) => Number(left.sort_order) - Number(right.sort_order))
                .map((stage) => stage.id);

            if (orderedIds.length === 0) {
                return;
            }

            const response = await fetch(this.stageReorderUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': this.csrfToken,
                },
                body: JSON.stringify({
                    workflow_domain_id: firstDomainId,
                    ordered_ids: orderedIds,
                }),
            });

            if (response.ok) {
                this.showToast('success', 'Workflow stages reordered.');
            }
        },
        openTaskTemplateCreate() {
            this.taskTemplateFormMode = 'create';
            this.editingTaskTemplateId = null;
            this.taskTemplateForm = emptyTaskTemplateForm();
        },
        openTaskTemplateEdit(taskTemplate) {
            this.taskTemplateFormMode = 'edit';
            this.editingTaskTemplateId = taskTemplate.id;
            this.taskTemplateForm = {
                workflow_domain_id: String(taskTemplate.workflow_domain_id || ''),
                workflow_stage_id: String(taskTemplate.workflow_stage_id || ''),
                title: taskTemplate.title || '',
                description: taskTemplate.description || '',
                sort_order: Number(taskTemplate.sort_order || 10),
                default_assignee_user_id: taskTemplate.default_assignee_user_id ? String(taskTemplate.default_assignee_user_id) : '',
                is_active: !!taskTemplate.is_active,
            };
        },
        resetTaskTemplateForm() {
            this.openTaskTemplateCreate();
        },
        async submitTaskTemplateForm() {
            const isCreate = this.taskTemplateFormMode === 'create';
            const url = isCreate ? this.taskTemplateStoreUrl : `${this.taskTemplateUpdateUrlBase}/${this.editingTaskTemplateId}`;
            const method = isCreate ? 'POST' : 'PATCH';
            const payload = {
                ...this.taskTemplateForm,
                workflow_domain_id: this.taskTemplateForm.workflow_domain_id === ''
                    ? null
                    : Number(this.taskTemplateForm.workflow_domain_id),
                workflow_stage_id: this.taskTemplateForm.workflow_stage_id === ''
                    ? null
                    : Number(this.taskTemplateForm.workflow_stage_id),
                sort_order: Number(this.taskTemplateForm.sort_order || 0),
                default_assignee_user_id: this.taskTemplateForm.default_assignee_user_id === ''
                    ? null
                    : Number(this.taskTemplateForm.default_assignee_user_id),
            };

            const response = await fetch(url, {
                method,
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': this.csrfToken,
                },
                body: JSON.stringify(payload),
            });

            if (!response.ok) {
                this.showToast('error', 'Unable to save workflow task template.');
                return;
            }

            const data = await response.json();
            this.upsertTaskTemplate(data.data || {});
            this.showToast('success', isCreate ? 'Workflow task template created.' : 'Workflow task template updated.');
            this.openTaskTemplateCreate();
        },
        upsertTaskTemplate(taskTemplate) {
            const existingIndex = this.taskTemplates.findIndex((entry) => entry.id === taskTemplate.id);

            if (existingIndex === -1) {
                this.taskTemplates.push(taskTemplate);
                return;
            }

            this.taskTemplates.splice(existingIndex, 1, taskTemplate);
        },
        async toggleTaskTemplate(taskTemplate) {
            const response = await fetch(`${this.taskTemplateUpdateUrlBase}/${taskTemplate.id}`, {
                method: 'PATCH',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': this.csrfToken,
                },
                body: JSON.stringify({
                    workflow_domain_id: taskTemplate.workflow_domain_id,
                    workflow_stage_id: taskTemplate.workflow_stage_id,
                    title: taskTemplate.title,
                    description: taskTemplate.description,
                    sort_order: taskTemplate.sort_order,
                    default_assignee_user_id: taskTemplate.default_assignee_user_id,
                    is_active: !taskTemplate.is_active,
                }),
            });

            if (!response.ok) {
                this.showToast('error', 'Unable to update workflow task template.');
                return;
            }

            const data = await response.json();
            this.upsertTaskTemplate(data.data || {});
            this.showToast('success', 'Workflow task template updated.');
        },
        async reorderTaskTemplates() {
            const stageId = this.taskTemplateForm.workflow_stage_id || this.filteredTaskTemplates()[0]?.workflow_stage_id;

            if (!stageId) {
                return;
            }

            const orderedIds = this.filteredTaskTemplates()
                .filter((taskTemplate) => String(taskTemplate.workflow_stage_id) === String(stageId) && taskTemplate.is_active)
                .sort((left, right) => Number(left.sort_order) - Number(right.sort_order))
                .map((taskTemplate) => taskTemplate.id);

            if (orderedIds.length === 0) {
                return;
            }

            const response = await fetch(this.taskTemplateReorderUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': this.csrfToken,
                },
                body: JSON.stringify({
                    workflow_stage_id: stageId,
                    ordered_ids: orderedIds,
                }),
            });

            if (response.ok) {
                this.showToast('success', 'Workflow task templates reordered.');
            }
        },
    }));
}
