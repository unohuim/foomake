@props([
    'config' => [],
])

@php
    $notes = $config['notes'] ?? [];
@endphp

<x-detail-section-card
    title="Notes"
    :description="__('Timeline of internal comments for this resource.')"
    :default-open="true"
>
    <div
        x-data="notesFeed({
            storeUrl: @js($config['store_url'] ?? ''),
            csrfToken: @js($config['csrf_token'] ?? csrf_token()),
            emptyState: @js($config['empty_state'] ?? __('No notes yet.')),
        })"
        data-notes-feed-root
    >
        <ol class="relative space-y-5 border-l border-gray-200 pl-5" role="list" data-notes-timeline x-ref="list">
            @foreach ($notes as $note)
                <li class="relative" data-note-row data-note-created-at="{{ $note['created_at'] ?? '' }}">
                    <span class="absolute -left-[1.65rem] top-4 flex h-3 w-3 rounded-full bg-blue-600 ring-4 ring-white"></span>

                    <article class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm" data-note-card>
                        <div class="flex flex-wrap items-center justify-between gap-x-3 gap-y-1">
                            <p class="text-sm font-semibold text-gray-900">{{ $note['author_name'] ?? __('Unknown user') }}</p>
                            <time class="text-xs font-medium text-gray-500" datetime="{{ $note['created_at'] ?? '' }}">
                                {{ $note['created_at_display'] ?? '' }}
                            </time>
                        </div>

                        <p class="mt-3 whitespace-pre-line text-sm leading-6 text-gray-700">{{ $note['body'] ?? '' }}</p>
                    </article>
                </li>
            @endforeach
        </ol>

        <div
            class="{{ count($notes) > 0 ? 'hidden ' : '' }}rounded-xl border border-dashed border-gray-300 px-4 py-8 text-center text-sm text-gray-500"
            x-ref="emptyState"
            data-notes-empty-state
        >
            {{ $config['empty_state'] ?? __('No notes yet.') }}
        </div>

        <form class="mt-6 space-y-3" x-on:submit.prevent="submit" data-notes-composer>
            <div>
                <label class="sr-only" for="notes-feed-body">{{ __('Comment') }}</label>
                <textarea
                    id="notes-feed-body"
                    rows="3"
                    class="block w-full rounded-xl border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 shadow-sm transition focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20 disabled:cursor-not-allowed disabled:bg-gray-100"
                    placeholder="{{ __('Add an internal comment') }}"
                    x-model="body"
                    x-bind:disabled="saving"
                    data-notes-body
                ></textarea>
                <p class="mt-2 text-sm text-red-600" x-show="error" x-text="error"></p>
            </div>

            <div class="flex items-center justify-end gap-2">
                <button
                    type="button"
                    class="inline-flex h-9 w-9 items-center justify-center text-gray-600 transition hover:text-gray-900 focus:outline-none focus-visible:text-gray-900"
                    x-on:click="sortByTimeToggle()"
                    aria-label="Toggle comment sort order"
                    data-notes-sort-toggle
                >
                    <svg x-show="sortDirection === 'asc'" class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 10.5 12 3m0 0 7.5 7.5M12 3v18" />
                    </svg>
                    <svg x-show="sortDirection === 'desc'" x-cloak class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 13.5 12 21m0 0-7.5-7.5M12 21V3" />
                    </svg>
                </button>

                <button
                    type="submit"
                    class="inline-flex items-center justify-center rounded-md border border-transparent bg-slate-900 px-4 py-2 text-xs font-semibold uppercase tracking-widest text-white transition hover:bg-slate-800 focus:outline-none focus:ring-2 focus:ring-slate-500 focus:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50"
                    x-bind:disabled="saving"
                >
                    {{ __('Comment') }}
                </button>
            </div>
        </form>
    </div>
</x-detail-section-card>
