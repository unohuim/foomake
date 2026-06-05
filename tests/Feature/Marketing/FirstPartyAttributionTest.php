<?php

declare(strict_types=1);

use App\Models\Tenant;
use App\Models\User;
use App\Models\VisitorAttribution;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->visitorCookieName = 'foomake_visitor_id';
});

it('1. anonymous request without cookie creates visitor id cookie', function (): void {
    $this->get('/?utm_source=linkedin')
        ->assertOk()
        ->assertCookie($this->visitorCookieName);
});

it('2. anonymous request creates visitor attribution row', function (): void {
    $this->get('/?utm_source=linkedin')
        ->assertOk();

    expect(VisitorAttribution::query()->count())->toBe(1);
});

it('3. attribution row stores a uuid visitor id', function (): void {
    $this->get('/?utm_source=linkedin')
        ->assertOk();

    $attribution = VisitorAttribution::query()->sole();

    expect(Str::isUuid($attribution->visitor_id))->toBeTrue();
});

it('4. attribution row stores first seen and last seen timestamps', function (): void {
    $this->get('/?utm_source=linkedin')
        ->assertOk();

    $attribution = VisitorAttribution::query()->sole();

    expect($attribution->first_seen_at)->not->toBeNull()
        ->and($attribution->last_seen_at)->not->toBeNull();
});

it('5. attribution table does not include an ip address column', function (): void {
    $this->get('/?utm_source=linkedin')
        ->assertOk();

    expect(VisitorAttribution::query()->sole()->getAttributes())->not->toHaveKey('ip_address');
});

it('6. attribution table does not include a user agent column', function (): void {
    $this->get('/?utm_source=linkedin')
        ->assertOk();

    expect(VisitorAttribution::query()->sole()->getAttributes())->not->toHaveKey('user_agent');
});

it('7. first request stores first landing page and first referrer', function (): void {
    $this->withHeader('Referer', 'https://example.com/article')
        ->get('/learn/food-manufacturing-mrp?utm_source=linkedin')
        ->assertOk();

    $attribution = VisitorAttribution::query()->sole();

    expect($attribution->first_landing_page)->toBe('http://localhost/learn/food-manufacturing-mrp?utm_source=linkedin')
        ->and($attribution->first_referrer)->toBe('https://example.com/article');
});

it('8. first request stores first utm fields', function (): void {
    $this->get('/?utm_source=linkedin&utm_medium=social&utm_campaign=beta&utm_content=post&utm_term=mrp')
        ->assertOk();

    $attribution = VisitorAttribution::query()->sole();

    expect($attribution->first_utm_source)->toBe('linkedin')
        ->and($attribution->first_utm_medium)->toBe('social')
        ->and($attribution->first_utm_campaign)->toBe('beta')
        ->and($attribution->first_utm_content)->toBe('post')
        ->and($attribution->first_utm_term)->toBe('mrp');
});

it('9. first touch fields are not overwritten by later requests', function (): void {
    $visitorId = (string) Str::uuid();

    $this->withCookie($this->visitorCookieName, $visitorId)
        ->withHeader('Referer', 'https://first.example')
        ->get('/?utm_source=first&utm_medium=email')
        ->assertOk();

    $this->withCookie($this->visitorCookieName, $visitorId)
        ->withHeader('Referer', 'https://second.example')
        ->get('/learn/recipe-management-software?utm_source=second&utm_medium=social')
        ->assertOk();

    $attribution = VisitorAttribution::query()->sole();

    expect($attribution->first_landing_page)->toBe('http://localhost/?utm_medium=email&utm_source=first')
        ->and($attribution->first_referrer)->toBe('https://first.example')
        ->and($attribution->first_utm_source)->toBe('first')
        ->and($attribution->first_utm_medium)->toBe('email');
});

it('10. latest touch fields update on later requests', function (): void {
    $visitorId = (string) Str::uuid();

    $this->withCookie($this->visitorCookieName, $visitorId)
        ->withHeader('Referer', 'https://first.example')
        ->get('/?utm_source=first&utm_medium=email')
        ->assertOk();

    $this->withCookie($this->visitorCookieName, $visitorId)
        ->withHeader('Referer', 'https://second.example')
        ->get('/learn/recipe-management-software?utm_source=second&utm_medium=social&utm_campaign=recipes')
        ->assertOk();

    $attribution = VisitorAttribution::query()->sole();

    expect($attribution->latest_landing_page)->toBe('http://localhost/learn/recipe-management-software?utm_campaign=recipes&utm_medium=social&utm_source=second')
        ->and($attribution->latest_referrer)->toBe('https://second.example')
        ->and($attribution->latest_utm_source)->toBe('second')
        ->and($attribution->latest_utm_medium)->toBe('social')
        ->and($attribution->latest_utm_campaign)->toBe('recipes');
});

it('11. later request without utm keeps latest utm values', function (): void {
    $visitorId = (string) Str::uuid();

    $this->withCookie($this->visitorCookieName, $visitorId)
        ->get('/?utm_source=first&utm_medium=email')
        ->assertOk();

    $this->withCookie($this->visitorCookieName, $visitorId)
        ->get('/learn/recipe-management-software')
        ->assertOk();

    $attribution = VisitorAttribution::query()->sole();

    expect($attribution->latest_utm_source)->toBe('first')
        ->and($attribution->latest_utm_medium)->toBe('email')
        ->and($attribution->latest_landing_page)->toBe('http://localhost/learn/recipe-management-software');
});

it('12. returning anonymous visitor reuses existing attribution row', function (): void {
    $visitorId = (string) Str::uuid();

    $this->withCookie($this->visitorCookieName, $visitorId)
        ->get('/?utm_source=first')
        ->assertOk();

    $this->withCookie($this->visitorCookieName, $visitorId)
        ->get('/learn/mrp-for-small-manufacturers?utm_source=second')
        ->assertOk();

    expect(VisitorAttribution::query()->count())->toBe(1);
});

it('13. registration links matching visitor attribution to user', function (): void {
    $visitorId = (string) Str::uuid();

    $this->withCookie($this->visitorCookieName, $visitorId)
        ->get('/?utm_source=linkedin')
        ->assertOk();

    $this->withCookie($this->visitorCookieName, $visitorId)
        ->post('/register', [
            'name' => 'Beta User',
            'email' => 'beta@example.test',
            'password' => 'password',
            'password_confirmation' => 'password',
        ])
        ->assertRedirect('/dashboard');

    $user = User::query()->where('email', 'beta@example.test')->firstOrFail();
    $attribution = VisitorAttribution::query()->sole();

    expect($attribution->user_id)->toBe($user->id);
});

it('14. registration sets converted at on matching attribution', function (): void {
    $visitorId = (string) Str::uuid();

    $this->withCookie($this->visitorCookieName, $visitorId)
        ->get('/?utm_source=linkedin')
        ->assertOk();

    $this->withCookie($this->visitorCookieName, $visitorId)
        ->post('/register', [
            'name' => 'Beta User',
            'email' => 'beta@example.test',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

    expect(VisitorAttribution::query()->sole()->converted_at)->not->toBeNull();
});

it('15. registration does not create duplicate attribution row', function (): void {
    $visitorId = (string) Str::uuid();

    $this->withCookie($this->visitorCookieName, $visitorId)
        ->get('/?utm_source=linkedin')
        ->assertOk();

    $this->withCookie($this->visitorCookieName, $visitorId)
        ->post('/register', [
            'name' => 'Beta User',
            'email' => 'beta@example.test',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

    expect(VisitorAttribution::query()->count())->toBe(1);
});

it('16. registration without visitor cookie still follows normal auth flow', function (): void {
    $this->post('/register', [
        'name' => 'Direct User',
        'email' => 'direct@example.test',
        'password' => 'password',
        'password_confirmation' => 'password',
    ])->assertRedirect('/dashboard');

    expect(User::query()->where('email', 'direct@example.test')->exists())->toBeTrue();
});

it('17. authenticated app navigation does not update attribution row', function (): void {
    $visitorId = (string) Str::uuid();

    $this->withCookie($this->visitorCookieName, $visitorId)
        ->get('/?utm_source=linkedin')
        ->assertOk();

    $tenant = Tenant::query()->create([
        'tenant_name' => 'App Tenant',
    ]);

    $user = User::query()->create([
        'tenant_id' => $tenant->id,
        'name' => 'App User',
        'email' => 'app@example.test',
        'email_verified_at' => now(),
        'password' => 'password',
    ]);

    $this->actingAs($user)
        ->withCookie($this->visitorCookieName, $visitorId)
        ->get('/dashboard')
        ->assertOk();

    $attribution = VisitorAttribution::query()->sole();

    expect($attribution->latest_landing_page)->toBe('http://localhost/?utm_source=linkedin');
});

it('18. attribution storage does not contain route history or behavior fields', function (): void {
    $this->get('/?utm_source=linkedin')
        ->assertOk();

    $attributes = VisitorAttribution::query()->sole()->getAttributes();

    expect($attributes)->not->toHaveKey('route_history')
        ->and($attributes)->not->toHaveKey('duration')
        ->and($attributes)->not->toHaveKey('scroll_depth')
        ->and($attributes)->not->toHaveKey('clickstream')
        ->and($attributes)->not->toHaveKey('ga_client_id')
        ->and($attributes)->not->toHaveKey('gclid')
        ->and($attributes)->not->toHaveKey('fbclid');
});

it('19. privacy page discloses first-party attribution', function (): void {
    $this->get('/privacy')
        ->assertOk()
        ->assertSee('first-party visitor ID')
        ->assertSee('landing page, referrer, and UTM campaign parameters');
});

it('20. privacy page states prohibited analytics are not used', function (): void {
    $this->get('/privacy')
        ->assertOk()
        ->assertSee('does not currently use Google Analytics')
        ->assertSee('ad pixels')
        ->assertSee('heatmaps')
        ->assertSee('session replay');
});

it('21. public pages do not include google analytics or tag manager scripts', function (): void {
    $this->get('/')
        ->assertOk()
        ->assertDontSee('googletagmanager', false)
        ->assertDontSee('google-analytics', false)
        ->assertDontSee('gtag(', false);
});

it('22. marketing pages do not include pixels heatmaps or replay scripts', function (): void {
    $this->get('/learn/food-manufacturing-mrp')
        ->assertOk()
        ->assertDontSee('googletagmanager', false)
        ->assertDontSee('facebook.com/tr', false)
        ->assertDontSee('hotjar', false)
        ->assertDontSee('fullstory', false);
});

it('23. visitor id cookie is long lived first party and http only', function (): void {
    $response = $this->get('/?utm_source=linkedin')
        ->assertOk();

    $cookie = collect($response->headers->getCookies())
        ->first(fn ($cookie) => $cookie->getName() === $this->visitorCookieName);

    expect($cookie)->not->toBeNull()
        ->and($cookie->isHttpOnly())->toBeTrue()
        ->and($cookie->getSameSite())->toBe('lax')
        ->and($cookie->getExpiresTime())->toBeGreaterThan(now()->addDays(80)->timestamp);
});

it('24. visitor id cookie value is not replaced when existing cookie is valid', function (): void {
    $visitorId = (string) Str::uuid();

    $this->withCookie($this->visitorCookieName, $visitorId)
        ->get('/?utm_source=linkedin')
        ->assertOk();

    expect(VisitorAttribution::query()->sole()->visitor_id)->toBe($visitorId);
});

it('25. documentation records the first-party attribution pattern', function (): void {
    expect(File::exists(base_path('docs/architecture/marketing/FirstPartyAttribution.yaml')))->toBeTrue()
        ->and(File::get(base_path('docs/architecture/marketing/FirstPartyAttribution.yaml')))
        ->toContain('No IP address, user agent profiling, route history, clickstream, heatmaps, session replay, ad identifiers, or third-party analytics identifiers may be stored.');
});
