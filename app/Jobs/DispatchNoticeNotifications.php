<?php

namespace App\Jobs;

use App\Models\Notice;
use App\Models\User;
use App\Notifications\NoticePublishedNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Queue\Queueable;

class DispatchNoticeNotifications implements ShouldQueue
{
    use Queueable;

    public function __construct(public int $noticeId) {}

    public function handle(): void
    {
        $notice = Notice::query()->findOrFail($this->noticeId);
        $channels = NoticePublishedNotification::channelsFromSettings();

        if ($channels === []) {
            return;
        }

        $recipients = User::query();

        if ($notice->audience !== 'all') {
            $roleNames = match ($notice->audience) {
                'staff' => ['Super Admin', 'Principal', 'Teacher', 'Accountant', 'Librarian', 'Receptionist'],
                'teachers' => ['Principal', 'Teacher'],
                'students' => ['Student'],
                'parents' => ['Parent'],
                default => [],
            };

            $recipients->whereHas('roles', fn (Builder $query) => $query->whereIn('name', $roleNames));
        }

        $recipients->chunkById(200, function ($users) use ($notice, $channels): void {
            foreach ($users as $user) {
                // ⬇ was: new NoticePublishedNotification($notice->id, $notice->title, $notice->body, $channels)
                $user->notify(new NoticePublishedNotification($notice, $channels));
            }
        });
    }
}