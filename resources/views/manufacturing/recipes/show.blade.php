<!-- resources/views/manufacturing/recipes/show.blade.php -->

<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ __('Recipe') }}</h2>
                <p class="mt-1 text-sm text-gray-500">{{ $recipe->item?->name ?? '—' }}</p>
            </div>
            <a
                href="{{ route('manufacturing.recipes.index') }}"
                class="text-sm text-blue-600 hover:text-blue-500"
            >
                {{ __('Back to Recipes') }}
            </a>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-5xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <div class="bg-white border border-gray-100 shadow-sm sm:rounded-lg">
                <div class="p-6">
                    <h3 class="text-lg font-medium text-gray-900">{{ __('Output Item') }}</h3>
                    <dl class="mt-4 grid grid-cols-1 sm:grid-cols-2 gap-6 text-sm">
                        <div>
                            <dt class="text-sm font-medium text-gray-500">{{ __('Name') }}</dt>
                            <dd class="mt-1 text-gray-900">{{ $recipe->item?->name ?? '—' }}</dd>
                        </div>
                        <div>
                            <dt class="text-sm font-medium text-gray-500">{{ __('Base UoM') }}</dt>
                            <dd class="mt-1 text-gray-900">
                                {{ $recipe->item?->baseUom ? $recipe->item->baseUom->name . ' (' . $recipe->item->baseUom->symbol . ')' : '—' }}
                            </dd>
                        </div>
                    </dl>
                </div>
            </div>

            <div class="bg-white border border-gray-100 shadow-sm sm:rounded-lg">
                <div class="p-6">
                    <h3 class="text-lg font-medium text-gray-900">{{ __('Active') }}</h3>
                    <p class="mt-2 text-sm text-gray-700">{{ $recipe->is_active ? 'Yes' : 'No' }}</p>
                </div>
            </div>

            <div class="bg-white border border-gray-100 shadow-sm sm:rounded-lg">
                <div class="p-6">
                    <h3 class="text-lg font-medium text-gray-900">{{ __('Lines') }}</h3>

                    @if ($recipe->lines->isEmpty())
                        <p class="mt-2 text-sm text-gray-600">{{ __('No recipe lines yet.') }}</p>
                    @else
                        <div class="mt-4 overflow-x-auto">
                            <table class="min-w-full text-sm">
                                <thead class="text-left text-gray-500">
                                    <tr class="border-b border-gray-100">
                                        <th class="px-3 py-2 font-medium">{{ __('Input Item') }}</th>
                                        <th class="px-3 py-2 font-medium">{{ __('Quantity') }}</th>
                                        <th class="px-3 py-2 font-medium">{{ __('UoM') }}</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100">
                                    @foreach ($recipe->lines as $line)
                                        <tr>
                                            <td class="px-3 py-3 text-gray-900">
                                                {{ $line->item?->name ?? '—' }}
                                            </td>
                                            <td class="px-3 py-3 text-gray-600">
                                                {{ number_format((float) $line->quantity, 2, '.', '') }}
                                            </td>
                                            <td class="px-3 py-3 text-gray-600">
                                                {{ $line->item?->baseUom ? $line->item->baseUom->name . ' (' . $line->item->baseUom->symbol . ')' : '—' }}
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
