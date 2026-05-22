<x-resource-detail-layout>
    <x-slot name="header">
        <div>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                {{ __('Make Order') }}
            </h2>
            <p class="mt-1 text-sm text-gray-500">
                {{ $makeOrder->recipe?->name ?? __('Recipe unavailable') }}
            </p>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="mx-auto max-w-4xl space-y-6 sm:px-6 lg:px-8">
            <div class="rounded-lg border border-gray-100 bg-white p-6 shadow-sm">
                <dl class="grid gap-6 sm:grid-cols-2">
                    <div>
                        <dt class="text-sm font-medium text-gray-500">{{ __('Output Item') }}</dt>
                        <dd class="mt-1 text-sm text-gray-900">{{ $makeOrder->outputItem?->name ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-sm font-medium text-gray-500">{{ __('Status') }}</dt>
                        <dd class="mt-1 text-sm text-gray-900">{{ $makeOrder->status }}</dd>
                    </div>
                    <div>
                        <dt class="text-sm font-medium text-gray-500">{{ __('Runs') }}</dt>
                        <dd class="mt-1 text-sm text-gray-900">{{ $runsDisplay }}</dd>
                    </div>
                    <div>
                        <dt class="text-sm font-medium text-gray-500">{{ __('Total Output') }}</dt>
                        <dd class="mt-1 text-sm text-gray-900">{{ $totalOutputQuantityDisplay }}</dd>
                    </div>
                    <div>
                        <dt class="text-sm font-medium text-gray-500">{{ __('Due Date') }}</dt>
                        <dd class="mt-1 text-sm text-gray-900">{{ $makeOrder->due_date?->format('Y-m-d') ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-sm font-medium text-gray-500">{{ __('Made At') }}</dt>
                        <dd class="mt-1 text-sm text-gray-900">{{ $makeOrder->made_at?->format('Y-m-d H:i') ?? '—' }}</dd>
                    </div>
                </dl>
            </div>
        </div>
    </div>
</x-resource-detail-layout>
