<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                {{ __('Inventory Counts') }}
            </h2>

            @can('inventory-adjustments-execute')
                <button
                    type="button"
                    class="inline-flex items-center px-4 py-2 bg-blue-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 transition"
                    @click="setCreateHash()"
                >
                    {{ __('Create Count') }}
                </button>
            @endcan
        </div>
    </x-slot>

    <div
        class="py-12"
        data-page="inventory-counts-index"
        data-payload="inventory-counts-index-payload"
        x-data="inventoryCountsIndex"
        x-init="init()"
        @open-create-inventory-count.window="openCreate()"
    >
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900 space-y-6">
                    <div
                        x-cloak
                        x-show="toast.show"
                        class="fixed top-5 right-5 z-50"
                    >
                        <div
                            class="px-4 py-2 rounded-md text-sm text-white"
                            :class="toast.type === 'success' ? 'bg-green-600' : 'bg-red-600'"
                        >
                            <span x-text="toast.message"></span>
                        </div>
                    </div>

                    <div x-ref="emptyStateContainer" class="{{ $counts->isEmpty() ? '' : 'hidden' }}">
                        <div class="text-sm text-gray-600 space-y-4">
                            <p>{{ __('No inventory counts yet.') }}</p>

                            @can('inventory-adjustments-execute')
                                <button
                                    type="button"
                                    class="inline-flex items-center px-4 py-2 bg-blue-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 transition"
                                    @click="openCreate()"
                                >
                                    {{ __('Create Inventory Count') }}
                                </button>
                            @endcan
                        </div>
                    </div>

                    <div x-ref="countsTableContainer" class="{{ $counts->isEmpty() ? 'hidden' : '' }}">
                        <div class="overflow-x-auto">
                            <table class="min-w-full text-sm">
                                <thead class="text-left text-gray-500">
                                    <tr class="border-b border-gray-100">
                                        <th class="px-3 py-2 font-medium">{{ __('Counted At') }}</th>
                                        <th class="px-3 py-2 font-medium">{{ __('Status') }}</th>
                                        <th class="px-3 py-2 font-medium">{{ __('Items') }}</th>
                                        <th class="px-3 py-2 font-medium">{{ __('Posted At') }}</th>
                                        <th class="px-3 py-2 text-right font-medium">{{ __('Actions') }}</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100" x-ref="countsTableBody">
                                    @foreach ($counts as $count)
                                        <tr
                                            data-count-id="{{ $count->id }}"
                                            data-counted-at="{{ $count->counted_at->format('Y-m-d H:i') }}"
                                            data-counted-at-iso="{{ $count->counted_at->format('Y-m-d\TH:i') }}"
                                            data-notes="{{ $count->notes ?? '' }}"
                                            data-status="{{ $count->status }}"
                                            data-posted-at="{{ $count->posted_at?->format('Y-m-d H:i') ?? '' }}"
                                            data-posted-at-iso="{{ $count->posted_at?->format('Y-m-d\TH:i') ?? '' }}"
                                            data-lines-count="{{ $count->lines_count }}"
                                            data-show-url="{{ route('inventory.counts.show', $count) }}"
                                            data-update-url="{{ route('inventory.counts.update', $count) }}"
                                            data-delete-url="{{ route('inventory.counts.destroy', $count) }}"
                                        >
                                            <td class="px-3 py-3 text-gray-900" data-role="counted-at">
                                                {{ $count->counted_at->format('Y-m-d H:i') }}
                                            </td>
                                            <td class="px-3 py-3 text-gray-600" data-role="status">
                                                {{ ucfirst($count->status) }}
                                            </td>
                                            <td class="px-3 py-3 text-gray-600" data-role="lines-count">
                                                {{ $count->lines_count }}
                                            </td>
                                            <td class="px-3 py-3 text-gray-600" data-role="posted-at">
                                                {{ $count->posted_at?->format('Y-m-d H:i') ?? '—' }}
                                            </td>
                                            <td class="px-3 py-3 text-right space-x-3">
                                                <a
                                                    class="text-blue-600 hover:text-blue-500"
                                                    data-role="show-link"
                                                    href="{{ route('inventory.counts.show', $count) }}"
                                                >
                                                    {{ __('View') }}
                                                </a>

                                                @can('inventory-adjustments-execute')
                                                    @if ($count->status === 'draft')
                                                        <button
                                                            type="button"
                                                            class="text-gray-700 hover:text-gray-900"
                                                            @click="openEdit($event)"
                                                        >
                                                            {{ __('Edit') }}
                                                        </button>
                                                        <button
                                                            type="button"
                                                            class="text-red-600 hover:text-red-500"
                                                            @click="deleteCount($event)"
                                                        >
                                                            {{ __('Delete') }}
                                                        </button>
                                                    @endif
                                                @endcan
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        @can('inventory-adjustments-execute')
            <template x-ref="countRowTemplate">
                <tr
                    data-count-id=""
                    data-counted-at=""
                    data-counted-at-iso=""
                    data-notes=""
                    data-status=""
                    data-posted-at=""
                    data-posted-at-iso=""
                    data-lines-count=""
                    data-show-url=""
                    data-update-url=""
                    data-delete-url=""
                >
                    <td class="px-3 py-3 text-gray-900" data-role="counted-at"></td>
                    <td class="px-3 py-3 text-gray-600" data-role="status"></td>
                    <td class="px-3 py-3 text-gray-600" data-role="lines-count"></td>
                    <td class="px-3 py-3 text-gray-600" data-role="posted-at"></td>
                    <td class="px-3 py-3 text-right space-x-3">
                        <a class="text-blue-600 hover:text-blue-500" data-role="show-link">
                            {{ __('View') }}
                        </a>
                        <button
                            type="button"
                            class="text-gray-700 hover:text-gray-900"
                            @click="openEdit($event)"
                        >
                            {{ __('Edit') }}
                        </button>
                        <button
                            type="button"
                            class="text-red-600 hover:text-red-500"
                            @click="deleteCount($event)"
                        >
                            {{ __('Delete') }}
                        </button>
                    </td>
                </tr>
            </template>
        @endcan

        @include('inventory.counts.partials.count-form', [
            'submitLabel' => __('Save Count'),
        ])
    </div>

    <script type="application/json" id="inventory-counts-index-payload">
        @json($payload)
    </script>
</x-app-layout>
