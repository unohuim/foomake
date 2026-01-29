{{-- resources/views/inventory/counts/show.blade.php --}}
<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <div>
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                    {{ __('Inventory Count') }}
                </h2>
                <p class="text-sm text-gray-500">
                    {{ $inventoryCount->counted_at->format('Y-m-d H:i') }}
                </p>
            </div>

            <div class="flex items-center gap-3">
                @can('inventory-adjustments-execute')
                    @if ($inventoryCount->status === 'draft')
                        <button
                            type="button"
                            class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-md text-xs font-semibold text-gray-700 uppercase tracking-widest hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 transition"
                            @click="$dispatch('inventory-count-open-edit')"
                        >
                            {{ __('Edit Count') }}
                        </button>
                        <button
                            type="button"
                            class="inline-flex items-center px-4 py-2 bg-blue-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 transition"
                            @click="$dispatch('inventory-count-open-post')"
                        >
                            {{ __('Post Count') }}
                        </button>
                    @endif
                @endcan

                <a
                    href="{{ route('inventory.counts.index') }}"
                    class="text-sm text-gray-600 hover:text-gray-900"
                >
                    {{ __('Back to Counts') }}
                </a>
            </div>
        </div>
    </x-slot>

    {{-- Safe payload: no JS inside attributes --}}
    @php
        $payload = [
            'count' => [
                'id' => $inventoryCount->id,
                'status' => $inventoryCount->status,
                'countedAt' => $inventoryCount->counted_at->format('Y-m-d H:i'),
                'countedAtIso' => $inventoryCount->counted_at->format('Y-m-d\TH:i'),
                'postedAt' => $inventoryCount->posted_at?->format('Y-m-d H:i') ?? '',
                'postUrl' => route('inventory.counts.post', $inventoryCount),
                'updateUrl' => route('inventory.counts.update', $inventoryCount),
                'deleteUrl' => route('inventory.counts.destroy', $inventoryCount),
            ],
            'lineCount' => $inventoryCount->lines_count,
            'lineCreateUrl' => route('inventory.counts.lines.store', $inventoryCount),
        ];
    @endphp

    <script type="application/json" id="inventory-count-show-payload">@json($payload)</script>

    <div
        class="py-12"
        data-page="inventory-count-show"
        data-payload="inventory-count-show-payload"
        x-data="inventoryCountShow"
        @inventory-count-open-edit.window="openEditCount()"
        @inventory-count-open-post.window="openPostConfirm()"
    >
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <div class="bg-white shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900 space-y-4">
                    <div x-cloak x-show="toast.show" class="fixed top-5 right-5 z-50">
                        <div
                            class="px-4 py-2 rounded-md text-sm text-white"
                            :class="toast.type === 'success' ? 'bg-green-600' : 'bg-red-600'"
                        >
                            <span x-text="toast.message"></span>
                        </div>
                    </div>

                    <div
                        x-ref="countMeta"
                        data-counted-at-iso="{{ $inventoryCount->counted_at->format('Y-m-d\TH:i') }}"
                        data-notes="{{ $inventoryCount->notes ?? '' }}"
                        data-status="{{ $inventoryCount->status }}"
                    ></div>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <p class="text-xs uppercase text-gray-500">{{ __('Status') }}</p>
                            <p class="text-sm text-gray-900" x-ref="statusDisplay">
                                {{ ucfirst($inventoryCount->status) }}
                            </p>
                        </div>
                        <div>
                            <p class="text-xs uppercase text-gray-500">{{ __('Counted At') }}</p>
                            <p class="text-sm text-gray-900" x-ref="countedAtDisplay">
                                {{ $inventoryCount->counted_at->format('Y-m-d H:i') }}
                            </p>
                        </div>
                        <div>
                            <p class="text-xs uppercase text-gray-500">{{ __('Posted At') }}</p>
                            <p class="text-sm text-gray-900" x-ref="postedAtDisplay">
                                {{ $inventoryCount->posted_at?->format('Y-m-d H:i') ?? '—' }}
                            </p>
                        </div>
                        <div>
                            <p class="text-xs uppercase text-gray-500">{{ __('Line Items') }}</p>
                            <p class="text-sm text-gray-900" x-ref="lineCountDisplay">
                                {{ $inventoryCount->lines_count }}
                            </p>
                        </div>
                        <div class="sm:col-span-2">
                            <p class="text-xs uppercase text-gray-500">{{ __('Notes') }}</p>
                            <p class="text-sm text-gray-900">
                                {{ $inventoryCount->notes ?: __('No notes') }}
                            </p>
                        </div>
                    </div>

                    @can('inventory-adjustments-execute')
                        @if ($inventoryCount->status === 'draft')
                            <div class="flex gap-3" x-ref="draftActions">
                                <button
                                    type="button"
                                    class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-md text-xs font-semibold text-gray-700 uppercase tracking-widest hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 transition"
                                    @click="openEditCount()"
                                >
                                    {{ __('Edit Count') }}
                                </button>
                                <button
                                    type="button"
                                    class="inline-flex items-center px-4 py-2 bg-blue-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 transition"
                                    @click="openPostConfirm()"
                                >
                                    {{ __('Post Count') }}
                                </button>
                            </div>
                        @endif
                    @endcan
                </div>
            </div>

            <div class="bg-white shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900 space-y-4">
                    <div class="flex items-center justify-between">
                        <h3 class="text-sm font-semibold text-gray-800">{{ __('Count Lines') }}</h3>

                        @can('inventory-adjustments-execute')
                            @if ($inventoryCount->status === 'draft')
                                <button
                                    type="button"
                                    class="inline-flex items-center px-4 py-2 bg-blue-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 transition"
                                    @click="openCreateLine()"
                                    x-ref="lineActions"
                                >
                                    {{ __('Add Line') }}
                                </button>
                            @endif
                        @endcan
                    </div>

                    <div
                        class="text-sm text-gray-600"
                        x-ref="linesEmptyState"
                        style="{{ $inventoryCount->lines->isEmpty() ? '' : 'display: none;' }}"
                    >
                        <p>{{ __('No count lines yet.') }}</p>
                    </div>

                    @if ($inventoryCount->lines->isNotEmpty())
                        <div class="overflow-x-auto">
                            <table class="min-w-full text-sm">
                                <thead class="text-left text-gray-500">
                                    <tr class="border-b border-gray-100">
                                        <th class="px-3 py-2 font-medium">{{ __('Item') }}</th>
                                        <th class="px-3 py-2 font-medium">{{ __('Counted Quantity') }}</th>
                                        <th class="px-3 py-2 font-medium">{{ __('Notes') }}</th>
                                        <th class="px-3 py-2 text-right font-medium">{{ __('Actions') }}</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100" x-ref="linesTableBody">
                                    @foreach ($inventoryCount->lines as $line)
                                        <tr
                                            data-line-id="{{ $line->id }}"
                                            data-item-id="{{ $line->item_id }}"
                                            data-counted-quantity="{{ $line->counted_quantity }}"
                                            data-notes="{{ $line->notes ?? '' }}"
                                            data-update-url="{{ route('inventory.counts.lines.update', [$inventoryCount, $line]) }}"
                                            data-delete-url="{{ route('inventory.counts.lines.destroy', [$inventoryCount, $line]) }}"
                                        >
                                            <td class="px-3 py-3 text-gray-900" data-role="item-display">
                                                {{ $line->item->name }} ({{ $line->item->baseUom->symbol }})
                                            </td>
                                            <td class="px-3 py-3 text-gray-600" data-role="counted-quantity">
                                                {{ $line->counted_quantity }}
                                            </td>
                                            <td class="px-3 py-3 text-gray-600" data-role="notes">
                                                {{ $line->notes ?? '—' }}
                                            </td>
                                            <td class="px-3 py-3 text-right space-x-3">
                                                @can('inventory-adjustments-execute')
                                                    @if ($inventoryCount->status === 'draft')
                                                        <button
                                                            type="button"
                                                            class="text-gray-700 hover:text-gray-900"
                                                            @click="openEditLine($event)"
                                                        >
                                                            {{ __('Edit') }}
                                                        </button>
                                                        <button
                                                            type="button"
                                                            class="text-red-600 hover:text-red-500"
                                                            @click="deleteLine($event)"
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
                    @else
                        <div class="overflow-x-auto" style="display: none;">
                            <table class="min-w-full text-sm">
                                <thead class="text-left text-gray-500">
                                    <tr class="border-b border-gray-100">
                                        <th class="px-3 py-2 font-medium">{{ __('Item') }}</th>
                                        <th class="px-3 py-2 font-medium">{{ __('Counted Quantity') }}</th>
                                        <th class="px-3 py-2 font-medium">{{ __('Notes') }}</th>
                                        <th class="px-3 py-2 text-right font-medium">{{ __('Actions') }}</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100" x-ref="linesTableBody"></tbody>
                            </table>
                        </div>
                    @endif
                </div>
            </div>
        </div>

        @can('inventory-adjustments-execute')
            <template x-ref="lineRowTemplate">
                <tr
                    data-line-id=""
                    data-item-id=""
                    data-counted-quantity=""
                    data-notes=""
                    data-update-url=""
                    data-delete-url=""
                >
                    <td class="px-3 py-3 text-gray-900" data-role="item-display"></td>
                    <td class="px-3 py-3 text-gray-600" data-role="counted-quantity"></td>
                    <td class="px-3 py-3 text-gray-600" data-role="notes"></td>
                    <td class="px-3 py-3 text-right space-x-3">
                        <button type="button" class="text-gray-700 hover:text-gray-900" @click="openEditLine($event)">
                            {{ __('Edit') }}
                        </button>
                        <button type="button" class="text-red-600 hover:text-red-500" @click="deleteLine($event)">
                            {{ __('Delete') }}
                        </button>
                    </td>
                </tr>
            </template>
        @endcan

        @include('inventory.counts.partials.count-form', [
            'formVar' => 'countForm',
            'errorsVar' => 'errors',
            'errorsPrefix' => 'count.',
            'submitLabel' => __('Save Count'),
        ])

        @include('inventory.counts.partials.line-form', [
            'items' => $items,
        ])

        @include('inventory.counts.partials.post-confirm')
    </div>
</x-app-layout>
