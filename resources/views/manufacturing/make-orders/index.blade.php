<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Make Orders') }}
        </h2>
    </x-slot>

    <script type="application/json" id="manufacturing-make-orders-payload">@json($payload)</script>

    <div
        class="flex h-[calc(100vh-8rem)] min-h-0 flex-col overflow-hidden"
        data-page="manufacturing-make-orders"
        data-payload="manufacturing-make-orders-payload"
        data-crud-config='@json($crudConfig)'
        x-data="manufacturingMakeOrders"
    >
        <div class="fixed top-6 right-6 z-50" x-cloak x-show="toast.visible">
            <div
                class="rounded-md px-4 py-3 text-sm shadow-md"
                :class="toast.type === 'success' ? 'bg-green-50 text-green-700' : 'bg-red-50 text-red-700'"
                x-text="toast.message"
            ></div>
        </div>

        <div class="mx-auto flex h-full min-h-0 w-full max-w-7xl flex-1 flex-col overflow-hidden sm:px-6 lg:px-8">
            <div class="flex h-full min-h-0 flex-1 flex-col" data-crud-root></div>
            @include('manufacturing.make-orders.partials.create-make-order-slide-over')
        </div>
    </div>
</x-app-layout>
