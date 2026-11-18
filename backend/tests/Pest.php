<?php

use App\Models\Office;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

pest()->extend(Tests\TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature', 'Unit');

pest()->extend(Tests\TestCase::class)->in('Integration');

/** Authenticated admin of a fresh office. */
function actingAsOfficeUser(array $officeAttributes = [], string $role = User::ROLE_ADMIN): User
{
    $office = Office::factory()->create($officeAttributes);
    $user = User::factory()->create(['office_id' => $office->id, 'role' => $role]);
    Sanctum::actingAs($user);

    return $user;
}

function fixture(string $path): array
{
    return json_decode(file_get_contents(__DIR__.'/Fixtures/'.$path), true, flags: JSON_THROW_ON_ERROR);
}
