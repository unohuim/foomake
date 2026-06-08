@props([
    'users' => collect(),
    'workflowDomainId' => null,
    'domainRecordId' => null,
    'workflowStageId' => null,
    'open' => 'showTaskCreate',
    'close' => 'showTaskCreate = false',
])

@php
    $hasWorkflowContext = $workflowDomainId !== null
        && $domainRecordId !== null;
@endphp

<div x-data="taskCreateDrawer">
    <x-slide-over-shell
        :open="$open"
        close="closeTaskCreate()"
        submit="submitTaskCreate($event)"
        :action="route('tasks.store')"
        title="{{ __('Create Task') }}"
        title-id="create-task-slide-over-title"
    >
        @csrf

        @if ($hasWorkflowContext)
            <input type="hidden" name="workflow_domain_id" value="{{ $workflowDomainId }}">
            <input type="hidden" name="domain_record_id" value="{{ $domainRecordId }}">
            @if ($workflowStageId !== null)
                <input type="hidden" name="workflow_stage_id" value="{{ $workflowStageId }}">
            @endif
        @endif

        <div class="space-y-5">
            <div>
                <label for="manual-task-title" class="block text-sm font-medium text-gray-700">{{ __('Title') }}</label>
                <input
                    id="manual-task-title"
                    name="title"
                    type="text"
                    required
                    maxlength="255"
                    class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500"
                    x-model="taskForm.title"
                >
                <p class="mt-1 text-sm text-red-600" x-show="taskErrors.title.length" x-text="taskErrors.title[0]"></p>
            </div>

            <div>
                <label for="manual-task-assigned-to-user-id" class="block text-sm font-medium text-gray-700">{{ __('Assigned To') }}</label>
                <select
                    id="manual-task-assigned-to-user-id"
                    name="assigned_to_user_id"
                    required
                    class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500"
                    x-model="taskForm.assigned_to_user_id"
                >
                    <option value="">{{ __('Select user') }}</option>
                    @foreach ($users as $user)
                        <option value="{{ $user['id'] ?? $user->id }}">{{ $user['name'] ?? $user->name }}</option>
                    @endforeach
                </select>
                <p class="mt-1 text-sm text-red-600" x-show="taskErrors.assigned_to_user_id.length" x-text="taskErrors.assigned_to_user_id[0]"></p>
            </div>

            <div>
                <label for="manual-task-due-date" class="block text-sm font-medium text-gray-700">{{ __('Due Date') }}</label>
                <input
                    id="manual-task-due-date"
                    name="due_date"
                    type="date"
                    class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500"
                    x-model="taskForm.due_date"
                    x-on:click="$el.showPicker?.()"
                >
                <p class="mt-1 text-sm text-red-600" x-show="taskErrors.due_date.length" x-text="taskErrors.due_date[0]"></p>
            </div>

            <div>
                <label for="manual-task-description" class="block text-sm font-medium text-gray-700">{{ __('Description') }}</label>
                <textarea
                    id="manual-task-description"
                    name="description"
                    rows="4"
                    class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500"
                    x-model="taskForm.description"
                ></textarea>
                <p class="mt-1 text-sm text-red-600" x-show="taskErrors.description.length" x-text="taskErrors.description[0]"></p>
            </div>

            <p class="text-sm text-red-600" x-show="taskCreateError" x-text="taskCreateError"></p>
        </div>

        <x-slot name="footer">
            <button
                type="button"
                class="rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 shadow-sm transition hover:bg-gray-50"
                x-on:click="closeTaskCreate()"
            >
                {{ __('Cancel') }}
            </button>
            <button
                type="submit"
                class="rounded-md border border-transparent bg-blue-600 px-4 py-2 text-sm font-medium text-white shadow-sm transition hover:bg-blue-500 disabled:cursor-not-allowed disabled:opacity-60"
                x-bind:disabled="taskCreateSaving"
            >
                {{ __('Create Task') }}
            </button>
        </x-slot>
    </x-slide-over-shell>
</div>
