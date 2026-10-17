<?php

namespace App\Services;

use App\Jobs\DispatchNoticeNotifications;
use App\Models\Notice;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class NoticeService
{
    /**
     * @param  array{title: string, body: string, audience: string, expires_at?: ?string}  $data
     */
    public function createDraft(array $data, User $actor): Notice
    {
        if (! $actor->can('notices.create')) {
            throw new AuthorizationException('You are not allowed to create school notices.');
        }

        return Notice::query()->create([
            'created_by' => $actor->id,
            'title' => $data['title'],
            'body' => $data['body'],
            'audience' => $data['audience'],
            'status' => 'draft',
            'expires_at' => $data['expires_at'] ?? null,
        ]);
    }

    public function updateDraft(Notice $notice, array $data, User $actor): Notice
    {
        $this->authorizeDraftChange($notice, $actor);
    
        return DB::transaction(function () use ($notice, $data): Notice {
            $locked = Notice::query()->lockForUpdate()->findOrFail($notice->id);
    
            if ($locked->status !== 'draft') {
                throw ValidationException::withMessages(['notice' => 'Only draft notices can be edited.']);
            }
    
            $locked->update([
                'title' => $data['title'],
                'body' => $data['body'],
                'audience' => $data['audience'],
                'expires_at' => $data['expires_at'] ?? null,
            ]);
    
            return $locked->refresh();
        });
    }

    public function publish(Notice $notice, User $actor): Notice
    {
        if (! $actor->can('notices.publish')) {
            throw new AuthorizationException('You are not allowed to publish school notices.');
        }

        return DB::transaction(function () use ($notice): Notice {
            $lockedNotice = Notice::query()->lockForUpdate()->findOrFail($notice->id);
            if ($lockedNotice->status !== 'draft') {
                throw ValidationException::withMessages([
                    'notice' => 'Only draft notices can be published.',
                ]);
            }

            if ($lockedNotice->expires_at?->isBefore(today())) {
                throw ValidationException::withMessages([
                    'expires_at' => 'An expired notice cannot be published.',
                ]);
            }

            $lockedNotice->update([
                'status' => 'published',
                'published_at' => now(),
            ]);

            return $lockedNotice->refresh();
        });
    }

    public function dispatchNotifications(Notice $notice): void
    {
        DispatchNoticeNotifications::dispatch($notice->id);
    }

    public function deleteDraft(Notice $notice, User $actor): void
    {
        $this->authorizeDraftChange($notice, $actor);
    
        DB::transaction(function () use ($notice): void {
            $locked = Notice::query()->lockForUpdate()->findOrFail($notice->id);
    
            if ($locked->status !== 'draft') {
                throw ValidationException::withMessages([
                    'notice' => 'Published notices cannot be deleted. Withdraw the notice to hide it from the board.',
                ]);
            }
    
            $locked->delete();
        });
    }

    public function withdraw(Notice $notice, User $actor): Notice
    {
        if (! $actor->can('notices.publish')) {
            throw new AuthorizationException('You are not allowed to withdraw school notices.');
        }
    
        return DB::transaction(function () use ($notice): Notice {
            $locked = Notice::query()->lockForUpdate()->findOrFail($notice->id);
    
            if ($locked->status !== 'published') {
                throw ValidationException::withMessages(['notice' => 'Only published notices can be withdrawn.']);
            }
            if ($locked->expires_at?->isBefore(today())) {
                throw ValidationException::withMessages(['notice' => 'This notice has already expired.']);
            }
    
            $locked->update(['expires_at' => today()->subDay()]);
    
            return $locked->refresh();
        });
    }

    private function authorizeDraftChange(Notice $notice, User $actor): void
    {
        $isAuthor = $actor->can('notices.create') && $notice->created_by === $actor->id;
    
        if (! $isAuthor && ! $actor->can('notices.publish')) {
            throw new AuthorizationException('You are not allowed to change this notice.');
        }
    }
}
