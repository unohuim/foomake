<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Inventory') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900">
                    @if ($items->isEmpty())
                        <p class="text-sm text-gray-600">
                            {{ __('No inventory items available.') }}
                        </p>
                    @else
                        <div class="overflow-x-auto">
                            <table class="min-w-full text-sm">
                                <thead class="text-left text-gray-500">
                                    <tr class="border-b border-gray-100">
                                        <th class="px-3 py-2 font-medium">{{ __('Item') }}</th>
                                        <th class="px-3 py-2 font-medium">{{ __('Base UoM') }}</th>
                                        <th class="px-3 py-2 text-right font-medium">{{ __('On-hand') }}</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100">
                                    @foreach ($items as $item)
                                        <tr>
                                            <td class="px-3 py-3 text-gray-900">
                                                {{ $item->name }}
                                            </td>
                                            <td class="px-3 py-3 text-gray-600">
                                                {{ $item->baseUom->name }} ({{ $item->baseUom->symbol }})
                                            </td>
                                            <td class="px-3 py-3 text-right text-gray-900">
                                                {{ $item->onHandQuantity() }}
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
    </div>
</x-app-layout>
