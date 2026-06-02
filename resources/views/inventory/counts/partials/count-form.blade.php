@props([
    'formVar' => 'form',
    'errorsVar' => 'errors',
    'errorsPrefix' => '',
    'users' => collect(),
    'scopedItem' => null,
    'showCountedQuantity' => false,
])

@php
    $errorsPrefix = $errorsPrefix !== '' ? rtrim($errorsPrefix, '.') : '';
    $errorsPath = $errorsPrefix !== '' ? $errorsVar . '.' . $errorsPrefix : $errorsVar;
@endphp

<x-slide-over-shell
    open="showCountForm"
    close="closeCountForm()"
    submit="submitCountForm()"
    title="{{ __('Inventory Count') }}"
    description="{{ __('Set the counted date and optional notes.') }}"
    title-id="inventory-count-form-slide-over-title"
>
    <div class="space-y-6">
        @if ($scopedItem)
            <div class="rounded-xl border border-slate-200 bg-slate-50 px-4 py-3">
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('Material') }}</p>
                <p class="mt-1 text-sm font-medium text-slate-900">
                    {{ data_get($scopedItem, 'name', '—') }}
                </p>
                <p class="mt-1 text-xs text-slate-500">
                    {{ __('Base UOM') }}: {{ data_get($scopedItem, 'baseUom.symbol', data_get($scopedItem, 'baseUom.name', '—')) }}
                </p>
            </div>
        @endif

        <div class="space-y-2">
            <label class="text-sm font-medium text-gray-700" for="counted_at">
                {{ __('Counted At') }}
            </label>
            <input
                id="counted_at"
                type="datetime-local"
                class="w-full rounded-md border-gray-300 focus:border-blue-500 focus:ring-blue-500"
                x-model="{{ $formVar }}.counted_at"
                x-on:change="handleCountedAtChange($event)"
                x-on:input="handleCountedAtChange($event)"
            />
            <p class="text-sm text-red-600" x-show="{{ $errorsPath }}?.counted_at" x-text="{{ $errorsPath }}?.counted_at?.[0]"></p>
        </div>

        <div class="space-y-2">
            <label class="text-sm font-medium text-gray-700" for="notes">
                {{ __('Notes') }}
            </label>
            <textarea
                id="notes"
                rows="3"
                class="w-full rounded-md border-gray-300 focus:border-blue-500 focus:ring-blue-500"
                x-model="{{ $formVar }}.notes"
            ></textarea>
            <p class="text-sm text-red-600" x-show="{{ $errorsPath }}?.notes" x-text="{{ $errorsPath }}?.notes?.[0]"></p>
        </div>

        <div class="space-y-2">
            <label class="text-sm font-medium text-gray-700" for="assigned_to_user_id">
                {{ __('Assigned User') }}
            </label>
            <select
                id="assigned_to_user_id"
                class="w-full rounded-md border-gray-300 focus:border-blue-500 focus:ring-blue-500"
                x-model="{{ $formVar }}.assigned_to_user_id"
            >
                <option value="">{{ __('Select a user') }}</option>
                @foreach ($users as $user)
                    <option value="{{ data_get($user, 'id') }}">
                        {{ data_get($user, 'name') }} ({{ data_get($user, 'email') }})
                    </option>
                @endforeach
            </select>
            <p class="text-sm text-red-600" x-show="{{ $errorsPath }}?.assigned_to_user_id" x-text="{{ $errorsPath }}?.assigned_to_user_id?.[0]"></p>
        </div>

        @if ($showCountedQuantity)
            <div class="space-y-2">
                <label class="text-sm font-medium text-gray-700" for="counted_quantity">
                    {{ __('Counted Quantity') }}
                </label>
                <input
                    id="counted_quantity"
                    type="text"
                    class="w-full rounded-md border-gray-300 focus:border-blue-500 focus:ring-blue-500"
                    x-model="{{ $formVar }}.counted_quantity"
                />
                <p class="text-sm text-red-600" x-show="{{ $errorsPath }}?.counted_quantity" x-text="{{ $errorsPath }}?.counted_quantity?.[0]"></p>
            </div>
        @endif

        <p class="text-sm text-red-600" x-show="{{ $errorsPath }}?.general" x-text="{{ $errorsPath }}?.general?.[0]"></p>
    </div>

    <x-slot name="footer">
        <button
            type="button"
            class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-md text-xs font-semibold text-gray-700 uppercase tracking-widest hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 transition"
            x-on:click="closeCountForm()"
        >
            {{ __('Cancel') }}
        </button>
        <button
            type="submit"
            class="inline-flex items-center px-4 py-2 bg-blue-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 transition"
        >
            {{ $submitLabel }}
        </button>
    </x-slot>
</x-slide-over-shell>
