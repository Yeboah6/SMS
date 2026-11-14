<?php

namespace App\Http\Middleware;

use App\Models\AcademicYear;
use App\Models\Notice;
use App\Models\SchoolSetting;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function share(Request $request): array
    {
        $user = $request->user();
        $schoolSettings = SchoolSetting::query()
            ->where('singleton_key', 'primary')
            ->first(['school_name', 'logo_path', 'fee_settings']);
        $recentNotices = null;

        if ($user && (
            $user->can('notices.publish')
            || $user->can('notices.create')
            || $user->can('notices.view')
            || $user->can('portal.notices.view')
        )) {
            $recentNotices = fn (): array => Notice::query()
                ->visibleTo($user)
                ->where('status', 'published')
                ->where(fn (Builder $query) => $query
                    ->whereNull('expires_at')
                    ->orWhereDate('expires_at', '>=', today()->toDateString()))
                ->latest('published_at')
                ->latest('id')
                ->limit(5)
                ->get(['id', 'title', 'body', 'published_at'])
                ->map(fn (Notice $notice): array => [
                    'id' => $notice->id,
                    'title' => $notice->title,
                    'excerpt' => Str::limit($notice->body, 160),
                    'publishedAt' => $notice->published_at?->toIso8601String(),
                ])
                ->all();
        }

        return [
            ...parent::share($request),
            'appName' => config('app.name'),
            'activeAcademicYear' => AcademicYear::active()->first(['id', 'name'])?->only(['id', 'name']),
            'schoolSettings' => $schoolSettings?->only(['school_name', 'logo_path']),
            'schoolLogoUrl' => $schoolSettings?->logo_path
                ? Storage::disk('public')->url($schoolSettings->logo_path)
                : null,
            'feeSettings' => array_replace(
                ['currency_code' => 'GHS', 'default_due_days' => 30],
                $schoolSettings?->fee_settings ?? [],
            ),
            'recentNotices' => $recentNotices,
            'auth' => [
                'user' => $user
                    ? [
                        'id' => $user->id,
                        'name' => $user->name,
                        'email' => $user->email,
                        'roles' => $user->getRoleNames(),
                        'permissions' => $user->getAllPermissions()->pluck('name'),
                    ]
                    : null,
            ],
            'status' => $request->session()->get('status'),
        ];
    }
}
