<x-app-layout>
    <x-slot name="header">
        <h2 class="pt-6 font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Inventory Counts') }}
        </h2>
    </x-slot>

    <script type="application/json" id="inventory-counts-index-payload">@json($payload)</script>

    <div
        class="flex h-[calc(100vh-8rem)] min-h-0 flex-col overflow-hidden"
        data-page="inventory-counts-index"
        data-payload="inventory-counts-index-payload"
        data-crud-config='@json($crudConfig)'
        x-data="inventoryCountsIndex"
        @open-create-inventory-count.window="openCreate()"
    >
        <x-ui.toast visible="toast.show" type="toast.type" message="toast.message" />

        <div class="mx-auto flex h-full min-h-0 w-full max-w-7xl flex-1 flex-col overflow-hidden sm:px-6 lg:px-8">
            <div class="flex h-full min-h-0 flex-1 flex-col" data-crud-root></div>

            @include('inventory.counts.partials.count-form', [
                'submitLabel' => __('Save Count'),
                'users' => $users,
            ])
        </div>
    </div>
</x-app-layout>
