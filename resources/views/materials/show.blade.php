<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">Material</h2>
                <p class="mt-1 text-sm text-gray-500">{{ $item->name }}</p>
            </div>
            <a
                href="{{ route('materials.index') }}"
                class="text-sm text-blue-600 hover:text-blue-500"
            >
                Back to Materials
            </a>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-5xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <div class="bg-white border border-gray-100 shadow-sm sm:rounded-lg">
                <div class="p-6">
                    <h3 class="text-lg font-medium text-gray-900">Core fields</h3>
                    <dl class="mt-4 grid grid-cols-1 sm:grid-cols-2 gap-6">
                        <div>
                            <dt class="text-sm font-medium text-gray-500">Name</dt>
                            <dd class="mt-1 text-sm text-gray-900">{{ $item->name }}</dd>
                        </div>
                    </dl>
                </div>
            </div>

            <div class="bg-white border border-gray-100 shadow-sm sm:rounded-lg">
                <div class="p-6">
                    <h3 class="text-lg font-medium text-gray-900">Flags</h3>
                    <dl class="mt-4 grid grid-cols-1 sm:grid-cols-3 gap-6 text-sm">
                        <div>
                            <dt class="text-sm font-medium text-gray-500">Purchasable</dt>
                            <dd class="mt-1 text-gray-900">{{ $item->is_purchasable ? 'Yes' : 'No' }}</dd>
                        </div>
                        <div>
                            <dt class="text-sm font-medium text-gray-500">Sellable</dt>
                            <dd class="mt-1 text-gray-900">{{ $item->is_sellable ? 'Yes' : 'No' }}</dd>
                        </div>
                        <div>
                            <dt class="text-sm font-medium text-gray-500">Manufacturable</dt>
                            <dd class="mt-1 text-gray-900">{{ $item->is_manufacturable ? 'Yes' : 'No' }}</dd>
                        </div>
                    </dl>
                </div>
            </div>

            <div class="bg-white border border-gray-100 shadow-sm sm:rounded-lg">
                <div class="p-6">
                    <h3 class="text-lg font-medium text-gray-900">Base UoM</h3>
                    <p class="mt-2 text-sm text-gray-700">
                        {{ $item->baseUom ? $item->baseUom->name . ' (' . $item->baseUom->symbol . ')' : '—' }}
                    </p>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
