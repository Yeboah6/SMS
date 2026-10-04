<?php

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

test('guests are sent to the Inertia sign-in page before opening the dashboard', function () {
    $this->get('/')->assertRedirect('/login');

    $this->get('/login')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('Auth/Login'));
});

test('a user can sign in and reach the dashboard', function () {
    $user = User::factory()->create([
        'password' => 'StrongSchoolPassword!42',
    ]);

    $this->post('/login', [
        'email' => $user->email,
        'password' => 'StrongSchoolPassword!42',
        'remember' => true,
    ])->assertRedirect('/dashboard');

    $this->assertAuthenticatedAs($user);

    $this->get('/dashboard')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Dashboard')
            ->where('accountCount', 1)
            ->where('auth.user.email', $user->email));
});

test('invalid credentials do not authenticate a user', function () {
    $user = User::factory()->create();

    $this->from('/login')->post('/login', [
        'email' => $user->email,
        'password' => 'incorrect-password',
    ])->assertRedirect('/login')->assertSessionHasErrors('email');

    $this->assertGuest();
});

test('login attempts are rate limited', function () {
    foreach (range(1, 5) as $attempt) {
        $this->post('/login', [
            'email' => 'unknown@example.test',
            'password' => 'incorrect-password',
        ])->assertRedirect();
    }

    $this->post('/login', [
        'email' => 'unknown@example.test',
        'password' => 'incorrect-password',
    ])->assertTooManyRequests();
});

test('users can request a password reset email', function () {
    Notification::fake();
    $user = User::factory()->create();

    $this->post('/forgot-password', ['email' => $user->email])
        ->assertRedirect()
        ->assertSessionHas('status');

    Notification::assertSentTo($user, ResetPassword::class);
});

test('registration is not publicly available', function () {
    $this->get('/register')->assertNotFound();
    $this->post('/register')->assertNotFound();
});

test('an authenticated user can sign out', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->post('/logout')->assertRedirect('/');

    $this->assertGuest();
});
