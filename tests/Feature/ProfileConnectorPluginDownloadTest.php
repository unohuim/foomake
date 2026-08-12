<?php

declare(strict_types=1);

use App\Models\Permission;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WordPressPluginConnection;
use App\Models\WordPressPluginPairingCode;
use App\Support\Integrations\WordPressPluginArchive;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->roleCounter = 1;

    $this->makeUser = function (array $tenantAttributes = [], array $permissionSlugs = []): User {
        $tenant = Tenant::factory()->create($tenantAttributes);
        $user = User::factory()->for($tenant)->create();

        if ($permissionSlugs !== []) {
            $role = Role::query()->create([
                'name' => 'connector-test-role-' . $this->roleCounter++,
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

    $this->fakeArchivePath = function (): string {
        $path = tempnam(sys_get_temp_dir(), 'foomake-test-plugin-');

        expect($path)->not->toBeFalse();

        file_put_contents($path, 'fake zip');

        return $path;
    };

    $this->pluginUuid = '123e4567-e89b-12d3-a456-426614174000';
    $this->siteUrl = 'http://wordpress.test/';
    $this->callbackUrl = 'http://wordpress.test/wp-admin/admin-post.php?action=foomake_connector_complete_pairing';

    $this->createPairing = function (string $code = 'pairing-code', array $attributes = []): WordPressPluginPairingCode {
        return WordPressPluginPairingCode::query()->create(array_merge([
            'plugin_uuid' => $this->pluginUuid,
            'site_url' => $this->siteUrl,
            'site_name' => 'Local Woo',
            'callback_url' => $this->callbackUrl,
            'code_hash' => WordPressPluginPairingCode::hashCode($code),
            'expires_at' => now()->addMinutes(15),
        ], $attributes));
    };

    $this->startPairingPayload = fn (array $overrides = []): array => array_merge([
        'plugin_uuid' => $this->pluginUuid,
        'site_url' => $this->siteUrl,
        'site_name' => 'Local Woo',
        'callback_url' => $this->callbackUrl,
    ], $overrides);
});

it('redirects guests away from the connectors page', function (): void {
    $this->get(route('profile.connectors.index'))
        ->assertRedirect(route('login'));
});

it('blocks connector page access without connector management permission', function (): void {
    $user = ($this->makeUser)();

    $this->actingAs($user)
        ->get(route('profile.connectors.index'))
        ->assertForbidden();
});

it('renders the connectors page as an inertia page for connector managers', function (): void {
    $user = ($this->makeUser)([], ['system-users-manage']);

    $this->actingAs($user)
        ->get(route('profile.connectors.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->component('Profile/Connectors/Index')
            ->has('shell.navigation.groups')
            ->where('connectors.wooCommerce.source', 'woocommerce')
            ->where('connectors.wooCommerce.connected', false)
            ->where('connectors.pluginDownloadUrl', route('profile.connectors.woocommerce.plugin.download'))
        );
});

it('includes the connectors account navigation item in the inertia shell', function (): void {
    $user = ($this->makeUser)([], ['system-users-manage']);

    $this->actingAs($user)
        ->get(route('profile.connectors.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->where('shell.navigation.accountItems.1.label', 'Connectors')
            ->where('shell.navigation.accountItems.1.active', true)
        );
});

it('redirects guests away from the plugin download route', function (): void {
    $this->get(route('profile.connectors.woocommerce.plugin.download'))
        ->assertRedirect(route('login'));
});

it('blocks plugin downloads without connector management permission', function (): void {
    $user = ($this->makeUser)();

    $this->actingAs($user)
        ->get(route('profile.connectors.woocommerce.plugin.download'))
        ->assertForbidden();
});

it('downloads the plugin archive for connector managers', function (): void {
    $user = ($this->makeUser)([], ['system-users-manage']);
    $archivePath = ($this->fakeArchivePath)();

    $this->app->instance(WordPressPluginArchive::class, new class ($archivePath) extends WordPressPluginArchive {
        public function __construct(private readonly string $archivePath)
        {
        }

        public function build(): string
        {
            return $this->archivePath;
        }
    });

    $this->actingAs($user)
        ->get(route('profile.connectors.woocommerce.plugin.download'))
        ->assertOk()
        ->assertDownload('foomake-connector.zip');
});

it('returns service unavailable when the plugin archive cannot be built', function (): void {
    $user = ($this->makeUser)([], ['system-users-manage']);

    $this->app->instance(WordPressPluginArchive::class, new class extends WordPressPluginArchive {
        public function build(): string
        {
            throw new RuntimeException('Archive unavailable.');
        }
    });

    $this->actingAs($user)
        ->get(route('profile.connectors.woocommerce.plugin.download'))
        ->assertStatus(503);
});

it('builds a zip containing the wordpress plugin root file', function (): void {
    if (! class_exists(ZipArchive::class)) {
        $this->markTestSkipped('The PHP zip extension is not installed.');
    }

    $archivePath = app(WordPressPluginArchive::class)->build();
    $zip = new ZipArchive();

    expect($zip->open($archivePath))->toBeTrue()
        ->and($zip->locateName('foomake-connector/foomake-connector.php'))->not->toBeFalse()
        ->and($zip->locateName('foomake-connector/readme.txt'))->not->toBeFalse();

    $zip->close();
    unlink($archivePath);
});

it('starts a wordpress plugin pairing request and returns an approval url', function (): void {
    $response = $this->postJson(
        route('api.wordpress-plugin.pairing.start'),
        ($this->startPairingPayload)()
    );

    $response
        ->assertCreated()
        ->assertJsonStructure(['pairing_url', 'expires_at']);

    $pairingUrl = (string) $response->json('pairing_url');
    parse_str((string) parse_url($pairingUrl, PHP_URL_QUERY), $query);

    expect($query)->toHaveKey('code')
        ->and(WordPressPluginPairingCode::query()->count())->toBe(1)
        ->and(WordPressPluginPairingCode::query()->first()?->code_hash)->not->toBe($query['code']);
});

it('rejects pairing start requests whose callback host differs from the site host', function (): void {
    $this->postJson(route('api.wordpress-plugin.pairing.start'), ($this->startPairingPayload)([
        'callback_url' => 'http://attacker.test/callback',
    ]))
        ->assertStatus(422)
        ->assertJsonValidationErrors(['callback_url']);
});

it('rejects pairing completion for an invalid code', function (): void {
    $this->postJson(route('api.wordpress-plugin.pairing.complete'), [
        'code' => 'missing-code',
        'plugin_uuid' => $this->pluginUuid,
    ])
        ->assertStatus(422)
        ->assertJsonPath('message', 'The pairing code is invalid.');
});

it('rejects pairing completion before tenant approval', function (): void {
    ($this->createPairing)('waiting-code');

    $this->postJson(route('api.wordpress-plugin.pairing.complete'), [
        'code' => 'waiting-code',
        'plugin_uuid' => $this->pluginUuid,
    ])
        ->assertStatus(409)
        ->assertJsonPath('message', 'The pairing code has not been approved.');
});

it('redirects guests away from the wordpress pairing approval page', function (): void {
    ($this->createPairing)('guest-code');

    $this->get(route('profile.connectors.wordpress.pair', ['code' => 'guest-code']))
        ->assertRedirect(route('login'));
});

it('blocks wordpress pairing approval page access without connector management permission', function (): void {
    $user = ($this->makeUser)();
    ($this->createPairing)('blocked-code');

    $this->actingAs($user)
        ->get(route('profile.connectors.wordpress.pair', ['code' => 'blocked-code']))
        ->assertForbidden();
});

it('renders a valid wordpress pairing approval page for connector managers', function (): void {
    $user = ($this->makeUser)([], ['system-users-manage']);
    ($this->createPairing)('valid-code');

    $this->actingAs($user)
        ->get(route('profile.connectors.wordpress.pair', ['code' => 'valid-code']))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->component('Profile/Connectors/WordPressPair')
            ->where('pairing.canApprove', true)
            ->where('pairing.siteUrl', $this->siteUrl)
            ->where('pairing.code', 'valid-code')
        );
});

it('renders an invalid wordpress pairing approval page with a safe error', function (): void {
    $user = ($this->makeUser)([], ['system-users-manage']);

    $this->actingAs($user)
        ->get(route('profile.connectors.wordpress.pair', ['code' => 'bad-code']))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->component('Profile/Connectors/WordPressPair')
            ->where('pairing.canApprove', false)
            ->where('pairing.error', 'The WordPress plugin pairing link is invalid.')
        );
});

it('renders an expired wordpress pairing approval page with a safe error', function (): void {
    $user = ($this->makeUser)([], ['system-users-manage']);
    ($this->createPairing)('expired-code', [
        'expires_at' => now()->subMinute(),
    ]);

    $this->actingAs($user)
        ->get(route('profile.connectors.wordpress.pair', ['code' => 'expired-code']))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->component('Profile/Connectors/WordPressPair')
            ->where('pairing.canApprove', false)
            ->where('pairing.error', 'The WordPress plugin pairing link has expired.')
        );
});

it('approves a wordpress pairing code and redirects back to the plugin callback', function (): void {
    $user = ($this->makeUser)([], ['system-users-manage']);
    ($this->createPairing)('approve-code');

    $this->actingAs($user)
        ->post(route('profile.connectors.wordpress.pair.approve'), [
            'code' => 'approve-code',
        ])
        ->assertRedirect($this->callbackUrl . '&code=approve-code');

    $pairing = WordPressPluginPairingCode::query()->firstOrFail();

    expect($pairing->tenant_id)->toBe($user->tenant_id)
        ->and($pairing->approved_at)->not->toBeNull()
        ->and($pairing->consumed_at)->toBeNull();
});

it('exchanges an approved pairing code for a bearer token and connection payload', function (): void {
    $user = ($this->makeUser)([], ['system-users-manage']);
    ($this->createPairing)('exchange-code', [
        'tenant_id' => $user->tenant_id,
        'approved_at' => now(),
    ]);

    $response = $this->postJson(route('api.wordpress-plugin.pairing.complete'), [
        'code' => 'exchange-code',
        'plugin_uuid' => $this->pluginUuid,
    ]);

    $response
        ->assertOk()
        ->assertJsonPath('token_type', 'Bearer')
        ->assertJsonPath('connection.connected', true)
        ->assertJsonStructure(['connection' => ['site_access_token']])
        ->assertJsonPath('connection.tenant_name', $user->tenant->tenant_name);

    $token = (string) $response->json('access_token');
    $siteAccessToken = (string) $response->json('connection.site_access_token');
    $connection = WordPressPluginConnection::withoutGlobalScopes()->firstOrFail();

    expect($token)->not->toBe('')
        ->and($siteAccessToken)->not->toBe('')
        ->and($connection->access_token_hash)->toBe(hash('sha256', $token))
        ->and($connection->site_access_token)->toBe($siteAccessToken)
        ->and($connection->tenant_id)->toBe($user->tenant_id);
});

it('marks a pairing code as consumed after token exchange', function (): void {
    $user = ($this->makeUser)([], ['system-users-manage']);
    ($this->createPairing)('consume-code', [
        'tenant_id' => $user->tenant_id,
        'approved_at' => now(),
    ]);

    $this->postJson(route('api.wordpress-plugin.pairing.complete'), [
        'code' => 'consume-code',
        'plugin_uuid' => $this->pluginUuid,
    ])->assertOk();

    expect(WordPressPluginPairingCode::query()->firstOrFail()->consumed_at)->not->toBeNull();
});

it('prevents an approved pairing code from being exchanged twice', function (): void {
    $user = ($this->makeUser)([], ['system-users-manage']);
    ($this->createPairing)('single-use-code', [
        'tenant_id' => $user->tenant_id,
        'approved_at' => now(),
    ]);

    $payload = [
        'code' => 'single-use-code',
        'plugin_uuid' => $this->pluginUuid,
    ];

    $this->postJson(route('api.wordpress-plugin.pairing.complete'), $payload)->assertOk();
    $this->postJson(route('api.wordpress-plugin.pairing.complete'), $payload)
        ->assertStatus(409)
        ->assertJsonPath('message', 'The pairing code has already been used.');
});

it('revokes older active plugin connections when a new plugin uuid pairs for the tenant', function (): void {
    $user = ($this->makeUser)([], ['system-users-manage']);

    WordPressPluginConnection::query()->create([
        'tenant_id' => $user->tenant_id,
        'plugin_uuid' => '123e4567-e89b-12d3-a456-426614174111',
        'site_url' => 'https://old.example.test/',
        'site_name' => 'Old Woo',
        'status' => WordPressPluginConnection::STATUS_CONNECTED,
        'access_token_hash' => hash('sha256', 'old-token'),
        'site_access_token' => 'old-site-token',
        'connected_at' => now()->subDay(),
    ]);

    ($this->createPairing)('new-current-code', [
        'tenant_id' => $user->tenant_id,
        'approved_at' => now(),
    ]);

    $this->postJson(route('api.wordpress-plugin.pairing.complete'), [
        'code' => 'new-current-code',
        'plugin_uuid' => $this->pluginUuid,
    ])->assertOk();

    $oldConnection = WordPressPluginConnection::withoutGlobalScopes()
        ->where('plugin_uuid', '123e4567-e89b-12d3-a456-426614174111')
        ->firstOrFail();

    expect($oldConnection->status)->toBe(WordPressPluginConnection::STATUS_REVOKED)
        ->and($oldConnection->access_token_hash)->toBeNull()
        ->and($oldConnection->site_access_token)->toBeNull()
        ->and($oldConnection->revoked_at)->not->toBeNull()
        ->and(WordPressPluginConnection::withoutGlobalScopes()
            ->where('tenant_id', $user->tenant_id)
            ->where('status', WordPressPluginConnection::STATUS_CONNECTED)
            ->whereNull('revoked_at')
            ->count())->toBe(1);
});

it('rejects wordpress plugin status requests without a bearer token', function (): void {
    $this->getJson(route('api.wordpress-plugin.status'))
        ->assertUnauthorized()
        ->assertJsonPath('message', 'Invalid or revoked plugin token.');
});

it('returns wordpress plugin status for a valid bearer token and updates last seen', function (): void {
    $user = ($this->makeUser)([], ['system-users-manage']);
    $token = 'plain-token';

    WordPressPluginConnection::query()->create([
        'tenant_id' => $user->tenant_id,
        'plugin_uuid' => $this->pluginUuid,
        'site_url' => $this->siteUrl,
        'site_name' => 'Local Woo',
        'status' => WordPressPluginConnection::STATUS_CONNECTED,
        'access_token_hash' => hash('sha256', $token),
        'connected_at' => now(),
    ]);

    $this->withHeader('Authorization', 'Bearer ' . $token)
        ->getJson(route('api.wordpress-plugin.status'))
        ->assertOk()
        ->assertJsonPath('connection.connected', true)
        ->assertJsonStructure(['connection' => ['site_access_token']])
        ->assertJsonPath('connection.tenant_name', $user->tenant->tenant_name);

    $connection = WordPressPluginConnection::withoutGlobalScopes()->firstOrFail();

    expect($connection->last_seen_at)->not->toBeNull()
        ->and($connection->site_access_token)->not->toBe('');
});

it('rejects wordpress plugin status for a revoked token', function (): void {
    $user = ($this->makeUser)([], ['system-users-manage']);
    $token = 'revoked-token';

    WordPressPluginConnection::query()->create([
        'tenant_id' => $user->tenant_id,
        'plugin_uuid' => $this->pluginUuid,
        'site_url' => $this->siteUrl,
        'site_name' => 'Local Woo',
        'status' => WordPressPluginConnection::STATUS_REVOKED,
        'access_token_hash' => hash('sha256', $token),
        'connected_at' => now(),
        'revoked_at' => now(),
    ]);

    $this->withHeader('Authorization', 'Bearer ' . $token)
        ->getJson(route('api.wordpress-plugin.status'))
        ->assertUnauthorized();
});

it('blocks wordpress plugin revocation without connector management permission', function (): void {
    $user = ($this->makeUser)();

    $this->actingAs($user)
        ->deleteJson(route('profile.connectors.wordpress-plugin.destroy'))
        ->assertForbidden();
});

it('revokes the current tenant wordpress plugin connection', function (): void {
    $user = ($this->makeUser)([], ['system-users-manage']);

    WordPressPluginConnection::query()->create([
        'tenant_id' => $user->tenant_id,
        'plugin_uuid' => $this->pluginUuid,
        'site_url' => $this->siteUrl,
        'site_name' => 'Local Woo',
        'status' => WordPressPluginConnection::STATUS_CONNECTED,
        'access_token_hash' => hash('sha256', 'token'),
        'connected_at' => now(),
    ]);

    $this->actingAs($user)
        ->deleteJson(route('profile.connectors.wordpress-plugin.destroy'))
        ->assertOk()
        ->assertJsonPath('data.connected', false)
        ->assertJsonPath('data.status', WordPressPluginConnection::STATUS_REVOKED);

    $connection = WordPressPluginConnection::withoutGlobalScopes()->firstOrFail();

    expect($connection->access_token_hash)->toBeNull()
        ->and($connection->revoked_at)->not->toBeNull();
});

it('includes wordpress plugin connection state on the connectors page', function (): void {
    $user = ($this->makeUser)([], ['system-users-manage']);

    WordPressPluginConnection::query()->create([
        'tenant_id' => $user->tenant_id,
        'plugin_uuid' => $this->pluginUuid,
        'site_url' => $this->siteUrl,
        'site_name' => 'Local Woo',
        'status' => WordPressPluginConnection::STATUS_CONNECTED,
        'access_token_hash' => hash('sha256', 'token'),
        'connected_at' => now(),
    ]);

    $this->actingAs($user)
        ->get(route('profile.connectors.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->where('connectors.wordPressPlugin.connected', true)
            ->where('connectors.wordPressPlugin.site_url', $this->siteUrl)
            ->where('connectors.pluginRevokeUrl', route('profile.connectors.wordpress-plugin.destroy'))
        );
});

it('prefers an active wordpress plugin connection over a newer stale disconnected row on the connectors page', function (): void {
    $user = ($this->makeUser)([], ['system-users-manage']);

    WordPressPluginConnection::query()->create([
        'tenant_id' => $user->tenant_id,
        'plugin_uuid' => $this->pluginUuid,
        'site_url' => $this->siteUrl,
        'site_name' => 'Active Woo',
        'status' => WordPressPluginConnection::STATUS_CONNECTED,
        'access_token_hash' => hash('sha256', 'active-token'),
        'connected_at' => now()->subDay(),
        'last_seen_at' => now(),
    ]);

    WordPressPluginConnection::query()->create([
        'tenant_id' => $user->tenant_id,
        'plugin_uuid' => '123e4567-e89b-12d3-a456-426614174999',
        'site_url' => 'https://stale.example.test/',
        'site_name' => 'Stale Woo',
        'status' => WordPressPluginConnection::STATUS_REVOKED,
        'access_token_hash' => null,
        'connected_at' => now(),
        'revoked_at' => now(),
    ]);

    $this->actingAs($user)
        ->get(route('profile.connectors.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->where('connectors.wordPressPlugin.connected', true)
            ->where('connectors.wordPressPlugin.site_name', 'Active Woo')
            ->where('connectors.wordPressPlugin.site_url', $this->siteUrl)
        );
});

it('loads customer import preview rows through a connected wordpress plugin', function (): void {
    $user = ($this->makeUser)([], ['system-users-manage']);
    $siteAccessToken = 'site-access-token';

    WordPressPluginConnection::query()->create([
        'tenant_id' => $user->tenant_id,
        'plugin_uuid' => $this->pluginUuid,
        'site_url' => $this->siteUrl,
        'site_name' => 'Local Woo',
        'status' => WordPressPluginConnection::STATUS_CONNECTED,
        'access_token_hash' => hash('sha256', 'token'),
        'site_access_token' => $siteAccessToken,
        'connected_at' => now(),
        'last_seen_at' => now(),
    ]);

    Http::fake([
        'http://wordpress.test/wp-json/foomake/v1/customers' => Http::response([
            'data' => [
                [
                    'external_id' => '42',
                    'name' => 'Acme Foods',
                    'email' => 'buyer@example.com',
                    'phone' => '555-1212',
                    'address_line_1' => '1 Main St',
                    'address_line_2' => '',
                    'city' => 'Toronto',
                    'region' => 'ON',
                    'postal_code' => 'M5V 1A1',
                    'country_code' => 'CA',
                ],
            ],
        ]),
    ]);

    $this->actingAs($user)
        ->postJson(route('sales.customers.import.preview'), [
            'source' => 'woocommerce',
        ])
        ->assertOk()
        ->assertJsonPath('data.is_connected', true)
        ->assertJsonPath('data.rows.0.external_id', '42')
        ->assertJsonPath('data.rows.0.name', 'Acme Foods');

    Http::assertSent(fn ($request): bool => $request->hasHeader('X-FooMake-Site-Token', $siteAccessToken)
        && $request->url() === 'http://wordpress.test/wp-json/foomake/v1/customers');
});
