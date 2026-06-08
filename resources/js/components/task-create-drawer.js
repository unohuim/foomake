const emptyErrors = () => ({
    title: [],
    assigned_to_user_id: [],
    due_date: [],
    description: [],
});

const emptyForm = () => ({
    title: '',
    assigned_to_user_id: '',
    due_date: '',
    description: '',
});

const asRecord = (value) => (value && typeof value === 'object' && !Array.isArray(value) ? value : {});

const csrfToken = () => {
    const csrfMeta = document.querySelector('meta[name=csrf-token]');

    return csrfMeta ? (csrfMeta.getAttribute('content') || '') : '';
};

/**
 * Register the manual task create drawer Alpine component.
 *
 * @param {import('alpinejs').Alpine} Alpine
 * @returns {void}
 */
export function registerTaskCreateDrawer(Alpine) {
    Alpine.data('taskCreateDrawer', () => ({
        taskForm: emptyForm(),
        taskErrors: emptyErrors(),
        taskCreateError: '',
        taskCreateSaving: false,

        resetTaskForm() {
            this.taskForm = emptyForm();
            this.taskErrors = emptyErrors();
            this.taskCreateError = '';
        },

        closeTaskCreate() {
            this.showTaskCreate = false;
            this.resetTaskForm();
        },

        async submitTaskCreate(event) {
            if (this.taskCreateSaving) {
                return;
            }

            this.taskCreateSaving = true;
            this.taskErrors = emptyErrors();
            this.taskCreateError = '';

            try {
                const form = event.target;
                const formData = new FormData(form);
                const optionalInteger = (name) => {
                    const value = formData.get(name);

                    return value === null || value === '' ? null : Number(value);
                };
                const response = await fetch(form.action, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        Accept: 'application/json',
                        'X-CSRF-TOKEN': String(formData.get('_token') || ''),
                    },
                    body: JSON.stringify({
                        title: this.taskForm.title,
                        assigned_to_user_id: this.taskForm.assigned_to_user_id === ''
                            ? null
                            : Number(this.taskForm.assigned_to_user_id),
                        due_date: this.taskForm.due_date === '' ? null : this.taskForm.due_date,
                        description: this.taskForm.description,
                        workflow_domain_id: optionalInteger('workflow_domain_id'),
                        domain_record_id: optionalInteger('domain_record_id'),
                        workflow_stage_id: optionalInteger('workflow_stage_id'),
                    }),
                });

                const data = await response.json().catch(() => ({}));

                if (response.status === 422) {
                    this.taskErrors = {
                        ...this.taskErrors,
                        ...(data.errors || {}),
                    };
                    this.taskCreateError = data.message || 'Unable to create task.';
                    return;
                }

                if (!response.ok) {
                    this.taskCreateError = data.message || 'Unable to create task.';
                    return;
                }

                window.dispatchEvent(new CustomEvent('task-created', { detail: { task: data.data || {} } }));
                this.closeTaskCreate();
            } catch (error) {
                this.taskCreateError = 'Unable to create task.';
            } finally {
                this.taskCreateSaving = false;
            }
        },
    }));

    Alpine.data('taskCreateSection', () => ({
        showTaskCreate: false,
        createdTasks: [],
        taskCompletingIds: [],

        appendCreatedTask(event) {
            const task = asRecord(event.detail?.task);

            if (!task.id) {
                return;
            }

            this.createdTasks = [...this.createdTasks, task];
        },

        taskStatusClasses(task) {
            return asRecord(task).is_completed
                ? 'bg-emerald-100 text-emerald-700'
                : 'bg-amber-100 text-amber-700';
        },

        isTaskCompleting(task) {
            const taskId = asRecord(task).id;

            return this.taskCompletingIds.includes(taskId);
        },

        async completeCreatedTask(task) {
            const safeTask = asRecord(task);

            if (!safeTask.id || !safeTask.complete_url || this.isTaskCompleting(safeTask)) {
                return;
            }

            this.taskCompletingIds = [...this.taskCompletingIds, safeTask.id];

            try {
                const response = await fetch(safeTask.complete_url, {
                    method: 'PATCH',
                    headers: {
                        Accept: 'application/json',
                        'X-CSRF-TOKEN': csrfToken(),
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                });
                const data = await response.json().catch(() => ({}));
                const updatedTask = asRecord(data.data);

                if (!response.ok || !updatedTask.id) {
                    return;
                }

                this.createdTasks = this.createdTasks.map((createdTask) => (
                    createdTask.id === updatedTask.id ? updatedTask : createdTask
                ));
            } finally {
                this.taskCompletingIds = this.taskCompletingIds.filter((taskId) => taskId !== safeTask.id);
            }
        },
    }));
}
