<?php

declare(strict_types=1);

use App\Models\Permission;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\TenantUserInvitation;
use App\Models\User;
use App\Notifications\TenantUserInvitationNotification;
use Database\Seeders\TenancyRolesPermissionsSeeder;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    $this->roleCounter = 1;

    $this->makeTenant = fn (string $name = 'Tenant A'): Tenant => Tenant::factory()->create([
        'tenant_name' => $name,
    ]);

    $this->makeUser = fn (Tenant $tenant, array $attributes = []): User => User::factory()->create(array_merge([
        'tenant_id' => $tenant->id,
        'email_verified_at' => now(),
    ], $attributes));

    $this->grantPermission = function (User $user, string $slug): void {
        $permission = Permission::query()->firstOrCreate([
            'slug' => $slug,
        ]);

        $role = Role::query()->create([
            'name' => 'tenant-user-management-role-' . $this->roleCounter,
        ]);

        $this->roleCounter++;

        $role->permissions()->syncWithoutDetaching([$permission->id]);
        $user->roles()->syncWithoutDetaching([$role->id]);
    };

    $this->makeAdmin = function (Tenant $tenant): User {
        $user = ($this->makeUser)($tenant);

        ($this->grantPermission)($user, 'admin-users-view');
        ($this->grantPermission)($user, 'admin-users-manage');

        return $user;
    };

    $this->makeRole = fn (string $name): Role => Role::query()->firstOrCreate([
        'name' => $name,
    ]);

    $this->createInvitation = function (Tenant $tenant, string $email, string $roleName = 'sales'): TenantUserInvitation {
        $role = ($this->makeRole)($roleName);

        return TenantUserInvitation::withoutGlobalScopes()->create([
            'tenant_id' => $tenant->id,
            'email' => $email,
            'role_id' => $role->id,
            'token' => hash('sha256', 'valid-token-' . $email),
            'expires_at' => now()->addDays(7),
        ]);
    };

    $this->extractCrudConfig = function (string $html): array {
        preg_match('/data-crud-config=([\'"])(.*?)\1/s', $html, $matches);

        return json_decode(html_entity_decode($matches[2] ?? '', ENT_QUOTES), true) ?: [];
    };

    $this->listRows = function (User $user): array {
        return $this->actingAs($user)
            ->getJson(route('admin.users.list'))
            ->assertOk()
            ->json('data');
    };
});

it('admin can view user management', function () {
    $tenant = ($this->makeTenant)();
    $admin = ($this->makeAdmin)($tenant);

    $this->actingAs($admin)
        ->get(route('admin.users.index'))
        ->assertOk()
        ->assertSee('Users');
});

it('users index renders through the configured CRUD page module contract', function () {
    $tenant = ($this->makeTenant)();
    $admin = ($this->makeAdmin)($tenant);

    $response = $this->actingAs($admin)
        ->get(route('admin.users.index'))
        ->assertOk()
        ->assertSee('data-page="admin-users-index"', false)
        ->assertSee('data-crud-config', false)
        ->assertSee('data-crud-root', false);

    $config = ($this->extractCrudConfig)($response->getContent());

    expect($config['resource'])->toBe('admin-users')
        ->and($config['endpoints']['list'])->toBe(route('admin.users.list'))
        ->and($config['columns'])->toBe(['name', 'email', 'role', 'status'])
        ->and($config['desktopList']['enabled'])->toBeTrue()
        ->and($config['desktopList']['titleExpression'])->toBe("record.name || record.email || '-'")
        ->and($config['desktopList']['urlExpression'])->toBe("record.email ? 'mailto:' + record.email : '#'")
        ->and($config['desktopList']['subtitleUrlExpression'])->toBe("record.email ? 'mailto:' + record.email : '#'");
});

it('users index does not render the bespoke members and invitations cards', function () {
    $tenant = ($this->makeTenant)();
    $admin = ($this->makeAdmin)($tenant);

    $this->actingAs($admin)
        ->get(route('admin.users.index'))
        ->assertOk()
        ->assertDontSee('Team members')
        ->assertDontSee('No members found.')
        ->assertDontSee('No pending invitations.');
});

it('non-admin cannot view user management', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);

    $this->actingAs($user)
        ->get(route('admin.users.index'))
        ->assertForbidden();
});

it('unauthenticated user cannot view user management', function () {
    $this->get(route('admin.users.index'))
        ->assertRedirect(route('login'));
});

it('guests cannot access admin invitation management routes', function () {
    $tenant = ($this->makeTenant)();
    $invitation = ($this->createInvitation)($tenant, 'guest.admin@example.com');

    $this->postJson(route('admin.users.invitations.store'), [
        'email' => 'guest.create@example.com',
        'role_id' => ($this->makeRole)('sales')->id,
    ])->assertUnauthorized();

    $this->postJson(route('admin.users.invitations.resend', $invitation))
        ->assertUnauthorized();

    $this->deleteJson(route('admin.users.invitations.revoke', $invitation))
        ->assertUnauthorized();

    $this->getJson(route('admin.users.invitations.show', $invitation))
        ->assertUnauthorized();
});

it('admin can create an invitation', function () {
    Notification::fake();

    $tenant = ($this->makeTenant)();
    $admin = ($this->makeAdmin)($tenant);
    $role = ($this->makeRole)('sales');

    $this->actingAs($admin)
        ->postJson(route('admin.users.invitations.store'), [
            'email' => 'new.member@example.com',
            'role_id' => $role->id,
        ])
        ->assertCreated()
        ->assertJsonPath('data.email', 'new.member@example.com')
        ->assertJsonPath('data.role', 'sales');

    $this->assertDatabaseHas('tenant_user_invitations', [
        'tenant_id' => $tenant->id,
        'email' => 'new.member@example.com',
        'role_id' => $role->id,
        'accepted_at' => null,
        'accepted_user_id' => null,
    ]);
});

it('invite token is generated hashed', function () {
    Notification::fake();

    $tenant = ($this->makeTenant)();
    $admin = ($this->makeAdmin)($tenant);
    $role = ($this->makeRole)('sales');

    $this->actingAs($admin)
        ->postJson(route('admin.users.invitations.store'), [
            'email' => 'hashed.token@example.com',
            'role_id' => $role->id,
        ])
        ->assertCreated();

    $invitation = TenantUserInvitation::withoutGlobalScopes()
        ->where('email', 'hashed.token@example.com')
        ->firstOrFail();

    expect($invitation->token)->toHaveLength(64)
        ->and($invitation->token)->not->toBe('hashed.token@example.com');
});

it('invite email notification is sent', function () {
    Notification::fake();

    $tenant = ($this->makeTenant)();
    $admin = ($this->makeAdmin)($tenant);
    $role = ($this->makeRole)('sales');

    $this->actingAs($admin)
        ->postJson(route('admin.users.invitations.store'), [
            'email' => 'notify.invite@example.com',
            'role_id' => $role->id,
        ])
        ->assertCreated();

    Notification::assertSentOnDemand(TenantUserInvitationNotification::class);
});

it('invite email uses the public guest invitation registration route', function () {
    Notification::fake();

    $tenant = ($this->makeTenant)();
    $admin = ($this->makeAdmin)($tenant);
    $role = ($this->makeRole)('sales');

    $this->actingAs($admin)
        ->postJson(route('admin.users.invitations.store'), [
            'email' => 'public.route@example.com',
            'role_id' => $role->id,
        ])
        ->assertCreated();

    Notification::assertSentOnDemand(
        TenantUserInvitationNotification::class,
        function (...$arguments): bool {
            $notification = collect($arguments)
                ->first(fn (mixed $argument): bool => $argument instanceof TenantUserInvitationNotification);
            $notifiable = collect($arguments)
                ->first(fn (mixed $argument): bool => is_object($argument)
                    && ! $argument instanceof TenantUserInvitationNotification);
            $mail = $notification->toMail($notifiable);

            return str_contains($mail->actionUrl, '/invitations/')
                && str_contains($mail->actionUrl, '/register');
        }
    );
});

it('invite email does not use admin invitation show route', function () {
    Notification::fake();

    $tenant = ($this->makeTenant)();
    $admin = ($this->makeAdmin)($tenant);
    $role = ($this->makeRole)('sales');

    $this->actingAs($admin)
        ->postJson(route('admin.users.invitations.store'), [
            'email' => 'not.admin.route@example.com',
            'role_id' => $role->id,
        ])
        ->assertCreated();

    Notification::assertSentOnDemand(
        TenantUserInvitationNotification::class,
        function (...$arguments): bool {
            $notification = collect($arguments)
                ->first(fn (mixed $argument): bool => $argument instanceof TenantUserInvitationNotification);
            $notifiable = collect($arguments)
                ->first(fn (mixed $argument): bool => is_object($argument)
                    && ! $argument instanceof TenantUserInvitationNotification);

            return ! str_contains($notification->toMail($notifiable)->actionUrl, '/admin/users/invitations/');
        }
    );
});

it('guest can view valid invite registration form', function () {
    $tenant = ($this->makeTenant)();
    ($this->createInvitation)($tenant, 'guest.form@example.com');

    $this->get(route('invitations.register', 'valid-token-guest.form@example.com'))
        ->assertOk()
        ->assertSee('guest.form@example.com')
        ->assertSee('Create Account');
});

it('guest does not receive forbidden for valid invite link', function () {
    $tenant = ($this->makeTenant)();
    ($this->createInvitation)($tenant, 'guest.not.forbidden@example.com');

    $this->get(route('invitations.register', 'valid-token-guest.not.forbidden@example.com'))
        ->assertStatus(200);
});

it('invite registration route does not require auth', function () {
    $tenant = ($this->makeTenant)();
    ($this->createInvitation)($tenant, 'no.auth@example.com');

    $this->get(route('invitations.register', 'valid-token-no.auth@example.com'))
        ->assertOk()
        ->assertSee('no.auth@example.com');
});

it('invite registration route does not require verified middleware', function () {
    $route = \Illuminate\Support\Facades\Route::getRoutes()->getByName('invitations.register');

    expect($route?->gatherMiddleware())->not->toContain('verified');
});

it('invite registration routes use guest middleware', function () {
    $getRoute = \Illuminate\Support\Facades\Route::getRoutes()->getByName('invitations.register');
    $postRoute = \Illuminate\Support\Facades\Route::getRoutes()->getByName('invitations.register.store');

    expect($getRoute?->gatherMiddleware())->toContain('guest')
        ->and($postRoute?->gatherMiddleware())->toContain('guest');
});

it('active members appear in the CRUD list', function () {
    $tenant = ($this->makeTenant)();
    $admin = ($this->makeAdmin)($tenant);
    $member = ($this->makeUser)($tenant, [
        'name' => 'Active Member',
        'email' => 'active.member@example.com',
    ]);
    $role = ($this->makeRole)('sales');
    $member->roles()->syncWithoutDetaching([$role->id]);

    $rows = ($this->listRows)($admin);

    expect(collect($rows)->contains(fn (array $row): bool => $row['email'] === 'active.member@example.com'
        && $row['name'] === 'Active Member'
        && $row['role'] === 'sales'
        && $row['status'] === 'Active'))->toBeTrue();
});

it('pending invitations appear in the CRUD list', function () {
    $tenant = ($this->makeTenant)();
    $admin = ($this->makeAdmin)($tenant);
    ($this->createInvitation)($tenant, 'pending.invite@example.com', 'inventory');

    $rows = ($this->listRows)($admin);

    expect(collect($rows)->contains(fn (array $row): bool => $row['email'] === 'pending.invite@example.com'
        && $row['role'] === 'inventory'
        && $row['status'] === 'Pending'))->toBeTrue();
});

it('expired invitations appear in the CRUD list', function () {
    $tenant = ($this->makeTenant)();
    $admin = ($this->makeAdmin)($tenant);
    $invitation = ($this->createInvitation)($tenant, 'expired.invite@example.com', 'sales');
    $invitation->update(['expires_at' => now()->subDay()]);

    $rows = ($this->listRows)($admin);

    expect(collect($rows)->contains(fn (array $row): bool => $row['email'] === 'expired.invite@example.com'
        && $row['status'] === 'Expired'))->toBeTrue();
});

it('status labels render correctly in the CRUD list read model', function () {
    $tenant = ($this->makeTenant)();
    $admin = ($this->makeAdmin)($tenant);
    ($this->makeUser)($tenant, ['email' => 'member.status@example.com']);
    ($this->createInvitation)($tenant, 'pending.status@example.com');
    $expired = ($this->createInvitation)($tenant, 'expired.status@example.com');
    $expired->update(['expires_at' => now()->subDay()]);

    $statuses = collect(($this->listRows)($admin))->pluck('status')->all();

    expect($statuses)->toContain('Active')
        ->and($statuses)->toContain('Pending')
        ->and($statuses)->toContain('Expired');
});

it('invite member remains the primary create action', function () {
    $tenant = ($this->makeTenant)();
    $admin = ($this->makeAdmin)($tenant);

    $response = $this->actingAs($admin)
        ->get(route('admin.users.index'))
        ->assertOk();

    $config = ($this->extractCrudConfig)($response->getContent());

    expect($config['labels']['createTitle'])->toBe('Invite Member')
        ->and($config['labels']['createAriaLabel'])->toBe('Invite Member')
        ->and($config['permissions']['showCreate'])->toBeTrue();
});

it('role selection remains available during invite', function () {
    $tenant = ($this->makeTenant)();
    $admin = ($this->makeAdmin)($tenant);
    ($this->makeRole)('sales');

    $this->actingAs($admin)
        ->get(route('admin.users.index'))
        ->assertOk()
        ->assertSee('id="invite-role"', false)
        ->assertSee('x-model="inviteForm.role_id"', false)
        ->assertSee('sales');
});

it('active member rows expose valid vertical-dots actions', function () {
    $tenant = ($this->makeTenant)();
    $admin = ($this->makeAdmin)($tenant);
    $member = ($this->makeUser)($tenant, ['email' => 'actions.member@example.com']);

    $rows = collect(($this->listRows)($admin));
    $row = $rows->firstWhere('record_id', $member->id);

    expect($row['available_actions'])->toBe(['change-role', 'remove-member']);
});

it('pending invite rows expose valid vertical-dots actions', function () {
    $tenant = ($this->makeTenant)();
    $admin = ($this->makeAdmin)($tenant);
    ($this->createInvitation)($tenant, 'actions.pending@example.com');

    $rows = collect(($this->listRows)($admin));
    $row = $rows->firstWhere('email', 'actions.pending@example.com');

    expect($row['available_actions'])->toBe(['change-role', 'resend-invite', 'revoke-invite']);
});

it('resend invite works', function () {
    Notification::fake();

    $tenant = ($this->makeTenant)();
    $admin = ($this->makeAdmin)($tenant);
    $invitation = ($this->createInvitation)($tenant, 'resend@example.com');
    $originalToken = $invitation->token;

    $this->actingAs($admin)
        ->postJson(route('admin.users.invitations.resend', $invitation))
        ->assertOk()
        ->assertJsonPath('data.email', 'resend@example.com');

    $invitation->refresh();

    expect($invitation->token)->not->toBe($originalToken)
        ->and($invitation->expires_at->isFuture())->toBeTrue()
        ->and($invitation->revoked_at)->toBeNull();

    Notification::assertSentOnDemand(TenantUserInvitationNotification::class);
});

it('revoke invite works', function () {
    $tenant = ($this->makeTenant)();
    $admin = ($this->makeAdmin)($tenant);
    $invitation = ($this->createInvitation)($tenant, 'revoke@example.com');

    $this->actingAs($admin)
        ->deleteJson(route('admin.users.invitations.revoke', $invitation))
        ->assertOk()
        ->assertJsonPath('data.email', 'revoke@example.com');

    expect($invitation->refresh()->revoked_at)->not->toBeNull();
});

it('expired invite rows expose valid vertical-dots actions', function () {
    $tenant = ($this->makeTenant)();
    $admin = ($this->makeAdmin)($tenant);
    $invitation = ($this->createInvitation)($tenant, 'actions.expired@example.com');
    $invitation->update(['expires_at' => now()->subDay()]);

    $rows = collect(($this->listRows)($admin));
    $row = $rows->firstWhere('email', 'actions.expired@example.com');

    expect($row['available_actions'])->toBe(['resend-invite', 'revoke-invite']);
});

it('users index config includes the shared vertical-dots row action menu contract', function () {
    $tenant = ($this->makeTenant)();
    $admin = ($this->makeAdmin)($tenant);

    $response = $this->actingAs($admin)
        ->get(route('admin.users.index'))
        ->assertOk();

    $config = ($this->extractCrudConfig)($response->getContent());

    expect($config['rowActions']['mode'])->toBe('menu')
        ->and($config['rowActions']['icon'])->toBe('ellipsis-vertical')
        ->and(collect($config['actions'])->pluck('id')->all())->toBe([
            'change-role',
            'remove-member',
            'resend-invite',
            'revoke-invite',
        ]);
});

it('shared CRUD card renderer supports an opt-in desktop stacked list contract', function () {
    $renderer = file_get_contents(resource_path('js/lib/crud-card-page.js'));
    $config = file_get_contents(resource_path('js/lib/crud-config.js'));

    expect($renderer)->toContain('const renderDesktopList = (config) =>')
        ->and($renderer)->toContain('data-crud-stacked-list')
        ->and($renderer)->toContain('normalized.desktopList.enabled ? renderDesktopList(normalized) : renderCardGrid(normalized)')
        ->and($config)->toContain('desktopList: {')
        ->and($config)->toContain('enabled: Boolean(rawDesktopList.enabled)');
});

it('unauthorized users cannot access the users list endpoint', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);

    $this->actingAs($user)
        ->getJson(route('admin.users.list'))
        ->assertForbidden();
});

it('cross-tenant members and invitations are not included in the CRUD list', function () {
    $tenant = ($this->makeTenant)();
    $otherTenant = ($this->makeTenant)('Tenant B');
    $admin = ($this->makeAdmin)($tenant);

    ($this->makeUser)($tenant, ['email' => 'same.tenant@example.com']);
    ($this->makeUser)($otherTenant, ['email' => 'other.member@example.com']);
    ($this->createInvitation)($tenant, 'same.invite@example.com');
    ($this->createInvitation)($otherTenant, 'other.invite@example.com');

    $emails = collect(($this->listRows)($admin))->pluck('email')->all();

    expect($emails)->toContain('same.tenant@example.com')
        ->and($emails)->toContain('same.invite@example.com')
        ->and($emails)->not->toContain('other.member@example.com')
        ->and($emails)->not->toContain('other.invite@example.com');
});

it('invite email is required', function () {
    $tenant = ($this->makeTenant)();
    $admin = ($this->makeAdmin)($tenant);
    $role = ($this->makeRole)('sales');

    $this->actingAs($admin)
        ->postJson(route('admin.users.invitations.store'), [
            'role_id' => $role->id,
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['email']);
});

it('invite role is required', function () {
    $tenant = ($this->makeTenant)();
    $admin = ($this->makeAdmin)($tenant);

    $this->actingAs($admin)
        ->postJson(route('admin.users.invitations.store'), [
            'email' => 'missing.role@example.com',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['role_id']);
});

it('invalid role is rejected', function () {
    $tenant = ($this->makeTenant)();
    $admin = ($this->makeAdmin)($tenant);

    $this->actingAs($admin)
        ->postJson(route('admin.users.invitations.store'), [
            'email' => 'bad.role@example.com',
            'role_id' => 999999,
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['role_id']);
});

it('invite is tenant-scoped', function () {
    Notification::fake();

    $tenant = ($this->makeTenant)();
    $otherTenant = ($this->makeTenant)('Tenant B');
    $admin = ($this->makeAdmin)($tenant);
    $role = ($this->makeRole)('inventory');

    $this->actingAs($admin)
        ->postJson(route('admin.users.invitations.store'), [
            'email' => 'tenant.member@example.com',
            'role_id' => $role->id,
        ])
        ->assertCreated();

    $this->assertDatabaseHas('tenant_user_invitations', [
        'tenant_id' => $tenant->id,
        'email' => 'tenant.member@example.com',
    ]);

    $this->assertDatabaseMissing('tenant_user_invitations', [
        'tenant_id' => $otherTenant->id,
        'email' => 'tenant.member@example.com',
    ]);
});

it('invite token is generated', function () {
    Notification::fake();

    $tenant = ($this->makeTenant)();
    $admin = ($this->makeAdmin)($tenant);
    $role = ($this->makeRole)('sales');

    $this->actingAs($admin)
        ->postJson(route('admin.users.invitations.store'), [
            'email' => 'tokened@example.com',
            'role_id' => $role->id,
        ])
        ->assertCreated();

    $invitation = TenantUserInvitation::withoutGlobalScopes()
        ->where('email', 'tokened@example.com')
        ->firstOrFail();

    expect($invitation->token)->toBeString()->not->toBe('');
});

it('invite expiry is stored', function () {
    Notification::fake();

    $tenant = ($this->makeTenant)();
    $admin = ($this->makeAdmin)($tenant);
    $role = ($this->makeRole)('sales');

    $this->actingAs($admin)
        ->postJson(route('admin.users.invitations.store'), [
            'email' => 'expires@example.com',
            'role_id' => $role->id,
        ])
        ->assertCreated();

    $invitation = TenantUserInvitation::withoutGlobalScopes()
        ->where('email', 'expires@example.com')
        ->firstOrFail();

    expect($invitation->expires_at)->not->toBeNull()
        ->and($invitation->expires_at->isFuture())->toBeTrue();
});

it('expired invite cannot be accepted', function () {
    $tenant = ($this->makeTenant)();
    $role = ($this->makeRole)('sales');

    TenantUserInvitation::withoutGlobalScopes()->create([
        'tenant_id' => $tenant->id,
        'email' => 'expired@example.com',
        'role_id' => $role->id,
        'token' => hash('sha256', 'expired-token'),
        'expires_at' => now()->subMinute(),
    ]);

    $this->post(route('invitations.register.store', 'expired-token'), [
        'name' => 'Expired Invite',
        'password' => 'password',
        'password_confirmation' => 'password',
    ])->assertForbidden();
});

it('invalid token cannot be accepted', function () {
    $this->post(route('invitations.register.store', 'not-a-real-token'), [
        'name' => 'Invalid Invite',
        'password' => 'password',
        'password_confirmation' => 'password',
    ])->assertNotFound();
});

it('accepted token cannot be reused', function () {
    $tenant = ($this->makeTenant)();
    $role = ($this->makeRole)('sales');
    $acceptedUser = ($this->makeUser)($tenant, ['email' => 'accepted@example.com']);

    TenantUserInvitation::withoutGlobalScopes()->create([
        'tenant_id' => $tenant->id,
        'email' => 'accepted@example.com',
        'role_id' => $role->id,
        'token' => hash('sha256', 'accepted-token'),
        'expires_at' => now()->addDay(),
        'accepted_at' => now(),
        'accepted_user_id' => $acceptedUser->id,
    ]);

    $this->post(route('invitations.register.store', 'accepted-token'), [
        'name' => 'Accepted Invite',
        'password' => 'password',
        'password_confirmation' => 'password',
    ])->assertForbidden();
});

it('revoked invite cannot be accepted', function () {
    $tenant = ($this->makeTenant)();
    $role = ($this->makeRole)('sales');

    TenantUserInvitation::withoutGlobalScopes()->create([
        'tenant_id' => $tenant->id,
        'email' => 'revoked.accept@example.com',
        'role_id' => $role->id,
        'token' => hash('sha256', 'revoked-token'),
        'expires_at' => now()->addDay(),
        'revoked_at' => now(),
    ]);

    $this->post(route('invitations.register.store', 'revoked-token'), [
        'name' => 'Revoked Invite',
        'email' => 'revoked.accept@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ])->assertForbidden();
});

it('invited registration creates a user', function () {
    $tenant = ($this->makeTenant)();
    ($this->createInvitation)($tenant, 'invited@example.com');

    $this->post(route('invitations.register.store', 'valid-token-invited@example.com'), [
        'name' => 'Invited User',
        'email' => 'invited@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ])->assertRedirect(route('dashboard', absolute: false));

    $this->assertDatabaseHas('users', [
        'email' => 'invited@example.com',
        'name' => 'Invited User',
    ]);
});

it('invited registration sets email verified at', function () {
    $tenant = ($this->makeTenant)();
    ($this->createInvitation)($tenant, 'verified.invited@example.com');

    $this->post(route('invitations.register.store', 'valid-token-verified.invited@example.com'), [
        'name' => 'Verified Invited',
        'email' => 'verified.invited@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    $user = User::query()->where('email', 'verified.invited@example.com')->firstOrFail();

    expect($user->email_verified_at)->not->toBeNull();
});

it('invited registration attaches user to inviting tenant', function () {
    $tenant = ($this->makeTenant)();
    ($this->createInvitation)($tenant, 'tenant.invited@example.com');

    $this->post(route('invitations.register.store', 'valid-token-tenant.invited@example.com'), [
        'name' => 'Tenant Invited',
        'email' => 'tenant.invited@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    $this->assertDatabaseHas('users', [
        'tenant_id' => $tenant->id,
        'email' => 'tenant.invited@example.com',
    ]);
});

it('invited registration assigns selected role', function () {
    $tenant = ($this->makeTenant)();
    $role = ($this->makeRole)('inventory');
    ($this->createInvitation)($tenant, 'role.invited@example.com', 'inventory');

    $this->post(route('invitations.register.store', 'valid-token-role.invited@example.com'), [
        'name' => 'Role Invited',
        'email' => 'role.invited@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    $user = User::query()->where('email', 'role.invited@example.com')->firstOrFail();

    expect($user->roles()->pluck('roles.id')->all())->toContain($role->id);
});

it('invited registration does not create a new tenant', function () {
    $tenant = ($this->makeTenant)();
    ($this->createInvitation)($tenant, 'no.new.tenant@example.com');

    expect(Tenant::query()->count())->toBe(1);

    $this->post(route('invitations.register.store', 'valid-token-no.new.tenant@example.com'), [
        'name' => 'No New Tenant',
        'email' => 'no.new.tenant@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    expect(Tenant::query()->count())->toBe(1);
});

it('normal registration still creates a tenant and admin', function () {
    Notification::fake();

    $this->post('/register', [
        'name' => 'Normal User',
        'email' => 'normal@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ])->assertRedirect(route('dashboard', absolute: false));

    $user = User::query()->where('email', 'normal@example.com')->firstOrFail();

    expect(Tenant::query()->count())->toBe(1)
        ->and($user->tenant_id)->not->toBeNull()
        ->and($user->roles()->where('name', 'admin')->exists())->toBeTrue();
});

it('normal registration requires email verification', function () {
    Notification::fake();

    $this->post('/register', [
        'name' => 'Verify User',
        'email' => 'verify@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ])->assertRedirect(route('dashboard', absolute: false));

    $user = User::query()->where('email', 'verify@example.com')->firstOrFail();

    expect($user->email_verified_at)->toBeNull();

    Notification::assertSentTo($user, VerifyEmail::class);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('Your email is not verified.');
});

it('normal login still works', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant, [
        'email' => 'login.works@example.com',
        'password' => Hash::make('password'),
    ]);

    $this->post(route('login'), [
        'email' => $user->email,
        'password' => 'password',
    ])->assertRedirect(route('dashboard', absolute: false));
});

it('invited registration uses invitation email instead of form email', function () {
    $tenant = ($this->makeTenant)();
    ($this->createInvitation)($tenant, 'invitation.email@example.com');

    $this->post(route('invitations.register.store', 'valid-token-invitation.email@example.com'), [
        'name' => 'Invitation Email',
        'email' => 'wrong@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ])->assertRedirect(route('dashboard', absolute: false));

    $this->assertDatabaseHas('users', [
        'email' => 'invitation.email@example.com',
        'name' => 'Invitation Email',
    ]);

    $this->assertDatabaseMissing('users', [
        'email' => 'wrong@example.com',
    ]);
});

it('invite acceptance can verify and attach an existing unverified user', function () {
    $sourceTenant = ($this->makeTenant)('Source Tenant');
    $targetTenant = ($this->makeTenant)('Target Tenant');
    $role = ($this->makeRole)('inventory');
    $existingUser = ($this->makeUser)($sourceTenant, [
        'email' => 'unverified.existing@example.com',
        'email_verified_at' => null,
    ]);
    ($this->createInvitation)($targetTenant, 'unverified.existing@example.com', 'inventory');

    $this->post(route('invitations.register.store', 'valid-token-unverified.existing@example.com'), [
        'name' => 'Updated Existing',
        'email' => 'unverified.existing@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ])->assertRedirect(route('dashboard', absolute: false));

    $existingUser->refresh();

    expect($existingUser->tenant_id)->toBe($targetTenant->id)
        ->and($existingUser->email_verified_at)->not->toBeNull()
        ->and($existingUser->roles()->pluck('roles.id')->all())->toContain($role->id);
});

it('verified existing user must authenticate before accepting invite', function () {
    $sourceTenant = ($this->makeTenant)('Source Tenant');
    $targetTenant = ($this->makeTenant)('Target Tenant');
    ($this->makeUser)($sourceTenant, ['email' => 'verified.existing@example.com']);
    ($this->createInvitation)($targetTenant, 'verified.existing@example.com');

    $this->post(route('invitations.register.store', 'valid-token-verified.existing@example.com'), [
        'name' => 'Verified Existing',
        'email' => 'verified.existing@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ])->assertRedirect(route('login'));
});

it('authenticated user cannot accept invite for a different email', function () {
    $tenant = ($this->makeTenant)();
    $authenticatedUser = ($this->makeUser)($tenant, ['email' => 'signed.in@example.com']);
    ($this->createInvitation)($tenant, 'invited.other@example.com');

    $this->actingAs($authenticatedUser)
        ->post(route('invitations.register.store', 'valid-token-invited.other@example.com'))
        ->assertRedirect(route('dashboard', absolute: false));
});

it('cross-tenant invitation access is blocked', function () {
    $tenant = ($this->makeTenant)();
    $otherTenant = ($this->makeTenant)('Tenant B');
    $admin = ($this->makeAdmin)($tenant);
    $otherInvitation = ($this->createInvitation)($otherTenant, 'other.tenant@example.com');

    $this->actingAs($admin)
        ->getJson(route('admin.users.invitations.show', $otherInvitation))
        ->assertNotFound();
});

it('duplicate pending invitation email is rejected for same tenant', function () {
    $tenant = ($this->makeTenant)();
    $admin = ($this->makeAdmin)($tenant);
    $role = ($this->makeRole)('sales');

    ($this->createInvitation)($tenant, 'duplicate@example.com', 'sales');

    $this->actingAs($admin)
        ->postJson(route('admin.users.invitations.store'), [
            'email' => 'duplicate@example.com',
            'role_id' => $role->id,
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['email']);
});

it('existing verified user email can be invited for authenticated acceptance', function () {
    Notification::fake();

    $tenant = ($this->makeTenant)();
    $otherTenant = ($this->makeTenant)('Tenant B');
    $admin = ($this->makeAdmin)($tenant);
    $existingUser = ($this->makeUser)($otherTenant, ['email' => 'existing@example.com']);
    $role = ($this->makeRole)('sales');

    $this->actingAs($admin)
        ->postJson(route('admin.users.invitations.store'), [
            'email' => $existingUser->email,
            'role_id' => $role->id,
        ])
        ->assertCreated()
        ->assertJsonPath('data.email', 'existing@example.com');
});

it('permission slugs allow tenant admins and deny users without permission', function () {
    $tenant = ($this->makeTenant)();
    $allowed = ($this->makeUser)($tenant);
    $denied = ($this->makeUser)($tenant);

    ($this->grantPermission)($allowed, 'admin-users-view');
    ($this->grantPermission)($allowed, 'admin-users-manage');

    $this->actingAs($allowed)
        ->get(route('admin.users.index'))
        ->assertOk();

    $this->actingAs($denied)
        ->get(route('admin.users.index'))
        ->assertForbidden();
});

it('admin users manage permission is required to create invitations', function () {
    $tenant = ($this->makeTenant)();
    $viewer = ($this->makeUser)($tenant);
    $role = ($this->makeRole)('sales');

    ($this->grantPermission)($viewer, 'admin-users-view');

    $this->actingAs($viewer)
        ->postJson(route('admin.users.invitations.store'), [
            'email' => 'manage.required@example.com',
            'role_id' => $role->id,
        ])
        ->assertForbidden();
});

it('super-admin bypass can view and create invitations', function () {
    Notification::fake();

    $this->seed(TenancyRolesPermissionsSeeder::class);

    $tenant = ($this->makeTenant)('Bypass Tenant');
    $superAdminRole = Role::query()->where('name', 'super-admin')->firstOrFail();
    $superAdmin = ($this->makeUser)($tenant);
    $superAdmin->roles()->syncWithoutDetaching([$superAdminRole->id]);
    $role = Role::query()->where('name', 'sales')->firstOrFail();

    $this->actingAs($superAdmin)
        ->get(route('admin.users.index'))
        ->assertOk();

    $this->actingAs($superAdmin)
        ->getJson(route('admin.users.list'))
        ->assertOk();

    $this->actingAs($superAdmin)
        ->postJson(route('admin.users.invitations.store'), [
            'email' => 'bypass@example.com',
            'role_id' => $role->id,
        ])
        ->assertCreated();
});

it('invitation acceptance marks invitation accepted with accepted user', function () {
    $tenant = ($this->makeTenant)();
    $invitation = ($this->createInvitation)($tenant, 'accepted.marker@example.com');

    $this->post(route('invitations.register.store', 'valid-token-accepted.marker@example.com'), [
        'name' => 'Accepted Marker',
        'email' => 'accepted.marker@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    $user = User::query()->where('email', 'accepted.marker@example.com')->firstOrFail();
    $invitation->refresh();

    expect($invitation->accepted_at)->not->toBeNull()
        ->and($invitation->accepted_user_id)->toBe($user->id);
});

it('invited registration stores hashed password', function () {
    $tenant = ($this->makeTenant)();
    ($this->createInvitation)($tenant, 'hashed@example.com');

    $this->post(route('invitations.register.store', 'valid-token-hashed@example.com'), [
        'name' => 'Hashed User',
        'email' => 'hashed@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    $user = User::query()->where('email', 'hashed@example.com')->firstOrFail();

    expect(Hash::check('password', $user->password))->toBeTrue();
});
