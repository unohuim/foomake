<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\File;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->renderSmartNumberInput = function (string $view, array $data = []): string {
        return Blade::render($view, $data);
    };

    $this->componentSource = fn (): string => File::get(resource_path('views/components/ui/smart-number-input.blade.php'));
    $this->jsSource = fn (): string => File::get(resource_path('js/components/smart-number-input.js'));
    $this->appJsSource = fn (): string => File::get(resource_path('js/app.js'));
    $this->materialsCreateSource = fn (): string => File::get(resource_path('views/materials/partials/create-material-slide-over.blade.php'));
    $this->materialsEditSource = fn (): string => File::get(resource_path('views/materials/partials/edit-material-slide-over.blade.php'));
    $this->inventoryCountFormSource = fn (): string => File::get(resource_path('views/inventory/counts/partials/count-form.blade.php'));
    $this->recipeCreateSource = fn (): string => File::get(resource_path('views/manufacturing/recipes/partials/create-recipe-slide-over.blade.php'));
    $this->recipeVersionSource = fn (): string => File::get(resource_path('views/manufacturing/recipes/partials/create-recipe-version-slide-over.blade.php'));
    $this->recipeLineSource = fn (): string => File::get(resource_path('views/manufacturing/recipes/partials/line-form-slide-over.blade.php'));
    $this->makeOrderCreateSource = fn (): string => File::get(resource_path('views/manufacturing/make-orders/partials/create-make-order-slide-over.blade.php'));
    $this->makeOrderShowSource = fn (): string => File::get(resource_path('views/manufacturing/make-orders/show.blade.php'));
    $this->purchaseOrderShowSource = fn (): string => File::get(resource_path('views/purchasing/orders/show.blade.php'));
    $this->salesProductsSource = fn (): string => File::get(resource_path('views/sales/products/index.blade.php'));
    $this->crudSectionJsSource = fn (): string => File::get(resource_path('js/lib/js-crud-section.js'));
    $this->inventoryCountShowJsSource = fn (): string => File::get(resource_path('js/pages/inventory-count-show.js'));
    $this->inventoryCountControllerSource = fn (): string => File::get(app_path('Http/Controllers/InventoryCountController.php'));
});

it('1. renders a smart number input wrapper', function (): void {
    $html = ($this->renderSmartNumberInput)(
        <<<'BLADE'
<x-ui.smart-number-input name="quantity" />
BLADE
    );

    expect($html)->toContain('data-smart-number-input-root');
});

it('2. renders a visible text input', function (): void {
    $html = ($this->renderSmartNumberInput)(
        <<<'BLADE'
<x-ui.smart-number-input name="quantity" />
BLADE
    );

    expect($html)->toContain('type="text"')
        ->and($html)->toContain('x-model="displayValue"');
});

it('3. renders a hidden canonical input with the configured name', function (): void {
    $html = ($this->renderSmartNumberInput)(
        <<<'BLADE'
<x-ui.smart-number-input name="shipping_cents" />
BLADE
    );

    expect($html)->toContain('type="hidden"')
        ->and($html)->toContain('name="shipping_cents"')
        ->and($html)->toContain('x-model="rawValue"');
});

it('4. supports integer type configuration', function (): void {
    $html = ($this->renderSmartNumberInput)(
        <<<'BLADE'
<x-ui.smart-number-input name="units" type="integer" :value="1234" />
BLADE
    );

    expect($html)->toContain('"type":"integer"')
        ->and($html)->toContain('"value":"1234"');
});

it('5. integer formatting uses thousands separators and no decimals', function (): void {
    $source = ($this->jsSource)();

    expect($source)->toContain('formatIntegerPart')
        ->and($source)->toContain('replace(/\\B(?=(\\d{3})+(?!\\d))/g,')
        ->and($source)->toContain("if (this.type === 'integer')");
});

it('6. supports decimal type configuration', function (): void {
    $html = ($this->renderSmartNumberInput)(
        <<<'BLADE'
<x-ui.smart-number-input name="quantity" type="decimal" precision="6" value="1234.500000" />
BLADE
    );

    expect($html)->toContain('"type":"decimal"')
        ->and($html)->toContain('"precision":6');
});

it('7. decimal formatting respects configured precision without float casts', function (): void {
    $source = ($this->jsSource)();

    expect($source)->toContain('normalizeFraction')
        ->and($source)->toContain('slice(0, this.precision)')
        ->and($source)->not->toContain('parseFloat')
        ->and($source)->not->toContain('Number(');
});

it('8. supports money type configuration', function (): void {
    $html = ($this->renderSmartNumberInput)(
        <<<'BLADE'
<x-ui.smart-number-input name="default_price_amount" type="money" currency="USD" value="1234.56" />
BLADE
    );

    expect($html)->toContain('"type":"money"')
        ->and($html)->toContain('"currency":"USD"');
});

it('9. money type renders a currency prefix or suffix zone', function (): void {
    $html = ($this->renderSmartNumberInput)(
        <<<'BLADE'
<x-ui.smart-number-input name="default_price_amount" type="money" currency="USD" prefix="$" />
BLADE
    );

    expect($html)->toContain('$')
        ->and($html)->toContain('USD')
        ->and($html)->toContain('pointer-events-none');
});

it('10. money type supports cents backed canonical values', function (): void {
    $html = ($this->renderSmartNumberInput)(
        <<<'BLADE'
<x-ui.smart-number-input name="unit_price_cents" type="money" raw-mode="cents" currency="USD" value="123456" />
BLADE
    );

    expect($html)->toContain('"rawMode":"cents"')
        ->and($html)->toContain('"value":"123456"');
});

it('11. supports percent type configuration', function (): void {
    $html = ($this->renderSmartNumberInput)(
        <<<'BLADE'
<x-ui.smart-number-input name="tax_percent" type="percent" value="12.5" />
BLADE
    );

    expect($html)->toContain('"type":"percent"')
        ->and($html)->toContain('%');
});

it('12. preserves passed attributes', function (): void {
    $html = ($this->renderSmartNumberInput)(
        <<<'BLADE'
<x-ui.smart-number-input name="quantity" class="w-24" data-testid="quantity-input" />
BLADE
    );

    expect($html)->toContain('w-24')
        ->and($html)->toContain('data-testid="quantity-input"');
});

it('13. renders disabled state', function (): void {
    $html = ($this->renderSmartNumberInput)(
        <<<'BLADE'
<x-ui.smart-number-input name="quantity" :disabled="true" />
BLADE
    );

    expect($html)->toContain('disabled')
        ->and($html)->toContain('disabled:cursor-not-allowed');
});

it('14. renders readonly state', function (): void {
    $html = ($this->renderSmartNumberInput)(
        <<<'BLADE'
<x-ui.smart-number-input name="quantity" :readonly="true" />
BLADE
    );

    expect($html)->toContain('readonly')
        ->and($html)->toContain('aria-readonly="true"');
});

it('15. includes local Alpine initialization', function (): void {
    $html = ($this->renderSmartNumberInput)(
        <<<'BLADE'
<x-ui.smart-number-input name="quantity" />
BLADE
    );

    expect($html)->toContain('x-data="smartNumberInput(')
        ->and($html)->toContain('x-modelable="rawValue"');
});

it('16. dispatches configured events on input change and blur', function (): void {
    $source = ($this->componentSource)();

    expect($source)->toContain('handleInput($event)')
        ->and($source)->toContain('handleChange($event)')
        ->and($source)->toContain('handleBlur($event)');
});

it('17. event payload includes required fields', function (): void {
    $source = ($this->jsSource)();

    expect($source)->toContain('name: this.name')
        ->and($source)->toContain('rawValue: this.rawValue')
        ->and($source)->toContain('displayValue: this.displayValue')
        ->and($source)->toContain('type: this.type')
        ->and($source)->toContain('currency: this.currency')
        ->and($source)->toContain('precision: this.precision')
        ->and($source)->toContain('source: sourceEventType');
});

it('17a. emits a default changed event for parent listeners', function (): void {
    $source = ($this->jsSource)();

    expect($source)->toContain("defaultChangedEvent: 'smart-number-input:changed'")
        ->and($source)->toContain('this.$dispatch(this.defaultChangedEvent');
});

it('17b. debounces input changed events by default', function (): void {
    $source = ($this->jsSource)();
    $componentSource = ($this->componentSource)();

    expect($source)->toContain('debounceMs: parseInt(config.debounceMs ?? 300, 10)')
        ->and($source)->toContain("this.dispatchDebouncedEvent('input')")
        ->and($source)->toContain('window.setTimeout(() => {')
        ->and($componentSource)->toContain("'debounceMs' => 300")
        ->and($componentSource)->toContain('debounceMs: {{ (int) $debounceMs }}');
});

it('17c. change and blur events cancel pending input debounce before dispatching', function (): void {
    $source = ($this->jsSource)();

    expect($source)->toContain('this.clearDebouncedEvent()')
        ->and($source)->toContain("this.dispatchConfiguredEvent('change')")
        ->and($source)->toContain("this.dispatchConfiguredEvent('blur')");
});

it('18. live input formatting inserts commas while preserving canonical raw values', function (): void {
    $source = ($this->jsSource)();

    expect($source)->toContain('formatDisplayValue(this.rawValue)')
        ->and($source)->toContain('this.rawValue = this.toRawValue(this.displayValue)')
        ->and($source)->toContain('restoreCaretPosition');
});

it('19. display formatting does not mutate canonical submitted value unexpectedly', function (): void {
    $source = ($this->jsSource)();

    expect($source)->toContain('toRawValue')
        ->and($source)->toContain('stripGrouping')
        ->and($source)->toContain("replace(/,/g, '')");
});

it('20. quantity fields can keep canonical string values through x model binding', function (): void {
    $html = ($this->renderSmartNumberInput)(
        <<<'BLADE'
<x-ui.smart-number-input name="quantity" type="decimal" precision="6" x-model="line.quantity_input" />
BLADE
    );

    expect($html)->toContain('x-model="line.quantity_input"')
        ->and($html)->toContain('x-modelable="rawValue"');
});

it('21. component is registered through the app bundle', function (): void {
    $source = ($this->appJsSource)();

    expect($source)->toContain("import { registerSmartNumberInput } from './components/smart-number-input'")
        ->and($source)->toContain('registerSmartNumberInput(Alpine)');
});

it('22. no global javascript state is introduced for smart number inputs', function (): void {
    $source = ($this->jsSource)();

    expect($source)->not->toContain('window.smartNumberInput')
        ->and($source)->not->toContain('window.SmartNumberInput');
});

it('23. no inline style attribute is introduced by the component', function (): void {
    $source = ($this->componentSource)();

    expect($source)->not->toContain('style=')
        ->and($source)->toContain('x-bind:size');
});

it('24. no float math is introduced for quantity or money formatting', function (): void {
    $source = ($this->jsSource)();

    expect($source)->not->toContain('parseFloat')
        ->and($source)->not->toContain('Math.round')
        ->and($source)->not->toContain('Number(');
});

it('25. material create starting quantity uses smart number input where migrated', function (): void {
    $source = ($this->materialsCreateSource)();

    expect($source)->toContain('<x-ui.smart-number-input')
        ->and($source)->toContain('name="starting_quantity"')
        ->and($source)->toContain('x-model="form.starting_quantity"');
});

it('26. material edit planning price uses smart number input where migrated', function (): void {
    $source = ($this->materialsEditSource)();

    expect($source)->toContain('<x-ui.smart-number-input')
        ->and($source)->toContain('name="default_price_amount"')
        ->and($source)->toContain('x-model="editForm.default_price_amount"');
});

it('27. inventory count counted quantity forms use smart number input where migrated', function (): void {
    $source = ($this->inventoryCountFormSource)();

    expect($source)->toContain('<x-ui.smart-number-input')
        ->and($source)->toContain('name="counted_quantity"');
});

it('28. recipe quantity forms use smart number input where migrated', function (): void {
    expect(($this->recipeCreateSource)())->toContain('<x-ui.smart-number-input')
        ->and(($this->recipeVersionSource)())->toContain('<x-ui.smart-number-input')
        ->and(($this->recipeLineSource)())->toContain('<x-ui.smart-number-input');
});

it('29. make order quantity fields use smart number input where migrated', function (): void {
    expect(($this->makeOrderCreateSource)())->toContain('<x-ui.smart-number-input')
        ->and(($this->makeOrderShowSource)())->toContain('<x-ui.smart-number-input');
});

it('30. purchase order numeric fields use smart number input where migrated', function (): void {
    $source = ($this->purchaseOrderShowSource)();

    expect($source)->toContain('<x-ui.smart-number-input')
        ->and($source)->toContain('name="shipping_amount"');
});

it('31. product price fields use smart number input where migrated', function (): void {
    $source = ($this->salesProductsSource)();

    expect($source)->toContain('<x-ui.smart-number-input')
        ->and($source)->toContain('name="default_price_amount"');
});

it('32. shared js crud section can render smart number row meta inputs', function (): void {
    $source = ($this->crudSectionJsSource)();

    expect($source)->toContain("meta.type === 'smart-number'")
        ->and($source)->toContain('smartNumberInput({')
        ->and($source)->toContain('data-smart-number-input-root');
});

it('32a. shared js crud section can render smart number create form fields', function (): void {
    $source = ($this->crudSectionJsSource)();

    expect($source)->toContain("field.type === 'smart-number'")
        ->and($source)->toContain('x-data="smartNumberInput({')
        ->and($source)->toContain('name: field.name')
        ->and($source)->toContain('x-model="form[field.name]"');
});

it('32b. shared js crud section resolves money field currency from selected options', function (): void {
    $source = ($this->crudSectionJsSource)();

    expect($source)->toContain('currency: smartNumberFieldCurrency(field)')
        ->and($source)->toContain("field.numberType === 'money' && smartNumberFieldCurrency(field) !== ''")
        ->and($source)->toContain('x-text="smartNumberFieldCurrency(field)"')
        ->and($source)->toContain('currencyFromField')
        ->and($source)->toContain('currencyOptionField');
});

it('32c. shared js crud section supports configured asymmetric field rows', function (): void {
    $source = ($this->crudSectionJsSource)();

    expect($source)->toContain('width: asString(safeField.width)')
        ->and($source)->toContain('fieldWrapperClass(field)')
        ->and($source)->toContain("field.width === 'short'")
        ->and($source)->toContain("field.width === 'right'")
        ->and($source)->toContain('sm:grid-cols-[minmax(0,10rem)_minmax(0,1fr)]');
});

it('33. js smart number row meta reuses the same Alpine data module', function (): void {
    $source = ($this->crudSectionJsSource)();

    expect($source)->toContain('x-data="smartNumberInput({')
        ->and($source)->toContain('x-modelable="rawValue"')
        ->and($source)->toContain('x-model="displayValue"')
        ->and($source)->toContain('debounceMs: meta.debounceMs || 300');
});

it('34. js smart number row meta syncs raw values back to the row record', function (): void {
    $source = ($this->crudSectionJsSource)();

    expect($source)->toContain('syncSmartNumberMetaValue(record, meta, rawValue)')
        ->and($source)->toContain('record[meta.field] = rawValue');
});

it('35. js smart number row meta preserves existing inline action callbacks', function (): void {
    $source = ($this->crudSectionJsSource)();

    expect($source)->toContain('performInlineMetaAction(record, meta)')
        ->and($source)->toContain('handleSmartNumberMetaChanged(record, meta, $event.detail)')
        ->and($source)->toContain('emitOnChange: false')
        ->and($source)->not->toContain('handleChange($event); syncSmartNumberMetaValue(record, meta, rawValue); performInlineMetaAction(record, meta)')
        ->and($source)->not->toContain('handleBlur($event); syncSmartNumberMetaValue(record, meta, rawValue); performInlineMetaAction(record, meta)');
});

it('35a. js smart number row meta saves from debounced component change events', function (): void {
    $source = ($this->crudSectionJsSource)();

    expect($source)->toContain('x-on:smart-number-input:changed="handleSmartNumberMetaChanged(record, meta, $event.detail)"')
        ->and($source)->toContain('async handleSmartNumberMetaChanged(record, meta, detail)')
        ->and($source)->toContain('this.syncSmartNumberMetaValue(record, meta, detail?.rawValue)')
        ->and($source)->toContain('await this.performInlineMetaAction(record, meta)');
});

it('36. inventory count row qty uses js smart number meta contract', function (): void {
    $source = ($this->inventoryCountShowJsSource)();

    expect($source)->toContain("type: 'smart-number'")
        ->and($source)->toContain("numberType: 'decimal'")
        ->and($source)->toContain("precisionField: 'uom_display_precision'");
});

it('37. inventory count row qty precision is owned by the item uom', function (): void {
    $source = ($this->inventoryCountControllerSource)();

    expect($source)->toContain("'uom_display_precision' =>")
        ->and($source)->toContain('$line->item?->baseUom?->display_precision');
});

it('38. inventory count row qty no longer uses the plain input meta type', function (): void {
    $source = ($this->inventoryCountShowJsSource)();

    expect($source)->not->toContain("type: 'input',\n                                handlerKey: 'updateCountedQuantity'");
});

it('39. smart number architecture documents blade versus js usage', function (): void {
    $source = File::get(base_path('docs/architecture/ui/SmartNumberInput.yaml'));

    expect($source)->toContain('Blade-rendered numeric fields must use the Blade component.')
        ->and($source)->toContain('JavaScript-rendered numeric fields must use the shared JavaScript smart-number renderer.')
        ->and($source)->toContain('smart-number-input:changed')
        ->and($source)->toContain('Input-source changed events must debounce for 300 milliseconds by default')
        ->and($source)->toContain('Save-success UI such as a green checkmark must be owned by parent page logic');
});

it('40. architecture inventory documents blade versus js usage', function (): void {
    $source = File::get(base_path('docs/ARCHITECTURE_INVENTORY.md'));

    expect($source)->toContain('Blade-rendered numeric fields use')
        ->and($source)->toContain('JavaScript-rendered numeric fields use the shared JS smart-number renderer')
        ->and($source)->toContain('Both renderers dispatch `smart-number-input:changed`')
        ->and($source)->toContain('Input-source changed events debounce for 300 milliseconds by default')
        ->and($source)->toContain('Save-success indicators such as green checkmarks belong to parent page logic');
});

it('41. inventory count row qty success feedback only reflects successful saves', function (): void {
    $source = ($this->inventoryCountShowJsSource)();

    expect($source)->toContain("showSuccessIcon: Boolean(safeRecord._countedQuantitySaved)")
        ->and($source)->not->toContain('hasCountedQuantityFeedback')
        ->and($source)->not->toContain('nextValue !== previousValue');
});

it('42. inventory count row qty keeps success feedback after blur save succeeds', function (): void {
    $source = ($this->inventoryCountShowJsSource)();

    expect($source)->toContain('updatedLine._countedQuantitySaved = true')
        ->and($source)->toContain("showSuccessIcon: Boolean(safeRecord._countedQuantitySaved)");
});
