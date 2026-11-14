<?php

use App\Models\Student;
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
        'login' => $user->email,
        'password' => 'StrongSchoolPassword!42',
        'remember' => true,
    ])->assertRedirect('/dashboard');

    $this->assertAuthenticatedAs($user);

    $this->get('/dashboard')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Dashboard')
            ->where('dashboard.roleLabel', 'School member')
            ->where('dashboard.metrics', [])
            ->where('auth.user.email', $user->email));
});

test('a student can sign in with their admission number', function () {
    $user = User::factory()->create([
        'email' => null,
        'password' => 'StrongSchoolPassword!42',
    ]);

    Student::query()->create([
        'user_id' => $user->id,
        'admission_number' => 'ADM0001',
        'first_name' => 'Ava',
        'last_name' => 'Learner',
        'date_of_birth' => '2010-01-01',
        'gender' => 'female',
        'admission_date' => '2025-09-01',
    ]);

    $this->post('/login', [
        'login' => 'adm0001',
        'password' => 'StrongSchoolPassword!42',
    ])->assertRedirect('/dashboard');

    $this->assertAuthenticatedAs($user);
});

test('deactivated accounts cannot sign in', function () {
    $user = User::factory()->create([
        'password' => 'StrongSchoolPassword!42',
    ]);
    $user->delete();

    $this->from('/login')->post('/login', [
        'login' => $user->email,
        'password' => 'StrongSchoolPassword!42',
    ])->assertRedirect('/login')->assertSessionHasErrors('login');

    $this->assertGuest();
});

test('invalid credentials do not authenticate a user', function () {
    $user = User::factory()->create();

    $this->from('/login')->post('/login', [
        'login' => $user->email,
        'password' => 'incorrect-password',
    ])->assertRedirect('/login')->assertSessionHasErrors('login');

    $this->assertGuest();
});

test('login attempts are rate limited', function () {
    foreach (range(1, 5) as $attempt) {
        $this->post('/login', [
            'login' => 'unknown@example.test',
            'password' => 'incorrect-password',
        ])->assertRedirect();
    }

    $this->post('/login', [
        'login' => 'unknown@example.test',
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
