<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('boots and serves the minimal public shell', function () {
    $this->get('/')
        ->assertSuccessful()
        ->assertSee('Lars Möller');
});

it('does not expose the admin panel to unauthenticated visitors', function () {
    $this->get('/admin')
        ->assertRedirect('/admin/login');
});

it('serves the authenticated admin shell to an admin user', function () {
    $user = User::factory()->admin()->create();

    $this->actingAs($user)
        ->get('/admin')
        ->assertSuccessful()
        ->assertSee('Dashboard');
});

it('uses the configured PostgreSQL connection', function () {
    expect(config('database.default'))->toBe('pgsql')
        ->and(config('database.connections.pgsql.driver'))->toBe('pgsql');

    expect($this->app['db']->connection('pgsql')->getPdo())->toBeInstanceOf(PDO::class);
});
