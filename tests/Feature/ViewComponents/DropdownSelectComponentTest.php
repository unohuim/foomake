<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\File;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->renderDropdown = function (string $view, array $data = []): string {
        return Blade::render($view, $data);
    };

    $this->dropdownSource = fn (): string => File::get(resource_path('views/components/dropdown-select.blade.php'));
    $this->optionSource = fn (): string => File::get(resource_path('views/components/dropdown-option.blade.php'));
    $this->dropdownJsSource = fn (): string => File::get(resource_path('js/components/dropdown-select.js'));
});

it('1. renders the dropdown select component wrapper', function () {
    $html = ($this->renderDropdown)(
        <<<'BLADE'
<x-dropdown-select name="recipe_type" />
BLADE
    );

    expect($html)->toContain('data-dropdown-select-root');
});

it('2. renders dropdown option markup for slot based options', function () {
    $html = ($this->renderDropdown)(
        <<<'BLADE'
<x-dropdown-select name="recipe_type">
    <x-dropdown-option value="manufacturing">Manufacturing</x-dropdown-option>
</x-dropdown-select>
BLADE
    );

    expect($html)->toContain('data-dropdown-option');
});

it('3. renders a trigger control', function () {
    $html = ($this->renderDropdown)(
        <<<'BLADE'
<x-dropdown-select name="recipe_type" />
BLADE
    );

    expect($html)->toContain('type="button"')
        ->and($html)->toContain('aria-haspopup="listbox"');
});

it('4. renders a dropdown listbox', function () {
    $html = ($this->renderDropdown)(
        <<<'BLADE'
<x-dropdown-select name="recipe_type" />
BLADE
    );

    expect($html)->toContain('role="listbox"');
});

it('5. renders a hidden input with the configured field name', function () {
    $html = ($this->renderDropdown)(
        <<<'BLADE'
<x-dropdown-select name="output_type" />
BLADE
    );

    expect($html)->toContain('type="hidden"')
        ->and($html)->toContain('name="output_type"');
});

it('6. hidden input can use name recipe_type', function () {
    $html = ($this->renderDropdown)(
        <<<'BLADE'
<x-dropdown-select name="recipe_type" />
BLADE
    );

    expect($html)->toContain('name="recipe_type"');
});

it('7. selected option value is represented as the stored value', function () {
    $html = ($this->renderDropdown)(
        <<<'BLADE'
<x-dropdown-select name="recipe_type" selected-value="fulfillment" />
BLADE
    );

    expect($html)->toContain('selectedValue')
        ->and($html)->toContain('fulfillment');
});

it('8. component displays the selected label', function () {
    $html = ($this->renderDropdown)(
        <<<'BLADE'
<x-dropdown-select name="recipe_type" selected-value="manufacturing">
    <x-dropdown-option value="manufacturing">Manufacturing</x-dropdown-option>
</x-dropdown-select>
BLADE
    );

    expect($html)->toContain('selectedLabel');
});

it('9. component supports option metadata in json state', function () {
    $html = ($this->renderDropdown)(
        <<<'BLADE'
<x-dropdown-select
    name="recipe_type"
    :options="[['value' => 'manufacturing', 'label' => 'Manufacturing', 'meta' => ['display_name' => 'Manufacturing']]]"
/>
BLADE
    );

    expect($html)->toContain('display_name');
});

it('10. validation error text renders below the dropdown', function () {
    $html = ($this->renderDropdown)(
        <<<'BLADE'
<x-dropdown-select name="recipe_type" :error-messages="['Recipe type is required.']" />
BLADE
    );

    expect($html)->toContain('Recipe type is required.');
});

it('11. no external dropdown package is introduced', function () {
    $packageLock = File::get(base_path('package-lock.json'));

    expect($packageLock)->not->toContain('headlessui')
        ->and($packageLock)->not->toContain('radix')
        ->and($packageLock)->not->toContain('downshift');
});

it('12. no global javascript state is introduced for the dropdown', function () {
    $source = ($this->dropdownJsSource)();

    expect($source)->not->toContain('window.dropdownSelect')
        ->and($source)->not->toContain('window.DropdownSelect');
});

it('13. open and close behavior exists in component source', function () {
    $source = ($this->dropdownSource)();

    expect($source)->toContain('x-on:click')
        ->and($source)->toContain('x-on:click.outside');
});

it('14. option selection behavior exists in component source', function () {
    $source = ($this->dropdownSource)();

    expect($source)->toContain('selectOption');
});

it('15. escape close behavior exists in component source', function () {
    $source = ($this->dropdownSource)();

    expect($source)->toContain('keydown.escape');
});

it('16. highlighted option state exists', function () {
    $source = ($this->dropdownJsSource)();

    expect($source)->toContain('highlightedIndex');
});

it('17. dropdown option source preserves metadata for slot usage', function () {
    $source = ($this->optionSource)();

    expect($source)->toContain('data-dropdown-option')
        ->and($source)->toContain('data-option=');
});

it('18. dropdown select supports slot based x-dropdown-option usage', function () {
    $html = ($this->renderDropdown)(
        <<<'BLADE'
<x-dropdown-select name="recipe_type">
    <x-dropdown-option value="manufacturing" :meta="['search_text' => 'manufacturing recipe']">Manufacturing</x-dropdown-option>
</x-dropdown-select>
BLADE
    );

    expect($html)->toContain('Manufacturing')
        ->and($html)->toContain('manufacturing recipe');
});

it('19. create recipe form uses dropdown select and not a native recipe_type select', function () {
    $source = File::get(resource_path('views/manufacturing/recipes/partials/create-recipe-slide-over.blade.php'));

    expect($source)->toContain('<x-dropdown-select')
        ->and($source)->not->toContain('<select name="recipe_type"')
        ->and($source)->not->toContain('<select id="recipe_type"');
});

it('20. edit recipe form uses dropdown select and not a native recipe_type select', function () {
    $source = File::get(resource_path('views/manufacturing/recipes/partials/edit-recipe-slide-over.blade.php'));

    expect($source)->toContain('<x-dropdown-select')
        ->and($source)->not->toContain('<select name="recipe_type"')
        ->and($source)->not->toContain('<select id="recipe_type"');
});

it('21. component renders hidden input with configured name and selected label for recipe type', function () {
    $html = ($this->renderDropdown)(
        <<<'BLADE'
<x-dropdown-select name="recipe_type" selected-value="fulfillment">
    <x-dropdown-option value="manufacturing">Manufacturing</x-dropdown-option>
    <x-dropdown-option value="fulfillment">Fulfillment</x-dropdown-option>
</x-dropdown-select>
BLADE
    );

    expect($html)->toContain('name="recipe_type"')
        ->and($html)->toContain('selectedLabel')
        ->and($html)->toContain('Fulfillment');
});

it('22. create recipe slide-over renders manufacturing and fulfillment dropdown options', function () {
    $source = File::get(resource_path('views/manufacturing/recipes/partials/create-recipe-slide-over.blade.php'));

    expect($source)->toContain('<x-dropdown-option value="manufacturing">Manufacturing</x-dropdown-option>')
        ->and($source)->toContain('<x-dropdown-option value="fulfillment">Fulfillment</x-dropdown-option>');
});

it('23. create recipe dropdown default hidden value is manufacturing', function () {
    $source = File::get(resource_path('views/manufacturing/recipes/partials/create-recipe-slide-over.blade.php'));

    expect($source)->toContain('selected-value="manufacturing"')
        ->and($source)->toContain('x-model="createForm.recipe_type"')
        ->and($source)->toContain('name="recipe_type"');
});

it('24. changing the dropdown selection updates the submitted recipe_type value', function () {
    $source = ($this->dropdownJsSource)();

    expect($source)->toContain('this.selectedValue = String(option.value);')
        ->and($source)->toContain('this.selectedLabel = option.label;');
});

it('25. dropdown select supports explicit options data alongside slot based options', function () {
    $source = ($this->dropdownJsSource)();

    expect($source)->toContain('resolveOptionsFromExpression')
        ->and($source)->toContain('slotConfiguredOptions')
        ->and($source)->toContain('...this.slotConfiguredOptions');
});

it('26. recipe type dropdown keeps slot based x-dropdown-option markup while using dynamic options expression', function () {
    $source = File::get(resource_path('views/manufacturing/recipes/partials/create-recipe-slide-over.blade.php'));

    expect($source)->toContain('options-expression="availableCreateRecipeTypeOptions()"')
        ->and($source)->toContain('<x-dropdown-option value="manufacturing">Manufacturing</x-dropdown-option>')
        ->and($source)->toContain('<x-dropdown-option value="fulfillment">Fulfillment</x-dropdown-option>');
});
