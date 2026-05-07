<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Workflows') }}
        </h2>
    </x-slot>

    <script type="application/json" id="admin-workflows-index-payload">@json($payload)</script>

    <div
        class="py-12"
        data-page="admin-workflows-index"
        data-payload="admin-workflows-index-payload"
        x-data="adminWorkflowsIndex"
    >
        <div class="fixed right-6 top-6 z-50" x-show="toast.visible">
            <div
                class="rounded-xl px-4 py-3 text-sm shadow-lg"
                :class="toast.type === 'success' ? 'bg-emerald-50 text-emerald-700' : 'bg-rose-50 text-rose-700'"
                x-text="toast.message"
            ></div>
        </div>

        <div class="mx-auto max-w-7xl space-y-6 sm:px-6 lg:px-8">
            <div class="rounded-3xl border border-slate-200 bg-white shadow-sm">
                <div class="flex flex-col gap-4 p-6 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <h3 class="text-lg font-semibold text-slate-900">Workflow configuration</h3>
                        <p class="mt-1 text-sm text-slate-600">Manage tenant-scoped operational stages and task templates.</p>
                    </div>

                    <label class="inline-flex items-center gap-3 text-sm font-medium text-slate-700">
                        <input
                            type="checkbox"
                            class="rounded border-slate-300 text-slate-900 shadow-sm focus:ring-slate-500"
                            x-model="showInactive"
                        >
                        <span>Show inactive</span>
                    </label>
                </div>
            </div>

            <section class="overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm">
                <button
                    type="button"
                    class="flex w-full items-center justify-between px-6 py-5 text-left"
                    x-on:click="stagesOpen = !stagesOpen"
                >
                    <div>
                        <h3 class="text-lg font-semibold text-slate-900">Stages</h3>
                        <p class="mt-1 text-sm text-slate-600">Create, edit, deactivate, reactivate, and reorder operational workflow stages.</p>
                    </div>
                    <span class="text-sm font-semibold text-slate-500" x-text="stagesOpen ? 'Hide' : 'Show'"></span>
                </button>

                <div class="border-t border-slate-200 px-6 py-6" x-cloak x-show="stagesOpen">
                    <div class="grid gap-6 lg:grid-cols-[minmax(0,1.3fr)_minmax(320px,0.7fr)]">
                        <div class="space-y-4">
                            <div class="flex items-center justify-between">
                                <h4 class="text-sm font-semibold uppercase tracking-[0.2em] text-slate-500">Configured stages</h4>
                                <button
                                    type="button"
                                    class="rounded-full border border-slate-300 px-3 py-1.5 text-xs font-semibold uppercase tracking-widest text-slate-700 hover:bg-slate-50"
                                    x-on:click="openStageCreate()"
                                >
                                    Create stage
                                </button>
                            </div>

                            <div class="space-y-3">
                                <template x-for="stage in filteredStages()" :key="stage.id">
                                    <div class="rounded-2xl border border-slate-200 bg-slate-50/70 p-4">
                                        <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                                            <div>
                                                <div class="flex items-center gap-3">
                                                    <p class="text-sm font-semibold text-slate-900" x-text="stage.name"></p>
                                                    <span
                                                        class="rounded-full px-2.5 py-1 text-[11px] font-semibold uppercase tracking-wide"
                                                        :class="stage.is_active ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-200 text-slate-600'"
                                                        x-text="stage.is_active ? 'Active' : 'Inactive'"
                                                    ></span>
                                                </div>
                                                <p class="mt-1 text-xs uppercase tracking-[0.2em] text-slate-500" x-text="stage.workflow_domain_key"></p>
                                                <p class="mt-2 text-sm text-slate-600" x-text="stage.description || 'No description.'"></p>
                                            </div>

                                            <div class="flex flex-wrap gap-3 text-sm">
                                                <button type="button" class="text-sky-700 hover:text-sky-600" x-on:click="openStageEdit(stage)">Edit</button>
                                                <button type="button" class="text-slate-700 hover:text-slate-600" x-on:click="toggleStage(stage)">
                                                    <span x-text="stage.is_active ? 'Deactivate' : 'Reactivate'"></span>
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </template>
                            </div>
                        </div>

                        <form class="rounded-2xl border border-slate-200 bg-slate-50/80 p-5" x-on:submit.prevent="submitStageForm()">
                            <div class="flex items-start justify-between gap-4">
                                <div>
                                    <h4 class="text-base font-semibold text-slate-900" x-text="stageFormMode === 'create' ? 'Create stage' : 'Edit stage'"></h4>
                                    <p class="mt-1 text-sm text-slate-600">Domain-backed operational stages only.</p>
                                </div>
                                <button type="button" class="text-sm text-slate-500 hover:text-slate-700" x-on:click="resetStageForm()">Reset</button>
                            </div>

                            <div class="mt-5 space-y-4">
                                <label class="block text-sm font-medium text-slate-700">
                                    Domain
                                    <select class="mt-1 block w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-slate-500 focus:ring-slate-500" x-model="stageForm.workflow_domain_id">
                                        <option value="">Select domain</option>
                                        <template x-for="domain in domains" :key="domain.id">
                                            <option :value="String(domain.id)" x-text="domain.name"></option>
                                        </template>
                                    </select>
                                </label>

                                <label class="block text-sm font-medium text-slate-700">
                                    Key
                                    <input type="text" class="mt-1 block w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-slate-500 focus:ring-slate-500" x-model="stageForm.key">
                                </label>

                                <label class="block text-sm font-medium text-slate-700">
                                    Name
                                    <input type="text" class="mt-1 block w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-slate-500 focus:ring-slate-500" x-model="stageForm.name">
                                </label>

                                <label class="block text-sm font-medium text-slate-700">
                                    Description
                                    <textarea class="mt-1 block w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-slate-500 focus:ring-slate-500" rows="3" x-model="stageForm.description"></textarea>
                                </label>

                                <label class="block text-sm font-medium text-slate-700">
                                    Sort order
                                    <input type="number" class="mt-1 block w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-slate-500 focus:ring-slate-500" x-model="stageForm.sort_order">
                                </label>

                                <label class="inline-flex items-center gap-3 text-sm font-medium text-slate-700" x-show="stageFormMode === 'edit'">
                                    <input type="checkbox" class="rounded border-slate-300 text-slate-900 shadow-sm focus:ring-slate-500" x-model="stageForm.is_active">
                                    <span>Active</span>
                                </label>
                            </div>

                            <div class="mt-5 flex items-center justify-between">
                                <button type="button" class="rounded-full border border-slate-300 px-3 py-1.5 text-xs font-semibold uppercase tracking-widest text-slate-700 hover:bg-slate-100" x-on:click="reorderStages()">
                                    Reorder active stages
                                </button>

                                <button type="submit" class="rounded-full bg-slate-900 px-4 py-2 text-xs font-semibold uppercase tracking-widest text-white hover:bg-slate-800">
                                    Save stage
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </section>

            <section class="overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm">
                <button
                    type="button"
                    class="flex w-full items-center justify-between px-6 py-5 text-left"
                    x-on:click="tasksOpen = !tasksOpen"
                >
                    <div>
                        <h3 class="text-lg font-semibold text-slate-900">Tasks</h3>
                        <p class="mt-1 text-sm text-slate-600">Configure stage-scoped task templates and default assignees.</p>
                    </div>
                    <span class="text-sm font-semibold text-slate-500" x-text="tasksOpen ? 'Hide' : 'Show'"></span>
                </button>

                <div class="border-t border-slate-200 px-6 py-6" x-cloak x-show="tasksOpen">
                    <div class="grid gap-6 lg:grid-cols-[minmax(0,1.3fr)_minmax(320px,0.7fr)]">
                        <div class="space-y-4">
                            <div class="flex items-center justify-between">
                                <h4 class="text-sm font-semibold uppercase tracking-[0.2em] text-slate-500">Task templates</h4>
                                <button
                                    type="button"
                                    class="rounded-full border border-slate-300 px-3 py-1.5 text-xs font-semibold uppercase tracking-widest text-slate-700 hover:bg-slate-50"
                                    x-on:click="openTaskTemplateCreate()"
                                >
                                    Create task
                                </button>
                            </div>

                            <div class="space-y-3">
                                <template x-for="taskTemplate in filteredTaskTemplates()" :key="taskTemplate.id">
                                    <div class="rounded-2xl border border-slate-200 bg-slate-50/70 p-4">
                                        <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                                            <div>
                                                <div class="flex items-center gap-3">
                                                    <p class="text-sm font-semibold text-slate-900" x-text="taskTemplate.title"></p>
                                                    <span
                                                        class="rounded-full px-2.5 py-1 text-[11px] font-semibold uppercase tracking-wide"
                                                        :class="taskTemplate.is_active ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-200 text-slate-600'"
                                                        x-text="taskTemplate.is_active ? 'Active' : 'Inactive'"
                                                    ></span>
                                                </div>
                                                <p class="mt-1 text-xs uppercase tracking-[0.2em] text-slate-500">
                                                    <span x-text="taskTemplate.workflow_domain_key"></span>
                                                    <span> / </span>
                                                    <span x-text="taskTemplate.workflow_stage_key"></span>
                                                </p>
                                                <p class="mt-2 text-sm text-slate-600" x-text="taskTemplate.description || 'No description.'"></p>
                                                <p class="mt-2 text-xs text-slate-500" x-text="taskTemplate.default_assignee_name ? `Default assignee: ${taskTemplate.default_assignee_name}` : 'Default assignee: first tenant user'"></p>
                                            </div>

                                            <div class="flex flex-wrap gap-3 text-sm">
                                                <button type="button" class="text-sky-700 hover:text-sky-600" x-on:click="openTaskTemplateEdit(taskTemplate)">Edit</button>
                                                <button type="button" class="text-slate-700 hover:text-slate-600" x-on:click="toggleTaskTemplate(taskTemplate)">
                                                    <span x-text="taskTemplate.is_active ? 'Deactivate' : 'Reactivate'"></span>
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </template>
                            </div>
                        </div>

                        <form class="rounded-2xl border border-slate-200 bg-slate-50/80 p-5" x-on:submit.prevent="submitTaskTemplateForm()">
                            <div class="flex items-start justify-between gap-4">
                                <div>
                                    <h4 class="text-base font-semibold text-slate-900" x-text="taskTemplateFormMode === 'create' ? 'Create task template' : 'Edit task template'"></h4>
                                    <p class="mt-1 text-sm text-slate-600">Generated tasks always resolve to a user.</p>
                                </div>
                                <button type="button" class="text-sm text-slate-500 hover:text-slate-700" x-on:click="resetTaskTemplateForm()">Reset</button>
                            </div>

                            <div class="mt-5 space-y-4">
                                <label class="block text-sm font-medium text-slate-700">
                                    Domain
                                    <select class="mt-1 block w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-slate-500 focus:ring-slate-500" x-model="taskTemplateForm.workflow_domain_id">
                                        <option value="">Select domain</option>
                                        <template x-for="domain in domains" :key="domain.id">
                                            <option :value="String(domain.id)" x-text="domain.name"></option>
                                        </template>
                                    </select>
                                </label>

                                <label class="block text-sm font-medium text-slate-700">
                                    Stage
                                    <select class="mt-1 block w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-slate-500 focus:ring-slate-500" x-model="taskTemplateForm.workflow_stage_id">
                                        <option value="">Select stage</option>
                                        <template x-for="stage in stageOptionsForTaskForm()" :key="stage.id">
                                            <option :value="String(stage.id)" x-text="stage.name"></option>
                                        </template>
                                    </select>
                                </label>

                                <label class="block text-sm font-medium text-slate-700">
                                    Title
                                    <input type="text" class="mt-1 block w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-slate-500 focus:ring-slate-500" x-model="taskTemplateForm.title">
                                </label>

                                <label class="block text-sm font-medium text-slate-700">
                                    Description
                                    <textarea class="mt-1 block w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-slate-500 focus:ring-slate-500" rows="3" x-model="taskTemplateForm.description"></textarea>
                                </label>

                                <label class="block text-sm font-medium text-slate-700">
                                    Default Assignee
                                    <select class="mt-1 block w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-slate-500 focus:ring-slate-500" x-model="taskTemplateForm.default_assignee_user_id">
                                        <option value="">First tenant user</option>
                                        <template x-for="user in users" :key="user.id">
                                            <option :value="String(user.id)" x-text="`${user.name} (${user.email})`"></option>
                                        </template>
                                    </select>
                                </label>

                                <label class="block text-sm font-medium text-slate-700">
                                    Sort order
                                    <input type="number" class="mt-1 block w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-slate-500 focus:ring-slate-500" x-model="taskTemplateForm.sort_order">
                                </label>

                                <label class="inline-flex items-center gap-3 text-sm font-medium text-slate-700" x-show="taskTemplateFormMode === 'edit'">
                                    <input type="checkbox" class="rounded border-slate-300 text-slate-900 shadow-sm focus:ring-slate-500" x-model="taskTemplateForm.is_active">
                                    <span>Active</span>
                                </label>
                            </div>

                            <div class="mt-5 flex items-center justify-between">
                                <button type="button" class="rounded-full border border-slate-300 px-3 py-1.5 text-xs font-semibold uppercase tracking-widest text-slate-700 hover:bg-slate-100" x-on:click="reorderTaskTemplates()">
                                    Reorder tasks
                                </button>

                                <button type="submit" class="rounded-full bg-slate-900 px-4 py-2 text-xs font-semibold uppercase tracking-widest text-white hover:bg-slate-800">
                                    Save task
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </section>
        </div>
    </div>
</x-app-layout>
