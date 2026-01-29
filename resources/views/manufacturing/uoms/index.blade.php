<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Units of Measure') }}
        </h2>
    </x-slot>

    @php
        $payload = [
            'categories' => $categories,
            'storeUrl' => route('manufacturing.uoms.store'),
            'updateUrlTemplate' => route('manufacturing.uoms.update', ['uom' => '__ID__']),
            'deleteUrlTemplate' => route('manufacturing.uoms.destroy', ['uom' => '__ID__']),
            'csrfToken' => csrf_token(),
        ];
    @endphp

    <script type="application/json" id="manufacturing-uoms-index-payload">@json($payload)</script>

    <div
        class="py-8"
        data-page="manufacturing-uoms-index"
        data-payload="manufacturing-uoms-index-payload"
        x-data="manufacturingUomsIndex"
        x-init="init()"
    >
        <div class="fixed top-6 right-6 z-50" x-show="toastVisible" x-cloak>
            <div class="rounded-md bg-green-50 border border-green-200 px-4 py-3 shadow-sm">
                <p class="text-sm text-green-700" x-text="toastMessage"></p>
            </div>
        </div>

        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900 space-y-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <h3 class="text-lg font-medium text-gray-900">{{ __('Units of Measure') }}</h3>
                            <p class="text-sm text-gray-500">{{ __('Maintain the units used in manufacturing and inventory.') }}</p>
                        </div>
                        <button type="button" class="inline-flex items-center px-4 py-2 bg-blue-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 transition" @click="openCreate" x-show="hasUoms()" x-cloak>
                            {{ __('Create Unit') }}
                        </button>
                    </div>

                    <div x-show="errorMessage" class="rounded-md bg-red-50 p-4">
                        <p class="text-sm text-red-700" x-text="errorMessage"></p>
                    </div>

                    <div x-show="!hasUoms()" class="rounded-md border border-dashed border-gray-200 p-8 text-center">
                        <h4 class="text-sm font-medium text-gray-900">{{ __('No units of measure yet') }}</h4>
                        <p class="mt-1 text-sm text-gray-500">{{ __('Create your first unit to begin assigning quantities.') }}</p>
                        <div class="mt-4">
                            <button type="button" class="inline-flex items-center px-4 py-2 bg-blue-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 transition" @click="openCreate">
                                {{ __('Create Unit') }}
                            </button>
                        </div>
                    </div>

                    <div class="space-y-6" x-show="hasUoms()">
                        <template x-for="category in categories" :key="category.id">
                            <div x-show="category.uoms.length > 0" class="space-y-3">
                                <div>
                                    <h4 class="text-sm font-semibold text-gray-900" x-text="category.name"></h4>
                                    <p class="text-xs text-gray-500">{{ __('Units grouped by category.') }}</p>
                                </div>
                                <div class="overflow-hidden border border-gray-100 rounded-lg">
                                    <table class="min-w-full divide-y divide-gray-100">
                                        <thead class="bg-gray-50">
                                            <tr>
                                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                    {{ __('Name') }}
                                                </th>
                                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                    {{ __('Symbol') }}
                                                </th>
                                                <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                    {{ __('Actions') }}
                                                </th>
                                            </tr>
                                        </thead>
                                        <tbody class="bg-white divide-y divide-gray-100">
                                            <template x-for="uom in category.uoms" :key="uom.id">
                                                <tr>
                                                    <td class="px-6 py-4 text-sm text-gray-900" x-text="uom.name"></td>
                                                    <td class="px-6 py-4 text-sm text-gray-600" x-text="uom.symbol"></td>
                                                    <td class="px-6 py-4 text-right text-sm">
                                                        <button type="button" class="text-blue-600 hover:text-blue-500 mr-4" @click="openEdit(uom)">
                                                            {{ __('Edit') }}
                                                        </button>
                                                        <button type="button" class="text-red-600 hover:text-red-500" @click="openDelete(uom)">
                                                            {{ __('Delete') }}
                                                        </button>
                                                    </td>
                                                </tr>
                                            </template>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </template>
                    </div>

                    <div x-show="formOpen" class="fixed inset-0 z-50 flex items-start justify-end">
                        <div class="fixed inset-0 bg-gray-900/50" @click="closeForm"></div>
                        <div class="relative bg-white shadow-xl w-full max-w-md h-full p-6 overflow-y-auto">
                            <div class="flex items-center justify-between">
                                <h4 class="text-lg font-medium text-gray-900" x-text="isEditing ? '{{ __('Edit Unit of Measure') }}' : '{{ __('Create Unit of Measure') }}'"></h4>
                                <button type="button" class="text-gray-400 hover:text-gray-500" @click="closeForm">
                                    <span class="sr-only">{{ __('Close') }}</span>
                                    <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                                        <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd" />
                                    </svg>
                                </button>
                            </div>

                            <p class="mt-1 text-sm text-gray-500">{{ __('Provide a name, symbol, and category for this unit.') }}</p>

                            <div class="mt-6 space-y-4">
                                <div>
                                    <label for="uom-name" class="block text-sm font-medium text-gray-700">{{ __('Name') }}</label>
                                    <input id="uom-name" type="text" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500" x-model="form.name" />
                                    <p class="mt-1 text-sm text-red-600" x-text="errors.name ? errors.name[0] : ''"></p>
                                </div>

                                <div>
                                    <label for="uom-symbol" class="block text-sm font-medium text-gray-700">{{ __('Symbol') }}</label>
                                    <input id="uom-symbol" type="text" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500" x-model="form.symbol" />
                                    <p class="mt-1 text-sm text-red-600" x-text="errors.symbol ? errors.symbol[0] : ''"></p>
                                </div>

                                <div>
                                    <label for="uom-category" class="block text-sm font-medium text-gray-700">{{ __('Category') }}</label>
                                    <select id="uom-category" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500" x-model="form.uom_category_id">
                                        <option value="">{{ __('Select a category') }}</option>
                                        <template x-for="category in categories" :key="category.id">
                                            <option :value="category.id" x-text="category.name"></option>
                                        </template>
                                    </select>
                                    <p class="mt-1 text-sm text-red-600" x-text="errors.uom_category_id ? errors.uom_category_id[0] : ''"></p>
                                </div>
                            </div>

                            <div class="mt-6 flex items-center justify-end gap-3">
                                <button type="button" class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-md text-sm font-medium text-gray-700 hover:bg-gray-50" @click="closeForm">
                                    {{ __('Cancel') }}
                                </button>
                                <button type="button" class="inline-flex items-center px-4 py-2 bg-blue-600 border border-transparent rounded-md text-sm font-medium text-white hover:bg-blue-500 disabled:opacity-50" :disabled="isSubmitting" @click="submitForm">
                                    <span x-text="isSubmitting ? '{{ __('Saving...') }}' : (isEditing ? '{{ __('Save') }}' : '{{ __('Create') }}')"></span>
                                </button>
                            </div>
                        </div>
                    </div>

                    <div x-show="deleteOpen" class="fixed inset-0 z-50 flex items-center justify-center">
                        <div class="fixed inset-0 bg-gray-900/50" @click="closeDelete"></div>
                        <div class="relative bg-white rounded-lg shadow-xl w-full max-w-md mx-4 p-6">
                            <h4 class="text-lg font-medium text-gray-900">{{ __('Delete Unit') }}</h4>
                            <p class="mt-2 text-sm text-gray-500">
                                {{ __('Are you sure you want to delete this unit? This action cannot be undone.') }}
                            </p>

                            <div class="mt-6 flex items-center justify-end gap-3">
                                <button type="button" class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-md text-sm font-medium text-gray-700 hover:bg-gray-50" @click="closeDelete">
                                    {{ __('Cancel') }}
                                </button>
                                <button type="button" class="inline-flex items-center px-4 py-2 bg-red-600 border border-transparent rounded-md text-sm font-medium text-white hover:bg-red-500 disabled:opacity-50" :disabled="isSubmitting" @click="confirmDelete">
                                    <span x-text="isSubmitting ? '{{ __('Deleting...') }}' : '{{ __('Delete') }}'"></span>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
