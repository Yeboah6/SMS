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
function schoolProfilePayload(): array
{
    return [
        'school_name' => 'Northstar School',
        'motto' => 'Learn and grow',
        'address' => '1 School Road',
        'phone' => '+1 555 0100',
        'email' => 'office@northstar.example',
        'website' => 'https://northstar.example',
    ];
}

/**
 * @return array<string, mixed>
 */
function gradingSettingsPayload(): array
{
    return [
        'attendance_threshold' => 82.5,
        'grade_settings' => ['bands' => [
            ['label' => 'A', 'minimum_percentage' => 80],
            ['label' => 'B', 'minimum_percentage' => 70],
            ['label' => 'C', 'minimum_percentage' => 60],
            ['label' => 'D', 'minimum_percentage' => 50],
            ['label' => 'F', 'minimum_percentage' => 0],
        ]],
    ];
}

/**
 * @return array<string, mixed>
 */
function notificationSettingsPayload(array $overrides = []): array
{
    return array_replace([
        'notification_settings' => [
            'database_notices' => true,
            'email_notices' => false,
            'sms_notices' => false,
            'notice_email_subject' => 'School update: {title}',
            'notice_email_body' => '{body} More: {url}',
            'notice_sms_body' => '{title}: {body}',
        ],
    ], $overrides);
}

test('super admins can view defaults and save school, grading, fee and notice settings', function () {
    $admin = createSchoolSettingsAdmin();
    config([
        'services.twilio.account_sid' => null,
        'services.twilio.auth_token' => null,
        'services.twilio.from_number' => null,
        'services.twilio.messaging_service_sid' => null,
    ]);

    $this->actingAs($admin)->get(route('settings.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Settings/Index')
            ->where('configured', false)
            ->where('settings.grade_settings.bands.0.label', 'A')
            ->where('settings.fee_settings.currency_code', 'USD')
            ->where('settings.fee_settings.default_due_days', 30)
            ->where('settings.notification_settings.database_notices', true)
            ->where('settings.notification_settings.email_notices', true)
            ->where('settings.notification_settings.sms_notices', false));

    $this->actingAs($admin)->put(route('settings.school.update'), schoolProfilePayload())
        ->assertRedirect(route('settings.index'))
        ->assertSessionHas('status', 'School profile saved.');

    $this->actingAs($admin)->get(route('settings.notifications'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Settings/Notifications')
            ->where('smsGatewayConfigured', false)
            ->where('settings.notification_settings.notice_email_subject', '{title}'));

    $this->actingAs($admin)->put(route('settings.grading.update'), gradingSettingsPayload())
        ->assertRedirect(route('settings.index'))
        ->assertSessionHas('status', 'Grading and attendance settings saved.');

    $this->actingAs($admin)->put(route('settings.fees.update'), [
        'fee_settings' => ['currency_code' => 'EUR', 'default_due_days' => 14],
    ])->assertRedirect(route('settings.index'))
        ->assertSessionHas('status', 'Fee defaults saved.');

    $this->actingAs($admin)->put(route('settings.notifications.update'), notificationSettingsPayload())
        ->assertRedirect(route('settings.index'))
        ->assertSessionHas('status', 'Notification settings saved.');

    $settings = SchoolSetting::query()->firstOrFail();
    expect($settings->school_name)->toBe('Northstar School')
        ->and($settings->grade_settings['bands'][0]['label'])->toBe('A')
        ->and($settings->fee_settings)->toBe(['currency_code' => 'EUR', 'default_due_days' => 14])
        ->and($settings->notification_settings)->toBe(notificationSettingsPayload()['notification_settings']);

    $this->actingAs($admin)->get('/dashboard')
        ->assertInertia(fn ($page) => $page
            ->where('feeSettings.currency_code', 'EUR')
            ->where('feeSettings.default_due_days', 14));
});

test('grading validation rejects unordered bands and the school profile rejects invalid logo uploads', function () {
    $admin = createSchoolSettingsAdmin();
    SchoolSetting::query()->create([
        'singleton_key' => 'primary',
        'school_name' => 'Northstar School',
    ]);

    $this->actingAs($admin)->put(route('settings.grading.update'), [
        'attendance_threshold' => 82.5,
        'grade_settings' => ['bands' => [
            ['label' => 'F', 'minimum_percentage' => 0],
            ['label' => 'A', 'minimum_percentage' => 80],
        ]],
    ])->assertSessionHasErrors('grade_settings.bands');

    $this->actingAs($admin)->put(route('settings.school.update'), [
        ...schoolProfilePayload(),
        'logo' => UploadedFile::fake()->create('not-an-image.txt', 10, 'text/plain'),
    ])->assertSessionHasErrors('logo');

    expect(SchoolSetting::query()->firstOrFail()->logo_path)->toBeNull();
});

test('school logo uploads replace and remove the previously stored public image', function () {
    Storage::fake('public');
    $admin = createSchoolSettingsAdmin();

    $this->actingAs($admin)->put(route('settings.school.update'), [
        ...schoolProfilePayload(),
        'logo' => UploadedFile::fake()->image('school-logo.png', 300, 200),
    ])->assertRedirect(route('settings.index'));

    $firstLogoPath = SchoolSetting::query()->firstOrFail()->logo_path;
    Storage::disk('public')->assertExists($firstLogoPath);

    $this->actingAs($admin)->put(route('settings.school.update'), [
        ...schoolProfilePayload(),
        'logo' => UploadedFile::fake()->image('replacement-logo.png', 400, 250),
    ])->assertRedirect(route('settings.index'));

    $replacementLogoPath = SchoolSetting::query()->firstOrFail()->logo_path;
    expect($replacementLogoPath)->not->toBe($firstLogoPath);
    Storage::disk('public')->assertMissing($firstLogoPath);
    Storage::disk('public')->assertExists($replacementLogoPath);

    $this->actingAs($admin)->put(route('settings.school.update'), [
        ...schoolProfilePayload(),
        'remove_logo' => true,
    ])->assertRedirect(route('settings.index'));

    expect(SchoolSetting::query()->firstOrFail()->logo_path)->toBeNull();
    Storage::disk('public')->assertMissing($replacementLogoPath);
});

test('SMS notice settings require a configured gateway and supported template placeholders', function () {
    $admin = createSchoolSettingsAdmin();
    $settings = SchoolSetting::query()->create([
        'singleton_key' => 'primary',
        'school_name' => 'Northstar School',
        'notification_settings' => ['sms_notices' => false],
    ]);
    $payload = notificationSettingsPayload([
        'notification_settings' => [
            ...notificationSettingsPayload()['notification_settings'],
            'sms_notices' => true,
        ],
    ]);

    config([
        'services.twilio.account_sid' => null,
        'services.twilio.auth_token' => null,
        'services.twilio.from_number' => null,
        'services.twilio.messaging_service_sid' => null,
    ]);

    $this->actingAs($admin)->put(route('settings.notifications.update'), $payload)
        ->assertSessionHasErrors('notification_settings.sms_notices');

    expect($settings->fresh()->notification_settings['sms_notices'])->toBeFalse();

    config([
        'services.twilio.account_sid' => 'ACaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa',
        'services.twilio.auth_token' => 'test-auth-token',
        'services.twilio.from_number' => '+15005550006',
        'services.twilio.messaging_service_sid' => null,
    ]);

    $this->actingAs($admin)->get(route('settings.notifications'))
        ->assertInertia(fn ($page) => $page->where('smsGatewayConfigured', true));

    config(['services.twilio.messaging_service_sid' => 'MGbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb']);
    $this->actingAs($admin)->put(route('settings.notifications.update'), $payload)
        ->assertSessionHasErrors('notification_settings.sms_notices');

    config(['services.twilio.messaging_service_sid' => null]);
    $payload['notification_settings']['notice_sms_body'] = '{unsupported}';

    $this->actingAs($admin)->put(route('settings.notifications.update'), $payload)
        ->assertSessionHasErrors('notification_settings.notice_sms_body');

    $payload['notification_settings']['notice_sms_body'] = '{title}: {body}';

    $this->actingAs($admin)->put(route('settings.notifications.update'), $payload)
        ->assertRedirect(route('settings.index'));

    expect($settings->fresh()->notification_settings['sms_notices'])->toBeTrue();
});

test('users without settings permissions cannot view or update school settings', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get(route('settings.index'))->assertForbidden();
    $this->actingAs($user)->put(route('settings.school.update'), schoolProfilePayload())->assertForbidden();
    $this->actingAs($user)->put(route('settings.notifications.update'), notificationSettingsPayload())->assertForbidden();

    expect(SchoolSetting::query()->count())->toBe(0);
});
