<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Recipes') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900">
                    @if ($recipes->isEmpty())
                        <p class="text-sm text-gray-600">{{ __('No recipes have been created yet.') }}</p>
                    @else
                        <div class="overflow-x-auto">
                            <table class="min-w-full text-sm">
                                <thead class="text-left text-gray-500">
                                    <tr class="border-b border-gray-100">
                                        <th class="px-3 py-2 font-medium">{{ __('Output Item') }}</th>
                                        <th class="px-3 py-2 font-medium">{{ __('Active') }}</th>
                                        <th class="px-3 py-2 font-medium">{{ __('Updated') }}</th>
                                        <th class="px-3 py-2 text-right font-medium">{{ __('View') }}</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100">
                                    @foreach ($recipes as $recipe)
                                        <tr>
                                            <td class="px-3 py-3 text-gray-900">
                                                {{ $recipe->item?->name ?? '—' }}
                                            </td>
                                            <td class="px-3 py-3 text-gray-600">
                                                {{ $recipe->is_active ? 'Yes' : 'No' }}
                                            </td>
                                            <td class="px-3 py-3 text-gray-600">
                                                {{ $recipe->updated_at?->format('Y-m-d H:i') ?? '—' }}
                                            </td>
                                            <td class="px-3 py-3 text-right">
                                                <a
                                                    href="{{ route('manufacturing.recipes.show', $recipe) }}"
                                                    class="text-sm text-blue-600 hover:text-blue-500"
                                                >
                                                    {{ __('View') }}
                                                </a>
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
