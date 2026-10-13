<?php

use App\Models\User;


function pagesTestAdmin(): User
{
    return User::factory()->admin()->create();
}
