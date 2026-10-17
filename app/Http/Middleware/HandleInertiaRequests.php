<?php

namespace App\Http\Middleware;

use App\Models\AcademicYear;
use App\Models\SchoolSetting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function share(Request $request): array
    {
        $schoolSettings = SchoolSetting::query()
            ->where('singleton_key', 'primary')
            ->first(['school_name', 'logo_path', 'fee_settings']);

        return [
            ...parent::share($request),
            'appName' => config('app.name'),
            'activeAcademicYear' => AcademicYear::active()->first(['id', 'name'])?->only(['id', 'name']),
            'schoolSettings' => $schoolSettings?->only(['school_name', 'logo_path']),
            'schoolLogoUrl' => $schoolSettings?->logo_path
                ? Storage::disk('public')->url($schoolSettings->logo_path)
                : null,
            'feeSettings' => array_replace(
                ['currency_code' => 'USD', 'default_due_days' => 30],
                $schoolSettings?->fee_settings ?? [],
            ),
            'auth' => [
                'user' => $request->user()
                    ? [
                        'id' => $request->user()->id,
                        'name' => $request->user()->name,
                        'email' => $request->user()->email,
                        'roles' => $request->user()->getRoleNames(),
                        'permissions' => $request->user()->getAllPermissions()->pluck('name'),
                    ]
                    : null,
            ],
            'status' => $request->session()->get('status'),
        ];
    }
}
