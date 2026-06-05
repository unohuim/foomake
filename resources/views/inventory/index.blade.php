<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Inventory') }}
        </h2>
    </x-slot>

    <script type="application/json" id="inventory-index-payload">@json($payload)</script>

    <div
        class="flex h-[calc(100vh-8rem)] min-h-0 flex-col overflow-hidden"
        data-page="inventory-index"
        data-payload="inventory-index-payload"
        data-crud-config='@json($crudConfig)'
        x-data="inventoryIndex"
    >
        <x-ui.toast visible="toast.visible" type="toast.type" message="toast.message" />

        <div class="mx-auto flex h-full min-h-0 w-full max-w-7xl flex-1 flex-col overflow-hidden sm:px-6 lg:px-8">
            <div class="flex h-full min-h-0 flex-1 flex-col" data-crud-root></div>
        </div>
    </div>
</x-app-layout>
