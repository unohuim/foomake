<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\File;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->renderSlideOverShell = function (string $view): string {
        return Blade::render($view);
    };

    $this->slideOverSource = fn (): string => File::get(resource_path('views/components/slide-over-shell.blade.php'));
    $this->materialCreateSource = fn (): string => File::get(resource_path('views/materials/partials/create-material-slide-over.blade.php'));
    $this->materialEditSource = fn (): string => File::get(resource_path('views/materials/partials/edit-material-slide-over.blade.php'));
    $this->recipeCreateSource = fn (): string => File::get(resource_path('views/manufacturing/recipes/partials/create-recipe-slide-over.blade.php'));
    $this->recipeEditSource = fn (): string => File::get(resource_path('views/manufacturing/recipes/partials/edit-recipe-slide-over.blade.php'));
    $this->recipeVersionSource = fn (): string => File::get(resource_path('views/manufacturing/recipes/partials/create-recipe-version-slide-over.blade.php'));
    $this->recipeLineSource = fn (): string => File::get(resource_path('views/manufacturing/recipes/partials/line-form-slide-over.blade.php'));
    $this->makeOrderSource = fn (): string => File::get(resource_path('views/manufacturing/make-orders/partials/create-make-order-slide-over.blade.php'));
    $this->adminUsersSource = fn (): string => File::get(resource_path('views/admin/users/index.blade.php'));
    $this->salesProductsSource = fn (): string => File::get(resource_path('views/sales/products/index.blade.php'));
    $this->salesCustomersSource = fn (): string => File::get(resource_path('views/sales/customers/index.blade.php'));
    $this->salesCustomerDetailSource = fn (): string => File::get(resource_path('views/sales/customers/show.blade.php'));
    $this->salesOrdersSource = fn (): string => File::get(resource_path('views/sales/orders/index.blade.php'));
    $this->suppliersSource = fn (): string => File::get(resource_path('views/purchasing/suppliers/index.blade.php'));
    $this->inventoryCountFormSource = fn (): string => File::get(resource_path('views/inventory/counts/partials/count-form.blade.php'));
    $this->inventoryCountLineFormSource = fn (): string => File::get(resource_path('views/inventory/counts/partials/line-form.blade.php'));
    $this->uomsSource = fn (): string => File::get(resource_path('views/manufacturing/uoms/index.blade.php'));
    $this->uomConversionsSource = fn (): string => File::get(resource_path('views/manufacturing/uom-conversions/index.blade.php'));
    $this->jsCrudSectionSource = fn (): string => File::get(resource_path('js/lib/js-crud-section.js'));
});

it('1. renders a fixed right-side slide-over shell', function () {
    $html = ($this->renderSlideOverShell)(
        <<<'BLADE'
<x-slide-over-shell open="isOpen" close="closePanel()" title="New Record" title-id="new-record-title">
    <p>Form fields</p>
</x-slide-over-shell>
BLADE
    );

    expect($html)->toContain('fixed inset-0 z-50 overflow-hidden')
        ->and($html)->toContain('fixed inset-y-0 right-0')
        ->and($html)->toContain('w-screen max-w-md');
});

it('2. exposes dialog accessibility attributes', function () {
    $html = ($this->renderSlideOverShell)(
        <<<'BLADE'
<x-slide-over-shell open="isOpen" close="closePanel()" title="New Record" title-id="new-record-title">
    <p>Form fields</p>
</x-slide-over-shell>
BLADE
    );

    expect($html)->toContain('role="dialog"')
        ->and($html)->toContain('aria-modal="true"')
        ->and($html)->toContain('aria-labelledby="new-record-title"')
        ->and($html)->toContain('id="new-record-title"');
});

it('3. binds the configured Alpine open expression', function () {
    $html = ($this->renderSlideOverShell)(
        <<<'BLADE'
<x-slide-over-shell open="isCreateOpen" close="closeCreate()" title="Create" title-id="create-title">
    <p>Form fields</p>
</x-slide-over-shell>
BLADE
    );

    expect($html)->toContain('x-show="isCreateOpen"');
});

it('4. binds the configured close action to backdrop and close button', function () {
    $html = ($this->renderSlideOverShell)(
        <<<'BLADE'
<x-slide-over-shell open="isOpen" close="closePanel()" title="Create" title-id="create-title">
    <p>Form fields</p>
</x-slide-over-shell>
BLADE
    );

    expect(substr_count($html, 'x-on:click="closePanel()"'))->toBe(3);
});

it('5. binds the configured submit action when provided', function () {
    $html = ($this->renderSlideOverShell)(
        <<<'BLADE'
<x-slide-over-shell open="isOpen" close="closePanel()" submit="submitCreate()" title="Create" title-id="create-title">
    <p>Form fields</p>
</x-slide-over-shell>
BLADE
    );

    expect($html)->toContain('x-on:submit.prevent="submitCreate()"');
});

it('6. renders the title text', function () {
    $html = ($this->renderSlideOverShell)(
        <<<'BLADE'
<x-slide-over-shell open="isOpen" close="closePanel()" title="Create Material" title-id="create-title">
    <p>Form fields</p>
</x-slide-over-shell>
BLADE
    );

    expect($html)->toContain('Create Material');
});

it('7. renders the description text when provided', function () {
    $html = ($this->renderSlideOverShell)(
        <<<'BLADE'
<x-slide-over-shell open="isOpen" close="closePanel()" title="Create" description="Add a record." title-id="create-title">
    <p>Form fields</p>
</x-slide-over-shell>
BLADE
    );

    expect($html)->toContain('Add a record.');
});

it('8. supports dynamic title expressions', function () {
    $html = ($this->renderSlideOverShell)(
        <<<'BLADE'
<x-slide-over-shell
    open="isOpen"
    close="closePanel()"
    title-expression="isEditing ? 'Edit' : 'Create'"
    title-id="dynamic-title"
>
    <p>Form fields</p>
</x-slide-over-shell>
BLADE
    );

    expect($html)->toContain('x-text="isEditing ? &#039;Edit&#039; : &#039;Create&#039;"');
});

it('9. supports dynamic description expressions', function () {
    $html = ($this->renderSlideOverShell)(
        <<<'BLADE'
<x-slide-over-shell
    open="isOpen"
    close="closePanel()"
    title="Create"
    description-expression="isEditing ? 'Update the record.' : 'Create the record.'"
    title-id="dynamic-description"
>
    <p>Form fields</p>
</x-slide-over-shell>
BLADE
    );

    expect($html)->toContain('x-text="isEditing ? &#039;Update the record.&#039; : &#039;Create the record.&#039;"');
});

it('10. renders the default slot as form body content', function () {
    $html = ($this->renderSlideOverShell)(
        <<<'BLADE'
<x-slide-over-shell open="isOpen" close="closePanel()" title="Create" title-id="create-title">
    <input id="resource-name" type="text" />
</x-slide-over-shell>
BLADE
    );

    expect($html)->toContain('id="resource-name"')
        ->and($html)->toContain('type="text"');
});

it('11. renders the footer slot below the body', function () {
    $html = ($this->renderSlideOverShell)(
        <<<'BLADE'
<x-slide-over-shell open="isOpen" close="closePanel()" title="Create" title-id="create-title">
    <p>Form fields</p>

    <x-slot name="footer">
        <button type="submit">Save</button>
    </x-slot>
</x-slide-over-shell>
BLADE
    );

    expect($html)->toContain('type="submit"')
        ->and($html)->toContain('Save');
});

it('12. supports custom panel width', function () {
    $html = ($this->renderSlideOverShell)(
        <<<'BLADE'
<x-slide-over-shell open="isOpen" close="closePanel()" title="Create" title-id="create-title" max-width="max-w-2xl">
    <p>Form fields</p>
</x-slide-over-shell>
BLADE
    );

    expect($html)->toContain('w-screen max-w-2xl');
});

it('13. uses a Heroicons x-mark icon for close', function () {
    $source = ($this->slideOverSource)();

    expect($source)->toContain('M6 18 18 6M6 6l12 12')
        ->and($source)->not->toContain('✕');
});

it('14. does not use Tailwind Plus custom elements or command attributes', function () {
    $source = ($this->slideOverSource)();

    expect($source)->not->toContain('el-dialog')
        ->and($source)->not->toContain('command=')
        ->and($source)->not->toContain('commandfor=')
        ->and($source)->not->toContain('@tailwindplus/elements')
        ->and($source)->not->toContain('cdn.jsdelivr.net');
});

it('15. does not introduce inline styles', function () {
    $source = ($this->slideOverSource)();

    expect($source)->not->toContain('style=');
});

it('16. keeps the form shell in the shared component', function () {
    $source = ($this->slideOverSource)();

    expect($source)->toContain('<form')
        ->and($source)->toContain('relative flex h-full flex-col')
        ->and($source)->toContain('divide-y divide-gray-200');
});

it('17. includes Alpine transitions for the backdrop and panel', function () {
    $source = ($this->slideOverSource)();

    expect($source)->toContain('x-transition:enter-start="opacity-0"')
        ->and($source)->toContain('x-transition:enter-start="translate-x-full"')
        ->and($source)->toContain('x-transition:leave-end="translate-x-full"');
});

it('18. closes when users click away from the panel', function () {
    $html = ($this->renderSlideOverShell)(
        <<<'BLADE'
<x-slide-over-shell open="isOpen" close="closePanel()" title="Create" title-id="create-title">
    <p>Form fields</p>
</x-slide-over-shell>
BLADE
    );

    expect($html)->toContain('x-on:click="closePanel()"')
        ->and($html)->toContain('x-on:click.stop');
});

it('19. material create slide-over uses the shared shell component', function () {
    $source = ($this->materialCreateSource)();

    expect($source)->toContain('<x-slide-over-shell')
        ->and($source)->not->toContain('fixed inset-0 z-50 overflow-hidden')
        ->and($source)->not->toContain('<form class="flex h-full flex-col bg-white shadow-xl"');
});

it('20. material edit slide-over uses the shared shell component', function () {
    $source = ($this->materialEditSource)();

    expect($source)->toContain('<x-slide-over-shell')
        ->and($source)->not->toContain('fixed inset-0 z-50 overflow-hidden')
        ->and($source)->not->toContain('<form class="flex h-full flex-col bg-white shadow-xl"');
});

it('21. recipe create and edit slide-overs use the shared shell component', function () {
    expect(($this->recipeCreateSource)())->toContain('<x-slide-over-shell')
        ->and(($this->recipeEditSource)())->toContain('<x-slide-over-shell');
});

it('22. recipe version and line slide-overs use the shared shell component', function () {
    expect(($this->recipeVersionSource)())->toContain('<x-slide-over-shell')
        ->and(($this->recipeLineSource)())->toContain('<x-slide-over-shell');
});

it('23. make order create and edit slide-over uses the shared shell component', function () {
    $source = ($this->makeOrderSource)();

    expect($source)->toContain('<x-slide-over-shell')
        ->and($source)->toContain('submitMakeOrderForm()');
});

it('23a. create slide-over date fields open the native picker when clicking the field', function () {
    expect(($this->makeOrderSource)())->toContain('x-on:click="$el.showPicker?.()"')
        ->and(($this->salesOrdersSource)())->toContain('x-on:click="$el.showPicker?.()"')
        ->and(($this->inventoryCountFormSource)())->toContain('x-on:click="$el.showPicker?.()"');
});

it('24. configured CRUD index form drawers use the shared shell component', function () {
    expect(($this->adminUsersSource)())->toContain('<x-slide-over-shell')
        ->and(($this->salesProductsSource)())->toContain('<x-slide-over-shell')
        ->and(($this->salesCustomersSource)())->toContain('<x-slide-over-shell')
        ->and(($this->salesOrdersSource)())->toContain('<x-slide-over-shell')
        ->and(($this->suppliersSource)())->toContain('<x-slide-over-shell');
});

it('25. customer detail form drawers use the shared shell component', function () {
    $source = ($this->salesCustomerDetailSource)();

    expect(substr_count($source, '<x-slide-over-shell'))->toBeGreaterThanOrEqual(3)
        ->and($source)->toContain('submitContactForm()')
        ->and($source)->toContain('submitOrderForm()');
});

it('26. inventory count form drawers use the shared shell component', function () {
    expect(($this->inventoryCountFormSource)())->toContain('<x-slide-over-shell')
        ->and(($this->inventoryCountLineFormSource)())->toContain('<x-slide-over-shell');
});

it('27. uom create and edit drawers use the shared shell component', function () {
    expect(($this->uomsSource)())->toContain('<x-slide-over-shell')
        ->and(($this->uomConversionsSource)())->toContain('<x-slide-over-shell');
});

it('28. converted blade views do not keep bespoke right drawer form shells', function () {
    $sources = [
        ($this->adminUsersSource)(),
        ($this->salesProductsSource)(),
        ($this->salesCustomersSource)(),
        ($this->salesCustomerDetailSource)(),
        ($this->salesOrdersSource)(),
        ($this->suppliersSource)(),
        ($this->inventoryCountFormSource)(),
        ($this->inventoryCountLineFormSource)(),
        ($this->uomsSource)(),
        ($this->uomConversionsSource)(),
        ($this->materialCreateSource)(),
        ($this->materialEditSource)(),
        ($this->recipeCreateSource)(),
        ($this->recipeEditSource)(),
        ($this->recipeVersionSource)(),
        ($this->recipeLineSource)(),
        ($this->makeOrderSource)(),
    ];

    foreach ($sources as $source) {
        expect($source)->not->toContain('<form class="flex h-full flex-col bg-white shadow-xl"')
            ->and($source)->not->toContain('<form class="h-full flex flex-col"')
            ->and($source)->not->toContain('pointer-events-none fixed inset-y-0 right-0');
    }
});

it('29. configured detail section create drawer mirrors the shared slide-over shell structure', function () {
    $source = ($this->jsCrudSectionSource)();

    expect($source)->toContain('role="dialog"')
        ->and($source)->toContain('aria-modal="true"')
        ->and($source)->toContain('fixed inset-0 z-50 overflow-hidden')
        ->and($source)->toContain('pointer-events-none fixed inset-y-0 right-0 flex max-w-full pl-10')
        ->and($source)->toContain('relative flex h-full flex-col divide-y divide-gray-200 bg-white shadow-xl')
        ->and($source)->not->toContain('fixed inset-0 z-40 flex justify-end bg-gray-900/30')
        ->and($source)->not->toContain('flex h-full w-full max-w-xl flex-col overflow-y-auto bg-white shadow-xl');
});
