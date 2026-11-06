<?php

use App\Jobs\DispatchNoticeNotifications;
use App\Models\Guardian;
use App\Models\Notice;
use App\Models\SchoolSetting;
use App\Models\Staff;
use App\Models\Student;
use App\Models\User;
use App\Notifications\Channels\SmsChannel;
use App\Notifications\NoticePublishedNotification;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
});

function createNoticeManager(string $role = 'Principal'): User
{
    $manager = User::factory()->create();
    $manager->assignRole($role);

    return $manager;
}

test('principals can save a notice draft and publish it for queued delivery', function () {
    $principal = createNoticeManager();

    $this->actingAs($principal)->post(route('notices.store'), [
        'title' => 'School closes early',
        'body' => 'Classes end at noon on Friday.',
        'audience' => 'parents',
        'expires_at' => today()->addWeek()->toDateString(),
    ])->assertRedirect()
        ->assertSessionHas('status', 'Notice saved as a draft.');

    $notice = Notice::query()->firstOrFail();
    expect($notice->status)->toBe('draft')
        ->and($notice->audience)->toBe('parents')
        ->and($notice->published_at)->toBeNull();

    Bus::fake([DispatchNoticeNotifications::class]);

    $this->actingAs($principal)->post(route('notices.publish', $notice))
        ->assertRedirect()
        ->assertSessionHas('status', 'Notice published and queued for delivery.');

    expect($notice->fresh()->status)->toBe('published')
        ->and($notice->fresh()->published_at)->not->toBeNull();
    Bus::assertDispatched(
        DispatchNoticeNotifications::class,
        fn (DispatchNoticeNotifications $job): bool => $job->noticeId === $notice->id,
    );
});

test('students and parents only see published notices intended for their audience', function () {
    $student = User::factory()->create();
    $student->assignRole('Student');
    $parent = User::factory()->create();
    $parent->assignRole('Parent');

    Notice::factory()->published()->create(['title' => 'Everyone', 'audience' => 'all']);
    Notice::factory()->published()->create(['title' => 'Students only', 'audience' => 'students']);
    Notice::factory()->published()->create(['title' => 'Parents only', 'audience' => 'parents']);
    Notice::factory()->create(['title' => 'Unpublished', 'audience' => 'all']);
    Notice::factory()->published()->create([
        'title' => 'Expired',
        'audience' => 'all',
        'expires_at' => today()->subDay()->toDateString(),
    ]);

    $this->actingAs($student)->get(route('notices.index'))
        ->assertInertia(fn ($page) => $page
            ->component('Notices/Index')
            ->where('notices.data', fn ($notices): bool => collect($notices)->pluck('title')->all() === [
                'Students only',
                'Everyone',
            ]));

    $this->actingAs($parent)->get(route('notices.index'))
        ->assertInertia(fn ($page) => $page
            ->component('Notices/Index')
            ->where('notices.data', fn ($notices): bool => collect($notices)->pluck('title')->all() === [
                'Parents only',
                'Everyone',
            ]));
});

test('teachers see staff notices while unpublished drafts remain restricted to publishers', function () {
    $teacher = User::factory()->create();
    $teacher->assignRole('Teacher');
    $principal = createNoticeManager();

    Notice::factory()->published()->create(['title' => 'Staff update', 'audience' => 'staff']);
    Notice::factory()->published()->create(['title' => 'Teacher update', 'audience' => 'teachers']);
    Notice::factory()->published()->create(['title' => 'Parent update', 'audience' => 'parents']);
    Notice::factory()->create(['title' => 'Draft', 'audience' => 'all']);

    $this->actingAs($teacher)->get(route('notices.index'))
        ->assertInertia(fn ($page) => $page
            ->where('notices.data', fn ($notices): bool => collect($notices)->pluck('title')->all() === [
                'Teacher update',
                'Staff update',
            ]));

    $this->actingAs($principal)->get(route('notices.index'))
        ->assertInertia(fn ($page) => $page
            ->where('notices.data', fn ($notices): bool => collect($notices)->pluck('title')->contains('Draft')));
});

test('queued notice delivery reaches only users in the selected audience', function () {
    $parent = User::factory()->create();
    $parent->assignRole('Parent');
    $student = User::factory()->create();
    $student->assignRole('Student');
    $notice = Notice::factory()->published()->create(['audience' => 'parents']);

    Notification::fake([NoticePublishedNotification::class]);
    (new DispatchNoticeNotifications($notice->id))->handle();

    Notification::assertSentTo($parent, NoticePublishedNotification::class);
    Notification::assertNotSentTo($student, NoticePublishedNotification::class);
});

test('queued notice delivery follows configured channels and skips disabled delivery', function () {
    $parent = User::factory()->create();
    $parent->assignRole('Parent');
    $notice = Notice::factory()->published()->create(['audience' => 'parents']);
    SchoolSetting::query()->create([
        'singleton_key' => 'primary',
        'school_name' => 'Settings Test School',
        'grade_settings' => ['bands' => [['label' => 'A', 'minimum_percentage' => 0]]],
        'fee_settings' => ['currency_code' => 'USD', 'default_due_days' => 30],
        'notification_settings' => ['database_notices' => true, 'email_notices' => false],
    ]);

    Notification::fake([NoticePublishedNotification::class]);
    (new DispatchNoticeNotifications($notice->id))->handle();

    Notification::assertSentTo(
        $parent,
        NoticePublishedNotification::class,
        fn (NoticePublishedNotification $notification): bool => $notification->via($parent) === ['database'],
    );

    SchoolSetting::query()->firstOrFail()->update([
        'notification_settings' => ['database_notices' => false, 'email_notices' => true],
    ]);
    $emailNotice = Notice::factory()->published()->create(['audience' => 'parents']);
    (new DispatchNoticeNotifications($emailNotice->id))->handle();

    Notification::assertSentTo(
        $parent,
        NoticePublishedNotification::class,
        fn (NoticePublishedNotification $notification): bool => $notification->via($parent) === ['mail'],
    );
    Notification::assertSentToTimes($parent, NoticePublishedNotification::class, 2);

    SchoolSetting::query()->firstOrFail()->update([
        'notification_settings' => [
            'database_notices' => false,
            'email_notices' => false,
            'sms_notices' => true,
            'notice_sms_body' => '{title}: {body}',
        ],
    ]);
    $smsNotice = Notice::factory()->published()->create([
        'audience' => 'parents',
        'title' => 'Parent meeting',
        'body' => 'Thursday at 5 PM.',
    ]);
    (new DispatchNoticeNotifications($smsNotice->id))->handle();

    Notification::assertSentTo(
        $parent,
        NoticePublishedNotification::class,
        fn (NoticePublishedNotification $notification): bool => $notification->via($parent) === [SmsChannel::class]
            && $notification->toSms($parent) === 'Parent meeting: Thursday at 5 PM.',
    );
    Notification::assertSentToTimes($parent, NoticePublishedNotification::class, 3);

    SchoolSetting::query()->firstOrFail()->update([
        'notification_settings' => ['database_notices' => false, 'email_notices' => false],
    ]);
    $disabledNotice = Notice::factory()->published()->create(['audience' => 'parents']);
    (new DispatchNoticeNotifications($disabledNotice->id))->handle();

    Notification::assertSentToTimes($parent, NoticePublishedNotification::class, 3);
});

test('users can mark only their own database notification as read', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    $notice = Notice::factory()->published()->create();
    $notification = new NoticePublishedNotification($notice);
    Mail::fake();

    Notification::sendNow($user, $notification);
    Notification::sendNow($otherUser, $notification);
    $userNotification = $user->notifications()->firstOrFail();
    $otherNotification = $otherUser->notifications()->firstOrFail();

    $this->actingAs($user)->post(route('notifications.read', $otherNotification->id))
        ->assertNotFound();
    $this->actingAs($user)->post(route('notifications.read', $userNotification->id))
        ->assertRedirect()
        ->assertSessionHas('status', 'Notification marked as read.');

    expect($userNotification->fresh()->read_at)->not->toBeNull()
        ->and($otherNotification->fresh()->read_at)->toBeNull();
});

test('notice mail escapes user supplied message content', function () {
    $user = User::factory()->make();
    $notice = Notice::factory()->create([
        'title' => 'School update',
        'body' => "<script>alert('unsafe')</script>",
    ]);
    $notification = new NoticePublishedNotification($notice, ['mail']);

    $html = $notification->toMail($user)->render()->toHtml();

    expect($html)
        ->toContain('&lt;script&gt;')
        ->not->toContain("<script>alert('unsafe')</script>");
});

test('notice email uses the configured subject and body placeholders', function () {
    $user = User::factory()->make();
    $notice = Notice::factory()->create([
        'title' => 'School update',
        'body' => 'Meeting on Tuesday.',
        'audience' => 'parents',
    ]);
    $notification = new NoticePublishedNotification($notice, ['mail'], templates: [
        'notice_email_subject' => 'Reminder: {title}',
        'notice_email_body' => '{body} Audience: {audience}. {url}',
    ]);

    $mail = $notification->toMail($user);
    $html = $mail->render()->toHtml();

    expect($mail->subject)->toBe('Reminder: School update')
        ->and($html)->toContain('Meeting on Tuesday.')
        ->toContain('Audience: parents')
        ->toContain(route('notices.show', $notice));
});

test('notice SMS uses the editable template and sends to a student linked guardian', function () {
    config([
        'services.twilio.account_sid' => 'ACaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa',
        'services.twilio.auth_token' => 'test-auth-token',
        'services.twilio.from_number' => '+15005550006',
        'services.twilio.messaging_service_sid' => null,
    ]);

    $studentAccount = User::factory()->create();
    $studentAccount->assignRole('Student');
    $student = Student::query()->create([
        'user_id' => $studentAccount->id,
        'admission_number' => 'SMS-STUDENT-1',
        'first_name' => 'Sam',
        'last_name' => 'Student',
        'date_of_birth' => '2012-01-01',
        'gender' => 'female',
        'admission_date' => today()->toDateString(),
    ]);
    $guardian = Guardian::query()->create([
        'name' => 'Sam Guardian',
        'phone' => '+233201234567',
    ]);
    $student->guardians()->attach($guardian, [
        'relationship' => 'parent',
        'is_primary' => true,
        'lives_with_student' => true,
    ]);
    $notice = Notice::factory()->published()->create([
        'title' => 'Exam timetable',
        'body' => 'The timetable is ready.',
        'audience' => 'students',
    ]);

    Http::preventStrayRequests();
    Http::fake([
        'api.twilio.com/2010-04-01/Accounts/ACaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa/Messages.json' => Http::response(['sid' => 'SMaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa'], 201),
    ]);

    Notification::sendNow($studentAccount, new NoticePublishedNotification(
        $notice,
        [SmsChannel::class],
        templates: ['notice_sms_body' => '{title}: {body} ({audience}) {url}'],
    ));

    Http::assertSent(fn (Request $request): bool => $request->method() === 'POST'
        && $request->url() === 'https://api.twilio.com/2010-04-01/Accounts/ACaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa/Messages.json'
        && $request->hasHeader('Authorization', 'Basic '.base64_encode(config('services.twilio.account_sid').':'.config('services.twilio.auth_token')))
        && ! $request->hasHeader('Idempotency-Key')
        && $request->data() === [
            'To' => '+233201234567',
            'Body' => 'Exam timetable: The timetable is ready. (students) '.route('notices.show', $notice),
            'From' => '+15005550006',
        ]);
});

test('notice SMS can send through a Twilio messaging service', function () {
    config([
        'services.twilio.account_sid' => 'ACaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa',
        'services.twilio.auth_token' => 'test-auth-token',
        'services.twilio.from_number' => null,
        'services.twilio.messaging_service_sid' => 'MGbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb',
    ]);

    $user = User::factory()->create();
    $user->assignRole('Teacher');
    Staff::query()->create([
        'user_id' => $user->id,
        'staff_number' => 'SMS-STAFF-MSG',
        'name' => $user->name,
        'phone' => '+15550101010',
        'is_teacher' => true,
        'status' => 'active',
    ]);
    $notice = Notice::factory()->published()->create();

    Http::preventStrayRequests();
    Http::fake([
        'api.twilio.com/2010-04-01/Accounts/ACaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa/Messages.json' => Http::response(['sid' => 'SMaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa'], 201),
    ]);

    Notification::sendNow($user, new NoticePublishedNotification($notice, [SmsChannel::class]));

    Http::assertSent(fn (Request $request): bool => $request->data() === [
        'To' => '+15550101010',
        'Body' => $notice->title.': '.$notice->body.' '.route('notices.show', $notice),
        'MessagingServiceSid' => 'MGbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb',
    ]);
});

test('notice SMS delivery surfaces Twilio errors for queue retry', function () {
    config([
        'services.twilio.account_sid' => 'ACaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa',
        'services.twilio.auth_token' => 'test-auth-token',
        'services.twilio.from_number' => '+15005550006',
        'services.twilio.messaging_service_sid' => null,
    ]);
    $user = User::factory()->create();
    $user->assignRole('Teacher');
    Staff::query()->create([
        'user_id' => $user->id,
        'staff_number' => 'SMS-STAFF-1',
        'name' => $user->name,
        'phone' => '+15550101010',
        'is_teacher' => true,
        'status' => 'active',
    ]);
    $notice = Notice::factory()->published()->create();
    Http::preventStrayRequests();
    Http::fake([
        'api.twilio.com/2010-04-01/Accounts/ACaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa/Messages.json' => Http::response(['message' => 'unavailable'], 503),
    ]);

    expect(fn () => Notification::sendNow(
        $user,
        new NoticePublishedNotification($notice, [SmsChannel::class]),
    ))->toThrow(RequestException::class);
});

test('guests and users without notice permissions cannot access or create notices', function () {
    $user = User::factory()->create();

    $this->get(route('notices.index'))->assertRedirect(route('login'));
    $this->actingAs($user)->get(route('notices.index'))->assertForbidden();
    $this->actingAs($user)->post(route('notices.store'), [
        'title' => 'Private announcement',
        'body' => 'This should not be created.',
        'audience' => 'all',
    ])->assertForbidden();

    $this->assertDatabaseCount('notices', 0);
});

test('notice forms reject missing content and unsupported audience values', function () {
    $principal = createNoticeManager();

    $this->actingAs($principal)->post(route('notices.store'), [])
        ->assertSessionHasErrors(['title', 'body', 'audience']);

    $this->actingAs($principal)->post(route('notices.store'), [
        'title' => 'Invalid audience',
        'body' => 'This audience does not exist.',
        'audience' => 'everyone-in-the-world',
    ])->assertSessionHasErrors('audience');

    $this->assertDatabaseCount('notices', 0);
});
