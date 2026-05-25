<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Recipes') }}
        </h2>
    </x-slot>

    <script type="application/json" id="manufacturing-recipes-index-payload">@json($payload)</script>

    <div
        class="flex h-[calc(100vh-8rem)] min-h-0 flex-col overflow-hidden py-6"
        data-page="manufacturing-recipes-index"
        data-payload="manufacturing-recipes-index-payload"
        data-crud-config='@json($crudConfig)'
        x-data="manufacturingRecipesIndex"
    >
        <div class="fixed top-6 right-6 z-50" x-show="toast.visible">
            <div
                class="rounded-md px-4 py-3 text-sm shadow-md"
                :class="toast.type === 'success' ? 'bg-green-50 text-green-700' : 'bg-red-50 text-red-700'"
                x-text="toast.message"
            ></div>
        </div>

        <div class="mx-auto flex h-full min-h-0 w-full max-w-7xl flex-1 flex-col overflow-hidden sm:px-6 lg:px-8">
            <div class="flex h-full min-h-0 flex-1 flex-col" data-crud-root></div>
        </div>

        @include('manufacturing.recipes.partials.delete-recipe-modal')
        @include('manufacturing.recipes.partials.create-recipe-slide-over')
        @include('manufacturing.recipes.partials.edit-recipe-slide-over')
        @include('manufacturing.recipes.partials.create-recipe-version-slide-over')
    </div>
</x-app-layout>
