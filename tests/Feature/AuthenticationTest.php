<?php

use App\Models\User;
use Illuminate\Support\Facades\Log;

it('redirects guests to the login page', function () {
    $this->get('/')->assertRedirect('/login');
});

it('shows the login page', function () {
    $this->get('/login')
        ->assertOk()
        ->assertSee('ITMS One')
        ->assertSee('Masuk');
});

it('has no public registration route', function () {
    $this->get('/register')->assertNotFound();
    $this->post('/register', [
        'name' => 'New User',
        'username' => 'new-user',
        'password' => 'secret-password',
    ])->assertNotFound();
});

it('authenticates an active user with username and password', function () {
    $user = User::factory()->create([
        'username' => 'admin',
        'password' => 'secret-password',
    ]);

    $this->post('/login', [
        'username' => 'admin',
        'password' => 'secret-password',
    ])->assertRedirect(route('dashboard'));

    $this->assertAuthenticatedAs($user);
    expect($user->fresh()->last_login_at)->not->toBeNull();
});

it('rejects invalid credentials', function () {
    User::factory()->create([
        'username' => 'admin',
        'password' => 'secret-password',
    ]);

    $this->from('/login')
        ->post('/login', [
            'username' => 'admin',
            'password' => 'wrong-password',
        ])
        ->assertRedirect('/login')
        ->assertSessionHasErrors('username');

    $this->assertGuest();
});

it('rejects inactive accounts', function () {
    User::factory()->inactive()->create([
        'username' => 'admin',
        'password' => 'secret-password',
    ]);

    $this->post('/login', [
        'username' => 'admin',
        'password' => 'secret-password',
    ])->assertSessionHasErrors('username');

    $this->assertGuest();
});

it('shows the dashboard to an authenticated user', function () {
    $user = User::factory()->create(['name' => 'ITMS Administrator']);

    $this->actingAs($user)
        ->get('/')
        ->assertOk()
        ->assertSee('Dashboard')
        ->assertSee('ITMS Administrator');
});

it('logs the user out', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post('/logout')
        ->assertRedirect(route('login'));

    $this->assertGuest();
});

it('throttles repeated failed logins', function () {
    User::factory()->create([
        'username' => 'admin',
        'password' => 'secret-password',
    ]);

    $payload = [
        'username' => 'admin',
        'password' => 'wrong-password',
    ];

    foreach (range(1, 6) as $attempt) {
        $this->from('/login')
            ->post('/login', $payload)
            ->assertSessionHasErrors('username');
    }

    $this->from('/login')
        ->post('/login', $payload)
        ->assertStatus(429);
});

it('logs failed login attempts', function () {
    Log::spy();

    User::factory()->create([
        'username' => 'admin',
        'password' => 'secret-password',
    ]);

    $this->from('/login')
        ->post('/login', [
            'username' => 'admin',
            'password' => 'wrong-password',
        ]);

    Log::shouldHaveReceived('warning')
        ->once()
        ->withArgs(fn ($message) => str_contains($message, 'Gagal masuk'));
});
