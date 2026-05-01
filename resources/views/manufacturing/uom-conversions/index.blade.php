<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('UoM Conversions') }}
        </h2>
    </x-slot>

    <script type="application/json" id="manufacturing-uom-conversions-index-payload">@json($payload)</script>

    <div
        class="py-8"
        data-page="manufacturing-uom-conversions-index"
        data-payload="manufacturing-uom-conversions-index-payload"
        x-data="manufacturingUomConversionsIndex"
    >
        <div class="fixed top-6 right-6 z-50" x-show="toastVisible" x-cloak>
            <div class="rounded-md bg-green-50 border border-green-200 px-4 py-3 shadow-sm">
                <p class="text-sm text-green-700" x-text="toastMessage"></p>
            </div>
        </div>

        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900 space-y-8">
                    <div x-cloak x-show="errorMessage" class="rounded-md bg-red-50 p-4">
                        <p class="text-sm text-red-700" x-text="errorMessage"></p>
                    </div>

                    <section class="space-y-4">
                        <div class="flex items-center justify-between gap-4">
                            <div>
                                <h3 class="text-lg font-medium text-gray-900">{{ __('General Conversions') }}</h3>
                                <p class="text-sm text-gray-500">{{ __('Global conversions are read-only. Tenant conversions are editable.') }}</p>
                            </div>
                            <button
                                type="button"
                                class="inline-flex items-center px-4 py-2 bg-blue-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 transition"
                                @click="openGeneralCreate"
                            >
                                {{ __('Create Conversion') }}
                            </button>
                        </div>

                        <div>
                            <h4 class="text-sm font-semibold text-gray-900">globalConversions</h4>
                            <div class="mt-3 overflow-hidden border border-gray-100 rounded-lg">
                                <table class="min-w-full divide-y divide-gray-100">
                                    <thead class="bg-gray-50">
                                        <tr>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">{{ __('From') }}</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">{{ __('To') }}</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">{{ __('Multiplier') }}</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">read_only</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">tenant_id</th>
                                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">{{ __('Actions') }}</th>
                                        </tr>
                                    </thead>
                                    <tbody class="bg-white divide-y divide-gray-100">
                                        <template x-if="globalConversions.length === 0">
                                            <tr>
                                                <td colspan="6" class="px-6 py-4 text-sm text-gray-500">{{ __('No global conversions available.') }}</td>
                                            </tr>
                                        </template>
                                        <template x-for="conversion in globalConversions" :key="`global-${conversion.id}`">
                                            <tr>
                                                <td class="px-6 py-4 text-sm text-gray-900" x-text="conversion.from_symbol"></td>
                                                <td class="px-6 py-4 text-sm text-gray-900" x-text="conversion.to_symbol"></td>
                                                <td class="px-6 py-4 text-sm text-gray-600" x-text="conversion.multiplier_display"></td>
                                                <td class="px-6 py-4 text-sm text-gray-600">true</td>
                                                <td class="px-6 py-4 text-sm text-gray-600">null</td>
                                                <td class="px-6 py-4 text-right text-sm text-gray-400">{{ __('Read-only') }}</td>
                                            </tr>
                                        </template>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <div>
                            <h4 class="text-sm font-semibold text-gray-900">tenantConversions</h4>
                            <div class="mt-3 overflow-hidden border border-gray-100 rounded-lg">
                                <table class="min-w-full divide-y divide-gray-100">
                                    <thead class="bg-gray-50">
                                        <tr>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">{{ __('From') }}</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">{{ __('To') }}</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">{{ __('Multiplier') }}</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">editable</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">tenant_id</th>
                                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">{{ __('Actions') }}</th>
                                        </tr>
                                    </thead>
                                    <tbody class="bg-white divide-y divide-gray-100">
                                        <template x-if="tenantConversions.length === 0">
                                            <tr>
                                                <td colspan="6" class="px-6 py-4 text-sm text-gray-500">{{ __('No tenant conversions yet.') }}</td>
                                            </tr>
                                        </template>
                                        <template x-for="conversion in tenantConversions" :key="`tenant-${conversion.id}`">
                                            <tr>
                                                <td class="px-6 py-4 text-sm text-gray-900" x-text="conversion.from_symbol"></td>
                                                <td class="px-6 py-4 text-sm text-gray-900" x-text="conversion.to_symbol"></td>
                                                <td class="px-6 py-4 text-sm text-gray-600" x-text="conversion.multiplier_display"></td>
                                                <td class="px-6 py-4 text-sm text-gray-600">true</td>
                                                <td class="px-6 py-4 text-sm text-gray-600" x-text="conversion.tenant_id"></td>
                                                <td class="px-6 py-4 text-right text-sm">
                                                    <button type="button" class="text-blue-600 hover:text-blue-500 mr-4" @click="openGeneralEdit(conversion)">
                                                        {{ __('Edit') }}
                                                    </button>
                                                    <button type="button" class="text-red-600 hover:text-red-500" @click="openGeneralDelete(conversion)">
                                                        {{ __('Delete') }}
                                                    </button>
                                                </td>
                                            </tr>
                                        </template>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </section>

                    <section class="space-y-4">
                        <div class="flex items-center justify-between gap-4">
                            <div>
                                <h3 class="text-lg font-medium text-gray-900">itemSpecificConversions</h3>
                                <p class="text-sm text-gray-500">{{ __('Item-specific conversions allow cross-category mappings and override tenant and global conversions.') }}</p>
                            </div>
                            <button
                                type="button"
                                class="inline-flex items-center px-4 py-2 bg-blue-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 transition"
                                @click="openItemCreate"
                            >
                                {{ __('Create Item Conversion') }}
                            </button>
                        </div>

                        <div class="overflow-hidden border border-gray-100 rounded-lg">
                            <table class="min-w-full divide-y divide-gray-100">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">{{ __('Item') }}</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">{{ __('From') }}</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">{{ __('To') }}</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">{{ __('Factor') }}</th>
                                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">{{ __('Actions') }}</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-100">
                                    <template x-if="itemSpecificConversions.length === 0">
                                        <tr>
                                            <td colspan="5" class="px-6 py-4 text-sm text-gray-500">{{ __('No item-specific conversions yet.') }}</td>
                                        </tr>
                                    </template>
                                    <template x-for="conversion in itemSpecificConversions" :key="`item-${conversion.id}`">
                                        <tr>
                                            <td class="px-6 py-4 text-sm text-gray-900" x-text="conversion.item_name"></td>
                                            <td class="px-6 py-4 text-sm text-gray-900" x-text="conversion.from_symbol"></td>
                                            <td class="px-6 py-4 text-sm text-gray-900" x-text="conversion.to_symbol"></td>
                                            <td class="px-6 py-4 text-sm text-gray-600" x-text="conversion.conversion_factor_display"></td>
                                            <td class="px-6 py-4 text-right text-sm">
                                                <button type="button" class="text-blue-600 hover:text-blue-500 mr-4" @click="openItemEdit(conversion)">
                                                    {{ __('Edit') }}
                                                </button>
                                                <button type="button" class="text-red-600 hover:text-red-500" @click="openItemDelete(conversion)">
                                                    {{ __('Delete') }}
                                                </button>
                                            </td>
                                        </tr>
                                    </template>
                                </tbody>
                            </table>
                        </div>
                    </section>

                    <div x-cloak x-show="generalFormOpen" class="fixed inset-0 z-50 overflow-hidden">
                        <div class="absolute inset-0 overflow-hidden">
                            <div class="fixed inset-0 bg-gray-900/50" @click="closeGeneralForm"></div>
                            <div class="pointer-events-none fixed inset-y-0 right-0 flex max-w-full pl-10">
                                <div class="pointer-events-auto w-screen max-w-md">
                                    <div class="flex h-full flex-col bg-white shadow-xl">
                                        <div class="flex-1 overflow-y-auto p-6">
                                            <div class="flex items-center justify-between">
                                                <h4 class="text-lg font-medium text-gray-900" x-text="generalIsEditing ? '{{ __('Edit Conversion') }}' : '{{ __('Create Conversion') }}'"></h4>
                                                <button type="button" class="text-gray-400 hover:text-gray-500" @click="closeGeneralForm">
                                                    <span class="sr-only">{{ __('Close') }}</span>
                                                    <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                                                        <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd" />
                                                    </svg>
                                                </button>
                                            </div>

                                            <div class="mt-6 space-y-4">
                                <div>
                                    <label for="general-from-uom" class="block text-sm font-medium text-gray-700">{{ __('From UoM') }}</label>
                                    <select id="general-from-uom" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500" x-model="generalForm.from_uom_id">
                                        <option value="">{{ __('Select a unit') }}</option>
                                        <template x-for="uom in generalUomOptions" :key="`general-from-${uom.id}`">
                                            <option :value="String(uom.id)" x-text="`${uom.symbol} · ${uom.name}`"></option>
                                        </template>
                                    </select>
                                    <p class="mt-1 text-sm text-red-600" x-text="generalErrors.from_uom_id ? generalErrors.from_uom_id[0] : ''"></p>
                                </div>

                                <div>
                                    <label for="general-to-uom" class="block text-sm font-medium text-gray-700">{{ __('To UoM') }}</label>
                                    <select id="general-to-uom" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500" x-model="generalForm.to_uom_id">
                                        <option value="">{{ __('Select a unit') }}</option>
                                        <template x-for="uom in generalUomOptions" :key="`general-to-${uom.id}`">
                                            <option :value="String(uom.id)" x-text="`${uom.symbol} · ${uom.name}`"></option>
                                        </template>
                                    </select>
                                    <p class="mt-1 text-sm text-red-600" x-text="generalErrors.to_uom_id ? generalErrors.to_uom_id[0] : ''"></p>
                                </div>

                                <div>
                                    <label for="general-multiplier" class="block text-sm font-medium text-gray-700">{{ __('Multiplier') }}</label>
                                    <input id="general-multiplier" type="text" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500" x-model="generalForm.multiplier" />
                                    <p class="mt-1 text-sm text-red-600" x-text="generalErrors.multiplier ? generalErrors.multiplier[0] : ''"></p>
                                </div>
                                            </div>
                                        </div>

                                        <div class="flex justify-end gap-3 border-t border-gray-100 bg-white px-6 py-4">
                                            <button type="button" class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-md text-sm font-medium text-gray-700 hover:bg-gray-50" @click="closeGeneralForm">
                                                {{ __('Cancel') }}
                                            </button>
                                            <button type="button" class="inline-flex items-center px-4 py-2 bg-blue-600 border border-transparent rounded-md text-sm font-medium text-white hover:bg-blue-500 disabled:opacity-50" :disabled="isSubmitting" @click="submitGeneralForm">
                                                <span x-text="generalIsEditing ? '{{ __('Save') }}' : '{{ __('Create') }}'"></span>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div x-cloak x-show="itemFormOpen" class="fixed inset-0 z-50 overflow-hidden">
                        <div class="absolute inset-0 overflow-hidden">
                            <div class="fixed inset-0 bg-gray-900/50" @click="closeItemForm"></div>
                            <div class="pointer-events-none fixed inset-y-0 right-0 flex max-w-full pl-10">
                                <div class="pointer-events-auto w-screen max-w-md">
                                    <div class="flex h-full flex-col bg-white shadow-xl">
                                        <div class="flex-1 overflow-y-auto p-6">
                                            <div class="flex items-center justify-between">
                                                <h4 class="text-lg font-medium text-gray-900" x-text="itemIsEditing ? '{{ __('Edit Item Conversion') }}' : '{{ __('Create Item Conversion') }}'"></h4>
                                                <button type="button" class="text-gray-400 hover:text-gray-500" @click="closeItemForm">
                                                    <span class="sr-only">{{ __('Close') }}</span>
                                                    <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                                                        <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd" />
                                                    </svg>
                                                </button>
                                            </div>

                                            <div class="mt-6 space-y-4">
                                <div>
                                    <label for="item-selector" class="block text-sm font-medium text-gray-700">{{ __('Item') }}</label>
                                    <select id="item-selector" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500" x-model="itemForm.item_id">
                                        <option value="">{{ __('Select an item') }}</option>
                                        <template x-for="item in items" :key="`item-${item.id}`">
                                            <option :value="String(item.id)" x-text="item.name"></option>
                                        </template>
                                    </select>
                                    <p class="mt-1 text-sm text-red-600" x-text="itemErrors.item_id ? itemErrors.item_id[0] : ''"></p>
                                </div>

                                <div>
                                    <label for="item-from-uom" class="block text-sm font-medium text-gray-700">{{ __('From UoM') }}</label>
                                    <select id="item-from-uom" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500" x-model="itemForm.from_uom_id">
                                        <option value="">{{ __('Select a unit') }}</option>
                                        <template x-for="uom in itemUomOptions" :key="`item-from-${uom.id}`">
                                            <option :value="String(uom.id)" x-text="`${uom.symbol} · ${uom.name}`"></option>
                                        </template>
                                    </select>
                                    <p class="mt-1 text-sm text-red-600" x-text="itemErrors.from_uom_id ? itemErrors.from_uom_id[0] : ''"></p>
                                </div>

                                <div>
                                    <label for="item-to-uom" class="block text-sm font-medium text-gray-700">{{ __('To UoM') }}</label>
                                    <select id="item-to-uom" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500" x-model="itemForm.to_uom_id">
                                        <option value="">{{ __('Select a unit') }}</option>
                                        <template x-for="uom in itemUomOptions" :key="`item-to-${uom.id}`">
                                            <option :value="String(uom.id)" x-text="`${uom.symbol} · ${uom.name}`"></option>
                                        </template>
                                    </select>
                                    <p class="mt-1 text-sm text-red-600" x-text="itemErrors.to_uom_id ? itemErrors.to_uom_id[0] : ''"></p>
                                </div>

                                <div>
                                    <label for="item-factor" class="block text-sm font-medium text-gray-700">{{ __('Conversion Factor') }}</label>
                                    <input id="item-factor" type="text" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500" x-model="itemForm.conversion_factor" />
                                    <p class="mt-1 text-sm text-red-600" x-text="itemErrors.conversion_factor ? itemErrors.conversion_factor[0] : ''"></p>
                                </div>
                                            </div>
                                        </div>

                                        <div class="flex justify-end gap-3 border-t border-gray-100 bg-white px-6 py-4">
                                            <button type="button" class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-md text-sm font-medium text-gray-700 hover:bg-gray-50" @click="closeItemForm">
                                                {{ __('Cancel') }}
                                            </button>
                                            <button type="button" class="inline-flex items-center px-4 py-2 bg-blue-600 border border-transparent rounded-md text-sm font-medium text-white hover:bg-blue-500 disabled:opacity-50" :disabled="isSubmitting" @click="submitItemForm">
                                                <span x-text="itemIsEditing ? '{{ __('Save') }}' : '{{ __('Create') }}'"></span>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div x-cloak x-show="deleteOpen" class="fixed inset-0 z-50 flex items-center justify-center">
                        <div class="fixed inset-0 bg-gray-900/50" @click="closeDelete"></div>
                        <div class="relative bg-white rounded-lg shadow-xl w-full max-w-md mx-4 p-6">
                            <h4 class="text-lg font-medium text-gray-900">{{ __('Delete Conversion') }}</h4>
                            <p class="mt-2 text-sm text-gray-500">{{ __('Are you sure you want to delete this conversion?') }}</p>

                            <div class="mt-6 flex items-center justify-end gap-3">
                                <button type="button" class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-md text-sm font-medium text-gray-700 hover:bg-gray-50" @click="closeDelete">
                                    {{ __('Cancel') }}
                                </button>
                                <button type="button" class="inline-flex items-center px-4 py-2 bg-red-600 border border-transparent rounded-md text-sm font-medium text-white hover:bg-red-500 disabled:opacity-50" :disabled="isSubmitting" @click="confirmDelete">
                                    {{ __('Delete') }}
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
