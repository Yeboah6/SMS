<?php

use App\Models\Staff;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
});

function staffOnboardingAdministrator(): User
{
    $administrator = User::factory()->create();
    $administrator->assignRole('Super Admin');

    return $administrator;
}

/**
 * @return array{name: string, email: string, role: string, status: string}
 */
function staffOnboardingPayload(): array
{
    return [
        'name' => 'Morgan Staff',
        'email' => 'morgan.staff@example.test',
        'role' => 'Teacher',
        'status' => 'active',
    ];
}

test('staff creation emails a valid password setup link without exposing the random password', function () {
    $administrator = staffOnboardingAdministrator();
    Notification::fake();

    $response = $this->actingAs($administrator)
        ->post(route('staff.store'), staffOnboardingPayload());

    $staff = Staff::query()->where('email', 'morgan.staff@example.test')->firstOrFail();
    $account = User::query()->where('email', 'morgan.staff@example.test')->firstOrFail();

    $response->assertRedirect(route('staff.show', $staff))
        ->assertSessionHas('status', "Staff profile {$staff->staff_number} created. Password setup link sent to {$account->email}.")
        ->assertSessionMissing('generated_credentials');

    expect($staff->user_id)->toBe($account->id)
        ->and($account->hasRole('Teacher'))->toBeTrue()
        ->and($account->password)->not->toBeEmpty();

    Notification::assertSentToOnce(
        $account,
        ResetPassword::class,
        fn (ResetPassword $notification, array $channels): bool => $channels === ['mail']
            && Password::broker()->tokenExists($account, $notification->token)
            && str_contains($notification->toMail($account)->actionUrl, '/reset-password/'),
    );
});

test('staff creation reports when a password setup email is throttled', function () {
    $administrator = staffOnboardingAdministrator();
    $previousAccount = User::factory()->create(['email' => 'morgan.staff@example.test']);
    Password::broker()->createToken($previousAccount);
    $previousAccount->forceDelete();
    Notification::fake();

    $response = $this->actingAs($administrator)
        ->post(route('staff.store'), staffOnboardingPayload());

    $staff = Staff::query()->where('email', 'morgan.staff@example.test')->firstOrFail();
    $account = User::query()->where('email', 'morgan.staff@example.test')->firstOrFail();

    $response->assertRedirect(route('staff.show', $staff))
        ->assertSessionHas('status', "Staff profile {$staff->staff_number} created.")
        ->assertSessionHasErrors([
            'password_setup' => "Staff profile {$staff->staff_number} was created, but the password setup link could not be sent. Ask {$account->email} to use Forgot password to request a new link.",
        ])
        ->assertSessionMissing('generated_credentials');

    expect($staff->user_id)->toBe($account->id);
    Notification::assertNothingSent();
});
