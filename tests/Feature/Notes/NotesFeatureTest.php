<?php

use App\Models\Customer;
use App\Models\InventoryCount;
use App\Models\Item;
use App\Models\MakeOrder;
use App\Models\Note;
use App\Models\Permission;
use App\Models\PurchaseOrder;
use App\Models\Recipe;
use App\Models\RecipeVersion;
use App\Models\Role;
use App\Models\SalesOrder;
use App\Models\Supplier;
use App\Models\Tenant;
use App\Models\Uom;
use App\Models\UomCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->makeUser = function (Tenant $tenant): User {
        return User::factory()->create([
            'tenant_id' => $tenant->id,
        ]);
    };

    $this->grantPermission = function (User $user, string $slug): void {
        $permission = Permission::query()->firstOrCreate([
            'slug' => $slug,
        ]);

        $role = Role::query()->forceCreate([
            'name' => 'role-' . $slug . '-' . Str::random(10),
        ]);

        $role->permissions()->syncWithoutDetaching([$permission->id]);
        $user->roles()->syncWithoutDetaching([$role->id]);
    };

    $this->makeInventoryCount = function (Tenant $tenant, array $overrides = []): InventoryCount {
        return InventoryCount::query()->forceCreate(array_merge([
            'tenant_id' => $tenant->id,
            'counted_at' => Carbon::parse('2026-06-01 10:00:00'),
        ], $overrides));
    };

    $this->makePurchaseOrder = function (Tenant $tenant, array $overrides = []): PurchaseOrder {
        $supplier = Supplier::query()->forceCreate([
            'tenant_id' => $tenant->id,
            'company_name' => 'Supplier ' . Str::random(8),
        ]);

        return PurchaseOrder::query()->forceCreate(array_merge([
            'tenant_id' => $tenant->id,
            'supplier_id' => $supplier->id,
            'status' => PurchaseOrder::STATUS_DRAFT,
            'po_subtotal_cents' => 0,
            'po_grand_total_cents' => 0,
        ], $overrides));
    };

    $this->makeSalesOrder = function (Tenant $tenant, array $overrides = []): SalesOrder {
        $customer = Customer::query()->forceCreate([
            'tenant_id' => $tenant->id,
            'name' => 'Customer ' . Str::random(8),
        ]);

        return SalesOrder::query()->forceCreate(array_merge([
            'tenant_id' => $tenant->id,
            'customer_id' => $customer->id,
            'status' => SalesOrder::STATUS_DRAFT,
        ], $overrides));
    };

    $this->makeMakeOrder = function (Tenant $tenant, array $overrides = []): MakeOrder {
        $category = UomCategory::query()->forceCreate([
            'name' => 'Category ' . Str::random(8),
        ]);
        $uom = Uom::query()->forceCreate([
            'uom_category_id' => $category->id,
            'name' => 'Unit ' . Str::random(8),
            'symbol' => 'u' . Str::random(8),
        ]);
        $item = Item::query()->forceCreate([
            'tenant_id' => $tenant->id,
            'name' => 'Item ' . Str::random(8),
            'base_uom_id' => $uom->id,
            'is_manufacturable' => true,
            'is_stockable' => true,
        ]);
        $recipe = Recipe::query()->forceCreate([
            'tenant_id' => $tenant->id,
            'item_id' => $item->id,
            'name' => 'Recipe ' . Str::random(8),
            'recipe_type' => 'manufacturing',
            'is_active' => true,
        ]);

        return MakeOrder::query()->forceCreate(array_merge([
            'tenant_id' => $tenant->id,
            'recipe_id' => $recipe->id,
            'output_item_id' => $item->id,
            'runs' => '1.000000',
            'expected_output_qty' => '1.000000',
            'output_quantity' => '1.000000',
            'status' => MakeOrder::STATUS_DRAFT,
        ], $overrides));
    };

    $this->makeNote = function (Tenant $tenant, User $author, object $noteable, string $body): Note {
        return Note::query()->forceCreate([
            'tenant_id' => $tenant->id,
            'noteable_type' => $noteable::class,
            'noteable_id' => $noteable->id,
            'author_user_id' => $author->id,
            'body' => $body,
            'visibility' => 'internal',
        ]);
    };
});

it('authorized user can view notes on an inventory count', function () {
    $tenant = Tenant::factory()->create();
    $user = ($this->makeUser)($tenant);
    $count = ($this->makeInventoryCount)($tenant);
    ($this->grantPermission)($user, 'inventory-adjustments-view');
    ($this->makeNote)($tenant, $user, $count, 'Cycle count note');

    $this->actingAs($user)
        ->getJson(route('inventory.counts.notes.index', $count))
        ->assertOk()
        ->assertJsonPath('data.0.body', 'Cycle count note');
});

it('unauthorized user cannot view notes', function () {
    $tenant = Tenant::factory()->create();
    $user = ($this->makeUser)($tenant);
    $count = ($this->makeInventoryCount)($tenant);

    $this->actingAs($user)
        ->getJson(route('inventory.counts.notes.index', $count))
        ->assertForbidden();
});

it('unauthenticated users cannot access note endpoints', function () {
    $count = ($this->makeInventoryCount)(Tenant::factory()->create());

    $this->getJson(route('inventory.counts.notes.index', $count))->assertUnauthorized();
    $this->postJson(route('inventory.counts.notes.store', $count), ['body' => 'Nope'])->assertUnauthorized();
});

it('cross-tenant notes are not visible', function () {
    $tenant = Tenant::factory()->create();
    $otherTenant = Tenant::factory()->create();
    $user = ($this->makeUser)($tenant);
    $otherUser = ($this->makeUser)($otherTenant);
    $count = ($this->makeInventoryCount)($tenant);
    ($this->grantPermission)($user, 'inventory-adjustments-view');
    ($this->makeNote)($otherTenant, $otherUser, $count, 'Other tenant note');

    $this->actingAs($user)
        ->getJson(route('inventory.counts.notes.index', $count))
        ->assertOk()
        ->assertJsonCount(0, 'data');
});

it('authorized user can create a note', function () {
    $tenant = Tenant::factory()->create();
    $user = ($this->makeUser)($tenant);
    $count = ($this->makeInventoryCount)($tenant);
    ($this->grantPermission)($user, 'inventory-adjustments-view');

    $this->actingAs($user)
        ->postJson(route('inventory.counts.notes.store', $count), ['body' => 'New comment'])
        ->assertCreated()
        ->assertJsonPath('note.body', 'New comment');
});

it('unauthorized user cannot create a note', function () {
    $tenant = Tenant::factory()->create();
    $user = ($this->makeUser)($tenant);
    $count = ($this->makeInventoryCount)($tenant);

    $this->actingAs($user)
        ->postJson(route('inventory.counts.notes.store', $count), ['body' => 'Nope'])
        ->assertForbidden();
});

it('cross-tenant user cannot create a note', function () {
    $tenant = Tenant::factory()->create();
    $otherTenant = Tenant::factory()->create();
    $user = ($this->makeUser)($otherTenant);
    $count = ($this->makeInventoryCount)($tenant);
    ($this->grantPermission)($user, 'inventory-adjustments-view');

    $this->actingAs($user)
        ->postJson(route('inventory.counts.notes.store', $count), ['body' => 'Nope'])
        ->assertNotFound();
});

it('note body is required', function () {
    $tenant = Tenant::factory()->create();
    $user = ($this->makeUser)($tenant);
    $count = ($this->makeInventoryCount)($tenant);
    ($this->grantPermission)($user, 'inventory-adjustments-view');

    $this->actingAs($user)
        ->postJson(route('inventory.counts.notes.store', $count), ['body' => ''])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['body']);
});

it('note body length is validated', function () {
    $tenant = Tenant::factory()->create();
    $user = ($this->makeUser)($tenant);
    $count = ($this->makeInventoryCount)($tenant);
    ($this->grantPermission)($user, 'inventory-adjustments-view');

    $this->actingAs($user)
        ->postJson(route('inventory.counts.notes.store', $count), ['body' => str_repeat('x', 2001)])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['body']);
});

it('created note stores tenant author and polymorphic parent columns', function () {
    $tenant = Tenant::factory()->create();
    $user = ($this->makeUser)($tenant);
    $count = ($this->makeInventoryCount)($tenant);
    ($this->grantPermission)($user, 'inventory-adjustments-view');

    $this->actingAs($user)
        ->postJson(route('inventory.counts.notes.store', $count), ['body' => 'Stored columns'])
        ->assertCreated();

    $note = Note::query()->firstOrFail();

    expect($note->tenant_id)->toBe($tenant->id)
        ->and($note->author_user_id)->toBe($user->id)
        ->and($note->noteable_type)->toBe(InventoryCount::class)
        ->and($note->noteable_id)->toBe($count->id);
});

it('inventory count create form notes create a domain note', function () {
    $tenant = Tenant::factory()->create();
    $user = ($this->makeUser)($tenant);
    ($this->grantPermission)($user, 'inventory-adjustments-execute');

    $response = $this->actingAs($user)
        ->postJson(route('inventory.counts.store'), [
            'name' => 'Cycle Count',
            'counted_at' => '2026-06-01',
            'notes' => 'Count the walk-in first.',
        ])
        ->assertCreated();

    $this->assertDatabaseHas('notes', [
        'tenant_id' => $tenant->id,
        'noteable_type' => InventoryCount::class,
        'noteable_id' => $response->json('count.id'),
        'author_user_id' => $user->id,
        'body' => 'Count the walk-in first.',
        'visibility' => 'internal',
        'is_pinned' => false,
    ]);
});

it('material detail inventory count create form notes create a domain note', function () {
    $tenant = Tenant::factory()->create();
    $user = ($this->makeUser)($tenant);
    ($this->grantPermission)($user, 'inventory-adjustments-execute');

    $category = UomCategory::query()->forceCreate([
        'tenant_id' => $tenant->id,
        'name' => 'Each',
    ]);
    $uom = Uom::query()->forceCreate([
        'tenant_id' => $tenant->id,
        'uom_category_id' => $category->id,
        'name' => 'Each',
        'symbol' => 'ea',
    ]);
    $item = Item::query()->forceCreate([
        'tenant_id' => $tenant->id,
        'name' => 'Eggs',
        'base_uom_id' => $uom->id,
        'is_stockable' => true,
    ]);

    $response = $this->actingAs($user)
        ->postJson(route('materials.inventory-counts.store', $item), [
            'name' => 'Egg Count',
            'counted_at' => '2026-06-01',
            'counted_quantity' => '12.000000',
            'notes' => 'Check the prep fridge.',
        ])
        ->assertCreated();

    $this->assertDatabaseHas('notes', [
        'tenant_id' => $tenant->id,
        'noteable_type' => InventoryCount::class,
        'noteable_id' => $response->json('count.id'),
        'author_user_id' => $user->id,
        'body' => 'Check the prep fridge.',
        'visibility' => 'internal',
        'is_pinned' => false,
    ]);
});

it('purchase order create form notes create a domain note', function () {
    $tenant = Tenant::factory()->create();
    $user = ($this->makeUser)($tenant);
    ($this->grantPermission)($user, 'purchasing-purchase-orders-create');

    $response = $this->actingAs($user)
        ->postJson(route('purchasing.orders.store'), [
            'notes' => 'Confirm delivery window.',
        ])
        ->assertCreated();

    $this->assertDatabaseHas('notes', [
        'tenant_id' => $tenant->id,
        'noteable_type' => PurchaseOrder::class,
        'noteable_id' => $response->json('data.id'),
        'author_user_id' => $user->id,
        'body' => 'Confirm delivery window.',
        'visibility' => 'internal',
        'is_pinned' => false,
    ]);
});

it('make order create form notes create a domain note', function () {
    $tenant = Tenant::factory()->create();
    $user = ($this->makeUser)($tenant);
    ($this->grantPermission)($user, 'inventory-make-orders-execute');

    $category = UomCategory::query()->forceCreate([
        'tenant_id' => $tenant->id,
        'name' => 'Mass',
    ]);
    $uom = Uom::query()->forceCreate([
        'tenant_id' => $tenant->id,
        'uom_category_id' => $category->id,
        'name' => 'Kilogram',
        'symbol' => 'kg',
    ]);
    $item = Item::query()->forceCreate([
        'tenant_id' => $tenant->id,
        'name' => 'Dough',
        'base_uom_id' => $uom->id,
        'is_manufacturable' => true,
        'is_stockable' => true,
    ]);
    $recipe = Recipe::query()->forceCreate([
        'tenant_id' => $tenant->id,
        'item_id' => $item->id,
        'name' => 'Dough Recipe',
        'recipe_type' => Recipe::TYPE_MANUFACTURING,
        'is_active' => true,
        'output_quantity' => '1.000000',
    ]);
    $version = RecipeVersion::query()->forceCreate([
        'tenant_id' => $tenant->id,
        'recipe_id' => $recipe->id,
        'version_number' => 100,
        'output_quantity' => '1.000000',
        'recipe_type' => Recipe::TYPE_MANUFACTURING,
        'status' => RecipeVersion::STATUS_PUBLISHED,
        'effective_from' => now(),
        'approved_at' => now(),
        'notes' => null,
    ]);
    $recipe->forceFill(['current_version_id' => $version->id])->save();

    $response = $this->actingAs($user)
        ->postJson(route('manufacturing.make-orders.store'), [
            'recipe_id' => $recipe->id,
            'runs' => '2.000000',
            'notes' => 'Use the spiral mixer.',
        ])
        ->assertCreated();

    $this->assertDatabaseHas('notes', [
        'tenant_id' => $tenant->id,
        'noteable_type' => MakeOrder::class,
        'noteable_id' => $response->json('data.id'),
        'author_user_id' => $user->id,
        'body' => 'Use the spiral mixer.',
        'visibility' => 'internal',
        'is_pinned' => false,
    ]);
});

it('sales order create form notes create a domain note', function () {
    $tenant = Tenant::factory()->create();
    $user = ($this->makeUser)($tenant);
    $customer = Customer::query()->forceCreate([
        'tenant_id' => $tenant->id,
        'name' => 'Retail Customer',
    ]);
    ($this->grantPermission)($user, 'sales-sales-orders-manage');

    $response = $this->actingAs($user)
        ->postJson(route('sales.orders.store'), [
            'customer_id' => $customer->id,
            'order_date' => '2026-06-01',
            'notes' => 'Customer asked for morning delivery.',
        ])
        ->assertCreated();

    $this->assertDatabaseHas('notes', [
        'tenant_id' => $tenant->id,
        'noteable_type' => SalesOrder::class,
        'noteable_id' => $response->json('data.id'),
        'author_user_id' => $user->id,
        'body' => 'Customer asked for morning delivery.',
        'visibility' => 'internal',
        'is_pinned' => false,
    ]);
});

it('note list is scoped to the parent resource', function () {
    $tenant = Tenant::factory()->create();
    $user = ($this->makeUser)($tenant);
    $count = ($this->makeInventoryCount)($tenant);
    $otherCount = ($this->makeInventoryCount)($tenant);
    ($this->grantPermission)($user, 'inventory-adjustments-view');
    ($this->makeNote)($tenant, $user, $count, 'Visible parent note');
    ($this->makeNote)($tenant, $user, $otherCount, 'Hidden parent note');

    $this->actingAs($user)
        ->getJson(route('inventory.counts.notes.index', $count))
        ->assertOk()
        ->assertJsonPath('data.0.body', 'Visible parent note')
        ->assertJsonCount(1, 'data');
});

it('notes are listed oldest first for timeline reading', function () {
    $tenant = Tenant::factory()->create();
    $user = ($this->makeUser)($tenant);
    $count = ($this->makeInventoryCount)($tenant);
    ($this->grantPermission)($user, 'inventory-adjustments-view');
    ($this->makeNote)($tenant, $user, $count, 'First note')->forceFill(['created_at' => now()->subHour()])->save();
    ($this->makeNote)($tenant, $user, $count, 'Second note')->forceFill(['created_at' => now()])->save();

    $this->actingAs($user)
        ->getJson(route('inventory.counts.notes.index', $count))
        ->assertOk()
        ->assertJsonPath('data.0.body', 'First note')
        ->assertJsonPath('data.1.body', 'Second note');
});

it('empty state renders when no notes exist', function () {
    $tenant = Tenant::factory()->create();
    $user = ($this->makeUser)($tenant);
    $count = ($this->makeInventoryCount)($tenant);
    ($this->grantPermission)($user, 'inventory-adjustments-view');

    $this->actingAs($user)
        ->get('/inventory/counts/' . $count->id)
        ->assertOk()
        ->assertSee('No notes yet.');
});

it('comment composer renders for an authorized inventory count viewer', function () {
    $tenant = Tenant::factory()->create();
    $user = ($this->makeUser)($tenant);
    $count = ($this->makeInventoryCount)($tenant);
    ($this->grantPermission)($user, 'inventory-adjustments-view');

    $this->actingAs($user)
        ->get('/inventory/counts/' . $count->id)
        ->assertOk()
        ->assertSee('Notes')
        ->assertDontSee('Activity & Notes')
        ->assertSee('Comment')
        ->assertSee('data-notes-feed-root', false);
});

it('comment composer renders a single icon-only time sort toggle', function () {
    $tenant = Tenant::factory()->create();
    $user = ($this->makeUser)($tenant);
    $count = ($this->makeInventoryCount)($tenant);
    ($this->grantPermission)($user, 'inventory-adjustments-view');
    $componentSource = file_get_contents(resource_path('views/components/notes-feed.blade.php'));
    preg_match('/<button[^>]*data-notes-sort-toggle[^>]*>/s', $componentSource, $sortToggleMatches);

    $this->actingAs($user)
        ->get('/inventory/counts/' . $count->id)
        ->assertOk()
        ->assertSee('data-notes-sort-toggle', false)
        ->assertSee('sortByTimeToggle()', false)
        ->assertSee('aria-label="Toggle comment sort order"', false)
        ->assertDontSee('data-notes-sort-ascending', false)
        ->assertDontSee('data-notes-sort-descending', false);

    expect($sortToggleMatches[0] ?? '')
        ->not->toContain('border')
        ->not->toContain('hover:bg')
        ->not->toContain('active:')
        ->not->toContain('focus:ring');
});

it('comment composer does not render for unauthorized inventory count access', function () {
    $tenant = Tenant::factory()->create();
    $user = ($this->makeUser)($tenant);
    $count = ($this->makeInventoryCount)($tenant);

    $this->actingAs($user)
        ->get('/inventory/counts/' . $count->id)
        ->assertForbidden()
        ->assertDontSee('data-notes-feed-root', false);
});

it('inventory count detail renders existing notes as boxed comment cards', function () {
    $tenant = Tenant::factory()->create();
    $user = ($this->makeUser)($tenant);
    $count = ($this->makeInventoryCount)($tenant);
    ($this->grantPermission)($user, 'inventory-adjustments-view');
    ($this->makeNote)($tenant, $user, $count, 'Visible feed note');

    $this->actingAs($user)
        ->get('/inventory/counts/' . $count->id)
        ->assertOk()
        ->assertSee('Visible feed note')
        ->assertSee('data-note-card', false)
        ->assertSee('data-notes-timeline', false);
});

it('make order detail can render the notes section', function () {
    $tenant = Tenant::factory()->create();
    $user = ($this->makeUser)($tenant);
    $makeOrder = ($this->makeMakeOrder)($tenant);
    ($this->grantPermission)($user, 'inventory-make-orders-view');

    $this->actingAs($user)
        ->get(route('manufacturing.make-orders.show', $makeOrder))
        ->assertOk()
        ->assertSee('Notes')
        ->assertDontSee('Activity & Notes');
});

it('purchase order detail can render the notes section', function () {
    $tenant = Tenant::factory()->create();
    $user = ($this->makeUser)($tenant);
    $purchaseOrder = ($this->makePurchaseOrder)($tenant);
    ($this->grantPermission)($user, 'purchasing-purchase-orders-create');

    $this->actingAs($user)
        ->get(route('purchasing.orders.show', $purchaseOrder))
        ->assertOk()
        ->assertSee('Notes')
        ->assertDontSee('Activity & Notes');
});

it('sales order detail can render the notes section', function () {
    $tenant = Tenant::factory()->create();
    $user = ($this->makeUser)($tenant);
    $salesOrder = ($this->makeSalesOrder)($tenant);
    ($this->grantPermission)($user, 'sales-sales-orders-manage');

    $this->actingAs($user)
        ->get(route('sales.orders.show', $salesOrder))
        ->assertOk()
        ->assertSee('Notes')
        ->assertDontSee('Activity & Notes');
});

it('make order note endpoints use parent authorization', function () {
    $tenant = Tenant::factory()->create();
    $user = ($this->makeUser)($tenant);
    $makeOrder = ($this->makeMakeOrder)($tenant);
    ($this->grantPermission)($user, 'inventory-make-orders-view');

    $this->actingAs($user)
        ->postJson(route('manufacturing.make-orders.notes.store', $makeOrder), ['body' => 'Make note'])
        ->assertCreated()
        ->assertJsonPath('note.noteable_type', MakeOrder::class);
});

it('purchase order note endpoints use parent authorization', function () {
    $tenant = Tenant::factory()->create();
    $user = ($this->makeUser)($tenant);
    $purchaseOrder = ($this->makePurchaseOrder)($tenant);
    ($this->grantPermission)($user, 'purchasing-purchase-orders-create');

    $this->actingAs($user)
        ->postJson(route('purchasing.orders.notes.store', $purchaseOrder), ['body' => 'Purchase note'])
        ->assertCreated()
        ->assertJsonPath('note.noteable_type', PurchaseOrder::class);
});

it('sales order note endpoints use parent authorization', function () {
    $tenant = Tenant::factory()->create();
    $user = ($this->makeUser)($tenant);
    $salesOrder = ($this->makeSalesOrder)($tenant);
    ($this->grantPermission)($user, 'sales-sales-orders-manage');

    $this->actingAs($user)
        ->postJson(route('sales.orders.notes.store', $salesOrder), ['body' => 'Sales note'])
        ->assertCreated()
        ->assertJsonPath('note.noteable_type', SalesOrder::class);
});

it('system activity rows are not required in v1 response', function () {
    $tenant = Tenant::factory()->create();
    $user = ($this->makeUser)($tenant);
    $count = ($this->makeInventoryCount)($tenant);
    ($this->grantPermission)($user, 'inventory-adjustments-view');

    $this->actingAs($user)
        ->getJson(route('inventory.counts.notes.index', $count))
        ->assertOk()
        ->assertJsonPath('meta.system_activity_supported', false);
});

it('attachments are not exposed in v1', function () {
    $tenant = Tenant::factory()->create();
    $user = ($this->makeUser)($tenant);
    $count = ($this->makeInventoryCount)($tenant);
    ($this->grantPermission)($user, 'inventory-adjustments-view');

    $this->actingAs($user)
        ->get('/inventory/counts/' . $count->id)
        ->assertOk()
        ->assertDontSee('Attachment')
        ->assertDontSee('Upload');
});

it('mood picker is not exposed in v1', function () {
    $tenant = Tenant::factory()->create();
    $user = ($this->makeUser)($tenant);
    $count = ($this->makeInventoryCount)($tenant);
    ($this->grantPermission)($user, 'inventory-adjustments-view');

    $this->actingAs($user)
        ->get('/inventory/counts/' . $count->id)
        ->assertOk()
        ->assertDontSee('Mood')
        ->assertDontSee('Emoji');
});

it('super-admin can access note endpoints through Gate before behavior', function () {
    $tenant = Tenant::factory()->create();
    $user = ($this->makeUser)($tenant);
    $role = Role::query()->forceCreate(['name' => 'super-admin']);
    $user->roles()->syncWithoutDetaching([$role->id]);
    $count = ($this->makeInventoryCount)($tenant);

    $this->actingAs($user)
        ->postJson(route('inventory.counts.notes.store', $count), ['body' => 'Super note'])
        ->assertCreated();
});
