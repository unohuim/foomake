<?php

declare(strict_types=1);

use App\Models\InventoryCount;
use App\Models\InventoryCountLine;
use App\Models\Item;
use App\Models\Note;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Task;
use App\Models\Tenant;
use App\Models\Uom;
use App\Models\UomCategory;
use App\Models\User;
use App\Models\WorkflowDomain;
use App\Models\WorkflowStage;
use App\Actions\Workflows\EnsureWorkflowDomainsSeededAction;
use App\Actions\Workflows\SeedDefaultWorkflowStagesForTenantAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->roleCounter = 1;
    $this->uomCounter = 1;

    $this->tenant = Tenant::factory()->create([
        'tenant_name' => 'Inventory Counts Tenant',
    ]);

    $this->user = User::factory()->create([
        'tenant_id' => $this->tenant->id,
        'email_verified_at' => now(),
    ]);

    $this->grantPermission = function (User $user, string $slug): void {
        $permission = Permission::query()->firstOrCreate([
            'slug' => $slug,
        ]);

        $role = Role::query()->create([
            'name' => 'inventory-counts-role-' . $this->roleCounter,
        ]);

        $this->roleCounter++;

        $role->permissions()->syncWithoutDetaching([$permission->id]);
        $user->roles()->syncWithoutDetaching([$role->id]);
    };

    $this->grantPermissions = function (User $user, array $slugs): void {
        foreach ($slugs as $slug) {
            ($this->grantPermission)($user, $slug);
        }
    };

    $uomSuffix = Str::lower(Str::random(8)) . '-' . $this->uomCounter;
    $this->uomCounter++;

    $this->uomCategory = UomCategory::query()->create([
        'tenant_id' => $this->tenant->id,
        'name' => 'Inventory Counts Category ' . $uomSuffix,
    ]);

    $this->uom = Uom::query()->create([
        'tenant_id' => $this->tenant->id,
        'uom_category_id' => $this->uomCategory->id,
        'name' => 'Each ' . $uomSuffix,
        'symbol' => 'ea-' . $uomSuffix,
    ]);

    $this->item = Item::query()->create([
        'tenant_id' => $this->tenant->id,
        'name' => 'Inventory Count Item',
        'base_uom_id' => $this->uom->id,
        'is_purchasable' => false,
        'is_sellable' => false,
        'is_manufacturable' => false,
        'default_price_cents' => null,
        'default_price_currency_code' => null,
    ]);

    $this->makeCount = function (array $overrides = []): InventoryCount {
        return InventoryCount::query()->forceCreate(array_merge([
            'tenant_id' => $this->tenant->id,
            'counted_at' => now()->startOfMinute(),
            'notes' => 'Inventory count notes',
        ], $overrides));
    };

    $this->makeLine = function (InventoryCount $count, array $overrides = []): InventoryCountLine {
        return InventoryCountLine::query()->forceCreate(array_merge([
            'tenant_id' => $this->tenant->id,
            'inventory_count_id' => $count->id,
            'item_id' => $this->item->id,
            'counted_quantity' => '5.000000',
            'notes' => 'Count line notes',
        ], $overrides));
    };

    $this->extractPayload = function ($response, string $payloadId): array {
        preg_match(
            '/<script[^>]+id="' . preg_quote($payloadId, '/') . '"[^>]*>(.*?)<\/script>/s',
            $response->getContent(),
            $matches
        );

        expect($matches)->toHaveKey(1);

        $payload = json_decode(html_entity_decode($matches[1], ENT_QUOTES), true);

        return is_array($payload) ? $payload : [];
    };

    $this->extractCrudConfig = function ($response): array {
        preg_match("/data-crud-config='([^']+)'/", $response->getContent(), $matches);

        expect($matches)->toHaveKey(1);

        $config = json_decode(html_entity_decode($matches[1], ENT_QUOTES), true);

        return is_array($config) ? $config : [];
    };

    $this->seedInventoryWorkflow = function (): void {
        app(EnsureWorkflowDomainsSeededAction::class)->execute();
        app(SeedDefaultWorkflowStagesForTenantAction::class)->execute($this->tenant);
    };

    $this->inventoryStage = function (string $key): WorkflowStage {
        ($this->seedInventoryWorkflow)();

        $domainId = WorkflowDomain::query()
            ->where('key', 'inventory')
            ->value('id');

        expect($domainId)->not->toBeNull();

        return WorkflowStage::withoutGlobalScopes()
            ->where('tenant_id', $this->tenant->id)
            ->where('workflow_domain_id', $domainId)
            ->where('key', $key)
            ->firstOrFail();
    };
});

it('1. redirects guests away from the inventory counts index', function (): void {
    $this->get(route('inventory.counts.index'))
        ->assertRedirect(route('login'));
});

it('2. redirects guests away from the inventory counts list endpoint', function (): void {
    $this->getJson(route('inventory.counts.list'))
        ->assertUnauthorized();
});

it('3. forbids authenticated users without the inventory count view permission from the index', function (): void {
    $this->actingAs($this->user)
        ->get(route('inventory.counts.index'))
        ->assertForbidden();
});

it('4. forbids authenticated users without the inventory count view permission from the list endpoint', function (): void {
    $this->actingAs($this->user)
        ->getJson(route('inventory.counts.list'))
        ->assertForbidden();
});

it('4a. allows assigned workflow users to access the inventory counts index', function (): void {
    ($this->grantPermission)($this->user, 'inventory-adjustments-execute');

    $count = ($this->makeCount)([
        'assigned_to_user_id' => $this->user->id,
        'notes' => 'Assigned direct count',
    ]);

    $this->actingAs($this->user)
        ->get(route('inventory.counts.index'))
        ->assertOk()
        ->assertSee('Inventory Counts')
        ->assertSee('data-page="inventory-counts-index"', false);
});

it('4b. assigned workflow users see only directly assigned counts in the list endpoint', function (): void {
    ($this->grantPermission)($this->user, 'inventory-adjustments-execute');

    $assignedCount = ($this->makeCount)([
        'assigned_to_user_id' => $this->user->id,
        'notes' => 'Visible assigned count',
    ]);
    $hiddenCount = ($this->makeCount)([
        'notes' => 'Hidden unassigned count',
    ]);

    $response = $this->actingAs($this->user)
        ->getJson(route('inventory.counts.list'))
        ->assertOk();

    expect(collect($response->json('data'))->pluck('id'))->toContain($assignedCount->id)
        ->and(collect($response->json('data'))->pluck('id'))->not->toContain($hiddenCount->id);
});

it('4c. assigned workflow users see counts with tasks assigned to them in the list endpoint', function (): void {
    ($this->grantPermission)($this->user, 'inventory-adjustments-execute');

    app(EnsureWorkflowDomainsSeededAction::class)->execute();
    app(SeedDefaultWorkflowStagesForTenantAction::class)->execute($this->tenant);
    $inventoryDomain = WorkflowDomain::query()->where('key', 'inventory')->firstOrFail();
    $stage = WorkflowStage::withoutGlobalScopes()
        ->where('tenant_id', $this->tenant->id)
        ->where('workflow_domain_id', $inventoryDomain->id)
        ->orderBy('sort_order')
        ->firstOrFail();
    $taskAssignedCount = ($this->makeCount)([
        'workflow_stage_id' => $stage->id,
        'notes' => 'Visible task count',
    ]);
    $hiddenCount = ($this->makeCount)([
        'workflow_stage_id' => $stage->id,
        'notes' => 'Hidden task count',
    ]);

    Task::query()->forceCreate([
        'tenant_id' => $this->tenant->id,
        'workflow_domain_id' => $inventoryDomain->id,
        'domain_record_id' => $taskAssignedCount->id,
        'workflow_stage_id' => $stage->id,
        'workflow_task_template_id' => null,
        'assigned_to_user_id' => $this->user->id,
        'title' => 'Assigned index task',
        'description' => null,
        'sort_order' => 10,
        'status' => Task::STATUS_OPEN,
    ]);

    $response = $this->actingAs($this->user)
        ->getJson(route('inventory.counts.list'))
        ->assertOk();

    expect(collect($response->json('data'))->pluck('id'))->toContain($taskAssignedCount->id)
        ->and(collect($response->json('data'))->pluck('id'))->not->toContain($hiddenCount->id);
});

it('4d. assigned workflow users do not see cross tenant assigned counts in the list endpoint', function (): void {
    ($this->grantPermission)($this->user, 'inventory-adjustments-execute');

    $otherTenant = Tenant::factory()->create();
    $crossTenantCount = InventoryCount::query()->forceCreate([
        'tenant_id' => $otherTenant->id,
        'assigned_to_user_id' => $this->user->id,
        'counted_at' => now()->startOfMinute(),
        'notes' => 'Cross tenant assigned count',
    ]);

    $response = $this->actingAs($this->user)
        ->getJson(route('inventory.counts.list'))
        ->assertOk();

    expect(collect($response->json('data'))->pluck('id'))->not->toContain($crossTenantCount->id);
});

it('4e. assigned workflow users do not get the inventory count create toolbar action from the index', function (): void {
    ($this->grantPermission)($this->user, 'inventory-adjustments-execute');
    ($this->makeCount)([
        'assigned_to_user_id' => $this->user->id,
    ]);

    $config = ($this->extractCrudConfig)(
        $this->actingAs($this->user)->get(route('inventory.counts.index'))
    );

    expect($config['permissions']['showCreate'] ?? null)->toBeFalse();
});

it('5. allows users with the inventory count view permission to access the index', function (): void {
    ($this->grantPermission)($this->user, 'inventory-adjustments-view');

    $this->actingAs($this->user)
        ->get(route('inventory.counts.index'))
        ->assertOk()
        ->assertSee('Inventory Counts');
});

it('6. renders the inventory counts page mount contract for the shared crud module', function (): void {
    ($this->grantPermission)($this->user, 'inventory-adjustments-view');

    $this->actingAs($this->user)
        ->get(route('inventory.counts.index'))
        ->assertOk()
        ->assertSee('data-page="inventory-counts-index"', false)
        ->assertSee('data-payload="inventory-counts-index-payload"', false)
        ->assertSee('data-crud-config=', false)
        ->assertSee('data-crud-root', false);
});

it('7. the index payload provides csrf context without embedding old count collection payloads', function (): void {
    ($this->grantPermission)($this->user, 'inventory-adjustments-view');

    $response = $this->actingAs($this->user)
        ->get(route('inventory.counts.index'))
        ->assertOk();

    $payload = ($this->extractPayload)($response, 'inventory-counts-index-payload');

    expect($payload['csrfToken'] ?? null)->toBeString()
        ->and($payload['csrfToken'] ?? null)->not->toBe('')
        ->and($payload)->not->toHaveKey('counts')
        ->and($payload)->not->toHaveKey('storeUrl');
});

it('8. the crud config identifies the inventory-counts resource', function (): void {
    ($this->grantPermission)($this->user, 'inventory-adjustments-view');

    $config = ($this->extractCrudConfig)(
        $this->actingAs($this->user)->get(route('inventory.counts.index'))
    );

    expect($config['resource'] ?? null)->toBe('inventory-counts');
});

it('9. the crud config includes the required inventory counts list and create endpoints', function (): void {
    ($this->grantPermissions)($this->user, [
        'inventory-adjustments-view',
        'inventory-adjustments-execute',
    ]);

    $config = ($this->extractCrudConfig)(
        $this->actingAs($this->user)->get(route('inventory.counts.index'))
    );

    expect($config['endpoints']['list'] ?? null)->toBe(route('inventory.counts.list'))
        ->and($config['endpoints']['create'] ?? null)->toBe(route('inventory.counts.store'))
        ->and($config['endpoints']['update'] ?? null)->toBe(url('/inventory/counts/{id}'))
        ->and($config['endpoints']['delete'] ?? null)->toBe(url('/inventory/counts/{id}'))
        ->and($config['detailUrlTemplate'] ?? null)->toBe(url('/inventory/counts/{id}'))
        ->and($config['headers']['counter'] ?? null)->toBe('Assigned');
});

it('10. the crud config exposes create through the shared toolbar contract for authorized users', function (): void {
    ($this->grantPermissions)($this->user, [
        'inventory-adjustments-view',
        'inventory-adjustments-execute',
    ]);

    $config = ($this->extractCrudConfig)(
        $this->actingAs($this->user)->get(route('inventory.counts.index'))
    );

    expect($config['permissions']['showCreate'] ?? null)->toBeTrue()
        ->and($config['labels']['createTitle'] ?? null)->toBe('Create Inventory Count')
        ->and($config['labels']['createAriaLabel'] ?? null)->toBe('Create Inventory Count');
});

it('11. the crud config hides create in the shared toolbar contract for view-only users', function (): void {
    ($this->grantPermission)($this->user, 'inventory-adjustments-view');

    $config = ($this->extractCrudConfig)(
        $this->actingAs($this->user)->get(route('inventory.counts.index'))
    );

    expect($config['permissions']['showCreate'] ?? null)->toBeFalse();
});

it('12. the old blade rendered inventory counts table is no longer the primary index markup', function (): void {
    $source = file_get_contents(resource_path('views/inventory/counts/index.blade.php'));

    expect($source)->toContain('data-crud-root')
        ->and($source)->not->toContain('<table class="min-w-full text-sm">')
        ->and($source)->not->toContain('No inventory counts yet.');
});

it('13. the blue create count button text and markup are removed from the index shell', function (): void {
    ($this->grantPermissions)($this->user, [
        'inventory-adjustments-view',
        'inventory-adjustments-execute',
    ]);

    $response = $this->actingAs($this->user)
        ->get(route('inventory.counts.index'))
        ->assertOk();

    expect($response->getContent())->not->toContain('Create Count')
        ->and($response->getContent())->not->toContain('Create Inventory Count</button>')
        ->and($response->getContent())->not->toContain('setCreateHash()');
});

it('14. the inventory counts page module mounts the shared crud renderer', function (): void {
    $source = file_get_contents(resource_path('js/pages/inventory-counts-index.js'));

    expect($source)->toContain("import { mountCrudRenderer } from '../lib/crud-page';")
        ->and($source)->toContain("import { createGenericCrud } from '../lib/generic-crud';")
        ->and($source)->toContain('const crud = createGenericCrud(parseCrudConfig(rootEl));')
        ->and($source)->toContain('mountCrudRenderer(crudRootEl, rendererConfig);')
        ->and($source)->toContain('assigned_to_user_id')
        ->and($source)->toContain('this.crud.buildDetailUrl(data?.count)')
        ->and($source)->toContain('window.location.assign(detailUrl)')
        ->and($source)->toContain("if (column === 'counter')")
        ->and($source)->toContain('truncateCounterEmail(record)')
        ->and($source)->toContain('slice(0, 20)');
});

it('15. the inventory counts list endpoint returns only tenant scoped count records', function (): void {
    ($this->grantPermission)($this->user, 'inventory-adjustments-view');

    $ownCount = ($this->makeCount)([
        'notes' => 'Own visible count',
    ]);
    ($this->makeLine)($ownCount);

    $otherTenant = Tenant::factory()->create();
    $otherCount = InventoryCount::query()->forceCreate([
        'tenant_id' => $otherTenant->id,
        'counted_at' => now()->addDay(),
        'notes' => 'Other tenant hidden count',
    ]);

    $response = $this->actingAs($this->user)
        ->getJson(route('inventory.counts.list'))
        ->assertOk();

    expect(collect($response->json('data'))->pluck('id'))->toContain($ownCount->id)
        ->and(collect($response->json('data'))->pluck('id'))->not->toContain($otherCount->id);
});

it('15a. the inventory counts list endpoint returns the assigned user email for the counter column', function (): void {
    ($this->grantPermission)($this->user, 'inventory-adjustments-view');

    $count = ($this->makeCount)([
        'assigned_to_user_id' => $this->user->id,
        'notes' => 'Assigned counter count',
    ]);

    $response = $this->actingAs($this->user)
        ->getJson(route('inventory.counts.list'))
        ->assertOk();

    $record = collect($response->json('data'))->firstWhere('id', $count->id);

    expect($record)->not->toBeNull()
        ->and($record['counter_email'] ?? null)->toBe($this->user->email);
});

it('15b. the inventory counts list endpoint keeps the counter column nullable for unassigned counts', function (): void {
    ($this->grantPermission)($this->user, 'inventory-adjustments-view');

    $count = ($this->makeCount)([
        'assigned_to_user_id' => null,
        'notes' => 'Unassigned counter count',
    ]);

    $response = $this->actingAs($this->user)
        ->getJson(route('inventory.counts.list'))
        ->assertOk();

    $record = collect($response->json('data'))->firstWhere('id', $count->id);

    expect($record)->not->toBeNull()
        ->and(array_key_exists('counter_email', $record))->toBeTrue()
        ->and($record['counter_email'])->toBeNull();
});

it('15c. the inventory counts list status column shows the workflow status label for scheduled counts', function (): void {
    ($this->grantPermission)($this->user, 'inventory-adjustments-view');
    $openStage = ($this->inventoryStage)('counting');

    $count = ($this->makeCount)([
        'workflow_stage_id' => $openStage->id,
        'posted_at' => null,
        'notes' => 'Open stage count',
    ]);

    $record = collect(
        $this->actingAs($this->user)->getJson(route('inventory.counts.list'))->assertOk()->json('data')
    )->firstWhere('id', $count->id);

    expect($record)->not->toBeNull()
        ->and($record['status_label'] ?? null)->toBe('SCHEDULED')
        ->and($record['status'] ?? null)->toBe('draft');
});

it('15d. the inventory counts list status column does not use posted lifecycle text for posted counts', function (): void {
    ($this->grantPermission)($this->user, 'inventory-adjustments-view');
    $completedStage = ($this->inventoryStage)('completing');

    $count = ($this->makeCount)([
        'workflow_stage_id' => $completedStage->id,
        'posted_at' => now(),
        'notes' => 'Posted completed stage count',
    ]);

    $record = collect(
        $this->actingAs($this->user)->getJson(route('inventory.counts.list'))->assertOk()->json('data')
    )->firstWhere('id', $count->id);

    expect($record)->not->toBeNull()
        ->and($record['status_label'] ?? null)->toBe('COMPLETED')
        ->and($record['posted_at'] ?? null)->not->toBe('—');
});

it('16. the inventory counts list endpoint returns the expected shared crud meta contract', function (): void {
    ($this->grantPermission)($this->user, 'inventory-adjustments-view');
    ($this->makeCount)();

    $response = $this->actingAs($this->user)
        ->getJson(route('inventory.counts.list'))
        ->assertOk();

    expect($response->json('meta.sort.column'))->toBe('counted_at')
        ->and($response->json('meta.sort.direction'))->toBe('desc')
        ->and($response->json('meta.allowed_sort_columns'))->toBe(['counted_at', 'status', 'lines_count', 'posted_at'])
        ->and($response->json('meta.total'))->toBe(1);
});

it('17. the inventory counts list endpoint supports search without exposing other records', function (): void {
    ($this->grantPermission)($this->user, 'inventory-adjustments-view');

    ($this->makeCount)(['notes' => 'Vanilla beans count']);
    ($this->makeCount)(['notes' => 'Cocoa powder count']);

    $response = $this->actingAs($this->user)
        ->getJson(route('inventory.counts.list', ['search' => 'Vanilla']))
        ->assertOk();

    expect($response->json('meta.search'))->toBe('Vanilla')
        ->and(collect($response->json('data'))->pluck('notes'))->toContain('Vanilla beans count')
        ->and(collect($response->json('data'))->pluck('notes'))->not->toContain('Cocoa powder count');
});

it('18. users with execute permission can create an inventory count through the existing create endpoint', function (): void {
    ($this->grantPermission)($this->user, 'inventory-adjustments-execute');

    $response = $this->actingAs($this->user)
        ->postJson(route('inventory.counts.store'), [
            'counted_at' => '2026-05-19 09:30',
            'notes' => 'Create flow preserved',
            'assigned_to_user_id' => $this->user->id,
        ]);

    $response->assertCreated();

    expect($response->json('count.id'))->not->toBeNull()
        ->and($response->json('count.status'))->toBe('draft')
        ->and($response->json('count.assigned_to_user_id'))->toBe($this->user->id)
        ->and($response->json('count.created_by_user_id'))->toBe($this->user->id)
        ->and($response->json('count.tasked_by_user_id'))->toBe($this->user->id);
});

it('19. create validation errors still return the expected json validation response', function (): void {
    ($this->grantPermission)($this->user, 'inventory-adjustments-execute');

    $response = $this->actingAs($this->user)
        ->postJson(route('inventory.counts.store'), [
            'counted_at' => '',
            'notes' => 'Missing date',
        ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['counted_at']);
});

it('19a. creating an inventory count with notes creates an authored notes feed record', function (): void {
    ($this->grantPermission)($this->user, 'inventory-adjustments-execute');

    $response = $this->actingAs($this->user)
        ->postJson(route('inventory.counts.store'), [
            'counted_at' => '2026-05-19 09:30',
            'notes' => '  Opening freezer cycle count  ',
        ])
        ->assertCreated();

    $count = InventoryCount::query()->findOrFail((int) $response->json('count.id'));
    $note = Note::query()
        ->where('tenant_id', $this->tenant->id)
        ->where('noteable_type', InventoryCount::class)
        ->where('noteable_id', $count->id)
        ->first();

    expect($note)->not->toBeNull()
        ->and($note?->author_user_id)->toBe($this->user->id)
        ->and($note?->body)->toBe('Opening freezer cycle count');
});

it('20. existing create behavior persists the count with the expected draft data', function (): void {
    ($this->grantPermission)($this->user, 'inventory-adjustments-execute');

    $countedAt = '2026-05-19 09:30';

    $response = $this->actingAs($this->user)
        ->post(route('inventory.counts.store'), [
            'counted_at' => $countedAt,
            'notes' => 'Ajax create',
            'assigned_to_user_id' => $this->user->id,
        ]);

    expect($response->getStatusCode())->toBeGreaterThanOrEqual(200)
        ->and($response->getStatusCode())->toBeLessThan(400);

    $count = InventoryCount::query()
        ->where('tenant_id', $this->tenant->id)
        ->where('notes', 'Ajax create')
        ->first();

    expect($count)->not->toBeNull()
        ->and($count?->status)->toBe('draft')
        ->and($count?->notes)->toBe('Ajax create')
        ->and($count?->counted_at->format('Y-m-d H:i'))->toBe($countedAt)
        ->and($count?->assigned_to_user_id)->toBe($this->user->id)
        ->and($count?->created_by_user_id)->toBe($this->user->id)
        ->and($count?->tasked_by_user_id)->toBe($this->user->id);
});

it('20b. create also succeeds without an assigned user when the UI leaves assignment blank', function (): void {
    ($this->grantPermission)($this->user, 'inventory-adjustments-execute');

    $response = $this->actingAs($this->user)
        ->postJson(route('inventory.counts.store'), [
            'counted_at' => '2026-05-19 10:15',
            'notes' => 'Unassigned create',
        ]);

    $response->assertCreated()
        ->assertJsonPath('count.status', 'draft')
        ->assertJsonPath('count.assigned_to_user_id', null);

    $count = InventoryCount::query()
        ->where('tenant_id', $this->tenant->id)
        ->where('notes', 'Unassigned create')
        ->first();

    expect($count)->not->toBeNull()
        ->and($count?->assigned_to_user_id)->toBeNull();
});

it('21. users with only the view permission cannot create an inventory count', function (): void {
    ($this->grantPermission)($this->user, 'inventory-adjustments-view');

    $this->actingAs($this->user)
        ->postJson(route('inventory.counts.store'), [
            'counted_at' => '2026-05-19 09:30',
            'notes' => 'Blocked create',
        ])
        ->assertForbidden();
});

it('22. the inventory count show route still works and renders existing count information', function (): void {
    ($this->grantPermission)($this->user, 'inventory-adjustments-view');
    $count = ($this->makeCount)([
        'counted_at' => now()->setTime(11, 45),
        'notes' => 'Detail notes',
    ]);
    ($this->makeLine)($count, [
        'counted_quantity' => '7.000000',
    ]);

    $this->actingAs($this->user)
        ->get(route('inventory.counts.show', $count))
        ->assertOk()
        ->assertSee('Inventory Count')
        ->assertSee($count->counted_at->format('Y-m-d H:i'))
        ->assertSee('Detail notes')
        ->assertSee('Materials')
        ->assertDontSee('Count Lines')
        ->assertSee('Tasks')
        ->assertSee('data-js-crud-section-root', false);
});

it('23. the show route remains the dedicated detail page and is not replaced by the crud index renderer', function (): void {
    ($this->grantPermission)($this->user, 'inventory-adjustments-view');
    $count = ($this->makeCount)();

    $response = $this->actingAs($this->user)
        ->get(route('inventory.counts.show', $count))
        ->assertOk()
        ->assertSee('data-page="inventory-count-show"', false);

    expect($response->getContent())->not->toContain('data-crud-config=')
        ->and($response->getContent())->not->toContain('data-page="inventory-counts-index"');
});

it('24. count line behavior remains reachable through the existing line endpoint', function (): void {
    ($this->grantPermission)($this->user, 'inventory-adjustments-execute');
    $count = ($this->makeCount)();

    $this->actingAs($this->user)
        ->postJson(route('inventory.counts.lines.store', $count), [
            'item_id' => $this->item->id,
            'counted_quantity' => '8.000000',
            'notes' => 'Line preserved',
        ])
        ->assertCreated()
        ->assertJsonPath('line.item_id', $this->item->id)
        ->assertJsonPath('line.counted_quantity', '8.000000');
});

it('25. posting behavior remains reachable through the existing post endpoint', function (): void {
    ($this->grantPermission)($this->user, 'inventory-adjustments-execute');
    $count = ($this->makeCount)();
    ($this->makeLine)($count, [
        'counted_quantity' => '8.000000',
    ]);

    $this->actingAs($this->user)
        ->postJson(route('inventory.counts.post', $count))
        ->assertOk()
        ->assertJsonPath('count.id', $count->id)
        ->assertJsonPath('count.status', 'posted');
});

it('26. inventory count permission denial still blocks the show route', function (): void {
    $count = ($this->makeCount)();

    $this->actingAs($this->user)
        ->get(route('inventory.counts.show', $count))
        ->assertForbidden();
});

it('27. inventory count execute permission still allows create even without the view permission', function (): void {
    ($this->grantPermission)($this->user, 'inventory-adjustments-execute');

    $this->actingAs($this->user)
        ->postJson(route('inventory.counts.store'), [
            'counted_at' => '2026-05-19 09:30',
            'notes' => 'Execute only create',
            'assigned_to_user_id' => $this->user->id,
        ])
        ->assertCreated();
});

it('28. the create slide over renders the assigned user field for workflow task assignment', function (): void {
    ($this->grantPermissions)($this->user, [
        'inventory-adjustments-view',
        'inventory-adjustments-execute',
    ]);

    $response = $this->actingAs($this->user)
        ->get(route('inventory.counts.index'))
        ->assertOk();

    expect($response->getContent())->toContain('Assigned User')
        ->and($response->getContent())->toContain('assigned_to_user_id')
        ->and($response->getContent())->toContain('Select a user');
});

it('28a. the inventory counts index owns the standard open create event contract for the shared slide over', function (): void {
    $source = file_get_contents(resource_path('views/inventory/counts/index.blade.php'));
    $pageSource = file_get_contents(resource_path('js/pages/inventory-counts-index.js'));

    expect($source)->toContain('@open-create-inventory-count.window="openCreate()"')
        ->and($pageSource)->toContain('openCreate()')
        ->and($pageSource)->toContain("action: this.endpoints.create || ''")
        ->and($pageSource)->not->toContain('counted_quantity:');
});

it('29. the counted at field source auto collapses the native picker after date selection without clearing the bound value', function (): void {
    $source = file_get_contents(resource_path('views/inventory/counts/partials/count-form.blade.php'));
    $pageSource = file_get_contents(resource_path('js/pages/inventory-counts-index.js'));

    expect($source)->toContain('type="datetime-local"')
        ->and($source)->toContain('x-on:click="$el.showPicker?.()"')
        ->and($source)->toContain('x-on:change="handleCountedAtChange($event)"')
        ->and($source)->toContain('x-on:input="handleCountedAtChange($event)"')
        ->and($pageSource)->toContain('handleCountedAtChange(event)')
        ->and($pageSource)->toContain('this.form.counted_at = event.target.value')
        ->and($pageSource)->toContain('requestAnimationFrame(() => {')
        ->and($pageSource)->toContain('event.target.blur()')
        ->and($pageSource)->toContain("this.focusCountedAtNextField('notes')");
});

it('30. the shared crud config exposes a Counter column and the mobile card summary includes the truncated counter email', function (): void {
    ($this->grantPermission)($this->user, 'inventory-adjustments-view');

    $config = ($this->extractCrudConfig)(
        $this->actingAs($this->user)->get(route('inventory.counts.index'))
    );

    expect($config['columns'] ?? [])->toContain('counter')
        ->and($config['headers']['counter'] ?? null)->toBe('Assigned')
        ->and($config['mobileCard']['bodyExpression'] ?? null)->toContain('inventoryCountSummary(record)');
});
