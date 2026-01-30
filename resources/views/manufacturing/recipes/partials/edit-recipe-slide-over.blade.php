<div
    class="fixed inset-0 z-50 overflow-hidden"
    x-cloak x-show="isEditOpen"
    role="dialog"
    aria-modal="true"
>
    <div class="absolute inset-0 overflow-hidden">
        <div
            class="absolute inset-0 bg-gray-500 bg-opacity-25 transition-opacity"
            x-cloak x-show="isEditOpen"
            x-on:click="closeEdit()"
        ></div>

        <div class="pointer-events-none fixed inset-y-0 right-0 flex max-w-full pl-10">
            <div class="pointer-events-auto w-screen max-w-md">
                <form class="flex h-full flex-col bg-white shadow-xl" x-on:submit.prevent="submitEdit()">
                    <div class="flex-1 overflow-y-auto p-6">
                        <div class="flex items-start justify-between">
                            <div>
                                <h2 class="text-lg font-medium text-gray-900">{{ __('Edit Recipe') }}</h2>
                                <p class="mt-1 text-sm text-gray-600">{{ __('Update the output item and status.') }}</p>
                            </div>
                            <button
                                type="button"
                                class="text-gray-400 hover:text-gray-500"
                                x-on:click="closeEdit()"
                            >
                                <span class="sr-only">{{ __('Close panel') }}</span>
                                ✕
                            </button>
                        </div>

                        <div class="mt-6" x-show="editGeneralError">
                            <div class="rounded-md bg-red-50 p-3 text-sm text-red-700" x-text="editGeneralError"></div>
                        </div>

                        <div class="mt-6 space-y-5">
                            <div>
                                <label for="recipe-edit-output-item" class="block text-sm font-medium text-gray-700">{{ __('Output Item') }}</label>
                                <select
                                    id="recipe-edit-output-item"
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm"
                                    x-model="editForm.item_id"
                                    :disabled="editOutputLocked"
                                    :class="editOutputLocked ? 'bg-gray-100 cursor-not-allowed' : ''"
                                >
                                    <option value="">{{ __('Select an item') }}</option>
                                    <template x-for="item in manufacturableItems" :key="item.id">
                                        <option x-bind:value="item.id" x-text="item.name"></option>
                                    </template>
                                </select>
                                <p class="mt-1 text-sm text-gray-500" x-show="editOutputLocked">
                                    {{ __('Output item is locked because the recipe has lines.') }}
                                </p>
                                <p class="mt-1 text-sm text-red-600" x-show="editErrors.item_id.length" x-text="editErrors.item_id[0]"></p>
                            </div>

                            <div>
                                <label class="flex items-center gap-2 text-sm text-gray-700">
                                    <input
                                        type="checkbox"
                                        class="rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                                        x-model="editForm.is_active"
                                    >
                                    {{ __('Active') }}
                                </label>
                                <p class="mt-1 text-sm text-red-600" x-show="editErrors.is_active.length" x-text="editErrors.is_active[0]"></p>
                            </div>
                        </div>
                    </div>

                    <div class="flex justify-end gap-3 border-t border-gray-100 bg-white px-6 py-4">
                        <button
                            type="button"
                            class="inline-flex items-center rounded-md border border-gray-300 bg-white px-4 py-2 text-xs font-semibold uppercase tracking-widest text-gray-700 shadow-sm transition hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2"
                            x-on:click="closeEdit()"
                        >
                            {{ __('Cancel') }}
                        </button>
                        <button
                            type="submit"
                            class="inline-flex items-center rounded-md border border-transparent bg-blue-600 px-4 py-2 text-xs font-semibold uppercase tracking-widest text-white transition hover:bg-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2"
                            :disabled="isEditSubmitting"
                            :class="isEditSubmitting ? 'opacity-50 cursor-not-allowed' : ''"
                        >
                            {{ __('Save Changes') }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
