<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\File;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->renderToast = function (string $view): string {
        return Blade::render($view);
    };

    $this->toastSource = fn (): string => File::get(resource_path('views/components/ui/toast.blade.php'));
    $this->materialsShowSource = fn (): string => File::get(resource_path('js/pages/materials-show.js'));
    $this->profileEditSource = fn (): string => File::get(resource_path('js/pages/profile-edit.js'));
});

it('1. renders a fixed toast notification shell', function (): void {
    $html = ($this->renderToast)(
        <<<'BLADE'
<x-ui.toast />
BLADE
    );

    expect($html)->toContain('fixed inset-x-3 top-3 z-50')
        ->and($html)->toContain('sm:right-6 sm:top-6 sm:w-full sm:max-w-sm')
        ->and($html)->toContain('rounded-lg bg-white shadow-lg');
});

it('2. renders assertive status semantics', function (): void {
    $html = ($this->renderToast)(
        <<<'BLADE'
<x-ui.toast />
BLADE
    );

    expect($html)->toContain('aria-live="assertive"')
        ->and($html)->toContain('role="status"');
});

it('3. binds default page-scoped toast expressions', function (): void {
    $html = ($this->renderToast)(
        <<<'BLADE'
<x-ui.toast />
BLADE
    );

    expect($html)->toContain('x-show="toast.visible"')
        ->and($html)->toContain('x-text="toast.message"')
        ->and($html)->toContain("x-show=\"toast.type === 'success'\"");
});

it('4. supports alternate Alpine state expressions', function (): void {
    $html = ($this->renderToast)(
        <<<'BLADE'
<x-ui.toast visible="toast.show" type="toast.kind" message="toast.copy" />
BLADE
    );

    expect($html)->toContain('x-show="toast.show"')
        ->and($html)->toContain('x-text="toast.copy"')
        ->and($html)->toContain("x-show=\"toast.kind === 'success'\"");
});

it('5. renders success and error icons', function (): void {
    $source = ($this->toastSource)();

    expect($source)->toContain("text-green-400")
        ->and($source)->toContain("text-red-400")
        ->and($source)->toContain("{{ \$type }} === 'success'")
        ->and($source)->toContain("{{ \$type }} !== 'success'");
});

it('6. uses Alpine transition directives', function (): void {
    $source = ($this->toastSource)();

    expect($source)->toContain('x-transition:enter="transform ease-out duration-300 transition"')
        ->and($source)->toContain('x-transition:leave="transition ease-in duration-100"');
});

it('7. supports a custom dismiss expression', function (): void {
    $html = ($this->renderToast)(
        <<<'BLADE'
<x-ui.toast visible="notice.visible" dismiss="dismissNotice()" />
BLADE
    );

    expect($html)->toContain('x-on:click="dismissNotice()"')
        ->and($html)->not->toContain('notice.visible = false');
});

it('8. page modules keep success toast timing at one and a half seconds', function (): void {
    expect(($this->materialsShowSource)())->toContain('}, 1500);')
        ->and(($this->profileEditSource)())->toContain('}, 1500);');
});
