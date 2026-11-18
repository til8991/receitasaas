<?php

use App\Models\Office;
use App\Models\User;

it('registers an office with its admin user and returns a token', function () {
    $response = $this->postJson('/api/auth/register', [
        'office_name' => 'Contabilidade Exemplo',
        'office_cnpj' => '11.222.333/0001-81',
        'name' => 'Ana Contadora',
        'email' => 'ana@example.com',
        'password' => 'senha1234',
        'password_confirmation' => 'senha1234',
    ]);

    $response->assertCreated()
        ->assertJsonPath('user.role', 'admin')
        ->assertJsonPath('user.office.cnpj', '11222333000181')
        ->assertJsonPath('user.office.plan_status', 'trialing');
    expect($response->json('token'))->toBeString();

    $this->withToken($response->json('token'))->getJson('/api/auth/me')
        ->assertOk()->assertJsonPath('user.email', 'ana@example.com');
});

it('rejects an invalid office CNPJ and duplicated office', function () {
    Office::factory()->create(['cnpj' => '11222333000181']);

    $payload = [
        'office_name' => 'X', 'name' => 'Y', 'email' => 'y@example.com',
        'password' => 'senha1234', 'password_confirmation' => 'senha1234',
    ];

    $this->postJson('/api/auth/register', $payload + ['office_cnpj' => '11222333000182'])
        ->assertUnprocessable()->assertJsonValidationErrors('office_cnpj');
    $this->postJson('/api/auth/register', $payload + ['office_cnpj' => '11222333000181'])
        ->assertUnprocessable()->assertJsonValidationErrors('office_cnpj');
});

it('logs in with valid credentials only', function () {
    $user = User::factory()->create(['email' => 'joao@example.com']);

    $this->postJson('/api/auth/login', ['email' => 'joao@example.com', 'password' => 'errada'])
        ->assertUnprocessable();
    $this->postJson('/api/auth/login', ['email' => 'joao@example.com', 'password' => 'password'])
        ->assertOk()->assertJsonPath('user.id', $user->id);
});

it('requires authentication on protected routes', function () {
    $this->getJson('/api/clients')->assertUnauthorized();
});

it('lets only admins manage users and keeps at least one admin', function () {
    $admin = actingAsOfficeUser();

    $created = $this->postJson('/api/users', [
        'name' => 'Colab', 'email' => 'colab@example.com', 'role' => 'collaborator', 'password' => 'senha1234',
    ])->assertCreated()->json('data');

    $this->putJson("/api/users/{$admin->id}", ['role' => 'collaborator'])
        ->assertUnprocessable()->assertJsonValidationErrors('role');

    Laravel\Sanctum\Sanctum::actingAs(User::find($created['id']));
    $this->postJson('/api/users', [
        'name' => 'Outro', 'email' => 'outro@example.com', 'role' => 'admin', 'password' => 'senha1234',
    ])->assertForbidden();
});

it('does not expose users from another office', function () {
    $other = User::factory()->create();
    actingAsOfficeUser();

    $this->putJson("/api/users/{$other->id}", ['name' => 'Hack'])->assertNotFound();
    expect($this->getJson('/api/users')->json('data'))->toHaveCount(1);
});
