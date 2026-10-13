<?php

use App\Models\User;

it('restricts the Pulse dashboard to administrator accounts', function (): void {
    $nonAdmin = User::factory()->create();
    $admin = User::factory()->admin()->create();

    $this->actingAs($nonAdmin, 'web')
        ->get('/pulse')
        ->assertForbidden();

    $this->actingAs($admin, 'web')
        ->get('/pulse')
        ->assertSuccessful();
});
