@props([
    'workflow' => [],
    'csrfToken' => '',
    'mode' => 'dispatch',
    'actionEventName' => 'workflow-action-button',
    'syncEventName' => 'workflow-updated',
    'syncStateKey' => 'workflow',
])

@php
    $workflowExpression = 'workflowActionButton('
        . \Illuminate\Support\Js::from($workflow)->toHtml()
        . ', '
        . \Illuminate\Support\Js::from($csrfToken)->toHtml()
        . ', '
        . \Illuminate\Support\Js::from([
            'mode' => $mode,
            'actionEventName' => $actionEventName,
            'syncEventName' => $syncEventName,
            'syncStateKey' => $syncStateKey,
        ])->toHtml()
        . ')';
@endphp

<div x-data="{{ $workflowExpression }}" x-cloak data-workflow-action-button>
    <template x-if="isCancelledState()">
        <button
            type="button"
            class="inline-flex items-stretch rounded-lg border border-slate-300 bg-white shadow-sm transition disabled:cursor-not-allowed disabled:opacity-60"
            disabled
        >
            <span class="inline-flex items-center px-3 py-2 text-sm font-medium text-slate-700">
                <span class="text-sm font-semibold uppercase text-slate-700" x-text="triggerLabel()"></span>
            </span>
        </button>
    </template>

    <template x-if="isCompletedState()">
        <button
            type="button"
            class="inline-flex items-stretch rounded-lg border border-slate-300 bg-white shadow-sm transition disabled:cursor-not-allowed disabled:opacity-60"
            disabled
        >
            <span class="inline-flex items-center px-3 py-2 text-sm font-medium text-slate-700">
                <span class="text-sm font-semibold uppercase text-slate-700" x-text="triggerLabel()"></span>
            </span>
        </button>
    </template>

    <template x-if="!isCancelledState() && !isCompletedState() && hasActions()">
        <x-dropdown align="right" width="w-72" contentClasses="rounded-xl border border-slate-200 bg-white p-1.5 shadow-lg ring-1 ring-black/5">
            <x-slot name="trigger">
                <button
                    type="button"
                    class="inline-flex items-stretch rounded-lg border border-slate-300 bg-white shadow-sm transition hover:border-slate-400 hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-60"
                    :disabled="loading"
                >
                    <span class="inline-flex items-center gap-2 px-3 py-2 text-sm font-medium text-slate-700">
                        <svg class="h-4 w-4 text-slate-500" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                            <path fill-rule="evenodd" d="M16.704 5.29a1 1 0 0 1 .006 1.414l-8 8.07a1 1 0 0 1-1.42 0l-4-4.035a1 1 0 0 1 1.42-1.41l3.29 3.32 7.29-7.36a1 1 0 0 1 1.414 0Z" clip-rule="evenodd" />
                        </svg>
                        <span class="text-sm font-semibold uppercase text-slate-700" x-text="triggerLabel()"></span>
                    </span>
                    <span class="inline-flex items-center border-l border-slate-300 px-2.5 text-slate-500">
                        <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                            <path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 0 1 1.06.02L10 11.168l3.71-3.938a.75.75 0 1 1 1.08 1.04l-4.25 4.5a.75.75 0 0 1-1.08 0l-4.25-4.5a.75.75 0 0 1 .02-1.06Z" clip-rule="evenodd" />
                        </svg>
                    </span>
                </button>
            </x-slot>

            <div class="space-y-1" role="menu">
                <template x-for="action in menuActions()" :key="action.id || action.type || action.handlerKey || action.label">
                    <button
                        type="button"
                        class="flex w-full items-start rounded-lg px-3 py-2 text-left text-sm transition hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-60"
                        :disabled="loading"
                        x-on:click.stop="$dispatch('close'); submit(action)"
                    >
                        <span class="min-w-0 flex-1">
                            <span class="block font-medium text-slate-900" x-text="action.label"></span>
                            <span class="mt-1 block text-xs leading-5 text-slate-500" x-text="action.description"></span>
                        </span>
                    </button>
                </template>
            </div>

            <p class="px-3 pb-2 pt-1 text-xs text-red-600" x-show="error" x-text="error"></p>
        </x-dropdown>
    </template>
</div>
