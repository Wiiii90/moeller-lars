<?php

use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('boots and serves the minimal public shell', function () {
    $this->get('/')
        ->assertSuccessful()
        ->assertSee('Lars Möller');
});

it('uses the configured PostgreSQL connection', function () {
    expect(config('database.default'))->toBe('pgsql')
        ->and(config('database.connections.pgsql.driver'))->toBe('pgsql');

    expect($this->app['db']->connection('pgsql')->getPdo())->toBeInstanceOf(PDO::class);
});
