<?php

namespace App\Http\Middleware;

use App\Models\AcademicYear;
use App\Models\SchoolSetting;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'appName' => config('app.name'),
            'activeAcademicYear' => AcademicYear::active()->first(['id', 'name'])?->only(['id', 'name']),
            'schoolSettings' => SchoolSetting::query()
                ->where('singleton_key', 'primary')
                ->first(['school_name', 'logo_path'])
                ?->only(['school_name', 'logo_path']),
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
