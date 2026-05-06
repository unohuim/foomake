<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\File;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->renderCombobox = function (string $view, array $data = []): string {
        return Blade::render($view, $data);
    };

    $this->comboboxSource = fn (): string => File::get(resource_path('views/components/combobox.blade.php'));
    $this->comboItemSource = fn (): string => File::get(resource_path('views/components/combo-item.blade.php'));
    $this->comboboxJsSource = fn (): string => File::get(resource_path('js/components/combobox.js'));
});

it('1. renders the combobox component wrapper', function () {
    $html = ($this->renderCombobox)(
        <<<'BLADE'
<x-combobox name="item_id" />
BLADE
    );

    expect($html)->toContain('data-combobox-root');
});

it('2. renders combo item markup for slot-based options', function () {
    $html = ($this->renderCombobox)(
        <<<'BLADE'
<x-combobox name="item_id">
    <x-combo-item value="123" label="Widget" />
</x-combobox>
BLADE
    );

    expect($html)->toContain('data-combo-item');
});

it('3. renders a searchable input', function () {
    $html = ($this->renderCombobox)(
        <<<'BLADE'
<x-combobox name="item_id" />
BLADE
    );

    expect($html)->toContain('type="text"')
        ->and($html)->toContain('role="combobox"');
});

it('4. renders a dropdown listbox', function () {
    $html = ($this->renderCombobox)(
        <<<'BLADE'
<x-combobox name="item_id" />
BLADE
    );

    expect($html)->toContain('role="listbox"');
});

it('5. renders a hidden input with the configured field name', function () {
    $html = ($this->renderCombobox)(
        <<<'BLADE'
<x-combobox name="output_item" />
BLADE
    );

    expect($html)->toContain('type="hidden"')
        ->and($html)->toContain('name="output_item"');
});

it('6. hidden input can use name item_id', function () {
    $html = ($this->renderCombobox)(
        <<<'BLADE'
<x-combobox name="item_id" />
BLADE
    );

    expect($html)->toContain('name="item_id"');
});

it('7. selected option value is represented as the selected item id', function () {
    $html = ($this->renderCombobox)(
        <<<'BLADE'
<x-combobox name="item_id" :selected-value="15" />
BLADE
    );

    expect($html)->toContain('selectedValue')
        ->and($html)->toContain('15');
});

it('8. component supports option metadata in json state', function () {
    $html = ($this->renderCombobox)(
        <<<'BLADE'
<x-combobox
    name="item_id"
    :options="[['value' => 7, 'label' => 'Bread', 'meta' => ['uom_display' => 'Each', 'has_recipe' => true]]]"
/>
BLADE
    );

    expect($html)->toContain('uom_display')
        ->and($html)->toContain('has_recipe');
});

it('9. validation error text renders below the combobox', function () {
    $html = ($this->renderCombobox)(
        <<<'BLADE'
<x-combobox name="item_id" :error-messages="['Output item is required.']" />
BLADE
    );

    expect($html)->toContain('Output item is required.');
});

it('10. no external combobox package is introduced', function () {
    $packageLock = File::get(base_path('package-lock.json'));

    expect($packageLock)->not->toContain('headlessui')
        ->and($packageLock)->not->toContain('downshift');
});

it('11. no global javascript state is introduced for the combobox', function () {
    $source = ($this->comboboxJsSource)();

    expect($source)->not->toContain('window.combobox')
        ->and($source)->not->toContain('window.Combobox');
});

it('12. arrow down keyboard behavior exists in component source', function () {
    $source = ($this->comboboxSource)();

    expect($source)->toContain('keydown.arrow-down');
});

it('13. arrow up keyboard behavior exists in component source', function () {
    $source = ($this->comboboxSource)();

    expect($source)->toContain('keydown.arrow-up');
});

it('14. enter selection behavior exists in component source', function () {
    $source = ($this->comboboxSource)();

    expect($source)->toContain('keydown.enter');
});

it('15. escape close behavior exists in component source', function () {
    $source = ($this->comboboxSource)();

    expect($source)->toContain('keydown.escape');
});

it('16. highlighted option state exists', function () {
    $source = ($this->comboboxJsSource)();

    expect($source)->toContain('highlightedIndex');
});

it('17. combo item source preserves metadata for slot usage', function () {
    $source = ($this->comboItemSource)();

    expect($source)->toContain('data-combo-item')
        ->and($source)->toContain('data-item=');
});

it('18. combobox supports slot based x-combo-item usage', function () {
    $html = ($this->renderCombobox)(
        <<<'BLADE'
<x-combobox name="item_id">
    <x-combo-item value="11" label="Starter" :meta="['search_text' => 'starter dough']" />
</x-combobox>
BLADE
    );

    expect($html)->toContain('Starter')
        ->and($html)->toContain('starter dough');
});

it('19. combobox supports a custom no results message', function () {
    $html = ($this->renderCombobox)(
        <<<'BLADE'
<x-combobox name="item_id" no-results-text="No items found." />
BLADE
    );

    expect($html)->toContain('No items found.');
});

it('20. combobox supports a custom placeholder', function () {
    $html = ($this->renderCombobox)(
        <<<'BLADE'
<x-combobox name="item_id" placeholder="Find an item" />
BLADE
    );

    expect($html)->toContain('Find an item');
});

it('21. combobox exposes option state for metadata driven filtering and display', function () {
    $source = ($this->comboboxJsSource)();

    expect($source)->toContain('normalizedOptions')
        ->and($source)->toContain('search_text')
        ->and($source)->toContain('meta');
});
