<?php

use App\Models\User;

it('blocks the source after 5 failed logins but still lets the real user in from another network', function () {
    User::factory()->create(['email' => 'joao@example.com']);

    foreach (range(1, 5) as $_) {
        $this->postJson('/api/auth/login', ['email' => 'joao@example.com', 'password' => 'errada'], ['REMOTE_ADDR' => '203.0.113.7'])
            ->assertUnprocessable();
    }
    $this->postJson('/api/auth/login', ['email' => 'joao@example.com', 'password' => 'password'], ['REMOTE_ADDR' => '203.0.113.7'])
        ->assertStatus(429);

    $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.20'])
        ->postJson('/api/auth/login', ['email' => 'joao@example.com', 'password' => 'password'])
        ->assertOk();
});

it('groups IPv6 clients by /64', function () {
    User::factory()->create(['email' => 'maria@example.com']);

    foreach (range(1, 5) as $i) {
        $this->withServerVariables(['REMOTE_ADDR' => "2001:db8:1:2::{$i}"])
            ->postJson('/api/auth/login', ['email' => 'maria@example.com', 'password' => 'errada']);
    }

    $this->withServerVariables(['REMOTE_ADDR' => '2001:db8:1:2::ffff'])
        ->postJson('/api/auth/login', ['email' => 'maria@example.com', 'password' => 'password'])
        ->assertStatus(429);
});
