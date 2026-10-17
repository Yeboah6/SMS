<?php

use App\Jobs\DispatchNoticeNotifications;
use App\Models\Notice;
use App\Models\SchoolSetting;
use App\Models\User;
use App\Notifications\NoticePublishedNotification;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
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
        'notification_settings' => ['database_notices' => false, 'email_notices' => false],
    ]);
    $disabledNotice = Notice::factory()->published()->create(['audience' => 'parents']);
    (new DispatchNoticeNotifications($disabledNotice->id))->handle();

    Notification::assertSentToTimes($parent, NoticePublishedNotification::class, 2);
});

test('users can mark only their own database notification as read', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    $notice = Notice::factory()->published()->create();
    $notification = new NoticePublishedNotification($notice->id, $notice->title, $notice->body);
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
    $notification = new NoticePublishedNotification(
        1,
        'School update',
        "<script>alert('unsafe')</script>",
    );

    $html = $notification->toMail($user)->render()->toHtml();

    expect($html)
        ->toContain('&lt;script&gt;')
        ->not->toContain("<script>alert('unsafe')</script>");
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
