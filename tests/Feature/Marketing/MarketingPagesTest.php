<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;

beforeEach(function (): void {
    $this->marketingSlugs = [
        'food-manufacturing-mrp',
        'inventory-management-for-food-manufacturers',
        'recipe-management-software',
        'purchase-order-software-for-food-manufacturers',
        'production-planning-for-small-food-manufacturers',
        'mrp-for-small-manufacturers',
    ];

    $this->marketingFile = fn (string $slug): string => resource_path("content/marketing/{$slug}.md");
});

afterEach(function (): void {
    $temporaryFiles = [
        resource_path('content/marketing/unsafe-script-test.md'),
        resource_path('content/marketing/noindex-test.md'),
    ];

    foreach ($temporaryFiles as $file) {
        if (File::exists($file)) {
            File::delete($file);
        }
    }
});

it('1. renders an existing markdown marketing page', function (): void {
    $this->get('/learn/food-manufacturing-mrp')
        ->assertOk()
        ->assertSee('MRP software built for small food manufacturers');
});

it('2. returns 404 for a missing marketing slug', function (): void {
    $this->get('/learn/not-a-real-page')
        ->assertNotFound();
});

it('3. rejects unsafe traversal slugs', function (): void {
    $this->get('/learn/../.env')
        ->assertNotFound();
});

it('4. parses front matter title into the rendered page', function (): void {
    $this->get('/learn/food-manufacturing-mrp')
        ->assertSee('<title>MRP Software for Small Food Manufacturers | FooMake</title>', false);
});

it('5. parses front matter description into the rendered page', function (): void {
    $this->get('/learn/food-manufacturing-mrp')
        ->assertSee(
            '<meta name="description" content="FooMake helps small food manufacturers manage recipes, inventory, purchasing, production, and sales orders without spreadsheets.">',
            false,
        );
});

it('6. validates slug front matter against the requested slug', function (): void {
    $this->get('/learn/food-manufacturing-mrp')
        ->assertOk()
        ->assertSee('content="http://localhost/learn/food-manufacturing-mrp"', false);
});

it('7. renders the page headline', function (): void {
    $this->get('/learn/inventory-management-for-food-manufacturers')
        ->assertOk()
        ->assertSee('Inventory management for small food manufacturers');
});

it('8. renders markdown body content', function (): void {
    $this->get('/learn/recipe-management-software')
        ->assertOk()
        ->assertSee('Recipes are the production memory of a small food business.');
});

it('9. renders the CTA label', function (): void {
    $this->get('/learn/purchase-order-software-for-food-manufacturers')
        ->assertOk()
        ->assertSee('Start beta access');
});

it('10. renders the CTA URL', function (): void {
    $this->get('/learn/production-planning-for-small-food-manufacturers')
        ->assertOk()
        ->assertSee('href="/register"', false);
});

it('11. renders Open Graph title metadata', function (): void {
    $this->get('/learn/mrp-for-small-manufacturers')
        ->assertOk()
        ->assertSee('<meta property="og:title"', false);
});

it('12. renders Open Graph description metadata', function (): void {
    $this->get('/learn/mrp-for-small-manufacturers')
        ->assertOk()
        ->assertSee('<meta property="og:description"', false);
});

it('13. respects noindex front matter', function (): void {
    File::ensureDirectoryExists(resource_path('content/marketing'));
    File::put(resource_path('content/marketing/noindex-test.md'), <<<'MARKDOWN'
---
title: "Noindex Test"
description: "A temporary noindex test page."
slug: "noindex-test"
headline: "Noindex test"
cta_label: "Start beta access"
cta_url: "/register"
noindex: true
---

This page should not be indexed.
MARKDOWN);

    $this->get('/learn/noindex-test')
        ->assertOk()
        ->assertSee('<meta name="robots" content="noindex,nofollow">', false);
});

it('14. renders the food manufacturing MRP page', function (): void {
    $this->get('/learn/food-manufacturing-mrp')
        ->assertOk()
        ->assertSee('food manufacturing MRP');
});

it('15. renders the inventory management page', function (): void {
    $this->get('/learn/inventory-management-for-food-manufacturers')
        ->assertOk()
        ->assertSee('materials, supplier packs, purchase orders, inventory counts, and production');
});

it('16. renders the recipe management page', function (): void {
    $this->get('/learn/recipe-management-software')
        ->assertOk()
        ->assertSee('recipe management software');
});

it('17. renders the purchase order page', function (): void {
    $this->get('/learn/purchase-order-software-for-food-manufacturers')
        ->assertOk()
        ->assertSee('Purchase order software');
});

it('18. renders the production planning page', function (): void {
    $this->get('/learn/production-planning-for-small-food-manufacturers')
        ->assertOk()
        ->assertSee('Production planning');
});

it('19. renders the small manufacturer MRP page', function (): void {
    $this->get('/learn/mrp-for-small-manufacturers')
        ->assertOk()
        ->assertSee('MRP for small manufacturers');
});

it('20. marketing pages use the public marketing shell', function (): void {
    $this->get('/learn/food-manufacturing-mrp')
        ->assertOk()
        ->assertSee('data-marketing-page', false);
});

it('21. marketing pages do not require authentication', function (): void {
    $this->assertGuest();

    $this->get('/learn/food-manufacturing-mrp')
        ->assertOk();
});

it('22. marketing routes do not break login', function (): void {
    $this->get('/login')
        ->assertOk();
});

it('23. marketing routes do not break register', function (): void {
    $this->get('/register')
        ->assertOk();
});

it('24. marketing routes do not break protected dashboard route', function (): void {
    $this->get('/dashboard')
        ->assertRedirect('/login');
});

it('25. homepage includes links to marketing pages', function (): void {
    $this->get('/')
        ->assertOk()
        ->assertSee('/learn/food-manufacturing-mrp')
        ->assertSee('/learn/inventory-management-for-food-manufacturers')
        ->assertSee('/learn/recipe-management-software');
});

it('26. sitemap includes marketing pages', function (): void {
    $response = $this->get('/sitemap.xml')
        ->assertOk()
        ->assertHeader('Content-Type', 'application/xml');

    foreach ($this->marketingSlugs as $slug) {
        $response->assertSee("http://localhost/learn/{$slug}", false);
    }
});

it('27. markdown rendering strips unsafe script tags', function (): void {
    File::ensureDirectoryExists(resource_path('content/marketing'));
    File::put(resource_path('content/marketing/unsafe-script-test.md'), <<<'MARKDOWN'
---
title: "Unsafe Script Test"
description: "A temporary script sanitization test page."
slug: "unsafe-script-test"
headline: "Unsafe script test"
cta_label: "Start beta access"
cta_url: "/register"
---

This content is safe.

<script>alert('unsafe')</script>
MARKDOWN);

    $this->get('/learn/unsafe-script-test')
        ->assertOk()
        ->assertSee('This content is safe.')
        ->assertDontSee('<script>', false)
        ->assertDontSee("alert('unsafe')", false);
});

it('28. markdown source files exist for all starter pages', function (): void {
    foreach ($this->marketingSlugs as $slug) {
        expect(File::exists(($this->marketingFile)($slug)))->toBeTrue();
    }
});

it('29. marketing pages include internal links between related pages', function (): void {
    $this->get('/learn/food-manufacturing-mrp')
        ->assertOk()
        ->assertSee('/learn/inventory-management-for-food-manufacturers')
        ->assertSee('/learn/recipe-management-software')
        ->assertSee('/learn/purchase-order-software-for-food-manufacturers');
});

it('30. sitemap does not expose authenticated app routes', function (): void {
    $this->get('/sitemap.xml')
        ->assertOk()
        ->assertDontSee('/dashboard')
        ->assertDontSee('/materials')
        ->assertDontSee('/admin/users');
});

it('31. guest invitation acceptance routes exist', function (): void {
    expect(Route::has('invitations.register'))->toBeTrue()
        ->and(Route::has('invitations.register.store'))->toBeTrue();
});

it('32. admin invitation routes remain authenticated', function (): void {
    $this->get('/admin/users/invitations/1')
        ->assertRedirect('/login');
});

it('33. public marketing route namespace avoids root route shadowing', function (): void {
    $this->get('/materials')
        ->assertRedirect('/login');

    $this->get('/learn/materials')
        ->assertNotFound();
});
