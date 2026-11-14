<?php

namespace App\Models;

use Database\Factories\NoticeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['created_by', 'title', 'body', 'audience', 'status', 'published_at', 'expires_at'])]
class Notice extends Model
{
    /** @use HasFactory<NoticeFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'published_at' => 'datetime',
            'expires_at' => 'date',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if ($user->can('notices.publish')) {
            return $query;
        }

        $audiences = ['all'];
        if ($user->hasRole('Student')) {
            $audiences[] = 'students';
        } elseif ($user->hasRole('Parent')) {
            $audiences[] = 'parents';
        } else {
            $audiences[] = 'staff';
            if ($user->hasRole('Teacher')) {
                $audiences[] = 'teachers';
            }
        }

        return $query->where(function (Builder $visible) use ($user, $audiences): void {
            $visible->where(fn (Builder $published) => $published
                ->where('status', 'published')
                ->whereIn('audience', $audiences));

            if ($user->can('notices.create')) {
                $visible->orWhere(fn (Builder $ownDrafts) => $ownDrafts
                    ->where('status', 'draft')
                    ->where('created_by', $user->id));
            }
        });
    }
}
