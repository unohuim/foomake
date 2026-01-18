<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Materials') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            @if ($items->isEmpty())
                <div class="bg-white border border-gray-100 shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <h3 class="text-lg font-medium text-gray-900">No materials yet</h3>
                        <p class="mt-2 text-sm text-gray-600">
                            Materials will appear here once you add them.
                        </p>
                        <div class="mt-4">
                            <button type="button" class="inline-flex items-center px-4 py-2 bg-blue-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 transition ease-in-out duration-150">
                                Create Material
                            </button>
                        </div>
                    </div>
                </div>
            @else
                <div class="bg-white border border-gray-100 shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-100">
                                <thead>
                                    <tr>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Name
                                        </th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Base UoM
                                        </th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Flags
                                        </th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100">
                                    @foreach ($items as $item)
                                        <tr>
                                            <td class="px-4 py-4 text-sm text-gray-900">
                                                {{ $item->name }}
                                            </td>
                                            <td class="px-4 py-4 text-sm text-gray-700">
                                                {{ $item->baseUom->name }} ({{ $item->baseUom->symbol }})
                                            </td>
                                            <td class="px-4 py-4 text-sm text-gray-700">
                                                <div class="flex flex-wrap gap-2 text-xs text-gray-600">
                                                    @if ($item->is_purchasable)
                                                        <span class="px-2 py-1 bg-gray-100 rounded-full">Purchasable</span>
                                                    @endif
                                                    @if ($item->is_sellable)
                                                        <span class="px-2 py-1 bg-gray-100 rounded-full">Sellable</span>
                                                    @endif
                                                    @if ($item->is_manufacturable)
                                                        <span class="px-2 py-1 bg-gray-100 rounded-full">Manufacturable</span>
                                                    @endif
                                                    @if (! $item->is_purchasable && ! $item->is_sellable && ! $item->is_manufacturable)
                                                        <span class="text-gray-400">—</span>
                                                    @endif
                                                </div>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
