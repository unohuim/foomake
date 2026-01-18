<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('UoM Categories') }}
        </h2>
    </x-slot>

    <div class="py-8" x-data="{
        categories: @json($categories),
        storeUrl: '{{ route('materials.uom-categories.store') }}',
        updateUrlTemplate: '{{ route('materials.uom-categories.update', ['uomCategory' => '__ID__']) }}',
        deleteUrlTemplate: '{{ route('materials.uom-categories.destroy', ['uomCategory' => '__ID__']) }}',
        csrfToken: '{{ csrf_token() }}',
        formOpen: false,
        deleteOpen: false,
        isEditing: false,
        isSubmitting: false,
        form: { id: null, name: '' },
        errors: {},
        errorMessage: '',
        deleteTarget: null,
        openCreate() {
            this.resetErrors();
            this.form = { id: null, name: '' };
            this.isEditing = false;
            this.formOpen = true;
        },
        openEdit(category) {
            this.resetErrors();
            this.form = { id: category.id, name: category.name };
            this.isEditing = true;
            this.formOpen = true;
        },
        closeForm() {
            this.formOpen = false;
        },
        openDelete(category) {
            this.resetErrors();
            this.deleteTarget = category;
            this.deleteOpen = true;
        },
        closeDelete() {
            this.deleteOpen = false;
            this.deleteTarget = null;
        },
        resetErrors() {
            this.errors = {};
            this.errorMessage = '';
        },
        updateCategory(updated) {
            const index = this.categories.findIndex((category) => category.id === updated.id);
            if (index >= 0) {
                this.categories.splice(index, 1, updated);
            } else {
                this.categories.push(updated);
            }
            this.sortCategories();
        },
        removeCategory(id) {
            this.categories = this.categories.filter((category) => category.id !== id);
        },
        sortCategories() {
            this.categories = this.categories.sort((a, b) => a.name.localeCompare(b.name));
        },
        async submitForm() {
            this.resetErrors();
            this.isSubmitting = true;

            const url = this.isEditing
                ? this.updateUrlTemplate.replace('__ID__', this.form.id)
                : this.storeUrl;

            const method = this.isEditing ? 'PATCH' : 'POST';

            const response = await fetch(url, {
                method,
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': this.csrfToken,
                },
                body: JSON.stringify({ name: this.form.name }),
            });

            this.isSubmitting = false;

            if (response.status === 422) {
                const data = await response.json();
                this.errors = data.errors || {};
                return;
            }

            if (!response.ok) {
                this.errorMessage = 'Something went wrong. Please try again.';
                return;
            }

            const data = await response.json();
            this.updateCategory(data);
            this.closeForm();
        },
        async confirmDelete() {
            if (!this.deleteTarget) {
                return;
            }

            this.isSubmitting = true;
            const response = await fetch(this.deleteUrlTemplate.replace('__ID__', this.deleteTarget.id), {
                method: 'DELETE',
                headers: {
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': this.csrfToken,
                },
            });

            this.isSubmitting = false;

            if (!response.ok) {
                this.errorMessage = 'Unable to delete the category.';
                return;
            }

            this.removeCategory(this.deleteTarget.id);
            this.closeDelete();
        },
    }">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900 space-y-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <h3 class="text-lg font-medium text-gray-900">{{ __('UoM Categories') }}</h3>
                            <p class="text-sm text-gray-500">{{ __('Define categories that group related units of measure.') }}</p>
                        </div>
                        <button type="button" class="inline-flex items-center px-4 py-2 bg-blue-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 transition" @click="openCreate">
                            {{ __('Create Category') }}
                        </button>
                    </div>

                    <div x-show="errorMessage" class="rounded-md bg-red-50 p-4">
                        <p class="text-sm text-red-700" x-text="errorMessage"></p>
                    </div>

                    <div x-show="categories.length === 0" class="rounded-md border border-dashed border-gray-200 p-8 text-center">
                        <h4 class="text-sm font-medium text-gray-900">{{ __('No UoM categories yet') }}</h4>
                        <p class="mt-1 text-sm text-gray-500">{{ __('Create your first unit of measure category to organize units.') }}</p>
                        <div class="mt-4">
                            <button type="button" class="inline-flex items-center px-4 py-2 bg-blue-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 transition" @click="openCreate">
                                {{ __('Create UoM Category') }}
                            </button>
                        </div>
                    </div>

                    <div x-show="categories.length > 0" class="overflow-hidden border border-gray-100 rounded-lg">
                        <table class="min-w-full divide-y divide-gray-100">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        {{ __('Name') }}
                                    </th>
                                    <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        {{ __('Actions') }}
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-100">
                                <template x-for="category in categories" :key="category.id">
                                    <tr>
                                        <td class="px-6 py-4 text-sm text-gray-900" x-text="category.name"></td>
                                        <td class="px-6 py-4 text-right text-sm">
                                            <button type="button" class="text-blue-600 hover:text-blue-500 mr-4" @click="openEdit(category)">
                                                {{ __('Edit') }}
                                            </button>
                                            <button type="button" class="text-red-600 hover:text-red-500" @click="openDelete(category)">
                                                {{ __('Delete') }}
                                            </button>
                                        </td>
                                    </tr>
                                </template>
                            </tbody>
                        </table>
                    </div>

                    <div x-show="formOpen" class="fixed inset-0 z-50 flex items-center justify-center">
                        <div class="fixed inset-0 bg-gray-900/50" @click="closeForm"></div>
                        <div class="relative bg-white rounded-lg shadow-xl w-full max-w-md mx-4 p-6">
                            <h4 class="text-lg font-medium text-gray-900" x-text="isEditing ? '{{ __('Edit UoM Category') }}' : '{{ __('Create UoM Category') }}'"></h4>
                            <p class="mt-1 text-sm text-gray-500">{{ __('Provide a clear category name.') }}</p>

                            <div class="mt-6 space-y-2">
                                <label for="category-name" class="block text-sm font-medium text-gray-700">{{ __('Name') }}</label>
                                <input id="category-name" type="text" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500" x-model="form.name" />
                                <p class="text-sm text-red-600" x-text="errors.name ? errors.name[0] : ''"></p>
                            </div>

                            <div class="mt-6 flex items-center justify-end gap-3">
                                <button type="button" class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-md text-sm font-medium text-gray-700 hover:bg-gray-50" @click="closeForm">
                                    {{ __('Cancel') }}
                                </button>
                                <button type="button" class="inline-flex items-center px-4 py-2 bg-blue-600 border border-transparent rounded-md text-sm font-medium text-white hover:bg-blue-500 disabled:opacity-50" :disabled="isSubmitting" @click="submitForm">
                                    <span x-text="isEditing ? '{{ __('Save') }}' : '{{ __('Create') }}'"></span>
                                </button>
                            </div>
                        </div>
                    </div>

                    <div x-show="deleteOpen" class="fixed inset-0 z-50 flex items-center justify-center">
                        <div class="fixed inset-0 bg-gray-900/50" @click="closeDelete"></div>
                        <div class="relative bg-white rounded-lg shadow-xl w-full max-w-md mx-4 p-6">
                            <h4 class="text-lg font-medium text-gray-900">{{ __('Delete UoM Category') }}</h4>
                            <p class="mt-2 text-sm text-gray-500">
                                {{ __('Are you sure you want to delete this category? This action cannot be undone.') }}
                            </p>

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
