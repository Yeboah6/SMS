<?php

use App\Models\SchoolSetting;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
});

function createSchoolSettingsAdmin(): User
{
    $admin = User::factory()->create();
    $admin->assignRole('Super Admin');

    return $admin;
}

/**
 * @return array<string, mixed>
 */
function schoolSettingsPayload(): array
{
    return [
        'school_name' => 'Northstar School',
        'motto' => 'Learn and grow',
        'address' => '1 School Road',
        'phone' => '+1 555 0100',
        'email' => 'office@northstar.example',
        'website' => 'https://northstar.example',
        'attendance_threshold' => 82.5,
        'grade_settings' => ['bands' => [
            ['label' => 'A', 'minimum_percentage' => 80],
            ['label' => 'B', 'minimum_percentage' => 70],
            ['label' => 'C', 'minimum_percentage' => 60],
            ['label' => 'D', 'minimum_percentage' => 50],
            ['label' => 'F', 'minimum_percentage' => 0],
        ]],
        'fee_settings' => ['currency_code' => 'EUR', 'default_due_days' => 14],
        'notification_settings' => ['database_notices' => true, 'email_notices' => false],
    ];
}

test('super admins can view settings defaults and persist grading, fee and notification preferences', function () {
    $admin = createSchoolSettingsAdmin();

    $this->actingAs($admin)->get(route('settings.edit'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Settings/Edit')
            ->where('settings.grade_settings.bands.0.label', 'A')
            ->where('settings.fee_settings.currency_code', 'USD')
            ->where('settings.fee_settings.default_due_days', 30)
            ->where('settings.notification_settings.database_notices', true)
            ->where('settings.notification_settings.email_notices', true));

    $this->actingAs($admin)->put(route('settings.update'), schoolSettingsPayload())
        ->assertRedirect()
        ->assertSessionHas('status', 'School settings saved.');

    $settings = SchoolSetting::query()->firstOrFail();
    expect($settings->school_name)->toBe('Northstar School')
        ->and($settings->grade_settings['bands'][0]['label'])->toBe('A')
        ->and($settings->fee_settings)->toBe(['currency_code' => 'EUR', 'default_due_days' => 14])
        ->and($settings->notification_settings)->toBe(['database_notices' => true, 'email_notices' => false]);

    $this->actingAs($admin)->get('/dashboard')
        ->assertInertia(fn ($page) => $page
            ->where('feeSettings.currency_code', 'EUR')
            ->where('feeSettings.default_due_days', 14));
});

test('school settings reject unordered grade bands and invalid logo uploads', function () {
    $admin = createSchoolSettingsAdmin();
    $payload = schoolSettingsPayload();
    $payload['grade_settings'] = ['bands' => [
        ['label' => 'F', 'minimum_percentage' => 0],
        ['label' => 'A', 'minimum_percentage' => 80],
    ]];

    $this->actingAs($admin)->put(route('settings.update'), $payload)
        ->assertSessionHasErrors('grade_settings.bands');
    expect(SchoolSetting::query()->count())->toBe(0);

    $payload['grade_settings'] = ['bands' => [
        ['label' => 'A', 'minimum_percentage' => 80],
        ['label' => 'F', 'minimum_percentage' => 1],
    ]];
    $this->actingAs($admin)->put(route('settings.update'), $payload)
        ->assertSessionHasErrors('grade_settings.bands');

    $this->actingAs($admin)->put(route('settings.update'), [
        ...schoolSettingsPayload(),
        'logo' => UploadedFile::fake()->create('not-an-image.txt', 10, 'text/plain'),
    ])->assertSessionHasErrors('logo');

    expect(SchoolSetting::query()->count())->toBe(0);
});

test('school logo uploads replace and remove the previously stored public image', function () {
    Storage::fake('public');
    $admin = createSchoolSettingsAdmin();

    $this->actingAs($admin)->put(route('settings.update'), [
        ...schoolSettingsPayload(),
        'logo' => UploadedFile::fake()->image('school-logo.png', 300, 200),
    ])->assertRedirect();

    $firstLogoPath = SchoolSetting::query()->firstOrFail()->logo_path;
    Storage::disk('public')->assertExists($firstLogoPath);

    $this->actingAs($admin)->put(route('settings.update'), [
        ...schoolSettingsPayload(),
        'logo' => UploadedFile::fake()->image('replacement-logo.png', 400, 250),
    ])->assertRedirect();

    $replacementLogoPath = SchoolSetting::query()->firstOrFail()->logo_path;
    expect($replacementLogoPath)->not->toBe($firstLogoPath);
    Storage::disk('public')->assertMissing($firstLogoPath);
    Storage::disk('public')->assertExists($replacementLogoPath);

    $this->actingAs($admin)->put(route('settings.update'), [
        ...schoolSettingsPayload(),
        'remove_logo' => true,
    ])->assertRedirect();

    expect(SchoolSetting::query()->firstOrFail()->logo_path)->toBeNull();
    Storage::disk('public')->assertMissing($replacementLogoPath);
});

test('users without settings permissions cannot view or update school settings', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get(route('settings.edit'))->assertForbidden();
    $this->actingAs($user)->put(route('settings.update'), schoolSettingsPayload())->assertForbidden();

    expect(SchoolSetting::query()->count())->toBe(0);
});
