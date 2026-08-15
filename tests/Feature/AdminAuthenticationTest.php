<?php

use App\Models\User;
use Filament\Auth\Pages\Login;
use Filament\Facades\Filament;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    Filament::setCurrentPanel('admin');
    Filament::bootCurrentPanel();

    RateLimiter::clear('livewire-rate-limiter:'.sha1(Login::class.'|authenticate|127.0.0.1'));
});

function createAdminUser(string $password = 'password'): User
{
    $user = User::factory()->create(['password' => $password]);

    $user->forceFill(['is_admin' => true])->save();

    return $user->refresh();
}

function loginComponent()
{
    return Livewire::test(Login::class);
}

it('redirects guests to the Filament login page', function () {
    $this->get('/admin')->assertRedirect('/admin/login');
});

it('serves the Filament login page to guests', function () {
    $this->get('/admin/login')->assertSuccessful();
});

it('denies panel access to non-admin users', function () {
    $user = User::factory()->create();

    $this->actingAs($user, 'web')
        ->get('/admin')
        ->assertForbidden();
});

it('serves the admin panel to admin users', function () {
    $user = createAdminUser();

    $this->actingAs($user, 'web')
        ->get('/admin')
        ->assertSuccessful()
        ->assertSee('Dashboard');
});

it('authenticates an admin through the installed Filament login component', function () {
    $admin = createAdminUser('known-password');
    $sessionIdBefore = session()->getId();

    loginComponent()
        ->set('data.email', $admin->email)
        ->set('data.password', 'known-password')
        ->call('authenticate')
        ->assertHasNoErrors();

    expect(Auth::guard('web')->user()->is($admin))->toBeTrue()
        ->and(session()->getId())->not->toBe($sessionIdBefore);
});

it('rejects an incorrect password through the Filament login component', function () {
    $admin = createAdminUser('known-password');

    loginComponent()
        ->set('data.email', $admin->email)
        ->set('data.password', 'wrong-password')
        ->call('authenticate')
        ->assertHasErrors(['data.email']);

    expect(Auth::guard('web')->check())->toBeFalse();
});

it('rejects valid credentials for a non-admin user', function () {
    $user = User::factory()->create(['password' => 'known-password']);

    loginComponent()
        ->set('data.email', $user->email)
        ->set('data.password', 'known-password')
        ->call('authenticate')
        ->assertHasErrors(['data.email']);

    expect(Auth::guard('web')->check())->toBeFalse()
        ->and($user->canAccessPanel(Filament::getCurrentOrDefaultPanel()))->toBeFalse();
});

it('uses Filament login rate limiting after five failed attempts', function () {
    $admin = createAdminUser('known-password');
    $component = loginComponent();

    foreach (range(1, 5) as $attempt) {
        $component
            ->set('data.email', $admin->email)
            ->set('data.password', 'wrong-password')
            ->call('authenticate')
            ->assertHasErrors(['data.email']);
    }

    $component
        ->set('data.email', $admin->email)
        ->set('data.password', 'wrong-password')
        ->call('authenticate')
        ->assertNotified('Too many login attempts');
});

it('logs out through the Filament logout endpoint and invalidates the session', function () {
    $admin = createAdminUser();

    $this->actingAs($admin, 'web');
    session(['admin-auth-test' => 'present']);
    $sessionIdBefore = session()->getId();

    $this->post(Filament::getLogoutUrl())
        ->assertRedirect();

    expect(Auth::guard('web')->check())->toBeFalse()
        ->and(session()->has('admin-auth-test'))->toBeFalse()
        ->and(session()->getId())->not->toBe($sessionIdBefore);
});

it('hashes passwords assigned through the User model', function () {
    $user = new User([
        'name' => 'Hash Test',
        'email' => 'hash-test@example.test',
        'password' => 'plain-password',
    ]);

    $user->save();

    expect($user->getRawOriginal('password'))->not->toBe('plain-password')
        ->and(Hash::check('plain-password', $user->getRawOriginal('password')))->toBeTrue();
});

it('uses the required session security configuration', function () {
    expect(config('session.encrypt'))->toBeTrue()
        ->and(config('session.http_only'))->toBeTrue()
        ->and(config('session.same_site'))->toBe('lax');
});

it('registers CSRF protection on the admin panel', function () {
    expect(Filament::getCurrentOrDefaultPanel()->getMiddleware())
        ->toContain(PreventRequestForgery::class);
});

it('does not expose registration or password reset routes', function () {
    $this->get('/admin/register')->assertNotFound();
    $this->get('/admin/password-reset/request')->assertNotFound();
});
