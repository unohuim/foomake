<?php

use App\Models\Permission;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Billing\TenantBillingEntitlement;
use Database\Seeders\TenancyRolesPermissionsSeeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;

beforeEach(function (): void {
    $this->makeUser = function (array $tenantAttributes = [], array $permissionSlugs = []): User {
        $tenant = Tenant::factory()->create($tenantAttributes);
        $user = User::factory()->for($tenant)->create();

        if ($permissionSlugs !== []) {
            $role = Role::query()->create([
                'name' => 'billing-test-role-' . $user->id,
            ]);

            foreach ($permissionSlugs as $slug) {
                $permission = Permission::query()->firstOrCreate([
                    'slug' => $slug,
                ]);

                $role->permissions()->syncWithoutDetaching([$permission->id]);
            }

            $user->roles()->syncWithoutDetaching([$role->id]);
        }

        return $user;
    };

    $this->makeAdmin = fn (array $tenantAttributes = []): User => ($this->makeUser)(
        $tenantAttributes,
        ['billing-subscription-manage']
    );

    $this->stripeSignature = function (string $payload): string {
        $timestamp = (string) now()->timestamp;
        $signature = hash_hmac('sha256', $timestamp . '.' . $payload, 'whsec_test');

        return 't=' . $timestamp . ',v1=' . $signature;
    };

    $this->postStripeWebhook = function (string $payload, string $signature) {
        return $this->call('POST', route('billing.stripe.webhook'), [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_STRIPE_SIGNATURE' => $signature,
        ], $payload);
    };
});

it('gives newly created tenants a seven day trial by default', function (): void {
    $now = Carbon::parse('2026-06-07 10:00:00');
    $this->travelTo($now);

    $tenant = Tenant::factory()->create([
        'trial_ends_at' => null,
    ]);

    expect($tenant->fresh()->trial_ends_at->equalTo($now->copy()->addDays(7)))->toBeTrue();
});

it('stores a seven day trial when a normal user registers', function (): void {
    $now = Carbon::parse('2026-06-07 10:00:00');
    $this->travelTo($now);

    $this->post('/register', [
        'name' => 'Trial Owner',
        'email' => 'trial-owner@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ])->assertRedirect(route('dashboard', absolute: false));

    $tenant = User::query()->where('email', 'trial-owner@example.com')->firstOrFail()->tenant;

    expect($tenant->trial_ends_at->equalTo($now->copy()->addDays(7)))->toBeTrue();
});

it('allows dashboard access while tenant trial is active', function (): void {
    $user = ($this->makeUser)([
        'trial_ends_at' => now()->addDay(),
    ]);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk();
});

it('redirects dashboard access to billing after trial expiry', function (): void {
    $user = ($this->makeUser)([
        'trial_ends_at' => now()->subMinute(),
    ]);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertRedirect(route('billing.index'));
});

it('returns payment required for json requests after trial expiry', function (): void {
    $user = ($this->makeUser)([
        'trial_ends_at' => now()->subMinute(),
    ]);

    $this->actingAs($user)
        ->getJson(route('navigation.state'))
        ->assertStatus(402);
});

it('allows the billing page after trial expiry', function (): void {
    $user = ($this->makeUser)([
        'trial_ends_at' => now()->subMinute(),
    ]);

    $this->actingAs($user)
        ->get(route('billing.index'))
        ->assertOk()
        ->assertSee('Billing required');
});

it('allows the profile page after trial expiry', function (): void {
    $user = ($this->makeUser)([
        'trial_ends_at' => now()->subMinute(),
    ]);

    $this->actingAs($user)
        ->get(route('profile.edit'))
        ->assertOk();
});

it('does not exempt profile connector management after trial expiry', function (): void {
    $user = ($this->makeUser)([
        'trial_ends_at' => now()->subMinute(),
    ], ['system-users-manage']);

    $this->actingAs($user)
        ->get(route('profile.connectors.index'))
        ->assertRedirect(route('billing.index'));
});

it('shows the billing navigation item to billing admins', function (): void {
    $user = ($this->makeAdmin)([
        'trial_ends_at' => now()->addDay(),
    ]);

    $this->actingAs($user)
        ->get(route('billing.index'))
        ->assertOk()
        ->assertSee('Manage billing')
        ->assertSee('Add payment details')
        ->assertSee(route('billing.checkout.store'), false);
});

it('hides billing management controls from non-admin tenant users', function (): void {
    $user = ($this->makeUser)([
        'trial_ends_at' => now()->addDay(),
    ]);

    $this->actingAs($user)
        ->get(route('billing.index'))
        ->assertOk()
        ->assertSee('Billing managed by admins')
        ->assertDontSee('Manage billing');
});

it('creates the billing manage permission when roles and permissions are seeded', function (): void {
    $this->seed(TenancyRolesPermissionsSeeder::class);

    expect(Permission::query()->where('slug', 'billing-subscription-manage')->exists())->toBeTrue();
});

it('grants billing management to the seeded admin role', function (): void {
    $this->seed(TenancyRolesPermissionsSeeder::class);

    $adminRole = Role::query()->where('name', 'admin')->firstOrFail();

    expect($adminRole->permissions()->where('slug', 'billing-subscription-manage')->exists())->toBeTrue();
});

it('allows invited tenant users through the shared tenant trial', function (): void {
    $tenant = Tenant::factory()->create([
        'trial_ends_at' => now()->addDay(),
    ]);
    $invitedUser = User::factory()->for($tenant)->create();

    $this->actingAs($invitedUser)
        ->get(route('dashboard'))
        ->assertOk();
});

it('locks invited tenant users when the shared tenant trial expires', function (): void {
    $tenant = Tenant::factory()->create([
        'trial_ends_at' => now()->subMinute(),
    ]);
    $invitedUser = User::factory()->for($tenant)->create();

    $this->actingAs($invitedUser)
        ->get(route('dashboard'))
        ->assertRedirect(route('billing.index'));
});

it('allows dashboard access with active subscription status', function (): void {
    $user = ($this->makeUser)([
        'trial_ends_at' => now()->subDay(),
        'billing_subscription_status' => Tenant::BILLING_SUBSCRIPTION_STATUS_ACTIVE,
    ]);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk();
});

it('allows dashboard access with provider trialing subscription status', function (): void {
    $user = ($this->makeUser)([
        'trial_ends_at' => now()->subDay(),
        'billing_subscription_status' => Tenant::BILLING_SUBSCRIPTION_STATUS_TRIALING,
    ]);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk();
});

it('allows dashboard access with active subscription status until a future end date', function (): void {
    $user = ($this->makeUser)([
        'trial_ends_at' => now()->subDay(),
        'billing_subscription_status' => Tenant::BILLING_SUBSCRIPTION_STATUS_ACTIVE,
        'billing_subscription_ends_at' => now()->addDay(),
    ]);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk();
});

it('locks dashboard access when active subscription has ended', function (): void {
    $user = ($this->makeUser)([
        'trial_ends_at' => now()->subDay(),
        'billing_subscription_status' => Tenant::BILLING_SUBSCRIPTION_STATUS_ACTIVE,
        'billing_subscription_ends_at' => now()->subMinute(),
    ]);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertRedirect(route('billing.index'));
});

it('does not grant access for past due subscription status', function (): void {
    $user = ($this->makeUser)([
        'trial_ends_at' => now()->subDay(),
        'billing_subscription_status' => Tenant::BILLING_SUBSCRIPTION_STATUS_PAST_DUE,
    ]);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertRedirect(route('billing.index'));
});

it('allows dashboard access during a temporary billing exemption', function (): void {
    $user = ($this->makeUser)([
        'trial_ends_at' => now()->subDay(),
        'billing_exempt_until' => now()->addDay(),
    ]);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk();
});

it('locks dashboard access after a temporary billing exemption expires', function (): void {
    $user = ($this->makeUser)([
        'trial_ends_at' => now()->subDay(),
        'billing_exempt_until' => now()->subMinute(),
    ]);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertRedirect(route('billing.index'));
});

it('keeps domain permission gates layered under billing trial access', function (): void {
    $user = ($this->makeUser)([
        'trial_ends_at' => now()->addDay(),
    ]);

    $this->actingAs($user)
        ->get(route('materials.index'))
        ->assertForbidden();
});

it('blocks operational routes before domain gates when billing access has expired', function (): void {
    $user = ($this->makeUser)([
        'trial_ends_at' => now()->subMinute(),
    ], ['inventory-materials-view']);

    $this->actingAs($user)
        ->get(route('materials.index'))
        ->assertRedirect(route('billing.index'));
});

it('reports active access from the billing entitlement service during trial', function (): void {
    $tenant = Tenant::factory()->create([
        'trial_ends_at' => now()->addMinute(),
    ]);

    expect(app(TenantBillingEntitlement::class)->hasAccess($tenant))->toBeTrue();
});

it('reports inactive access from the billing entitlement service without trial subscription or exemption', function (): void {
    $tenant = Tenant::factory()->create([
        'trial_ends_at' => now()->subMinute(),
        'billing_exempt_until' => null,
        'billing_subscription_status' => null,
        'billing_subscription_ends_at' => null,
    ]);

    expect(app(TenantBillingEntitlement::class)->hasAccess($tenant))->toBeFalse();
});

it('redirects billing admins to stripe checkout during trial and preserves trial end', function (): void {
    config([
        'services.stripe.secret' => 'sk_test_123',
        'services.stripe.subscription_price_id' => 'price_123',
    ]);

    $trialEndsAt = now()->addDays(3);
    $user = ($this->makeAdmin)([
        'trial_ends_at' => $trialEndsAt,
    ]);

    Http::fake([
        'https://api.stripe.com/v1/checkout/sessions' => Http::response([
            'url' => 'https://checkout.stripe.test/session_123',
        ]),
    ]);

    $this->actingAs($user)
        ->post(route('billing.checkout.store'))
        ->assertRedirect('https://checkout.stripe.test/session_123');

    Http::assertSent(function ($request) use ($trialEndsAt): bool {
        $payload = $request->data();

        return $request->hasHeader('Authorization', 'Bearer sk_test_123')
            && $payload['mode'] === 'subscription'
            && $payload['line_items'][0]['price'] === 'price_123'
            && $payload['client_reference_id'] !== ''
            && $payload['subscription_data']['trial_end'] === $trialEndsAt->timestamp;
    });
});

it('does not allow non-admin tenant users to start checkout', function (): void {
    $user = ($this->makeUser)([
        'trial_ends_at' => now()->addDay(),
    ]);

    $this->actingAs($user)
        ->post(route('billing.checkout.store'))
        ->assertForbidden();
});

it('stores stripe identifiers from a completed checkout webhook', function (): void {
    config(['services.stripe.webhook_secret' => 'whsec_test']);

    $tenant = Tenant::factory()->create();
    $payload = json_encode([
        'type' => 'checkout.session.completed',
        'data' => [
            'object' => [
                'client_reference_id' => (string) $tenant->id,
                'customer' => 'cus_123',
                'subscription' => 'sub_123',
                'metadata' => [
                    'tenant_id' => (string) $tenant->id,
                ],
            ],
        ],
    ], JSON_THROW_ON_ERROR);

    ($this->postStripeWebhook)($payload, ($this->stripeSignature)($payload))->assertOk();

    $tenant->refresh();

    expect($tenant->billing_provider)->toBe('stripe')
        ->and($tenant->billing_provider_customer_id)->toBe('cus_123')
        ->and($tenant->billing_provider_subscription_id)->toBe('sub_123');
});

it('stores provider trialing subscription status from a stripe webhook', function (): void {
    config(['services.stripe.webhook_secret' => 'whsec_test']);

    $tenant = Tenant::factory()->create([
        'trial_ends_at' => now()->subDay(),
    ]);
    $payload = json_encode([
        'type' => 'customer.subscription.updated',
        'data' => [
            'object' => [
                'id' => 'sub_456',
                'customer' => 'cus_456',
                'status' => 'trialing',
                'metadata' => [
                    'tenant_id' => (string) $tenant->id,
                ],
            ],
        ],
    ], JSON_THROW_ON_ERROR);

    ($this->postStripeWebhook)($payload, ($this->stripeSignature)($payload))->assertOk();

    $tenant->refresh();

    expect($tenant->billing_provider)->toBe('stripe')
        ->and($tenant->billing_provider_customer_id)->toBe('cus_456')
        ->and($tenant->billing_provider_subscription_id)->toBe('sub_456')
        ->and($tenant->billing_subscription_status)->toBe(Tenant::BILLING_SUBSCRIPTION_STATUS_TRIALING)
        ->and(app(TenantBillingEntitlement::class)->hasAccess($tenant))->toBeTrue();
});

it('rejects stripe webhooks with invalid signatures', function (): void {
    config(['services.stripe.webhook_secret' => 'whsec_test']);

    $payload = json_encode([
        'type' => 'customer.subscription.updated',
        'data' => ['object' => []],
    ], JSON_THROW_ON_ERROR);

    ($this->postStripeWebhook)($payload, 't=' . now()->timestamp . ',v1=invalid')->assertStatus(400);
});
