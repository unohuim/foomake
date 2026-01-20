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

    <div
        class="py-12"
        x-data="{
            csrf: document.querySelector('meta[name=csrf-token]').getAttribute('content'),
            showCountForm: false,
            showLineForm: false,
            showPostConfirm: false,
            errors: { count: {}, line: {}, post: '' },
            toast: { show: false, type: 'success', message: '' },
            count: {
                id: {{ $inventoryCount->id }},
                status: '{{ $inventoryCount->status }}',
                countedAt: '{{ $inventoryCount->counted_at->format('Y-m-d H:i') }}',
                countedAtIso: '{{ $inventoryCount->counted_at->format('Y-m-d\TH:i') }}',
                postedAt: '{{ $inventoryCount->posted_at?->format('Y-m-d H:i') ?? '' }}',
                postUrl: '{{ route('inventory.counts.post', $inventoryCount) }}',
                updateUrl: '{{ route('inventory.counts.update', $inventoryCount) }}',
                deleteUrl: '{{ route('inventory.counts.destroy', $inventoryCount) }}'
            },
            lineCount: {{ $inventoryCount->lines_count }},
            countForm: { counted_at: '', notes: '', method: 'PATCH' },
            lineForm: { id: null, item_id: '', counted_quantity: '', notes: '', action: '', method: 'POST' },
            openEditCount() {
                this.errors.count = {};
                this.countForm = {
                    counted_at: this.$refs.countMeta.dataset.countedAtIso,
                    notes: this.$refs.countMeta.dataset.notes || '',
                    method: 'PATCH'
                };
                this.showCountForm = true;
                this.showLineForm = false;
                this.showPostConfirm = false;
            },
            closeCountForm() {
                this.showCountForm = false;
            },
            async submitCountForm() {
                this.errors.count = {};
                const response = await fetch(this.count.updateUrl, {
                    method: 'PATCH',
                    headers: {
                        'Accept': 'application/json',
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': this.csrf
                    },
                    body: JSON.stringify({
                        counted_at: this.countForm.counted_at,
                        notes: this.countForm.notes
                    })
                });

                const data = await response.json();

                if (!response.ok) {
                    if (response.status === 422) {
                        this.errors.count = data.errors || { general: [data.message || 'Unable to save count.'] };
                        return;
                    }

                    this.showToast('error', data.message || 'Unable to save count.');
                    return;
                }

                this.updateCountDetails(data.count);
                this.showToast('success', 'Inventory count updated.');
                this.closeCountForm();
            },
            openCreateLine() {
                this.errors.line = {};
                this.lineForm = {
                    id: null,
                    item_id: '',
                    counted_quantity: '',
                    notes: '',
                    action: '{{ route('inventory.counts.lines.store', $inventoryCount) }}',
                    method: 'POST'
                };
                this.showLineForm = true;
                this.showCountForm = false;
                this.showPostConfirm = false;
            },
            openEditLine(event) {
                const row = event.target.closest('tr');
                if (!row) {
                    return;
                }

                this.errors.line = {};
                this.lineForm = {
                    id: row.dataset.lineId,
                    item_id: row.dataset.itemId,
                    counted_quantity: row.dataset.countedQuantity,
                    notes: row.dataset.notes || '',
                    action: row.dataset.updateUrl,
                    method: 'PATCH'
                };
                this.showLineForm = true;
                this.showCountForm = false;
                this.showPostConfirm = false;
            },
            closeLineForm() {
                this.showLineForm = false;
            },
            async submitLineForm() {
                this.errors.line = {};
                const response = await fetch(this.lineForm.action, {
                    method: this.lineForm.method,
                    headers: {
                        'Accept': 'application/json',
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': this.csrf
                    },
                    body: JSON.stringify({
                        item_id: this.lineForm.item_id,
                        counted_quantity: this.lineForm.counted_quantity,
                        notes: this.lineForm.notes
                    })
                });

                const data = await response.json();

                if (!response.ok) {
                    if (response.status === 422) {
                        this.errors.line = data.errors || { general: [data.message || 'Unable to save line.'] };
                        return;
                    }

                    this.showToast('error', data.message || 'Unable to save line.');
                    return;
                }

                if (this.lineForm.method === 'POST') {
                    this.insertLineRow(data.line);
                    this.lineCount = this.lineCount + 1;
                } else {
                    this.updateLineRow(data.line);
                }

                this.updateLineCountDisplay();
                this.showToast('success', 'Inventory line saved.');
                this.closeLineForm();
            },
            async deleteLine(event) {
                const row = event.target.closest('tr');
                if (!row) {
                    return;
                }

                if (!confirm('Delete this line?')) {
                    return;
                }

                const response = await fetch(row.dataset.deleteUrl, {
                    method: 'DELETE',
                    headers: {
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': this.csrf
                    }
                });

                const data = await response.json();

                if (!response.ok) {
                    this.showToast('error', data.message || 'Unable to delete line.');
                    return;
                }

                row.remove();
                this.lineCount = Math.max(this.lineCount - 1, 0);
                this.updateLineCountDisplay();
                this.showToast('success', 'Inventory line deleted.');
            },
            openPostConfirm() {
                this.errors.post = '';
                this.showPostConfirm = true;
                this.showCountForm = false;
                this.showLineForm = false;
            },
            closePostConfirm() {
                this.showPostConfirm = false;
            },
            async confirmPost() {
                this.errors.post = '';
                const response = await fetch(this.count.postUrl, {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': this.csrf
                    }
                });

                const data = await response.json();

                if (!response.ok) {
                    this.errors.post = data.message || 'Unable to post count.';
                    return;
                }

                this.updateCountDetails(data.count);
                this.count.status = data.count.status;
                this.showPostConfirm = false;
                this.showToast('success', 'Inventory count posted.');
            },
            updateCountDetails(count) {
                this.count.countedAt = count.counted_at;
                this.count.countedAtIso = count.counted_at_iso;
                this.count.postedAt = count.posted_at_display || '';
                this.$refs.countedAtDisplay.textContent = count.counted_at;
                this.$refs.statusDisplay.textContent = this.formatStatus(count.status);
                this.$refs.postedAtDisplay.textContent = count.posted_at_display || '—';
                this.$refs.countMeta.dataset.countedAtIso = count.counted_at_iso;
                this.$refs.countMeta.dataset.notes = count.notes || '';
                this.$refs.countMeta.dataset.status = count.status;
                this.toggleDraftActions(count.status === 'draft');
            },
            toggleDraftActions(isDraft) {
                if (this.$refs.draftActions) {
                    this.$refs.draftActions.style.display = isDraft ? 'flex' : 'none';
                }
                if (this.$refs.lineActions) {
                    this.$refs.lineActions.style.display = isDraft ? 'block' : 'none';
                }
            },
            insertLineRow(line) {
                const template = this.$refs.lineRowTemplate.content.cloneNode(true);
                const row = template.querySelector('tr');

                this.applyLineData(row, line);

                this.$refs.linesTableBody.prepend(row);
                window.Alpine.initTree(row);
            },
            updateLineRow(line) {
                const row = this.$refs.linesTableBody.querySelector('[data-line-id=\"' + line.id + '\"]');
                if (!row) {
                    return;
                }

                this.applyLineData(row, line);
            },
            applyLineData(row, line) {
                row.dataset.lineId = line.id;
                row.dataset.itemId = line.item_id;
                row.dataset.countedQuantity = line.counted_quantity;
                row.dataset.notes = line.notes || '';
                row.dataset.updateUrl = line.update_url;
                row.dataset.deleteUrl = line.delete_url;

                row.querySelector('[data-role=\"item-display\"]').textContent = line.item_display;
                row.querySelector('[data-role=\"counted-quantity\"]').textContent = line.counted_quantity;
                row.querySelector('[data-role=\"notes\"]').textContent = line.notes || '—';
            },
            updateLineCountDisplay() {
                this.$refs.lineCountDisplay.textContent = this.lineCount;
                if (this.lineCount === 0 && this.$refs.linesEmptyState) {
                    this.$refs.linesEmptyState.style.display = 'block';
                }
                if (this.lineCount > 0 && this.$refs.linesEmptyState) {
                    this.$refs.linesEmptyState.style.display = 'none';
                }
            },
            formatStatus(status) {
                return status === 'posted' ? 'Posted' : 'Draft';
            },
            showToast(type, message) {
                this.toast = { show: true, type: type, message: message };
                setTimeout(() => {
                    this.toast.show = false;
                }, 2500);
            }
        }"
    >
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <div class="bg-white shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900 space-y-4">
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

                    <div x-ref="countMeta"
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
                    </td>
                </tr>
            </template>
        @endcan

        @include('inventory.counts.partials.count-form', [
            'submitLabel' => __('Save Count'),
        ])

        @include('inventory.counts.partials.line-form', [
            'items' => $items,
        ])

        @include('inventory.counts.partials.post-confirm')
    </div>
</x-app-layout>
