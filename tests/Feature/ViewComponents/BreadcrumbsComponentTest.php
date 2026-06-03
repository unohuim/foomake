<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\File;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->renderBreadcrumbs = function (string $view): string {
        return Blade::render($view);
    };

    $this->breadcrumbsSource = fn (): string => File::get(resource_path('views/components/ui/breadcrumbs.blade.php'));
    $this->detailHeaderSource = fn (): string => File::get(resource_path('views/components/resource-detail-header-breadcrumb.blade.php'));
    $this->inventoryCountShowSource = fn (): string => File::get(resource_path('views/inventory/counts/show.blade.php'));
    $this->materialShowSource = fn (): string => File::get(resource_path('views/materials/show.blade.php'));
    $this->supplierShowSource = fn (): string => File::get(resource_path('views/purchasing/suppliers/show.blade.php'));
    $this->purchaseOrderShowSource = fn (): string => File::get(resource_path('views/purchasing/orders/show.blade.php'));
    $this->makeOrderShowSource = fn (): string => File::get(resource_path('views/manufacturing/make-orders/show.blade.php'));
    $this->recipeShowSource = fn (): string => File::get(resource_path('views/manufacturing/recipes/show.blade.php'));
    $this->salesOrderShowSource = fn (): string => File::get(resource_path('views/sales/orders/show.blade.php'));
    $this->salesCustomerShowSource = fn (): string => File::get(resource_path('views/sales/customers/show.blade.php'));
});

it('1. renders a breadcrumb nav with the expected aria label', function () {
    $html = ($this->renderBreadcrumbs)(
        <<<'BLADE'
<x-ui.breadcrumbs :items="[['label' => 'Materials', 'url' => '/materials'], ['label' => 'Flour']]" />
BLADE
    );

    expect($html)->toContain('<nav')
        ->and($html)->toContain('aria-label="Breadcrumb"');
});

it('2. renders the Home link first', function () {
    $html = ($this->renderBreadcrumbs)(
        <<<'BLADE'
<x-ui.breadcrumbs :items="[['label' => 'Materials', 'url' => '/materials']]" />
BLADE
    );

    expect($html)->toContain('href="http://localhost/dashboard"')
        ->and($html)->toContain('<span class="sr-only">Home</span>');
});

it('3. renders supplied breadcrumb labels', function () {
    $html = ($this->renderBreadcrumbs)(
        <<<'BLADE'
<x-ui.breadcrumbs :items="[['label' => 'Inventory Counts', 'url' => '/inventory/counts'], ['label' => 'ID# 1']]" />
BLADE
    );

    expect($html)->toContain('Inventory Counts')
        ->and($html)->toContain('ID# 1');
});

it('4. marks the last item current by default', function () {
    $html = ($this->renderBreadcrumbs)(
        <<<'BLADE'
<x-ui.breadcrumbs :items="[['label' => 'Suppliers', 'url' => '/purchasing/suppliers'], ['label' => 'Acme']]" />
BLADE
    );

    expect($html)->toContain('aria-current="page"')
        ->and($html)->toContain('Acme');
});

it('5. respects an explicit current item', function () {
    $html = ($this->renderBreadcrumbs)(
        <<<'BLADE'
<x-ui.breadcrumbs :items="[
    ['label' => 'Customers', 'url' => '/sales/customers', 'current' => true],
    ['label' => 'Archived'],
]" />
BLADE
    );

    expect($html)->toContain('aria-current="page"')
        ->and($html)->toContain('Customers');
});

it('6. renders chevron separators with the Tailwind Plus shape', function () {
    $html = ($this->renderBreadcrumbs)(
        <<<'BLADE'
<x-ui.breadcrumbs :items="[['label' => 'Recipes', 'url' => '/manufacturing/recipes'], ['label' => 'Batch']]" />
BLADE
    );

    expect($html)->toContain('viewBox="0 0 24 44"')
        ->and($html)->toContain('preserveAspectRatio="none"')
        ->and($html)->toContain('M.293 0l22 22-22 22h1.414l22-22-22-22H.293z');
});

it('7. uses full-width parent-spanning border-y styling', function () {
    $html = ($this->renderBreadcrumbs)(
        <<<'BLADE'
<x-ui.breadcrumbs :items="[['label' => 'Materials']]" />
BLADE
    );

    expect($html)->toContain('flex w-full border-y border-gray-200 bg-white');
});

it('8. keeps the inner list full width inside the parent', function () {
    $html = ($this->renderBreadcrumbs)(
        <<<'BLADE'
<x-ui.breadcrumbs :items="[['label' => 'Materials']]" />
BLADE
    );

    expect($html)->toContain('flex w-full items-center space-x-4 px-4 sm:px-6 lg:px-8');
});

it('9. can render a full-bleed page-width bar', function () {
    $html = ($this->renderBreadcrumbs)(
        <<<'BLADE'
<x-ui.breadcrumbs :full-bleed="true" :items="[['label' => 'Materials']]" />
BLADE
    );

    expect($html)->toContain('w-screen -translate-x-1/2 border-y border-gray-200 bg-white')
        ->and($html)->toContain('mx-auto flex w-full max-w-7xl items-center');
});

it('10. preserves passed attributes', function () {
    $html = ($this->renderBreadcrumbs)(
        <<<'BLADE'
<x-ui.breadcrumbs class="mt-4" data-testid="breadcrumbs" :items="[['label' => 'Materials']]" />
BLADE
    );

    expect($html)->toContain('data-testid="breadcrumbs"')
        ->and($html)->toContain('mt-4');
});

it('11. renders non-current URL items as links', function () {
    $html = ($this->renderBreadcrumbs)(
        <<<'BLADE'
<x-ui.breadcrumbs :items="[['label' => 'Materials', 'url' => '/materials'], ['label' => 'Flour']]" />
BLADE
    );

    expect($html)->toContain('href="/materials"')
        ->and($html)->toContain('Materials');
});

it('12. renders the current item safely without a URL', function () {
    $html = ($this->renderBreadcrumbs)(
        <<<'BLADE'
<x-ui.breadcrumbs :items="[['label' => 'Materials', 'url' => '/materials'], ['label' => 'Flour']]" />
BLADE
    );

    expect($html)->toContain('<span')
        ->and($html)->toContain('aria-current="page"')
        ->and($html)->toContain('Flour');
});

it('13. ignores a legacy leading Home item to prevent duplicate Home crumbs', function () {
    $html = ($this->renderBreadcrumbs)(
        <<<'BLADE'
<x-ui.breadcrumbs :items="[
    ['label' => 'Home', 'url' => '/'],
    ['label' => 'Materials', 'url' => '/materials'],
    ['label' => 'Flour'],
]" />
BLADE
    );

    expect(substr_count($html, 'sr-only">Home</span>'))->toBe(1);
});

it('14. does not drop the first non-home item', function () {
    $html = ($this->renderBreadcrumbs)(
        <<<'BLADE'
<x-ui.breadcrumbs :items="[
    ['label' => 'Suppliers', 'url' => '/purchasing/suppliers', 'current' => false],
    ['label' => 'Acme Foods', 'current' => true],
]" />
BLADE
    );

    expect($html)->toContain('Suppliers')
        ->and($html)->toContain('Acme Foods')
        ->and($html)->toContain('href="/purchasing/suppliers"');
});

it('15. detail header component uses the shared breadcrumbs component', function () {
    $source = ($this->detailHeaderSource)();

    expect($source)->toContain('<x-ui.breadcrumbs')
        ->and($source)->toContain('order-first')
        ->and($source)->toContain(':full-bleed="true"')
        ->and($source)->not->toContain('<x-resource-breadcrumbs');
});

it('16. inventory count detail breadcrumb links to Inventory Counts index', function () {
    $source = ($this->inventoryCountShowSource)();

    expect($source)->toContain("route('inventory.counts.index')")
        ->and($source)->toContain('<x-resource-detail-header-breadcrumb');
});

it('17. material detail breadcrumb links to Materials index', function () {
    $source = ($this->materialShowSource)();

    expect($source)->toContain("route('materials.index')")
        ->and($source)->toContain('<x-resource-detail-header-breadcrumb');
});

it('18. supplier detail breadcrumb links to Suppliers index', function () {
    $source = ($this->supplierShowSource)();

    expect($source)->toContain("route('purchasing.suppliers.index')")
        ->and($source)->toContain('<x-resource-detail-header-breadcrumb');
});

it('19. purchase order detail breadcrumb links to Purchase Orders index', function () {
    $source = ($this->purchaseOrderShowSource)();

    expect($source)->toContain("route('purchasing.orders.index')")
        ->and($source)->toContain('<x-resource-detail-header-breadcrumb');
});

it('20. make order detail breadcrumb links to Make Orders index through payload breadcrumbs', function () {
    $source = ($this->makeOrderShowSource)();

    expect($source)->toContain('<x-resource-detail-header-breadcrumb')
        ->and($source)->toContain("\$payload['breadcrumbs']");
});

it('21. recipe detail breadcrumb links to Recipes index', function () {
    $source = ($this->recipeShowSource)();

    expect($source)->toContain("route('manufacturing.recipes.index')")
        ->and($source)->toContain('<x-resource-detail-header-breadcrumb');
});

it('22. sales detail pages use the shared resource detail header breadcrumb component', function () {
    expect(($this->salesOrderShowSource)())->toContain('<x-resource-detail-header-breadcrumb')
        ->and(($this->salesCustomerShowSource)())->toContain('<x-resource-detail-header-breadcrumb');
});

it('23. old scattered breadcrumb component calls are removed from migrated pages', function () {
    $sources = [
        ($this->supplierShowSource)(),
        ($this->salesOrderShowSource)(),
        ($this->salesCustomerShowSource)(),
        ($this->detailHeaderSource)(),
    ];

    foreach ($sources as $source) {
        expect($source)->not->toContain('<x-resource-breadcrumbs');
    }
});

it('24. no Vue code or Heroicons Vue package is introduced', function () {
    $packageLock = File::get(base_path('package-lock.json'));
    $source = ($this->breadcrumbsSource)();

    expect($source)->not->toContain('<template>')
        ->and($source)->not->toContain('vue')
        ->and($packageLock)->not->toContain('@heroicons/vue');
});

it('25. no inline styles or CSS files are introduced for breadcrumbs', function () {
    $source = ($this->breadcrumbsSource)();

    expect($source)->not->toContain('style=')
        ->and(File::exists(resource_path('css/breadcrumbs.css')))->toBeFalse();
});

it('26. no global JavaScript state is introduced for breadcrumbs', function () {
    $source = ($this->breadcrumbsSource)();

    expect($source)->not->toContain('window.')
        ->and($source)->not->toContain('Alpine.data');
});
