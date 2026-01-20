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
                    @click="openCreate()"
                >
                    {{ __('Create Count') }}
                </button>
            @endcan
        </div>
    </x-slot>

    <div
        class="py-12"
        x-data="{
            csrf: document.querySelector('meta[name=csrf-token]').getAttribute('content'),
            showCountForm: false,
            isEditing: false,
            errors: {},
            toast: { show: false, type: 'success', message: '' },
            form: { id: null, counted_at: '', notes: '', action: '', method: 'POST' },
            openCreate() {
                this.isEditing = false;
                this.errors = {};
                this.form = {
                    id: null,
                    counted_at: '',
                    notes: '',
                    action: '{{ route('inventory.counts.store') }}',
                    method: 'POST'
                };
                this.showCountForm = true;
            },
            openEdit(event) {
                const row = event.target.closest('tr');
                if (!row) {
                    return;
                }
                this.isEditing = true;
                this.errors = {};
                this.form = {
                    id: row.dataset.countId,
                    counted_at: row.dataset.countedAtIso,
                    notes: row.dataset.notes || '',
                    action: row.dataset.updateUrl,
                    method: 'PATCH'
                };
                this.showCountForm = true;
            },
            closeCountForm() {
                this.showCountForm = false;
            },
            async submitCountForm() {
                this.errors = {};
                const response = await fetch(this.form.action, {
                    method: this.form.method,
                    headers: {
                        'Accept': 'application/json',
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': this.csrf
                    },
                    body: JSON.stringify({
                        counted_at: this.form.counted_at,
                        notes: this.form.notes
                    })
                });

                const data = await response.json();

                if (!response.ok) {
                    if (response.status === 422) {
                        this.errors = data.errors || { general: [data.message || 'Unable to save count.'] };
                        return;
                    }

                    this.showToast('error', data.message || 'Unable to save count.');
                    return;
                }

                if (this.isEditing) {
                    this.updateRow(data.count);
                } else {
                    this.insertRow(data.count);
                }

                this.showToast('success', 'Inventory count saved.');
                this.closeCountForm();
            },
            async deleteCount(event) {
                const row = event.target.closest('tr');
                if (!row) {
                    return;
                }

                if (!confirm('Delete this inventory count?')) {
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
                    this.showToast('error', data.message || 'Unable to delete count.');
                    return;
                }

                row.remove();
                this.showToast('success', 'Inventory count deleted.');
            },
            insertRow(count) {
                const template = this.$refs.countRowTemplate.content.cloneNode(true);
                const row = template.querySelector('tr');

                this.applyRowData(row, count);

                this.$refs.countsTableBody.prepend(row);
                window.Alpine.initTree(row);
            },
            updateRow(count) {
                const row = this.$refs.countsTableBody.querySelector('[data-count-id=\"' + count.id + '\"]');
                if (!row) {
                    return;
                }

                this.applyRowData(row, count);
            },
            applyRowData(row, count) {
                row.dataset.countId = count.id;
                row.dataset.countedAt = count.counted_at;
                row.dataset.countedAtIso = count.counted_at_iso;
                row.dataset.notes = count.notes || '';
                row.dataset.status = count.status;
                row.dataset.postedAt = count.posted_at_display || '';
                row.dataset.postedAtIso = count.posted_at_iso || '';
                row.dataset.linesCount = count.lines_count;
                row.dataset.showUrl = count.show_url;
                row.dataset.updateUrl = count.update_url;
                row.dataset.deleteUrl = count.delete_url;

                row.querySelector('[data-role=\"counted-at\"]').textContent = count.counted_at;
                row.querySelector('[data-role=\"status\"]').textContent = this.formatStatus(count.status);
                row.querySelector('[data-role=\"lines-count\"]').textContent = count.lines_count;
                row.querySelector('[data-role=\"posted-at\"]').textContent = count.posted_at_display || '—';
                row.querySelector('[data-role=\"show-link\"]').setAttribute('href', count.show_url);
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

                    @if ($counts->isEmpty())
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
                    @else
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
                    @endif
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
</x-app-layout>
